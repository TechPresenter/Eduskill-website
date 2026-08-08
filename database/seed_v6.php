<?php
/**
 * =============================================================================
 *  Schema v6 seeder (run once, after schema_v6.sql)
 * =============================================================================
 *  - Inserts the four membership email templates (skips any that exist).
 *  - Generates the cron trigger token if it is still blank.
 *  - Back-fills membership_code + qr_token + join_date onto existing members
 *    so cards and QR verification work for everyone immediately.
 *
 *  Run:  C:\xampp\php\php.exe database\seed_v6.php
 *  Safe to run more than once.
 * =============================================================================
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config.php';
require $root . '/includes/database.php';
require $root . '/includes/functions.php';
require $root . '/includes/helper.php';
require $root . '/includes/membership.php';

$cli = PHP_SAPI === 'cli';
$nl  = $cli ? "\n" : "<br>";
$say = static function (string $m) use ($nl) { echo $m . $nl; };

/* -------------------------------------------------- 1. Email templates */
$templates = [
    [
        'slug'    => 'membership_welcome',
        'name'    => 'Membership — Welcome / Activated',
        'subject' => 'Welcome to {{site_name}} — your membership is active',
        'variables' => 'name, member_code, tier, valid_till, verify_url, site_name',
        'body'    => '<p>Hi {{name}},</p>'
            . '<p>Welcome aboard! Your <strong>{{tier}}</strong> membership with {{site_name}} is now active.</p>'
            . '<table style="margin:14px 0;font-size:15px;">'
            . '<tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Membership ID</td><td><strong>{{member_code}}</strong></td></tr>'
            . '<tr><td style="padding:4px 14px 4px 0;color:#6b7280;">Valid until</td><td><strong>{{valid_till}}</strong></td></tr>'
            . '</table>'
            . '<p>You can view and download your digital membership card any time from your account.</p>'
            . '<p><a href="{{verify_url}}" style="display:inline-block;padding:11px 20px;background:#063566;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">View my card</a></p>'
            . '<p>Thank you for standing with us.</p>',
    ],
    [
        'slug'    => 'membership_expiry_reminder',
        'name'    => 'Membership — Expiry Reminder',
        'subject' => 'Your {{site_name}} membership expires in {{days_left}} days',
        'variables' => 'name, member_code, tier, valid_till, days_left, renew_url, site_name',
        'body'    => '<p>Hi {{name}},</p>'
            . '<p>This is a friendly reminder that your <strong>{{tier}}</strong> membership (ID {{member_code}}) '
            . 'expires on <strong>{{valid_till}}</strong> — that is <strong>{{days_left}} days</strong> away.</p>'
            . '<p>Renew now to keep your benefits without interruption.</p>'
            . '<p><a href="{{renew_url}}" style="display:inline-block;padding:11px 20px;background:#58A42F;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Renew membership</a></p>'
            . '<p>If you have already renewed, please ignore this message.</p>',
    ],
    [
        'slug'    => 'membership_renewed',
        'name'    => 'Membership — Renewed',
        'subject' => 'Membership renewed — valid until {{valid_till}}',
        'variables' => 'name, member_code, tier, valid_till, verify_url, site_name',
        'body'    => '<p>Hi {{name}},</p>'
            . '<p>Thank you! Your {{tier}} membership (ID {{member_code}}) has been renewed and is now '
            . 'valid until <strong>{{valid_till}}</strong>.</p>'
            . '<p><a href="{{verify_url}}" style="display:inline-block;padding:11px 20px;background:#063566;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">View my card</a></p>',
    ],
    [
        'slug'    => 'membership_expired',
        'name'    => 'Membership — Expired',
        'subject' => 'Your {{site_name}} membership has expired',
        'variables' => 'name, member_code, tier, renew_url, site_name',
        'body'    => '<p>Hi {{name}},</p>'
            . '<p>Your {{tier}} membership (ID {{member_code}}) has expired. We would love to have you continue with us.</p>'
            . '<p><a href="{{renew_url}}" style="display:inline-block;padding:11px 20px;background:#58A42F;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Reactivate membership</a></p>',
    ],
];

$added = 0;
foreach ($templates as $tpl) {
    if (find_by('email_templates', 'slug', $tpl['slug'])) {
        continue;
    }
    db_insert('email_templates', [
        'name'      => $tpl['name'],
        'slug'      => $tpl['slug'],
        'subject'   => $tpl['subject'],
        'body'      => $tpl['body'],
        'variables' => $tpl['variables'],
        'status'    => 1,
    ]);
    $added++;
}
$say("Email templates: $added added (" . count($templates) . " total).");

/* -------------------------------------------------- 2. Cron token */
if ((string) get_setting('membership_cron_token', '') === '') {
    $token = bin2hex(random_bytes(20));
    set_setting('membership_cron_token', $token, 'membership', 'text');
    $say("Cron token generated: $token");
} else {
    $say("Cron token already set.");
}

/* -------------------------------------------------- 3. Back-fill members */
$members = db_all('SELECT id, member_code, qr_token, join_date, created_at FROM members');
$touched = 0;
foreach ($members as $m) {
    $update = [];
    if (empty($m['member_code'])) {
        $update['member_code'] = member_generate_code((int) $m['id']);
    }
    if (empty($m['qr_token'])) {
        $update['qr_token'] = member_new_qr_token();
    }
    if (empty($m['join_date']) && !empty($m['created_at'])) {
        $update['join_date'] = date('Y-m-d', strtotime((string) $m['created_at']));
    }
    if ($update) {
        db_update('members', $update, 'id = :id', [':id' => (int) $m['id']]);
        $touched++;
    }
}
$say("Members back-filled: $touched of " . count($members) . ".");
$say("Seed complete.");
