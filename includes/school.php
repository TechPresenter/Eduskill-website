<?php
/**
 * =============================================================================
 *  School Management domain layer
 * =============================================================================
 *  Reusable helpers for partner schools: identity codes, fee-receipt numbers,
 *  fee status, and the per-school stat rollup used by the dashboard. Loaded by
 *  bootstrap.php.
 * =============================================================================
 */

declare(strict_types=1);

/* =============================================================================
 |  IDENTITY CODES
 |============================================================================*/

function school_generate_code(int $id): string
{
    $p = strtoupper((string) get_setting('school_code_prefix', 'SCH'));
    $p = preg_replace('/[^A-Z0-9]/', '', $p) ?: 'SCH';
    return sprintf('%s-%04d', $p, $id);
}

function student_generate_code(int $schoolId, int $id): string
{
    $p = strtoupper((string) get_setting('student_code_prefix', 'STU'));
    $p = preg_replace('/[^A-Z0-9]/', '', $p) ?: 'STU';
    return sprintf('%s-%03d-%05d', $p, $schoolId, $id);
}

/** Unique fee-receipt number for a payment row. */
function school_receipt_no(int $paymentId): string
{
    $p = strtoupper((string) get_setting('school_receipt_prefix', 'RCP'));
    $p = preg_replace('/[^A-Z0-9]/', '', $p) ?: 'RCP';
    return sprintf('%s-%s-%05d', $p, date('Y'), $paymentId);
}

/** Assign a school code if missing; returns the (possibly updated) row. */
function school_ensure_code(array $school): array
{
    if (empty($school['code'])) {
        $code = school_generate_code((int) $school['id']);
        db_update('schools', ['code' => $code], 'id = :id', [':id' => (int) $school['id']]);
        $school['code'] = $code;
    }
    return $school;
}

/** Assign a student code if missing; returns the (possibly updated) row. */
function student_ensure_code(array $student): array
{
    if (empty($student['student_code'])) {
        $code = student_generate_code((int) $student['school_id'], (int) $student['id']);
        db_update('school_students', ['student_code' => $code], 'id = :id', [':id' => (int) $student['id']]);
        $student['student_code'] = $code;
    }
    return $student;
}

/* =============================================================================
 |  FEES
 |============================================================================*/

/** Derive a fee payment status from the amounts. */
function school_fee_status(float $amount, float $paid): string
{
    if ($paid <= 0.0) {
        return 'pending';
    }
    return ($paid + 0.001 >= $amount) ? 'paid' : 'partial';
}

/* =============================================================================
 |  LOOKUPS
 |============================================================================*/

/** Active NGO programs for enrolment dropdowns: [id => title]. */
function school_programs(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_all("SELECT id, title FROM programs WHERE status = 'active' AND deleted_at IS NULL ORDER BY title") as $r) {
                $cache[(int) $r['id']] = $r['title'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

/** All schools for filter dropdowns: [id => name]. */
function school_options(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT id, name FROM schools ORDER BY name') as $r) {
            $cache[(int) $r['id']] = $r['name'];
        }
    }
    return $cache;
}

function school_role_labels(): array
{
    return [
        'coordinator'   => 'Coordinator',
        'teacher'       => 'Teacher',
        'volunteer'     => 'Volunteer',
        'administrator' => 'Administrator',
        'counselor'     => 'Counselor',
    ];
}

/* =============================================================================
 |  STATS (dashboard)
 |============================================================================*/

/** Percentage present (present+late) over the last N days, or null if no data. */
function school_attendance_rate(int $schoolId, int $days = 30): ?float
{
    $days = max(1, $days);
    $row = db_row(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS present
           FROM school_attendance
          WHERE school_id = :s AND `date` >= DATE_SUB(CURDATE(), INTERVAL $days DAY)",
        [':s' => $schoolId]
    );
    $total = (int) ($row['total'] ?? 0);
    if ($total === 0) {
        return null;
    }
    return round(((int) $row['present'] / $total) * 100, 1);
}

/** Rollup of the headline numbers for one school. */
function school_stats(int $schoolId): array
{
    return [
        'students'        => db_count('school_students', 'school_id = :s', [':s' => $schoolId]),
        'active_students' => db_count('school_students', "school_id = :s AND status IN ('enrolled','active')", [':s' => $schoolId]),
        'batches'         => db_count('school_batches', 'school_id = :s', [':s' => $schoolId]),
        'ongoing_batches' => db_count('school_batches', "school_id = :s AND status = 'ongoing'", [':s' => $schoolId]),
        'staff'           => db_count('school_staff', "school_id = :s AND status = 'active'", [':s' => $schoolId]),
        'fee_collected'   => (float) db_value("SELECT COALESCE(SUM(amount_paid),0) FROM school_fee_payments WHERE school_id = :s", [':s' => $schoolId]),
        'fee_due'         => (float) db_value("SELECT COALESCE(SUM(amount - amount_paid),0) FROM school_fee_payments WHERE school_id = :s AND status IN ('pending','partial')", [':s' => $schoolId]),
        'attendance_rate' => school_attendance_rate($schoolId),
    ];
}

/* =============================================================================
 |  STATUS PILLS (admin theme classes)
 |============================================================================*/

function school_status_pill(string $s): string
{
    return ['active' => 'pill-green', 'inactive' => 'pill-gray', 'prospective' => 'pill-amber'][$s] ?? 'pill-gray';
}

function student_status_pill(string $s): string
{
    return ['enrolled' => 'pill-blue', 'active' => 'pill-green', 'completed' => 'pill-gray', 'dropped' => 'pill-red'][$s] ?? 'pill-gray';
}

function batch_status_pill(string $s): string
{
    return ['upcoming' => 'pill-blue', 'ongoing' => 'pill-green', 'completed' => 'pill-gray', 'cancelled' => 'pill-red'][$s] ?? 'pill-gray';
}

function fee_status_pill(string $s): string
{
    return ['paid' => 'pill-green', 'partial' => 'pill-amber', 'pending' => 'pill-red', 'waived' => 'pill-gray'][$s] ?? 'pill-gray';
}
