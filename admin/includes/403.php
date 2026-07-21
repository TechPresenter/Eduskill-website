<?php defined('ESK') || exit('No direct access.'); ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Access denied</title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"><?= palette_style() ?></head>
<body class="admin-shell"><div class="grid min-h-screen place-items-center p-6 text-center">
  <div><p class="font-display text-6xl font-extrabold text-warning-500">403</p>
  <h1 class="mt-3 font-display text-xl font-bold text-content">You don't have access to that</h1>
  <p class="mt-2 text-content-muted">Your account lacks permission for this area.</p>
  <a href="<?= e(url('admin/dashboard.php')) ?>" class="mt-6 inline-block rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-on-brand">Back to dashboard</a></div>
</div></body></html>
