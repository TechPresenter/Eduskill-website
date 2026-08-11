<?php
/**
 * Member logout — POST-only with CSRF so a bare link or forged image tag can't
 * end the session. A GET renders a tiny confirmation page whose form posts back
 * here with a token.
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    require_csrf();
    member_logout();
    set_flash('success', 'You have been signed out.');
    redirect('/login');
}

$siteName = get_setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($siteName) ?> — Sign Out</title>
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
<style>
.lo-shell{ min-height:100vh; display:grid; place-items:center; padding:2rem 1.25rem; background:var(--grad-soft, #f1faf5); }
.lo-card{ width:100%; max-width:400px; text-align:center; padding:2.2rem 1.9rem; border-radius:22px;
    background:var(--surface, #fff); border:1px solid var(--border, rgba(0,0,0,.08));
    box-shadow:0 24px 60px -24px rgba(6,78,59,.35); }
.lo-card h1{ font-size:1.45rem; margin:.2rem 0 .4rem; }
.lo-card p{ color:var(--muted); margin:0 0 1.4rem; }
.lo-badge{ width:58px; height:58px; margin:0 auto .9rem; border-radius:18px; display:grid; place-items:center;
    color:#fff; background:linear-gradient(135deg,#0B4E3D,#174D3D); }
.lo-badge svg.lucide{ width:26px; height:26px; }
.lo-cancel{ display:inline-flex; align-items:center; gap:.35rem; margin-top:1.1rem; font-weight:700; color:var(--muted); }
.lo-cancel:hover{ color:#0B4E3D; }
</style>
</head>
<body>
<div class="lo-shell">
    <div class="lo-card">
        <span class="lo-badge"><?= lucide('log-out') ?></span>
        <h1>Sign out?</h1>
        <p>You'll need to sign in again to access your account.</p>
        <form method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-3d btn-block btn-lg"><?= lucide('log-out') ?> Yes, sign me out</button>
        </form>
        <a class="lo-cancel" href="<?= e(url('account')) ?>"><?= lucide('arrow-left') ?> Cancel — back to my account</a>
    </div>
</div>
<script src="<?= e(asset('vendor/lucide.min.js')) ?>"></script>
<script>try { window.lucide && window.lucide.createIcons(); } catch (e) {}</script>
</body>
</html>
