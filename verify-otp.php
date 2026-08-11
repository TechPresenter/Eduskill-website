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

/**
 * Send a code ONLY to an address that actually needs one — but answer
 * identically either way.
 *
 * This page used to mail a 6-digit code to whatever address it was handed, with
 * no check that the address was registered, unverified, or even real. Two things
 * came out of that:
 *
 *   1. It was an outbound-mail amplifier pointed at any third party the caller
 *      names, sending the foundation's own templated mail from its own domain.
 *      Rate-limited, but rate-limited per IP — see the ceiling in
 *      member_auth.php for why that is not the same as bounded.
 *   2. Codes were minted for addresses with no account (create_member_token()
 *      accepts member_id = NULL), and verify_member_otp() would accept one — so
 *      the page was a working "verify anything" endpoint whose only effect was
 *      to confuse the person who received the mail.
 *
 * The same three conditions verify-email.php's resend uses, so the two agree:
 * the account exists, it is not already verified, and it is not suspended. The
 * NOTICE is deliberately identical in every case, including "no such account" —
 * a different message here would turn this page into an account-existence
 * oracle, which is the exact thing the sign-up form goes to some trouble to
 * avoid being.
 *
 * @return bool true when a code was actually sent (for the session flag only).
 */
$otpDispatch = static function (string $addr): bool {
    $member = find_by('members', 'email', $addr);
    $needs  = $member
        && empty($member['email_verified_at'])
        && ($member['status'] ?? '') !== 'suspended';
    if ($needs) {
        send_member_otp($addr);
        return true;
    }
    /* Not an error the caller may see, but an event worth having: a burst of
       these is somebody walking a list of addresses through this page. */
    log_activity('member_otp_not_sent', 'members',
        'OTP requested for ' . $addr . ' — ' . ($member === null
            ? 'no such account'
            : (!empty($member['email_verified_at']) ? 'already verified' : 'account suspended'))
        . '; no code sent, neutral response shown');
    return false;
};

if (is_post()) {
    require_csrf();

    $action = post('action', 'verify');

    if ($action === 'send' || $action === 'resend') {
        /* ---- (Re)send a fresh code ------------------------------------------
           THROTTLED. This branch had no rate limit of any kind, while the GET
           branch below did: any session holding a valid CSRF token could POST
           action=resend in a loop and mail unlimited 6-digit codes to an
           arbitrary address — an outbound-mail amplifier pointed at whoever the
           attacker names, and a way to burn the send reputation of the domain.
           Same counters as the GET path plus a per-address one, so rotating the
           address does not buy a fresh per-IP budget and vice versa.

           failOpen stays at the default (allow) here: this is a legitimate
           user's "the code never arrived" button, and a broken logs/throttle
           directory must not lock people out of verifying their own account.
           The cost of the fail-open case is unmetered mail, not an unmetered
           account mint — which is why registration itself chooses differently. */
        if (!$validEmail) {
            $error = 'Please enter a valid email address to receive a code.';
        } elseif (!pwf_throttle('otp-send', 3, 600)) {
            $notice = 'A code was recently sent. Please check your inbox (and spam folder), or try again in a few minutes.';
        } elseif (!pwf_throttle('otp-send:' . $email, 5, 3600)) {
            $notice = 'We have already sent several codes to that address. Please check your inbox, or try again later.';
        } else {
            // Same wording whether or not a code was actually sent.
            if ($otpDispatch($email)) {
                $_SESSION['_otp_sent_for'] = $email;
            }
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
                /* The message comes from member_apply_verification(), which knows
                   what verification actually achieved for THIS account. It used
                   to have ' You can now sign in.' appended unconditionally —
                   which contradicts itself for an account that is verified but
                   still waiting on approval (every School account, and anything
                   registered while auto-approve is off), and for a suspended one.
                   Say what happened, not what we hope happened. */
                set_flash('success', $r['message']);
                redirect('/login');
            }
            $error = $r['message'];
        }
    }
} elseif ($validEmail && (($_SESSION['_otp_sent_for'] ?? '') !== $email)) {
    // First GET for this email in the session -> send exactly one code.
    // Rate-limited per IP AND per address, on the same counters the POST resend
    // branch above uses, so the two cannot be alternated to double the budget.
    if (pwf_throttle('otp-send', 3, 600) && pwf_throttle('otp-send:' . $email, 5, 3600)) {
        if ($otpDispatch($email)) {
            $_SESSION['_otp_sent_for'] = $email;
        }
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
<?php /* Same link order as includes/header.php. ui.css and the design system
         were both missing here, so this screen was the only member-facing auth
         page still rendering the pre-redesign components. The design system
         loads LAST of the stylesheets so its scales win; the Theme Engine block
         follows it because it only emits the custom properties it reads. */ ?>
<link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
<?php echo function_exists('theme_style_tag') ? theme_style_tag('site') : ''; ?>
<style>
/* =========================================================================
   Verify OTP — page-scoped layer ON TOP of design-system.css.
   ========================================================================= */
.auth-shell{
    position: relative; overflow: hidden;
    padding: clamp(1.25rem, 4vw, 3rem) clamp(.85rem, 3vw, 1.5rem);
    background:
        radial-gradient(900px 640px at 6% -8%,
            color-mix(in srgb, var(--primary, #0B4E3D) 11%, transparent), transparent 58%),
        radial-gradient(780px 560px at 104% 106%,
            color-mix(in srgb, var(--yellow, #FFE987) 55%, transparent), transparent 58%),
        var(--ivory, #FEFEF1);
}
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

/* Header badge: flat highlight yellow with brand-green ink (7.95:1). It was
   var(--grad-accent), a two-stop gradient. */
.auth-box .icon-badge{
    width: 64px; height: 64px; margin: 0 auto 1rem; border-radius: var(--r-md);
    background: var(--yellow, #FFE987); color: var(--primary, #0B4E3D); box-shadow: none;
}
.auth-box .icon-badge svg.lucide{ width: 28px; height: 28px; }
.auth-box .section-head{ margin-bottom: 1.4rem; }
.auth-box .section-title{ font-size: clamp(1.55rem, 5.4vw, 1.95rem); line-height: 1.15; }
.auth-box .section-subtitle{ font-size: .96rem; line-height: 1.6; }
.auth-box .section-subtitle strong{ color: var(--text, #151818); }

/* ---------------------------------------------------------- OTP BOXES ----
   Six fixed 54px boxes plus gaps needed 372px, so on a 320px screen the row
   overflowed the card. They are flexible now, and below 400px the row becomes
   3x2 — six boxes cannot each keep a 44px touch target on one line at 320px. */
.otp-inputs{ gap: clamp(.3rem, 2vw, .6rem); }
.otp-inputs input{
    flex: 1 1 0; width: auto; min-width: 0; max-width: 56px; height: 56px;
    font-size: 1.45rem; border-radius: var(--r-sm);
}
.otp-inputs input:focus{
    border-color: var(--primary, #0B4E3D);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #0B4E3D) 16%, transparent);
}

/* Email field (shown when the request arrives without an address) */
.field-float input{ font-size: 1rem; border-radius: var(--r-sm); }
.field-float input:focus{
    border-color: var(--primary, #0B4E3D);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #0B4E3D) 16%, transparent);
}
.field-float input:focus ~ label,
.field-float input:not(:placeholder-shown) ~ label{ color: var(--primary, #0B4E3D); }

/* Resend row */
.auth-box form.text-center .text-muted{ display: block; margin-bottom: .35rem; }

.auth-back{
    display: inline-flex; align-items: center; gap: .4rem; min-height: 44px;
    font-size: .9rem; font-weight: 600; color: var(--muted, #4B6754);
}
.auth-back:hover{ color: var(--primary, #0B4E3D); }
.auth-back svg.lucide{ width: 15px; height: 15px; }
.alert{ border-radius: var(--r-sm); }

@media (max-width: 540px){ .auth-box{ max-width: 100%; } }
@media (max-width: 400px){
    .otp-inputs{ display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; }
    .otp-inputs input{ max-width: none; width: 100%; height: 52px; }
}
@media (max-width: 380px){
    .auth-shell{ padding: 1rem .7rem; }
    .auth-box .card{ padding: 1.3rem 1.05rem; }
}
</style>
</head>
<body>
<div class="auth-shell">

    <!-- ============================== VERIFY PANEL ============================== -->
    <main class="auth-main">
        <div class="auth-box">
            <div class="card">

                <div class="section-head is-centered">
                    <span class="icon-badge"><?= lucide('shield') ?></span>
                    <span class="eyebrow">Email Verification</span>
                    <h2 class="section-title">Verify Your Account</h2>
                    <?php if ($validEmail): ?>
                        <p class="section-subtitle">
                            Enter the 6-digit code sent to<br>
                            <strong><?= e($email) ?></strong>
                        </p>
                    <?php else: ?>
                        <p class="section-subtitle">Tell us your email and we'll send you a fresh verification code.</p>
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

                        <button class="btn btn-secondary btn-block btn-lg" type="submit">Verify &amp; Continue</button>
                    </form>

                    <!-- ---------- Resend (separate POST) ---------- -->
                    <form method="post" class="text-center mt-3" style="margin-bottom:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="resend">
                        <input type="hidden" name="email" value="<?= e($email) ?>">
                        <span class="text-muted" style="font-size:.92rem;">Didn't receive it? Check spam, or</span>
                        <button type="submit" class="btn btn-outline btn-sm">Resend code</button>
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

                        <button class="btn btn-secondary btn-block btn-lg" type="submit">Send Code</button>
                    </form>

                <?php endif; ?>

                <div class="divider mt-3"></div>

                <p class="text-center" style="margin-bottom:0;">
                    Already verified? <a class="link-underline" href="<?= e(url('login')) ?>" style="font-weight:700;">Back to Sign In</a>
                </p>
                <p class="text-center mt-1" style="margin-bottom:0;">
                    Need a new account? <a class="link-underline" href="<?= e(url('signup')) ?>" style="font-weight:700;">Create one</a>
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
})();
</script>
</body>
</html>
