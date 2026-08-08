<?php
/**
 * =============================================================================
 *  Cron — Membership expiry reminders
 * =============================================================================
 *  Sends email (and SMS, if enabled) reminders at the configured thresholds
 *  (default 30/15/7 days before expiry) and an "expired" notice when a term
 *  lapses. Idempotent: every send is logged in membership_reminders with a
 *  unique (member, expiry_date, stage, channel) key, so re-running is safe.
 *
 *  Run daily. Two ways:
 *    CLI:  C:\xampp\php\php.exe C:\xampp\htdocs\pwf\cron\membership-reminders.php
 *    URL:  https://your-site/cron/membership-reminders.php?token=<cron token>
 *
 *  The token lives in Admin → Membership Settings.
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/sms.php';

@set_time_limit(0);
ignore_user_abort(true);

$isCli = PHP_SAPI === 'cli';

/* ---- Access control for web triggers ---- */
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $token    = (string) get('token', '');
    $expected = (string) get_setting('membership_cron_token', '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

$log = static function (string $m) use ($isCli): void {
    echo $m . ($isCli ? "\n" : "<br>\n");
};

if ((int) get_setting('membership_reminders_enabled', '1') !== 1) {
    $log('Reminders are disabled in settings. Nothing to do.');
    exit;
}

$siteName = get_setting('site_name', SITE_NAME);
$today    = date('Y-m-d');
$smsOn    = sms_enabled();
$sent     = ['email' => 0, 'sms' => 0, 'skipped' => 0];

/**
 * Record + guard a single reminder. Returns true if it should be sent now
 * (i.e. not already logged for this member/expiry/stage/channel).
 */
$already = static function (int $memberId, string $expiry, int $stage, string $channel): bool {
    return (bool) db_row(
        'SELECT id FROM membership_reminders WHERE member_id = :m AND expiry_date = :e AND stage = :s AND channel = :c LIMIT 1',
        [':m' => $memberId, ':e' => $expiry, ':s' => $stage, ':c' => $channel]
    );
};
$record = static function (int $memberId, string $expiry, int $stage, string $channel, bool $ok): void {
    db_insert('membership_reminders', [
        'member_id'   => $memberId,
        'expiry_date' => $expiry,
        'stage'       => $stage,
        'channel'     => $channel,
        'status'      => $ok ? 'sent' : 'failed',
    ]);
};

/** Build the merge vars for a member. */
$varsFor = static function (array $m) use ($siteName): array {
    $plan = membership_plan((int) ($m['plan_id'] ?? 0));
    return [
        'name'        => $m['name'],
        'member_code' => $m['member_code'],
        'tier'        => $plan['name'] ?? 'Member',
        'valid_till'  => $m['expiry_date'] ? format_date($m['expiry_date'], 'd M Y') : '',
        'days_left'   => (string) max(0, (int) member_days_left($m)),
        'renew_url'   => abs_url('membership-renew'),
        'verify_url'  => member_verify_url($m),
        'site_name'   => $siteName,
    ];
};

/* =============================================================================
 |  1. UPCOMING EXPIRY REMINDERS (stages: 30, 15, 7, …)
 |============================================================================*/
// Disjoint, contiguous windows per stage so a skipped cron day is caught up on
// the next run (and no member is matched by two stages). membership_reminder_days()
// is sorted descending, e.g. [30, 15, 7] → windows (16..30], (8..15], [0..7].
$stages = membership_reminder_days();
foreach ($stages as $i => $stage) {
    $upper       = date('Y-m-d', strtotime($today . ' +' . $stage . ' days'));
    $nextSmaller = $stages[$i + 1] ?? -1;                 // -1 → lower bound = today
    $lower       = date('Y-m-d', strtotime($today . ' +' . ($nextSmaller + 1) . ' days'));
    $members = db_all(
        "SELECT * FROM members
          WHERE membership_status = 'active' AND expiry_date BETWEEN :lo AND :hi
            AND email IS NOT NULL AND email <> ''",
        [':lo' => $lower, ':hi' => $upper]
    );
    foreach ($members as $m) {
        $mid  = (int) $m['id'];
        $exp  = (string) $m['expiry_date']; // idempotency keyed on the real expiry
        $vars = $varsFor($m);

        if (!$already($mid, $exp, $stage, 'email')) {
            $ok = send_template('membership_expiry_reminder', $m['email'], $vars);
            $record($mid, $exp, $stage, 'email', $ok);
            $sent['email'] += $ok ? 1 : 0;
        } else {
            $sent['skipped']++;
        }

        if ($smsOn && !empty($m['phone']) && !$already($mid, $exp, $stage, 'sms')) {
            $text = "Hi {$vars['name']}, your {$siteName} membership {$vars['member_code']} expires on {$vars['valid_till']} ({$vars['days_left']} days). Renew soon.";
            $res  = send_sms($m['phone'], $text);
            // Tolerate either SMS-layer contract ('success' here, 'ok' in
            // messaging.php) — a NULL here is a fatal TypeError under strict_types.
            $smsOk = (bool) ($res['success'] ?? $res['ok'] ?? false);
            $record($mid, $exp, $stage, 'sms', $smsOk);
            $sent['sms'] += $smsOk ? 1 : 0;
        }
    }
    $log("Stage {$stage}d (expiry ≤ {$upper}): " . count($members) . ' member(s) processed.');
}

/* =============================================================================
 |  2. EXPIRY / LAPSE — flip status and notify recently-expired members
 |============================================================================*/
$expired = db_all(
    "SELECT * FROM members
      WHERE membership_status = 'active' AND expiry_date IS NOT NULL AND expiry_date < :today",
    [':today' => $today]
);
$flipped = 0;
foreach ($expired as $m) {
    $mid = (int) $m['id'];
    db_update('members', ['membership_status' => 'expired'], 'id = :id', [':id' => $mid]);
    member_audit_log($mid, 'expired', ['membership_status' => ['active', 'expired']], 'Membership lapsed (cron)', null);
    $flipped++;

    // Notify only those expired within the last 3 days (avoid back-dated spam).
    $recentlyExpired = strtotime((string) $m['expiry_date']) >= strtotime($today . ' -3 days');
    if (!$recentlyExpired) {
        continue;
    }
    $exp  = (string) $m['expiry_date'];
    $vars = $varsFor($m);
    if (!empty($m['email']) && !$already($mid, $exp, 0, 'email')) {
        $ok = send_template('membership_expired', $m['email'], $vars);
        $record($mid, $exp, 0, 'email', $ok);
        $sent['email'] += $ok ? 1 : 0;
    }
    if ($smsOn && !empty($m['phone']) && !$already($mid, $exp, 0, 'sms')) {
        $text = "Hi {$vars['name']}, your {$siteName} membership {$vars['member_code']} has expired. Renew: {$vars['renew_url']}";
        $res   = send_sms($m['phone'], $text);
        $smsOk = (bool) ($res['success'] ?? $res['ok'] ?? false);
        $record($mid, $exp, 0, 'sms', $smsOk);
        $sent['sms'] += $smsOk ? 1 : 0;
    }
}
$log("Expiry sweep: {$flipped} membership(s) marked expired.");

log_activity('cron', 'membership', "Reminders: {$sent['email']} emails, {$sent['sms']} SMS, {$flipped} expired");
$log("Done — {$sent['email']} email(s), {$sent['sms']} SMS sent, {$sent['skipped']} already-sent skipped.");
