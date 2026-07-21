<?php
defined('ESK') || exit('No direct access.');
$u = current_user();
$initial = strtoupper(mb_substr((string) ($u['name'] ?? '?'), 0, 1));
$admin_title = $admin_title ?? 'Dashboard';
$admin_subtitle = $admin_subtitle ?? '';
$roleLabel = has_role('super_admin') ? 'Super Admin' : (current_roles()[0] ?? 'Staff');
?><!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($admin_title) ?> · <?= e(setting('site_name', APP_NAME)) ?> Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="base-url" content="<?= e(BASE_URL) ?>">
  <script>(function(){try{var t=localStorage.getItem('esk-theme');if(t==='dark'||(!t&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark');}catch(e){}})();</script>
  <?= palette_style() ?>
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="min-h-full bg-surface-sunken">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="min-h-screen lg:pl-72">
    <!-- Topbar -->
    <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-edge bg-surface/90 px-4 backdrop-blur-md sm:px-6">
      <button id="esk-sidebar-toggle" type="button" class="grid h-9 w-9 place-items-center rounded-lg text-content-muted hover:bg-surface-sunken lg:hidden" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
      <div>
        <h1 class="font-display text-lg font-bold tracking-tight text-content"><?= e($admin_title) ?></h1>
        <p class="text-xs text-content-muted"><i class="fa-regular fa-calendar mr-1"></i><?= date('l, d M Y') ?> · <span id="esk-clock"><?= date('h:i A') ?></span></p>
      </div>
      <div class="ml-auto flex items-center gap-1.5">
        <a href="<?= e(url('index.php')) ?>" target="_blank" class="hidden items-center gap-2 rounded-lg border border-edge px-3 py-1.5 text-sm font-medium text-content-muted hover:bg-surface-sunken sm:flex"><i class="fa-solid fa-globe text-accent-500"></i> Live Site</a>
        <button id="esk-theme-toggle" type="button" class="grid h-9 w-9 place-items-center rounded-lg text-content-muted hover:bg-surface-sunken" aria-label="Theme"><span class="hidden dark:inline"><i class="fa-solid fa-sun"></i></span><span class="inline dark:hidden"><i class="fa-solid fa-moon"></i></span></button>
        <details class="relative">
          <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-surface-sunken">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white"><?= e($initial) ?></span>
            <span class="hidden text-left sm:block"><span class="block text-sm font-semibold leading-tight text-content"><?= e($u['name'] ?? '') ?></span><span class="block text-2xs text-content-muted"><?= e($roleLabel) ?></span></span>
          </summary>
          <div class="dropdown">
            <div class="border-b border-edge px-4 py-2.5"><p class="text-sm font-medium text-content"><?= e($u['name'] ?? '') ?></p><p class="truncate text-xs text-content-muted"><?= e($u['email'] ?? '') ?></p></div>
            <a href="<?= e(url('admin/logout.php')) ?>" class="dropdown-item-danger"><i class="fa-solid fa-right-from-bracket"></i> Sign out</a>
          </div>
        </details>
      </div>
    </header>

    <main class="p-4 sm:p-6">
      <?php if ($f = flash('success')): ?><div class="mb-5 flex items-center gap-2 rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800"><i class="fa-solid fa-circle-check"></i><?= e($f) ?></div><?php endif; ?>
      <?php if ($f = flash('error')): ?><div class="mb-5 flex items-center gap-2 rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800"><i class="fa-solid fa-circle-exclamation"></i><?= e($f) ?></div><?php endif; ?>
