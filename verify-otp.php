<?php
/**
 * =============================================================================
 *  Verify OTP — standalone premium auth shell (DEV_CONTRACT_V2 §4).
 *  Renders its own <head> (tailwind.css + premium.css); no public header/footer.
 *
 *  Flow:
 *    - The email arrives via ?email= (from signup / "verify now" links) or is
 *      typed into the request-code form when missing.
 *    - On the first GET that carries a valid email we send ONE 6-digit code
 *      (guarded by a per-email session flag so a refresh doesn't re-send).
 *    - POST action=verify  -> verify_member_otp(); success -> flash + /login.
 *    - POST action=send/resend -> send_member_otp() again.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

// A signed-in member is already verified — nothing to do here.
if (is_member_logged_in()) {
    redirect('/account');
}

$error    = '';
$notice   = '';
$otpValue = '';

// Email may come from the hidden field (POST) or the URL (GET).
$email      = strtolower(clean(request('email', '')));
$validEmail = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

if (is_post()) {
    require_csrf();

    $action = post('action', 'verify');

    if ($action === 'send' || $action === 'resend') {
        // ---- (Re)send a fresh code -------------------------------------------
        if (!$validEmail) {
            $error = 'Please enter a valid email address to receive a code.';
        } else {
            send_member_otp($email);
            $_SESSION['_otp_sent_for'] = $email;
            $notice = 'A fresh 6-digit code is on its way. It expires in 15 minutes.';
        }
    } else {
        // ---- Verify the entered code -----------------------------------------
        $otpValue = preg_replace('/\D/', '', (string) post('otp'));

        if (!$validEmail) {
            $error = 'We could not find the email for this request. Please request a new code below.';
        } elseif (strlen($otpValue) !== 6) {
            $error = 'Please enter the complete 6-digit code.';
        } else {
            $r = verify_member_otp($email, $otpValue);
            if (!empty($r['success'])) {
                set_flash('success', $r['message'] . ' You can now sign in.');
                redirect('/login');
            }
            $error = $r['message'];
        }
    }
} elseif ($validEmail && (($_SESSION['_otp_sent_for'] ?? '') !== $email)) {
    // First GET for this email in the session -> send exactly one code.
    // Rate-limited per IP so the endpoint can't be scripted to spam inboxes.
    if (pwf_throttle('otp-send', 3, 600)) {
        send_member_otp($email);
        $_SESSION['_otp_sent_for'] = $email;
        $notice = 'We have emailed a 6-digit verification code to your inbox. It expires in 15 minutes.';
    } else {
        $notice = 'A code was recently sent. Please check your inbox (and spam folder), or try again in a few minutes.';
    }
}

seo_set([
    'title'    => 'Verify OTP',
    'page_key' => 'verify-otp',
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
<title><?= e($siteName) ?> — Verify OTP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
</head>
<body>
<div class="auth-shell">

    <!-- ============================== VERIFY PANEL ============================== -->
    <main class="auth-main">
        <div class="auth-box">
            <div class="glass-card">

                <div class="text-center mb-3">
                    <span class="icon-badge accent" style="width:64px;height:64px;font-size:1.7rem;margin:0 auto 1rem;"><?= lucide('shield') ?></span>
                    <span class="eyebrow" style="justify-content:center;">Email Verification</span>
                    <h2 class="mb-0" style="font-size:1.9rem;">Verify Your Account</h2>
                    <?php if ($validEmail): ?>
                        <p class="text-muted mt-1" style="margin-bottom:0;">
                            Enter the 6-digit code sent to<br>
                            <strong style="color:var(--text);"><?= e($email) ?></strong>
                        </p>
                    <?php else: ?>
                        <p class="text-muted mt-1" style="margin-bottom:0;">Tell us your email and we'll send you a fresh verification code.</p>
                    <?php endif; ?>
                </div>

                <?php if ($notice): ?>
                    <div class="alert alert-info"><?= e($notice) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($validEmail): ?>

                    <!-- ---------- OTP entry ---------- -->
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="verify">
                        <input type="hidden" name="email" value="<?= e($email) ?>">

                        <div class="mb-3">
                            <div class="otp-inputs">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*"
                                           autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                                           aria-label="Digit <?= $i + 1 ?>"
                                           value="<?= e(substr($otpValue, $i, 1)) ?>"
                                           <?= $i === 0 ? 'autofocus' : '' ?>>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="otp" data-otp-value value="<?= e($otpValue) ?>">
                        </div>

                        <button class="btn btn-3d btn-block btn-lg" type="submit">Verify &amp; Continue</button>
                    </form>

                    <!-- ---------- Resend (separate POST) ---------- -->
                    <form method="post" class="text-center mt-3" style="margin-bottom:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="resend">
                        <input type="hidden" name="email" value="<?= e($email) ?>">
                        <span class="text-muted" style="font-size:.92rem;">Didn't receive it? Check spam, or</span>
                        <button type="submit" class="btn btn-ghost btn-sm" style="font-weight:700;">Resend code</button>
                    </form>

                <?php else: ?>

                    <!-- ---------- Request a code (email missing) ---------- -->
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send">

                        <div class="field-float">
                            <input type="email" id="email" name="email" placeholder=" " required autocomplete="email"
                                   value="<?= e($email) ?>" autofocus>
                            <label for="email">Email address</label>
                        </div>

                        <button class="btn btn-3d btn-block btn-lg" type="submit">Send Code</button>
                    </form>

                <?php endif; ?>

                <div class="divider mt-3"></div>

                <p class="text-center" style="margin-bottom:0;">
                    Already verified? <a href="<?= e(url('login')) ?>" style="font-weight:700;">Back to Sign In</a>
                </p>
                <p class="text-center mt-1" style="margin-bottom:0;">
                    Need a new account? <a href="<?= e(url('signup')) ?>" style="font-weight:700;">Create one</a>
                </p>
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
