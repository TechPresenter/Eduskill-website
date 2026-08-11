<?php
/**
 * =============================================================================
 *  Donation domain layer (5.9)
 * =============================================================================
 *  Receipts (80G), payment completion (+ campaign recompute + receipt email),
 *  refunds with audit trail, the annual 80G tax certificate, recurring-donation
 *  helpers, and a settings-driven gateway registry. Loaded by bootstrap.php.
 *  The heavy gateway adapters (curl) are required on demand by the donate flow
 *  and webhook handlers, not here.
 * =============================================================================
 */

declare(strict_types=1);

/* =============================================================================
 |  RECEIPTS + COMPLETION
 |============================================================================*/

function donation_receipt_no(int $id): string
{
    $p = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) get_setting('donation_receipt_prefix', 'DN'))) ?: 'DN';
    return sprintf('%s-%s-%05d', $p, date('Y'), $id);
}

/** Stable server secret for receipt links (generated once). */
function donation_secret(): string
{
    $s = (string) get_setting('donation_secret', '');
    if ($s === '') {
        $s = bin2hex(random_bytes(24));
        set_setting('donation_secret', $s, 'donation', 'text');
    }
    return $s;
}

/** Unguessable token gating public access to a donation receipt. */
function donation_receipt_token(int $donationId): string
{
    return substr(hash_hmac('sha256', 'receipt|' . $donationId, donation_secret()), 0, 24);
}

/**
 * Mark a donation paid: set completed + gateway ids + receipt no, recompute the
 * campaign, and email the receipt. Idempotent (no-op if already completed).
 * $opts: gateway, order_id, payment_id, method.
 */
function donation_mark_paid(int $donationId, array $opts = []): bool
{
    $d = find('donations', $donationId);
    if (!$d) {
        return false;
    }
    // Idempotent, and a redelivered/duplicate webhook must never resurrect a
    // donation that was already completed or subsequently refunded.
    if (in_array($d['status'], ['completed', 'refunded'], true)) {
        return true;
    }
    $data = [
        'status'     => 'completed',
        'donated_at' => date('Y-m-d H:i:s'),
    ];
    if (!empty($opts['gateway']))  { $data['gateway']            = $opts['gateway']; }
    if (!empty($opts['method']))   { $data['payment_method']     = $opts['method']; }
    if (!empty($opts['order_id'])) { $data['gateway_order_id']   = $opts['order_id']; }
    if (!empty($opts['payment_id'])) {
        $data['gateway_payment_id'] = $opts['payment_id'];
        if (empty($d['transaction_id'])) { $data['transaction_id'] = $opts['payment_id']; }
    }
    if (empty($d['receipt_no'])) {
        $data['receipt_no'] = donation_receipt_no($donationId);
    }
    db_update('donations', $data, 'id = :id', [':id' => $donationId]);

    if (!empty($d['campaign_id'])) {
        campaign_recompute((int) $d['campaign_id']);
    }
    donation_send_receipt($donationId);
    log_activity('donation_paid', 'donations', 'Donation #' . $donationId . ' completed (' . ($opts['gateway'] ?? 'manual') . ')');
    return true;
}

/** Email the donor their receipt (best effort). Returns true if sent. */
function donation_send_receipt(int $donationId): bool
{
    $d = find('donations', $donationId);
    if (!$d || empty($d['email']) || !is_email($d['email'])) {
        return false;
    }
    $link = abs_url('donation-receipt?id=' . $donationId . '&t=' . donation_receipt_token($donationId));
    $ok = send_mail($d['email'], 'Your donation receipt — ' . get_setting('site_name', SITE_NAME),
        '<p>Dear ' . e($d['donor_name']) . ',</p>'
        . '<p>Thank you for your generous donation of <strong>' . money($d['amount'], $d['currency'] === 'INR' ? '₹' : $d['currency'] . ' ', 2)
        . '</strong>. Your official receipt (No. ' . e($d['receipt_no']) . ') is ready.</p>'
        . '<p><a href="' . e($link) . '" style="display:inline-block;padding:11px 20px;background:#0B4E3D;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Download receipt</a></p>'
        . '<p>Donations to ' . e(get_setting('site_name', SITE_NAME)) . ' are eligible for tax exemption under Section 80G.</p>');
    if ($ok) {
        db_update('donations', ['receipt_sent' => 1], 'id = :id', [':id' => $donationId]);
    }
    return $ok;
}

/* =============================================================================
 |  REFUNDS
 |============================================================================*/

/**
 * Record a refund (audit row) and flip the donation to refunded, then recompute
 * the campaign. The actual gateway refund call is made by the caller (which has
 * the adapter loaded) and its id/status passed in.
 */
function donation_record_refund(int $donationId, float $amount, ?string $reason, ?string $gatewayRefundId, string $status = 'processed', ?int $adminId = null): ?int
{
    $d = find('donations', $donationId);
    if (!$d) {
        return null;
    }
    $refundId = db_insert('donation_refunds', [
        'donation_id'       => $donationId,
        'amount'            => $amount,
        'reason'            => $reason,
        'gateway'           => $d['gateway'],
        'gateway_refund_id' => $gatewayRefundId,
        'status'            => in_array($status, ['pending', 'processed', 'failed'], true) ? $status : 'processed',
        'created_by'        => $adminId ?? ($_SESSION['user']['id'] ?? null),
    ]);
    if ($status === 'processed') {
        // Accumulate across multiple partial refunds; only a full refund flips
        // the donation to 'refunded'. Partial refunds stay 'completed' so the
        // campaign keeps the net (amount − refunded) via campaign_recompute().
        $refundedTotal = round((float) ($d['refund_amount'] ?? 0) + $amount, 2);
        $isFull        = $refundedTotal + 0.001 >= (float) $d['amount'];
        db_update('donations', [
            'status'        => $isFull ? 'refunded' : 'completed',
            'refunded_at'   => date('Y-m-d H:i:s'),
            'refund_amount' => $refundedTotal,
        ], 'id = :id', [':id' => $donationId]);
        if (!empty($d['campaign_id'])) {
            campaign_recompute((int) $d['campaign_id']); // nets the refund out of the raised total
        }
    }
    log_activity('donation_refund', 'donations', 'Refund #' . $refundId . ' for donation #' . $donationId . ' (' . $status . ')');
    return $refundId;
}

/* =============================================================================
 |  GATEWAY REGISTRY (settings-driven — no adapter load)
 |============================================================================*/

/** Enabled + credentialed donation gateways: [key => label]. */
function donation_enabled_gateways(): array
{
    $g = [];
    if ((int) get_setting('razorpay_enabled', '0') === 1 && get_setting('razorpay_key_id', '') !== '') {
        $g['razorpay'] = 'Razorpay';
    }
    if ((int) get_setting('stripe_enabled', '0') === 1 && get_setting('stripe_secret', '') !== '') {
        $g['stripe'] = 'Stripe';
    }
    if ((int) get_setting('paypal_enabled', '0') === 1 && get_setting('paypal_client_id', '') !== '') {
        $g['paypal'] = 'PayPal';
    }
    if (function_exists('cashfree_enabled') && cashfree_enabled()) {
        $g['cashfree'] = 'Cashfree';
    }
    return $g;
}

/** Load a gateway adapter file on demand. */
function donation_load_gateway(string $gw): bool
{
    $file = __DIR__ . '/gateways/' . preg_replace('/[^a-z]/', '', $gw) . '.php';
    if (is_file($file)) {
        require_once $file;
        return true;
    }
    return false;
}

/* =============================================================================
 |  RECURRING
 |============================================================================*/

/** Next charge date for a frequency from a base date. */
function subscription_next_date(string $frequency, ?string $from = null): string
{
    $base = $from ?: date('Y-m-d');
    $add  = ['monthly' => '+1 month', 'quarterly' => '+3 months', 'annual' => '+1 year'][$frequency] ?? '+1 month';
    return date('Y-m-d', strtotime($base . ' ' . $add));
}

/* =============================================================================
 |  PILLS + LABELS
 |============================================================================*/

function donation_status_pill(string $s): string
{
    return ['completed' => 'pill-green', 'pending' => 'pill-amber', 'failed' => 'pill-red', 'refunded' => 'pill-blue'][$s] ?? 'pill-gray';
}

/** Currency symbol/prefix for money(). */
function donation_symbol(string $currency = 'INR'): string
{
    return ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'][$currency] ?? ($currency . ' ');
}

/* =============================================================================
 |  SHARED GATEWAY HTTP (used by the adapters in includes/gateways/)
 |============================================================================*/

/**
 * JSON/form HTTP request for gateway adapters. Returns [httpCode, decodedArray, rawBody].
 * $opts: headers (array), form (bool — send as x-www-form-urlencoded), basic ([user,pass]).
 */
function gw_http(string $method, string $url, $body = null, array $opts = []): array
{
    $headers = $opts['headers'] ?? [];
    $ch = curl_init($url);
    $curl = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 12,
    ];
    if ($body !== null) {
        if (!empty($opts['form'])) {
            $curl[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body) : $body;
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            $curl[CURLOPT_POSTFIELDS] = is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES) : $body;
            $headers[] = 'Content-Type: application/json';
        }
    }
    if (!empty($opts['basic'])) {
        $curl[CURLOPT_USERPWD] = $opts['basic'][0] . ':' . $opts['basic'][1];
    }
    $curl[CURLOPT_HTTPHEADER] = array_merge(['Accept: application/json'], $headers);
    curl_setopt_array($ch, $curl);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        error_log('[gateway] cURL: ' . $err);
        return [0, ['error' => $err], ''];
    }
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string) $resp, true);
    return [$code, is_array($json) ? $json : [], (string) $resp];
}
