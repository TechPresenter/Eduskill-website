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

    // Fires the reset email when the account exists; silent otherwise.
    request_password_reset($emailValue);

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
    <?php echo function_exists('theme_style_tag') ? theme_style_tag('site') : ''; ?>
<style>
/* =========================================================================
   FORGOT PASSWORD — page-scoped premium redesign
   Forest green (#063566) · teal (#084881) · gold (#E67B1D)
   ========================================================================= */
.fp-shell{
    --fp-green-900:#0f3d24; --fp-green-700:#042a52; --fp-green-600:#063566;
    --fp-teal:#084881; --fp-gold:#E67B1D;
    min-height:100vh; display:grid; grid-template-columns:1.05fr 1fr;
    place-items:stretch; position:relative; overflow:hidden; padding:0;
    background:
        radial-gradient(900px circle at 12% 6%, rgba(6,53,102,.12), transparent 46%),
        radial-gradient(820px circle at 88% 94%, rgba(230,123,29,.13), transparent 46%),
        var(--grad-soft);
}
/* Slow aurora sweep behind the whole shell */
.fp-shell::before{
    content:""; position:absolute; inset:-25%; z-index:0; pointer-events:none;
    background:conic-gradient(from 0deg at 50% 50%, rgba(6,53,102,0), rgba(8,72,129,.10), rgba(230,123,29,.07), rgba(6,53,102,0));
    animation:fpAurora 30s linear infinite;
}
@keyframes fpAurora{ to{ transform:rotate(1turn); } }

/* ---------------------------------------------------------- BRAND ASIDE */
.fp-aside{
    position:relative; overflow:hidden; z-index:1; color:#fff;
    display:flex; align-items:center;
    padding:clamp(2.5rem,5vw,4.75rem);
    background:linear-gradient(150deg, var(--fp-green-900) 0%, var(--fp-green-600) 42%, var(--fp-teal) 100%);
    isolation:isolate;
}
.fp-aside::after{
    content:""; position:absolute; inset:0; z-index:0; opacity:.7; pointer-events:none;
    background:
        radial-gradient(circle at 18% 26%, rgba(255,255,255,.10) 0, transparent 42%),
        radial-gradient(circle at 82% 74%, rgba(230,123,29,.20) 0, transparent 45%);
}
.fp-aside .blob{ opacity:.4; filter:blur(4px); }
.fp-aside .blob.b1{ background:linear-gradient(135deg,#084881,#7BC94F); }
.fp-aside .blob.b2{ background:linear-gradient(135deg,#E67B1D,#063566); }
.fp-aside .shape{ color:#fff; opacity:.14; }
.fp-aside .shape svg{ width:100%; height:100%; }
.aside-inner{ position:relative; z-index:2; max-width:460px; }

.fp-brand{ display:inline-flex; align-items:center; gap:.7rem; color:#fff; text-decoration:none;
    font-family:'Outfit',sans-serif; font-weight:800; font-size:1.18rem; letter-spacing:-.01em; margin-bottom:2.2rem; }
.fp-brand-badge{ width:46px; height:46px; border-radius:14px; display:grid; place-items:center;
    background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.28);
    box-shadow:inset 0 1px 0 rgba(255,255,255,.35); }
.fp-brand-badge svg.lucide{ width:24px; height:24px; color:#fff; }
.fp-brand:hover{ color:#fff; }
.fp-brand:hover .fp-brand-badge{ transform:translateY(-2px) rotate(-4deg); transition:transform .25s ease; }

.fp-chip{ display:inline-flex; align-items:center; gap:.45rem; padding:.4rem .85rem; border-radius:999px;
    background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.24);
    font-size:.76rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; margin-bottom:1.1rem; }
.fp-chip svg.lucide{ width:15px; height:15px; color:var(--fp-gold); }

.fp-aside h2{ font-family:'Outfit',sans-serif; font-size:clamp(1.9rem,3.2vw,2.5rem); line-height:1.12;
    font-weight:800; letter-spacing:-.02em; margin:0 0 1rem; color:#fff; }
.fp-aside .fp-lede{ font-size:1.06rem; line-height:1.65; color:rgba(255,255,255,.9); margin:0 0 1.75rem; }

.fp-points{ list-style:none; margin:0 0 2rem; padding:0; display:grid; gap:.75rem; }
.fp-points li{ display:flex; align-items:flex-start; gap:.7rem; font-size:.98rem; color:rgba(255,255,255,.94); line-height:1.4; }
.fp-tick{ flex-shrink:0; width:26px; height:26px; border-radius:50%; display:grid; place-items:center;
    background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.26); margin-top:1px; }
.fp-tick svg.lucide{ width:15px; height:15px; color:var(--fp-gold); stroke-width:3; }

.fp-stats{ display:flex; gap:2.25rem; padding-top:1.5rem; border-top:1px solid rgba(255,255,255,.18); }
.fp-stats .num{ font-family:'Outfit',sans-serif; font-size:1.85rem; font-weight:800; line-height:1; color:#fff; }
.fp-stats .lbl{ display:block; margin-top:.25rem; font-size:.82rem; color:rgba(255,255,255,.82); }

/* ---------------------------------------------------------- FORM PANEL */
.fp-main{ position:relative; z-index:1; display:grid; place-items:center;
    padding:clamp(2rem,4vw,3.75rem) clamp(1.25rem,4vw,3rem); }
.fp-box{ width:100%; max-width:452px; }

.fp-logo{ display:flex; align-items:center; justify-content:center; gap:.6rem; margin-bottom:1.5rem;
    color:var(--text); font-family:'Outfit',sans-serif; font-weight:800; font-size:1.1rem; text-decoration:none; }
.fp-logo img{ width:48px; height:48px; object-fit:contain; }
.fp-logo small{ display:block; font-size:.6rem; font-weight:700; letter-spacing:.09em; color:var(--muted); text-transform:uppercase; }

.fp-card{ padding:clamp(1.85rem,3vw,2.6rem); border-radius:var(--radius-lg); }

.fp-head-badge{ width:66px; height:66px; margin:0 auto 1.1rem; border-radius:20px; display:grid; place-items:center;
    color:#fff; background:linear-gradient(135deg, var(--fp-green-600), var(--fp-teal));
    box-shadow:0 12px 26px rgba(6,53,102,.32); position:relative; }
.fp-head-badge::after{ content:""; position:absolute; inset:0; border-radius:20px; border:1px solid rgba(255,255,255,.35);
    -webkit-mask:linear-gradient(#fff,transparent); mask:linear-gradient(#fff,transparent); }
.fp-head-badge svg.lucide{ width:30px; height:30px; }
.fp-head-badge.is-success{ background:linear-gradient(135deg,#58A42F,#084881);
    box-shadow:0 12px 26px rgba(16,163,74,.35); animation:fpPop .5s cubic-bezier(.34,1.56,.64,1) both; }
@keyframes fpPop{ 0%{ transform:scale(.6); opacity:0; } 100%{ transform:scale(1); opacity:1; } }

.fp-card h2{ font-family:'Outfit',sans-serif; font-size:1.75rem; font-weight:800; letter-spacing:-.02em; margin:0; color:var(--text); }
.fp-card .eyebrow{ margin-bottom:.5rem; }

/* Floating field — greened focus + trailing icon */
.fp-card .field-float{ margin-bottom:1.15rem; }
.fp-field input{ padding-right:2.7rem; }
.fp-field input:focus{ border-color:var(--fp-green-600); box-shadow:0 0 0 4px rgba(6,53,102,.16); }
.fp-field input:focus + label,
.fp-field input:not(:placeholder-shown) + label{ color:var(--fp-green-600); }
.fp-field-ico{ position:absolute; right:.95rem; top:50%; transform:translateY(-50%);
    display:grid; place-items:center; color:var(--muted); pointer-events:none; transition:color .2s; }
.fp-field-ico svg.lucide{ width:19px; height:19px; }
.fp-field:focus-within .fp-field-ico{ color:var(--fp-green-600); }

/* Brand green 3D button (overrides default blue) */
.fp-card .btn-3d.fp-btn{ display:inline-flex; align-items:center; justify-content:center; gap:.55rem;
    background:linear-gradient(135deg, var(--fp-green-600) 0%, var(--fp-teal) 100%);
    box-shadow:0 6px 0 var(--fp-green-700), 0 14px 28px rgba(6,53,102,.34); }
.fp-card .btn-3d.fp-btn:hover{ transform:translateY(-3px);
    box-shadow:0 9px 0 var(--fp-green-700), 0 20px 38px rgba(6,53,102,.42); }
.fp-card .btn-3d.fp-btn:active{ transform:translateY(3px);
    box-shadow:0 2px 0 var(--fp-green-700), 0 8px 16px rgba(6,53,102,.3); }
.fp-card .btn-3d.fp-btn svg.lucide{ width:18px; height:18px; }

.fp-card a{ color:var(--fp-green-600); font-weight:700; }
.fp-card a:hover{ color:var(--fp-green-700); }

.fp-alert-info{ display:flex; gap:.7rem; align-items:flex-start; }
.fp-alert-info svg.lucide{ width:19px; height:19px; flex-shrink:0; margin-top:2px; color:var(--info); }

.fp-back{ display:inline-flex; align-items:center; gap:.4rem; color:var(--muted); font-size:.9rem; font-weight:600; }
.fp-back:hover{ color:var(--fp-green-600); }
.fp-back svg.lucide{ width:16px; height:16px; }

/* --------------------------------------------------------- RESPONSIVE */
@media (max-width:960px){
    .fp-shell{ grid-template-columns:1fr; }
    .fp-aside{ display:none; }
    .fp-main{ padding:2.5rem 1.25rem; }
}
@media (max-width:420px){
    .fp-stats{ gap:1.5rem; }
    .fp-card{ padding:1.5rem 1.25rem; }
}
@media (prefers-reduced-motion:reduce){
    .fp-shell::before,.fp-head-badge.is-success{ animation:none; }
}
</style>
</head>
<body>
<div class="auth-shell fp-shell">

    <!-- ============================== BRAND ASIDE ============================== -->
    <aside class="fp-aside">
        <span class="blob b1" style="top:-70px;left:-60px;"></span>
        <span class="blob b2" style="bottom:-80px;right:-50px;"></span>
        <div class="floating-shapes" aria-hidden="true">
            <span class="shape" style="top:18%;left:14%;width:34px;height:34px;"><?= lucide('shield') ?></span>
            <span class="shape" style="top:66%;left:78%;width:28px;height:28px;"><?= lucide('key-round') ?></span>
            <span class="shape" style="top:46%;left:54%;width:24px;height:24px;"><?= lucide('lock') ?></span>
        </div>

        <div class="aside-inner">
            <a href="<?= e(url('')) ?>" class="fp-brand">
                <span class="fp-brand-badge"><?= lucide('heart') ?></span>
                <?= e($siteName) ?>
            </a>

            <span class="fp-chip"><?= lucide('shield') ?> Account Recovery</span>
            <h2>Locked out? Let's get you back in.</h2>
            <p class="fp-lede"><?= e($tagline) ?> No worries — resetting your password takes less than a minute. We'll email you a secure link to set a new one.</p>

            <ul class="fp-points">
                <li><span class="fp-tick"><?= lucide('check') ?></span> Secure, single-use reset link</li>
                <li><span class="fp-tick"><?= lucide('check') ?></span> Link expires in 30 minutes</li>
                <li><span class="fp-tick"><?= lucide('check') ?></span> Your data stays private &amp; protected</li>
                <li><span class="fp-tick"><?= lucide('check') ?></span> Need help? Our team is one click away</li>
            </ul>

            <div class="fp-stats">
                <div>
                    <div class="num"><span data-counter="25000">0</span>+</div>
                    <span class="lbl">Lives impacted</span>
                </div>
                <div>
                    <div class="num"><span data-counter="800">0</span>+</div>
                    <span class="lbl">Volunteers</span>
                </div>
            </div>
        </div>
    </aside>

    <!-- ============================== RESET FORM ============================== -->
    <main class="fp-main">
        <div class="fp-box">

            <a href="<?= e(url('')) ?>" class="fp-logo">
                <img src="<?= e(asset('images/logo-128.webp')) ?>" alt="<?= e($siteName) ?>" width="48" height="48">
                <span><?= e($siteName) ?><small>Member Portal</small></span>
            </a>

            <div class="glass-card fp-card reveal">

                <?php if ($submitted): ?>

                    <!-- ---------- Generic confirmation (no user enumeration) ---------- -->
                    <div class="text-center">
                        <span class="fp-head-badge is-success"><?= lucide('mail-check') ?></span>
                        <h2>Check your inbox</h2>
                        <p class="text-muted mt-1" style="margin-bottom:0;">
                            <?= e($genericMessage) ?>
                        </p>
                    </div>

                    <div class="alert alert-info fp-alert-info mt-3">
                        <?= lucide('info') ?>
                        <span>Didn't get an email within a few minutes? Check your spam folder, or make sure you entered the address you registered with. The reset link expires in 30 minutes.</span>
                    </div>

                    <a href="<?= e(url('login')) ?>" class="btn btn-3d fp-btn btn-block btn-lg mt-2">
                        <?= lucide('arrow-left') ?> Back to Sign In
                    </a>

                    <p class="text-center mt-3" style="margin-bottom:0;">
                        Entered the wrong email?
                        <a href="<?= e(url('forgot-password')) ?>">Try again</a>
                    </p>

                <?php else: ?>

                    <!-- ---------- Request form ---------- -->
                    <div class="text-center mb-3">
                        <span class="fp-head-badge"><?= lucide('key-round') ?></span>
                        <span class="eyebrow" style="justify-content:center;">Account Recovery</span>
                        <h2>Forgot Password?</h2>
                        <p class="text-muted mt-1" style="margin-bottom:0;">Enter the email linked to your account and we'll send you a reset link.</p>
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

                        <button class="btn btn-3d fp-btn btn-block btn-lg" type="submit">
                            <?= lucide('send') ?> Send Reset Link
                        </button>
                    </form>

                    <p class="text-center mt-3" style="margin-bottom:0;">
                        Remembered it? <a href="<?= e(url('login')) ?>">Back to Sign In</a>
                    </p>
                    <p class="text-center mt-1" style="margin-bottom:0;">
                        New here? <a href="<?= e(url('signup')) ?>">Create an account</a>
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
