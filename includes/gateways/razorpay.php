<?php
/**
 * =============================================================================
 *  Razorpay adapter (donations) — Orders + Checkout signature + webhook + refund
 * =============================================================================
 *  Flow: server creates an Order → client Checkout.js collects payment and
 *  returns razorpay_payment_id/order_id/signature → server verifies the HMAC
 *  signature before marking the donation paid. Disabled until keyed.
 * =============================================================================
 */

declare(strict_types=1);

function razorpay_enabled(): bool
{
    return (int) get_setting('razorpay_enabled', '0') === 1
        && get_setting('razorpay_key_id', '') !== ''
        && get_setting('razorpay_key_secret', '') !== '';
}

function razorpay_config(): array
{
    return [
        'key_id'         => trim((string) get_setting('razorpay_key_id', '')),
        'key_secret'     => trim((string) get_setting('razorpay_key_secret', '')),
        'webhook_secret' => trim((string) get_setting('razorpay_webhook_secret', '')),
    ];
}

/**
 * Create a Razorpay order. $amount is in major units (₹); Razorpay wants paise.
 * Returns ['success','order_id','key_id','amount_paise','currency','message'].
 */
function razorpay_create_order(float $amount, string $currency, string $receipt): array
{
    if (!razorpay_enabled()) {
        return ['success' => false, 'message' => 'Razorpay is disabled'];
    }
    $cfg   = razorpay_config();
    $paise = (int) round($amount * 100);
    [$code, $json] = gw_http('POST', 'https://api.razorpay.com/v1/orders', [
        'amount'          => $paise,
        'currency'        => $currency,
        'receipt'         => $receipt,
        'payment_capture' => 1,
    ], ['basic' => [$cfg['key_id'], $cfg['key_secret']]]);

    if ($code >= 200 && $code < 300 && !empty($json['id'])) {
        return [
            'success'       => true,
            'order_id'      => $json['id'],
            'key_id'        => $cfg['key_id'],
            'amount_paise'  => $paise,
            'currency'      => $currency,
            'message'       => 'ok',
        ];
    }
    return ['success' => false, 'message' => $json['error']['description'] ?? ('Razorpay error HTTP ' . $code)];
}

/** Verify the checkout callback signature: HMAC-SHA256(order_id|payment_id, secret). */
function razorpay_verify_payment_signature(string $orderId, string $paymentId, string $signature): bool
{
    $cfg = razorpay_config();
    if ($cfg['key_secret'] === '' || $signature === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $cfg['key_secret']);
    return hash_equals($expected, $signature);
}

/** Verify a webhook: HMAC-SHA256(rawBody, webhook_secret) == X-Razorpay-Signature. */
function razorpay_verify_webhook(string $rawBody, string $signature): bool
{
    $cfg    = razorpay_config();
    $secret = $cfg['webhook_secret'] !== '' ? $cfg['webhook_secret'] : $cfg['key_secret'];
    if ($secret === '' || $signature === '') {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
}

/** Refund a payment (full or partial in ₹). Returns ['success','refund_id','message']. */
function razorpay_refund(string $paymentId, ?float $amount = null): array
{
    if (!razorpay_enabled()) {
        return ['success' => false, 'message' => 'Razorpay is disabled'];
    }
    $cfg  = razorpay_config();
    $body = $amount !== null ? ['amount' => (int) round($amount * 100)] : [];
    [$code, $json] = gw_http('POST', 'https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId) . '/refund',
        $body ?: '{}', ['basic' => [$cfg['key_id'], $cfg['key_secret']]]);
    if ($code >= 200 && $code < 300 && !empty($json['id'])) {
        return ['success' => true, 'refund_id' => $json['id'], 'message' => 'ok'];
    }
    return ['success' => false, 'message' => $json['error']['description'] ?? ('Razorpay refund error HTTP ' . $code)];
}
