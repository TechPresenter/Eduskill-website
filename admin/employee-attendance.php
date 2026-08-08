<?php
/**
 * =============================================================================
 *  Admin — Employee Attendance (daily marking + monthly summary)
 *    default        -> mark a day's attendance for all active employees
 *    ?view=summary  -> per-employee monthly counts (present/late/absent/…)
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$statuses = attendance_status_labels();
$deptOpts = department_options();

/* -------------------------------------------------------------- SAVE (bulk) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $date = clean(post('att_date')) ?: date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }

    $st = (array) ($_POST['status'] ?? []);
    $ci = (array) ($_POST['check_in'] ?? []);
    $co = (array) ($_POST['check_out'] ?? []);
    $nt = (array) ($_POST['note'] ?? []);
    $saved = 0;
    foreach ($st as $eid => $status) {
        $eid = (int) $eid;
        if (!$eid || !isset($statuses[$status])) { continue; }
        db_query(
            "INSERT INTO employee_attendance (employee_id, att_date, status, check_in, check_out, note)
             VALUES (:e, :d, :s, :ci, :co, :n)
             ON DUPLICATE KEY UPDATE status = VALUES(status), check_in = VALUES(check_in), check_out = VALUES(check_out), note = VALUES(note)",
            [
                ':e' => $eid, ':d' => $date, ':s' => $status,
                ':ci' => ($ci[$eid] ?? '') ?: null, ':co' => ($co[$eid] ?? '') ?: null,
                ':n' => clean($nt[$eid] ?? '') ?: null,
            ]
        );
        $saved++;
    }
    log_activity('update', 'employee_attendance', 'Marked attendance for ' . $saved . ' employees on ' . $date);
    set_flash('success', "Attendance saved for $saved employees on " . format_date($date) . '.');
    redirect('/admin/employee-attendance?date=' . $date);
}

$view = get('view') === 'summary' ? 'summary' : 'mark';

/* =============================================================== SUMMARY */
if ($view === 'summary') {
    $month = (string) get('month', date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) { $month = date('Y-m'); }
    $start = $month . '-01';
    $end   = date('Y-m-t', strtotime($start));

    $rows = db_all(
        "SELECT e.id, e.name, e.employee_code,
                SUM(a.status = 'present')  AS present,
                SUM(a.status = 'late')     AS late,
                SUM(a.status = 'half_day') AS half_day,
                SUM(a.status = 'absent')   AS absent,
                SUM(a.status = 'leave')    AS on_leave,
                SUM(a.status = 'holiday')  AS holiday,
                COUNT(a.id)                AS marked
           FROM employees e
           LEFT JOIN employee_attendance a ON a.employee_id = e.id AND a.att_date BETWEEN :s AND :en
          WHERE e.status = 'active'
          GROUP BY e.id, e.name, e.employee_code
          ORDER BY e.name",
        [':s' => $start, ':en' => $end]
    );

    $page_title = 'Attendance Summary';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Attendance Summary</h1><span class="muted"><?= e(date('F Y', strtotime($start))) ?> · <?= count($rows) ?> employees</span></div>
        <div class="flex" style="gap:.5rem;">
            <form method="get" action="<?= e(admin_url('employee-attendance')) ?>" class="flex" style="gap:.5rem;">
                <input type="hidden" name="view" value="summary">
                <input class="form-control" type="month" name="month" value="<?= e($month) ?>" onchange="this.form.submit()">
            </form>
            <a class="btn btn-primary" href="<?= e(admin_url('employee-attendance')) ?>"><?= lucide('calendar-check') ?> Mark attendance</a>
        </div>
    </div>
    <div class="panel"><div class="panel-body">
        <?php if ($rows): ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th>Employee</th><th>Present</th><th>Late</th><th>Half</th><th>Absent</th><th>Leave</th><th>Holiday</th><th>Marked</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e($r['name']) ?></strong><br><small class="text-muted"><?= e($r['employee_code']) ?></small></td>
                    <td><span class="pill pill-green"><?= (int) $r['present'] ?></span></td>
                    <td><?= (int) $r['late'] ?: '<span class="text-muted">0</span>' ?></td>
                    <td><?= (int) $r['half_day'] ?: '<span class="text-muted">0</span>' ?></td>
                    <td><?= (int) $r['absent'] ? '<span class="pill pill-red">' . (int) $r['absent'] . '</span>' : '<span class="text-muted">0</span>' ?></td>
                    <td><?= (int) $r['on_leave'] ?: '<span class="text-muted">0</span>' ?></td>
                    <td><?= (int) $r['holiday'] ?: '<span class="text-muted">0</span>' ?></td>
                    <td><strong><?= (int) $r['marked'] ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('calendar-x') ?></div>No active employees to summarise.</div>
        <?php endif; ?>
    </div></div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

/* =============================================================== MARK a day */
$date = (string) get('date', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }
$deptFilter = (int) get('department', 0);

$where = "e.status = 'active'"; $params = [':d' => $date];
if ($deptFilter && isset($deptOpts[$deptFilter])) { $where .= ' AND e.department_id = :dept'; $params[':dept'] = $deptFilter; }

$emps = db_all(
    "SELECT e.id, e.name, e.employee_code, e.photo, d.name AS dept_name,
            a.status AS att_status, a.check_in, a.check_out, a.note
       FROM employees e
       LEFT JOIN departments d ON d.id = e.department_id
       LEFT JOIN employee_attendance a ON a.employee_id = e.id AND a.att_date = :d
      WHERE $where
      ORDER BY e.name",
    $params
);
$prev = date('Y-m-d', strtotime($date . ' -1 day'));
$next = date('Y-m-d', strtotime($date . ' +1 day'));

$page_title = 'Mark Attendance';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Mark Attendance</h1><span class="muted"><?= e(format_date($date, 'l, d M Y')) ?> · <?= count($emps) ?> employees</span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('employee-attendance?view=summary')) ?>"><?= lucide('bar-chart-3') ?> Monthly summary</a>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <div class="flex" style="gap:.4rem;align-items:center;">
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('employee-attendance?date=' . $prev)) ?>"><?= lucide('chevron-left') ?></a>
            <form method="get" action="<?= e(admin_url('employee-attendance')) ?>"><input class="form-control" type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"></form>
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('employee-attendance?date=' . $next)) ?>"><?= lucide('chevron-right') ?></a>
        </div>
        <form method="get" action="<?= e(admin_url('employee-attendance')) ?>">
            <input type="hidden" name="date" value="<?= e($date) ?>">
            <select class="form-select" name="department" onchange="this.form.submit()">
                <option value="">All departments</option>
                <?php foreach ($deptOpts as $did => $dname): ?><option value="<?= (int) $did ?>" <?= $deptFilter === (int) $did ? 'selected' : '' ?>><?= e($dname) ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($emps): ?>
    <form method="post" action="<?= e(admin_url('employee-attendance')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="att_date" value="<?= e($date) ?>">
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th>Employee</th><th>Status</th><th>Check-in</th><th>Check-out</th><th>Note</th></tr></thead>
            <tbody>
            <?php foreach ($emps as $r):
                $cur = $r['att_status'] ?: 'present'; $eid = (int) $r['id']; ?>
                <tr>
                    <td>
                        <div class="flex" style="gap:.55rem;align-items:center;">
                            <img src="<?= e(image_url($r['photo'] ?? null, 'avatar')) ?>" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex:none;">
                            <div><strong><?= e($r['name']) ?></strong><br><small class="text-muted"><?= e($r['employee_code']) ?><?= $r['dept_name'] ? ' · ' . e($r['dept_name']) : '' ?></small></div>
                        </div>
                    </td>
                    <td><select class="form-select" name="status[<?= $eid ?>]" style="min-width:130px;">
                        <?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= $cur === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                    </select></td>
                    <td><input class="form-control" type="time" name="check_in[<?= $eid ?>]" value="<?= e($r['check_in'] ? substr($r['check_in'], 0, 5) : '') ?>" style="width:120px;"></td>
                    <td><input class="form-control" type="time" name="check_out[<?= $eid ?>]" value="<?= e($r['check_out'] ? substr($r['check_out'], 0, 5) : '') ?>" style="width:120px;"></td>
                    <td><input class="form-control" name="note[<?= $eid ?>]" value="<?= e($r['note'] ?? '') ?>" placeholder="—"></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <div class="form-actions mt-3"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save attendance</button></div>
    </form>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('users-round') ?></div>No active employees<?= $deptFilter ? ' in this department' : '' ?>. <a href="<?= e(admin_url('employees?action=create')) ?>">Add employees</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
