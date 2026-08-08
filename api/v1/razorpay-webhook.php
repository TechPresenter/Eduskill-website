<?php
/**
 * =============================================================================
 *  Razorpay webhook — verifies HMAC signature, then completes the donation
 * =============================================================================
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/gateways/razorpay.php';

$raw = file_get_contents('php://input') ?: '';
$sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (!razorpay_verify_webhook($raw, $sig)) {
    http_response_code(401);
    exit('invalid signature');
}

$data  = json_decode($raw, true) ?: [];
$event = $data['event'] ?? '';
if (in_array($event, ['payment.captured', 'order.paid'], true)) {
    $entity  = $data['payload']['payment']['entity'] ?? [];
    $orderId = $entity['order_id'] ?? '';
    if ($orderId) {
        $don = db_row('SELECT * FROM donations WHERE gateway_order_id = :o LIMIT 1', [':o' => $orderId]);
        if ($don && $don['status'] !== 'completed') {
            donation_mark_paid((int) $don['id'], ['gateway' => 'razorpay', 'order_id' => $orderId, 'payment_id' => $entity['id'] ?? null, 'method' => 'Razorpay']);
        }
    }
}
http_response_code(200);
echo 'ok';
