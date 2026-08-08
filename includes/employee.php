<?php
/**
 * =============================================================================
 *  Employee / HR helpers — shared by the admin/employee-* modules.
 * =============================================================================
 *  Codes, dropdown option lists, status label/pill maps, salary maths and the
 *  ID-card verify URL. Pure helpers — no output, all reads use prepared stmts.
 * =============================================================================
 */

declare(strict_types=1);

/** Next sequential employee code, e.g. EMP0007. */
function employee_next_code(): string
{
    $max = (int) db_value(
        "SELECT MAX(CAST(SUBSTRING(employee_code, 4) AS UNSIGNED)) FROM employees WHERE employee_code REGEXP '^EMP[0-9]+$'"
    );
    return 'EMP' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
}

/** [id => name] of departments (optionally active only). */
function department_options(bool $activeOnly = false): array
{
    $sql = "SELECT id, name FROM departments" . ($activeOnly ? " WHERE status = 'active'" : "") . " ORDER BY name";
    $out = [];
    foreach (db_all($sql) as $r) { $out[(int) $r['id']] = $r['name']; }
    return $out;
}

/** [id => 'CODE — Name'] of employees (default: active only). */
function employee_options(string $status = 'active'): array
{
    $where  = $status !== '' ? "WHERE status = :s" : '';
    $params = $status !== '' ? [':s' => $status] : [];
    $out = [];
    foreach (db_all("SELECT id, employee_code, name FROM employees $where ORDER BY name", $params) as $r) {
        $out[(int) $r['id']] = $r['employee_code'] . ' — ' . $r['name'];
    }
    return $out;
}

function employee_status_labels(): array { return ['active' => 'Active', 'inactive' => 'Inactive', 'terminated' => 'Terminated']; }
function employee_status_pill(string $s): string { return ['active' => 'pill-green', 'inactive' => 'pill-gray', 'terminated' => 'pill-red'][$s] ?? 'pill-gray'; }
function employment_type_labels(): array { return ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'intern' => 'Intern', 'volunteer' => 'Volunteer']; }
function attendance_status_labels(): array { return ['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'half_day' => 'Half Day', 'leave' => 'On Leave', 'holiday' => 'Holiday']; }
function attendance_status_pill(string $s): string { return ['present' => 'pill-green', 'absent' => 'pill-red', 'late' => 'pill-amber', 'half_day' => 'pill-blue', 'leave' => 'pill-violet', 'holiday' => 'pill-gray'][$s] ?? 'pill-gray'; }
function leave_type_labels(): array { return ['casual' => 'Casual', 'sick' => 'Sick', 'earned' => 'Earned', 'maternity' => 'Maternity', 'unpaid' => 'Unpaid (LOP)', 'other' => 'Other']; }
function leave_status_labels(): array { return ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled']; }
function leave_status_pill(string $s): string { return ['pending' => 'pill-amber', 'approved' => 'pill-green', 'rejected' => 'pill-red', 'cancelled' => 'pill-gray'][$s] ?? 'pill-gray'; }
function review_period_labels(): array { return ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'half_yearly' => 'Half-yearly', 'annual' => 'Annual']; }
function employee_doc_labels(): array { return ['appointment_letter' => 'Appointment Letter', 'agreement' => 'Agreement', 'id_proof' => 'ID Proof', 'certificate' => 'Certificate', 'resume' => 'Resume', 'other' => 'Other']; }

/** Earnings / deductions / gross / net from a salary_structures row. */
function salary_compute(array $s): array
{
    $earn = (float) ($s['basic'] ?? 0) + (float) ($s['hra'] ?? 0) + (float) ($s['conveyance'] ?? 0)
          + (float) ($s['medical'] ?? 0) + (float) ($s['special_allowance'] ?? 0);
    $ded  = (float) ($s['pf'] ?? 0) + (float) ($s['professional_tax'] ?? 0)
          + (float) ($s['tds'] ?? 0) + (float) ($s['other_deduction'] ?? 0);
    return ['earnings' => round($earn, 2), 'deductions' => round($ded, 2), 'gross' => round($earn, 2), 'net' => round($earn - $ded, 2)];
}

/** Public verify URL encoded into the employee ID-card QR. */
function employee_verify_url(array $emp): string
{
    return abs_url('verify-employee?code=' . urlencode((string) ($emp['employee_code'] ?? '')));
}

/** Month number => short label (1..12). */
function month_label(int $m): string
{
    return ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][$m] ?? '';
}
