<?php
/**
 * =============================================================================
 *  Mailer — send HTML email via PHP mail() or a minimal SMTP client
 * =============================================================================
 *  - send_mail()      low-level HTML sender (mail() or SMTP based on USE_SMTP)
 *  - send_template()  load an email_templates row and merge {{variables}}
 *  - mail_layout()    wrap content in a branded HTML shell
 *
 *  SMTP is implemented with fsockopen (no Composer/PHPMailer). It is OFF by
 *  default (USE_SMTP = false in config.php); mail() is used until you enable it.
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Resolve who a message is from and who a reply should go to.
 *
 * Split out of send_mail() so the rule is stated once and can be tested without
 * sending anything. Precedence, highest first:
 *
 *   1. what the caller passed in $opts
 *   2. the matching setting (editable in Settings -> Mail & Delivery)
 *   3. the constant in config.php
 *
 * The Reply-To default is the part that did not exist before: without one, any
 * message that did not name a reply address arrived with none, so a reply went
 * to whatever the mail host had rewritten From to.
 *
 * The per-message override is what makes an enquiry answerable — the contact
 * form and the application handlers deliberately set reply_to to the SENDER'S
 * address so staff can hit Reply and reach them. That must keep winning over
 * the organisational default, which is why this returns $opts rather than
 * overwriting a value the caller chose.
 */
function mail_envelope(array $opts = []): array
{
    $fromEmail = $opts['from_email'] ?? get_setting('mail_from_email', MAIL_FROM_EMAIL);
    $fromName  = $opts['from_name']  ?? get_setting('mail_from_name', MAIL_FROM_NAME);

    $replyTo = trim((string) ($opts['reply_to'] ?? ''));
    if ($replyTo === '' || !is_email($replyTo)) {
        $replyTo = (string) get_setting('mail_reply_to', $fromEmail);
    }
    if (is_email($replyTo)) {
        $opts['reply_to'] = $replyTo;
    } else {
        unset($opts['reply_to']);
    }

    return ['from_email' => $fromEmail, 'from_name' => $fromName, 'opts' => $opts];
}

/**
 * Send an HTML email. Returns true on success.
 *
 * @param array $opts ['from_email','from_name','reply_to','cc','bcc','is_html'=>bool]
 */
function send_mail(string $to, string $subject, string $body, array $opts = []): bool
{
    $env       = mail_envelope($opts);
    $fromEmail = $env['from_email'];
    $fromName  = $env['from_name'];
    $opts      = $env['opts'];
    $isHtml    = $opts['is_html'] ?? true;
    $html      = $isHtml ? mail_layout($body, $subject) : $body;

    $useSmtp = (bool) (get_setting('use_smtp', USE_SMTP ? '1' : '0'));

    try {
        if ($useSmtp) {
            return smtp_send($to, $subject, $html, $fromEmail, $fromName, $opts);
        }
        return mail_native($to, $subject, $html, $fromEmail, $fromName, $opts);
    } catch (Throwable $e) {
        error_log('[mailer] ' . $e->getMessage());
        return false;
    }
}

/**
 * Load an email template by slug and send it with variables substituted.
 * Template body may contain {{name}}, {{url}}, etc.
 */
function send_template(string $slug, string $to, array $vars = [], array $opts = []): bool
{
    $tpl = find_by('email_templates', 'slug', $slug);
    if (!$tpl || (int) $tpl['status'] !== 1) {
        error_log("[mailer] template '$slug' not found or inactive");
        return false;
    }
    $subject = mail_render($tpl['subject'], $vars);
    $body    = mail_render($tpl['body'], $vars);
    return send_mail($to, $subject, $body, $opts);
}

/**
 * Fire an email automation (email_automations row) if it exists and is enabled.
 * Used for welcome / donation-receipt / birthday / expiry / inactivity triggers.
 */
function em_trigger(string $triggerKey, string $to, array $vars = [], array $opts = []): bool
{
    try {
        $a = find_by('email_automations', 'trigger_key', $triggerKey);
        if (!$a || empty($a['enabled'])) {
            return false;
        }
        return send_mail($to, mail_render($a['subject'], $vars), mail_render((string) ($a['body'] ?? ''), $vars), $opts);
    } catch (Throwable $e) {
        error_log('[em_trigger] ' . $e->getMessage());
        return false;
    }
}

/** Replace {{key}} placeholders in a template string. */
function mail_render(string $template, array $vars): string
{
    $vars = array_merge([
        'site_name'  => get_setting('site_name', SITE_NAME),
        'site_email' => get_setting('contact_email', SITE_EMAIL),
        'site_url'   => rtrim(APP_URL, '/') . '/',
        'year'       => date('Y'),
    ], $vars);

    return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', static function ($m) use ($vars) {
        return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
    }, $template);
}

/**
 * Branded HTML email shell.
 */
function mail_layout(string $content, string $title = ''): string
{
    $siteName = e(get_setting('site_name', SITE_NAME));
    $year     = date('Y');
    $address  = e(get_setting('contact_address', SITE_ADDRESS));

    // Themed from Global Theme Settings (email tokens), with brand fallbacks.
    $tHead = function_exists('theme_get') ? (theme_get('email.header_bg') ?: '#0B4E3D') : '#0B4E3D';
    $tGrad = function_exists('theme_get') ? (theme_get('color.secondary')  ?: '#174D3D') : '#174D3D';
    $tFont = function_exists('theme_get') ? (theme_get('email.font') ?: 'Arial, Helvetica, sans-serif') : 'Arial, Helvetica, sans-serif';
    $tSig  = function_exists('theme_get') ? trim((string) theme_get('email.signature')) : '';
    $tHead = preg_match('/^#[0-9a-fA-F]{6}$/', $tHead) ? $tHead : '#0B4E3D';
    $tGrad = preg_match('/^#[0-9a-fA-F]{6}$/', $tGrad) ? $tGrad : '#174D3D';
    $tFont = e($tFont);
    if ($tSig !== '') { $content .= '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:13px;">' . nl2br(e($tSig)) . '</div>'; }

    return <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:{$tFont};color:#1f2937;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.06);">
<tr><td style="background:linear-gradient(135deg,{$tHead},{$tGrad});padding:28px 32px;color:#fff;font-size:20px;font-weight:bold;">
{$siteName}
</td></tr>
<tr><td style="padding:32px;font-size:15px;line-height:1.7;">
{$content}
</td></tr>
<tr><td style="padding:20px 32px;background:#f9fafb;color:#6b7280;font-size:12px;text-align:center;border-top:1px solid #e5e7eb;">
&copy; {$year} {$siteName}<br>{$address}
</td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
}

/* -----------------------------------------------------------------------------
 | Native mail() transport
 | -------------------------------------------------------------------------- */
function mail_native(string $to, string $subject, string $html, string $fromEmail, string $fromName, array $opts): bool
{
    $headers   = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . mail_encode_name($fromName) . ' <' . $fromEmail . '>';
    if (!empty($opts['reply_to'])) {
        $headers[] = 'Reply-To: ' . $opts['reply_to'];
    }
    if (!empty($opts['cc']))  { $headers[] = 'Cc: '  . $opts['cc']; }
    if (!empty($opts['bcc'])) { $headers[] = 'Bcc: ' . $opts['bcc']; }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $encodedSubject, $html, implode("\r\n", $headers));
}

/** RFC 2047 encode a display name. */
function mail_encode_name(string $name): string
{
    return preg_match('/[^\x20-\x7e]/', $name)
        ? '=?UTF-8?B?' . base64_encode($name) . '?='
        : '"' . str_replace('"', '', $name) . '"';
}

/* -----------------------------------------------------------------------------
 | Minimal SMTP client (fsockopen). Enough for typical shared-host SMTP relays.
 | -------------------------------------------------------------------------- */
function smtp_send(string $to, string $subject, string $html, string $fromEmail, string $fromName, array $opts): bool
{
    $host   = get_setting('smtp_host', SMTP_HOST);
    $port   = (int) get_setting('smtp_port', SMTP_PORT);
    $user   = get_setting('smtp_user', SMTP_USER);
    $pass   = get_setting('smtp_pass', SMTP_PASS);
    $secure = get_setting('smtp_secure', SMTP_SECURE);

    if ($host === '') {
        error_log('[smtp] host not configured');
        return false;
    }

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @fsockopen($remote, $port, $errno, $errstr, 20);
    if (!$fp) {
        error_log("[smtp] connect failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($fp, 20);

    $read = static function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = static function (string $c) use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        return $read();
    };

    $read(); // greeting
    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $cmd($ehlo);

    if ($secure === 'tls') {
        $cmd('STARTTLS');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            error_log('[smtp] TLS negotiation failed');
            return false;
        }
        $cmd($ehlo);
    }

    if ($user !== '') {
        $cmd('AUTH LOGIN');
        $cmd(base64_encode($user));
        $auth = $cmd(base64_encode($pass));
        if (!str_starts_with(trim($auth), '235')) {
            fclose($fp);
            error_log('[smtp] auth failed: ' . trim($auth));
            return false;
        }
    }

    $cmd('MAIL FROM:<' . $fromEmail . '>');
    foreach (array_filter([$to, $opts['cc'] ?? null, $opts['bcc'] ?? null]) as $rcpt) {
        $cmd('RCPT TO:<' . $rcpt . '>');
    }
    $cmd('DATA');

    $headers  = 'From: ' . mail_encode_name($fromName) . " <$fromEmail>\r\n";
    $headers .= "To: <$to>\r\n";
    if (!empty($opts['reply_to'])) $headers .= 'Reply-To: ' . $opts['reply_to'] . "\r\n";
    $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";

    // Dot-stuffing for lines that begin with a period.
    $body = preg_replace('/^\./m', '..', $html);
    $result = $cmd($headers . "\r\n" . $body . "\r\n.");
    $cmd('QUIT');
    fclose($fp);

    return str_starts_with(trim($result), '250');
}
