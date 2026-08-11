<?php
/**
 * =============================================================================
 *  Unified Payments — one Cashfree checkout path for every workflow.
 * =============================================================================
 *  Wraps includes/payments.php (Cashfree Payment Links). Any module starts a
 *  payment with pay_start(); the hosted checkout returns to /pay-return which
 *  calls pay_reconcile() (also called by the webhook). On the first paid
 *  transition pay_fulfill() runs exactly once (enrol, confirm, redeem coupon,
 *  email receipt, notify admins). Server-side status is always re-verified via
 *  the Cashfree API — the redirect/webhook body is never trusted on its own.
 * =============================================================================
 */

declare(strict_types=1);
require_once __DIR__ . '/payments.php';

/** Human labels for the supported payment contexts. */
function pay_contexts(): array
{
    return [
        'course' => 'Course Fee', 'exam' => 'Exam Fee', 'quiz' => 'Quiz Fee',
        'membership' => 'Membership', 'donation' => 'Donation', 'campaign' => 'Campaign',
        'event' => 'Event Registration', 'certificate' => 'Certification', 'workshop' => 'Workshop',
        'resource' => 'Digital Resource', 'other' => 'Other',
    ];
}

/** A unique, human-scannable order id. */
function pay_generate_order_id(string $context): string
{
    $p = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $context) ?: 'ORD', 0, 3));
    return $p . date('ymd') . strtoupper(bin2hex(random_bytes(4)));
}

/** Fetch a payment by order id. */
function pay_get(string $orderId): ?array
{
    return db_row("SELECT * FROM payments WHERE order_id = :o LIMIT 1", [':o' => $orderId]) ?: null;
}

/** Update a payment's status (+ optional fields). */
function pay_mark(string $orderId, string $status, array $extra = []): void
{
    $data = array_merge(['status' => $status], $extra);
    db_update('payments', $data, 'order_id = :o', [':o' => $orderId]);
}

/**
 * Begin a payment. $a: context_type, context_id, member_id, name, email, phone,
 * amount, discount, coupon_code, purpose, meta[]. Returns ['ok','url','order_id','error'].
 */
function pay_start(array $a): array
{
    if (!cashfree_enabled()) {
        return ['ok' => false, 'error' => 'Online payments are not enabled yet.'];
    }
    $amount   = round((float) ($a['amount'] ?? 0), 2);
    $discount = round((float) ($a['discount'] ?? 0), 2);
    $net      = max(0, round($amount - $discount, 2));
    if ($net <= 0) {
        return ['ok' => false, 'error' => 'Invalid payment amount.'];
    }
    $context = $a['context_type'] ?? 'other';
    $orderId = pay_generate_order_id($context);

    db_insert('payments', [
        'order_id'       => $orderId,
        'context_type'   => $context,
        'context_id'     => $a['context_id'] ?? null,
        'member_id'      => $a['member_id'] ?? null,
        'customer_name'  => $a['name'] ?? null,
        'customer_email' => $a['email'] ?? null,
        'customer_phone' => $a['phone'] ?? null,
        'amount'         => $amount,
        'discount'       => $discount,
        'net_amount'     => $net,
        'coupon_code'    => $a['coupon_code'] ?? null,
        'gateway'        => 'cashfree',
        'status'         => 'created',
        'purpose'        => $a['purpose'] ?? (pay_contexts()[$context] ?? 'Payment'),
        'meta'           => isset($a['meta']) ? json_encode($a['meta'], JSON_UNESCAPED_SLASHES) : null,
    ]);

    $base = rtrim(APP_URL, '/');
    $r = cashfree_create_link([
        'linkId'        => $orderId,
        'amount'        => $net,
        'purpose'       => $a['purpose'] ?? (pay_contexts()[$context] ?? 'Payment'),
        'customerName'  => $a['name'] ?? '',
        'customerEmail' => $a['email'] ?? '',
        'customerPhone' => $a['phone'] ?? '',
        'returnUrl'     => $base . '/pay-return?order=' . $orderId,
        'notifyUrl'     => $base . '/api/v1/cashfree-webhook',
    ]);
    if (!$r['success']) {
        pay_mark($orderId, 'failed');
        return ['ok' => false, 'error' => $r['message'], 'order_id' => $orderId];
    }
    db_update('payments', ['gateway_link_id' => $r['link_id'], 'status' => 'pending'], 'order_id = :o', [':o' => $orderId]);
    return ['ok' => true, 'url' => $r['link_url'], 'order_id' => $orderId];
}

/**
 * Re-verify a payment against Cashfree and apply state transitions. Fulfilment
 * runs exactly once on the first paid transition. Returns the payment row.
 */
function pay_reconcile(string $orderId): ?array
{
    $p = pay_get($orderId);
    if (!$p || in_array($p['status'], ['paid', 'refunded'], true)) {
        return $p;
    }
    $st = cashfree_link_status($orderId);

    if ($st['paid']) {
        // Atomic guard: only the transition from non-paid → paid returns rows.
        $n = db_update('payments',
            ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'), 'net_amount' => $st['amount_paid'] > 0 ? $st['amount_paid'] : $p['net_amount']],
            "order_id = :o AND status <> 'paid'", [':o' => $orderId]);
        $p = pay_get($orderId);
        if ($n > 0 && $p) {
            $rn = pay_receipt_no($p);
            db_update('payments', ['receipt_no' => $rn], 'order_id = :o', [':o' => $orderId]);
            $p['receipt_no'] = $rn;
            pay_fulfill($p);
        }
    } elseif (in_array($st['status'], ['EXPIRED', 'CANCELLED'], true)) {
        pay_mark($orderId, $st['status'] === 'EXPIRED' ? 'expired' : 'cancelled');
        $p = pay_get($orderId);
    }
    return $p;
}

/** Generate a receipt number for a paid payment. */
function pay_receipt_no(array $p): string
{
    $prefix = strtoupper((string) get_setting('payment_receipt_prefix', 'EIF'));
    return $prefix . date('Ymd') . '-' . str_pad((string) $p['id'], 5, '0', STR_PAD_LEFT);
}

/** Run once when a payment first becomes paid: fulfil the order + side effects. */
function pay_fulfill(array $p): void
{
    try {
        // Consume a coupon, if one was applied.
        if (!empty($p['coupon_code']) && (float) $p['discount'] > 0 && function_exists('coupon_find')) {
            $c = coupon_find((string) $p['coupon_code']);
            if ($c) {
                $ctx = in_array($p['context_type'], ['course', 'membership', 'donation'], true) ? $p['context_type'] : 'other';
                coupon_redeem($c, $ctx, (float) $p['amount'], (float) $p['discount'], $p['customer_email'] ?? null, $p['context_id'] ? (int) $p['context_id'] : null);
            }
        }
        // Attribute a referred donation, if a referral was captured.
        if ($p['context_type'] === 'donation' && function_exists('ref_record')) {
            ref_record('donation', ['name' => $p['customer_name'], 'email' => $p['customer_email'], 'amount' => (float) $p['net_amount']]);
        }

        // Context-specific fulfilment (idempotent).
        switch ($p['context_type']) {
            case 'course':
                if (function_exists('lms_enroll') && !empty($p['member_id']) && !empty($p['context_id'])) {
                    lms_enroll((int) $p['context_id'], ['member_id' => (int) $p['member_id']]);
                }
                break;
            case 'event':
                $meta = json_decode((string) ($p['meta'] ?? ''), true) ?: [];
                if (!empty($meta['registration_id'])) {
                    try { db_update('event_registrations', ['status' => 'confirmed'], 'id = :id', [':id' => (int) $meta['registration_id']]); } catch (Throwable $e) {}
                }
                break;
        }

        if (function_exists('notify')) {
            notify('Payment received — ' . number_format((float) $p['net_amount']) . ' ' . $p['currency'], [
                'body' => ucfirst((string) $p['context_type']) . ' · ' . ($p['customer_email'] ?? ''),
                'icon' => 'credit-card', 'type' => 'success', 'url' => admin_url('payments'),
            ]);
        }
        pay_email_receipt($p);
    } catch (Throwable $e) {
        error_log('[pay_fulfill] ' . $e->getMessage());
    }
}

/** Email a simple payment receipt to the customer (best effort). */
function pay_email_receipt(array $p): void
{
    $to = (string) ($p['customer_email'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    $body = '<p>Dear ' . e($p['customer_name'] ?: 'Supporter') . ',</p>'
          . '<p>Thank you. We have received your payment. Here is your receipt:</p>'
          . '<table cellpadding="6" style="border-collapse:collapse;font-size:14px;">'
          . '<tr><td><strong>Receipt No</strong></td><td>' . e($p['receipt_no']) . '</td></tr>'
          . '<tr><td><strong>Order ID</strong></td><td>' . e($p['order_id']) . '</td></tr>'
          . '<tr><td><strong>Purpose</strong></td><td>' . e($p['purpose']) . '</td></tr>'
          . '<tr><td><strong>Amount paid</strong></td><td>' . e($p['currency']) . ' ' . number_format((float) $p['net_amount'], 2) . '</td></tr>'
          . '</table>';
    try { send_mail($to, 'Payment receipt · ' . $p['receipt_no'], $body); } catch (Throwable $e) {}
}

/** Record a refund (admin-initiated). Marks the payment refunded. */
function pay_refund(array $p, float $amount, string $reason = ''): array
{
    $amount = min((float) $p['net_amount'], round($amount, 2));
    if ($amount <= 0) return ['ok' => false, 'error' => 'Invalid refund amount.'];
    db_update('payments', [
        'status'        => 'refunded',
        'refund_amount' => (float) $p['refund_amount'] + $amount,
        'refunded_at'   => date('Y-m-d H:i:s'),
    ], 'order_id = :o', [':o' => $p['order_id']]);
    if (function_exists('log_activity')) log_activity('refund', 'payments', 'Refund ' . number_format($amount) . ' on ' . $p['order_id'] . ($reason ? ' — ' . $reason : ''));
    return ['ok' => true];
}

/** Log an inbound webhook for the audit trail. */
function pay_webhook_log(string $gateway, ?string $event, ?string $orderId, bool $sigOk, bool $handled, string $raw, string $note = ''): void
{
    try {
        db_insert('payment_webhooks', [
            'gateway' => $gateway, 'event' => $event, 'order_id' => $orderId,
            'signature_ok' => $sigOk ? 1 : 0, 'handled' => $handled ? 1 : 0,
            'note' => $note ?: null, 'raw' => mb_substr($raw, 0, 60000),
        ]);
    } catch (Throwable $e) {}
}
