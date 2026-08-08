<?php
/**
 * =============================================================================
 *  Cashfree payments — pluggable, settings-driven, disabled until configured
 * =============================================================================
 *  Uses the Cashfree Payment Links API (2023-08-01), which returns a hosted
 *  checkout URL we redirect the member to — no client-side SDK required. On
 *  completion Cashfree redirects to our return URL and (optionally) calls our
 *  webhook, whose signature we verify with HMAC-SHA256.
 *
 *  Everything is off until an admin enables Cashfree and pastes the App ID +
 *  Secret on the Membership Settings page. Offline/manual renewal always works
 *  regardless, so the renewal feature is usable with or without a gateway.
 * =============================================================================
 */

declare(strict_types=1);

/** Is Cashfree turned on and credentialed? */
function cashfree_enabled(): bool
{
    if ((int) get_setting('cashfree_enabled', '0') !== 1) {
        return false;
    }
    return get_setting('cashfree_app_id', '') !== '' && get_setting('cashfree_secret_key', '') !== '';
}

/** Resolved config: [app_id, secret, base, webhook_secret, env]. */
function cashfree_config(): array
{
    $env = strtolower((string) get_setting('cashfree_env', 'sandbox'));
    return [
        'app_id'         => trim((string) get_setting('cashfree_app_id', '')),
        'secret'         => trim((string) get_setting('cashfree_secret_key', '')),
        'webhook_secret' => trim((string) get_setting('cashfree_webhook_secret', '')),
        'env'            => $env,
        'base'           => $env === 'production' ? 'https://api.cashfree.com' : 'https://sandbox.cashfree.com',
    ];
}

/**
 * Create a Cashfree payment link for a renewal.
 *
 * @param array $args linkId, amount, customerName, customerEmail, customerPhone,
 *                    purpose, returnUrl, notifyUrl
 * @return array ['success'=>bool, 'link_url'=>?string, 'link_id'=>?string,
 *                'message'=>string, 'raw'=>array]
 */
function cashfree_create_link(array $args): array
{
    if (!cashfree_enabled()) {
        return ['success' => false, 'link_url' => null, 'link_id' => null, 'message' => 'Cashfree is disabled', 'raw' => []];
    }
    $cfg = cashfree_config();

    $payload = [
        'link_id'       => $args['linkId'],
        'link_amount'   => round((float) $args['amount'], 2),
        'link_currency' => 'INR',
        'link_purpose'  => $args['purpose'] ?? 'Membership renewal',
        'customer_details' => [
            'customer_name'  => $args['customerName'] ?? '',
            'customer_email' => $args['customerEmail'] ?? '',
            'customer_phone' => cashfree_phone($args['customerPhone'] ?? ''),
        ],
        'link_notify' => ['send_sms' => false, 'send_email' => false],
        'link_meta'   => [
            'return_url' => $args['returnUrl'] ?? '',
            'notify_url' => $args['notifyUrl'] ?? '',
        ],
        'link_auto_reminders' => false,
    ];

    [$code, $json] = cashfree_api('POST', '/pg/links', $payload, $cfg);
    if ($code >= 200 && $code < 300 && !empty($json['link_url'])) {
        return [
            'success'  => true,
            'link_url' => $json['link_url'],
            'link_id'  => $json['link_id'] ?? $args['linkId'],
            'message'  => 'Link created',
            'raw'      => $json,
        ];
    }
    return [
        'success'  => false,
        'link_url' => null,
        'link_id'  => null,
        'message'  => $json['message'] ?? ('Cashfree error (HTTP ' . $code . ')'),
        'raw'      => $json,
    ];
}

/**
 * Fetch a payment link's status. Returns ['paid'=>bool, 'status'=>string,
 * 'amount_paid'=>float, 'raw'=>array].
 */
function cashfree_link_status(string $linkId): array
{
    if (!cashfree_enabled()) {
        return ['paid' => false, 'status' => 'disabled', 'amount_paid' => 0.0, 'raw' => []];
    }
    $cfg = cashfree_config();
    [$code, $json] = cashfree_api('GET', '/pg/links/' . rawurlencode($linkId), null, $cfg);
    $status = strtoupper((string) ($json['link_status'] ?? 'UNKNOWN'));
    return [
        'paid'        => $status === 'PAID',
        'status'      => $status,
        'amount_paid' => (float) ($json['link_amount_paid'] ?? 0),
        'raw'         => $json,
    ];
}

/**
 * Verify a Cashfree webhook signature.
 * signature = base64( HMAC-SHA256( timestamp + rawBody, secret ) ).
 */
function cashfree_verify_webhook(string $timestamp, string $rawBody, string $signature): bool
{
    $cfg = cashfree_config();
    $secret = $cfg['webhook_secret'] !== '' ? $cfg['webhook_secret'] : $cfg['secret'];
    if ($secret === '' || $signature === '') {
        return false;
    }
    $expected = base64_encode(hash_hmac('sha256', $timestamp . $rawBody, $secret, true));
    return hash_equals($expected, $signature);
}

/** Cashfree wants phone digits only (they validate 8-12 digits). */
function cashfree_phone(string $phone): string
{
    $d = preg_replace('/[^0-9]/', '', $phone) ?? '';
    if (strlen($d) > 10 && str_starts_with($d, '91')) {
        $d = substr($d, 2);
    }
    return $d !== '' ? $d : '0000000000';
}

/* -----------------------------------------------------------------------------
 | cURL helper — returns [httpCode, decodedJsonArray].
 | -------------------------------------------------------------------------- */
function cashfree_api(string $method, string $path, ?array $body, array $cfg): array
{
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'x-api-version: 2023-08-01',
        'x-client-id: ' . $cfg['app_id'],
        'x-client-secret: ' . $cfg['secret'],
    ];
    $ch = curl_init($cfg['base'] . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        error_log('[cashfree] cURL: ' . $err);
        return [0, ['message' => 'Connection error: ' . $err]];
    }
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string) $resp, true);
    return [$code, is_array($json) ? $json : ['raw' => (string) $resp]];
}
