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
<?php /* Same link order as includes/header.php. ui.css and the design system
         were both missing here, so this screen rendered none of the redesign.
         The design system loads LAST of the stylesheets so its scales win; the
         Theme Engine block follows because it only emits the custom properties
         the design system reads. */ ?>
<link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
<?php echo function_exists('theme_style_tag') ? theme_style_tag('site') : ''; ?>
<style>
/* =========================================================================
   Reset Password — page-scoped layer ON TOP of design-system.css.
   ========================================================================= */

/* premium.css now ramps the aside #0F4537 → #0B4E3D → #174D3D, all three in
   palette, so this is no longer a colour correction — it is the brand rule that
   a brand field is ONE flat colour, not a gradient (the same call .cta-band and
   .page-hero make). Flat brand field; white ink is 9.67:1 on it. */
.auth-aside{ background: var(--primary, #0B4E3D); }
/* The decorative blobs inherited --grad-ocean / --grad-purple; the second ends
   in #a855f7. Re-pointed onto the brand tokens. */
.auth-aside .blob.b1{ background: color-mix(in srgb, #fff 22%, transparent); }
.auth-aside .blob.b2{ background: color-mix(in srgb, var(--yellow, #FFE987) 42%, transparent); }
.auth-aside .aside-points li svg{ background: rgba(255,255,255,.22); }

/* ---------------------------------------------------------------- FORM */
.auth-main{ padding: clamp(1.25rem, 4vw, 3rem) clamp(.85rem, 3vw, 1.5rem); }
.auth-box{ max-width: 468px; }
.auth-box .card{
    position: relative; overflow: hidden;
    padding: clamp(1.5rem, 3.4vw, 2.5rem);
    border-radius: var(--r-xl); box-shadow: var(--elev-4);
}
.auth-box .card::before{
    content: ""; position: absolute; inset: 0 0 auto; height: 5px;
    background: var(--yellow, #FFE987);
}
.auth-box .card:hover{ transform: none; box-shadow: var(--elev-4); border-color: var(--border, #C1CCB3); }

/* Success badge: flat highlight yellow with brand-green ink (7.95:1); it was
   var(--grad-accent). The aside's own badge keeps its inline treatment. */
.auth-box .icon-badge{
    width: 64px; height: 64px; margin: 0 auto 1rem; border-radius: var(--r-md);
    background: var(--yellow, #FFE987); color: var(--primary, #0B4E3D); box-shadow: none;
}
.auth-box .icon-badge svg.lucide{ width: 28px; height: 28px; }
/* Invalid-link state: the danger token (4.83:1 on white) instead of #ef4444,
   which is 3.76:1 and was carrying a glyph. */
.auth-box .icon-badge.is-danger{
    background: color-mix(in srgb, var(--danger, #DC2626) 12%, transparent);
    color: var(--danger, #DC2626); font-size: 1.8rem; font-weight: 800;
}
.auth-box .section-head{ margin-bottom: 1.4rem; }
.auth-box .section-title{ font-size: clamp(1.55rem, 5.4vw, 1.95rem); line-height: 1.15; }
.auth-box .section-subtitle{ font-size: .96rem; line-height: 1.6; }
.auth-box .section-subtitle strong{ color: var(--text, #151818); }

/* Fields: 16px text (smaller makes iOS zoom on focus) + token focus ring. */
.field-float input{ font-size: 1rem; border-radius: var(--r-sm); }
.field-float input:focus{
    border-color: var(--primary, #0B4E3D);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #0B4E3D) 16%, transparent);
}
.field-float input:focus ~ label,
.field-float input:not(:placeholder-shown) ~ label{ color: var(--primary, #0B4E3D); }

/* Password show/hide: 20px mark inside a 44px touch target. */
#password, #confirm_password{ padding-right: 3.25rem; }
.field-float .toggle-pass{
    display: inline-flex; align-items: center; justify-content: center;
    width: 44px; height: 44px; min-width: 44px;
    right: .35rem; top: 50%; transform: translateY(-50%);
    border-radius: var(--r-sm); color: var(--muted, #4B6754);
    transition: color var(--dur, .3s) var(--ease-out, ease),
                background-color var(--dur, .3s) var(--ease-out, ease);
}
.field-float .toggle-pass:hover{
    color: var(--primary, #0B4E3D);
    background: color-mix(in srgb, var(--primary, #0B4E3D) 9%, transparent);
}
.field-float .toggle-pass svg.lucide{ width: 20px; height: 20px; }

.pw-strength{ border-radius: var(--r-pill); }
/* --warning resolves to the CTA orange (3.37:1 on white); --accent-ink is the
   same orange one step deeper at 4.60:1, so the medium bar matches the label
   colour used on the signup screen. */
.pw-strength.medium span{ background: var(--accent-ink, #CA4C1E); }

.auth-box a{ font-weight: 700; }
.alert{ border-radius: var(--r-sm); }
.auth-back{
    display: inline-flex; align-items: center; gap: .4rem; min-height: 44px;
    font-size: .9rem; font-weight: 600; color: var(--muted, #4B6754);
}
.auth-back:hover{ color: var(--primary, #0B4E3D); }
.auth-back svg.lucide{ width: 15px; height: 15px; }

@media (max-width: 540px){ .auth-box{ max-width: 100%; } }
@media (max-width: 380px){
    .auth-main{ padding: 1rem .7rem; }
    .auth-box .card{ padding: 1.3rem 1.05rem; }
}
</style>
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
            <?php /* The "25,000+ lives impacted / 800+ volunteers" counters that
                     sat here are removed: neither figure is sourced anywhere in
                     the project, and an auth screen is the last place to assert
                     an unverified number. forgot-password.php's aside was
                     restored without them for the same reason, so the two
                     screens match. */ ?>
        </div>
    </aside>

    <!-- ============================== RESET FORM ============================== -->
    <main class="auth-main">
        <div class="auth-box">
            <div class="card">

                <?php // Pending flashes (redirects that land on /reset-password). ?>
                <?php foreach (get_flashes() as $fType => $fMsgs):
                    $fClass = in_array($fType, ['success', 'error', 'warning', 'info'], true) ? $fType : 'info';
                    foreach ($fMsgs as $fMsg): ?>
                    <div class="alert alert-<?= e($fClass) ?>"><?= lucide($fClass === 'success' ? 'circle-check' : ($fClass === 'error' ? 'triangle-alert' : 'info')) ?> <?= e($fMsg) ?></div>
                <?php endforeach; endforeach; ?>

                <?php if ($success): ?>
                    <!-- ------------------------ SUCCESS STATE ------------------------ -->
                    <div class="text-center">
                        <div class="section-head is-centered">
                            <span class="icon-badge"><?= lucide('check') ?></span>
                            <h2 class="section-title">Password updated</h2>
                            <p class="section-subtitle"><?= e($successMsg) ?></p>
                        </div>
                        <a href="<?= e(url('login')) ?>" class="btn btn-secondary btn-block btn-lg">Continue to Sign In</a>
                        <p class="text-center mt-3" style="margin-bottom:0;">
                            <a href="<?= e(url('')) ?>" class="auth-back"><?= lucide('arrow-left') ?> Back to homepage</a>
                        </p>
                    </div>

                <?php elseif (!$linkOk): ?>
                    <!-- ------------------------ INVALID LINK STATE ------------------------ -->
                    <div class="section-head is-centered">
                        <span class="icon-badge is-danger">!</span>
                        <h2 class="section-title">Link not valid</h2>
                        <p class="section-subtitle">This password-reset link is missing or has expired. Please request a fresh link and try again.</p>
                    </div>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= e($error) ?></div>
                    <?php endif; ?>
                    <a href="<?= e(url('forgot-password')) ?>" class="btn btn-secondary btn-block btn-lg">Request a new link</a>
                    <p class="text-center mt-3" style="margin-bottom:0;">
                        Remembered it? <a class="link-underline" href="<?= e(url('login')) ?>">Sign in</a>
                    </p>

                <?php else: ?>
                    <!-- ------------------------ RESET FORM ------------------------ -->
                    <div class="section-head is-centered">
                        <span class="eyebrow">Account Recovery</span>
                        <h2 class="section-title">Reset Password</h2>
                        <p class="section-subtitle">
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

                        <button class="btn btn-secondary btn-block btn-lg mt-2" type="submit">Update Password</button>
                    </form>

                    <p class="text-center mt-3" style="margin-bottom:0;">
                        Remembered it? <a class="link-underline" href="<?= e(url('login')) ?>">Back to Sign In</a>
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!$success): ?>
                <p class="text-center mt-3">
                    <a href="<?= e(url('')) ?>" class="auth-back"><?= lucide('arrow-left') ?> Back to homepage</a>
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
