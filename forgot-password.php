<?php
/**
 * =============================================================================
 *  Forgot Password — standalone premium auth shell (DEV_CONTRACT_V2 §4).
 *  Renders its own <head> (tailwind.css + premium.css); no public header/footer.
 *
 *  On POST we call request_password_reset() and ALWAYS respond with the same
 *  generic confirmation, so an attacker cannot tell whether an email is
 *  registered (no user enumeration).
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

// Signed-in members have no reason to be here — send them to their dashboard.
if (is_member_logged_in()) {
    redirect('/account');
}

$error      = '';
$submitted  = false;
$emailValue = '';

/* Same wording is shown whether or not the account exists (no enumeration). */
$genericMessage = 'If an account exists, a reset link has been sent.';

if (is_post()) {
    require_csrf();

    $emailValue = clean(post('email'));

    /* Throttled like every other mail-sending path (verify-otp.php uses the
       same two-key shape). Without this, a loop against this endpoint sends an
       unlimited number of genuine "reset your password" emails to any address —
       flooding the victim and burning the domain's sending reputation, which
       takes receipts, verification links and OTPs down with it.

       Every branch still reports the SAME generic confirmation. Reporting the
       throttle would turn this form into an account-existence oracle, which is
       exactly what the generic message exists to prevent. */
    if (!is_email($emailValue)) {
        // Malformed address: nothing to send, no signal returned.
    } elseif (!pwf_throttle('pw-reset', 3, 600)) {
        // Per-IP budget: 3 requests / 10 minutes.
    } elseif (!pwf_throttle('pw-reset:' . strtolower($emailValue), 3, 3600)) {
        // Per-address budget: 3 / hour, so one victim cannot be flooded.
    } else {
        // Fires the reset email when the account exists; silent otherwise.
        request_password_reset($emailValue);
    }

    $submitted = true;
}

seo_set([
    'title'    => 'Forgot Password',
    'page_key' => 'forgot-password',
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
<title><?= e($siteName) ?> — Forgot Password</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
    <?php /* Same link order as includes/header.php: the design system loads LAST
             of the stylesheets so its scales and components win; the Theme Engine
             block follows because it only emits the custom properties it reads. */ ?>
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <?php echo function_exists('theme_style_tag') ? theme_style_tag('site') : ''; ?>
<style>
/* =========================================================================
   FORGOT PASSWORD — page-scoped layer ON TOP of design-system.css.
   The local --fp-* palette is gone; colour, radius, elevation and motion all
   come from the Theme Engine tokens and the design-system scales.
   ========================================================================= */
/* The two-column split is owned by premium.css's `.auth-shell:has(.auth-aside)`
   (0,2,0) — the same rule reset-password.php and verify-otp.php resolve — so no
   grid-template-columns is declared here. Restating it at (0,1,0) would lose to
   that rule anyway, and stating it at all was what let the aside go missing
   without the layout complaining. */
.fp-shell{
    min-height:100vh; display:grid;
    place-items:stretch; position:relative; overflow:hidden; padding:0;
    background:
        radial-gradient(900px 640px at 8% -4%,
            color-mix(in srgb, var(--primary, #0B4E3D) 11%, transparent), transparent 58%),
        radial-gradient(780px 560px at 96% 102%,
            color-mix(in srgb, var(--yellow, #FFE987) 55%, transparent), transparent 58%),
        var(--ivory, #FEFEF1);
}
/* The 30s rotating conic sweep is gone — a full-viewport gradient repainting
   forever behind a 452px form. The two brand washes above carry the depth. */

/* ---------------------------------------------------------- BRAND ASIDE
   The bespoke .fp-aside (its own #063566/#084881/#E67B1D palette, a conic
   aurora and a 26px coloured drop shadow) is replaced by the SHARED
   .auth-aside component premium.css already ships for reset-password.php and
   verify-otp.php, so all three recovery screens are one layout again. Three
   colour corrections, identical to reset-password.php's:
     · flat brand field instead of premium's 3-stop ramp — the brand rules ask
       for one flat field, not a gradient. White on #0B4E3D is 9.67:1.
     · the decorative blobs inherit --grad-ocean / --grad-purple, and the second
       ends in #a855f7 — re-pointed onto #fff and the highlight yellow.
     · the tick discs get an explicit alpha so they do not depend on whichever
       stop of the removed ramp sat behind them. */
.auth-aside{ background: var(--primary, #0B4E3D); }
.auth-aside .blob.b1{ background: color-mix(in srgb, #fff 22%, transparent); }
.auth-aside .blob.b2{ background: color-mix(in srgb, var(--yellow, #FFE987) 42%, transparent); }
.auth-aside .aside-points li svg{ background: rgba(255,255,255,.22); }
.auth-aside .icon-badge{ box-shadow:none; }

/* ---------------------------------------------------------- FORM PANEL */
.fp-main{ position:relative; z-index:1; display:grid; place-items:center;
    padding:clamp(1.5rem,4vw,3.75rem) clamp(.85rem,4vw,3rem); }
.fp-box{ width:100%; max-width:452px; }

.fp-logo{ display:flex; align-items:center; justify-content:center; gap:.6rem; margin-bottom:1.5rem;
    color:var(--text, #151818); font-family:var(--font-head, inherit); font-weight:800; font-size:1.1rem; text-decoration:none; }
.fp-logo img{ width:48px; height:48px; object-fit:contain; border-radius:var(--r-md); }
.fp-logo small{ display:block; font-size:.62rem; font-weight:700; letter-spacing:.09em; color:var(--muted, #4B6754); text-transform:uppercase; }

/* .card is the design-system surface; only the radius step and this page's
   padding are set here. */
.fp-card{ position:relative; overflow:hidden; padding:clamp(1.5rem,3vw,2.5rem); border-radius:var(--r-xl); box-shadow:var(--elev-4); }
.fp-card::before{ content:""; position:absolute; inset:0 0 auto; height:5px; background:var(--yellow, #FFE987); }
.fp-card:hover{ transform:none; box-shadow:var(--elev-4); border-color:var(--border, #C1CCB3); }

/* Head badge: flat brand green with a white glyph (9.67:1); the success state
   is the highlight yellow with brand-green ink (7.95:1). Both were two-stop
   gradients with a 26px coloured drop shadow. */
.fp-head-badge{ width:64px; height:64px; margin:0 auto 1.1rem; border-radius:var(--r-md); display:grid; place-items:center;
    color:#fff; background:var(--primary, #0B4E3D); }
.fp-head-badge svg.lucide{ width:28px; height:28px; }
.fp-head-badge.is-success{ background:var(--yellow, #FFE987); color:var(--primary, #0B4E3D); }

.fp-card .section-head{ margin-bottom:1.4rem; }
.fp-card .section-title{ font-size:clamp(1.5rem,5.2vw,1.85rem); line-height:1.15; }
.fp-card .section-subtitle{ font-size:.96rem; line-height:1.6; }

/* Floating field — token focus ring + trailing icon */
.fp-card .field-float{ margin-bottom:1.15rem; }
.fp-field input{ padding-right:3rem; font-size:1rem; border-radius:var(--r-sm); }
.fp-field input:focus{ border-color:var(--primary, #0B4E3D);
    box-shadow:0 0 0 3px color-mix(in srgb, var(--primary, #0B4E3D) 16%, transparent); }
.fp-field input:focus ~ label,
.fp-field input:not(:placeholder-shown) ~ label{ color:var(--primary, #0B4E3D); }
.fp-field-ico{ position:absolute; right:1rem; top:50%; transform:translateY(-50%);
    display:grid; place-items:center; color:var(--muted, #4B6754); pointer-events:none;
    transition:color var(--dur, .3s) var(--ease-out, ease); }
.fp-field-ico svg.lucide{ width:19px; height:19px; }
.fp-field:focus-within .fp-field-ico{ color:var(--primary, #0B4E3D); }

.fp-card .btn svg.lucide{ width:18px; height:18px; }
.fp-card a{ color:var(--primary, #0B4E3D); font-weight:700; }

.fp-alert-info{ display:flex; gap:.7rem; align-items:flex-start; }
.fp-alert-info svg.lucide{ width:19px; height:19px; flex-shrink:0; margin-top:2px; color:var(--secondary, #174D3D); }
.alert{ border-radius:var(--r-sm); }

.fp-back{ display:inline-flex; align-items:center; gap:.4rem; min-height:44px;
    color:var(--muted, #4B6754); font-size:.9rem; font-weight:600; }
.fp-back:hover{ color:var(--primary, #0B4E3D); }
.fp-back svg.lucide{ width:16px; height:16px; }

/* --------------------------------------------------------- RESPONSIVE */
@media (max-width:960px){
    .fp-main{ padding:2.5rem 1.25rem; }
}
@media (max-width:420px){
    .fp-main{ padding:1.25rem .7rem; }
    .fp-card{ padding:1.35rem 1.05rem; }
    .fp-logo img{ width:44px; height:44px; }
}
</style>
</head>
<body>
<div class="auth-shell fp-shell">

    <?php /* Restored. The redesign pass dropped this whole aside, which took the
             page from the split-screen every other recovery screen uses down to a
             single column, and took the heading, the tagline lede and the four
             reassurance points with it. All four points are factual: the reset
             link is single-use and does expire in 30 minutes
             (includes/member_auth.php:236). The two `data-counter` stats that
             were also in here (25,000 lives / 800 volunteers) are NOT restored —
             they are unverified placeholder figures, and the same two have been
             removed from reset-password.php's aside for the same reason. */ ?>
    <!-- ============================== BRAND ASIDE ============================== -->
    <aside class="auth-aside">
        <span class="blob b1" style="top:-70px;left:-60px;"></span>
        <span class="blob b2" style="bottom:-80px;right:-50px;"></span>
        <div class="floating-shapes" aria-hidden="true">
            <span class="shape" style="top:18%;left:14%;"></span>
            <span class="shape" style="top:66%;left:78%;"></span>
            <span class="shape" style="top:46%;left:54%;"></span>
        </div>

        <div class="aside-inner">
            <a href="<?= e(url('')) ?>" class="flex items-center gap-2 mb-4" style="color:#fff;text-decoration:none;font-weight:800;font-size:1.15rem;">
                <span class="icon-badge" style="background:rgba(255,255,255,.2);width:44px;height:44px;"><?= lucide('shield') ?></span>
                <?= e($siteName) ?>
            </a>

            <h2>Locked out? Let's get you back in. <?= lucide('key-round') ?></h2>
            <p class="aside-tagline"><?= e($tagline) ?></p>
            <p style="font-size:1.05rem;">No worries — resetting your password takes less than a minute. We'll email you a secure link to set a new one.</p>

            <ul class="aside-points">
                <li><?= lucide('check') ?> Secure, single-use reset link</li>
                <li><?= lucide('check') ?> Link expires in 30 minutes</li>
                <li><?= lucide('check') ?> Your data stays private &amp; protected</li>
                <li><?= lucide('check') ?> Need help? Our team is one click away</li>
            </ul>
        </div>
    </aside>

    <!-- ============================== RESET FORM ============================== -->
    <main class="fp-main">
        <div class="fp-box">

            <a href="<?= e(url('')) ?>" class="fp-logo">
                <img src="<?= e(asset('images/logo-128.webp')) ?>" alt="<?= e($siteName) ?>" width="48" height="48">
                <span><?= e($siteName) ?><small>Member Portal</small></span>
            </a>

            <div class="card fp-card reveal">

                <?php if ($submitted): ?>

                    <!-- ---------- Generic confirmation (no user enumeration) ---------- -->
                    <div class="section-head is-centered">
                        <span class="fp-head-badge is-success"><?= lucide('mail-check') ?></span>
                        <h2 class="section-title">Check your inbox</h2>
                        <p class="section-subtitle">
                            <?= e($genericMessage) ?>
                        </p>
                    </div>

                    <div class="alert alert-info fp-alert-info mt-3">
                        <?= lucide('info') ?>
                        <span>Didn't get an email within a few minutes? Check your spam folder, or make sure you entered the address you registered with. The reset link expires in 30 minutes.</span>
                    </div>

                    <a href="<?= e(url('login')) ?>" class="btn btn-secondary btn-block btn-lg mt-2">
                        <?= lucide('arrow-left') ?> Back to Sign In
                    </a>

                    <p class="text-center mt-3" style="margin-bottom:0;">
                        Entered the wrong email?
                        <a class="link-underline" href="<?= e(url('forgot-password')) ?>">Try again</a>
                    </p>

                <?php else: ?>

                    <!-- ---------- Request form ---------- -->
                    <div class="section-head is-centered">
                        <span class="fp-head-badge"><?= lucide('key-round') ?></span>
                        <span class="eyebrow">Account Recovery</span>
                        <h2 class="section-title">Forgot Password?</h2>
                        <p class="section-subtitle">Enter the email linked to your account and we'll send you a reset link.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <?= csrf_field() ?>

                        <div class="field-float fp-field">
                            <input type="email" id="email" name="email" placeholder=" " required autocomplete="email"
                                   value="<?= e($emailValue) ?>" autofocus>
                            <label for="email">Email address</label>
                            <span class="fp-field-ico"><?= lucide('mail') ?></span>
                        </div>

                        <button class="btn btn-secondary btn-block btn-lg" type="submit">
                            <?= lucide('send') ?> Send Reset Link
                        </button>
                    </form>

                    <p class="text-center mt-3" style="margin-bottom:0;">
                        Remembered it? <a class="link-underline" href="<?= e(url('login')) ?>">Back to Sign In</a>
                    </p>
                    <p class="text-center mt-1" style="margin-bottom:0;">
                        New here? <a class="link-underline" href="<?= e(url('signup')) ?>">Create an account</a>
                    </p>

                <?php endif; ?>
            </div>

            <p class="text-center mt-3" style="margin-bottom:0;">
                <a href="<?= e(url('')) ?>" class="fp-back"><?= lucide('arrow-left') ?> Back to homepage</a>
            </p>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<script src="<?= e(asset('js/premium.js')) ?>"></script>
<!-- Lucide icons (standalone auth shell — no shared footer) -->
<script src="https://cdn.jsdelivr.net/npm/lucide@0.469.0/dist/umd/lucide.min.js"></script>
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
