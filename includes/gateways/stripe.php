<?php
/**
 * =============================================================================
 *  Stripe adapter (donations) — hosted Checkout + webhook + refund
 * =============================================================================
 *  Flow: server creates a Checkout Session and redirects the donor to Stripe's
 *  hosted page; on success Stripe redirects back and (via webhook) confirms.
 *  Signature verified with the webhook signing secret. Disabled until keyed.
 * =============================================================================
 */

declare(strict_types=1);

function stripe_enabled(): bool
{
    return (int) get_setting('stripe_enabled', '0') === 1 && get_setting('stripe_secret', '') !== '';
}

function stripe_config(): array
{
    return [
        'secret'         => trim((string) get_setting('stripe_secret', '')),
        'publishable'    => trim((string) get_setting('stripe_publishable', '')),
        'webhook_secret' => trim((string) get_setting('stripe_webhook_secret', '')),
    ];
}

/**
 * Create a hosted Checkout Session. $amount in major units; Stripe wants cents.
 * Returns ['success','url','session_id','message'].
 */
function stripe_create_checkout(float $amount, string $currency, string $name, string $successUrl, string $cancelUrl, array $meta = []): array
{
    if (!stripe_enabled()) {
        return ['success' => false, 'message' => 'Stripe is disabled'];
    }
    $cfg   = stripe_config();
    $cents = (int) round($amount * 100);
    $form = [
        'mode'                                        => 'payment',
        'success_url'                                 => $successUrl,
        'cancel_url'                                  => $cancelUrl,
        'line_items[0][quantity]'                     => 1,
        'line_items[0][price_data][currency]'         => strtolower($currency),
        'line_items[0][price_data][unit_amount]'      => $cents,
        'line_items[0][price_data][product_data][name]' => $name,
    ];
    foreach ($meta as $k => $v) {
        $form['metadata[' . $k . ']'] = $v;
        $form['payment_intent_data[metadata][' . $k . ']'] = $v;
    }
    [$code, $json] = gw_http('POST', 'https://api.stripe.com/v1/checkout/sessions', $form, [
        'form'  => true,
        'basic' => [$cfg['secret'], ''],
    ]);
    if ($code >= 200 && $code < 300 && !empty($json['url'])) {
        return ['success' => true, 'url' => $json['url'], 'session_id' => $json['id'] ?? '', 'message' => 'ok'];
    }
    return ['success' => false, 'message' => $json['error']['message'] ?? ('Stripe error HTTP ' . $code)];
}

/** Fetch a Checkout Session (to confirm payment on return). */
function stripe_get_session(string $sessionId): array
{
    $cfg = stripe_config();
    [$code, $json] = gw_http('GET', 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId),
        null, ['basic' => [$cfg['secret'], '']]);
    return ['ok' => $code >= 200 && $code < 300, 'paid' => ($json['payment_status'] ?? '') === 'paid', 'raw' => $json];
}

/** Verify the Stripe-Signature header against the raw payload. */
function stripe_verify_webhook(string $payload, string $sigHeader): bool
{
    $cfg = stripe_config();
    if ($cfg['webhook_secret'] === '' || $sigHeader === '') {
        return false;
    }
    $t = null; $v1 = [];
    foreach (explode(',', $sigHeader) as $part) {
        [$k, $val] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') { $t = $val; }
        if ($k === 'v1') { $v1[] = $val; }
    }
    if ($t === null || !$v1) {
        return false;
    }
    // Reject stale timestamps (>5 min) to prevent replay.
    if (abs(time() - (int) $t) > 300) {
        return false;
    }
    $expected = hash_hmac('sha256', $t . '.' . $payload, $cfg['webhook_secret']);
    foreach ($v1 as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}

/** Refund a payment by its PaymentIntent id. $amount in major units (optional). */
function stripe_refund(string $paymentIntent, ?float $amount = null): array
{
    if (!stripe_enabled()) {
        return ['success' => false, 'message' => 'Stripe is disabled'];
    }
    $cfg  = stripe_config();
    $form = ['payment_intent' => $paymentIntent];
    if ($amount !== null) {
        $form['amount'] = (int) round($amount * 100);
    }
    [$code, $json] = gw_http('POST', 'https://api.stripe.com/v1/refunds', $form, ['form' => true, 'basic' => [$cfg['secret'], '']]);
    if ($code >= 200 && $code < 300 && !empty($json['id'])) {
        return ['success' => true, 'refund_id' => $json['id'], 'message' => 'ok'];
    }
    return ['success' => false, 'message' => $json['error']['message'] ?? ('Stripe refund error HTTP ' . $code)];
}
