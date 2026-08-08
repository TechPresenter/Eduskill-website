<?php
/**
 * =============================================================================
 *  Member Login — standalone premium auth shell (DEV_CONTRACT_V2 §4).
 *  Renders its own <head> (tailwind.css + premium.css); no public header/footer.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';   // social sign-in availability + URLs

// Already signed in? Straight to the account dashboard.
if (is_member_logged_in()) {
    redirect('/account');
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
    <?php echo function_exists('theme_style_tag') ? theme_style_tag('site') : ''; ?>
<style>
/* =========================================================================
   Member Login — page-specific premium polish (forest-green brand skin).
   Standalone page, so brand tokens are re-mapped locally to the NGO's
   forest-green / teal / gold palette without touching the shared CSS.
   ========================================================================= */
:root{
    --brand:        #063566;
    --brand-600:    #063566;
    --brand-700:    #042a52;
    --brand-2:      #084881;
    --accent:       #E67B1D;
    --accent-2:     #E67B1D;
    --gold:         #E67B1D;
    --teal:         #084881;
    --grad-brand:   linear-gradient(135deg, #063566 0%, #084881 100%);
    --ring:         rgba(8,72,129,.28);
    --shadow-brand: 0 14px 34px rgba(8,72,129,.30);
}

/* ------------------------------------------------------ AURORA BACKGROUND */
.auth-shell{
    position: relative;
    overflow: hidden;
    isolation: isolate;
    padding: clamp(1.5rem, 4vw, 3rem) 1.25rem;
    background:
        radial-gradient(1100px 720px at 6% -8%,  rgba(8,72,129,.18), transparent 55%),
        radial-gradient(1000px 720px at 104% 108%, rgba(230,123,29,.16), transparent 55%),
        radial-gradient(820px 620px  at 50% 46%, rgba(6,53,102,.07),  transparent 60%),
        linear-gradient(160deg, #f1faf5 0%, #eef7f3 46%, #f6fbf1 100%);
}
.auth-aurora{ position: absolute; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
.auth-aurora .orb{
    position: absolute; border-radius: 50%; filter: blur(64px);
    opacity: .55; mix-blend-mode: multiply;
    animation: orbFloat 20s ease-in-out infinite;
}
.auth-aurora .orb-1{ width: 460px; height: 460px; top: -140px; left: -120px;
    background: radial-gradient(circle at 30% 30%, #22c197, #063566); }
.auth-aurora .orb-2{ width: 400px; height: 400px; bottom: -160px; right: -110px;
    background: radial-gradient(circle at 30% 30%, #2dd4bf, #084881); animation-delay: -6s; }
.auth-aurora .orb-3{ width: 320px; height: 320px; top: 42%; right: 8%;
    background: radial-gradient(circle at 30% 30%, #f4cd6a, #E67B1D); opacity: .38; animation-delay: -11s; }
.auth-aurora .orb-4{ width: 260px; height: 260px; bottom: 6%; left: 6%;
    background: radial-gradient(circle at 30% 30%, #5eead4, #084881); opacity: .34; animation-delay: -3s; }
/* faint dotted texture layer */
.auth-shell::after{
    content: ""; position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background-image: radial-gradient(rgba(6,53,102,.10) 1px, transparent 1.4px);
    background-size: 26px 26px;
    -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 40%, #000 0%, transparent 78%);
            mask-image: radial-gradient(ellipse 70% 60% at 50% 40%, #000 0%, transparent 78%);
    opacity: .5;
}
@keyframes orbFloat{
    0%,100%{ transform: translate(0,0) scale(1); }
    33%    { transform: translate(30px,-26px) scale(1.08); }
    66%    { transform: translate(-22px,24px) scale(.94); }
}
.auth-main{ position: relative; z-index: 2; }

/* ------------------------------------------------------ CARD + LOGO */
.auth-box{ max-width: 468px; }
.auth-logo{ gap: .7rem; margin-bottom: 1.6rem; }
.auth-logo img{ border-radius: 14px; box-shadow: 0 8px 20px rgba(6,78,59,.18); }
.auth-logo span{ line-height: 1.15; }
.auth-logo small{ color: var(--teal); letter-spacing: .12em; }

.auth-box .glass-card{
    position: relative;
    padding: clamp(1.6rem, 3.4vw, 2.6rem);
    border-radius: 26px;
    background: rgba(255,255,255,.74);
    backdrop-filter: blur(24px) saturate(165%);
    -webkit-backdrop-filter: blur(24px) saturate(165%);
    border: 1px solid rgba(255,255,255,.75);
    box-shadow:
        0 34px 80px -34px rgba(6,78,59,.5),
        0 2px 0 rgba(255,255,255,.65) inset,
        0 -1px 0 rgba(6,53,102,.05) inset;
    overflow: hidden;
}
/* Gold-into-green hairline crowning the card */
.auth-box .glass-card::before{
    content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #063566, #084881 55%, #E67B1D);
}

/* ------------------------------------------------------ HEADINGS */
.auth-eyebrow{
    display: inline-flex; align-items: center; gap: .45rem;
    justify-content: center; margin: 0 auto .55rem;
    padding: .32rem .85rem; border-radius: 999px;
    font-size: .68rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase;
    color: var(--brand-700);
    background: rgba(8,72,129,.10);
    border: 1px solid rgba(8,72,129,.22);
}
.auth-eyebrow svg.lucide{ width: 14px; height: 14px; color: var(--teal); }
.auth-title{
    font-size: clamp(1.8rem, 4.5vw, 2.15rem); font-weight: 800; line-height: 1.1;
    margin: 0; letter-spacing: -.02em;
    background: linear-gradient(120deg, #042a52 0%, #084881 90%);
    -webkit-background-clip: text; background-clip: text; color: transparent;
}
.auth-sub{ color: var(--muted); margin: .5rem 0 0; font-size: .96rem; }

/* ------------------------------------------------------ FIELDS + VALIDATION */
/* General-sibling float so the label still lifts on the password field,
   where the show/hide button sits between the input and its label. */
.field-float input:focus ~ label,
.field-float input:not(:placeholder-shown) ~ label{
    top: .3rem; left: .85rem; font-size: .72rem; font-weight: 700;
    color: var(--brand-600); padding: 0 .3rem;
}
.field-float{ margin-bottom: 1.15rem; }
.field-float input{ transition: border-color .2s, box-shadow .2s, background .2s; }
.field-float input:hover:not(:focus){ border-color: rgba(8,72,129,.5); }
.field-float input.is-invalid{ border-color: var(--danger); }
.field-float input.is-invalid:focus{ box-shadow: 0 0 0 4px rgba(220,38,38,.16); }
.field-float.shake{ animation: fieldShake .38s cubic-bezier(.36,.07,.19,.97) both; }
@keyframes fieldShake{
    10%,90%{ transform: translateX(-1px); }
    20%,80%{ transform: translateX(2px); }
    30%,50%,70%{ transform: translateX(-4px); }
    40%,60%{ transform: translateX(4px); }
}

/* Password show/hide toggle (Lucide eye / eye-off) */
.toggle-pass{
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; right: .55rem; top: .7rem; border-radius: 9px;
    color: var(--muted); transition: color .2s, background .2s;
}
.toggle-pass:hover{ color: var(--brand-600); background: rgba(8,72,129,.10); }
.toggle-pass svg.lucide{ width: 19px; height: 19px; }
.toggle-pass .tp-off{ display: none; }
.toggle-pass.is-on .tp-on{ display: none; }
.toggle-pass.is-on .tp-off{ display: inline-flex; }

/* Remember-me / forgot row */
.auth-meta{ margin-bottom: 1.3rem; }
.auth-meta .checkbox{ font-size: .92rem; color: var(--text-soft); }
.auth-meta a{ font-weight: 700; color: var(--brand-600); }
.auth-meta a:hover{ color: var(--teal); }

/* ------------------------------------------------------ SUBMIT BUTTON */
.auth-shell .btn-3d{
    background: linear-gradient(135deg, #063566 0%, #084881 100%);
    border-radius: 14px; font-weight: 700; letter-spacing: .015em;
    box-shadow: 0 6px 0 #0f4c43, 0 16px 30px rgba(8,72,129,.38);
}
.auth-shell .btn-3d::after{
    content: ""; position: absolute; inset: 0; border-radius: inherit;
    background: linear-gradient(115deg, transparent 30%, rgba(255,255,255,.35) 50%, transparent 70%);
    transform: translateX(-120%); transition: transform .6s ease; pointer-events: none;
}
.auth-shell .btn-3d:hover{ box-shadow: 0 9px 0 #0f4c43, 0 22px 42px rgba(8,72,129,.5); }
.auth-shell .btn-3d:hover::after{ transform: translateX(120%); }
.auth-shell .btn-3d:active{ box-shadow: 0 2px 0 #0f4c43, 0 8px 16px rgba(8,72,129,.34); }
.auth-shell .btn-3d svg.lucide{ margin-left: .1rem; }

/* ------------------------------------------------------ SEPARATOR + SOCIAL */
.auth-sep{ margin: 1.4rem 0; font-weight: 600; letter-spacing: .02em; }
.social-auth{ gap: .8rem; }
.btn-social{
    gap: .55rem; padding: .8rem 1rem; border-radius: 13px; font-weight: 700;
    background: var(--surface); color: var(--text-soft);
    transition: transform .2s, border-color .2s, box-shadow .2s, color .2s;
}
.btn-social .brand-svg{ width: 19px; height: 19px; flex-shrink: 0; }
.btn-social:hover{ transform: translateY(-2px); }
.btn-social.google:hover{ border-color: #4285F4; color: #1a73e8; box-shadow: 0 10px 20px rgba(66,133,244,.16); }
.btn-social.facebook:hover{ border-color: #1877F2; color: #1877F2; box-shadow: 0 10px 20px rgba(24,119,242,.16); }

/* Footer links */
.auth-newhere{ font-size: .96rem; }
.auth-newhere a{ font-weight: 800; color: var(--brand-600); }
.auth-newhere a:hover{ color: var(--teal); }
.auth-back{
    display: inline-flex; align-items: center; gap: .35rem;
    font-size: .9rem; color: var(--muted); font-weight: 600;
}
.auth-back:hover{ color: var(--brand-600); }
.auth-back svg.lucide{ width: 15px; height: 15px; }

.alert{ border-radius: 13px; }
.alert a{ display: inline-flex; align-items: center; gap: .3rem; }
.alert svg.lucide{ width: 15px; height: 15px; }

/* ------------------------------------------------------ RESPONSIVE */
@media (max-width: 540px){
    .auth-box{ max-width: 100%; }
    .social-auth{ grid-template-columns: 1fr; }
    .auth-logo{ font-size: 1.05rem; }
}

/* ------------------------------------------------------ DARK MODE (if enabled) */
:root[data-theme="dark"] .auth-shell{
    background:
        radial-gradient(1100px 720px at 6% -8%,  rgba(8,72,129,.22), transparent 55%),
        radial-gradient(1000px 720px at 104% 108%, rgba(230,123,29,.14), transparent 55%),
        radial-gradient(820px 620px  at 50% 46%, rgba(6,53,102,.16),  transparent 60%),
        linear-gradient(160deg, #07130f 0%, #0a1a17 55%, #0b1510 100%);
}
:root[data-theme="dark"] .auth-aurora .orb{ mix-blend-mode: screen; opacity: .4; }
:root[data-theme="dark"] .auth-box .glass-card{
    background: rgba(16,28,24,.62); border-color: rgba(255,255,255,.08);
}
:root[data-theme="dark"] .auth-title{
    background: linear-gradient(120deg, #7BC94F 0%, #2dd4bf 90%);
    -webkit-background-clip: text; background-clip: text;
}

/* ------------------------------------------------------ REDUCED MOTION */
@media (prefers-reduced-motion: reduce){
    .auth-aurora .orb{ animation: none; }
    .auth-shell .btn-3d::after{ display: none; }
}
</style>
</head>
<body>
<div class="auth-shell">
    <div class="auth-aurora" aria-hidden="true">
        <span class="orb orb-1"></span>
        <span class="orb orb-2"></span>
        <span class="orb orb-3"></span>
        <span class="orb orb-4"></span>
    </div>

    <!-- ============================== LOGIN FORM ============================== -->
    <main class="auth-main">
        <div class="auth-box">
            <a href="<?= e(url('')) ?>" class="auth-logo reveal">
                <img src="<?= e(asset('images/logo-128.webp')) ?>" alt="<?= e($siteName) ?>" width="52" height="52">
                <span><?= e($siteName) ?><small>Member Portal</small></span>
            </a>
            <div class="glass-card reveal delay-1">
                <div class="text-center mb-3">
                    <span class="auth-eyebrow"><?= lucide('shield') ?> Secure Sign In</span>
                    <h1 class="auth-title">Welcome back</h1>
                    <p class="auth-sub">Enter your details to access your account.</p>
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
                        <a href="<?= e(url('verify-otp?email=' . urlencode($unverifiedEmail))) ?>" style="font-weight:700;">Verify now <?= lucide('arrow-right') ?></a>
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
                        <a href="<?= e(url('forgot-password')) ?>">Forgot password?</a>
                    </div>

                    <button class="btn btn-3d btn-block btn-lg" type="submit"><?= lucide('log-in') ?> Sign In</button>
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
                    New here? <a href="<?= e(url('signup')) ?>">Create an account</a>
                </p>
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
