<?php
/**
 * =============================================================================
 *  PayPal adapter (donations) — Orders v2 (create → approve → capture) + refund
 * =============================================================================
 *  Flow: server creates an Order and redirects the donor to the approve link;
 *  on return the server captures the order. Webhook signatures are verified via
 *  PayPal's verify-webhook-signature API. Disabled until keyed.
 * =============================================================================
 */

declare(strict_types=1);

function paypal_enabled(): bool
{
    return (int) get_setting('paypal_enabled', '0') === 1
        && get_setting('paypal_client_id', '') !== ''
        && get_setting('paypal_secret', '') !== '';
}

function paypal_config(): array
{
    $env = strtolower((string) get_setting('paypal_env', 'sandbox'));
    return [
        'client_id'  => trim((string) get_setting('paypal_client_id', '')),
        'secret'     => trim((string) get_setting('paypal_secret', '')),
        'webhook_id' => trim((string) get_setting('paypal_webhook_id', '')),
        'env'        => $env,
        'base'       => $env === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com',
    ];
}

/** OAuth2 access token (client-credentials). */
function paypal_token(): ?string
{
    $cfg = paypal_config();
    [$code, $json] = gw_http('POST', $cfg['base'] . '/v1/oauth2/token', ['grant_type' => 'client_credentials'], [
        'form'  => true,
        'basic' => [$cfg['client_id'], $cfg['secret']],
    ]);
    return ($code >= 200 && $code < 300 && !empty($json['access_token'])) ? $json['access_token'] : null;
}

/**
 * Create an order. Returns ['success','order_id','approve_url','message'].
 */
function paypal_create_order(float $amount, string $currency, string $returnUrl, string $cancelUrl, string $ref): array
{
    if (!paypal_enabled()) {
        return ['success' => false, 'message' => 'PayPal is disabled'];
    }
    $token = paypal_token();
    if (!$token) {
        return ['success' => false, 'message' => 'PayPal auth failed'];
    }
    $cfg = paypal_config();
    [$code, $json] = gw_http('POST', $cfg['base'] . '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount'    => ['currency_code' => $currency, 'value' => number_format($amount, 2, '.', '')],
            'custom_id' => $ref,
        ]],
        'application_context' => [
            'return_url'  => $returnUrl,
            'cancel_url'  => $cancelUrl,
            'user_action' => 'PAY_NOW',
            'brand_name'  => get_setting('site_name', SITE_NAME),
        ],
    ], ['headers' => ['Authorization: Bearer ' . $token]]);

    if ($code >= 200 && $code < 300 && !empty($json['id'])) {
        $approve = '';
        foreach ($json['links'] ?? [] as $l) {
            if (($l['rel'] ?? '') === 'approve') { $approve = $l['href']; break; }
        }
        return ['success' => true, 'order_id' => $json['id'], 'approve_url' => $approve, 'message' => 'ok'];
    }
    return ['success' => false, 'message' => $json['message'] ?? ('PayPal error HTTP ' . $code)];
}

/** Capture an approved order. Returns ['success','capture_id','paid','message']. */
function paypal_capture(string $orderId): array
{
    if (!paypal_enabled()) {
        return ['success' => false, 'message' => 'PayPal is disabled'];
    }
    $token = paypal_token();
    if (!$token) {
        return ['success' => false, 'message' => 'PayPal auth failed'];
    }
    $cfg = paypal_config();
    [$code, $json] = gw_http('POST', $cfg['base'] . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
        '{}', ['headers' => ['Authorization: Bearer ' . $token]]);
    $status  = $json['status'] ?? '';
    $capture = $json['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';
    return [
        'success'    => $code >= 200 && $code < 300 && $status === 'COMPLETED',
        'capture_id' => $capture,
        'paid'       => $status === 'COMPLETED',
        'message'    => $status ?: ($json['message'] ?? 'capture failed'),
    ];
}

/** Verify a webhook via PayPal's verification API. */
function paypal_verify_webhook(array $headers, string $rawBody): bool
{
    $cfg = paypal_config();
    if ($cfg['webhook_id'] === '') {
        return false;
    }
    $token = paypal_token();
    if (!$token) {
        return false;
    }
    $h = static fn(string $k): string => $headers[$k] ?? $headers[strtolower($k)] ?? '';
    $payload = [
        'transmission_id'   => $h('Paypal-Transmission-Id'),
        'transmission_time' => $h('Paypal-Transmission-Time'),
        'cert_url'          => $h('Paypal-Cert-Url'),
        'auth_algo'         => $h('Paypal-Auth-Algo'),
        'transmission_sig'  => $h('Paypal-Transmission-Sig'),
        'webhook_id'        => $cfg['webhook_id'],
        'webhook_event'     => json_decode($rawBody, true),
    ];
    [$code, $json] = gw_http('POST', $cfg['base'] . '/v1/notifications/verify-webhook-signature', $payload,
        ['headers' => ['Authorization: Bearer ' . $token]]);
    return $code >= 200 && $code < 300 && ($json['verification_status'] ?? '') === 'SUCCESS';
}

/** Refund a capture. $amount optional (major units). */
function paypal_refund(string $captureId, ?float $amount = null, string $currency = 'USD'): array
{
    if (!paypal_enabled()) {
        return ['success' => false, 'message' => 'PayPal is disabled'];
    }
    $token = paypal_token();
    if (!$token) {
        return ['success' => false, 'message' => 'PayPal auth failed'];
    }
    $cfg  = paypal_config();
    $body = $amount !== null
        ? ['amount' => ['value' => number_format($amount, 2, '.', ''), 'currency_code' => $currency]]
        : (object) [];
    [$code, $json] = gw_http('POST', $cfg['base'] . '/v2/payments/captures/' . rawurlencode($captureId) . '/refund',
        $amount !== null ? $body : '{}', ['headers' => ['Authorization: Bearer ' . $token]]);
    if ($code >= 200 && $code < 300 && !empty($json['id'])) {
        return ['success' => true, 'refund_id' => $json['id'], 'message' => 'ok'];
    }
    return ['success' => false, 'message' => $json['message'] ?? ('PayPal refund error HTTP ' . $code)];
}
