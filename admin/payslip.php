<?php
/**
 * =============================================================================
 *  Admin — Payslip (printable). ?id=<payslip id>. Renders a branded salary
 *  slip from the stored breakdown snapshot; "Print / Save PDF" via the browser.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int) get('id', 0);
$ps = $id ? find('payslips', $id) : null;
if (!$ps) { set_flash('error', 'Payslip not found.'); redirect('/admin/employees'); }

$emp  = find('employees', (int) $ps['employee_id']);
if (!$emp) { set_flash('error', 'Employee not found.'); redirect('/admin/employees'); }
$dept = $emp['department_id'] ? find('departments', (int) $emp['department_id']) : null;
$org  = get_setting('site_name', SITE_NAME);
$bd   = json_decode((string) $ps['breakdown'], true) ?: ['earnings' => [], 'deductions' => []];

$earnLabels = ['basic' => 'Basic', 'hra' => 'HRA', 'conveyance' => 'Conveyance', 'medical' => 'Medical', 'special_allowance' => 'Special Allowance'];
$dedLabels  = ['pf' => 'Provident Fund', 'professional_tax' => 'Professional Tax', 'tds' => 'TDS', 'other_deduction' => 'Other Deduction', 'lop' => 'Loss of Pay'];

$page_title = 'Payslip — ' . $emp['name'];
include __DIR__ . '/partials/head.php';
?>
<style>
    .psl { max-width: 760px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; color: #111827; font-family: 'Roboto', system-ui, sans-serif; box-shadow: 0 16px 40px -20px rgba(6,53,102,.35); }
    .psl-head { background: linear-gradient(135deg,#063566,#084881); color: #fff; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; }
    .psl-head h2 { margin: 0; font-size: 1.2rem; }
    .psl-head .p { font-size: .78rem; opacity: .92; }
    .psl-meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px 18px; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; font-size: .85rem; }
    .psl-meta .k { color: #64748b; font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; }
    .psl-meta .v { font-weight: 700; }
    .psl-cols { display: grid; grid-template-columns: 1fr 1fr; }
    .psl-col { padding: 16px 24px; }
    .psl-col + .psl-col { border-left: 1px solid #e2e8f0; }
    .psl-col h4 { margin: 0 0 10px; font-size: .8rem; letter-spacing: .06em; text-transform: uppercase; color: #063566; }
    .psl-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: .88rem; border-bottom: 1px dashed #eef2f7; }
    .psl-row.total { border-top: 2px solid #cbd5e1; border-bottom: none; margin-top: 6px; padding-top: 8px; font-weight: 800; }
    .psl-net { background: #ecfdf5; border-top: 1px solid #d1fae5; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
    .psl-net .amt { font-size: 1.5rem; font-weight: 800; color: #308629; }
    .psl-foot { padding: 12px 24px; font-size: .72rem; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; }
    @media print {
        .admin-sidebar, .admin-topbar, .admin-overlay, .admin-page-head, .psl-actions, .no-print { display: none !important; }
        .admin-main { margin-left: 0 !important; } .admin-content { padding: 0 !important; }
        .psl { box-shadow: none; border: 1px solid #cbd5e1; } body, .admin-layout { background: #fff !important; }
    }
</style>

<div class="admin-page-head">
    <div><h1>Payslip</h1><span class="muted"><?= e($emp['name']) ?> · <?= e(month_label((int) $ps['period_month'])) ?> <?= (int) $ps['period_year'] ?></span></div>
    <div class="flex psl-actions" style="gap:.5rem;">
        <a class="btn btn-ghost" href="<?= e(admin_url('employee-salary?employee=' . $emp['id'])) ?>">&larr; Back</a>
        <button class="btn btn-primary" type="button" onclick="window.print()"><?= lucide('printer') ?> Print / Save PDF</button>
    </div>
</div>

<div class="psl">
    <div class="psl-head">
        <div><h2><?= e($org) ?></h2><div class="p">Salary Slip</div></div>
        <div style="text-align:right;"><div style="font-weight:800;font-size:1.05rem;"><?= e(month_label((int) $ps['period_month'])) ?> <?= (int) $ps['period_year'] ?></div><div class="p"><?= e($emp['employee_code']) ?></div></div>
    </div>
    <div class="psl-meta">
        <div><div class="k">Employee</div><div class="v"><?= e($emp['name']) ?></div></div>
        <div><div class="k">Designation</div><div class="v"><?= e($emp['designation'] ?: '—') ?></div></div>
        <div><div class="k">Department</div><div class="v"><?= e($dept['name'] ?? '—') ?></div></div>
        <div><div class="k">Date of joining</div><div class="v"><?= e(!empty($emp['joining_date']) ? format_date($emp['joining_date']) : '—') ?></div></div>
        <?php if ((float) $ps['lop_days'] > 0): ?><div><div class="k">Loss of pay</div><div class="v"><?= e(rtrim(rtrim((string) $ps['lop_days'], '0'), '.')) ?> day(s)</div></div><?php endif; ?>
        <?php if (!empty($ps['note'])): ?><div><div class="k">Note</div><div class="v"><?= e($ps['note']) ?></div></div><?php endif; ?>
    </div>
    <div class="psl-cols">
        <div class="psl-col">
            <h4>Earnings</h4>
            <?php $earnTotal = 0; foreach ($earnLabels as $k => $l): $v = (float) ($bd['earnings'][$k] ?? 0); if ($v <= 0) continue; $earnTotal += $v; ?>
                <div class="psl-row"><span><?= e($l) ?></span><span><?= e(money($v)) ?></span></div>
            <?php endforeach; ?>
            <div class="psl-row total"><span>Gross Earnings</span><span><?= e(money((float) $ps['gross'])) ?></span></div>
        </div>
        <div class="psl-col">
            <h4>Deductions</h4>
            <?php foreach ($dedLabels as $k => $l): $v = (float) ($bd['deductions'][$k] ?? 0); if ($v <= 0) continue; ?>
                <div class="psl-row"><span><?= e($l) ?></span><span><?= e(money($v)) ?></span></div>
            <?php endforeach; ?>
            <div class="psl-row total"><span>Total Deductions</span><span><?= e(money((float) $ps['deductions'])) ?></span></div>
        </div>
    </div>
    <div class="psl-net">
        <div><div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;">Net Payable</div><strong>Amount credited to employee</strong></div>
        <div class="amt"><?= e(money((float) $ps['net'])) ?></div>
    </div>
    <div class="psl-foot">This is a computer-generated payslip and does not require a signature. Generated on <?= e(format_date($ps['generated_at'], 'd M Y')) ?>.</div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
