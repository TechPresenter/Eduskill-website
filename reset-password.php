<?php
/**
 * =============================================================================
 *  Reset Password — standalone premium auth shell (DEV_CONTRACT_V2 §4).
 *  Renders its own <head> (tailwind.css + premium.css); no public header/footer.
 *
 *  Flow: user arrives from the emailed link  /reset-password?email=&token=
 *  The email + token are carried in hidden fields and only consumed on POST via
 *  reset_member_password(). On success we show a confirmation + a link to login.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$email   = clean(get('email'));
$token   = trim((string) get('token'));
$error   = '';
$success = false;
$successMsg = '';

if (is_post()) {
    require_csrf();

    $email    = clean(post('email'));
    $token    = trim((string) post('token'));
    $password = (string) post('password');
    $confirm  = (string) post('confirm_password');

    if ($email === '' || $token === '') {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (mb_strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match. Please re-enter them.';
    } else {
        $r = reset_member_password($email, $token, $password);
        if (!empty($r['success'])) {
            $success    = true;
            $successMsg = $r['message'];
        } else {
            $error = $r['message'];
        }
    }
}

// The form can only work with both an email and a token present.
$linkOk = ($email !== '' && $token !== '');

seo_set([
    'title'    => 'Reset Password',
    'page_key' => 'reset-password',
    'robots'   => 'noindex,nofollow',
]);

$siteName = get_setting('site_name', SITE_NAME);
$tagline  = get_setting('site_tagline', SITE_TAGLINE);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<?= csrf_meta() ?>
<title><?= e($siteName) ?> — Reset Password</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
</head>
<body>
<div class="auth-shell">

    <!-- ============================== BRAND ASIDE ============================== -->
    <aside class="auth-aside">
        <span class="blob b1" style="top:-60px;left:-50px;"></span>
        <span class="blob b2" style="bottom:-70px;right:-40px;"></span>
        <div class="floating-shapes" aria-hidden="true">
            <span class="shape" style="top:16%;left:14%;"></span>
            <span class="shape" style="top:62%;left:80%;"></span>
            <span class="shape" style="top:44%;left:48%;"></span>
        </div>
        <div class="aside-inner">
            <a href="<?= e(url('')) ?>" class="flex items-center gap-2 mb-4" style="color:#fff;text-decoration:none;font-weight:800;font-size:1.15rem;">
                <span class="icon-badge" style="background:rgba(255,255,255,.2);width:44px;height:44px;"><?= lucide('shield') ?></span>
                <?= e($siteName) ?>
            </a>
            <h2>Set a new password <?= lucide('lock') ?></h2>
            <p class="aside-tagline"><?= e($tagline) ?></p>
            <p style="font-size:1.05rem;">Choose a strong password to keep your membership, donations and volunteering safe.</p>
            <ul class="aside-points">
                <li><?= lucide('check') ?> Use at least 8 characters</li>
                <li><?= lucide('check') ?> Mix upper &amp; lower case letters</li>
                <li><?= lucide('check') ?> Add a number and a symbol</li>
                <li><?= lucide('check') ?> Avoid reusing an old password</li>
            </ul>
            <div class="flex gap-4 mt-4">
                <div>
                    <div style="font-size:1.8rem;font-weight:800;line-height:1;"><span data-counter="25000">0</span>+</div>
                    <small style="color:rgba(255,255,255,.85);">Lives impacted</small>
                </div>
                <div>
                    <div style="font-size:1.8rem;font-weight:800;line-height:1;"><span data-counter="800">0</span>+</div>
                    <small style="color:rgba(255,255,255,.85);">Volunteers</small>
                </div>
            </div>
        </div>
    </aside>

    <!-- ============================== RESET FORM ============================== -->
    <main class="auth-main">
        <div class="auth-box">
            <div class="glass-card">

                <?php // Pending flashes (redirects that land on /reset-password). ?>
                <?php foreach (get_flashes() as $fType => $fMsgs):
                    $fClass = in_array($fType, ['success', 'error', 'warning', 'info'], true) ? $fType : 'info';
                    foreach ($fMsgs as $fMsg): ?>
                    <div class="alert alert-<?= e($fClass) ?>"><?= lucide($fClass === 'success' ? 'circle-check' : ($fClass === 'error' ? 'triangle-alert' : 'info')) ?> <?= e($fMsg) ?></div>
                <?php endforeach; endforeach; ?>

                <?php if ($success): ?>
                    <!-- ------------------------ SUCCESS STATE ------------------------ -->
                    <div class="text-center">
                        <span class="icon-badge accent" style="width:64px;height:64px;font-size:1.8rem;margin:0 auto 1rem;"><?= lucide('check') ?></span>
                        <h2 class="mb-1" style="font-size:1.9rem;">Password updated</h2>
                        <p class="text-muted"><?= e($successMsg) ?></p>
                        <a href="<?= e(url('login')) ?>" class="btn btn-3d btn-block btn-lg mt-3">Continue to Sign In</a>
                        <p class="text-center mt-3" style="margin-bottom:0;">
                            <a href="<?= e(url('')) ?>" class="text-muted" style="font-size:.9rem;"><?= lucide('arrow-left') ?> Back to homepage</a>
                        </p>
                    </div>

                <?php elseif (!$linkOk): ?>
                    <!-- ------------------------ INVALID LINK STATE ------------------------ -->
                    <div class="text-center mb-3">
                        <span class="icon-badge" style="width:64px;height:64px;font-size:1.8rem;margin:0 auto 1rem;background:rgba(239,68,68,.12);color:#ef4444;">!</span>
                        <h2 class="mb-1" style="font-size:1.9rem;">Link not valid</h2>
                        <p class="text-muted">This password-reset link is missing or has expired. Please request a fresh link and try again.</p>
                    </div>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= e($error) ?></div>
                    <?php endif; ?>
                    <a href="<?= e(url('forgot-password')) ?>" class="btn btn-3d btn-block btn-lg">Request a new link</a>
                    <p class="text-center mt-3" style="margin-bottom:0;">
                        Remembered it? <a href="<?= e(url('login')) ?>" style="font-weight:700;">Sign in</a>
                    </p>

                <?php else: ?>
                    <!-- ------------------------ RESET FORM ------------------------ -->
                    <div class="text-center mb-3">
                        <span class="eyebrow" style="justify-content:center;">Account Recovery</span>
                        <h2 class="mb-0" style="font-size:1.9rem;">Reset Password</h2>
                        <p class="text-muted mt-1" style="margin-bottom:0;">
                            Create a new password for
                            <strong><?= e($email) ?></strong>.
                        </p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="email" value="<?= e($email) ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div class="field-float">
                            <input type="password" id="password" name="password" placeholder=" " required minlength="8"
                                   autocomplete="new-password" data-password-strength="#pwMeter" autofocus>
                            <button type="button" class="toggle-pass" data-toggle-password="#password" aria-label="Show password"><?= lucide('eye') ?></button>
                            <label for="password">New password</label>
                        </div>
                        <div class="pw-strength" id="pwMeter"><span></span></div>
                        <p class="form-hint mb-3">Use 8+ characters with a mix of letters, numbers &amp; symbols.</p>

                        <div class="field-float">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder=" " required minlength="8"
                                   autocomplete="new-password">
                            <button type="button" class="toggle-pass" data-toggle-password="#confirm_password" aria-label="Show password"><?= lucide('eye') ?></button>
                            <label for="confirm_password">Confirm new password</label>
                        </div>

                        <button class="btn btn-3d btn-block btn-lg mt-2" type="submit">Update Password</button>
                    </form>

                    <p class="text-center mt-3" style="margin-bottom:0;">
                        Remembered it? <a href="<?= e(url('login')) ?>" style="font-weight:700;">Back to Sign In</a>
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!$success): ?>
                <p class="text-center mt-3">
                    <a href="<?= e(url('')) ?>" class="text-muted" style="font-size:.9rem;"><?= lucide('arrow-left') ?> Back to homepage</a>
                </p>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<script src="<?= e(asset('js/premium.js')) ?>"></script>
<!-- Lucide icons (standalone auth shell — no shared footer) -->
<script src="<?= e(asset('vendor/lucide.min.js')) ?>"></script>
<script>
    (function () {
        function draw() { try { window.lucide && window.lucide.createIcons(); } catch (e) {} }
        draw();
        document.addEventListener('DOMContentLoaded', draw);
        window.PWFdrawIcons = draw;
    })();
</script>
</body>
</html>
