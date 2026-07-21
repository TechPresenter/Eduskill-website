<?php
/** Admin reset-password — validates the hashed single-use token and sets a new password. */
require_once __DIR__ . '/../includes/config.php';
if (is_logged_in()) {
    redirect('admin/dashboard.php');
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$email = (string) ($_GET['email'] ?? $_POST['email'] ?? '');
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $pw = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        $policy = strlen($pw) >= 8 && preg_match('/[A-Z]/', $pw) && preg_match('/[0-9]/', $pw) && preg_match('/[^A-Za-z0-9]/', $pw);
        if ($pw !== $confirm) {
            $error = 'The passwords did not match.';
        } elseif (!$policy) {
            $error = 'Password must be at least 8 characters with an uppercase letter, a number, and a symbol.';
        } else {
            $row = db_one('SELECT * FROM password_resets WHERE email = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1', [$email]);
            if ($row === null || !hash_equals((string) $row['token_hash'], hash('sha256', $token))) {
                $error = 'This reset link is invalid or has expired. Please request a new one.';
            } else {
                $user = db_one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
                if ($user !== null) {
                    db_exec('UPDATE users SET password = ? WHERE id = ?', [password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]), (int) $user['id']]);
                    db_exec('DELETE FROM password_resets WHERE email = ?', [$email]);
                    db_exec('DELETE FROM remember_tokens WHERE user_id = ?', [(int) $user['id']]);
                    activity_log('auth.password_reset', (int) $user['id']);
                    flash('success', 'Your password has been reset. Please sign in.');
                    redirect('admin/login.php');
                }
                $error = 'This reset link is invalid. Please request a new one.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en" class="h-full">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>New password · <?= e(setting('site_name', APP_NAME)) ?></title><meta name="robots" content="noindex"><?= palette_style() ?><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"></head>
<body class="grid min-h-screen place-items-center bg-gradient-to-br from-[#1e1b4b] to-[#0f172a] p-6">
  <div class="w-full max-w-sm rounded-panel bg-surface p-8 shadow-modal">
    <h1 class="font-display text-xl font-bold text-content">Choose a new password</h1>
    <p class="mt-1 text-sm text-content-muted">For <strong><?= e($email) ?></strong></p>
    <?php if ($error): ?><div class="mt-4 rounded-lg border border-danger-200 bg-danger-50 px-4 py-2.5 text-sm text-danger-800"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="mt-5 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="email" value="<?= e($email) ?>">
      <div><label class="block text-sm font-medium text-content">New password</label><input type="password" name="password" autocomplete="new-password" autofocus class="mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2.5 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"><p class="mt-1 text-2xs text-content-subtle">8+ chars, an uppercase letter, a number, and a symbol.</p></div>
      <div><label class="block text-sm font-medium text-content">Confirm password</label><input type="password" name="password_confirmation" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2.5 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"></div>
      <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-on-brand hover:bg-brand-700">Reset password</button>
    </form>
  </div>
</body></html>
