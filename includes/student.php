<?php
/**
 * =============================================================================
 *  Student Management domain layer
 * =============================================================================
 *  Admissions → enrolment, student identity (codes, QR, portal member link),
 *  certificates + marksheets (serials, QR verification, grading), notice
 *  targeting/notification, and net-fee maths. Loaded by bootstrap.php.
 *  (Base student helpers — codes, pills — live in school.php.)
 * =============================================================================
 */

declare(strict_types=1);

/* =============================================================================
 |  IDENTITY (QR token, admission no, portal member link)
 |============================================================================*/

function student_new_qr_token(): string
{
    do { $t = bin2hex(random_bytes(16)); } while (find_by('school_students', 'qr_token', $t));
    return $t;
}

/** Ensure a student has a code (via school.php) + a QR token. */
function student_ensure_full(array $student): array
{
    $student = student_ensure_code($student); // from school.php
    if (empty($student['qr_token'])) {
        $token = student_new_qr_token();
        db_update('school_students', ['qr_token' => $token], 'id = :id', [':id' => (int) $student['id']]);
        $student['qr_token'] = $token;
    }
    return $student;
}

function student_verify_url(array $student): string
{
    return abs_url('verify-student?token=' . urlencode((string) ($student['qr_token'] ?? '')));
}

function admission_generate_no(int $id): string
{
    $p = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) get_setting('admission_prefix', 'ADM'))) ?: 'ADM';
    return sprintf('%s-%s-%04d', $p, date('Y'), $id);
}

/**
 * Create/link a Member account so a student can sign in to the learning portal.
 * Emails a set-password (reset) link on first creation. Returns the member id.
 */
function student_link_member(array $student): ?int
{
    $email = strtolower(trim((string) ($student['email'] ?? '')));
    if ($email === '' || !is_email($email)) {
        return null;
    }
    $existing = find_by('members', 'email', $email);
    if ($existing) {
        $mid = (int) $existing['id'];
    } else {
        $mid = db_insert('members', [
            'name'              => $student['name'],
            'email'             => $email,
            'password'          => password_hash(str_random(14), PASSWORD_DEFAULT),
            'phone'             => $student['phone'] ?? null,
            'type'              => 'member',
            'status'            => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
        if (function_exists('request_password_reset')) {
            try { request_password_reset($email); } catch (Throwable $e) { error_log('[student member] ' . $e->getMessage()); }
        }
    }
    db_update('school_students', ['member_id' => $mid], 'id = :id', [':id' => (int) $student['id']]);
    return $mid;
}

/**
 * Access guard for a student document (certificate / marksheet / ID card):
 * allow an admin, OR the member who owns the student record. Anyone else is
 * bounced through require_admin() (which redirects to the admin login).
 */
function student_portal_or_admin_guard(int $studentId): void
{
    if (function_exists('current_user') && current_user()) {
        return; // admin
    }
    if (is_member_logged_in()) {
        $st = find('school_students', $studentId);
        if ($st && (int) $st['member_id'] === (int) (current_member()['id'] ?? 0)) {
            return; // owning member
        }
    }
    require_admin();
}

/* =============================================================================
 |  ADMISSIONS
 |============================================================================*/

/**
 * Approve an admission and enrol the applicant as a student.
 * $opts: school_id, program_id, batch_id, grade, create_login(bool), admin_id.
 * Returns the new/linked student row, or null.
 */
function admission_enroll(int $admissionId, array $opts = []): ?array
{
    $adm = find('admissions', $admissionId);
    if (!$adm) {
        return null;
    }
    if (!empty($adm['student_id']) && ($s = find('school_students', (int) $adm['student_id']))) {
        return $s; // already enrolled
    }
    $schoolId = (int) ($opts['school_id'] ?? $adm['school_id'] ?? 0);
    if ($schoolId <= 0 || !find('schools', $schoolId)) {
        return null; // must land in a valid school
    }

    $studentId = db_insert('school_students', [
        'school_id'       => $schoolId,
        'name'            => $adm['name'],
        'gender'          => $adm['gender'],
        'dob'             => $adm['dob'],
        'guardian_name'   => $adm['guardian_name'],
        'father_name'     => $adm['father_name'],
        'mother_name'     => $adm['mother_name'],
        'phone'           => $adm['phone'],
        'email'           => $adm['email'],
        'address'         => $adm['address'],
        'city'            => $adm['city'],
        'state'           => $adm['state'],
        'pincode'         => $adm['pincode'],
        'grade'           => $opts['grade'] ?? $adm['grade_applying'],
        'previous_school' => $adm['previous_school'],
        'program_id'      => (int) ($opts['program_id'] ?? $adm['program_id'] ?? 0) ?: null,
        'batch_id'        => (int) ($opts['batch_id'] ?? 0) ?: null,
        'admission_no'    => admission_generate_no($admissionId),
        'enrollment_date' => date('Y-m-d'),
        'status'          => 'enrolled',
    ]);
    $student = student_ensure_full(find('school_students', $studentId));

    if (!empty($opts['create_login'])) {
        student_link_member($student);
        $student = find('school_students', $studentId);
    }

    db_update('admissions', [
        'status'      => 'approved',
        'student_id'  => $studentId,
        'reviewed_by' => $opts['admin_id'] ?? ($_SESSION['user']['id'] ?? null),
        'reviewed_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', [':id' => $admissionId]);

    return $student;
}

/* =============================================================================
 |  CERTIFICATES
 |============================================================================*/

function certificate_serial(int $id): string
{
    $p = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) get_setting('certificate_prefix', 'CERT'))) ?: 'CERT';
    return sprintf('%s-%s-%05d', $p, date('Y'), $id);
}

function certificate_new_qr(): string
{
    do { $t = bin2hex(random_bytes(16)); } while (find_by('student_certificates', 'qr_token', $t));
    return $t;
}

/**
 * Issue a certificate to a student. $data: course_id, type, title, description,
 * template, issue_date, signed_by, signature_image, created_by. Returns the id.
 */
function certificate_issue(int $studentId, array $data): ?int
{
    if (!find('school_students', $studentId)) {
        return null;
    }
    $id = db_insert('student_certificates', [
        'student_id'      => $studentId,
        'course_id'       => (int) ($data['course_id'] ?? 0) ?: null,
        'type'            => in_array($data['type'] ?? '', ['completion', 'participation', 'merit', 'achievement'], true) ? $data['type'] : 'completion',
        'title'           => $data['title'] ?? 'Certificate',
        'description'     => $data['description'] ?? null,
        'template'        => $data['template'] ?? 'classic',
        'issue_date'      => $data['issue_date'] ?? date('Y-m-d'),
        'signed_by'       => $data['signed_by'] ?? null,
        'signature_image' => $data['signature_image'] ?? null,
        'qr_token'        => certificate_new_qr(),
        'created_by'      => $data['created_by'] ?? ($_SESSION['user']['id'] ?? null),
    ]);
    db_update('student_certificates', ['serial' => certificate_serial($id)], 'id = :id', [':id' => $id]);
    return $id;
}

function certificate_verify_url(array $cert): string
{
    return abs_url('verify-award?serial=' . urlencode((string) ($cert['serial'] ?? '')));
}

/* =============================================================================
 |  MARKSHEETS
 |============================================================================*/

function marksheet_serial(int $id): string
{
    $p = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) get_setting('marksheet_prefix', 'MS'))) ?: 'MS';
    return sprintf('%s-%s-%05d', $p, date('Y'), $id);
}

function marksheet_new_qr(): string
{
    do { $t = bin2hex(random_bytes(16)); } while (find_by('marksheets', 'qr_token', $t));
    return $t;
}

/** Letter grade from a percentage (Indian-style bands). */
function grade_from_percent(float $p): string
{
    return match (true) {
        $p >= 90 => 'A+',
        $p >= 80 => 'A',
        $p >= 70 => 'B+',
        $p >= 60 => 'B',
        $p >= 50 => 'C',
        $p >= 40 => 'D',
        default  => 'F',
    };
}

/**
 * Recompute a marksheet's totals/percentage/grade/result from its subject rows.
 * Pass-mark threshold is 33% overall and each subject must be >= 33% to pass.
 */
function marksheet_recompute(int $marksheetId): void
{
    $subjects = db_all('SELECT * FROM marksheet_subjects WHERE marksheet_id = :m', [':m' => $marksheetId]);
    $max = 0.0; $obt = 0.0; $failed = false;
    foreach ($subjects as $s) {
        $sm = (float) $s['max_marks'];
        $so = (float) $s['obtained_marks'];
        $max += $sm;
        $obt += $so;
        $pct = $sm > 0 ? ($so / $sm) * 100 : 0;
        db_update('marksheet_subjects', ['grade' => grade_from_percent($pct)], 'id = :id', [':id' => (int) $s['id']]);
        if ($sm > 0 && $pct < 33) { $failed = true; }
    }
    $percentage = $max > 0 ? round(($obt / $max) * 100, 2) : 0.0;
    db_update('marksheets', [
        'max_total'      => $max,
        'obtained_total' => $obt,
        'percentage'     => $percentage,
        'grade'          => grade_from_percent($percentage),
        'result'         => ($subjects ? (($failed || $percentage < 33) ? 'fail' : 'pass') : 'pending'),
    ], 'id = :id', [':id' => $marksheetId]);
}

/** Ensure a marksheet has a serial + QR token (call before publishing/printing). */
function marksheet_ensure_identity(array $ms): array
{
    $update = [];
    if (empty($ms['serial']))   { $update['serial']   = marksheet_serial((int) $ms['id']); }
    if (empty($ms['qr_token'])) { $update['qr_token'] = marksheet_new_qr(); }
    if ($update) {
        db_update('marksheets', $update, 'id = :id', [':id' => (int) $ms['id']]);
        $ms = array_merge($ms, $update);
    }
    return $ms;
}

function marksheet_verify_url(array $ms): string
{
    return abs_url('verify-award?serial=' . urlencode((string) ($ms['serial'] ?? '')));
}

/* =============================================================================
 |  NOTICES (targeting + notification)
 |============================================================================*/

/** Recipients ([{name,email,phone}]) for a notice, by its audience. */
function notice_recipients(array $notice): array
{
    $where  = "email IS NOT NULL AND email <> ''";
    $params = [];
    switch ($notice['audience']) {
        case 'school':
            $where .= ' AND school_id = :s'; $params[':s'] = (int) $notice['school_id']; break;
        case 'batch':
            $where .= ' AND batch_id = :b';  $params[':b'] = (int) $notice['batch_id']; break;
        case 'course':
            // students enrolled in the course (via course_enrollments.student_id)
            return db_all(
                "SELECT DISTINCT s.name, s.email, s.phone
                   FROM school_students s
                   JOIN course_enrollments e ON e.student_id = s.id
                  WHERE e.course_id = :c AND s.email IS NOT NULL AND s.email <> ''",
                [':c' => (int) $notice['course_id']]
            );
        case 'all':
        default:
            break;
    }
    return db_all("SELECT name, email, phone FROM school_students WHERE $where", $params);
}

/**
 * Email (and SMS, if enabled + flagged) a notice to its audience.
 * Returns ['email'=>n, 'sms'=>n, 'total'=>n].
 */
function notice_dispatch(int $noticeId): array
{
    $notice = find('notices', $noticeId);
    $sent = ['email' => 0, 'sms' => 0, 'total' => 0];
    if (!$notice) {
        return $sent;
    }
    $recipients = notice_recipients($notice);
    $sent['total'] = count($recipients);
    $subject = $notice['title'];
    $body = '<p>' . nl2br(e((string) $notice['body'])) . '</p>';
    $smsOn = function_exists('sms_enabled') && sms_enabled();

    foreach ($recipients as $r) {
        if (!empty($notice['notify_email']) && !empty($r['email'])) {
            if (send_mail($r['email'], $subject, $body)) { $sent['email']++; }
        }
        if (!empty($notice['notify_sms']) && $smsOn && !empty($r['phone'])) {
            $res = send_sms($r['phone'], $notice['title'] . ': ' . mb_substr(strip_tags((string) $notice['body']), 0, 200));
            if ($res['success']) { $sent['sms']++; }
        }
    }
    return $sent;
}

/* =============================================================================
 |  FEES (net after discount + scholarship)
 |============================================================================*/

/** Net payable = amount − discount − scholarship. */
function student_fee_net(array $payment): float
{
    return max(0.0, (float) $payment['amount'] - (float) ($payment['discount'] ?? 0) - (float) ($payment['scholarship'] ?? 0));
}

/** Outstanding due for a payment row (0 when waived). */
function student_fee_due(array $payment): float
{
    if (($payment['status'] ?? '') === 'waived') {
        return 0.0;
    }
    return max(0.0, student_fee_net($payment) - (float) $payment['amount_paid']);
}

/** Fee status from net payable vs paid (waived preserved by caller). */
function student_fee_status(array $payment): string
{
    $net  = student_fee_net($payment);
    $paid = (float) $payment['amount_paid'];
    if ($paid <= 0.0) {
        return 'pending';
    }
    return ($paid + 0.001 >= $net) ? 'paid' : 'partial';
}
