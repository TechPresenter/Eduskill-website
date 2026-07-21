<?php
require __DIR__ . '/includes/auth.php';
require_admin('settings.manage');
$admin_title = 'Settings';

// Grouped settings schema: group => [ [key, label, type, group_name] ]. type: text|textarea|color|number|tel|email
$schema = [
    'General' => [
        ['site_name', 'Site name', 'text', 'general'],
        ['site_tagline', 'Tagline', 'textarea', 'general'],
    ],
    'Branding' => [
        ['brand_color', 'Primary colour', 'color', 'theme'],
        ['accent_color', 'Accent colour', 'color', 'theme'],
    ],
    'Homepage hero' => [
        ['hero_eyebrow', 'Eyebrow text', 'text', 'home'],
        ['hero_title', 'Headline', 'text', 'home'],
        ['hero_subtitle', 'Sub-headline', 'textarea', 'home'],
        ['stat_students', 'Stat: students supported', 'number', 'home'],
        ['stat_schools', 'Stat: partner schools', 'number', 'home'],
        ['stat_volunteers', 'Stat: volunteers', 'number', 'home'],
        ['stat_districts', 'Stat: districts', 'number', 'home'],
    ],
    'Contact & social' => [
        ['contact_email', 'Contact email', 'email', 'general'],
        ['contact_phone', 'Contact phone', 'tel', 'general'],
        ['contact_address', 'Address', 'text', 'general'],
        ['whatsapp_number', 'WhatsApp number (digits only)', 'tel', 'general'],
    ],
    'Email (SMTP)' => [
        ['mail_driver', 'Sending mode', 'select', 'mail', ['log' => 'Log only (test — do not send)', 'smtp' => 'SMTP (send for real)']],
        ['smtp_host', 'SMTP host', 'text', 'mail'],
        ['smtp_port', 'SMTP port', 'number', 'mail'],
        ['smtp_encryption', 'Encryption', 'select', 'mail', ['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None']],
        ['smtp_username', 'SMTP username', 'text', 'mail'],
        ['smtp_password', 'SMTP password', 'password', 'mail'],
        ['mail_from_email', 'From email', 'email', 'mail'],
        ['mail_from_name', 'From name', 'text', 'mail'],
        ['mail_notify_email', 'Send contact notices to', 'email', 'mail'],
    ],
];

// "Send test email" — sends to the logged-in admin and shows the SMTP transcript.
if (($_POST['action'] ?? '') === 'test_email' && verify_csrf($_POST['_csrf'] ?? null)) {
    $me = current_user();
    $transcript = '';
    $ok = send_mail((string) $me['email'], 'Eduskill test email', '<p>This is a test email from your Eduskill admin panel. If you are reading it, sending works. 🎉</p>', (string) $me['name'], $transcript);
    flash($ok ? 'success' : 'error', ($ok ? 'Test email processed. ' : 'Test email failed. ') . 'See details below.');
    $_SESSION['_mail_transcript'] = $transcript;
    redirect('admin/settings.php');
}

// Save.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        flash('error', 'Session expired. Please try again.');
        redirect('admin/settings.php');
    }
    foreach ($schema as $fields) {
        foreach ($fields as $f) {
            [$key, $label, $type, $group] = $f;
            $value = trim((string) ($_POST[$key] ?? ''));
            // A blank password means "keep the current one" — never wipe a stored SMTP password.
            if ($type === 'password' && $value === '') {
                continue;
            }
            $storeType = $type === 'number' ? 'int' : 'string';
            db_exec(
                "INSERT INTO settings (setting_key, setting_value, type, group_name) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$key, $value, $storeType, $group]
            );
        }
    }
    activity_log('settings.updated', (int) ($_SESSION['uid'] ?? 0));
    flash('success', 'Settings saved. Refresh the site to see changes.');
    redirect('admin/settings.php');
}

$in = 'mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div><h2 class="page-title">Settings</h2><p class="page-subtitle">Everything here is live on the public site.</p></div>
</div>

<form method="post" class="max-w-3xl space-y-6">
  <?= csrf_field() ?>
  <?php foreach ($schema as $group => $fields): ?>
    <div class="rounded-2xl border border-edge bg-surface p-6 shadow-card">
      <h3 class="mb-4 font-display text-sm font-bold uppercase tracking-wide text-content-muted"><?= e($group) ?></h3>
      <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
        <?php foreach ($fields as $f): [$key, $label, $type, $g] = $f; $opts = $f[4] ?? []; $val = (string) setting($key, ''); ?>
          <div class="<?= $type === 'textarea' ? 'sm:col-span-2' : '' ?>">
            <label class="block text-sm font-medium text-content"><?= e($label) ?></label>
            <?php if ($type === 'textarea'): ?>
              <textarea name="<?= e($key) ?>" rows="2" class="<?= $in ?>"><?= e($val) ?></textarea>
            <?php elseif ($type === 'color'): ?>
              <div class="mt-1 flex items-center gap-3">
                <input type="color" name="<?= e($key) ?>" value="<?= e($val ?: '#4f46e5') ?>" class="h-11 w-16 cursor-pointer rounded-lg border border-edge bg-surface p-1">
                <span class="rounded-lg bg-surface-sunken px-3 py-2 font-mono text-sm text-content-muted"><?= e($val) ?></span>
              </div>
            <?php elseif ($type === 'select'): ?>
              <select name="<?= e($key) ?>" class="<?= $in ?>"><?php foreach ($opts as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= $val === (string) $ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
            <?php elseif ($type === 'password'): ?>
              <input type="password" name="<?= e($key) ?>" value="" placeholder="<?= $val !== '' ? '••••••• (unchanged)' : '' ?>" autocomplete="new-password" class="<?= $in ?>">
            <?php else: ?>
              <input type="<?= $type === 'number' ? 'number' : ($type === 'email' ? 'email' : ($type === 'tel' ? 'tel' : 'text')) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>" class="<?= $in ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($group === 'Email (SMTP)'): ?>
        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-edge pt-4">
          <button type="submit" form="test-email-form" class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-100"><i class="fa-solid fa-paper-plane mr-1.5"></i>Send test email</button>
          <span class="text-xs text-content-muted">Sends to your account email. Save your SMTP settings first.</span>
        </div>
        <?php $tr = $_SESSION['_mail_transcript'] ?? null; unset($_SESSION['_mail_transcript']); ?>
        <?php if ($tr !== null): ?>
          <pre class="mt-3 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-900 p-4 text-xs text-slate-200"><?= e($tr) ?></pre>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="sticky bottom-0 flex items-center justify-end gap-3 rounded-2xl border border-edge bg-surface/95 p-4 shadow-card backdrop-blur">
    <a href="<?= e(url('admin/dashboard.php')) ?>" class="text-sm font-medium text-content-muted hover:text-content">Cancel</a>
    <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-on-brand hover:bg-brand-700"><i class="fa-solid fa-floppy-disk mr-1.5"></i>Save settings</button>
  </div>
</form>

<!-- Separate form so "Send test email" uses the SAVED SMTP settings, not unsaved form values. -->
<form id="test-email-form" method="post" class="hidden"><?= csrf_field() ?><input type="hidden" name="action" value="test_email"></form>
<?php require __DIR__ . '/includes/footer.php'; ?>
