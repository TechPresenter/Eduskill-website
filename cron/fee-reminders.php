<?php
/**
 * =============================================================================
 *  Cron — School fee due reminders
 * =============================================================================
 *  Emails (and SMS, if enabled) a due-fee reminder to the guardians of students
 *  with outstanding balances (status pending/partial, net due > 0). Run weekly.
 *
 *    CLI:  C:\xampp\php\php.exe C:\xampp\htdocs\pwf\cron\fee-reminders.php
 *    URL:  https://your-site/cron/fee-reminders?token=<cron token>
 *  (Token = the same membership cron token, in Admin → Membership Settings.)
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/sms.php';

@set_time_limit(0);
ignore_user_abort(true);
$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $token    = (string) get('token', '');
    $expected = (string) get_setting('membership_cron_token', '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

$log = static function (string $m) use ($isCli): void { echo $m . ($isCli ? "\n" : "<br>\n"); };
$siteName = get_setting('site_name', SITE_NAME);
$smsOn    = sms_enabled();

// Outstanding fees grouped by student (skip general/no-student rows).
$rows = db_all(
    "SELECT p.*, s.name AS student_name, s.email AS student_email, s.phone AS student_phone,
            s.guardian_email, s.guardian_phone, s.guardian_name
       FROM school_fee_payments p
       JOIN school_students s ON s.id = p.student_id
      WHERE p.status IN ('pending','partial')
      ORDER BY p.student_id"
);

$byStudent = [];
foreach ($rows as $r) {
    $due = student_fee_due($r); // student.php: net minus paid, 0 when waived
    if ($due <= 0) {
        continue;
    }
    $sid = (int) $r['student_id'];
    if (!isset($byStudent[$sid])) {
        $byStudent[$sid] = ['row' => $r, 'due' => 0.0, 'items' => []];
    }
    $byStudent[$sid]['due'] += $due;
    $byStudent[$sid]['items'][] = ['title' => $r['title'], 'due' => $due];
}

$emails = 0; $sms = 0;
foreach ($byStudent as $sid => $g) {
    $r     = $g['row'];
    $email = $r['guardian_email'] ?: $r['student_email'];
    $phone = $r['guardian_phone'] ?: $r['student_phone'];
    $lines = '';
    foreach ($g['items'] as $it) {
        $lines .= '<li>' . e($it['title']) . ' — ' . money($it['due'], '₹', 2) . '</li>';
    }
    if ($email && is_email($email)) {
        $body = '<p>Dear ' . e($r['guardian_name'] ?: 'Guardian') . ',</p>'
            . '<p>This is a reminder that the following fees for <strong>' . e($r['student_name'])
            . '</strong> are outstanding at ' . e($siteName) . ':</p><ul>' . $lines . '</ul>'
            . '<p><strong>Total due: ' . money($g['due'], '₹', 2) . '</strong></p>'
            . '<p>Please clear the dues at your earliest convenience. Thank you.</p>';
        if (send_mail($email, 'Fee due reminder — ' . $r['student_name'], $body)) { $emails++; }
    }
    if ($smsOn && $phone) {
        $res = send_sms($phone, 'Fee reminder from ' . $siteName . ': ' . money($g['due'], '₹', 0)
            . ' due for ' . $r['student_name'] . '. Please pay soon.');
        if ($res['success']) { $sms++; }
    }
}

log_activity('cron', 'school-fees', "Fee reminders: $emails emails, $sms SMS to " . count($byStudent) . ' guardians');
$log('Fee reminders sent: ' . $emails . ' email(s), ' . $sms . ' SMS to ' . count($byStudent) . ' student(s) with dues.');
