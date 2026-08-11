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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <?php /* Stylesheet order mirrors admin/partials/head.php — this screen belongs
             to the panel, so it resolves the same way the panel does:

               · premium.css is NOT loaded. It was dropped from the shared admin
                 head (69 KB of public-site decoration); all it contributed here
                 was `body{font-family:var(--font-sans)}` — which tailwind.css
                 already sets — and an 'Outfit' display face the panel does not
                 use, so the Outfit request went with it.
               · theme_style_tag() is called with the 'admin' scope, as the shared
                 head does. Only the sidebar and footer tokens are scope-gated and
                 this page has neither, so the resolved values are unchanged.
               · admin-ds.css loads LAST, after the Theme Engine's inline block,
                 so the radius / elevation / motion scales win deterministically.
               · admin-pro.css is deliberately still omitted: it re-pads .btn-lg
                 and .btn-sm, which would resize this form's controls. Nothing in
                 this page's markup needs it. */ ?>
    <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
    <?php /* The public design system is linked so this screen shares the site's
             --accent-ink, motion and component layer, but it is placed BEFORE
             admin-ds.css on purpose: this page belongs to the panel, and the
             panel's design system must keep the last word on radius, elevation
             and button fills (design-system.css declares a 20px --r-lg and a
             heavier --elev ladder, which are the public site's scales, not the
             panel's). Everything in admin-ds.css is scoped to `body.admin`, so
             it outranks the design system's plain-class rules either way — the
             order simply makes the intent explicit. */ ?>
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <?php echo function_exists('theme_style_tag') ? theme_style_tag('admin') : ''; ?>
    <link rel="stylesheet" href="<?= e(asset('css/admin-ds.css')) ?>">
    <style>
        /* .login-wrap / .login-card are declared in admin.css but used by this
           file alone. One colour-level correction: the backdrop was
           var(--grad-brand), a full-bleed two-stop gradient, which the brand
           direction rules out. Flat brand field instead. Radius and elevation
           need no override — admin.css already reads var(--radius-lg) and
           var(--shadow-lg), which admin-ds.css re-points onto 16px / --elev-3. */
        body.admin .login-wrap { background: var(--brand-600, #0B4E3D); }

        /* ---- Touch targets ------------------------------------------------
           The panel's .form-control rules are all scoped to .admin-content,
           which this page does not have, so the inputs fell back to ui.css's
           .68rem padding — a 43px control. 16px text also stops iOS Safari
           zooming the page when a field takes focus. */
        body.admin .login-card .form-control { min-height: 44px; font-size: 1rem; }
        body.admin .login-card .checkbox {
            display: flex; align-items: center; gap: .6rem;
            min-height: 44px; cursor: pointer; color: var(--text-soft);
        }
        body.admin .login-card .checkbox input {
            width: 1.15rem; height: 1.15rem; margin: 0; flex: none;
            accent-color: var(--brand-600, #0B4E3D);
        }
        body.admin .login-card .btn-block { min-height: 48px; }
        /* The two footer links are the only other touch targets on the page. */
        body.admin .login-card p a {
            display: inline-flex; align-items: center; min-height: 44px;
            color: var(--brand-600, #0B4E3D); font-weight: 600;
        }

        /* ---- 320px --------------------------------------------------------
           2.25rem of card padding either side left 240px of usable width. */
        @media (max-width: 380px) {
            body.admin .login-wrap { padding: var(--sp-3); }
            body.admin .login-card { padding: var(--sp-5) var(--sp-4); }
            body.admin .login-card .login-brand img { width: 64px; height: 64px; }
            body.admin .login-card .login-brand h2 { font-size: 1.25rem; }
        }
    </style>
</head>
<?php /* `admin` is the scoping hook every rule in admin-ds.css hangs off; without
         it this page gets the stylesheet but none of the tokens. The panel's own
         body.admin rule is a background colour, which .login-wrap covers. */ ?>
<body class="admin">
<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand">
            <img src="<?= e(asset('images/logo-128.webp')) ?>" alt="<?= e($siteName) ?>" width="88" height="88" style="margin:0 auto var(--sp-3);display:block;object-fit:contain;">
            <h2 style="margin:0;"><?= e($siteName) ?></h2>
            <p class="text-muted" style="margin:var(--sp-1) 0 0;">Admin Panel — please sign in</p>
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
            <p class="text-center text-muted" style="margin-top:var(--sp-4);font-size:.85rem;">
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

                <label class="checkbox" style="margin-bottom:var(--sp-4);"><input type="checkbox" name="remember" value="1"> Remember me on this device</label>
                <button class="btn btn-primary btn-block btn-lg" type="submit">Sign In</button>
            </form>
        <?php endif; ?>

        <p class="text-center text-muted" style="margin-top:var(--sp-5);font-size:.85rem;">
            <a href="<?= e(url('/')) ?>">← Back to website</a>
        </p>
    </div>
</div>
</body>
</html>
