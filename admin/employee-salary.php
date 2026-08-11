<?php
/**
 * =============================================================================
 *  Admin — Salary structure + payslip generation (per employee, ?employee=)
 *  Set the salary components, then generate a monthly payslip (stored + print).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$empId = (int) request('employee', 0);
$emp   = $empId ? find('employees', $empId) : null;
if (!$emp && is_post()) { set_flash('error', 'Employee not found.'); redirect('/admin/employee-salary'); }

/* -------------------------------------------------------------- OVERVIEW (no employee chosen) */
if (!$emp) {
    $page_title = 'Salary & Payslips';
    $rows = db_all(
        "SELECT e.id, e.employee_code, e.name, e.designation, e.photo,
                s.id AS has_struct,
                (s.basic + s.hra + s.conveyance + s.medical + s.special_allowance) AS gross,
                (SELECT COUNT(*) FROM payslips p WHERE p.employee_id = e.id) AS slip_count
           FROM employees e
           LEFT JOIN salary_structures s ON s.employee_id = e.id
          WHERE e.status = 'active'
          ORDER BY e.name"
    );
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Salary &amp; Payslips</h1><span class="muted">Set salary structures and generate monthly payslips</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('employees')) ?>"><?= lucide('users-round') ?> All employees</a>
    </div>
    <div class="panel"><div class="panel-body">
        <?php if ($rows): ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th>Employee</th><th>Monthly gross</th><th>Structure</th><th>Payslips</th><th style="text-align:right;">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><div class="flex" style="gap:.55rem;align-items:center;">
                        <img src="<?= e(image_url($r['photo'] ?? null, 'avatar')) ?>" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex:none;">
                        <div><strong><?= e($r['name']) ?></strong><br><small class="text-muted"><?= e($r['employee_code']) ?><?= $r['designation'] ? ' · ' . e($r['designation']) : '' ?></small></div>
                    </div></td>
                    <td><?= $r['has_struct'] ? e(money((float) $r['gross'])) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $r['has_struct'] ? '<span class="pill pill-green">Set</span>' : '<span class="pill pill-gray">Not set</span>' ?></td>
                    <td><?= (int) $r['slip_count'] ?></td>
                    <td style="text-align:right;"><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('employee-salary?employee=' . $r['id'])) ?>"><?= lucide('wallet') ?> Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('wallet') ?></div>No active employees. <a href="<?= e(admin_url('employees?action=create')) ?>">Add one</a>.</div>
        <?php endif; ?>
    </div></div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}
$redir = '?employee=' . $empId;

$fields = ['basic', 'hra', 'conveyance', 'medical', 'special_allowance', 'pf', 'professional_tax', 'tds', 'other_deduction'];

/* -------------------------------------------------------------- SAVE STRUCTURE */
if (is_post() && post('_do') === 'save_structure') {
    require_csrf();
    $data = ['employee_id' => $empId, 'effective_from' => clean(post('effective_from')) ?: null];
    foreach ($fields as $f) { $data[$f] = max(0, (float) post($f, 0)); }
    $existing = db_row("SELECT id FROM salary_structures WHERE employee_id = :e", [':e' => $empId]);
    if ($existing) {
        db_update('salary_structures', $data, 'employee_id = :e', [':e' => $empId]);
    } else {
        db_insert('salary_structures', $data);
    }
    log_activity('update', 'salary_structures', 'Updated salary structure for employee #' . $empId);
    set_flash('success', 'Salary structure saved.');
    redirect('/admin/employee-salary' . $redir);
}

/* -------------------------------------------------------------- GENERATE PAYSLIP */
if (is_post() && post('_do') === 'generate') {
    require_csrf();
    $struct = db_row("SELECT * FROM salary_structures WHERE employee_id = :e", [':e' => $empId]);
    if (!$struct) {
        set_flash('error', 'Set the salary structure before generating a payslip.');
        redirect('/admin/employee-salary' . $redir);
    }
    $month = (int) post('period_month', (int) date('n'));
    $year  = (int) post('period_year', (int) date('Y'));
    $lop   = max(0, (float) post('lop_days', 0));
    if ($month < 1 || $month > 12) { $month = (int) date('n'); }

    $c = salary_compute($struct);
    $perDay  = $c['gross'] / 30;
    $lopAmt  = round($perDay * $lop, 2);
    $deduct  = round($c['deductions'] + $lopAmt, 2);
    $net     = round($c['gross'] - $deduct, 2);
    $breakdown = json_encode([
        'earnings'   => ['basic' => (float) $struct['basic'], 'hra' => (float) $struct['hra'], 'conveyance' => (float) $struct['conveyance'], 'medical' => (float) $struct['medical'], 'special_allowance' => (float) $struct['special_allowance']],
        'deductions' => ['pf' => (float) $struct['pf'], 'professional_tax' => (float) $struct['professional_tax'], 'tds' => (float) $struct['tds'], 'other_deduction' => (float) $struct['other_deduction'], 'lop' => $lopAmt],
    ]);

    db_query(
        "INSERT INTO payslips (employee_id, period_month, period_year, basic, allowances, deductions, gross, net, lop_days, note, breakdown)
         VALUES (:e,:m,:y,:b,:al,:d,:g,:n,:lop,:note,:bd)
         ON DUPLICATE KEY UPDATE basic=VALUES(basic), allowances=VALUES(allowances), deductions=VALUES(deductions),
            gross=VALUES(gross), net=VALUES(net), lop_days=VALUES(lop_days), note=VALUES(note), breakdown=VALUES(breakdown), generated_at=NOW()",
        [
            ':e' => $empId, ':m' => $month, ':y' => $year,
            ':b' => (float) $struct['basic'], ':al' => round($c['earnings'] - (float) $struct['basic'], 2),
            ':d' => $deduct, ':g' => $c['gross'], ':n' => $net, ':lop' => $lop,
            ':note' => clean(post('note')) ?: null, ':bd' => $breakdown,
        ]
    );
    log_activity('create', 'payslips', "Generated payslip $month/$year for employee #" . $empId);
    set_flash('success', 'Payslip generated for ' . month_label($month) . " $year.");
    redirect('/admin/employee-salary' . $redir);
}

/* -------------------------------------------------------------- DELETE PAYSLIP */
if (is_post() && post('_do') === 'delete_payslip') {
    require_csrf();
    $pid = (int) post('id', 0);
    $ps  = find('payslips', $pid);
    if ($ps && (int) $ps['employee_id'] === $empId) {
        db_delete('payslips', 'id = :id', [':id' => $pid]);
        set_flash('success', 'Payslip deleted.');
    }
    redirect('/admin/employee-salary' . $redir);
}

$struct = db_row("SELECT * FROM salary_structures WHERE employee_id = :e", [':e' => $empId]) ?: [];
$comp   = salary_compute($struct);
$slips  = db_all("SELECT * FROM payslips WHERE employee_id = :e ORDER BY period_year DESC, period_month DESC", [':e' => $empId]);
$sv     = static fn(string $k) => e(old($k, $struct[$k] ?? '0'));

$page_title = 'Salary — ' . $emp['name'];
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Salary &amp; Payslips</h1><span class="muted"><?= e($emp['employee_code']) ?> · <?= e($emp['name']) ?></span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('employees?action=edit&id=' . $empId)) ?>">&larr; Back to employee</a>
</div>

<div class="grid-2" style="align-items:start;gap:1.25rem;">
    <!-- Salary structure -->
    <div class="panel"><div class="panel-body">
        <h3 class="mb-3">Salary structure</h3>
        <form class="admin-form" method="post" action="<?= e(admin_url('employee-salary')) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save_structure"><input type="hidden" name="employee" value="<?= (int) $empId ?>">
            <p class="text-muted" style="font-size:.8rem;font-weight:700;margin:.2rem 0;">EARNINGS</p>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Basic</label><input class="form-control sal-in" type="number" step="0.01" min="0" name="basic" value="<?= $sv('basic') ?>"></div>
                <div class="form-group"><label class="form-label">HRA</label><input class="form-control sal-in" type="number" step="0.01" min="0" name="hra" value="<?= $sv('hra') ?>"></div>
                <div class="form-group"><label class="form-label">Conveyance</label><input class="form-control sal-in" type="number" step="0.01" min="0" name="conveyance" value="<?= $sv('conveyance') ?>"></div>
                <div class="form-group"><label class="form-label">Medical</label><input class="form-control sal-in" type="number" step="0.01" min="0" name="medical" value="<?= $sv('medical') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Special allowance</label><input class="form-control sal-in" type="number" step="0.01" min="0" name="special_allowance" value="<?= $sv('special_allowance') ?>"></div>
            <p class="text-muted" style="font-size:.8rem;font-weight:700;margin:.5rem 0 .2rem;">DEDUCTIONS</p>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">PF</label><input class="form-control sal-in" type="number" step="0.01" min="0" name="pf" value="<?= $sv('pf') ?>"></div>
                <div class="form-group"><label class="form-label">Professional tax</label><input class="form-control sal-in" type="number" step="0.01" min="0" name="professional_tax" value="<?= $sv('professional_tax') ?>"></div>
                <div class="form-group"><label class="form-label">TDS</label><input class="form-control sal-in" type="number" step="0.01" min="0" name="tds" value="<?= $sv('tds') ?>"></div>
                <div class="form-group"><label class="form-label">Other deduction</label><input class="form-control sal-in" type="number" step="0.01" min="0" name="other_deduction" value="<?= $sv('other_deduction') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Effective from</label><input class="form-control" type="date" name="effective_from" value="<?= e($struct['effective_from'] ?? '') ?>"></div>
            <div class="an-salary-summary" style="display:flex;justify-content:space-between;gap:.5rem;background:var(--surface-2);border-radius:12px;padding:.8rem 1rem;margin:.4rem 0 1rem;">
                <div><small class="text-muted">Gross</small><div style="font-weight:800;font-size:1.1rem;" id="sal-gross"><?= e(money($comp['gross'])) ?></div></div>
                <div><small class="text-muted">Deductions</small><div style="font-weight:800;font-size:1.1rem;color:#b91c1c;" id="sal-ded"><?= e(money($comp['deductions'])) ?></div></div>
                <div><small class="text-muted">Net pay</small><div style="font-weight:800;font-size:1.1rem;color:#1F5C48;" id="sal-net"><?= e(money($comp['net'])) ?></div></div>
            </div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save structure</button></div>
        </form>
    </div></div>

    <!-- Generate payslip + history -->
    <div class="panel"><div class="panel-body">
        <h3 class="mb-3">Generate payslip</h3>
        <form class="admin-form" method="post" action="<?= e(admin_url('employee-salary')) ?>" style="margin-bottom:1.25rem;">
            <?= csrf_field() ?><input type="hidden" name="_do" value="generate"><input type="hidden" name="employee" value="<?= (int) $empId ?>">
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Month</label>
                    <select class="form-select" name="period_month">
                        <?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>" <?= $m === (int) date('n') ? 'selected' : '' ?>><?= month_label($m) ?></option><?php endfor; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Year</label>
                    <select class="form-select" name="period_year">
                        <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 3; $y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?>
                    </select></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Loss-of-pay days</label><input class="form-control" type="number" step="0.5" min="0" name="lop_days" value="0"></div>
                <div class="form-group"><label class="form-label">Note</label><input class="form-control" name="note" placeholder="Optional"></div>
            </div>
            <div class="form-actions"><button class="btn btn-primary" type="submit" <?= $struct ? '' : 'disabled title="Set the salary structure first"' ?>><?= lucide('receipt') ?> Generate payslip</button></div>
            <?php if (!$struct): ?><small class="text-muted">Save a salary structure first to enable payslip generation.</small><?php endif; ?>
        </form>

        <h3 class="mb-3">Payslip history</h3>
        <?php if ($slips): ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th>Period</th><th>Gross</th><th>Net</th><th style="text-align:right;">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($slips as $s): ?>
                <tr>
                    <td><strong><?= e(month_label((int) $s['period_month'])) ?> <?= (int) $s['period_year'] ?></strong></td>
                    <td><?= e(money((float) $s['gross'])) ?></td>
                    <td style="color:#1F5C48;font-weight:700;"><?= e(money((float) $s['net'])) ?></td>
                    <td><div class="actions">
                        <a class="icon-btn" href="<?= e(admin_url('payslip?id=' . $s['id'])) ?>" target="_blank" title="View / print"><?= lucide('printer') ?></a>
                        <form method="post" action="<?= e(admin_url('employee-salary')) ?>" data-confirm="Delete this payslip?" style="display:inline;">
                            <?= csrf_field() ?><input type="hidden" name="_do" value="delete_payslip"><input type="hidden" name="employee" value="<?= (int) $empId ?>"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('receipt') ?></div>No payslips generated yet.</div>
        <?php endif; ?>
    </div></div>
</div>

<script>
(function () {
    var ins = document.querySelectorAll('.sal-in');
    var earn = ['basic', 'hra', 'conveyance', 'medical', 'special_allowance'];
    var ded = ['pf', 'professional_tax', 'tds', 'other_deduction'];
    function val(n) { var el = document.querySelector('[name="' + n + '"]'); return el ? (parseFloat(el.value) || 0) : 0; }
    function fmt(n) { return '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
    function recalc() {
        var g = 0, d = 0;
        earn.forEach(function (n) { g += val(n); });
        ded.forEach(function (n) { d += val(n); });
        document.getElementById('sal-gross').textContent = fmt(g);
        document.getElementById('sal-ded').textContent = fmt(d);
        document.getElementById('sal-net').textContent = fmt(g - d);
    }
    ins.forEach(function (el) { el.addEventListener('input', recalc); });
})();
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
