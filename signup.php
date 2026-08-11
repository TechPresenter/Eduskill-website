<?php
/**
 * =============================================================================
 *  signup.php — UNIFIED registration (STANDALONE auth shell, no header/footer).
 * =============================================================================
 *  One form for every self-registerable role: student, volunteer, member, donor
 *  and school. There is no second signup page and no per-role variant.
 *
 *  Flow:
 *    - Already signed in?  → /account
 *    - POST: require_csrf(), then member_register_unified($_POST).
 *      · Policy allows a ready account  → seat the session, land on the role's
 *        destination (auth_dashboard_for()).
 *      · Otherwise                      → success card explaining exactly what
 *        happens next (verify the address / wait for approval / both).
 *
 *  THE ROLE LIST AND THE FIELD LIST ARE BOTH DERIVED, not hardcoded here:
 *    auth_self_registerable_roles()  (includes/auth_router.php) — the allowlist
 *    reg_profile_fields($role)       (includes/member_auth.php) — what to ask
 *  so the form can never offer a role the server refuses, nor collect a field
 *  the server ignores. The server re-validates everything regardless: this page
 *  is the presentation of the rules, never the enforcement of them.
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
$doneResult  = [];

/* -----------------------------------------------------------------------------
 | The account types on offer, and the union of fields they need.
 | -----------------------------------------------------------------------------
 | Built from the server-side allowlist + per-role field specs, so this screen
 | cannot drift from what member_register_unified() will accept. Columns shared
 | by several roles are rendered ONCE (a second <input name="city"> would make
 | the last one in the DOM win the POST) and shown/hidden per role.
 */
$selfRoles = auth_self_registerable_roles();

$unionLabels = [
    'dob'        => 'Date of birth',
    'gender'     => 'Gender',
    'occupation' => 'Occupation / Designation',
    'address'    => 'Address',
    'city'       => 'City / Town',
    'state'      => 'State',
    'pincode'    => 'PIN code',
];

$fieldSpec  = [];   // col => spec (type/max, from the first role that asks)
$fieldRoles = [];   // col => [roles that ask for it]
$fieldReq   = [];   // col => [roles that require it]
$fieldHints = [];   // col => [role => hint]
foreach ($selfRoles as $r) {
    foreach (reg_profile_fields($r) as $col => $spec) {
        $fieldSpec[$col] ??= $spec;
        $fieldRoles[$col][] = $r;
        if (!empty($spec['required'])) { $fieldReq[$col][] = $r; }
        if (!empty($spec['hint']))     { $fieldHints[$col][$r] = $spec['hint']; }
    }
}
$profileCols = array_keys($fieldSpec);

/* Repopulate on validation errors (never echo without e()).
   $rolePosted is what arrived; $vRole is the sanitised value used to re-check a
   radio. They are kept apart on purpose — see the POST handler below. */
$rolePosted = strtolower(clean(post('role')));
$vRole      = in_array($rolePosted, $selfRoles, true) ? $rolePosted : '';
$vName  = clean(post('name'));
$vEmail = strtolower(clean(post('email')));
$vPhone = clean(post('phone'));
$vProf  = [];
foreach ($profileCols as $col) { $vProf[$col] = clean(post($col)); }

if (is_post()) {
    require_csrf();

    if (!post('terms')) {
        $error = 'Please accept the Terms & Conditions and Privacy Policy to continue.';
    } elseif ($rolePosted === '') {
        // Nothing was chosen — an unselected radio, not an attack.
        $error = 'Please choose the type of account you want to create.';
    } else {
        /* NOTE: the raw $_POST goes to the model even when the posted role is not
           on the allowlist. It must: rejecting a forged role HERE would refuse it
           silently, and the refusal-plus-audit-trail lives in
           member_register_unified() — which is also the only path a direct POST
           that never loads this page can take. Filtering here first would have
           meant a posted 'staff' was blocked but never logged. */
        $r = member_register_unified($_POST);

        if (!empty($r['success'])) {
            /* -------------------------------------------------------------
               Policy path A — the account is verified AND active, so seat the
               session and take them to their destination. Redirect rather than
               render: the session id has just been regenerated, and a POST
               response that holds a live session is one refresh away from a
               duplicate submission.
               ------------------------------------------------------------- */
            if (!empty($r['log_in_now']) && !empty($r['member_id'])) {
                $row = find('members', (int) $r['member_id']);
                if ($row) {
                    member_set_session($row);
                    db_update('members',
                        ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => client_ip()],
                        'id = :id', [':id' => (int) $row['id']]);
                    record_login_attempt($vEmail, true);
                    set_flash('success', $r['message']);
                    redirect(auth_dashboard_for((string) $r['role'], 'members'));
                }
            }
            /* Policy path B — tell them precisely what still has to happen. */
            $success    = true;
            $doneEmail  = $vEmail;
            $doneResult = $r;
        } else {
            $error       = $r['message'] ?? 'We could not create your account. Please try again.';
            $fieldErrors = $r['errors'] ?? [];
        }
    }
}

/* Which step to open on: land the visitor on the step that actually failed. */
$startStep = 1;
if ($fieldErrors) {
    $keys = array_keys($fieldErrors);
    if (array_intersect($keys, $profileCols))                          { $startStep = 3; }
    if (array_intersect($keys, ['name', 'email', 'password', 'phone'])) { $startStep = 2; }
    if (in_array('role', $keys, true))                                  { $startStep = 1; }
} elseif ($error !== '' && $vRole !== '') {
    $startStep = 3;   // terms live on the last step
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
    <?php /* Same link order as includes/header.php: the design system loads LAST
             of the stylesheets so its scales and components win; the Theme Engine
             block follows because it only emits the custom properties it reads. */ ?>
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <?php echo function_exists('theme_style_tag') ? theme_style_tag('site') : ''; ?>
<style>
/* =======================================================================
   SIGNUP — page-scoped layer ON TOP of design-system.css.
   Every colour, radius, elevation and easing below is a token, so the screen
   follows the Theme Engine and the design-system scales rather than the
   hand-mixed teal / mustard values this file used to carry.
   ======================================================================= */
.su-shell { position: relative; overflow: hidden; padding: clamp(1.25rem, 4vw, 2.5rem) clamp(.85rem, 3vw, 1.25rem); }

/* Two brand washes behind the split card (was three animated blurs, one of
   them full-strength CTA orange). Static: nothing here needs to move. */
.su-bg { position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
.su-bg span { position: absolute; border-radius: 50%; filter: blur(70px); opacity: .5; }
.su-bg .o1 { width: 440px; height: 440px; top: -140px; left: -110px;
    background: color-mix(in srgb, var(--primary, #0B4E3D) 30%, transparent); }
.su-bg .o2 { width: 400px; height: 400px; bottom: -160px; right: -100px;
    background: color-mix(in srgb, var(--secondary, #174D3D) 26%, transparent); }
.su-bg .o3 { width: 320px; height: 320px; top: 42%; left: 56%;
    background: color-mix(in srgb, var(--yellow, #FFE987) 70%, transparent); opacity: .6; }

/* Split card */
.su-split {
    position: relative; z-index: 1; width: 100%; max-width: 1060px;
    display: grid; grid-template-columns: 1.04fr .96fr;
    border-radius: var(--r-xl); overflow: hidden;
    box-shadow: var(--elev-5);
    border: 1px solid var(--border, #C1CCB3);
    animation: suRise .7s var(--ease-out, cubic-bezier(.22,1,.36,1)) both;
}
@keyframes suRise { from { opacity: 0; transform: translateY(26px); } to { opacity: 1; transform: none; } }

/* -------- Brand / marketing aside -------- */
/* Flat brand field. It was a three-stop gradient whose first stop (#0b3d24)
   is not in the palette. */
.su-aside {
    position: relative; overflow: hidden; padding: clamp(2.2rem, 4vw, 3.1rem) clamp(1.9rem, 3vw, 2.7rem);
    color: #fff; background: var(--primary, #0B4E3D);
    display: flex; flex-direction: column;
}
.su-aside::after {
    content: ""; position: absolute; inset: 0;
    background:
        radial-gradient(560px circle at 12% 8%, rgba(255,255,255,.12), transparent 46%),
        radial-gradient(520px circle at 92% 96%,
            color-mix(in srgb, var(--yellow, #FFE987) 18%, transparent), transparent 44%);
    pointer-events: none;
}
.su-aside > * { position: relative; z-index: 1; }
.su-brand { display: inline-flex; align-items: center; gap: .65rem; font-weight: 800; font-size: 1.12rem; color: #fff; text-decoration: none; margin-bottom: 2.2rem; }
.su-brand img { width: 46px; height: 46px; object-fit: contain; border-radius: var(--r-sm); background: rgba(255,255,255,.16); padding: 6px; }
/* Highlight yellow on the green field is 7.95:1 (the old #f4d47a was an
   off-palette mustard). */
.su-eyebrow { display: inline-flex; align-items: center; gap: .45rem; font-size: .72rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--yellow, #FFE987); margin-bottom: .7rem; }
.su-eyebrow svg.lucide { width: 15px; height: 15px; }
.su-aside h1 { font-size: clamp(1.65rem, 3.4vw, 2.05rem); line-height: 1.14; margin: 0 0 .8rem; color: #fff; letter-spacing: -.01em; }
.su-aside .lead { color: rgba(255,255,255,.86); font-size: 1.02rem; line-height: 1.6; margin: 0; }
.su-points { list-style: none; margin: 1.8rem 0 0; padding: 0; display: grid; gap: 1rem; }
.su-points li { display: flex; align-items: flex-start; gap: .8rem; color: rgba(255,255,255,.94); font-weight: 500; line-height: 1.45; }
.su-points .tick { width: 32px; height: 32px; border-radius: var(--r-sm); display: grid; place-items: center; background: rgba(255,255,255,.17); border: 1px solid rgba(255,255,255,.22); color: #fff; flex-shrink: 0; }
.su-points .tick svg.lucide { width: 16px; height: 16px; }
.su-stats { display: flex; gap: 2.4rem; margin-top: auto; padding-top: 1.9rem; border-top: 1px solid rgba(255,255,255,.22); }
.su-stats .v { font-size: 1.8rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
.su-stats small { display: block; margin-top: .3rem; color: rgba(255,255,255,.84); font-size: .8rem; }

/* -------- Form column -------- */
.su-form-col { background: var(--surface, #fff); padding: clamp(1.9rem, 3vw, 2.9rem) clamp(1.4rem, 2.6vw, 2.6rem); display: flex; flex-direction: column; justify-content: center; }
.su-form-col .auth-box { max-width: none; width: 100%; }
.su-form-col .auth-logo { display: none; margin-bottom: 1.3rem; }
.su-form-col .auth-logo span { color: var(--text, #151818); }
.su-form-col .auth-logo small { color: var(--muted, #4B6754); }
/* Flatten the inner .card — the whole column is already a surface. */
.su-form-col .card { background: transparent; border: 0; box-shadow: none; padding: 0; }
.su-form-col .card:hover { transform: none; box-shadow: none; border-color: transparent; }

/* Heading block uses the design-system .section-head / .section-title pair.
   tailwind.css centres every .section-head; this one is left-aligned. */
.su-form-col .section-head { margin-bottom: 1.5rem; max-width: none; text-align: left; }
.su-form-col .section-title { font-size: clamp(1.5rem, 4.6vw, 1.85rem); margin: .2rem 0 .35rem; letter-spacing: -.01em; }
.su-form-col .section-subtitle { font-size: .95rem; }
.su-form-col .eyebrow svg.lucide { width: 14px; height: 14px; }

/* Fields: 16px text (smaller makes iOS zoom on focus) + token focus ring. */
.field-float input { font-size: 1rem; border-radius: var(--r-sm); }
.field-float input:hover:not(:focus) { border-color: color-mix(in srgb, var(--primary, #0B4E3D) 45%, transparent); }
.field-float input:focus { border-color: var(--primary, #0B4E3D);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #0B4E3D) 16%, transparent); }
.field-float input:focus ~ label,
.field-float input:not(:placeholder-shown) ~ label { color: var(--primary, #0B4E3D); }

/* Password visibility toggle: 20px mark inside a 44px touch target. */
#password, #password_confirm { padding-right: 3.25rem; }
.field-float .toggle-pass {
    display: inline-flex; align-items: center; justify-content: center;
    width: 44px; height: 44px; min-width: 44px;
    right: .35rem; top: 50%; transform: translateY(-50%);
    border-radius: var(--r-sm); color: var(--muted, #4B6754);
    transition: color var(--dur, .3s) var(--ease-out, ease), background-color var(--dur, .3s) var(--ease-out, ease);
}
.field-float .toggle-pass:hover { color: var(--primary, #0B4E3D);
    background: color-mix(in srgb, var(--primary, #0B4E3D) 9%, transparent); }
.field-float .toggle-pass svg.lucide { width: 20px; height: 20px; }

/* Password strength meta row + live label */
.pw-strength { transition: none; border-radius: var(--r-pill); }
.pw-meta { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: .55rem 0 1rem; }
.pw-meta .form-hint { margin: 0; }
.pw-label { font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--muted, #4B6754); white-space: nowrap; transition: color var(--dur, .3s) var(--ease-out, ease); }
.pw-label.weak { color: var(--danger, #DC2626); }
/* --warning resolves to the CTA orange, which is 3.37:1 on white — below AA
   for this .7rem uppercase label. --accent-ink is the same orange one step
   deeper: 4.60:1. The meter fill follows it so bar and label agree. */
.pw-label.medium { color: var(--accent-ink, #CA4C1E); }
.pw-strength.medium span { background: var(--accent-ink, #CA4C1E); }
.pw-label.strong { color: var(--success, #2F8065); }

/* Social brand buttons */
.brand-svg { flex-shrink: 0; }
.social-auth .btn-social {
    min-height: 48px; border-radius: var(--r-pill); font-weight: 700;
    background: var(--surface, #fff); color: var(--text, #151818);
    border: 1.5px solid var(--border, #C1CCB3);
}
/* premium.css turns the label --brand-600 on hover; on white that is fine at
   9.67:1, so only the elevation token is corrected here. */
.social-auth .btn-social:hover { box-shadow: var(--elev-2); color: var(--primary, #0B4E3D); }

/* Terms row: the whole label is a 44px target on touch. */
.checkbox { align-items: center; gap: .65rem; min-height: 44px; padding-block: .35rem; cursor: pointer; }
.checkbox input { width: 1.15rem; height: 1.15rem; margin-top: 0; flex: none; accent-color: var(--primary, #0B4E3D); }
.checkbox a { color: var(--primary, #0B4E3D); font-weight: 700; }

/* Terms + secure note */
.su-secure { display: flex; align-items: center; justify-content: center; gap: .4rem; margin: 1rem 0 0; font-size: .8rem; color: var(--muted, #4B6754); }
.su-secure svg.lucide { width: 15px; height: 15px; color: var(--primary, #0B4E3D); }

/* Success view badge — flat highlight yellow with brand-green ink (7.95:1),
   in place of the green two-stop gradient. */
.su-form-col .icon-box { padding: 0; }
.su-form-col .icon-box .ib-icon {
    width: 64px; height: 64px; border-radius: var(--r-md);
    background: var(--yellow, #FFE987); color: var(--primary, #0B4E3D);
    box-shadow: none;
}
.su-form-col .icon-box:hover .ib-icon { transform: none; }
.su-form-col .icon-box .ib-icon svg.lucide { width: 30px; height: 30px; }

/* -------------------- Country / mobile field --------------------
 * country_field() brings its own control, so it needs matching to the
 * .field-float inputs around it: same width, height, radius and focus ring. */
.signup-country { margin-bottom: 1rem; }
.signup-country .cs-label {
    display: block; font-size: .85rem; font-weight: 600;
    color: var(--text, #151818); margin-bottom: .45rem;
}
.signup-country .cs-control {
    width: 100%; border-radius: var(--r-sm); border-width: 1.5px;
    background: var(--surface, #fff);
}
.signup-country .cs-toggle { border-radius: var(--r-sm) 0 0 var(--r-sm); padding-inline: .85rem; min-height: 44px; flex: none; }
.signup-country .cs-phone  { padding-block: .95rem; font-size: 1rem; }
/* .cs-control is a flex row (dial-code toggle + tel input). Both are flex items
   with the default min-width:auto, so neither could shrink below its intrinsic
   width — the pair floored the form column's min-content at 371px and, because
   .su-form-col is a grid item, the whole card grew past a 320px screen and was
   clipped by .su-shell's overflow:hidden. min-width:0 lets them shrink. */
.su-form-col, .su-form-col .auth-box { min-width: 0; }
.signup-country .cs-control { min-width: 0; }
.signup-country .cs-phone { min-width: 0; flex: 1 1 0; }
.signup-country .cs-control:focus-within {
    border-color: var(--primary, #0B4E3D);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #0B4E3D) 16%, transparent);
}
/* The no-JS <select> fallback must stay visually hidden once the script runs. */
.signup-country .cs-native { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }

/* =======================================================================
   STEPPER — three steps: account type → your details → a little more.
   PROGRESSIVE ENHANCEMENT: with no JS every step is visible and the form is
   one ordinary long page that still submits and still validates server-side.
   .su-steps only becomes one-panel-at-a-time once the script adds .is-live.
   ======================================================================= */
.su-progress { display: flex; align-items: center; gap: .5rem; margin: 0 0 1.4rem; }
.su-progress li { display: flex; align-items: center; gap: .5rem; flex: 1; list-style: none; min-width: 0; }
.su-progress .dot {
    width: 26px; height: 26px; flex: none; border-radius: 50%; display: grid; place-items: center;
    font-size: .78rem; font-weight: 800; font-variant-numeric: tabular-nums;
    background: color-mix(in srgb, var(--primary, #0B4E3D) 12%, transparent);
    color: var(--primary, #0B4E3D);
    border: 1.5px solid transparent;
}
.su-progress .lbl { font-size: .78rem; font-weight: 700; color: var(--muted, #4B6754); white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; }
.su-progress li[aria-current="step"] .dot { background: var(--primary, #0B4E3D); color: #fff; }
.su-progress li[aria-current="step"] .lbl { color: var(--text, #151818); }
.su-progress li.is-done .dot { background: var(--success, #2F8065); color: #fff; }
.su-progress .bar { flex: 1; height: 2px; background: var(--border, #C1CCB3); border-radius: 2px; min-width: 8px; }
@media (max-width: 560px) { .su-progress .lbl { display: none; } }

.su-steps.is-live .su-step { display: none; }
.su-steps.is-live .su-step.is-active { display: block; animation: suFade .32s var(--ease-out, ease) both; }
@keyframes suFade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.su-step > h3 { font-size: 1.02rem; margin: 0 0 .2rem; }
.su-step > .step-sub { font-size: .88rem; color: var(--muted, #4B6754); margin: 0 0 1.1rem; }
/* Without JS the steps stack, so they need visible separation. */
.su-steps:not(.is-live) .su-step + .su-step {
    margin-top: 1.6rem; padding-top: 1.4rem; border-top: 1px solid var(--border, #C1CCB3);
}
.su-nav { display: flex; gap: .7rem; margin-top: 1.2rem; }
.su-nav .btn { flex: 1; }
.su-steps:not(.is-live) .su-nav { display: none; }

/* -------------------- Account-type cards -------------------- */
.su-roles { display: grid; gap: .7rem; margin: 0 0 .4rem; }
@media (min-width: 620px) { .su-roles { grid-template-columns: 1fr 1fr; } }
.su-role { position: relative; display: block; cursor: pointer; }
.su-role input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.su-role .body {
    display: flex; gap: .75rem; align-items: flex-start;
    padding: .85rem .9rem; min-height: 44px;
    border: 1.5px solid var(--border, #C1CCB3); border-radius: var(--r-md);
    background: var(--surface, #fff);
    transition: border-color var(--dur, .3s) var(--ease-out, ease),
                box-shadow var(--dur, .3s) var(--ease-out, ease),
                background-color var(--dur, .3s) var(--ease-out, ease);
}
.su-role:hover .body { border-color: color-mix(in srgb, var(--primary, #0B4E3D) 45%, transparent); }
.su-role input:checked ~ .body {
    border-color: var(--primary, #0B4E3D);
    background: color-mix(in srgb, var(--primary, #0B4E3D) 5%, transparent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #0B4E3D) 14%, transparent);
}
/* Keyboard focus must be visible even though the input itself is hidden. */
.su-role input:focus-visible ~ .body { outline: 2px solid var(--primary, #0B4E3D); outline-offset: 2px; }
.su-role .ico {
    width: 36px; height: 36px; flex: none; border-radius: var(--r-sm); display: grid; place-items: center;
    background: var(--yellow, #FFE987); color: var(--primary, #0B4E3D);
}
.su-role .ico svg.lucide { width: 18px; height: 18px; }
.su-role .txt { min-width: 0; }
.su-role .nm { display: block; font-weight: 700; color: var(--text, #151818); line-height: 1.3; }
.su-role .bl { display: block; font-size: .82rem; color: var(--muted, #4B6754); line-height: 1.42; margin-top: .15rem; }
.su-role .tag {
    display: inline-block; margin-left: .4rem; padding: .05rem .4rem; border-radius: var(--r-pill);
    font-size: .64rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
    background: color-mix(in srgb, var(--primary, #0B4E3D) 12%, transparent); color: var(--primary, #0B4E3D);
    vertical-align: middle;
}
/* Roles a human has to approve say so BEFORE the person fills the form in. */
.su-role .review {
    display: inline-flex; align-items: center; gap: .25rem; margin-top: .35rem;
    font-size: .74rem; font-weight: 700; color: var(--accent-ink, #CA4C1E);
}
.su-role .review svg.lucide { width: 13px; height: 13px; }

/* -------------------- Role-scoped profile fields -------------------- */
.su-prof [data-for-roles] { display: block; }
.su-prof.is-live [data-for-roles] { display: none; }
.su-prof.is-live [data-for-roles].is-on { display: block; }
.su-field { margin-bottom: 1rem; }
.su-field > label.form-label { display: block; font-size: .85rem; font-weight: 600; color: var(--text, #151818); margin-bottom: .45rem; }
.su-field .form-control, .su-field select {
    width: 100%; font-size: 1rem; min-height: 46px; padding: .7rem .85rem;
    border: 1.5px solid var(--border, #C1CCB3); border-radius: var(--r-sm);
    background: var(--surface, #fff); color: var(--text, #151818);
}
.su-field .form-control:focus, .su-field select:focus {
    outline: none; border-color: var(--primary, #0B4E3D);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #0B4E3D) 16%, transparent);
}
.su-field .form-hint { display: block; margin-top: .3rem; font-size: .78rem; color: var(--muted, #4B6754); }
.su-field .req { color: var(--danger, #DC2626); }
/* Short fields pair up on anything wider than a small phone; address-style
   fields keep the full width. Grid items stay display:block, so the show/hide
   rules above are unaffected. */
@media (min-width: 520px) {
    .su-prof { display: grid; grid-template-columns: 1fr 1fr; gap: 0 .8rem; }
    .su-prof > .su-field { grid-column: span 2; }
    .su-prof > .su-field.su-half { grid-column: span 1; }
}

/* Success view: what happens next, as an ordered list rather than a paragraph. */
.su-next { text-align: left; margin: 1.2rem 0 0; padding: 0; list-style: none; display: grid; gap: .7rem; }
.su-next li { display: flex; gap: .6rem; align-items: flex-start; font-size: .92rem; line-height: 1.5; }
.su-next .n {
    width: 22px; height: 22px; flex: none; border-radius: 50%; display: grid; place-items: center;
    font-size: .72rem; font-weight: 800;
    background: color-mix(in srgb, var(--primary, #0B4E3D) 12%, transparent); color: var(--primary, #0B4E3D);
}

/* -------------------- Responsive -------------------- */
@media (max-width: 980px) {
    .su-split { grid-template-columns: 1fr; max-width: 560px; }
    .su-brand { margin-bottom: 1.4rem; }
    .su-points { display: none; }
    .su-stats { margin-top: 1.4rem; padding-top: 1.4rem; }
}
@media (max-width: 820px) {
    /* Blurred washes off on a phone — see design system §13. */
    .su-bg { display: none; }
}
@media (max-width: 560px) {
    .su-aside { display: none; }
    .su-split { border-radius: var(--r-lg); box-shadow: var(--elev-4); }
    .su-form-col .auth-logo { display: flex; }
    .social-auth { grid-template-columns: 1fr; }
}
@media (max-width: 380px) {
    /* 320px: keep the form's own gutters, not the desktop column padding. */
    .su-shell { padding: 1rem .7rem; }
    .su-form-col { padding: 1.5rem 1.05rem; }
    .su-form-col .auth-logo img { width: 44px; height: 44px; }
    .pw-meta { flex-direction: column; align-items: flex-start; gap: .3rem; }
}
@media (prefers-reduced-motion: reduce) {
    .su-split { animation: none; }
    .su-steps.is-live .su-step.is-active { animation: none; }
    .social-auth .btn-social:hover { transform: none; }
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

            <span class="su-eyebrow"><?= lucide('sparkles') ?> One account, every portal</span>
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

            <?php if ($success):
                $needsVerify  = !empty($doneResult['needs_verification']);
                $needsApprove = !empty($doneResult['needs_approval']);
                $roleName     = reg_role_copy((string) ($doneResult['role'] ?? 'member'))['name'];
            ?>
                <!-- -------------------------------------------------- Success view -->
                <div class="card text-center reveal">
                    <div class="icon-box">
                        <span class="ib-icon"><?= lucide('check') ?></span>
                    </div>
                    <h2 class="section-title">Account created</h2>
                    <p class="text-muted mt-1">
                        Your <strong><?= e(strtolower($roleName)) ?></strong> account for
                        <strong><?= e($doneEmail) ?></strong> is set up.
                    </p>

                    <?php /* What still has to happen, in order — no guessing. */ ?>
                    <ol class="su-next">
                        <?php $n = 0; ?>
                        <?php if ($needsVerify): $n++; ?>
                            <li><span class="n"><?= $n ?></span><span>
                                Open the email we just sent and click <strong>Verify Email</strong>.
                                The link is valid for 24 hours (check your spam folder too).
                            </span></li>
                        <?php else: $n++; ?>
                            <li><span class="n"><?= $n ?></span><span>
                                We have emailed <strong><?= e($doneEmail) ?></strong> to confirm the account was created.
                                If that was not you, reset the password from that email straight away.
                            </span></li>
                        <?php endif; ?>
                        <?php if ($needsApprove): $n++; ?>
                            <li><span class="n"><?= $n ?></span><span>
                                Our team reviews <?= e(strtolower($roleName)) ?> accounts before they open.
                                We will email you the moment yours is approved.
                            </span></li>
                        <?php endif; ?>
                        <?php $n++; ?>
                        <li><span class="n"><?= $n ?></span><span>Sign in and you will land straight on your dashboard.</span></li>
                    </ol>

                    <a class="btn btn-secondary btn-block btn-lg mt-3" href="<?= e(url('login')) ?>">
                        <?= lucide('log-in') ?> Go to Sign In
                    </a>
                    <?php if ($needsVerify): ?>
                    <p class="text-center mt-3">
                        Didn't get the email?
                        <a class="link-underline" href="<?= e($verifyUrl) ?>">Verify with a code instead</a>
                        &nbsp;·&nbsp;
                        <a class="link-underline" href="<?= e(url('signup')) ?>">Start over</a>
                    </p>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- ----------------------------------------------------- Form view -->
                <div class="card reveal">
                    <div class="section-head">
                        <span class="eyebrow is-plain"><?= lucide('user-plus') ?> Join Us</span>
                        <h2 class="section-title">Create your account</h2>
                        <p class="section-subtitle">One registration for every EduSkill India portal.</p>
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

                    <ol class="su-progress" data-su-progress hidden>
                        <li data-step-pill="1"><span class="dot">1</span><span class="lbl">Account type</span></li>
                        <span class="bar" aria-hidden="true"></span>
                        <li data-step-pill="2"><span class="dot">2</span><span class="lbl">Your details</span></li>
                        <span class="bar" aria-hidden="true"></span>
                        <li data-step-pill="3"><span class="dot">3</span><span class="lbl">Finish</span></li>
                    </ol>

                    <form method="post" id="signupForm" data-start-step="<?= (int) $startStep ?>">
                        <?= csrf_field() ?>

                        <div class="su-steps" data-su-steps>

                            <!-- ============================ STEP 1 — account type -->
                            <section class="su-step" data-step="1" aria-labelledby="su-h1">
                                <h3 id="su-h1">Who are you joining as?</h3>
                                <p class="step-sub">This decides which dashboard you land on and what we ask you for.</p>

                                <div class="su-roles" role="radiogroup" aria-labelledby="su-h1">
                                    <?php foreach ($selfRoles as $slug):
                                        $copy   = reg_role_copy($slug);
                                        $cfg    = auth_roles()[$slug] ?? [];
                                        $review = auth_role_requires_admin_approval($slug);
                                    ?>
                                    <label class="su-role">
                                        <input type="radio" name="role" value="<?= e($slug) ?>"
                                               data-role-radio
                                               <?= $vRole === $slug ? 'checked' : '' ?> required>
                                        <span class="body">
                                            <span class="ico" aria-hidden="true"><?= lucide($cfg['icon'] ?? 'user') ?></span>
                                            <span class="txt">
                                                <span class="nm"><?= e($copy['name']) ?><?php if ($copy['tag'] !== ''): ?><span class="tag"><?= e($copy['tag']) ?></span><?php endif; ?></span>
                                                <span class="bl"><?= e($copy['blurb']) ?></span>
                                                <?php if ($review): ?>
                                                    <span class="review"><?= lucide('shield-check') ?> Reviewed by our team before it opens</span>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>

                                <p class="form-hint" style="margin-top:.6rem;">
                                    Staff, teacher and administrator accounts are created by the foundation — they are not available here.
                                </p>

                                <div class="su-nav">
                                    <button type="button" class="btn btn-secondary btn-lg" data-su-next>Continue <?= lucide('arrow-right') ?></button>
                                </div>
                            </section>

                            <!-- ============================ STEP 2 — your details -->
                            <section class="su-step" data-step="2" aria-labelledby="su-h2">
                                <h3 id="su-h2">Your details</h3>
                                <p class="step-sub" data-name-sub>How we address you, and how you sign in.</p>

                                <div class="field-float">
                                    <input type="text" id="name" name="name" placeholder=" " maxlength="128"
                                           value="<?= e($vName) ?>" autocomplete="name" required>
                                    <label for="name" data-name-label>Full Name</label>
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
                                    <?= country_field(['name' => 'phone', 'label' => 'Mobile Number (optional)', 'value' => $vPhone]) ?>
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

                                <div class="su-nav">
                                    <button type="button" class="btn btn-outline btn-lg" data-su-prev><?= lucide('arrow-left') ?> Back</button>
                                    <button type="button" class="btn btn-secondary btn-lg" data-su-next>Continue <?= lucide('arrow-right') ?></button>
                                </div>
                            </section>

                            <!-- ============================ STEP 3 — role profile -->
                            <section class="su-step" data-step="3" aria-labelledby="su-h3">
                                <h3 id="su-h3">Almost done</h3>
                                <p class="step-sub">Only what your account type actually needs.</p>

                                <div class="su-prof" data-su-prof>
                                <?php
                                /* One control per column, tagged with the roles that ask for it.
                                   Rendered from reg_profile_fields() so this list cannot drift
                                   from what the server reads. */
                                $genders = ['' => 'Prefer not to say', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'];
                                foreach ($profileCols as $col):
                                    $spec  = $fieldSpec[$col];
                                    $label = $unionLabels[$col] ?? $spec['label'];
                                    $roles = implode(' ', $fieldRoles[$col]);
                                    $reqIn = implode(' ', $fieldReq[$col] ?? []);
                                    $wrapCls = in_array($col, ['city', 'state', 'dob', 'gender', 'pincode'], true) ? ' su-half' : '';
                                ?>
                                    <div class="su-field<?= $wrapCls ?>" data-for-roles="<?= e($roles) ?>" data-required-for="<?= e($reqIn) ?>">
                                        <label class="form-label" for="f_<?= e($col) ?>">
                                            <?= e($label) ?><span class="req" data-req-mark hidden> *</span>
                                        </label>
                                        <?php if ($spec['type'] === 'gender'): ?>
                                            <select class="form-control" id="f_<?= e($col) ?>" name="<?= e($col) ?>">
                                                <?php foreach ($genders as $gk => $gl): ?>
                                                    <option value="<?= e($gk) ?>" <?= $vProf[$col] === $gk ? 'selected' : '' ?>><?= e($gl) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($spec['type'] === 'date'): ?>
                                            <input class="form-control" type="date" id="f_<?= e($col) ?>" name="<?= e($col) ?>"
                                                   max="<?= e(date('Y-m-d')) ?>" value="<?= e($vProf[$col]) ?>">
                                        <?php else: ?>
                                            <input class="form-control" type="text" id="f_<?= e($col) ?>" name="<?= e($col) ?>"
                                                   maxlength="<?= (int) ($spec['max'] ?? 191) ?>" value="<?= e($vProf[$col]) ?>">
                                        <?php endif; ?>
                                        <?php foreach (($fieldHints[$col] ?? []) as $hRole => $hint): ?>
                                            <small class="form-hint" data-hint-role="<?= e($hRole) ?>" hidden><?= e($hint) ?></small>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                                </div>

                                <?php /* Shown by JS for roles whose accounts a human must approve —
                                         set before submission, not discovered afterwards. */ ?>
                                <div class="alert alert-info" data-su-review hidden>
                                    <?= lucide('info') ?>
                                    <span>Accounts of this type are reviewed by our team before they open. You will
                                    get an email as soon as yours is approved.</span>
                                </div>

                                <label class="checkbox mb-3">
                                    <input type="checkbox" name="terms" id="terms" value="1" required>
                                    <span>I agree to the
                                        <a href="<?= e(url('terms')) ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>
                                        and
                                        <a href="<?= e(url('privacy-policy')) ?>" target="_blank" rel="noopener">Privacy Policy</a>.
                                    </span>
                                </label>

                                <div class="su-nav" style="margin-bottom:.2rem;">
                                    <button type="button" class="btn btn-outline btn-lg" data-su-prev><?= lucide('arrow-left') ?> Back</button>
                                </div>

                                <button class="btn btn-secondary btn-block btn-lg" type="submit"><?= lucide('user-plus') ?> Create Account</button>
                                <p class="su-secure"><?= lucide('lock') ?> Your information is encrypted and never shared.</p>
                            </section>
                        </div>
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
                        <a class="link-underline" href="<?= e(url('login')) ?>" style="font-weight:700;">Sign in</a>
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

    var form = document.getElementById('signupForm');
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

    /* =====================================================================
       ROLE-SCOPED FIELDS
       ---------------------------------------------------------------------
       Every profile control is in the DOM for every role; this shows the ones
       the chosen role asks for and applies `required` only to those. Fields
       that stay hidden are still submitted (empty) and the server ignores any
       column outside the chosen role's spec, so hiding is purely a UX concern —
       nothing here is a validation boundary.
       ===================================================================== */
    var prof    = form.querySelector('[data-su-prof]');
    var radios  = Array.prototype.slice.call(form.querySelectorAll('[data-role-radio]'));
    var review  = form.querySelector('[data-su-review]');
    var nameLbl = form.querySelector('[data-name-label]');
    var nameSub = form.querySelector('[data-name-sub]');
    var reviewRoles = <?php
        $reviewRoles = [];
        foreach ($selfRoles as $slug) {
            if (auth_role_requires_admin_approval($slug)) { $reviewRoles[] = $slug; }
        }
        echo json_encode($reviewRoles, JSON_THROW_ON_ERROR); ?>;
    /* A school registers as an institution, so the name field is the institution's. */
    var nameCopy = { school: ['Institution / School name', 'Tell us about the institution and who is registering it.'] };

    function currentRole() {
        for (var i = 0; i < radios.length; i++) { if (radios[i].checked) return radios[i].value; }
        return '';
    }

    function applyRole() {
        var role = currentRole();
        if (prof) { prof.classList.add('is-live'); }

        form.querySelectorAll('[data-for-roles]').forEach(function (wrap) {
            var forRoles = (wrap.getAttribute('data-for-roles') || '').split(' ');
            var reqRoles = (wrap.getAttribute('data-required-for') || '').split(' ');
            var on       = role !== '' && forRoles.indexOf(role) !== -1;
            var required = on && reqRoles.indexOf(role) !== -1;

            wrap.classList.toggle('is-on', on);
            var mark = wrap.querySelector('[data-req-mark]');
            if (mark) { mark.hidden = !required; }

            wrap.querySelectorAll('input,select').forEach(function (el) {
                /* Only a VISIBLE control may be required: a hidden required field
                   blocks submission with a validation message nobody can see. */
                if (required) { el.setAttribute('required', 'required'); }
                else { el.removeAttribute('required'); }
            });
            wrap.querySelectorAll('[data-hint-role]').forEach(function (h) {
                h.hidden = h.getAttribute('data-hint-role') !== role;
            });
        });

        if (review) { review.hidden = reviewRoles.indexOf(role) === -1; }
        if (nameLbl && nameSub) {
            var copy = nameCopy[role];
            nameLbl.textContent = copy ? copy[0] : 'Full Name';
            nameSub.textContent = copy ? copy[1] : 'How we address you, and how you sign in.';
        }
    }
    radios.forEach(function (r) {
        r.addEventListener('change', function () { applyRole(); goTo(2); });
    });

    /* =====================================================================
       STEPPER
       ---------------------------------------------------------------------
       Enhancement only: .is-live is what collapses the stacked steps into one
       panel at a time, and it is added here. With JS off the form stays a
       single scrolling page whose steps are separated by rules, every control
       reachable, and the server validates it exactly the same way.
       ===================================================================== */
    var steps    = form.querySelector('[data-su-steps]');
    var progress = document.querySelector('[data-su-progress]');
    var panels   = Array.prototype.slice.call(form.querySelectorAll('.su-step'));
    var current  = 1;

    function paint() {
        panels.forEach(function (p) {
            p.classList.toggle('is-active', Number(p.getAttribute('data-step')) === current);
        });
        if (progress) {
            progress.querySelectorAll('[data-step-pill]').forEach(function (pill) {
                var n = Number(pill.getAttribute('data-step-pill'));
                pill.classList.toggle('is-done', n < current);
                if (n === current) { pill.setAttribute('aria-current', 'step'); }
                else { pill.removeAttribute('aria-current'); }
            });
        }
    }

    function goTo(n) {
        current = Math.min(Math.max(n, 1), panels.length);
        paint();
        var active = panels[current - 1];
        if (active) {
            var focusable = active.querySelector('input:not([type=hidden]):not([disabled]),select,button[data-su-next]');
            if (focusable && current !== 1) { try { focusable.focus({ preventScroll: true }); } catch (e) {} }
            active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    /* Validate only the CURRENT panel before advancing, so a later step's
       empty required field cannot silently block the Continue button. */
    function panelValid(panel) {
        var fields = panel.querySelectorAll('input,select,textarea');
        for (var i = 0; i < fields.length; i++) {
            if (!fields[i].checkValidity()) {
                fields[i].reportValidity();
                return false;
            }
        }
        return true;
    }

    form.querySelectorAll('[data-su-next]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var panel = btn.closest('.su-step');
            if (Number(panel.getAttribute('data-step')) === 1 && currentRole() === '') {
                window.toast ? window.toast('error', 'Please choose an account type.')
                             : Swal.fire({ icon: 'warning', title: 'Please choose an account type.' });
                return;
            }
            refreshMatch();
            if (!panelValid(panel)) return;
            goTo(current + 1);
        });
    });
    form.querySelectorAll('[data-su-prev]').forEach(function (btn) {
        btn.addEventListener('click', function () { goTo(current - 1); });
    });

    form.addEventListener('submit', function (e) {
        if (currentRole() === '') {
            e.preventDefault();
            goTo(1);
            window.toast ? window.toast('error', 'Please choose an account type.')
                         : Swal.fire({ icon: 'warning', title: 'Please choose an account type.' });
            return;
        }
        if (pass.value !== confirm.value) {
            e.preventDefault();
            refreshMatch();
            goTo(2);
            confirm.focus();
            window.toast ? window.toast('error', 'Passwords do not match.')
                         : Swal.fire({ icon: 'error', title: 'Passwords do not match' });
            return;
        }
        if (!terms.checked) {
            e.preventDefault();
            goTo(3);
            window.toast ? window.toast('error', 'Please accept the Terms & Privacy Policy.')
                         : Swal.fire({ icon: 'warning', title: 'Please accept the Terms & Privacy Policy.' });
        }
    });

    /* Switch the stepper on, then open the step the server says failed. */
    if (steps) { steps.classList.add('is-live'); }
    if (progress) { progress.hidden = false; }
    applyRole();
    goTo(Number(form.getAttribute('data-start-step')) || 1);
    drawIcons();
})();
</script>
<?php /* This is a standalone auth page with no shared footer, so the country
         selector's data + behaviour script are not emitted automatically the
         way includes/footer.php does it for the rest of the site. Without this
         the control renders but the picker never opens. */ ?>
<?= country_field_assets() ?>
</body>
</html>
