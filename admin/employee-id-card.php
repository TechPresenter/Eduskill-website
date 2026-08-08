<?php
/**
 * =============================================================================
 *  Admin — Employee ID Card (branded, printable, with verification QR)
 *  On-screen HTML/CSS card + inline SVG QR. "Print / Save as PDF" uses the
 *  browser print pipeline; a scoped print stylesheet isolates the card.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/lib/qrcode.php';

$id  = (int) get('id', 0);
$emp = $id ? find('employees', $id) : null;
if (!$emp) { set_flash('error', 'Employee not found.'); redirect('/admin/employees'); }

$dept    = $emp['department_id'] ? find('departments', (int) $emp['department_id']) : null;
$org     = get_setting('site_name', SITE_NAME);
$mono    = strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $org) ?: 'P', 0, 1));
$photo   = image_url($emp['photo'] ?? null, 'avatar');
$joined  = !empty($emp['joining_date']) ? format_date($emp['joining_date'], 'M Y') : '—';
$host    = parse_url(APP_URL, PHP_URL_HOST) ?: 'eduskillindia.org';
$qrSvg   = PWF_QR::encode(employee_verify_url($emp), 'M')->svg(2, 4, '#0f172a', '#ffffff');
$typeLbl = employment_type_labels()[$emp['employment_type']] ?? $emp['employment_type'];

$page_title = 'ID Card — ' . $emp['name'];
include __DIR__ . '/partials/head.php';
?>
<style>
    .emp-idc-wrap { display: flex; flex-direction: column; align-items: center; gap: 1.25rem; }
    .emp-card {
        width: 560px; max-width: 100%; border-radius: 20px; overflow: hidden; color: #111827;
        background: #fff; box-shadow: 0 20px 50px -18px rgba(6,53,102,.4); font-family: 'Roboto', system-ui, sans-serif;
        border: 1px solid #e2e8f0;
    }
    .emp-card-head { background: linear-gradient(135deg, #063566, #084881); color: #fff; padding: 16px 20px; display: flex; align-items: center; gap: 12px; }
    .emp-card-head .mono { width: 46px; height: 46px; border-radius: 50%; background: #fff; color: #063566; display: grid; place-items: center; font-weight: 800; font-size: 1.25rem; flex: none; }
    .emp-card-head strong { font-size: 1.05rem; display: block; line-height: 1.2; }
    .emp-card-head span { font-size: .68rem; letter-spacing: .16em; opacity: .92; }
    .emp-card-body { display: flex; gap: 18px; padding: 20px; }
    .emp-photo { width: 118px; height: 148px; object-fit: cover; border-radius: 14px; border: 3px solid #063566; flex: none; background: #f1f5f9; }
    .emp-info { flex: 1; min-width: 0; }
    .emp-name { font-size: 1.4rem; font-weight: 800; line-height: 1.1; }
    .emp-code { color: #084881; font-weight: 700; margin: 2px 0 10px; letter-spacing: .04em; }
    .emp-rows { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 14px; font-size: .82rem; }
    .emp-rows .k { color: #64748b; font-size: .64rem; letter-spacing: .06em; text-transform: uppercase; }
    .emp-rows .v { font-weight: 700; color: #111827; }
    .emp-qr { flex: none; text-align: center; width: 104px; }
    .emp-qr > div { width: 96px; height: 96px; margin: 0 auto; }
    .emp-qr small { display: block; font-size: .6rem; color: #64748b; margin-top: 3px; }
    .emp-card-foot { background: #0f3d2b; color: #d1fae5; font-size: .68rem; padding: 8px 20px; text-align: center; letter-spacing: .02em; }
    .emp-status { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .66rem; font-weight: 800; color: #fff; }
    @media print {
        .admin-sidebar, .admin-topbar, .admin-overlay, .admin-page-head, .emp-idc-actions, .no-print { display: none !important; }
        .admin-main { margin-left: 0 !important; }
        .admin-content { padding: 0 !important; }
        .emp-card { box-shadow: none; border: 1px solid #cbd5e1; }
        body, .admin-layout { background: #fff !important; }
    }
</style>

<div class="admin-page-head">
    <div><h1>Employee ID Card</h1><span class="muted"><?= e($emp['employee_code']) ?> · <?= e($emp['name']) ?></span></div>
    <div class="flex emp-idc-actions" style="gap:.5rem;">
        <a class="btn btn-ghost" href="<?= e(admin_url('employees?action=edit&id=' . $emp['id'])) ?>">&larr; Back</a>
        <button class="btn btn-primary" type="button" onclick="window.print()"><?= lucide('printer') ?> Print / Save PDF</button>
    </div>
</div>

<div class="emp-idc-wrap">
    <div class="emp-card">
        <div class="emp-card-head">
            <span class="mono"><?= e($mono) ?></span>
            <div><strong><?= e($org) ?></strong><span>EMPLOYEE ID CARD</span></div>
        </div>
        <div class="emp-card-body">
            <img class="emp-photo" src="<?= e($photo) ?>" alt="">
            <div class="emp-info">
                <div class="emp-name"><?= e($emp['name']) ?></div>
                <div class="emp-code"><?= e($emp['employee_code']) ?></div>
                <div class="emp-rows">
                    <div><div class="k">Designation</div><div class="v"><?= e($emp['designation'] ?: '—') ?></div></div>
                    <div><div class="k">Department</div><div class="v"><?= e($dept['name'] ?? '—') ?></div></div>
                    <div><div class="k">Type</div><div class="v"><?= e($typeLbl) ?></div></div>
                    <div><div class="k">Joined</div><div class="v"><?= e($joined) ?></div></div>
                    <?php if (!empty($emp['blood_group'])): ?><div><div class="k">Blood group</div><div class="v"><?= e($emp['blood_group']) ?></div></div><?php endif; ?>
                    <?php if (!empty($emp['phone'])): ?><div><div class="k">Phone</div><div class="v"><?= e($emp['phone']) ?></div></div><?php endif; ?>
                </div>
                <div style="margin-top:10px;">
                    <span class="emp-status" style="background:<?= $emp['status'] === 'active' ? '#58A42F' : ($emp['status'] === 'terminated' ? '#dc2626' : '#6b7280') ?>;"><?= strtoupper(e(employee_status_labels()[$emp['status']] ?? $emp['status'])) ?></span>
                </div>
            </div>
            <div class="emp-qr">
                <div><?= $qrSvg ?></div>
                <small>Scan to verify</small>
            </div>
        </div>
        <div class="emp-card-foot">Verify this card at <?= e($host) ?>/verify-employee</div>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
