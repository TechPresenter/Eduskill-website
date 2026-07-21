<?php
/** Admin login — plain form POST (works without JS). */
require_once __DIR__ . '/../includes/config.php';

if (is_logged_in()) {
    redirect('admin/dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ip = client_ip();
        if ($login === '' || $password === '') {
            $error = 'Please enter your email and password.';
        } elseif (login_locked($login, $ip)) {
            $error = 'Too many attempts. Please wait 15 minutes and try again.';
        } else {
            $user = attempt_login($login, $password);
            record_login_attempt($login, $ip, $user !== null);
            if ($user !== null) {
                login_user($user);
                redirect('admin/dashboard.php');
            }
            $error = "Those credentials don't match our records.";
        }
    }
}
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · <?= e(setting('site_name', APP_NAME)) ?> Admin</title>
  <meta name="robots" content="noindex">
  <?= palette_style() ?>
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="grid min-h-screen place-items-center bg-gradient-to-br from-[#1e1b4b] to-[#0f172a] p-6">
  <div class="w-full max-w-sm rounded-panel bg-surface p-8 shadow-modal">
    <div class="mb-1 flex items-center gap-2.5">
      <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 font-display text-base font-extrabold text-on-brand">E</span>
      <strong class="font-display text-content"><?= e(setting('site_name', 'Eduskill')) ?></strong>
    </div>
    <h1 class="mt-4 font-display text-xl font-bold text-content">Sign in</h1>
    <p class="mt-1 text-sm text-content-muted">Welcome back. Enter your details to continue.</p>

    <?php if ($error): ?>
      <div class="mt-5 rounded-lg border border-danger-200 bg-danger-50 px-4 py-2.5 text-sm text-danger-800"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="mt-5 space-y-4">
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm font-medium text-content" for="login">Email or mobile</label>
        <input id="login" name="login" autocomplete="username" autofocus value="<?= e($_POST['login'] ?? '') ?>"
               class="mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2.5 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
      </div>
      <div>
        <label class="block text-sm font-medium text-content" for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password"
               class="mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2.5 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
      </div>
      <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-on-brand hover:bg-brand-700">Sign in</button>
    </form>
    <a href="<?= e(url('admin/forgot.php')) ?>" class="mt-4 block text-center text-sm font-medium text-brand-600 hover:underline">Forgot your password?</a>
    <p class="mt-6 text-center text-xs text-content-subtle"><?= e(setting('site_name', 'Eduskill India Foundation')) ?> · Admin Panel</p>
  </div>
</body>
</html>
