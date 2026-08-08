<?php
/**
 * =============================================================================
 *  signup.php — Member registration (STANDALONE auth shell, no header/footer).
 * =============================================================================
 *  Flow:
 *    - Already signed in?            → redirect to /account
 *    - POST: require_csrf(), validate terms + matching passwords, then
 *      member_register($_POST). On success show a "verify your email" screen
 *      with a link to /verify-otp?email=…; on failure show inline error(s).
 * =============================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (is_member_logged_in()) {
    redirect('/account');
}

$error       = '';
$fieldErrors = [];
$success     = false;
$doneEmail   = '';

// Repopulate on validation errors (never echo without e()).
$vName  = clean(post('name'));
$vEmail = strtolower(clean(post('email')));
$vPhone = clean(post('phone'));

if (is_post()) {
    require_csrf();

    $password = (string) post('password');
    $confirm  = (string) post('password_confirm');

    if (!post('terms')) {
        $error = 'Please accept the Terms & Conditions and Privacy Policy to continue.';
    } elseif ($password === '' || $password !== $confirm) {
        $error = 'The two passwords do not match. Please re-enter them.';
    } else {
        $r = member_register($_POST);
        if (!empty($r['success'])) {
            $success   = true;
            $doneEmail = $vEmail;
        } else {
            $error       = $r['message'] ?? 'We could not create your account. Please try again.';
            $fieldErrors = $r['errors'] ?? [];
        }
    }
}

seo_set([
    'title'   => 'Sign Up',
    'page_key' => 'signup',
    'robots'  => 'noindex,nofollow',
]);

$siteName  = get_setting('site_name', SITE_NAME);
$tagline   = get_setting('site_tagline', SITE_TAGLINE);
$logo      = get_setting('site_logo');
$verifyUrl = url('verify-otp?email=' . urlencode($doneEmail));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<?= csrf_meta() ?>
<title><?= e($siteName) ?> — Sign Up</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
    <?php echo function_exists('theme_style_tag') ? theme_style_tag('site') : ''; ?>
<style>
/* =======================================================================
   SIGNUP — page-specific premium polish (scoped, no shared CSS touched)
   ======================================================================= */
.su-shell { position: relative; overflow: hidden; padding: 2.5rem 1.25rem; }

/* Animated aurora background */
.su-bg { position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
.su-bg span { position: absolute; border-radius: 50%; filter: blur(64px); opacity: .55; animation: suFloat 20s ease-in-out infinite; }
.su-bg .o1 { width: 440px; height: 440px; background: radial-gradient(circle, rgba(6,53,102,.38), transparent 70%); top: -140px; left: -110px; }
.su-bg .o2 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(8,72,129,.30), transparent 70%); bottom: -160px; right: -100px; animation-delay: -7s; }
.su-bg .o3 { width: 320px; height: 320px; background: radial-gradient(circle, rgba(230,123,29,.24), transparent 70%); top: 42%; left: 56%; animation-delay: -13s; }
@keyframes suFloat { 0%,100% { transform: translate(0,0) scale(1); } 33% { transform: translate(32px,-26px) scale(1.09); } 66% { transform: translate(-24px,18px) scale(.94); } }

/* Split card */
.su-split {
    position: relative; z-index: 1; width: 100%; max-width: 1060px;
    display: grid; grid-template-columns: 1.04fr .96fr;
    border-radius: 28px; overflow: hidden;
    box-shadow: 0 34px 90px -34px rgba(12,40,26,.55), 0 6px 24px rgba(12,40,26,.12);
    border: 1px solid rgba(255,255,255,.5);
    animation: suRise .7s cubic-bezier(.16,.84,.44,1) both;
}
:root[data-theme="dark"] .su-split { border-color: rgba(255,255,255,.08); }
@keyframes suRise { from { opacity: 0; transform: translateY(26px); } to { opacity: 1; transform: none; } }

/* -------- Brand / marketing aside -------- */
.su-aside {
    position: relative; overflow: hidden; padding: 3.1rem 2.7rem; color: #fff;
    background: linear-gradient(155deg, #0b3d24 0%, #063566 46%, #084881 128%);
    display: flex; flex-direction: column;
}
.su-aside::after {
    content: ""; position: absolute; inset: 0;
    background:
        radial-gradient(560px circle at 12% 8%, rgba(255,255,255,.14), transparent 46%),
        radial-gradient(520px circle at 92% 96%, rgba(230,123,29,.20), transparent 44%);
    pointer-events: none;
}
.su-aside > * { position: relative; z-index: 1; }
.su-brand { display: inline-flex; align-items: center; gap: .65rem; font-weight: 800; font-size: 1.12rem; color: #fff; text-decoration: none; margin-bottom: 2.2rem; }
.su-brand img { width: 46px; height: 46px; object-fit: contain; border-radius: 12px; background: rgba(255,255,255,.16); padding: 6px; }
.su-eyebrow { display: inline-flex; align-items: center; gap: .45rem; font-size: .72rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #f4d47a; margin-bottom: .7rem; }
.su-eyebrow svg.lucide { width: 15px; height: 15px; }
.su-aside h1 { font-size: 2.05rem; line-height: 1.14; margin: 0 0 .8rem; color: #fff; letter-spacing: -.01em; }
.su-aside .lead { color: rgba(255,255,255,.85); font-size: 1.02rem; line-height: 1.6; margin: 0; }
.su-points { list-style: none; margin: 1.8rem 0 0; padding: 0; display: grid; gap: 1rem; }
.su-points li { display: flex; align-items: flex-start; gap: .8rem; color: rgba(255,255,255,.92); font-weight: 500; line-height: 1.45; }
.su-points .tick { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; background: rgba(255,255,255,.17); border: 1px solid rgba(255,255,255,.14); color: #fff; flex-shrink: 0; }
.su-points .tick svg.lucide { width: 16px; height: 16px; }
.su-stats { display: flex; gap: 2.4rem; margin-top: auto; padding-top: 1.9rem; }
.su-stats::before { content: ""; display: block; }
.su-stats { border-top: 1px solid rgba(255,255,255,.18); }
.su-stats .v { font-size: 1.8rem; font-weight: 800; line-height: 1; }
.su-stats small { display: block; margin-top: .3rem; color: rgba(255,255,255,.82); font-size: .8rem; }

/* -------- Form column -------- */
.su-form-col { background: var(--surface); padding: 2.9rem 2.6rem; display: flex; flex-direction: column; justify-content: center; }
.su-form-col .auth-box { max-width: none; width: 100%; }
.su-form-col .auth-logo { display: none; margin-bottom: 1.3rem; }
/* Flatten the inner glass-card — the whole column is already a surface */
.su-form-col .glass-card { background: transparent; border: 0; box-shadow: none; padding: 0; backdrop-filter: none; }

.su-head { margin-bottom: 1.5rem; }
.su-head .eyebrow { justify-content: flex-start; }
.su-head h2 { font-size: 1.85rem; margin: .2rem 0 .35rem; letter-spacing: -.01em; }
.su-head p { margin: 0; }

/* Password visibility toggle → Lucide icon */
.toggle-pass { display: inline-flex; align-items: center; justify-content: center; }
.toggle-pass svg.lucide { width: 20px; height: 20px; }
.toggle-pass:hover { color: var(--brand-600); }

/* Password strength meta row + live label */
.pw-strength { transition: none; }
.pw-meta { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: .55rem 0 1rem; }
.pw-meta .form-hint { margin: 0; }
.pw-label { font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); white-space: nowrap; transition: color .3s; }
.pw-label.weak { color: var(--danger); }
.pw-label.medium { color: var(--warning); }
.pw-label.strong { color: var(--success); }

/* Social brand buttons */
.brand-svg { flex-shrink: 0; }
.social-auth .btn-social { border-radius: 12px; }
.social-auth .btn-social:hover { box-shadow: 0 8px 20px -8px rgba(12,40,26,.30); }

/* Terms + secure note */
.su-secure { display: flex; align-items: center; justify-content: center; gap: .4rem; margin: 1rem 0 0; font-size: .8rem; color: var(--muted); }
.su-secure svg.lucide { width: 15px; height: 15px; color: var(--brand-600); }

/* Success view badge */
.su-form-col .icon-box .ib-icon svg.lucide { width: 34px; height: 34px; }

/* Micro-interaction: inputs lift focus ring already handled by .field-float */
.field-float input:hover { border-color: var(--brand-500, var(--brand-600)); }

/* -------------------- Country / mobile field --------------------
 * country_field() brings its own control, so it needs matching to the
 * .field-float inputs around it: same width, height, radius and focus ring. */
.signup-country { margin-bottom: 1rem; }
.signup-country .cs-label {
    display: block; font-size: .85rem; font-weight: 600;
    color: var(--text, #0f172a); margin-bottom: .45rem;
}
.signup-country .cs-control {
    width: 100%; border-radius: 12px; border-width: 1.5px;
    background: var(--surface, #fff);
}
.signup-country .cs-toggle { border-radius: 12px 0 0 12px; padding-inline: .85rem; }
.signup-country .cs-phone  { padding-block: .95rem; font-size: .97rem; }
.signup-country .cs-control:focus-within {
    border-color: var(--brand-600, #084881);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand-600, #084881) 15%, transparent);
}
/* The no-JS <select> fallback must stay visually hidden once the script runs. */
.signup-country .cs-native { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }

/* -------------------- Responsive -------------------- */
@media (max-width: 980px) {
    .su-split { grid-template-columns: 1fr; max-width: 520px; }
    .su-aside { padding: 2.2rem 1.9rem; }
    .su-aside h1 { font-size: 1.65rem; }
    .su-brand { margin-bottom: 1.4rem; }
    .su-points { display: none; }
    .su-stats { margin-top: 1.4rem; padding-top: 1.4rem; }
    .su-form-col { padding: 2.3rem 1.9rem; }
}
@media (max-width: 560px) {
    .su-shell { padding: 1.4rem .9rem; }
    .su-aside { display: none; }
    .su-split { border-radius: 22px; box-shadow: 0 22px 54px -26px rgba(12,40,26,.5); }
    .su-form-col { padding: 1.9rem 1.4rem; }
    .su-form-col .auth-logo { display: flex; }
    .social-auth { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="auth-shell su-shell">
    <div class="su-bg" aria-hidden="true"><span class="o1"></span><span class="o2"></span><span class="o3"></span></div>

    <div class="su-split">

        <!-- ------------------------------------------------- Brand / marketing aside -->
        <aside class="su-aside reveal">
            <a href="<?= e(url('/')) ?>" class="su-brand">
                <img src="<?= e(asset('images/logo-128.webp')) ?>" alt="<?= e($siteName) ?>" width="46" height="46">
                <span><?= e($siteName) ?></span>
            </a>

            <span class="su-eyebrow"><?= lucide('sparkles') ?> Member Portal</span>
            <h1>Become a part of the change.</h1>
            <p class="lead"><?= e($tagline) ?></p>

            <ul class="su-points">
                <li><span class="tick"><?= lucide('shield-check') ?></span><span>Bank-grade security — your details stay encrypted at rest.</span></li>
                <li><span class="tick"><?= lucide('hand-heart') ?></span><span>Join a community of <strong>800+</strong> volunteers driving real impact.</span></li>
                <li><span class="tick"><?= lucide('trending-up') ?></span><span>Track your contributions and follow the causes you care about.</span></li>
                <li><span class="tick"><?= lucide('bell') ?></span><span>Get timely updates on programs, events and campaigns.</span></li>
            </ul>

            <div class="su-stats">
                <div>
                    <div class="v"><span data-counter="25000">0</span>+</div>
                    <small>Lives impacted</small>
                </div>
                <div>
                    <div class="v"><span data-counter="800">0</span>+</div>
                    <small>Active volunteers</small>
                </div>
            </div>
        </aside>

        <!-- ------------------------------------------------------------- Form column -->
        <main class="su-form-col">
            <div class="auth-box">
                <a href="<?= e(url('/')) ?>" class="auth-logo">
                    <img src="<?= e(asset('images/logo-128.webp')) ?>" alt="<?= e($siteName) ?>" width="52" height="52">
                    <span><?= e($siteName) ?><small>Create your account</small></span>
                </a>

            <?php if ($success): ?>
                <!-- -------------------------------------------------- Success view -->
                <div class="glass-card text-center reveal">
                    <div class="icon-box g" style="padding:0;">
                        <span class="ib-icon"><?= lucide('check') ?></span>
                    </div>
                    <h2 class="text-grad-ocean">Account created!</h2>
                    <p class="text-muted mt-1">
                        We've sent a verification link to
                        <strong><?= e($doneEmail) ?></strong>.
                        Click the link in that email (check your spam folder too) to activate your account.
                    </p>
                    <a class="btn btn-3d btn-block btn-lg mt-3" href="<?= e(url('login')) ?>">
                        <?= lucide('log-in') ?> Go to Sign In
                    </a>
                    <p class="text-center mt-3">
                        Didn't get the email?
                        <a href="<?= e($verifyUrl) ?>">Verify with a code instead</a>
                        &nbsp;·&nbsp;
                        <a href="<?= e(url('signup')) ?>">Start over</a>
                    </p>
                </div>

            <?php else: ?>
                <!-- ----------------------------------------------------- Form view -->
                <div class="glass-card reveal">
                    <div class="su-head">
                        <span class="eyebrow"><?= lucide('user-plus') ?> Join Us</span>
                        <h2>Create your account</h2>
                        <p class="text-muted">It only takes a minute — no cost, ever.</p>
                    </div>

                    <?php // Pending flashes (redirects that land on /signup). ?>
                    <?php foreach (get_flashes() as $fType => $fMsgs):
                        $fClass = in_array($fType, ['success', 'error', 'warning', 'info'], true) ? $fType : 'info';
                        foreach ($fMsgs as $fMsg): ?>
                        <div class="alert alert-<?= e($fClass) ?>"><?= lucide($fClass === 'success' ? 'circle-check' : ($fClass === 'error' ? 'triangle-alert' : 'info')) ?> <span><?= e($fMsg) ?></span></div>
                    <?php endforeach; endforeach; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= lucide('circle-alert') ?> <span><?= e($error) ?></span></div>
                    <?php endif; ?>

                    <?php if ($fieldErrors): ?>
                        <div class="alert alert-error">
                            <ul style="margin:0;padding-left:1.1rem;">
                                <?php foreach ($fieldErrors as $fe): ?>
                                    <li><?= e($fe) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="signupForm">
                        <?= csrf_field() ?>

                        <div class="field-float">
                            <input type="text" id="name" name="name" placeholder=" " maxlength="128"
                                   value="<?= e($vName) ?>" autocomplete="name" required>
                            <label for="name">Full Name</label>
                        </div>

                        <div class="field-float">
                            <input type="email" id="email" name="email" placeholder=" " maxlength="191"
                                   value="<?= e($vEmail) ?>" autocomplete="email" required>
                            <label for="email">Email Address</label>
                        </div>

                        <?php /* country_field() supplies its own label and control, so it
                                 must not sit inside .field-float — that wrapper's absolutely
                                 positioned label was overlapping the country selector, and
                                 the second <label for="phone"> duplicated it. */ ?>
                        <div class="signup-country">
                            <?= country_field(['name' => 'phone', 'label' => 'Mobile Number (optional)']) ?>
                        </div>

                        <div class="field-float">
                            <input type="password" id="password" name="password" placeholder=" "
                                   minlength="8" autocomplete="new-password" required
                                   data-password-strength="#pwMeter">
                            <button type="button" class="toggle-pass" data-toggle-pw="#password"
                                    aria-label="Show password"><?= lucide('eye') ?></button>
                            <label for="password">Password</label>
                        </div>
                        <div class="pw-strength" id="pwMeter"><span></span></div>
                        <div class="pw-meta">
                            <span class="form-hint">Use at least 8 characters with a mix of letters, numbers &amp; symbols.</span>
                            <span class="pw-label" id="pwLabel"></span>
                        </div>

                        <div class="field-float">
                            <input type="password" id="password_confirm" name="password_confirm" placeholder=" "
                                   minlength="8" autocomplete="new-password" required>
                            <button type="button" class="toggle-pass" data-toggle-pw="#password_confirm"
                                    aria-label="Show password"><?= lucide('eye') ?></button>
                            <label for="password_confirm">Confirm Password</label>
                        </div>
                        <p class="form-error" id="matchError" style="display:none;"><?= lucide('circle-x') ?> The passwords do not match.</p>

                        <label class="checkbox mb-3">
                            <input type="checkbox" name="terms" id="terms" value="1" required>
                            <span>I agree to the
                                <a href="<?= e(url('terms')) ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>
                                and
                                <a href="<?= e(url('privacy-policy')) ?>" target="_blank" rel="noopener">Privacy Policy</a>.
                            </span>
                        </label>

                        <button class="btn btn-3d btn-block btn-lg" type="submit"><?= lucide('user-plus') ?> Create Account</button>

                        <p class="su-secure"><?= lucide('lock') ?> Your information is encrypted and never shared.</p>
                    </form>

                    <div class="auth-sep">or sign up with</div>
                    <div class="social-auth">
                        <button type="button" class="btn-social" data-social="Google">
                            <svg class="brand-svg" width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/><path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/><path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/><path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303c-.792 2.237-2.231 4.166-4.087 5.571l6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/></svg>
                            Google
                        </button>
                        <button type="button" class="btn-social" data-social="Facebook">
                            <svg class="brand-svg" width="18" height="18" viewBox="0 0 24 24" fill="#1877F2" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            Facebook
                        </button>
                    </div>

                    <p class="text-center mt-3">
                        Already have an account?
                        <a href="<?= e(url('login')) ?>" style="font-weight:700;">Sign in</a>
                    </p>
                </div>
            <?php endif; ?>

            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<script src="<?= e(asset('js/premium.js')) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.469.0/dist/umd/lucide.min.js"></script>
<script>
(function () {
    /* Render Lucide icons (this shell has no shared header/footer). */
    function drawIcons() { try { window.lucide && window.lucide.createIcons(); } catch (e) {} }
    drawIcons();

    /* Social buttons are UI-only until an OAuth provider is configured. */
    document.querySelectorAll('.btn-social[data-social]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            Swal.fire({
                icon: 'info',
                title: btn.dataset.social + ' sign-up',
                text: 'Social sign-up isn’t enabled yet. Configure an OAuth provider in admin settings to turn this on.',
                confirmButtonText: 'Got it'
            });
        });
    });

    /* Password show/hide with Lucide icon swap (self-contained, keeps behaviour). */
    document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.querySelector(btn.getAttribute('data-toggle-pw'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = show ? '<i data-lucide="eye-off"></i>' : '<i data-lucide="eye"></i>';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            drawIcons();
        });
    });

    /* Live password-strength label (mirrors premium.js meter scoring). */
    var pw      = document.getElementById('password');
    var pwLabel = document.getElementById('pwLabel');
    if (pw && pwLabel) {
        pw.addEventListener('input', function () {
            var v = pw.value, score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/\d/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            if (v === '') { pwLabel.textContent = ''; pwLabel.className = 'pw-label'; return; }
            var level = score <= 1 ? 'weak' : score <= 3 ? 'medium' : 'strong';
            pwLabel.textContent = level === 'weak' ? 'Weak' : level === 'medium' ? 'Medium' : 'Strong';
            pwLabel.className = 'pw-label ' + level;
        });
    }

    /* Live confirm-password matching + submit guard. */
    var form    = document.getElementById('signupForm');
    if (!form) return;
    var pass    = document.getElementById('password');
    var confirm = document.getElementById('password_confirm');
    var msg     = document.getElementById('matchError');
    var terms   = document.getElementById('terms');

    function passwordsMatch() {
        return confirm.value === '' || pass.value === confirm.value;
    }
    function refreshMatch() {
        var ok = passwordsMatch();
        msg.style.display = ok ? 'none' : 'block';
        confirm.classList.toggle('is-invalid', !ok);
        confirm.setCustomValidity(ok ? '' : 'Passwords do not match');
    }
    pass.addEventListener('input', refreshMatch);
    confirm.addEventListener('input', refreshMatch);

    form.addEventListener('submit', function (e) {
        if (pass.value !== confirm.value) {
            e.preventDefault();
            refreshMatch();
            confirm.focus();
            window.toast ? window.toast('error', 'Passwords do not match.')
                         : Swal.fire({ icon: 'error', title: 'Passwords do not match' });
            return;
        }
        if (!terms.checked) {
            e.preventDefault();
            window.toast ? window.toast('error', 'Please accept the Terms & Privacy Policy.')
                         : Swal.fire({ icon: 'warning', title: 'Please accept the Terms & Privacy Policy.' });
        }
    });
})();
</script>
<?php /* This is a standalone auth page with no shared footer, so the country
         selector's data + behaviour script are not emitted automatically the
         way includes/footer.php does it for the rest of the site. Without this
         the control renders but the picker never opens. */ ?>
<?= country_field_assets() ?>
</body>
</html>
