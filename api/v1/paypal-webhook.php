<?php
/**
 * =============================================================================
 *  PayPal webhook — verifies via PayPal's API, then completes the donation
 * =============================================================================
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/gateways/paypal.php';

$raw = file_get_contents('php://input') ?: '';

// Rebuild the request headers with PayPal's expected capitalisation.
$hdr = [];
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_')) {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
        $hdr[$name] = $v;
    }
}

if (!paypal_verify_webhook($hdr, $raw)) {
    http_response_code(401);
    exit('invalid signature');
}

$data = json_decode($raw, true) ?: [];
if (($data['event_type'] ?? '') === 'PAYMENT.CAPTURE.COMPLETED') {
    $res   = $data['resource'] ?? [];
    $donId = (int) ($res['custom_id'] ?? 0);
    if ($donId) {
        $don = find('donations', $donId);
        if ($don && $don['status'] !== 'completed') {
            donation_mark_paid($donId, ['gateway' => 'paypal', 'payment_id' => $res['id'] ?? null, 'method' => 'PayPal']);
        }
    }
}
http_response_code(200);
echo 'ok';
