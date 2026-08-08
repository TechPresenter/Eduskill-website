<?php
/**
 * =============================================================================
 *  Admin login — throttled, CSRF, CAPTCHA-after-3, Remember Me, TOTP 2FA,
 *  optional IP whitelist, password_hash verification.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';

// Optional IP whitelist for the admin area.
if (!admin_ip_allowed()) {
    http_response_code(403);
    exit('Access to the admin panel is restricted from your network.');
}

redirect_if_authenticated('/admin/dashboard');

$errorMsg = '';
$email    = '';
$stage    = !empty($_SESSION['_2fa_user']) ? 'twofa' : 'credentials';

if (is_post()) {
    require_csrf();

    if (post('_stage') === 'twofa') {
        // -- Step 2: verify the authenticator code --
        $result = finish_2fa_login((string) post('code'));
        if ($result['success']) {
            set_flash('success', $result['message']);
            redirect($_SESSION['_intended'] ?? '/admin/dashboard');
        }
        $errorMsg = $result['message'];
        $stage    = empty($result['twofa']) ? 'credentials' : 'twofa';
    } else {
        // -- Step 1: credentials --
        $email    = strtolower(clean(post('email')));
        $password = (string) post('password');

        if ($email === '' || $password === '') {
            $errorMsg = 'Please enter both email and password.';
        } elseif (login_needs_captcha($email) && !captcha_verify()) {
            $errorMsg = 'Incorrect answer to the security question. Please try again.';
        } else {
            $result = login($email, $password);
            if ($result['success']) {
                if (post('remember')) {
                    remember_issue('users', current_user_id(), 'pwf_remember');
                }
                $intended = $_SESSION['_intended'] ?? '/admin/dashboard';
                unset($_SESSION['_intended']);
                set_flash('success', $result['message']);
                redirect($intended);
            } elseif (!empty($result['twofa'])) {
                $stage    = 'twofa';
                $errorMsg = '';
            } else {
                $errorMsg = $result['message'];
            }
        }
    }
}

$showCaptcha = ($stage === 'credentials') && login_needs_captcha($email);
$captcha     = $showCaptcha ? captcha_new() : null;
$siteName    = get_setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Admin Login · <?= e($siteName) ?></title>
    <link rel="icon" href="<?= e(asset('images/favicon.jpg')) ?>" type="image/jpeg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
    <?php echo function_exists('theme_style_tag') ? theme_style_tag('site') : ''; ?>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand">
            <img src="<?= e(asset('images/logo-128.webp')) ?>" alt="<?= e($siteName) ?>" width="88" height="88" style="margin:0 auto .75rem;display:block;object-fit:contain;">
            <h2 style="margin:0;"><?= e($siteName) ?></h2>
            <p class="text-muted" style="margin:.25rem 0 0;">Admin Panel — please sign in</p>
        </div>

        <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-error"><?= e($errorMsg) ?></div>
        <?php endif; ?>

        <?php if ($stage === 'twofa'): ?>
            <!-- Two-factor verification -->
            <p class="text-muted" style="font-size:.9rem;">Open your authenticator app and enter the current 6-digit code.</p>
            <form method="post" action="<?= e(admin_url('login')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_stage" value="twofa">
                <div class="form-group">
                    <label class="form-label" for="code">Authentication code</label>
                    <input class="form-control" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                           pattern="[0-9]{6}" maxlength="6" required autofocus style="letter-spacing:.4em;text-align:center;font-size:1.3rem;">
                </div>
                <button class="btn btn-primary btn-block btn-lg" type="submit">Verify &amp; Sign In</button>
            </form>
            <p class="text-center text-muted" style="margin-top:1rem;font-size:.85rem;">
                <a href="<?= e(admin_url('login')) ?>">← Start over</a>
            </p>
        <?php else: ?>
            <!-- Credentials -->
            <form method="post" action="<?= e(admin_url('login')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                </div>

                <?php if ($showCaptcha): ?>
                    <div class="form-group">
                        <label class="form-label" for="captcha">Security check: what is <?= (int) $captcha['a'] ?> + <?= (int) $captcha['b'] ?>?</label>
                        <input class="form-control" type="number" id="captcha" name="captcha" required autocomplete="off">
                    </div>
                <?php endif; ?>

                <label class="checkbox" style="margin-bottom:1rem;"><input type="checkbox" name="remember" value="1"> Remember me on this device</label>
                <button class="btn btn-primary btn-block btn-lg" type="submit">Sign In</button>
            </form>
        <?php endif; ?>

        <p class="text-center text-muted" style="margin-top:1.25rem;font-size:.85rem;">
            <a href="<?= e(url('/')) ?>">← Back to website</a>
        </p>
    </div>
</div>
</body>
</html>
