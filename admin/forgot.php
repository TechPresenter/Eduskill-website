<?php
/** Admin forgot-password — emails a reset link (hashed, single-use, 30-min token). */
require_once __DIR__ . '/../includes/config.php';
if (is_logged_in()) {
    redirect('admin/dashboard.php');
}

$done = false;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user = db_one('SELECT id, name FROM users WHERE email = ? AND status = "active" LIMIT 1', [$email]);
            if ($user !== null) {
                $token = bin2hex(random_bytes(32));
                db_exec('DELETE FROM password_resets WHERE email = ?', [$email]);
                db_insert(
                    'INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, (NOW() + INTERVAL 30 MINUTE))',
                    [$email, hash('sha256', $token)]
                );
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('admin/reset.php')
                    . '?token=' . $token . '&email=' . rawurlencode($email);
                send_mail(
                    $email,
                    'Reset your Eduskill admin password',
                    '<p>Hi ' . e((string) $user['name']) . ',</p><p>We received a request to reset your password. This link is valid for 30 minutes:</p>'
                        . '<p><a href="' . e($link) . '">' . e($link) . '</a></p><p>If you did not request this, you can ignore this email.</p>',
                    (string) $user['name']
                );
                activity_log('auth.password_reset_requested', (int) $user['id']);
            }
        }
        $done = true; // same response whether or not the email exists (no enumeration)
    }
}
?>
<!doctype html>
<html lang="en" class="h-full">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset password · <?= e(setting('site_name', APP_NAME)) ?></title><meta name="robots" content="noindex"><?= palette_style() ?><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"></head>
<body class="grid min-h-screen place-items-center bg-gradient-to-br from-[#1e1b4b] to-[#0f172a] p-6">
  <div class="w-full max-w-sm rounded-panel bg-surface p-8 shadow-modal">
    <h1 class="font-display text-xl font-bold text-content">Reset your password</h1>
    <?php if ($done): ?>
      <div class="mt-4 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800">If that email is registered, a reset link is on its way. (In test mode the link is written to the email log.)</div>
      <a href="<?= e(url('admin/login.php')) ?>" class="mt-6 block text-center text-sm font-medium text-brand-600 hover:underline">← Back to sign in</a>
    <?php else: ?>
      <p class="mt-1 text-sm text-content-muted">Enter your account email and we'll send a reset link.</p>
      <?php if ($error): ?><div class="mt-4 rounded-lg border border-danger-200 bg-danger-50 px-4 py-2.5 text-sm text-danger-800"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="mt-5 space-y-4"><?= csrf_field() ?>
        <div><label class="block text-sm font-medium text-content">Email</label><input type="email" name="email" autofocus required class="mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2.5 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"></div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-on-brand hover:bg-brand-700">Send reset link</button>
      </form>
      <a href="<?= e(url('admin/login.php')) ?>" class="mt-5 block text-center text-sm font-medium text-content-muted hover:text-content">← Back to sign in</a>
    <?php endif; ?>
  </div>
</body></html>
