<?php
/**
 * =============================================================================
 *  SMS notifications — pluggable, settings-driven, disabled until configured
 * =============================================================================
 *  Providers: MSG91 (Flow API v5, DLT template) and Fast2SMS (bulkV2).
 *  Everything is off by default (sms_enabled = 0). Nothing is sent — and no
 *  network call is made — until an admin enables SMS and supplies keys on the
 *  Membership Settings page. All functions are safe to call when disabled.
 *
 *  Indian SMS requires a DLT-approved template. For MSG91 set the DLT template
 *  id; the message text is passed as the template's first variable (var1).
 *  Fast2SMS 'q' (quick) route is used when no sender id is configured.
 * =============================================================================
 */

declare(strict_types=1);

/** Is SMS turned on and (loosely) configured? */
function sms_enabled(): bool
{
    return (int) get_setting('sms_enabled', '0') === 1;
}

/**
 * Normalise an Indian mobile number to a bare international form (e.g.
 * 917491932148). Returns '' when it cannot be made valid.
 */
function sms_normalize_number(string $to): string
{
    $d = preg_replace('/[^0-9]/', '', $to) ?? '';
    if (strlen($d) === 10) {
        $d = '91' . $d;
    } elseif (strlen($d) === 11 && str_starts_with($d, '0')) {
        $d = '91' . substr($d, 1);
    }
    return (strlen($d) >= 11 && strlen($d) <= 13) ? $d : '';
}

/**
 * Send a plain-text SMS. Returns ['success' => bool, 'message' => string].
 * Never throws.
 */
function send_sms(string $to, string $message): array
{
    if (!sms_enabled()) {
        return ['success' => false, 'message' => 'SMS is disabled'];
    }
    $number = sms_normalize_number($to);
    if ($number === '') {
        return ['success' => false, 'message' => 'Invalid mobile number'];
    }
    $provider = strtolower((string) get_setting('sms_provider', 'msg91'));
    try {
        $res = $provider === 'fast2sms'
            ? sms_send_fast2sms($number, $message)
            : sms_send_msg91($number, $message);
        return sms_normalize_result($res);
    } catch (Throwable $e) {
        error_log('[sms] ' . $e->getMessage());
        return ['success' => false, 'message' => 'SMS gateway error'];
    }
}

/**
 * Normalise a provider result to this module's ['success','message'] contract.
 *
 * Necessary because sms_send_msg91() below is shadowed: messaging.php is loaded
 * globally by bootstrap and declares its own sms_send_msg91() first, and that
 * one returns ['ok','id','error','log_id'] instead. Callers reading
 * $res['success'] therefore got NULL the moment SMS was switched on — under
 * strict_types that is a fatal TypeError, not a silent falsey (it killed the
 * membership-reminder cron mid-sweep). Accept either shape and always hand back
 * ours, so no caller depends on which declaration happened to win.
 */
function sms_normalize_result(array $res): array
{
    if (array_key_exists('success', $res)) {
        return $res + ['message' => $res['error'] ?? ''];
    }
    return [
        'success' => (bool) ($res['ok'] ?? false),
        'message' => (string) ($res['error'] ?? ''),
    ] + $res;
}

/* -----------------------------------------------------------------------------
 | MSG91 — Flow API v5 (DLT template)
 | -------------------------------------------------------------------------- */
// Guard against a name clash with the unified messaging.php module, which is
// loaded globally in bootstrap and declares its own sms_send_msg91(). Without
// this guard, requiring sms.php (e.g. from Membership Settings) fatals with a
// "Cannot redeclare" error. SMS is off by default so send_sms() short-circuits
// before this is ever called; when enabled, messaging.php's version is used.
if (!function_exists('sms_send_msg91')) {
    function sms_send_msg91(string $number, string $message): array
    {
        $authkey  = trim((string) get_setting('msg91_authkey', ''));
        $template = trim((string) get_setting('msg91_dlt_template_id', ''));
        if ($authkey === '' || $template === '') {
            return ['success' => false, 'message' => 'MSG91 not configured (auth key + DLT template id required)'];
        }

        $payload = [
            'template_id' => $template,
            'short_url'   => '0',
            'recipients'  => [[
                'mobiles' => $number,
                'var1'    => $message,
                'message' => $message,
            ]],
        ];
        [$code, $body] = sms_http_post(
            'https://control.msg91.com/api/v5/flow/',
            json_encode($payload),
            ['authkey: ' . $authkey, 'Content-Type: application/json', 'Accept: application/json']
        );
        $ok = $code >= 200 && $code < 300 && stripos($body, 'error') === false;
        return ['success' => $ok, 'message' => $ok ? 'Queued via MSG91' : ('MSG91: ' . substr($body, 0, 180))];
    }
}

/* -----------------------------------------------------------------------------
 | Fast2SMS — bulkV2
 | -------------------------------------------------------------------------- */
function sms_send_fast2sms(string $number, string $message): array
{
    $key    = trim((string) get_setting('fast2sms_key', ''));
    $sender = trim((string) get_setting('fast2sms_sender', ''));
    if ($key === '') {
        return ['success' => false, 'message' => 'Fast2SMS not configured (API key required)'];
    }
    // Fast2SMS wants a bare 10-digit number.
    $local = preg_replace('/^91/', '', $number);

    $fields = [
        'message'  => $message,
        'language' => 'english',
        'numbers'  => $local,
    ];
    if ($sender !== '') {
        $fields['route']     = 'dlt';
        $fields['sender_id'] = $sender;
    } else {
        $fields['route'] = 'q'; // quick transactional route (no DLT)
    }
    [$code, $body] = sms_http_post(
        'https://www.fast2sms.com/dev/bulkV2',
        http_build_query($fields),
        ['authorization: ' . $key, 'Content-Type: application/x-www-form-urlencoded']
    );
    $ok = $code >= 200 && $code < 300 && stripos($body, '"return":true') !== false;
    return ['success' => $ok, 'message' => $ok ? 'Queued via Fast2SMS' : ('Fast2SMS: ' . substr($body, 0, 180))];
}

/* -----------------------------------------------------------------------------
 | Tiny cURL POST helper — returns [httpCode, body].
 | -------------------------------------------------------------------------- */
function sms_http_post(string $url, string $body, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('cURL: ' . $err);
    }
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, (string) $resp];
}
