<?php
/**
 * =============================================================================
 *  Stripe webhook — verifies the Stripe-Signature, then completes the donation
 * =============================================================================
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/gateways/stripe.php';

$raw = file_get_contents('php://input') ?: '';
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!stripe_verify_webhook($raw, $sig)) {
    http_response_code(401);
    exit('invalid signature');
}

$data = json_decode($raw, true) ?: [];
if (($data['type'] ?? '') === 'checkout.session.completed') {
    $sess  = $data['data']['object'] ?? [];
    $donId = (int) ($sess['metadata']['donation_id'] ?? 0);
    if ($donId && ($sess['payment_status'] ?? '') === 'paid') {
        $don = find('donations', $donId);
        if ($don && $don['status'] !== 'completed') {
            donation_mark_paid($donId, ['gateway' => 'stripe', 'payment_id' => $sess['payment_intent'] ?? null, 'method' => 'Stripe']);
        }
    }
}
http_response_code(200);
echo 'ok';
