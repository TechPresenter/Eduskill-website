# DEV CONTRACT — Addendum v2 (Premium UI + Member Auth + new entities)

Read this together with `DEV_CONTRACT.md`. Same rules apply (bootstrap first,
`e()` everything, prepared statements, `csrf_field()`/`require_csrf()`, DB-driven
with fallbacks, no placeholders/dead buttons, PHP 8.2-safe).

The premium layer (`assets/css/premium.css`, `assets/js/premium.js`) and the new
fonts/loader are already wired into `includes/header.php`/`footer.php`. Public
pages that `include header.php`/`footer.php` get all of it automatically — you do
NOT add `<link>`/`<script>` tags. **Auth pages are standalone** (see §4) and must
link the stylesheets themselves.

## 1. Premium CSS classes (use these — already styled)

- **Gradient text:** `text-grad-multi`, `text-grad-ocean`, `text-grad-purple`, `text-grad-sunset`
- **3D / gradient buttons:** `btn btn-3d` (+`.purple`/`.green`/`.sunset`), `btn btn-grad`, `btn btn-glow` (animated, wrap icon in `<span class="heart">`)
- **Cards/effects:** `glass-card`, `neu`, `tilt` (add `data-tilt="10"`), `icon-box`(+`.p`/`.g`/`.o`) with `.ib-icon`
- **Dividers/decor:** `wave-divider` (put an inline `<svg>` inside), `blob b1|b2|b3` (absolute decorative), `floating-shapes`>`.shape`
- **Marquee / logo slider:** `marquee`>`marquee-track` (JS auto-duplicates for seamless loop; `.reverse` variant), `logo-slide`, top bar `marquee-bar`
- **Timeline:** `timeline`>`timeline-item`(>`.tl-date`); **process:** `process-steps`>`process-step`
- **Tabs:** `tabs`>`tab-nav`>`tab-btn[data-tab="panelId"]` + `tab-panel#panelId` (first gets `.is-active`)
- **Accordion:** `<details class="accordion-item"><summary>Q</summary><div class="acc-body">A</div></details>`
- **Stats:** `stat-premium`; counters still use `<span data-counter="123">0</span>`
- **Skeleton:** `skeleton line|title|thumb`; **Toast:** `window.toast('success'|'error'|'info','msg')`
- **Mega menu** is already in the navbar. **FAB** stack already in the footer.
- **Before/After:** `ba-slider`>`img` + `.ba-after`>`img` + `.ba-handle`>`span`
- **Mouse parallax:** wrap in `[data-mouse-parallax]`, layers get `data-depth="20"`

## 2. Floating-label form fields (premium inputs)

```html
<div class="field-float">
  <input type="text" id="name" name="name" placeholder=" " required>
  <label for="name">Full Name</label>
</div>
```
IMPORTANT: the input MUST have `placeholder=" "` (a space) for the floating label to work.
Password field with strength + show/hide:
```html
<div class="field-float">
  <input type="password" id="password" name="password" placeholder=" " required
         data-password-strength="#pwMeter">
  <button type="button" class="toggle-pass" data-toggle-password="#password">👁️</button>
  <label for="password">Password</label>
</div>
<div class="pw-strength" id="pwMeter"><span></span></div>
```
Multi-step: wrap in `<form data-stepper>` with a `.stepper`>`.step`>`.dot`/`.label`, and
`.form-step`(first `.is-active`) sections; nav buttons `[data-next]` / `[data-prev]`.
OTP: `.otp-inputs`>6×`<input maxlength="1">` + a sibling `<input type="hidden" name="otp" data-otp-value>`.

## 3. Auth suite (member accounts) — backend is READY

`includes/member_auth.php` (loaded globally). Functions:
- `member_register($_POST)` → `[success,message,errors?,needs_verification]` (needs name,email,password≥8)
- `member_login($email,$password)` → `[success,message,unverified?]`
- `member_logout()`, `current_member()`, `is_member_logged_in()`, `require_member('/login')`
- `request_password_reset($email)` (emails a link; always show a generic success msg — no user enumeration)
- `reset_member_password($email,$token,$newPassword)` → `[success,message]`
- `verify_member_email($email,$token)` → `[success,message]`
- `send_member_otp($email)` (emails a 6-digit code) · `verify_member_otp($email,$code)` → `[success,message]`
- `send_member_verification($email,$memberId,$name)` (resend link)

Member session lives in `$_SESSION['member']`; admin is separate (`$_SESSION['user']`).
Member login is at **`/login`** (root); admin login stays at `/admin/login`.

## 4. Auth page pattern (STANDALONE — no public header/footer)

Auth pages render their own premium shell (so they can use the split-screen layout).
Handle POST server-side with the member_auth functions; show inline `.alert` + optionally `Swal`.

```php
<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_member_logged_in()) redirect('/account');
$error = $success = '';
if (is_post()) {
    require_csrf();
    $r = member_login(clean(post('email')), (string) post('password'));
    if ($r['success']) { set_flash('success', $r['message']); redirect('/account'); }
    $error = $r['message'];
}
seo_set(['title' => 'Login', 'robots' => 'noindex,nofollow']);
$siteName = get_setting('site_name', 'EDUSKILL INDIA FOUNDATION');
?>
<!DOCTYPE html><html lang="en" data-theme="light"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?= csrf_meta() ?><title><?= e($siteName) ?> — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
</head><body>
<div class="auth-shell">
  <div class="auth-aside">
    <span class="blob b1" style="top:-40px;left:-40px;"></span>
    <span class="blob b2" style="bottom:-40px;right:-30px;"></span>
    <div class="aside-inner">
      <h2>Welcome back 👋</h2>
      <p>Sign in to manage your membership, donations and volunteering.</p>
      <ul class="aside-points"><li>✓ Track your impact</li><li>✓ Faster donations</li><li>✓ Event registrations</li></ul>
    </div>
  </div>
  <div class="auth-main"><div class="auth-box"><div class="card">
    <h2>Sign In</h2>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field-float"><input type="email" id="email" name="email" placeholder=" " required><label for="email">Email</label></div>
      <div class="field-float">
        <input type="password" id="password" name="password" placeholder=" " required>
        <button type="button" class="toggle-pass" data-toggle-password="#password">👁️</button>
        <label for="password">Password</label>
      </div>
      <div class="flex justify-between items-center mb-2"><label class="checkbox"><input type="checkbox" name="remember"> Remember me</label><a href="<?= e(url('forgot-password')) ?>">Forgot password?</a></div>
      <button class="btn btn-3d btn-block btn-lg" type="submit">Sign In</button>
    </form>
    <div class="auth-sep">or continue with</div>
    <div class="social-auth">
      <button type="button" class="btn-social" onclick="Swal.fire({icon:'info',title:'Social login',text:'Configure an OAuth provider in admin settings to enable this.'})">G Google</button>
      <button type="button" class="btn-social" onclick="Swal.fire({icon:'info',title:'Social login',text:'Configure an OAuth provider in admin settings to enable this.'})">f Facebook</button>
    </div>
    <p class="text-center mt-3">New here? <a href="<?= e(url('signup')) ?>">Create an account</a></p>
  </div></div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<script src="<?= e(asset('js/premium.js')) ?>"></script>
</body></html>
```
Social login buttons are UI-only (no OAuth configured) — they must show an informative
SweetAlert (as above), never silently do nothing. Keep them; do not fake a login.

## 5. New tables (v2 + v3) — key columns

- **members**(name,email,password,phone,avatar,email_verified_at,status['active'|'pending'|'suspended'])
- **member_tokens**(member_id,email,type['verify_email'|'reset_password'|'otp'],token,expires_at,used_at)
- **careers**(title,slug,department,location,type['full-time'|'part-time'|'contract'|'internship'|'volunteer'],description,requirements,salary_range,openings,deadline,status['open'|'closed'])
- **job_applications**(career_id,name,email,phone,resume,cover_letter,status) → handler `forms/career-apply`
- **membership_plans**(name,slug,price,duration,benefits[one/line],is_featured,sort_order,status) · **membership_applications**(plan_id,name,email,phone,address,occupation,message,status) → handler `forms/membership`
- **schemes**(title,slug,category,department,short_description,description,eligibility,benefits,documents_required,apply_url,image,deadline,status['active'|'closed'])
- **scholarships**(title,slug,description,eligibility,amount,level,deadline,status['open'|'closed']) · **scholarship_applications**(scholarship_id,name,email,phone,institution,course,guardian_name,annual_income,document,status) → handler `forms/scholarship`
- **partner_applications**(organization,contact_name,email,phone,website,tier,message,status) → handler `forms/partner`
- **issued_certificates**(certificate_number[unique],holder_name,email,type,program,issue_date,expiry_date,file_path,status['valid'|'revoked'])
- **pages**(title,slug,subtitle,content,banner_image,status) — CMS pages for legal content (disclaimer/refund/cookie): query by slug, fall back to real default legal copy in `.prose`.

## 6. Form endpoints available
`forms/contact`, `forms/newsletter`, `forms/feedback`, `forms/volunteer`, `forms/internship`,
`forms/donate`, `forms/event-register`, `forms/comment`, `forms/download`, `forms/partner`,
`forms/scholarship`, `forms/career-apply`, `forms/membership`.
Use `<form data-ajax-form data-endpoint="<?= e(url('forms/<name>')) ?>">` + `<?= csrf_field() ?>`
for public AJAX forms (handled by forms.js + SweetAlert2). Auth pages post to themselves instead.
