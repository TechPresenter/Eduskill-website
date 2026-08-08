<?php
/**
 * =============================================================================
 *  Cashfree webhook — server-to-server payment notification
 * =============================================================================
 *  1. Verifies the HMAC signature (rejects with 401 on mismatch).
 *  2. Logs every inbound notification to payment_webhooks (audit trail).
 *  3. Applies the legacy membership-renewal flow (unchanged).
 *  4. Reconciles the unified `payments` ledger for every other workflow.
 *  Status is always re-verified via the Cashfree API — the body is never
 *  trusted on its own. Responds 200 quickly so Cashfree does not retry.
 * =============================================================================
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/payments.php';
require_once __DIR__ . '/../../includes/pay.php';

$raw       = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
$sigOk     = cashfree_verify_webhook($timestamp, $raw, $signature);

$payload = json_decode($raw, true);
$data    = is_array($payload) ? ($payload['data'] ?? []) : [];
$event   = is_array($payload) ? ($payload['type'] ?? ($payload['event'] ?? null)) : null;
$linkId  = $data['link_id']
    ?? ($data['order']['order_tags']['link_id'] ?? null)
    ?? ($data['link']['link_id'] ?? null);

// Reject anything we can't authenticate (but log the attempt first).
if (!$sigOk) {
    pay_webhook_log('cashfree', $event, $linkId, false, false, $raw, 'invalid signature');
    http_response_code(401);
    echo 'invalid signature';
    exit;
}

$handled = false;
$note    = '';

if ($linkId) {
    // (1) Legacy membership renewal flow — trust the API, not the payload.
    $renewal = db_row('SELECT * FROM membership_renewals WHERE order_id = :o LIMIT 1', [':o' => $linkId]);
    if ($renewal && $renewal['status'] !== 'paid') {
        $status = cashfree_link_status((string) $linkId);
        if ($status['paid']) {
            membership_mark_renewal((int) $renewal['id'], 'paid');
            log_activity('update', 'membership-renewals', 'Cashfree webhook: renewal #' . $renewal['id'] . ' paid');
            $handled = true;
            $note = 'membership renewal paid';
        }
    }

    // (2) Unified payments ledger — course/event/exam/… workflows.
    if (pay_get((string) $linkId)) {
        $p = pay_reconcile((string) $linkId);
        $handled = true;
        $note = 'payment ' . ($p['status'] ?? '?');
    }

    // (3) Donations. These do NOT go through the payments ledger — donate-pay.php
    // writes its own 'don_<id>_<hex>' link id straight onto donations.gateway_order_id,
    // so neither lookup above can ever match one. Without this branch a donor who
    // pays and then closes the tab (or whose bank app drops the redirect) leaves the
    // donation pending forever: no campaign credit, no receipt, no 80G email.
    // Same server-to-server guarantee the other gateways' webhooks already provide.
    $don = db_row('SELECT * FROM donations WHERE gateway_order_id = :o LIMIT 1', [':o' => $linkId]);
    if ($don && !in_array($don['status'], ['completed', 'refunded'], true)) {
        $status = cashfree_link_status((string) $linkId);   // never trust the payload
        if (!empty($status['paid'])) {
            donation_mark_paid((int) $don['id'], ['gateway' => 'cashfree', 'method' => 'Cashfree']);
            log_activity('update', 'donations', 'Cashfree webhook: donation #' . $don['id'] . ' paid');
            $handled = true;
            $note = trim($note . ' donation #' . $don['id'] . ' paid');
        }
    }
}

pay_webhook_log('cashfree', $event, $linkId, true, $handled, $raw, $note);
http_response_code(200);
echo 'ok';
