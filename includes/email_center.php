<?php
/**
 * =============================================================================
 *  Email Center — domain layer for the Email Marketing & Management module.
 * =============================================================================
 *  Powers the unified mailbox (email_messages), composer (send / draft /
 *  schedule / outbox), signatures & accounts, the 26-template library,
 *  contacts, and analytics (email_events). Sends through the existing SMTP
 *  layer (email_accounts → em_smtp_send() or the global send_mail()).
 *
 *  Loaded on demand by the admin email pages + the email-track endpoint
 *  (NOT bootstrap — keeps the front-of-site light). Requires includes/
 *  email_marketing.php for em_smtp_send()/mail_render()/mail_layout().
 * =============================================================================
 */

declare(strict_types=1);

if (!function_exists('em_smtp_send')) {
    require_once __DIR__ . '/email_marketing.php';
}

/* =============================================================================
 |  FOLDERS & COUNTS
 |============================================================================*/

/** Canonical folder metadata: slug => [label, lucide icon]. */
function ec_folders(): array
{
    return [
        'inbox'     => ['Inbox',     'inbox'],
        'sent'      => ['Sent',      'send'],
        'drafts'    => ['Drafts',    'file-text'],
        'outbox'    => ['Outbox',    'upload'],
        'scheduled' => ['Scheduled', 'clock'],
        'archive'   => ['Archived',  'archive'],
        'spam'      => ['Spam',      'shield-alert'],
        'trash'     => ['Trash',     'trash-2'],
    ];
}

/** Per-folder message counts (+ unread for inbox, star/important pseudo-folders). */
function ec_folder_counts(): array
{
    $rows = db_all("SELECT folder, COUNT(*) AS n, SUM(is_read = 0) AS unread
                    FROM email_messages WHERE deleted_at IS NULL GROUP BY folder");
    $out = [];
    foreach (ec_folders() as $slug => $_) { $out[$slug] = ['total' => 0, 'unread' => 0]; }
    foreach ($rows as $r) {
        $out[$r['folder']] = ['total' => (int) $r['n'], 'unread' => (int) $r['unread']];
    }
    $out['starred']   = ['total' => (int) db_value("SELECT COUNT(*) FROM email_messages WHERE is_starred = 1 AND folder <> 'trash' AND deleted_at IS NULL")];
    $out['important'] = ['total' => (int) db_value("SELECT COUNT(*) FROM email_messages WHERE is_important = 1 AND folder <> 'trash' AND deleted_at IS NULL")];
    return $out;
}

/**
 * Query a mailbox view. $view is a folder slug, or 'starred'|'important'|
 * 'incoming'|'outgoing'|'label:<id>'. Returns paginate() output.
 */
function ec_mailbox(string $view, array $opts = []): array
{
    $where  = ['m.deleted_at IS NULL'];
    $params = [];
    $join   = '';

    if (str_starts_with($view, 'label:')) {
        $join    = ' JOIN email_message_labels ml ON ml.message_id = m.id';
        $where[] = 'ml.label_id = :lbl';
        $where[] = "m.folder <> 'trash'";
        $params[':lbl'] = (int) substr($view, 6);
    } elseif ($view === 'starred') {
        $where[] = 'm.is_starred = 1';
        $where[] = "m.folder <> 'trash'";
    } elseif ($view === 'important') {
        $where[] = 'm.is_important = 1';
        $where[] = "m.folder <> 'trash'";
    } elseif ($view === 'incoming') {
        $where[] = "m.direction = 'incoming'";
        $where[] = "m.folder NOT IN ('trash','spam')";
    } elseif ($view === 'outgoing') {
        $where[] = "m.direction = 'outgoing'";
        $where[] = "m.folder NOT IN ('trash','drafts')";
    } else {
        $where[] = 'm.folder = :folder';
        $params[':folder'] = array_key_exists($view, ec_folders()) ? $view : 'inbox';
    }

    if (!empty($opts['q'])) {
        $where[] = "CONCAT_WS(' ', m.subject, m.from_email, m.from_name, m.to_email, m.preview) LIKE :q";
        $params[':q'] = '%' . $opts['q'] . '%';
    }
    if (!empty($opts['unread'])) { $where[] = 'm.is_read = 0'; }

    $order = ($view === 'scheduled') ? 'm.scheduled_at ASC' : 'COALESCE(m.sent_at, m.created_at) DESC';
    $sql = "SELECT m.* FROM email_messages m$join WHERE " . implode(' AND ', $where) . " ORDER BY $order";
    return paginate($sql, $params, (int) ($opts['per'] ?? 25));
}

/* =============================================================================
 |  MESSAGE STATE (star / important / read / move)
 |============================================================================*/

function ec_message(int $id): ?array
{
    $m = find('email_messages', $id);
    return ($m && $m['deleted_at'] === null) ? $m : null;
}

/** Labels attached to a message. */
function ec_message_labels(int $id): array
{
    return db_all("SELECT l.* FROM email_labels l
                   JOIN email_message_labels ml ON ml.label_id = l.id
                   WHERE ml.message_id = :m ORDER BY l.sort_order", [':m' => $id]);
}

/** All messages in a thread (oldest first) for the conversation view. */
function ec_thread(string $threadId): array
{
    if ($threadId === '') return [];
    return db_all("SELECT * FROM email_messages WHERE thread_id = :t AND deleted_at IS NULL ORDER BY created_at ASC", [':t' => $threadId]);
}

function ec_mark_read(int $id, bool $read = true): void
{
    db_update('email_messages', ['is_read' => $read ? 1 : 0], 'id = :id', [':id' => $id]);
}

function ec_toggle(int $id, string $flag): void
{
    if (!in_array($flag, ['is_starred', 'is_important', 'is_read'], true)) return;
    db_query("UPDATE email_messages SET `$flag` = 1 - `$flag` WHERE id = :id", [':id' => $id]);
}

/** Move a message to a folder. Delete = soft-delete when already in trash. */
function ec_move(int $id, string $folder): void
{
    if ($folder === 'delete') {
        $m = find('email_messages', $id);
        if ($m && $m['folder'] === 'trash') {
            db_update('email_messages', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);
        } else {
            db_update('email_messages', ['folder' => 'trash'], 'id = :id', [':id' => $id]);
        }
        return;
    }
    if (array_key_exists($folder, ec_folders())) {
        db_update('email_messages', ['folder' => $folder], 'id = :id', [':id' => $id]);
    }
}

/** Apply a bulk action to a set of ids. Returns count touched. */
function ec_bulk(array $ids, string $action): int
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return 0;
    foreach ($ids as $id) {
        match ($action) {
            'read'      => ec_mark_read($id, true),
            'unread'    => ec_mark_read($id, false),
            'star'      => db_update('email_messages', ['is_starred' => 1], 'id = :id', [':id' => $id]),
            'unstar'    => db_update('email_messages', ['is_starred' => 0], 'id = :id', [':id' => $id]),
            'important' => db_update('email_messages', ['is_important' => 1], 'id = :id', [':id' => $id]),
            'archive'   => ec_move($id, 'archive'),
            'spam'      => ec_move($id, 'spam'),
            'trash'     => ec_move($id, 'trash'),
            'delete'    => ec_move($id, 'delete'),
            'inbox'     => ec_move($id, 'inbox'),
            default     => null,
        };
    }
    return count($ids);
}

/* =============================================================================
 |  COMPOSE / SEND / SCHEDULE
 |============================================================================*/

/** Default sending account (or null). */
function ec_default_account(): ?array
{
    return db_row("SELECT * FROM email_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC LIMIT 1");
}

/** Default signature HTML (or ''). */
function ec_default_signature(): string
{
    $s = db_row("SELECT body_html FROM email_signatures ORDER BY is_default DESC, id ASC LIMIT 1");
    return $s['body_html'] ?? '';
}

/** A short plain-text preview from HTML. */
function ec_preview(string $html): string
{
    $t = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    return mb_substr($t, 0, 180);
}

/**
 * Persist a composed message. $data keys: to_email, cc, bcc, subject, body_html,
 * from_name, from_email, reply_to, priority, read_receipt, folder ('drafts'|
 * 'outbox'|'scheduled'|'sent'), scheduled_at, account_id, attachments (array),
 * thread_id, in_reply_to. Returns the message id.
 */
function ec_save_message(array $data, ?int $id = null): int
{
    $html = (string) ($data['body_html'] ?? '');
    $priority = $data['priority'] ?? 'normal';
    if (!in_array($priority, ['low', 'normal', 'high'], true)) { $priority = 'normal'; }
    $row = [
        'account_id'   => $data['account_id']   ?? null,
        'thread_id'    => $data['thread_id']     ?? null,
        'in_reply_to'  => $data['in_reply_to']   ?? null,
        'direction'    => 'outgoing',
        'folder'       => $data['folder']        ?? 'drafts',
        'from_name'    => $data['from_name']     ?? null,
        'from_email'   => $data['from_email']    ?? null,
        'to_email'     => $data['to_email']      ?? null,
        'cc'           => $data['cc']            ?? null,
        'bcc'          => $data['bcc']           ?? null,
        'reply_to'     => $data['reply_to']      ?? null,
        'subject'      => $data['subject']       ?? null,
        'preview'      => ec_preview($html),
        'body_html'    => $html,
        'body_text'    => trim(strip_tags($html)),
        'priority'     => $priority,
        'read_receipt' => !empty($data['read_receipt']) ? 1 : 0,
        'scheduled_at' => $data['scheduled_at']  ?? null,
        'attachments'  => !empty($data['attachments']) ? json_encode(array_values($data['attachments'])) : null,
        'has_attachments' => !empty($data['attachments']) ? 1 : 0,
    ];
    if ($id) {
        db_update('email_messages', $row, 'id = :id', [':id' => $id]);
        return $id;
    }
    if (empty($row['thread_id'])) {
        $row['thread_id'] = substr(bin2hex(random_bytes(8)), 0, 16);
    }
    $row['open_token'] = bin2hex(random_bytes(12));
    $row['created_by'] = function_exists('current_user_id') ? current_user_id() : null;
    return db_insert('email_messages', $row);
}

/**
 * Actually deliver a stored message via its account's SMTP (or global mailer),
 * move it to Sent, and log a 'sent' event. Returns true on success.
 */
function ec_deliver(int $id): bool
{
    $m = find('email_messages', $id);
    if (!$m) return false;

    $account = !empty($m['account_id']) ? find('email_accounts', (int) $m['account_id']) : ec_default_account();
    $fromEmail = $m['from_email'] ?: ($account['email'] ?? get_setting('mail_from_email', MAIL_FROM_EMAIL));
    $fromName  = $m['from_name']  ?: ($account['name']  ?? get_setting('mail_from_name', MAIL_FROM_NAME));

    // The composer already owns the signature (it is part of the editable body),
    // so we do NOT re-append it here — only add the open-tracking pixel.
    $base = rtrim(APP_URL, '/');
    $html = (string) $m['body_html'];
    if (!empty($m['open_token'])) {
        $html .= '<img src="' . $base . '/email-track?c=open&m=' . $m['open_token'] . '" width="1" height="1" alt="" style="display:none">';
    }

    $opts = array_filter([
        'from_email' => $fromEmail,
        'from_name'  => $fromName,
        'reply_to'   => $m['reply_to'] ?: ($account['reply_to'] ?? null),
        'cc'         => $m['cc'] ?: null,
        'bcc'        => $m['bcc'] ?: null,
    ], static fn ($v) => $v !== null && $v !== '');

    // Only genuinely-valid recipients count — an all-invalid To must NOT be
    // silently marked "sent". No valid address at all → back to Outbox.
    $valid = array_values(array_filter(
        array_map('trim', preg_split('/[,;]+/', (string) $m['to_email'])),
        'is_email'
    ));
    if (!$valid) {
        db_update('email_messages', [
            'folder' => 'outbox', 'sent_at' => null,
            'error_text' => 'No valid recipient address.',
        ], 'id = :id', [':id' => $id]);
        return false;
    }

    $ok = true;
    foreach ($valid as $to) {
        $sent = ($account && !empty($account['smtp_host']))
            ? em_smtp_send(ec_account_smtp($account), $to, (string) $m['subject'], mail_layout($html, (string) $m['subject']), $opts)
            : send_mail($to, (string) $m['subject'], $html, $opts);
        $ok = $ok && $sent;
        ec_log_event($id, null, $to, $sent ? 'sent' : 'fail');
        if ($sent) {
            // Keep CRM "last contacted" accurate for known contacts.
            db_query("UPDATE contacts SET last_contacted_at = NOW() WHERE email = :e", [':e' => $to]);
        }
    }

    db_update('email_messages', [
        'folder'  => $ok ? 'sent' : 'outbox',
        'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
        'is_read' => 1,
        'error_text' => $ok ? null : 'Delivery failed — left in Outbox to retry.',
    ], 'id = :id', [':id' => $id]);

    return $ok;
}

/** Adapt an email_accounts row to the shape em_smtp_send() expects. */
function ec_account_smtp(array $a): array
{
    return [
        'host'       => $a['smtp_host'] ?? '',
        'port'       => $a['smtp_port'] ?? 587,
        'secure'     => $a['smtp_secure'] ?? 'tls',
        'username'   => $a['smtp_user'] ?? '',
        'password'   => ec_secret_read($a['smtp_pass'] ?? ''),
        'from_email' => $a['email'] ?? '',
        'from_name'  => $a['name'] ?? '',
    ];
}

/** Process due scheduled messages + retry the outbox. Returns [sent,failed]. */
function ec_process_queue(int $limit = 50): array
{
    $due = db_all("SELECT id FROM email_messages
                   WHERE deleted_at IS NULL AND (
                        (folder = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW())
                     OR folder = 'outbox')
                   ORDER BY COALESCE(scheduled_at, created_at) ASC LIMIT " . max(1, $limit));
    $sent = 0; $failed = 0;
    foreach ($due as $r) {
        ec_deliver((int) $r['id']) ? $sent++ : $failed++;
    }
    return ['sent' => $sent, 'failed' => $failed];
}

/* =============================================================================
 |  SECRETS (encrypt account passwords at rest via the Security Center key)
 |============================================================================*/

/** Encrypt a secret for storage (falls back to plaintext if encryption is off). */
function ec_secret_write(string $plain): string
{
    if ($plain === '') return '';
    if (function_exists('sec_encrypt') && function_exists('sec_has_encryption') && sec_has_encryption()) {
        return sec_encrypt($plain) ?? $plain;
    }
    return $plain;
}

/** Decrypt a stored secret (handles both encrypted + legacy plaintext). */
function ec_secret_read(?string $stored): string
{
    $stored = (string) $stored;
    if ($stored === '') return '';
    if (function_exists('sec_is_encrypted') && sec_is_encrypted($stored)) {
        return (function_exists('sec_decrypt') ? (sec_decrypt($stored) ?? '') : '');
    }
    return $stored;
}

/* =============================================================================
 |  ANALYTICS
 |============================================================================*/

/** Record an email event (open/click/etc.) with parsed device/geo. */
function ec_log_event(?int $messageId, ?int $campaignId, ?string $recipient, string $type, string $url = ''): void
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    [$device, $os, $browser] = ec_parse_ua($ua);
    try {
        db_insert('email_events', [
            'message_id'  => $messageId,
            'campaign_id' => $campaignId,
            'recipient'   => $recipient,
            'event_type'  => $type,
            'url'         => $url ?: null,
            'device'      => $device,
            'os'          => $os,
            'browser'     => $browser,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => mb_substr($ua, 0, 255),
        ]);
    } catch (Throwable $e) { error_log('[ec_log_event] ' . $e->getMessage()); }
}

/** Very small UA parser → [device, os, browser]. */
function ec_parse_ua(string $ua): array
{
    $device = preg_match('/Mobile|Android|iPhone|iPod/i', $ua) ? 'mobile'
            : (preg_match('/iPad|Tablet/i', $ua) ? 'tablet' : 'desktop');
    $os = 'Other';
    foreach (['Windows' => 'Windows', 'Macintosh|Mac OS' => 'macOS', 'Android' => 'Android',
              'iPhone|iPad|iOS' => 'iOS', 'Linux' => 'Linux'] as $rx => $name) {
        if (preg_match("/$rx/i", $ua)) { $os = $name; break; }
    }
    $browser = 'Other';
    foreach (['Edg' => 'Edge', 'OPR|Opera' => 'Opera', 'Chrome' => 'Chrome',
              'Firefox' => 'Firefox', 'Safari' => 'Safari'] as $rx => $name) {
        if (preg_match("/$rx/i", $ua)) { $browser = $name; break; }
    }
    return [$device, $os, $browser];
}

/**
 * Unified dashboard stats across campaigns + the mailbox. Returns the 8 KPIs
 * the spec asks for plus deliverability rates.
 */
function ec_dashboard_stats(): array
{
    $camp = db_row("SELECT
        COALESCE(SUM(sent_count),0)  AS sent,
        COALESCE(SUM(open_count),0)  AS opened,
        COALESCE(SUM(click_count),0) AS clicked,
        COALESCE(SUM(fail_count),0)  AS failed
        FROM email_campaigns") ?: [];

    $mailSent = (int) db_value("SELECT COUNT(*) FROM email_messages WHERE folder = 'sent' AND deleted_at IS NULL");
    $ev = db_all("SELECT event_type, COUNT(*) n FROM email_events GROUP BY event_type");
    $evc = [];
    foreach ($ev as $r) { $evc[$r['event_type']] = (int) $r['n']; }

    $sent      = (int) ($camp['sent'] ?? 0) + $mailSent;
    $opened    = (int) ($camp['opened'] ?? 0) + ($evc['open'] ?? 0);
    $clicked   = (int) ($camp['clicked'] ?? 0) + ($evc['click'] ?? 0);
    $failed    = (int) ($camp['failed'] ?? 0) + ($evc['fail'] ?? 0);
    $bounced   = $evc['bounce'] ?? 0;
    $spam      = $evc['spam'] ?? 0;
    $unsub     = (int) db_value("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'unsubscribed'");
    $delivered = max(0, $sent - $bounced - $failed);

    $pct = static fn(int $a, int $b): float => $b > 0 ? round($a / $b * 100, 1) : 0.0;
    return [
        'sent'       => $sent,
        'delivered'  => $delivered,
        'opened'     => $opened,
        'clicked'    => $clicked,
        'bounced'    => $bounced,
        'failed'     => $failed,
        'spam'       => $spam,
        'unsubscribed' => $unsub,
        'open_rate'  => $pct($opened, max(1, $delivered)),
        'click_rate' => $pct($clicked, max(1, $delivered)),
        'bounce_rate'=> $pct($bounced, max(1, $sent)),
        'deliver_rate'=> $pct($delivered, max(1, $sent)),
    ];
}

/** Daily send/open/click series for the last N days (dashboard chart). */
function ec_dashboard_series(int $days = 14): array
{
    $rows = db_all("SELECT DATE(created_at) d,
                      SUM(event_type = 'sent')  sent,
                      SUM(event_type = 'open')  opened,
                      SUM(event_type = 'click') clicked
                    FROM email_events
                    WHERE created_at >= (CURRENT_DATE - INTERVAL :d DAY)
                    GROUP BY DATE(created_at) ORDER BY d", [':d' => $days]);
    $map = [];
    foreach ($rows as $r) { $map[$r['d']] = $r; }
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i day"));
        $out[] = [
            'date'    => $day,
            'label'   => date('d M', strtotime($day)),
            'sent'    => (int) ($map[$day]['sent'] ?? 0),
            'opened'  => (int) ($map[$day]['opened'] ?? 0),
            'clicked' => (int) ($map[$day]['clicked'] ?? 0),
        ];
    }
    return $out;
}

/** Breakdown for analytics pie/bars: 'device' | 'browser' | 'os' | 'country'. */
function ec_breakdown(string $dim): array
{
    if (!in_array($dim, ['device', 'browser', 'os', 'country'], true)) return [];
    return db_all("SELECT COALESCE(NULLIF(`$dim`,''),'Unknown') AS k, COUNT(*) AS n
                   FROM email_events WHERE event_type IN ('open','click')
                   GROUP BY k ORDER BY n DESC LIMIT 12");
}

/* =============================================================================
 |  TEMPLATE LIBRARY
 |============================================================================*/

/** Templates grouped by category for the library grid. */
function ec_templates_by_category(): array
{
    $rows = db_all("SELECT * FROM email_templates ORDER BY category, sort_order, name");
    $out = [];
    foreach ($rows as $r) { $out[$r['category'] ?: 'Other'][] = $r; }
    return $out;
}

/* =============================================================================
 |  IMAP / POP3 READER  (dormant until the imap extension is enabled)
 |============================================================================*/

/** True when live incoming-mail sync is possible on this server. */
function ec_imap_available(): bool
{
    return extension_loaded('imap');
}

/**
 * Fetch recent messages from an account's IMAP mailbox into email_messages.
 * No-ops gracefully (returns a status array) when the imap extension is off or
 * the account has no IMAP config — the Inbox then simply shows what's stored.
 */
function ec_imap_sync(array $account, int $limit = 30): array
{
    if (!ec_imap_available()) {
        return ['ok' => false, 'reason' => 'imap-ext-missing', 'fetched' => 0];
    }
    if (empty($account['imap_host'])) {
        return ['ok' => false, 'reason' => 'not-configured', 'fetched' => 0];
    }
    $flags = '/imap';
    if (($account['imap_secure'] ?? 'ssl') === 'ssl') { $flags .= '/ssl'; }
    elseif (($account['imap_secure'] ?? '') === 'tls') { $flags .= '/tls'; }
    $mailbox = '{' . $account['imap_host'] . ':' . (int) ($account['imap_port'] ?: 993) . $flags . '}INBOX';

    $fn_open = 'imap_open';
    $conn = @$fn_open($mailbox, (string) ($account['imap_user'] ?? ''), ec_secret_read($account['imap_pass'] ?? ''), 0, 1);
    if (!$conn) {
        return ['ok' => false, 'reason' => 'connect-failed', 'error' => function_exists('imap_last_error') ? imap_last_error() : '', 'fetched' => 0];
    }
    $total   = (int) call_user_func('imap_num_msg', $conn);
    $start   = max(1, $total - $limit + 1);
    $fetched = 0;
    for ($i = $total; $i >= $start; $i--) {
        $ov = call_user_func('imap_fetch_overview', $conn, (string) $i, 0);
        if (!$ov || !isset($ov[0])) { continue; }
        $o   = $ov[0];
        $uid = (string) ($o->message_id ?? ($account['id'] . '-' . $i));
        if (db_value("SELECT id FROM email_messages WHERE message_uid = :u LIMIT 1", [':u' => $uid])) { continue; }
        $body = (string) call_user_func('imap_fetchbody', $conn, (string) $i, '1');
        $from = ec_imap_decode((string) ($o->from ?? ''));
        db_insert('email_messages', [
            'account_id'  => $account['id'],
            'message_uid' => $uid,
            'direction'   => 'incoming',
            'folder'      => 'inbox',
            'from_email'  => ec_imap_addr($from),
            'from_name'   => $from,
            'to_email'    => $account['email'],
            'subject'     => ec_imap_decode((string) ($o->subject ?? '(no subject)')),
            'preview'     => ec_preview($body),
            'body_html'   => nl2br(e($body)),
            'body_text'   => $body,
            'is_read'     => !empty($o->seen) ? 1 : 0,
            'sent_at'     => isset($o->date) ? date('Y-m-d H:i:s', strtotime($o->date)) : null,
            'thread_id'   => substr(md5($uid), 0, 16),
        ]);
        $fetched++;
    }
    call_user_func('imap_close', $conn);
    db_update('email_accounts', ['last_sync_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => (int) $account['id']]);
    return ['ok' => true, 'fetched' => $fetched];
}

function ec_imap_decode(string $s): string
{
    if (!function_exists('imap_mime_header_decode')) return $s;
    $out = '';
    foreach (imap_mime_header_decode($s) as $p) { $out .= $p->text; }
    return $out;
}

function ec_imap_addr(string $from): string
{
    return preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : trim($from);
}
