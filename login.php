<?php
/**
 * =============================================================================
 *  Member Login — standalone premium auth shell (DEV_CONTRACT_V2 §4).
 *  Renders its own <head> (tailwind.css + premium.css); no public header/footer.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';   // social sign-in availability + URLs

/* -----------------------------------------------------------------------------
 | Portal context
 | -----------------------------------------------------------------------------
 | .htaccess maps /login/{role} -> login.php?portal={role} (an explicit role
 | whitelist, never an open capture). The portal only changes the WORDING on
 | this page — the badge, heading and blurb. It never decides where a successful
 | sign-in lands: that comes from the authenticated account's own role via
 | auth_post_login_target(), so opening /login/member with a student account
 | still ends at the student dashboard.
 */
$portal    = strtolower(trim((string) get('portal', '')));
$portalCtx = function_exists('auth_role_context') ? auth_role_context($portal) : [
    'slug' => '', 'label' => 'Sign in', 'icon' => 'log-in',
    'title' => 'Welcome back', 'blurb' => 'Sign in to continue to your EduSkill India portal.',
];

// Already signed in? Send them to the dashboard their role actually owns.
if (is_member_logged_in()) {
    $role = function_exists('auth_current_role') ? auth_current_role() : '';
    redirect($role !== '' && function_exists('auth_dashboard_for') ? auth_dashboard_for($role) : '/account');
}

$error           = '';
$unverifiedEmail = '';
$emailValue      = '';

if (is_post()) {
    require_csrf();

    $emailValue = clean(post('email'));
    $r = member_login($emailValue, (string) post('password'));

    if (!empty($r['success'])) {
        if (post('remember') && !empty($r['member_id'])) {
            remember_issue('members', (int) $r['member_id'], 'pwf_member_remember');
        }
        set_flash('success', $r['message']);
        // Return the member to the page that sent them to login (same-site
        // relative paths only — never an absolute/protocol-relative URL).
        $intended = (string) ($_SESSION['_member_intended'] ?? '');
        unset($_SESSION['_member_intended']);
        if ($intended !== '' && $intended[0] === '/' && !str_starts_with($intended, '//')) {
            redirect($intended);
        }
        redirect('/account');
    }

    // Unverified accounts get a direct path to OTP verification.
    if (!empty($r['unverified'])) {
        $unverifiedEmail = $r['email'] ?? $emailValue;
    }
    $error = $r['message'];
}

seo_set([
    'title'    => 'Login',
    'page_key' => 'login',
    'robots'   => 'noindex,nofollow',
]);

$siteName = get_setting('site_name', SITE_NAME);
$tagline  = get_setting('site_tagline', SITE_TAGLINE);

// Presentation-only: highlight the credential fields when a login attempt failed
// (but not when the account merely needs OTP verification).
$fieldErr = ($error !== '' && $unverifiedEmail === '');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<?= csrf_meta() ?>
<title><?= e($siteName) ?> — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
    <?php /* Same link order as includes/header.php: the design system loads LAST
             of the stylesheets so its radius / elevation / motion scales and its
             components win over the accumulated per-component values above.
             The Theme Engine block follows it because it only emits custom
             properties, which the design system then reads. */ ?>
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <?php echo function_exists('theme_style_tag') ? theme_style_tag('site') : ''; ?>
<style>
/* =========================================================================
   Member Login — page-scoped layer ON TOP of design-system.css.
   The local :root palette that used to live here is gone: colour, radius,
   elevation and motion all come from the Theme Engine + the design system, so
   this screen now follows the same tokens as the rest of the public site (and
   an admin palette change reaches it, which it previously could not).
   ========================================================================= */

/* ------------------------------------------------------------------ SHELL */
.auth-shell{
    position: relative; overflow: hidden; isolation: isolate;
    padding: clamp(1.25rem, 4vw, 3rem) clamp(.85rem, 3vw, 1.5rem);
    background:
        radial-gradient(900px 640px at 6% -8%,
            color-mix(in srgb, var(--primary, #0B4E3D) 11%, transparent), transparent 58%),
        radial-gradient(780px 560px at 104% 106%,
            color-mix(in srgb, var(--yellow, #FFE987) 55%, transparent), transparent 58%),
        var(--ivory, #FEFEF1);
}
/* Two brand washes in place of four blurred orbs in mint / teal / mustard —
   none of which were in the palette — and no infinite float animation. */
.auth-aurora{ position: absolute; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
.auth-aurora .orb{ position: absolute; border-radius: 50%; filter: blur(70px); opacity: .5; }
.auth-aurora .orb-1{ width: 420px; height: 420px; top: -150px; left: -130px;
    background: color-mix(in srgb, var(--primary, #0B4E3D) 30%, transparent); }
.auth-aurora .orb-2{ width: 360px; height: 360px; bottom: -170px; right: -120px;
    background: color-mix(in srgb, var(--secondary, #174D3D) 26%, transparent); }
.auth-aurora .orb-3{ width: 300px; height: 300px; top: 44%; right: 6%;
    background: color-mix(in srgb, var(--yellow, #FFE987) 72%, transparent); opacity: .6; }
.auth-main{ position: relative; z-index: 2; }

/* ---------------------------------------------------------- CARD + LOGO */
.auth-box{ max-width: 468px; }
.auth-logo{ gap: .7rem; margin-bottom: 1.5rem; }
.auth-logo img{ border-radius: var(--r-md); box-shadow: var(--elev-2); }
.auth-logo span{ line-height: 1.15; color: var(--text, #151818); }
.auth-logo small{ color: var(--muted, #4B6754); letter-spacing: .12em; }

/* .card is the design-system surface: hairline border, --r-lg family, no
   glass. Only the radius step and the page's own padding are set here. */
.auth-box .card{
    position: relative; overflow: hidden;
    padding: clamp(1.5rem, 3.4vw, 2.5rem);
    border-radius: var(--r-xl);
    box-shadow: var(--elev-4);
}
/* A flat yellow rule crowning the card — the same device the design system
   uses to anchor the featured photograph, instead of a 3-stop gradient. */
.auth-box .card::before{
    content: ""; position: absolute; inset: 0 0 auto; height: 5px;
    background: var(--yellow, #FFE987);
}
/* The card is a static surface; the lift belongs to clickable cards. */
.auth-box .card:hover{ transform: none; box-shadow: var(--elev-4); border-color: var(--border, #C1CCB3); }

/* -------------------------------------------------------------- HEADINGS */
.auth-box .section-head{ margin-bottom: 1.4rem; }
.auth-box .section-title{
    font-size: clamp(1.6rem, 5.6vw, 2.05rem); line-height: 1.14; letter-spacing: -.02em;
}
.auth-box .section-subtitle{ font-size: .96rem; line-height: 1.6; }
.auth-box .eyebrow svg.lucide{ width: 14px; height: 14px; }

/* --------------------------------------------------- FIELDS + VALIDATION */
.field-float{ margin-bottom: 1.1rem; }
/* 16px minimum: anything smaller makes iOS Safari zoom the page on focus. */
.field-float input{
    font-size: 1rem; border-radius: var(--r-sm);
    transition: border-color var(--dur, .3s) var(--ease-out, ease),
                box-shadow var(--dur, .3s) var(--ease-out, ease);
}
.field-float input:hover:not(:focus){
    border-color: color-mix(in srgb, var(--primary, #0B4E3D) 45%, transparent);
}
.field-float input:focus{
    border-color: var(--primary, #0B4E3D);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #0B4E3D) 16%, transparent);
}
/* General-sibling float so the label still lifts on the password field,
   where the show/hide button sits between the input and its label. */
.field-float input:focus ~ label,
.field-float input:not(:placeholder-shown) ~ label{
    top: .3rem; left: .85rem; font-size: .72rem; font-weight: 700;
    color: var(--primary, #0B4E3D); padding: 0 .3rem;
}
.field-float input.is-invalid{ border-color: var(--danger, #DC2626); }
.field-float input.is-invalid:focus{
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger, #DC2626) 18%, transparent);
}
.field-float.shake{ animation: fieldShake .38s cubic-bezier(.36,.07,.19,.97) both; }
@keyframes fieldShake{
    10%,90%{ transform: translateX(-1px); }
    20%,80%{ transform: translateX(2px); }
    30%,50%,70%{ transform: translateX(-4px); }
    40%,60%{ transform: translateX(4px); }
}

/* Password show/hide: a 20px mark inside a 44px touch target (it measured
   34x34 before). The input reserves room so the value never runs under it. */
#password{ padding-right: 3.25rem; }
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
.toggle-pass .tp-off{ display: none; }
.toggle-pass.is-on .tp-on{ display: none; }
.toggle-pass.is-on .tp-off{ display: inline-flex; }

/* Remember-me / forgot row — the checkbox row is a 44px target on touch. */
.auth-meta{ margin-bottom: 1.25rem; }
.auth-meta .checkbox{
    font-size: .92rem; color: var(--text-soft, #372C22);
    align-items: center; min-height: 44px; cursor: pointer;
}
.auth-meta .checkbox input{
    width: 1.15rem; height: 1.15rem; margin-top: 0; accent-color: var(--primary, #0B4E3D);
}
.auth-meta a{ font-weight: 700; color: var(--primary, #0B4E3D); }

/* ----------------------------------------------------- SEPARATOR + SOCIAL */
.auth-sep{ margin: 1.35rem 0; font-weight: 600; color: var(--muted, #4B6754); }
.social-auth{ gap: .7rem; }
.btn-social{
    gap: .55rem; padding: .8rem 1rem; min-height: 48px;
    border-radius: var(--r-pill); font-weight: 700;
    background: var(--surface, #fff); color: var(--text, #151818);
    border: 1.5px solid var(--border, #C1CCB3);
    transition: transform var(--dur-fast, .18s) var(--ease-out, ease),
                border-color var(--dur, .3s) var(--ease-out, ease),
                box-shadow var(--dur, .3s) var(--ease-out, ease),
                color var(--dur, .3s) var(--ease-out, ease);
}
.btn-social .brand-svg{ width: 19px; height: 19px; flex-shrink: 0; }
/* The hover label used to turn #1877F2 / #1a73e8 — 4.23:1 and 4.51:1 on the
   white button, i.e. below AA for a .9rem label. Brand green is 9.67:1; the
   provider colour stays where it belongs, on the glyph and the border. */
.btn-social:hover{
    transform: translateY(-2px); color: var(--primary, #0B4E3D); box-shadow: var(--elev-2);
}
.btn-social.google:hover{ border-color: #4285F4; }
.btn-social.facebook:hover{ border-color: #1877F2; }

/* ---------------------------------------------------------- FOOTER LINKS */
.auth-newhere{ font-size: .96rem; color: var(--text-soft, #372C22); }
.auth-newhere a{ font-weight: 800; }
.auth-back{
    display: inline-flex; align-items: center; gap: .4rem; min-height: 44px;
    font-size: .9rem; color: var(--muted, #4B6754); font-weight: 600;
}
.auth-back:hover{ color: var(--primary, #0B4E3D); }
.auth-back svg.lucide{ width: 15px; height: 15px; }

.alert{ border-radius: var(--r-sm); }
.alert a{ display: inline-flex; align-items: center; gap: .3rem; font-weight: 700; }
.alert svg.lucide{ width: 15px; height: 15px; }

/* ------------------------------------------------------------ RESPONSIVE */
@media (max-width: 820px){
    /* Ambient washes off on a phone: three 70px blurs repainting on a phone GPU
       for an effect the card covers anyway (design system §13). */
    .auth-aurora{ display: none; }
}
@media (max-width: 540px){
    .auth-box{ max-width: 100%; }
    .social-auth{ grid-template-columns: 1fr; }
    .auth-logo{ font-size: 1.05rem; }
}
@media (max-width: 380px){
    /* 320px: 2.25rem of card padding either side left 248px for the form. */
    .auth-shell{ padding: 1rem .7rem; }
    .auth-box .card{ padding: 1.3rem 1.05rem; }
    .auth-logo img{ width: 44px; height: 44px; }
}

/* ------------------------------------------------------- REDUCED MOTION */
@media (prefers-reduced-motion: reduce){
    .field-float.shake{ animation: none; }
    .btn-social{ transition: none; }
    .btn-social:hover{ transform: none; }
}
</style>
</head>
<body>
<div class="auth-shell">
    <div class="auth-aurora" aria-hidden="true">
        <span class="orb orb-1"></span>
        <span class="orb orb-2"></span>
        <span class="orb orb-3"></span>
    </div>

    <?php
    /* ---------------------------- ROLE SIDEBAR ----------------------------
       forgot-password.php and reset-password.php already shipped .auth-aside;
       login.php, the page every visitor actually starts from, did not — so the
       two-column treatment appeared only on the recovery pages and sign-in
       looked like a different product.
       The copy is role-aware: /login/{role} sets $portalCtx (auth_role_context),
       so a volunteer sees what a volunteer portal offers rather than generic
       marketing. Falls back to the member wording on a bare /login.
       premium.css owns .auth-shell:has(.auth-aside), so simply rendering this
       switches the page to the two-column layout with no extra CSS. */
    $asidePoints = [
        'student'   => ['Your courses, lessons and progress', 'Assignments and exam results', 'Certificates you can verify online'],
        'volunteer' => ['Opportunities matched to your skills', 'Log the hours you contribute', 'Recognition and certificates'],
        'donor'     => ['Every donation in one place', '80G receipts ready to download', 'See the work your giving funded'],
        'member'    => ['Your digital membership card', 'Renewals and member benefits', 'Receipts and giving history'],
        'staff'     => ['Your assigned modules and queues', 'Only what your role may access', 'Every action is logged'],
        'school'    => ['Students, batches and attendance', 'Fees and receipts', 'Results and marksheets'],
        'teacher'   => ['Your courses and live sessions', 'Assignments and exam marking', 'Student progress at a glance'],
    ];
    $pSlug   = (string) ($portalCtx['slug'] ?? '');
    $points  = $asidePoints[$pSlug] ?? $asidePoints['member'];
    $pTitle  = (string) ($portalCtx['title'] ?? 'Welcome back');
    $pBlurb  = (string) ($portalCtx['blurb'] ?? 'Sign in to continue to your EduSkill India portal.');
    ?>
    <aside class="auth-aside">
        <span class="blob b1" style="top:-70px;left:-60px;"></span>
        <span class="blob b2" style="bottom:-80px;right:-50px;"></span>
        <div class="floating-shapes" aria-hidden="true">
            <span class="shape" style="top:18%;left:14%;"></span>
            <span class="shape" style="top:66%;left:78%;"></span>
            <span class="shape" style="top:46%;left:54%;"></span>
        </div>

        <div class="aside-inner">
            <a class="aside-brand" href="<?= e(url('')) ?>">
                <span class="icon-badge"><?= lucide((string) ($portalCtx['icon'] ?? 'log-in')) ?></span>
                <strong><?= e($siteName) ?></strong>
            </a>

            <h2><?= e($pTitle) ?></h2>
            <p class="aside-tagline"><?= e(get_setting('site_tagline', 'Empowering Communities • Spreading Hope • Creating Change')) ?></p>
            <p><?= e($pBlurb) ?></p>

            <ul class="aside-points">
                <?php foreach ($points as $pt): ?>
                    <li><?= lucide('check') ?><span><?= e($pt) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <!-- ============================== LOGIN FORM ============================== -->
    <main class="auth-main">
        <div class="auth-box">
            <a href="<?= e(url('')) ?>" class="auth-logo reveal">
                <img src="<?= e(brand_logo_url()) ?>" alt="<?= e($siteName) ?>" width="52" height="52">
                <span><?= e($siteName) ?></span>
            </a>
            <div class="card auth-card reveal delay-1">
                <?php /* The portal badge is the only thing that changes per role —
                         one platform, one design, contextual wording. */ ?>
                <div class="auth-head">
                    <span class="auth-badge">
                        <?= lucide($portalCtx['icon'] ?: 'log-in') ?>
                        <?= e($portalCtx['label']) ?>
                    </span>
                    <h1 class="auth-title"><?= e($portalCtx['title']) ?></h1>
                    <p class="auth-blurb"><?= e($portalCtx['blurb']) ?></p>
                </div>

                <?php // Pending flashes (e.g. logout / OTP-verified redirects land here). ?>
                <?php foreach (get_flashes() as $fType => $fMsgs):
                    $fClass = in_array($fType, ['success', 'error', 'warning', 'info'], true) ? $fType : 'info';
                    foreach ($fMsgs as $fMsg): ?>
                    <div class="alert alert-<?= e($fClass) ?>"><?= lucide($fClass === 'success' ? 'circle-check' : ($fClass === 'error' ? 'triangle-alert' : 'info')) ?> <?= e($fMsg) ?></div>
                <?php endforeach; endforeach; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= lucide('triangle-alert') ?> <?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($unverifiedEmail): ?>
                    <div class="alert alert-warning">
                        Your email isn't verified yet.
                        <a class="link-underline" href="<?= e(url('verify-otp?email=' . urlencode($unverifiedEmail))) ?>">Verify now <?= lucide('arrow-right') ?></a>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate id="loginForm">
                    <?= csrf_field() ?>

                    <div class="field-float">
                        <input type="email" id="email" name="email" placeholder=" " required autocomplete="email"
                               value="<?= e($emailValue) ?>" autofocus<?= $fieldErr ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
                        <label for="email">Email address</label>
                    </div>

                    <div class="field-float">
                        <input type="password" id="password" name="password" placeholder=" " required autocomplete="current-password"<?= $fieldErr ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
                        <button type="button" class="toggle-pass" id="togglePass" aria-label="Show password" aria-pressed="false">
                            <span class="tp-on"><?= lucide('eye') ?></span>
                            <span class="tp-off"><?= lucide('eye-off') ?></span>
                        </button>
                        <label for="password">Password</label>
                    </div>

                    <div class="flex justify-between items-center auth-meta flex-wrap gap-2">
                        <label class="checkbox"><input type="checkbox" name="remember" value="1"> Remember me</label>
                        <a class="link-underline" href="<?= e(url('forgot-password')) ?>">Forgot password?</a>
                    </div>

                    <button class="btn btn-secondary btn-block btn-lg" type="submit"><?= lucide('log-in') ?> Sign In</button>
                </form>

                <?php
                /* Social sign-in. Each provider is shown only when an admin has
                   switched it on AND saved credentials in
                   Settings -> Social Login, so the block disappears entirely
                   rather than offering a button that cannot work. */
                $oaGoogle   = oauth_enabled('google');
                $oaFacebook = oauth_enabled('facebook');
                ?>
                <?php if ($oaGoogle || $oaFacebook): ?>
                <div class="auth-sep">or continue with</div>

                <div class="social-auth"<?= ($oaGoogle && $oaFacebook) ? '' : ' style="grid-template-columns:1fr;"' ?>>
                    <?php if ($oaGoogle): ?>
                    <a class="btn-social google" href="<?= e(url('oauth-start?provider=google')) ?>" rel="nofollow">
                        <svg class="brand-svg" width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/><path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/><path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/><path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303c-.792 2.237-2.231 4.166-4.087 5.571l6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/></svg>
                        Google
                    </a>
                    <?php endif; ?>
                    <?php if ($oaFacebook): ?>
                    <a class="btn-social facebook" href="<?= e(url('oauth-start?provider=facebook')) ?>" rel="nofollow">
                        <svg class="brand-svg" width="18" height="18" viewBox="0 0 24 24" fill="#1877F2" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        Facebook
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <p class="text-center mt-3 auth-newhere" style="margin-bottom:0;">
                    New here? <a class="link-underline" href="<?= e(url('signup')) ?>">Create an account</a>
                </p>

                <?php
                /* Portal switcher. Somebody who lands on the wrong door should be
                   able to move, not guess a URL. Sign-in still works from ANY of
                   these — the destination comes from the account's own role — so
                   this is wayfinding, not a gate. */
                $switchRoles = ['member', 'donor', 'volunteer', 'student', 'teacher', 'school'];
                $allRoles    = function_exists('auth_roles') ? auth_roles() : [];
                ?>
                <div class="auth-switch">
                    <p>Other portals</p>
                    <div class="auth-switch-list">
                        <?php foreach ($switchRoles as $r):
                            $cfg = $allRoles[$r] ?? null;
                            if (!$cfg) { continue; }
                            $isCurrent = ($portalCtx['slug'] ?? '') === $r; ?>
                            <a class="<?= $isCurrent ? 'is-current' : '' ?>" href="<?= e(url('login/' . $r)) ?>"
                               <?= $isCurrent ? 'aria-current="page"' : '' ?>>
                                <?= lucide($cfg['icon']) ?> <?= e(ucfirst($r)) ?>
                            </a>
                        <?php endforeach; ?>
                        <a href="<?= e(url('login/admin')) ?>"><?= lucide('shield') ?> Admin</a>
                    </div>
                </div>
            </div>

            <p class="text-center mt-3">
                <a href="<?= e(url('')) ?>" class="auth-back"><?= lucide('arrow-left') ?> Back to homepage</a>
            </p>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<script src="<?= e(asset('js/premium.js')) ?>"></script>
<!-- Lucide icons (this standalone page does not load the shared footer) -->
<script src="https://cdn.jsdelivr.net/npm/lucide@0.469.0/dist/umd/lucide.min.js"></script>
<script>
(function () {
    function drawIcons() { try { window.lucide && window.lucide.createIcons(); } catch (e) {} }
    drawIcons();
    document.addEventListener('DOMContentLoaded', drawIcons);

    document.addEventListener('DOMContentLoaded', function () {
        /* Password visibility toggle (Lucide eye / eye-off, no emoji). */
        var toggle = document.getElementById('togglePass');
        var pass   = document.getElementById('password');
        if (toggle && pass) {
            toggle.addEventListener('click', function () {
                var show = pass.type === 'password';
                pass.type = show ? 'text' : 'password';
                toggle.classList.toggle('is-on', show);
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                pass.focus();
            });
        }

        /* Lightweight validation states — clear the invalid ring as the user types. */
        var form = document.getElementById('loginForm');
        if (!form) return;
        form.querySelectorAll('input[required]').forEach(function (input) {
            input.addEventListener('input', function () {
                input.classList.remove('is-invalid');
                input.removeAttribute('aria-invalid');
            });
            input.addEventListener('invalid', function () {
                input.classList.add('is-invalid');
                var wrap = input.closest('.field-float');
                if (wrap) { wrap.classList.remove('shake'); void wrap.offsetWidth; wrap.classList.add('shake'); }
            });
        });
    });
})();
</script>
</body>
</html>
