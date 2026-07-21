<?php
/**
 * Outbound email — dependency-free (no Composer/PHPMailer). PHP's mail() is unreliable on shared
 * hosting, so we speak SMTP directly over a socket: EHLO → STARTTLS/SSL → AUTH LOGIN → MAIL/RCPT/DATA.
 *
 * Two drivers, chosen from the settings table (admin-editable):
 *   'log'  — record to email_log + storage/logs/mail.log, DON'T send. The safe default: nothing tries
 *            to email until real SMTP credentials are entered.
 *   'smtp' — actually send via the configured host. Every attempt is logged either way, and a failure
 *            returns the full SMTP transcript so "why didn't my email go out?" has a real answer.
 */

declare(strict_types=1);

defined('ESK') || exit('No direct access.');

/**
 * Send an HTML email. On failure $transcript holds the SMTP conversation for debugging.
 */
function send_mail(string $toEmail, string $subject, string $htmlBody, string $toName = '', ?string &$transcript = null): bool
{
    $driver = (string) setting('mail_driver', 'log');

    if ($driver !== 'smtp') {
        mail_log_record($toEmail, $toName, $subject, $htmlBody, 'logged', null);
        @file_put_contents(
            STORAGE_PATH . '/logs/mail.log',
            sprintf("==== %s ====\nTo: %s\nSubject: %s\n\n%s\n\n", date('c'), $toEmail, $subject, strip_tags($htmlBody)),
            FILE_APPEND
        );
        $transcript = "Mail driver is 'log' (test mode). The message was recorded to the email log and storage/logs/mail.log but NOT sent. Set the driver to SMTP in Settings to send for real.";
        return true;
    }

    $cfg = [
        'host' => (string) setting('smtp_host', ''),
        'port' => (int) setting('smtp_port', 587),
        'encryption' => (string) setting('smtp_encryption', 'tls'),
        'username' => (string) setting('smtp_username', ''),
        'password' => (string) setting('smtp_password', ''),
        'from_email' => (string) setting('mail_from_email', (string) setting('contact_email', 'noreply@localhost')),
        'from_name' => (string) setting('mail_from_name', (string) setting('site_name', APP_NAME)),
    ];

    $t = '';
    $ok = smtp_send($cfg, $toEmail, $toName, $subject, $htmlBody, $t);
    $transcript = $t;
    mail_log_record($toEmail, $toName, $subject, $htmlBody, $ok ? 'sent' : 'failed', $ok ? null : $t);
    return $ok;
}

function mail_log_record(string $to, string $toName, string $subject, string $body, string $status, ?string $error): void
{
    try {
        db_insert(
            'INSERT INTO email_log (to_email, to_name, subject, body, status, error) VALUES (?, ?, ?, ?, ?, ?)',
            [$to, $toName !== '' ? $toName : null, $subject, $body, $status, $error !== null ? substr($error, 0, 500) : null]
        );
    } catch (Throwable $e) {
        error_log('[mailer] email_log: ' . $e->getMessage());
    }
}

/**
 * Minimal but correct SMTP client. Checks every server response code and aborts on the first
 * unexpected one, returning the transcript.
 */
function smtp_send(array $cfg, string $toEmail, string $toName, string $subject, string $htmlBody, ?string &$transcript = null): bool
{
    $host = $cfg['host'];
    $port = (int) $cfg['port'];
    $enc = strtolower((string) $cfg['encryption']);   // tls | ssl | none
    if ($host === '') {
        $transcript = 'No SMTP host configured.';
        return false;
    }

    $log = '';
    $fail = function (string $why) use (&$log, &$transcript): bool {
        $transcript = $log . "\n>>> ABORT: " . $why;
        return false;
    };

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return $fail("connect to {$remote} failed: {$errstr} ({$errno})");
    }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp, &$log): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $log .= $line;
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;   // last line of a multiline reply
            }
        }
        return $data;
    };
    $put = function (string $c) use ($fp, &$log): void {
        fwrite($fp, $c . "\r\n");
        $log .= $c . "\r\n";
    };
    $code = fn (string $r): string => substr(trim($r), 0, 3);

    if ($code($read()) !== '220') {
        return $fail('bad greeting');
    }
    $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
    $put($ehlo);
    if ($code($read()) !== '250') {
        return $fail('EHLO rejected');
    }

    if ($enc === 'tls') {
        $put('STARTTLS');
        if ($code($read()) !== '220') {
            return $fail('STARTTLS refused');
        }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            return $fail('TLS negotiation failed');
        }
        $put($ehlo);
        if ($code($read()) !== '250') {
            return $fail('EHLO after TLS rejected');
        }
    }

    if ($cfg['username'] !== '') {
        $put('AUTH LOGIN');
        if ($code($read()) !== '334') {
            return $fail('AUTH LOGIN not accepted');
        }
        $put(base64_encode((string) $cfg['username']));
        if ($code($read()) !== '334') {
            return $fail('username rejected');
        }
        $put(base64_encode((string) $cfg['password']));
        if ($code($read()) !== '235') {
            return $fail('authentication failed (check username/password)');
        }
    }

    $put('MAIL FROM:<' . $cfg['from_email'] . '>');
    if ($code($read()) !== '250') {
        return $fail('MAIL FROM rejected');
    }
    $put('RCPT TO:<' . $toEmail . '>');
    $rc = $code($read());
    if ($rc !== '250' && $rc !== '251') {
        return $fail('recipient rejected');
    }
    $put('DATA');
    if ($code($read()) !== '354') {
        return $fail('DATA not accepted');
    }

    $headers = 'From: ' . mb_encode_mimeheader((string) $cfg['from_name']) . ' <' . $cfg['from_email'] . ">\r\n"
        . 'To: ' . ($toName !== '' ? mb_encode_mimeheader($toName) . ' ' : '') . '<' . $toEmail . ">\r\n"
        . 'Subject: ' . mb_encode_mimeheader($subject) . "\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: 8bit' . "\r\n";
    // Dot-stuff lines beginning with a period (RFC 5321).
    $body = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $htmlBody)));
    fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
    $log .= "[message body sent]\r\n";
    if ($code($read()) !== '250') {
        return $fail('message not accepted after DATA');
    }

    $put('QUIT');
    @fclose($fp);
    $transcript = $log;
    return true;
}
