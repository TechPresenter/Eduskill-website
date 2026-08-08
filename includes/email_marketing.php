<?php
/**
 * =============================================================================
 *  Email Marketing engine — campaigns: segment → prepare → batch send → track.
 * =============================================================================
 *  Loaded by the admin campaign pages + the public email-track endpoint.
 *  Sends via a selected SMTP profile (email_smtp_profiles) or the global mailer.
 *  Every recipient gets a unique token used for open-pixel + click + unsubscribe.
 * =============================================================================
 */

declare(strict_types=1);

/** Stable server secret for unsubscribe links (generated once, like donation_secret). */
function newsletter_secret(): string
{
    $s = (string) get_setting('newsletter_secret', '');
    if ($s === '') {
        $s = bin2hex(random_bytes(24));
        set_setting('newsletter_secret', $s, 'newsletter', 'text');
    }
    return $s;
}

/** Capability token authorising subscription changes for one email address. */
function newsletter_unsub_token(string $email): string
{
    return substr(hash_hmac('sha256', 'unsub|' . strtolower(trim($email)), newsletter_secret()), 0, 24);
}

/** [sql, params] selecting subscribers for a campaign's segment. */
function em_recipient_query(array $c): array
{
    $where = ['email <> \'\''];
    $params = [];
    if (($c['segment_status'] ?? 'subscribed') !== 'all') {
        $where[] = "status = 'subscribed'";
    }
    if (!empty($c['segment_tag'])) {
        $tag = trim((string) $c['segment_tag']);
        $where[] = "(tags = :t OR tags LIKE :ta OR tags LIKE :tb OR tags LIKE :tc)";
        $params[':t']  = $tag;
        $params[':ta'] = $tag . ',%';
        $params[':tb'] = '%,' . $tag;
        $params[':tc'] = '%,' . $tag . ',%';
    }
    return ["SELECT id, name, email FROM newsletter_subscribers WHERE " . implode(' AND ', $where), $params];
}

/** How many subscribers match a segment. */
function em_segment_count(array $c): int
{
    [$sql, $p] = em_recipient_query($c);
    return (int) db_value(preg_replace('/^SELECT .*? FROM/i', 'SELECT COUNT(*) FROM', $sql, 1), $p);
}

/** Build the queued recipient list for a campaign (re-runnable). Returns count. */
function em_prepare(int $campaignId): int
{
    $c = find('email_campaigns', $campaignId);
    if (!$c) return 0;
    [$sql, $p] = em_recipient_query($c);
    $subs = db_all($sql, $p);

    db_delete('email_campaign_recipients', 'campaign_id = :c', [':c' => $campaignId]);
    $i = 0;
    foreach ($subs as $s) {
        $variant = (!empty($c['ab_enabled']) && $i % 2 === 1) ? 'B' : 'A';
        db_insert('email_campaign_recipients', [
            'campaign_id'   => $campaignId,
            'subscriber_id' => (int) $s['id'],
            'email'         => $s['email'],
            'name'          => $s['name'],
            'variant'       => $variant,
            'token'         => bin2hex(random_bytes(16)),
            'status'        => 'queued',
        ]);
        $i++;
    }
    db_update('email_campaigns', ['total' => count($subs)], 'id = :id', [':id' => $campaignId]);
    return count($subs);
}

/** Merge, click-wrap links, and append the unsubscribe footer + open pixel. */
function em_build_body(array $c, array $r): string
{
    $base = rtrim(APP_URL, '/');
    $body = mail_render((string) ($c['body'] ?? ''), ['name' => $r['name'] ?: 'Friend', 'email' => $r['email']]);

    // Click tracking — route http links through the redirector.
    $body = preg_replace_callback('#href="(https?://[^"]+)"#i', static function ($m) use ($base, $r) {
        return 'href="' . $base . '/email-track?c=click&t=' . $r['token'] . '&u=' . rawurlencode($m[1]) . '"';
    }, $body) ?? $body;

    $unsub = $base . '/unsubscribe?email=' . rawurlencode($r['email'])
           . '&t=' . newsletter_unsub_token((string) $r['email']);
    $prefs = $unsub . '&prefs=1';
    $site  = e(get_setting('site_name', SITE_NAME));
    $footer = '<p style="font-size:12px;color:#9ca3af;text-align:center;margin-top:24px;line-height:1.6;">'
            . 'You are receiving this because you subscribed to ' . $site . '.<br>'
            . '<a href="' . $unsub . '" style="color:#9ca3af;">Unsubscribe</a> &middot; '
            . '<a href="' . $prefs . '" style="color:#9ca3af;">Email preferences</a></p>';
    $pixel = '<img src="' . $base . '/email-track?c=open&t=' . $r['token'] . '" width="1" height="1" alt="" style="display:none">';

    return $body . $footer . $pixel;
}

/** Send one queued batch. Returns ['sent','failed','remaining']. */
function em_send_batch(int $campaignId, int $limit = 25): array
{
    $c = find('email_campaigns', $campaignId);
    if (!$c) return ['sent' => 0, 'failed' => 0, 'remaining' => 0];
    $profile = !empty($c['smtp_profile_id']) ? find('email_smtp_profiles', (int) $c['smtp_profile_id']) : null;

    $rows = db_all("SELECT * FROM email_campaign_recipients WHERE campaign_id = :c AND status = 'queued' ORDER BY id ASC LIMIT " . max(1, (int) $limit), [':c' => $campaignId]);
    $sent = 0; $failed = 0;
    foreach ($rows as $r) {
        $subject = ($r['variant'] === 'B' && !empty($c['subject_b'])) ? $c['subject_b'] : $c['subject'];
        $subject = mail_render($subject, ['name' => $r['name'] ?: 'Friend']);
        $html    = em_build_body($c, $r);
        $opts    = array_filter(['from_email' => $c['from_email'] ?: null, 'from_name' => $c['from_name'] ?: null], static fn ($v) => $v !== null);

        $ok = $profile
            ? em_smtp_send($profile, $r['email'], $subject, mail_layout($html, $subject), $opts)
            : send_mail($r['email'], $subject, $html, $opts);

        db_update('email_campaign_recipients', ['status' => $ok ? 'sent' : 'failed', 'sent_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => (int) $r['id']]);
        $ok ? $sent++ : $failed++;
        if ($ok && !empty($r['subscriber_id'])) {
            db_update('newsletter_subscribers', ['last_email_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => (int) $r['subscriber_id']]);
        }
    }

    em_recount($campaignId);
    $remaining = (int) db_value("SELECT COUNT(*) FROM email_campaign_recipients WHERE campaign_id = :c AND status = 'queued'", [':c' => $campaignId]);
    db_update('email_campaigns', ['status' => $remaining === 0 ? 'sent' : 'sending'], 'id = :id', [':id' => $campaignId]);
    return ['sent' => $sent, 'failed' => $failed, 'remaining' => $remaining];
}

/** Recompute a campaign's aggregate counters from its recipient rows. */
function em_recount(int $campaignId): void
{
    db_query(
        "UPDATE email_campaigns SET
            sent_count  = (SELECT COUNT(*) FROM email_campaign_recipients WHERE campaign_id = :a AND status IN ('sent','opened','clicked')),
            open_count  = (SELECT COUNT(*) FROM email_campaign_recipients WHERE campaign_id = :b AND status IN ('opened','clicked')),
            click_count = (SELECT COUNT(*) FROM email_campaign_recipients WHERE campaign_id = :c AND status = 'clicked'),
            fail_count  = (SELECT COUNT(*) FROM email_campaign_recipients WHERE campaign_id = :d AND status = 'failed')
         WHERE id = :e",
        [':a' => $campaignId, ':b' => $campaignId, ':c' => $campaignId, ':d' => $campaignId, ':e' => $campaignId]
    );
}

/**
 * Send one message through a specific SMTP profile (fsockopen). Returns bool.
 */
function em_smtp_send(array $p, string $to, string $subject, string $html, array $opts = []): bool
{
    $host = (string) $p['host'];
    if ($host === '') return false;
    $secure = $p['secure'] ?? 'tls';
    $fromEmail = $opts['from_email'] ?? ($p['from_email'] ?: get_setting('mail_from_email', MAIL_FROM_EMAIL));
    $fromName  = $opts['from_name']  ?? ($p['from_name'] ?: get_setting('mail_from_name', MAIL_FROM_NAME));

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @fsockopen($remote, (int) $p['port'], $errno, $errstr, 20);
    if (!$fp) { error_log("[em-smtp] connect: $errstr"); return false; }
    stream_set_timeout($fp, 20);
    $read = static function () use ($fp): string { $d = ''; while ($l = fgets($fp, 515)) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $d; };
    $cmd = static function (string $c) use ($fp, $read): string { fwrite($fp, $c . "\r\n"); return $read(); };

    $read();
    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $cmd($ehlo);
    if ($secure === 'tls') {
        $cmd('STARTTLS');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
        $cmd($ehlo);
    }
    if (!empty($p['username'])) {
        $cmd('AUTH LOGIN');
        $cmd(base64_encode((string) $p['username']));
        $auth = $cmd(base64_encode((string) $p['password']));
        if (!str_starts_with(trim($auth), '235')) { fclose($fp); error_log('[em-smtp] auth failed'); return false; }
    }
    $cmd('MAIL FROM:<' . $fromEmail . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $cmd('DATA');
    $headers  = 'From: ' . mail_encode_name($fromName) . " <$fromEmail>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $body = preg_replace('/^\./m', '..', $html);
    $result = $cmd($headers . "\r\n" . $body . "\r\n.");
    $cmd('QUIT');
    fclose($fp);
    return str_starts_with(trim($result), '250');
}
