<?php
/**
 * Email tracking endpoint.
 *   ?c=open&t=<token>          -> 1x1 gif, marks the campaign recipient opened
 *   ?c=click&t=<token>&u=<url> -> marks clicked, redirects to the real URL
 *   ?c=open&m=<token>          -> marks a mailbox message (email_messages) opened
 *   ?c=click&m=<token>&u=<url> -> mailbox message click, redirects
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/email_marketing.php';

$c = (string) get('c', 'open');
$t = preg_replace('/[^a-f0-9]/', '', (string) get('t', ''));
$m = preg_replace('/[^a-f0-9]/', '', (string) get('m', ''));

/* ---- Mailbox message tracking (email_messages via open_token) ---- */
if ($m !== '') {
    require_once __DIR__ . '/includes/email_center.php';
    $msg = db_row("SELECT id, from_email, to_email, open_count, click_count FROM email_messages WHERE open_token = :m LIMIT 1", [':m' => $m]);
    if ($msg) {
        if ($c === 'click') {
            $u = (string) get('u', '');
            db_query("UPDATE email_messages SET click_count = click_count + 1 WHERE id = :id", [':id' => (int) $msg['id']]);
            ec_log_event((int) $msg['id'], null, (string) $msg['to_email'], 'click', $u);
            header('Location: ' . (preg_match('#^https?://#i', $u) ? $u : url('/')), true, 302);
            exit;
        }
        db_query("UPDATE email_messages SET open_count = open_count + 1 WHERE id = :id", [':id' => (int) $msg['id']]);
        ec_log_event((int) $msg['id'], null, (string) $msg['to_email'], 'open');
    }
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

$row = $t !== '' ? db_row("SELECT * FROM email_campaign_recipients WHERE token = :t LIMIT 1", [':t' => $t]) : null;

/** Log campaign engagement to email_events for device/browser/geo analytics. */
$logEvent = static function (array $row, string $type, string $url = ''): void {
    require_once __DIR__ . '/includes/email_center.php';
    ec_log_event(null, (int) $row['campaign_id'], (string) $row['email'], $type, $url);
};

if ($c === 'click') {
    $u = (string) get('u', '');
    // Only honor the redirect when a valid recipient token actually matched —
    // otherwise this endpoint would be an open redirect on the trusted origin
    // (anyone could craft …/email-track?c=click&u=https://evil to phish).
    if ($row) {
        $set = ['status' => 'clicked', 'clicked_at' => date('Y-m-d H:i:s')];
        if (empty($row['opened_at'])) { $set['opened_at'] = date('Y-m-d H:i:s'); }
        db_update('email_campaign_recipients', $set, 'id = :id', [':id' => (int) $row['id']]);
        em_recount((int) $row['campaign_id']);
        $logEvent($row, 'click', $u);
        header('Location: ' . (preg_match('#^https?://#i', $u) ? $u : url('/')), true, 302);
        exit;
    }
    header('Location: ' . url('/'), true, 302);
    exit;
}

// open pixel
if ($row && in_array($row['status'], ['sent', 'queued'], true)) {
    db_update('email_campaign_recipients', ['status' => 'opened', 'opened_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => (int) $row['id']]);
    em_recount((int) $row['campaign_id']);
    $logEvent($row, 'open');
}
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit;
