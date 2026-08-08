<?php
/**
 * =============================================================================
 *  Verify Email — standalone premium auth shell (DEV_CONTRACT_V2 §4).
 *  Renders its own <head> (tailwind.css + premium.css); no public header/footer.
 *
 *  Two flows:
 *    1. Link click  -> /verify-email?email=&token=
 *       Calls verify_member_email() and shows a success / error result card.
 *       A stale-but-already-verified link is treated gracefully (no scary error).
 *    2. No params (or resend) -> instructions + a "resend verification" form.
 *       The resend is privacy-preserving: we always show the same confirmation,
 *       so an attacker cannot probe which emails are registered.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

// Signed-in members are already verified — send them to their dashboard.
if (is_member_logged_in()) {
    redirect('/account');
}

$verifyResult = null;   // ['success'=>bool,'message'=>string] when a link was checked
$resent       = false;  // true once a resend confirmation should be shown
$formError    = '';     // inline validation error on the resend form
$emailValue   = '';     // pre-fills / carries the address between requests

/* ------------------------------------------------------------ RESEND (POST) */
if (is_post()) {
    require_csrf();

    $emailValue = strtolower(clean(post('email')));
    $errors     = validate($_POST, ['email' => 'required|email']);

    if ($errors) {
        $formError = reset($errors);
    } else {
        // Only actually send when the account exists and still needs verifying,
        // but ALWAYS report success (no user enumeration).
        $member = find_by('members', 'email', $emailValue);
        if ($member && empty($member['email_verified_at']) && $member['status'] !== 'suspended') {
            send_member_verification($emailValue, (int) $member['id'], $member['name']);
        }
        $resent = true;
    }
}

/* ---------------------------------------------------- VERIFICATION (GET link) */
if (!is_post()) {
    $linkEmail = clean(get('email'));
    $linkToken = clean(get('token'));

    if ($linkEmail !== '' && $linkToken !== '') {
        $emailValue   = strtolower($linkEmail);
        $verifyResult = verify_member_email($linkEmail, $linkToken);

        // Refreshing a consumed link, or an already-verified account, shouldn't
        // look like a failure — show a friendly "already verified" state.
        if (empty($verifyResult['success'])) {
            $existing = find_by('members', 'email', $emailValue);
            if ($existing && !empty($existing['email_verified_at'])) {
                $verifyResult = [
                    'success' => true,
                    'message' => 'Your email is already verified. You can sign in anytime.',
                ];
            }
        }
    }
}

$verified   = $verifyResult !== null && !empty($verifyResult['success']);
$failed     = $verifyResult !== null && empty($verifyResult['success']);

seo_set([
    'title'    => 'Verify Email',
    'page_key' => 'verify-email',
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
<title><?= e($siteName) ?> — Verify Email</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
</head>
<body>
<!-- Centered, full-width shell: the brand aside is intentionally hidden here so
     the verification result reads like a focused, single-purpose status page. -->
<div class="auth-shell" style="grid-template-columns:1fr;">
    <main class="auth-main" style="position:relative;overflow:hidden;">
        <span class="blob b1" style="top:-90px;left:-70px;opacity:.28;"></span>
        <span class="blob b3" style="bottom:-110px;right:-80px;opacity:.28;"></span>
        <div class="floating-shapes" aria-hidden="true">
            <span class="shape" style="top:16%;left:14%;"></span>
            <span class="shape" style="top:70%;left:82%;"></span>
            <span class="shape" style="top:40%;left:90%;"></span>
        </div>

        <div class="auth-box reveal" style="max-width:480px;position:relative;z-index:2;">
            <div class="text-center mb-3">
                <a href="<?= e(url('')) ?>" class="flex items-center justify-center gap-2" style="text-decoration:none;font-weight:800;font-size:1.1rem;color:var(--text);">
                    <span class="icon-badge"><?= lucide('heart') ?></span>
                    <?= e($siteName) ?>
                </a>
            </div>

            <div class="glass-card">

            <?php if ($verified): ?>

                <!-- ============================ SUCCESS ============================ -->
                <div class="icon-box g" style="padding:0;">
                    <div class="ib-icon"><?= lucide('check') ?></div>
                    <span class="eyebrow" style="justify-content:center;">Email Confirmed</span>
                    <h2 class="mb-0" style="font-size:1.9rem;">You're all set! <?= lucide('party-popper') ?></h2>
                    <p class="text-muted mt-1"><?= e($verifyResult['message']) ?></p>
                </div>

                <a href="<?= e(url('login')) ?>" class="btn btn-3d green btn-block btn-lg mt-2">Continue to Login</a>

                <p class="text-center mt-3" style="margin-bottom:0;">
                    Ready to make a difference?
                    <a href="<?= e(url('donate')) ?>" style="font-weight:700;">Donate now</a>
                </p>

            <?php elseif ($failed): ?>

                <!-- ============================= FAILURE ============================ -->
                <div class="icon-box o" style="padding:0;">
                    <div class="ib-icon">!</div>
                    <span class="eyebrow" style="justify-content:center;">Verification Failed</span>
                    <h2 class="mb-0" style="font-size:1.9rem;">Link expired or invalid</h2>
                    <p class="text-muted mt-1"><?= e($verifyResult['message']) ?></p>
                </div>

                <div class="alert alert-warning mt-2">
                    Verification links expire after 24 hours and can be used only once. Request a fresh link below and we'll email it to you right away.
                </div>

                <form method="post" action="<?= e(url('verify-email')) ?>" novalidate class="mt-2">
                    <?= csrf_field() ?>
                    <div class="field-float">
                        <input type="email" id="email" name="email" placeholder=" " required autocomplete="email"
                               value="<?= e($emailValue) ?>">
                        <label for="email">Your email address</label>
                    </div>
                    <button class="btn btn-3d btn-block btn-lg" type="submit">Resend Verification Link</button>
                </form>

                <p class="text-center mt-3" style="margin-bottom:0;">
                    Already verified? <a href="<?= e(url('login')) ?>" style="font-weight:700;">Sign in</a>
                </p>

            <?php elseif ($resent): ?>

                <!-- ===================== RESEND CONFIRMATION ===================== -->
                <div class="icon-box" style="padding:0;">
                    <div class="ib-icon"><?= lucide('mail') ?></div>
                    <span class="eyebrow" style="justify-content:center;">Check Your Inbox</span>
                    <h2 class="mb-0" style="font-size:1.9rem;">Verification link sent</h2>
                    <p class="text-muted mt-1">
                        If an account with that email still needs verifying, a fresh link is on its way. It expires in 24 hours.
                    </p>
                </div>

                <div class="alert alert-info mt-2">
                    Didn't receive it? Check your spam folder, or make sure you entered the address you registered with.
                </div>

                <a href="<?= e(url('login')) ?>" class="btn btn-3d btn-block btn-lg mt-2">Back to Sign In</a>

                <p class="text-center mt-3" style="margin-bottom:0;">
                    Entered the wrong email?
                    <a href="<?= e(url('verify-email')) ?>" style="font-weight:700;">Try again</a>
                </p>

            <?php else: ?>

                <!-- ================= INSTRUCTIONS + RESEND FORM ================= -->
                <div class="icon-box p" style="padding:0;">
                    <div class="ib-icon"><?= lucide('mail') ?></div>
                    <span class="eyebrow" style="justify-content:center;">Almost there</span>
                    <h2 class="mb-0" style="font-size:1.9rem;">Verify your email</h2>
                    <p class="text-muted mt-1">
                        We sent a verification link to your inbox when you signed up. Click it to activate your account.
                    </p>
                </div>

                <ul class="list-check mt-2 mb-3">
                    <li>Open the email from <?= e($siteName) ?> and tap <strong>Verify Email</strong>.</li>
                    <li>Can't find it? Check your spam or promotions folder.</li>
                    <li>Link expired? Enter your email below for a new one.</li>
                </ul>

                <?php if ($formError): ?>
                    <div class="alert alert-error"><?= e($formError) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('verify-email')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="field-float">
                        <input type="email" id="email" name="email" placeholder=" " required autocomplete="email"
                               value="<?= e($emailValue) ?>" autofocus>
                        <label for="email">Your email address</label>
                    </div>
                    <button class="btn btn-3d btn-block btn-lg" type="submit">Send Verification Link</button>
                </form>

                <p class="text-center mt-3" style="margin-bottom:0;">
                    Already verified? <a href="<?= e(url('login')) ?>" style="font-weight:700;">Sign in</a>
                </p>
                <p class="text-center mt-1" style="margin-bottom:0;">
                    New here? <a href="<?= e(url('signup')) ?>" style="font-weight:700;">Create an account</a>
                </p>

            <?php endif; ?>

            </div>

            <p class="text-center mt-3">
                <a href="<?= e(url('')) ?>" class="text-muted" style="font-size:.9rem;"><?= lucide('arrow-left') ?> Back to homepage</a>
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
})();
</script>
</body>
</html>
