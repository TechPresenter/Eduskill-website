<?php
/**
 * =============================================================================
 *  Messaging — SMS (MSG91 / Twilio), WhatsApp (Cloud API), Push (OneSignal).
 * =============================================================================
 *  Config lives in the `settings` group 'messaging' (edited from admin/sms.php,
 *  admin/whatsapp.php, admin/push.php). Every send is recorded in messaging_log
 *  for the delivery reports. All calls are wrapped so a failure never throws.
 *
 *  Each sender returns ['ok'=>bool, 'id'=>?string, 'error'=>?string, 'log_id'=>int].
 * =============================================================================
 */

declare(strict_types=1);

/** Minimal cURL request helper. Returns ['code'=>int, 'body'=>string, 'error'=>string]. */
function msg_http(string $url, array $o = []): array
{
    if (!function_exists('curl_init')) {
        return ['code' => 0, 'body' => '', 'error' => 'cURL unavailable'];
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int) ($o['timeout'] ?? 15),
        CURLOPT_CUSTOMREQUEST  => $o['method'] ?? 'POST',
    ];
    if (!empty($o['headers'])) $opts[CURLOPT_HTTPHEADER] = $o['headers'];
    if (isset($o['body']))     $opts[CURLOPT_POSTFIELDS] = is_array($o['body']) ? http_build_query($o['body']) : $o['body'];
    if (!empty($o['auth']))    $opts[CURLOPT_USERPWD]    = $o['auth'];
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => is_string($body) ? $body : '', 'error' => $err];
}

/** Record a messaging attempt. Returns the log row id. */
function msg_log(string $channel, ?string $recipient, string $body, string $status, array $extra = []): int
{
    try {
        return db_insert('messaging_log', [
            'channel'         => $channel,
            'provider'        => $extra['provider'] ?? null,
            'recipient'       => $recipient ? substr($recipient, 0, 191) : null,
            'template'        => $extra['template'] ?? null,
            'body'            => mb_substr($body, 0, 2000),
            'status'          => $status,
            'provider_msg_id' => $extra['id'] ?? null,
            'error'           => isset($extra['error']) ? substr((string) $extra['error'], 0, 500) : null,
            'created_by'      => function_exists('current_user_id') ? (current_user_id() ?: null) : null,
        ]);
    } catch (Throwable $e) {
        error_log('[messaging log] ' . $e->getMessage());
        return 0;
    }
}

/* ============================================================ SMS */

/** Send an SMS via the configured provider (MSG91 or Twilio). */
function sms_send(string $to, string $message, array $opts = []): array
{
    if (!(bool) get_setting('sms_enabled', '0')) {
        return ['ok' => false, 'error' => 'SMS is disabled.', 'log_id' => msg_log('sms', $to, $message, 'failed', ['error' => 'disabled'])];
    }
    $provider = (string) get_setting('sms_provider', 'msg91');
    return $provider === 'twilio' ? sms_send_twilio($to, $message) : sms_send_msg91($to, $message, $opts);
}

/** MSG91 v5 Flow API (India / DLT). */
function sms_send_msg91(string $to, string $message, array $opts = []): array
{
    $authkey  = trim((string) get_setting('msg91_authkey', ''));
    $sender   = trim((string) get_setting('msg91_sender', ''));
    $template = $opts['template_id'] ?? trim((string) get_setting('msg91_dlt_template', ''));
    $mobile   = preg_replace('/\D/', '', $to);
    if ($authkey === '' || $template === '') {
        return ['ok' => false, 'error' => 'MSG91 not configured.', 'log_id' => msg_log('sms', $to, $message, 'failed', ['provider' => 'msg91', 'error' => 'not configured'])];
    }
    $payload = json_encode([
        'template_id' => $template,
        'sender'      => $sender,
        'short_url'   => '0',
        'recipients'  => [array_merge(['mobiles' => $mobile], $opts['vars'] ?? ['message' => $message])],
    ]);
    $r = msg_http('https://control.msg91.com/api/v5/flow/', [
        'headers' => ['accept: application/json', 'content-type: application/json', 'authkey: ' . $authkey],
        'body'    => $payload,
    ]);
    $data = json_decode($r['body'], true);
    $ok   = $r['code'] === 200 && (($data['type'] ?? '') === 'success' || isset($data['request_id']));
    $id   = $data['request_id'] ?? null;
    $log  = msg_log('sms', $to, $message, $ok ? 'sent' : 'failed', ['provider' => 'msg91', 'id' => $id, 'error' => $ok ? null : ($data['message'] ?? $r['error'] ?: 'HTTP ' . $r['code'])]);
    return ['ok' => $ok, 'id' => $id, 'error' => $ok ? null : ($data['message'] ?? 'send failed'), 'log_id' => $log];
}

/** Twilio Messages API. */
function sms_send_twilio(string $to, string $message): array
{
    $sid   = trim((string) get_setting('twilio_sid', ''));
    $token = trim((string) get_setting('twilio_token', ''));
    $from  = trim((string) get_setting('twilio_from', ''));
    if ($sid === '' || $token === '' || $from === '') {
        return ['ok' => false, 'error' => 'Twilio not configured.', 'log_id' => msg_log('sms', $to, $message, 'failed', ['provider' => 'twilio', 'error' => 'not configured'])];
    }
    $r = msg_http('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json', [
        'auth' => $sid . ':' . $token,
        'body' => ['To' => $to, 'From' => $from, 'Body' => $message],
    ]);
    $data = json_decode($r['body'], true);
    $ok   = in_array($r['code'], [200, 201], true) && !empty($data['sid']);
    $log  = msg_log('sms', $to, $message, $ok ? 'sent' : 'failed', ['provider' => 'twilio', 'id' => $data['sid'] ?? null, 'error' => $ok ? null : ($data['message'] ?? $r['error'] ?: 'HTTP ' . $r['code'])]);
    return ['ok' => $ok, 'id' => $data['sid'] ?? null, 'error' => $ok ? null : ($data['message'] ?? 'send failed'), 'log_id' => $log];
}

/* ============================================================ WHATSAPP */

/** Send a WhatsApp message via Meta's WhatsApp Cloud API (plain text or template). */
function whatsapp_send(string $to, string $message, array $opts = []): array
{
    if (!(bool) get_setting('whatsapp_enabled', '0')) {
        return ['ok' => false, 'error' => 'WhatsApp is disabled.', 'log_id' => msg_log('whatsapp', $to, $message, 'failed', ['error' => 'disabled'])];
    }
    $token   = trim((string) get_setting('whatsapp_token', ''));
    $phoneId = trim((string) get_setting('whatsapp_phone_id', ''));
    $mobile  = preg_replace('/\D/', '', $to);
    if ($token === '' || $phoneId === '') {
        return ['ok' => false, 'error' => 'WhatsApp not configured.', 'log_id' => msg_log('whatsapp', $to, $message, 'failed', ['provider' => 'meta', 'error' => 'not configured'])];
    }
    if (!empty($opts['template'])) {
        $msg = ['type' => 'template', 'template' => ['name' => $opts['template'], 'language' => ['code' => $opts['lang'] ?? 'en_US']]];
    } else {
        $msg = ['type' => 'text', 'text' => ['preview_url' => true, 'body' => $message]];
    }
    $payload = json_encode(array_merge(['messaging_product' => 'whatsapp', 'to' => $mobile], $msg));
    $r = msg_http('https://graph.facebook.com/v18.0/' . rawurlencode($phoneId) . '/messages', [
        'headers' => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        'body'    => $payload,
    ]);
    $data = json_decode($r['body'], true);
    $id   = $data['messages'][0]['id'] ?? null;
    $ok   = $r['code'] === 200 && $id !== null;
    $log  = msg_log('whatsapp', $to, $message, $ok ? 'sent' : 'failed', ['provider' => 'meta', 'template' => $opts['template'] ?? null, 'id' => $id, 'error' => $ok ? null : ($data['error']['message'] ?? $r['error'] ?: 'HTTP ' . $r['code'])]);
    return ['ok' => $ok, 'id' => $id, 'error' => $ok ? null : ($data['error']['message'] ?? 'send failed'), 'log_id' => $log];
}

/* ============================================================ PUSH (OneSignal) */

/**
 * Send a web-push via OneSignal. $opts: ['segments'=>[...], 'url'=>string,
 * 'filters'=>[...], 'heading'=>string]. Defaults to the "Subscribed Users" segment.
 */
function push_send(string $message, array $opts = []): array
{
    if (!(bool) get_setting('onesignal_enabled', '0')) {
        return ['ok' => false, 'error' => 'Push is disabled.', 'log_id' => msg_log('push', null, $message, 'failed', ['error' => 'disabled'])];
    }
    $appId  = trim((string) get_setting('onesignal_app_id', ''));
    $apiKey = trim((string) get_setting('onesignal_api_key', ''));
    if ($appId === '' || $apiKey === '') {
        return ['ok' => false, 'error' => 'OneSignal not configured.', 'log_id' => msg_log('push', null, $message, 'failed', ['provider' => 'onesignal', 'error' => 'not configured'])];
    }
    $payload = [
        'app_id'   => $appId,
        'contents' => ['en' => $message],
        'headings' => ['en' => $opts['heading'] ?? get_setting('site_name', SITE_NAME)],
    ];
    if (!empty($opts['url']))      $payload['url'] = $opts['url'];
    if (!empty($opts['filters']))  $payload['filters'] = $opts['filters'];
    else                           $payload['included_segments'] = $opts['segments'] ?? ['Subscribed Users'];

    $r = msg_http('https://onesignal.com/api/v1/notifications', [
        'headers' => ['Authorization: Basic ' . $apiKey, 'Content-Type: application/json'],
        'body'    => json_encode($payload),
    ]);
    $data = json_decode($r['body'], true);
    $id   = $data['id'] ?? null;
    $ok   = $r['code'] === 200 && $id !== null && empty($data['errors']);
    $err  = $ok ? null : (is_array($data['errors'] ?? null) ? implode('; ', $data['errors']) : ($data['errors'] ?? $r['error'] ?: 'HTTP ' . $r['code']));
    $log  = msg_log('push', $opts['heading'] ?? null, $message, $ok ? 'sent' : 'failed', ['provider' => 'onesignal', 'id' => $id, 'error' => $err]);
    return ['ok' => $ok, 'id' => $id, 'error' => $err, 'log_id' => $log];
}
