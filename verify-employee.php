<?php
/**
 * =============================================================================
 *  Public — Employee verification (target of the ID-card QR).
 *  ?code=EMP0007 -> shows the employee's non-sensitive identity + a verified
 *  badge, or a "not found" state. No auth; exposes only public-safe fields.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$code      = strtoupper(trim((string) request('code', '')));
$emp       = null;
$throttled = false;
if ($code !== '') {
    /* Own bucket — see the note in verify-award.php. The five public verifiers
       shared one 'verify-lookup' key, so they spent each other's budget. */
    if (!pwf_throttle('verify-employee', 10, 300)) {
        $throttled = true;
    } else {
        $emp = db_row("SELECT * FROM employees WHERE employee_code = :c", [':c' => $code]);
    }
}

$dept  = ($emp && $emp['department_id']) ? find('departments', (int) $emp['department_id']) : null;
$valid = $emp && $emp['status'] === 'active';
$org   = get_setting('site_name', SITE_NAME);

seo_set(['title' => 'Verify Employee', 'page_key' => 'verify-employee', 'robots' => 'noindex,nofollow']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($org) ?> — Verify Employee</title>
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
</head>
<body>
<div class="auth-shell">
    <div class="auth-main"><div class="auth-box"><div class="glass-card" style="text-align:center;">
        <?php if ($throttled): ?>
            <span class="icon-badge" style="width:64px;height:64px;margin:0 auto 1rem;background:#b45309;color:#fff;"><?= lucide('clock') ?></span>
            <h2 style="margin:0 0 .5rem;">Too many attempts</h2>
            <p class="text-muted">Too many verification attempts from your connection. Please wait a few minutes and try again.</p>
        <?php elseif ($emp): ?>
            <span class="icon-badge <?= $valid ? '' : 'accent' ?>" style="width:64px;height:64px;margin:0 auto 1rem;background:<?= $valid ? 'linear-gradient(135deg,#0B4E3D,#174D3D)' : '#dc2626' ?>;color:#fff;">
                <?= $valid ? lucide('badge-check') : lucide('alert-triangle') ?>
            </span>
            <h2 style="margin:0 0 .25rem;"><?= e($emp['name']) ?></h2>
            <p class="text-muted" style="margin:0 0 1rem;font-weight:700;letter-spacing:.04em;"><?= e($emp['employee_code']) ?></p>
            <img src="<?= e(image_url($emp['photo'] ?? null, 'avatar')) ?>" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid #0B4E3D;margin-bottom:1rem;">
            <div style="display:inline-block;background:<?= $valid ? 'rgba(47,128,101,.14)' : 'rgba(220,38,38,.12)' ?>;color:<?= $valid ? '#1F5C48' : '#b91c1c' ?>;font-weight:800;padding:.4rem 1rem;border-radius:999px;font-size:.8rem;margin-bottom:1.2rem;">
                <?= $valid ? 'VERIFIED EMPLOYEE' : strtoupper(e(employee_status_labels()[$emp['status']] ?? 'INACTIVE')) ?>
            </div>
            <div style="text-align:left;border-top:1px solid var(--border);padding-top:1rem;font-size:.9rem;">
                <?php
                $rows = [
                    'Designation' => $emp['designation'] ?: '—',
                    'Department'  => $dept['name'] ?? '—',
                    'Type'        => employment_type_labels()[$emp['employment_type']] ?? '—',
                    'Organisation'=> $org,
                ];
                foreach ($rows as $k => $v): ?>
                    <div style="display:flex;justify-content:space-between;gap:1rem;padding:.35rem 0;">
                        <span class="text-muted"><?= e($k) ?></span><strong><?= e($v) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <span class="icon-badge" style="width:64px;height:64px;margin:0 auto 1rem;background:#6b7280;color:#fff;"><?= lucide('search-x') ?></span>
            <h2 style="margin:0 0 .5rem;">No match found</h2>
            <p class="text-muted"><?= $code === '' ? 'No employee code supplied.' : 'No employee matches the code “' . e($code) . '”.' ?></p>
        <?php endif; ?>
        <p class="text-center mt-3" style="margin-bottom:0;">
            <a href="<?= e(url('')) ?>" class="text-muted" style="font-size:.9rem;"><?= lucide('arrow-left') ?> <?= e($org) ?></a>
        </p>
    </div></div></div>
</div>
<script src="<?= e(asset('vendor/lucide.min.js')) ?>"></script>
<script>window.lucide && window.lucide.createIcons();</script>
</body>
</html>
