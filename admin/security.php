<?php
/**
 * =============================================================================
 *  Admin — Security Center
 * =============================================================================
 *  A single console over the whole security posture: live status dashboard,
 *  2FA (+ recovery codes), IP restriction, login protection, password policy,
 *  upload hardening, security headers / HTTPS, encryption-at-rest, database
 *  backups, and the security audit feed.
 *
 *  Every enforcement toggle is DEFAULT-OFF and cannot lock the admin out of
 *  http://localhost (loopback is always allowed; HTTPS/HSTS never fire on plain
 *  HTTP; force-2FA only redirects to enrollment). See includes/security.php.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$page_title = 'Security Center';
$me = find('users', (int) current_user_id());

/* ------------------------------------------------------- Backup file download */
if (get('action') === 'download-backup') {
    $bk   = find('backups', (int) get('id', 0));
    $path = $bk ? sec_backup_file_path((string) $bk['filename']) : null;
    if ($path !== null && is_file($path)) {
        sec_log('backup_download', 'Downloaded backup ' . $bk['filename'], 'notice');
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
    set_flash('error', 'That backup file could not be found.');
    redirect('/admin/security#backups');
}

/* ------------------------------------------------------------------- Actions  */
if (is_post()) {
    require_csrf();
    $do = post('_do');

    /* --- Two-factor: enable / disable / recovery codes / force policy ------- */
    if ($do === 'enable-2fa') {
        $secret = (string) ($_SESSION['_totp_setup'] ?? '');
        if ($secret !== '' && totp_verify($secret, (string) post('code'))) {
            db_update('users', ['totp_secret' => $secret, 'totp_enabled' => 1, 'twofa_enrolled_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $me['id']]);
            unset($_SESSION['_totp_setup']);
            $_SESSION['_recovery_codes'] = sec_recovery_generate((int) $me['id'], 10);
            log_activity('2fa_enable', 'security', 'Enabled two-factor authentication');
            sec_log('2fa_enable', 'Enabled 2FA for ' . $me['email'], 'notice');
            set_flash('success', 'Two-factor authentication is enabled. Save your recovery codes below.');
        } else {
            set_flash('error', 'That code was not valid. Please scan/enter the key again and retry.');
        }
        redirect('/admin/security#twofa');
    }

    if ($do === 'disable-2fa') {
        if (password_verify((string) post('password'), $me['password'])) {
            db_update('users', ['totp_secret' => null, 'totp_enabled' => 0, 'twofa_enrolled_at' => null], 'id = :id', [':id' => $me['id']]);
            db_delete('user_recovery_codes', 'user_id = :u', [':u' => $me['id']]);
            log_activity('2fa_disable', 'security', 'Disabled two-factor authentication');
            sec_log('2fa_disable', 'Disabled 2FA for ' . $me['email'], 'warning');
            set_flash('success', 'Two-factor authentication has been disabled.');
        } else {
            set_flash('error', 'Incorrect password.');
        }
        redirect('/admin/security#twofa');
    }

    if ($do === 'gen-recovery') {
        if ((int) ($me['totp_enabled'] ?? 0) === 1) {
            $_SESSION['_recovery_codes'] = sec_recovery_generate((int) $me['id'], 10);
            set_flash('success', 'New recovery codes generated. Your old codes no longer work.');
        }
        redirect('/admin/security#twofa');
    }

    if ($do === 'force-2fa') {
        set_setting('force_2fa', post('force_2fa') ? '1' : '0', 'security', 'boolean');
        sec_log('policy', 'Set force_2fa = ' . (post('force_2fa') ? 'on' : 'off'), 'notice');
        set_flash('success', 'Two-factor policy updated.');
        redirect('/admin/security#twofa');
    }

    /* --- Access & session --------------------------------------------------- */
    if ($do === 'save-access') {
        $timeout = max(0, (int) post('session_timeout_minutes', 30));
        if ($timeout > 0 && $timeout < 5) { $timeout = 5; } // floor so 1-min can't sign you out constantly
        set_setting('session_timeout_minutes', (string) $timeout, 'security', 'number');
        set_setting('trust_proxy', post('trust_proxy') ? '1' : '0', 'security', 'boolean');

        // IP whitelist — refuse a list that would lock the current admin out.
        $newList = trim((string) post('admin_ip_whitelist', ''));
        $ip = sec_client_ip();
        $wouldAllow = ($newList === '') || sec_is_loopback($ip);
        if (!$wouldAllow) {
            foreach (array_filter(array_map('trim', explode(',', $newList))) as $entry) {
                if ($entry === $ip || sec_cidr_match($ip, $entry)) { $wouldAllow = true; break; }
            }
        }
        if (!$wouldAllow) {
            set_flash('error', 'That whitelist would lock you out — your IP ' . $ip . ' is not covered. Add it first.');
            redirect('/admin/security#access');
        }
        set_setting('admin_ip_whitelist', $newList, 'security', 'text');
        log_activity('update', 'security', 'Updated access & session settings');
        sec_log('policy', 'Updated access/session settings', 'notice');
        set_flash('success', 'Access & session settings saved.');
        redirect('/admin/security#access');
    }

    /* --- Login protection --------------------------------------------------- */
    if ($do === 'save-login') {
        set_setting('login_max_attempts', (string) max(3, (int) post('login_max_attempts', 5)), 'security', 'number');
        set_setting('login_lockout_mins', (string) max(1, (int) post('login_lockout_mins', 15)), 'security', 'number');
        set_setting('captcha_after', (string) max(1, (int) post('captcha_after', 3)), 'security', 'number');
        sec_log('policy', 'Updated login-protection thresholds', 'notice');
        set_flash('success', 'Login protection settings saved.');
        redirect('/admin/security#login');
    }
    if ($do === 'unlock') {
        $n = sec_unlock((string) post('unlock_key', ''));
        set_flash('success', 'Cleared ' . $n . ' failed attempt(s).');
        redirect('/admin/security#login');
    }
    if ($do === 'prune-attempts') {
        $n = sec_prune_login_attempts(90);
        set_flash('success', 'Removed ' . $n . ' old login-attempt record(s).');
        redirect('/admin/security#login');
    }

    /* --- Password policy ---------------------------------------------------- */
    if ($do === 'save-password') {
        set_setting('pw_min_len', (string) max(6, (int) post('pw_min_len', 8)), 'security', 'number');
        foreach (['pw_require_upper', 'pw_require_lower', 'pw_require_number', 'pw_require_symbol'] as $k) {
            set_setting($k, post($k) ? '1' : '0', 'security', 'boolean');
        }
        set_setting('pw_expiry_days', (string) max(0, (int) post('pw_expiry_days', 0)), 'security', 'number');
        set_setting('pw_history_count', (string) max(0, (int) post('pw_history_count', 0)), 'security', 'number');
        sec_log('policy', 'Updated password policy', 'notice');
        set_flash('success', 'Password policy saved.');
        redirect('/admin/security#password');
    }

    /* --- Uploads ------------------------------------------------------------ */
    if ($do === 'save-uploads') {
        set_setting('upload_max_mb', (string) max(1, (int) post('upload_max_mb', 5)), 'security', 'number');
        set_setting('upload_block_svg', post('upload_block_svg') ? '1' : '0', 'security', 'boolean');
        sec_log('policy', 'Updated upload policy', 'notice');
        set_flash('success', 'Upload settings saved.');
        redirect('/admin/security#uploads');
    }

    /* --- Headers & HTTPS ---------------------------------------------------- */
    if ($do === 'save-headers') {
        set_setting('force_https', post('force_https') ? '1' : '0', 'security', 'boolean');
        set_setting('hsts_enabled', post('hsts_enabled') ? '1' : '0', 'security', 'boolean');
        sec_log('policy', 'Updated header/HTTPS settings', 'notice');
        set_flash('success', 'Header & HTTPS settings saved.');
        redirect('/admin/security#headers');
    }

    /* --- Encryption --------------------------------------------------------- */
    if ($do === 'save-encryption') {
        set_setting('encrypt_secrets', post('encrypt_secrets') ? '1' : '0', 'security', 'boolean');
        set_flash('success', 'Encryption settings saved.');
        redirect('/admin/security#encryption');
    }

    /* --- Backups ------------------------------------------------------------ */
    if ($do === 'run-backup') {
        $res = sec_backup_run((string) post('destination', 'local'));
        set_flash($res['success'] ? 'success' : 'error', $res['message']);
        redirect('/admin/security#backups');
    }
    if ($do === 'save-backup-config') {
        set_setting('backup_destination', in_array(post('backup_destination'), ['local', 'ftp', 's3'], true) ? post('backup_destination') : 'local', 'security', 'text');
        set_setting('backup_retention', (string) max(1, (int) post('backup_retention', 7)), 'security', 'number');
        set_setting('backup_ftp_host', trim((string) post('backup_ftp_host', '')), 'security', 'text');
        set_setting('backup_ftp_user', trim((string) post('backup_ftp_user', '')), 'security', 'text');
        set_setting('backup_ftp_path', trim((string) post('backup_ftp_path', '')), 'security', 'text');
        sec_setting_secret_set('backup_ftp_pass', (string) post('backup_ftp_pass', '')); // blank-preserve + encrypt
        set_flash('success', 'Backup configuration saved.');
        redirect('/admin/security#backups');
    }
    if ($do === 'delete-backup') {
        sec_backup_delete((int) post('id', 0));
        set_flash('success', 'Backup deleted.');
        redirect('/admin/security#backups');
    }
    if ($do === 'prune-backups') {
        $n = sec_backup_prune((int) get_setting('backup_retention', 7));
        set_flash('success', 'Pruned ' . $n . ' old backup(s).');
        redirect('/admin/security#backups');
    }
}

/* --------------------------------------------------------------- View data    */
$enabled = (int) ($me['totp_enabled'] ?? 0) === 1;
if (!$enabled) {
    if (empty($_SESSION['_totp_setup'])) {
        $_SESSION['_totp_setup'] = totp_secret_new();
    }
    $setupSecret = $_SESSION['_totp_setup'];
    $otpUri = totp_uri($setupSecret, $me['email']);
    $qrSvg  = sec_2fa_qr_svg($otpUri);
}
$recoveryCodes = $_SESSION['_recovery_codes'] ?? null;
unset($_SESSION['_recovery_codes']);

$features   = sec_feature_status();
$policy     = sec_password_policy();
$httpsInfo  = sec_https_status();
$headerScan = sec_headers_status();
$myIp       = sec_client_ip();
$lockedList = sec_locked_list();
$attempts   = sec_recent_attempts(30);
$backups    = sec_backup_list(20);
$auditFeed  = db_all("SELECT * FROM activity_logs WHERE module = 'security' OR severity IN ('warning','critical') ORDER BY id DESC LIMIT 25");
$dumpBin    = sec_mysqldump_path();

$pill = static fn(string $state): string => match ($state) {
    'ok'   => 'pill-green',
    'warn' => 'pill-amber',
    default => 'pill-gray',
};
$stateLabel = static fn(string $state): string => match ($state) {
    'ok' => 'Active', 'warn' => 'Review', default => 'Off',
};

include __DIR__ . '/partials/head.php';
?>
<style>
/* Screen-local layout ONLY. Everything with a shared implementation now comes
   from it: the tab strip is admin-pro.css's `.tabs`/`.tab` (this file used to
   carry a byte-identical `.sc-tabs`/`.sc-tab` copy), the page head is
   `.admin-page-head`, and status is a `.pill` — never a bare colour dot, which
   the design system rules out as "colour alone". */
.sc-wrap{max-width:1180px}
.sc-ip{display:inline-flex;align-items:center;gap:var(--sp-2);background:var(--surface-2);border:1px solid var(--border);border-radius:var(--r-md);padding:var(--sp-2) var(--sp-4);font-size:.85rem}
.sc-ip code{font-weight:800;color:var(--brand-600)}
.sc-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:var(--sp-3);margin-bottom:var(--sp-6)}
.sc-card{display:block;padding:var(--sp-4);border:1px solid var(--border);border-radius:var(--r-lg);background:var(--surface);box-shadow:var(--elev-1);color:inherit;text-decoration:none;transition:box-shadow var(--dur-1) var(--ease),border-color var(--dur-1) var(--ease)}
.sc-card:hover{box-shadow:var(--elev-2);border-color:var(--brand-600)}
.sc-card .sc-top{display:flex;align-items:center;justify-content:space-between;gap:var(--sp-2);margin-bottom:var(--sp-2)}
.sc-card h3{margin:0;font-size:.92rem;font-weight:700}
.sc-card p{margin:0;font-size:.8rem;line-height:1.5;color:var(--muted)}
.sc-panel{display:none}
.sc-panel.is-active{display:block}
.sc-form{max-width:720px}
.sc-form-sm{max-width:640px}
.sc-narrow{max-width:420px}
/* Beats `body.admin .panel`'s `border` shorthand without !important. */
.admin-content .panel.sc-warn{border-color:var(--st-warn)}
.sc-codes{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--sp-2);max-width:420px;margin:var(--sp-3) 0}
.sc-codes code{padding:var(--sp-2);border:1px dashed var(--border);border-radius:var(--r-sm);background:var(--surface-2);text-align:center;font-weight:700;letter-spacing:.05em}
.sc-hdr-check{display:flex;align-items:center;justify-content:space-between;gap:var(--sp-3);padding:var(--sp-2) 0;border-bottom:1px solid var(--border);font-size:.9rem}
/* #fff is deliberate and not a palette exception: a QR needs maximum quiet-zone
   contrast to scan, in either theme. */
.sc-qr{display:grid;place-items:center;width:172px;height:172px;padding:var(--sp-3);border:1px solid var(--border);border-radius:var(--r-md);background:#fff}
.sc-qr svg{width:100%;height:100%}
.sc-2col{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:var(--sp-6);align-items:start}
.sc-step{margin:0 0 var(--sp-2);font-weight:600}
.sc-step-2{margin-top:var(--sp-4)}
.sc-key{font-size:1.05rem;font-weight:800;letter-spacing:.15em;color:var(--brand-600)}
/* .admin-content .form-control pins font-size at 12.5px, so the one-time-code
   field has to match that specificity to stay legible. */
.admin-content .form-control.sc-otp{text-align:center;font-size:1.3rem;letter-spacing:.35em}
.sc-hint-gap{margin-bottom:var(--sp-3)}
.sc-inline{display:inline}
.sc-alert-gap{margin-top:var(--sp-2)}
.sc-lede{margin:0 0 var(--sp-2)}
.sc-check-gap{margin-top:var(--sp-2)}
.sc-gap-t{margin-top:var(--sp-4)}
.sc-file{font-size:.8rem}
.sc-details{margin-top:var(--sp-4)}
.sc-details > summary{cursor:pointer}
.sc-btn-row{display:flex;flex-wrap:wrap;gap:var(--sp-2);margin-bottom:var(--sp-4)}
.sc-ta-r{text-align:right}
</style>

<div class="admin-content sc-wrap">
<div class="admin-page-head">
    <div><h1><?= lucide('shield-alert') ?> Security Center</h1><span class="muted">Monitor and control every layer of application security</span></div>
    <div class="sc-ip"><?= lucide('map-pin') ?> Your IP: <code><?= e($myIp) ?></code></div>
</div>

<!-- ===================== OVERVIEW DASHBOARD ===================== -->
<?php /* The card grid needs a heading of its own, otherwise the outline runs
         h1 → h4 (card titles) → h2 (the tab panels below) and skips two levels on
         the way in. It is visually redundant next to the cards, so it is announced
         to assistive tech only — the same .sr-only the topbar already uses. */ ?>
<h2 class="sr-only">Security overview</h2>
<div class="sc-cards">
    <?php foreach ($features as $f): ?>
        <a class="sc-card" href="#<?= e($f['href'] ?: 'overview') ?>" <?= $f['href'] ? 'data-goto="' . e($f['href']) . '"' : '' ?>>
            <div class="sc-top">
                <h3><?= e($f['label']) ?></h3>
                <span class="pill <?= e($pill($f['state'])) ?>"><?= e($stateLabel($f['state'])) ?></span>
            </div>
            <p><?= e($f['detail']) ?></p>
        </a>
    <?php endforeach; ?>
</div>

<!-- ===================== TABS ===================== -->
<div class="tabs" role="tablist">
    <button type="button" class="tab is-active" data-tab="access"><?= lucide('key-round') ?> Access &amp; Session</button>
    <button type="button" class="tab" data-tab="twofa"><?= lucide('smartphone') ?> Two-Factor</button>
    <button type="button" class="tab" data-tab="login"><?= lucide('log-in') ?> Login Protection</button>
    <button type="button" class="tab" data-tab="password"><?= lucide('lock-keyhole') ?> Password Policy</button>
    <button type="button" class="tab" data-tab="uploads"><?= lucide('file-up') ?> Uploads</button>
    <button type="button" class="tab" data-tab="headers"><?= lucide('globe-lock') ?> Headers &amp; HTTPS</button>
    <button type="button" class="tab" data-tab="encryption"><?= lucide('binary') ?> Encryption</button>
    <button type="button" class="tab" data-tab="backups"><?= lucide('database-backup') ?> Backups</button>
    <button type="button" class="tab" data-tab="audit"><?= lucide('scroll-text') ?> Audit Log</button>
</div>

<!-- ===================== ACCESS & SESSION ===================== -->
<section class="sc-panel is-active" id="tab-access">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('key-round') ?> Access &amp; Session</h2></div><div class="panel-body">
        <form method="post" action="<?= e(admin_url('security')) ?>" class="admin-form sc-form">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save-access">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Idle session timeout (minutes)</label>
                    <input class="form-control" type="number" name="session_timeout_minutes" min="0" value="<?= e(get_setting('session_timeout_minutes', '30')) ?>">
                    <span class="form-hint">Signed out after this much inactivity. 0 disables it; values under 5 are raised to 5.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin IP whitelist</label>
                    <input class="form-control" name="admin_ip_whitelist" value="<?= e(get_setting('admin_ip_whitelist', '')) ?>" placeholder="203.0.113.5, 198.51.100.0/24">
                    <span class="form-hint">Comma-separated IPs or CIDR ranges. Blank = allow all. Loopback (<?= e($myIp) ?>) is always allowed, and a list excluding your IP is refused.</span>
                </div>
            </div>
            <label class="checkbox"><input type="checkbox" name="trust_proxy" value="1" <?= (int) get_setting('trust_proxy', 0) === 1 ? 'checked' : '' ?>> Trust reverse-proxy headers (X-Forwarded-For / CF-Connecting-IP)</label>
            <span class="form-hint">Leave off unless a real proxy/CDN sits in front — proxy headers are spoofable and would weaken IP restriction &amp; lockout.</span>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save</button></div>
        </form>
    </div></div>
</section>

<!-- ===================== TWO-FACTOR ===================== -->
<section class="sc-panel" id="tab-twofa">
    <?php if ($recoveryCodes): ?>
        <div class="panel sc-warn"><div class="panel-head"><h2 class="panel-title"><?= lucide('life-buoy') ?> Your recovery codes</h2></div><div class="panel-body">
            <div class="alert alert-warning">Save these now — each works once if you lose your authenticator. They are shown only this once.</div>
            <div class="sc-codes"><?php foreach ($recoveryCodes as $c): ?><code><?= e($c) ?></code><?php endforeach; ?></div>
        </div></div>
    <?php endif; ?>

    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('smartphone') ?> Two-Factor Authentication (TOTP)</h2>
        <span class="pill <?= $enabled ? 'pill-green' : 'pill-gray' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span></div>
        <div class="panel-body">
        <?php if ($enabled): ?>
            <p class="text-muted">Your account is protected with an authenticator app. You'll be asked for a 6-digit code at every sign-in. <strong><?= (int) sec_recovery_remaining((int) $me['id']) ?></strong> recovery code(s) remain.</p>
            <div class="sc-btn-row">
                <form method="post" action="<?= e(admin_url('security')) ?>"><?= csrf_field() ?><input type="hidden" name="_do" value="gen-recovery"><button class="btn btn-secondary" type="submit"><?= lucide('refresh-cw') ?> Regenerate recovery codes</button></form>
            </div>
            <form method="post" action="<?= e(admin_url('security')) ?>" class="sc-narrow">
                <?= csrf_field() ?><input type="hidden" name="_do" value="disable-2fa">
                <div class="form-group"><label class="form-label">Confirm your password to disable 2FA</label>
                    <input class="form-control" type="password" name="password" required></div>
                <button class="btn btn-danger" type="submit">Disable 2FA</button>
            </form>
        <?php else: ?>
            <p class="text-muted">Install Google Authenticator (or any TOTP app), scan the code, then confirm. Recovery codes are generated automatically.</p>
            <div class="sc-2col">
                <div>
                    <p class="sc-step">1. Scan this QR code:</p>
                    <?php if (!empty($qrSvg)): ?><div class="sc-qr"><?= $qrSvg ?></div><?php endif; ?>
                    <p class="sc-step sc-step-2">…or enter the key manually:</p>
                    <code class="sc-key"><?= e(chunk_split($setupSecret, 4, ' ')) ?></code>
                    <p class="form-hint">Account: <?= e($me['email']) ?> · Issuer: <?= e(get_setting('site_name', SITE_NAME)) ?></p>
                </div>
                <div>
                    <p class="sc-step">2. Enter the 6-digit code:</p>
                    <form method="post" action="<?= e(admin_url('security')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="enable-2fa">
                        <div class="form-group"><input class="form-control sc-otp" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="000000"></div>
                        <button class="btn btn-primary" type="submit">Verify &amp; Enable 2FA</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('building-2') ?> Organisation Policy</h2></div><div class="panel-body">
        <form method="post" action="<?= e(admin_url('security')) ?>"><?= csrf_field() ?><input type="hidden" name="_do" value="force-2fa">
            <label class="checkbox"><input type="checkbox" name="force_2fa" value="1" <?= (int) get_setting('force_2fa', 0) === 1 ? 'checked' : '' ?>> Require 2FA for all admins</label>
            <span class="form-hint sc-hint-gap">Admins without 2FA are redirected here to enroll — they are never locked out. Enroll yourself first so you have recovery codes.</span>
            <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save policy</button>
        </form>
    </div></div>
</section>

<!-- ===================== LOGIN PROTECTION ===================== -->
<section class="sc-panel" id="tab-login">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('log-in') ?> Login Protection</h2></div><div class="panel-body">
        <form method="post" action="<?= e(admin_url('security')) ?>" class="admin-form sc-form">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save-login">
            <div class="grid-3">
                <div class="form-group"><label class="form-label">Max failed attempts</label>
                    <input class="form-control" type="number" name="login_max_attempts" min="3" value="<?= e((string) sec_lockout_max()) ?>">
                    <span class="form-hint">Before lockout. Minimum 3.</span></div>
                <div class="form-group"><label class="form-label">Lockout window (minutes)</label>
                    <input class="form-control" type="number" name="login_lockout_mins" min="1" value="<?= e((string) sec_lockout_mins()) ?>"></div>
                <div class="form-group"><label class="form-label">CAPTCHA after N fails</label>
                    <input class="form-control" type="number" name="captcha_after" min="1" value="<?= e((string) sec_captcha_after()) ?>"></div>
            </div>
            <div class="alert alert-info">Loopback (localhost) is exempt from lockout so you can't lock yourself out while testing.</div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save</button></div>
        </form>
    </div></div>

    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('lock') ?> Currently Locked</h2>
        <?php if ($lockedList): ?><span class="pill pill-red"><?= count($lockedList) ?> locked</span><?php endif; ?></div>
        <div class="panel-body">
        <?php if ($lockedList): ?>
            <div class="table-wrap"><table class="admin-table"><thead><tr><th>Email</th><th>IP</th><th>Fails</th><th>Last</th><th></th></tr></thead><tbody>
            <?php foreach ($lockedList as $l): ?>
                <tr><td><?= e($l['email'] ?: '—') ?></td><td><code><?= e($l['ip_address']) ?></code></td><td><?= (int) $l['fails'] ?></td>
                    <td><small class="text-muted"><?= e(time_ago($l['last_at'])) ?></small></td>
                    <td class="sc-ta-r"><form method="post" action="<?= e(admin_url('security')) ?>" class="sc-inline"><?= csrf_field() ?><input type="hidden" name="_do" value="unlock"><input type="hidden" name="unlock_key" value="<?= e($l['email'] ?: $l['ip_address']) ?>"><button class="btn btn-sm btn-outline" type="submit"><?= lucide('unlock') ?> Unlock</button></form></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('shield-check') ?></div>
                <p class="es-title">No accounts are locked out</p>
                <p class="es-text">Sign-ins that trip the failed-attempt threshold appear here with an unlock control.</p>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('history') ?> Recent Login Attempts</h2>
        <form method="post" action="<?= e(admin_url('security')) ?>" class="sc-inline"><?= csrf_field() ?><input type="hidden" name="_do" value="prune-attempts"><button class="btn btn-sm btn-ghost" type="submit"><?= lucide('trash-2') ?> Prune &gt;90 days</button></form></div>
        <div class="panel-body">
        <?php if ($attempts): ?>
            <div class="table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>Email</th><th>IP</th><th>Result</th></tr></thead><tbody>
            <?php foreach ($attempts as $a): ?>
                <tr><td><small class="text-muted"><?= e(format_datetime($a['created_at'], 'd M, H:i')) ?></small></td>
                    <td><?= e($a['email'] ?: '—') ?></td><td><code><?= e($a['ip_address']) ?></code></td>
                    <td><span class="pill <?= $a['success'] ? 'pill-green' : 'pill-red' ?>"><?= $a['success'] ? 'success' : 'failed' ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('history') ?></div>
                <p class="es-title">No login attempts recorded</p>
                <p class="es-text">Every admin sign-in — successful or failed — is logged here with its source IP.</p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</section>

<!-- ===================== PASSWORD POLICY ===================== -->
<section class="sc-panel" id="tab-password">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('lock-keyhole') ?> Password Policy</h2></div><div class="panel-body">
        <form method="post" action="<?= e(admin_url('security')) ?>" class="admin-form sc-form">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save-password">
            <div class="grid-3">
                <div class="form-group"><label class="form-label">Minimum length</label>
                    <input class="form-control" type="number" name="pw_min_len" min="6" value="<?= (int) $policy['min_len'] ?>"></div>
                <div class="form-group"><label class="form-label">Expiry (days)</label>
                    <input class="form-control" type="number" name="pw_expiry_days" min="0" value="<?= (int) $policy['expiry_days'] ?>">
                    <span class="form-hint">0 = never expire.</span></div>
                <div class="form-group"><label class="form-label">Prevent reuse (last N)</label>
                    <input class="form-control" type="number" name="pw_history_count" min="0" value="<?= (int) $policy['history_count'] ?>">
                    <span class="form-hint">0 = allow reuse.</span></div>
            </div>
            <div class="grid-2">
                <label class="checkbox"><input type="checkbox" name="pw_require_upper" value="1" <?= $policy['require_upper'] ? 'checked' : '' ?>> Require an uppercase letter</label>
                <label class="checkbox"><input type="checkbox" name="pw_require_lower" value="1" <?= $policy['require_lower'] ? 'checked' : '' ?>> Require a lowercase letter</label>
                <label class="checkbox"><input type="checkbox" name="pw_require_number" value="1" <?= $policy['require_number'] ? 'checked' : '' ?>> Require a number</label>
                <label class="checkbox"><input type="checkbox" name="pw_require_symbol" value="1" <?= $policy['require_symbol'] ? 'checked' : '' ?>> Require a special character</label>
            </div>
            <div class="alert alert-info sc-alert-gap">Applies to admin user creation and password changes. Passwords are always bcrypt-hashed.</div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save</button></div>
        </form>
    </div></div>
</section>

<!-- ===================== UPLOADS ===================== -->
<section class="sc-panel" id="tab-uploads">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('file-up') ?> Upload Protection</h2></div><div class="panel-body">
        <form method="post" action="<?= e(admin_url('security')) ?>" class="admin-form sc-form-sm">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save-uploads">
            <div class="form-group"><label class="form-label">Maximum file size (MB)</label>
                <input class="form-control" type="number" name="upload_max_mb" min="1" value="<?= e(get_setting('upload_max_mb', '5')) ?>"></div>
            <label class="checkbox"><input type="checkbox" name="upload_block_svg" value="1" <?= (int) get_setting('upload_block_svg', 0) === 1 ? 'checked' : '' ?>> Block SVG uploads</label>
            <span class="form-hint sc-hint-gap">SVGs can carry scripts. Uploaded SVGs are already stripped of scripts/handlers; block them entirely for maximum safety.</span>
            <div class="alert alert-info">Always enforced: extension + MIME whitelist, random filenames, real-image validation, PHP execution disabled under <code>/uploads</code>.</div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save</button></div>
        </form>
    </div></div>
</section>

<!-- ===================== HEADERS & HTTPS ===================== -->
<section class="sc-panel" id="tab-headers">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('globe-lock') ?> Response Headers</h2></div><div class="panel-body">
        <p class="text-muted sc-lede">Live self-check of security headers on this response:</p>
        <?php foreach ($headerScan as $name => $present): ?>
            <div class="sc-hdr-check"><strong><?= e($name) ?></strong>
                <span class="pill <?= $present ? 'pill-green' : 'pill-amber' ?>"><?= $present ? 'present' : 'not detected' ?></span></div>
        <?php endforeach; ?>
        <p class="form-hint">Headers are set in <code>.htaccess</code> and mirrored by a PHP fallback for hosts without mod_headers.</p>
    </div></div>

    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('shield-check') ?> HTTPS &amp; HSTS</h2>
        <span class="pill <?= $httpsInfo['is_https'] ? 'pill-green' : 'pill-gray' ?>"><?= $httpsInfo['is_https'] ? 'HTTPS' : 'HTTP' ?></span></div>
        <div class="panel-body">
        <?php if (!$httpsInfo['safe_to_enable']): ?>
            <div class="alert alert-warning"><?= lucide('triangle-alert') ?> This site is running over <strong>HTTP<?= APP_ENV !== 'production' ? ' in development' : '' ?></strong>. Forcing HTTPS or HSTS now would make the site unreachable, so these are safe to enable only in production over HTTPS. The toggles below are saved but stay dormant until then.</div>
        <?php endif; ?>
        <form method="post" action="<?= e(admin_url('security')) ?>"><?= csrf_field() ?><input type="hidden" name="_do" value="save-headers">
            <label class="checkbox"><input type="checkbox" name="force_https" value="1" <?= $httpsInfo['force_enabled'] ? 'checked' : '' ?> <?= $httpsInfo['safe_to_enable'] ? '' : 'disabled' ?>> Force HTTPS (redirect HTTP → HTTPS)</label><br>
            <label class="checkbox sc-check-gap"><input type="checkbox" name="hsts_enabled" value="1" <?= $httpsInfo['hsts_enabled'] ? 'checked' : '' ?> <?= $httpsInfo['safe_to_enable'] ? '' : 'disabled' ?>> Enable HSTS (Strict-Transport-Security)</label>
            <span class="form-hint sc-hint-gap">Both take effect only in production over real HTTPS. HSTS is cached by browsers — enable only when HTTPS is permanent.</span>
            <?php if ($httpsInfo['safe_to_enable']): ?><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save</button><?php else: ?><button class="btn btn-secondary" type="submit"><?= lucide('save') ?> Save (dormant)</button><?php endif; ?>
        </form>
    </div></div>
</section>

<!-- ===================== ENCRYPTION ===================== -->
<section class="sc-panel" id="tab-encryption">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('binary') ?> Encryption at Rest</h2>
        <span class="pill <?= sec_has_encryption() ? 'pill-green' : 'pill-gray' ?>"><?= sec_has_encryption() ? 'AES-256-GCM ready' : 'Not configured' ?></span></div>
        <div class="panel-body">
        <?php if (sec_has_encryption()): ?>
            <div class="alert alert-info"><?= lucide('shield-check') ?> An AES-256-GCM key (<code>APP_KEY</code>) is configured. Sensitive settings such as backup credentials are encrypted before storage. Passwords are always bcrypt-hashed.</div>
        <?php else: ?>
            <div class="alert alert-warning"><?= lucide('triangle-alert') ?> No <code>APP_KEY</code> is set in <code>config.php</code>, so field-level encryption is disabled. Passwords remain securely bcrypt-hashed regardless.</div>
        <?php endif; ?>
        <form method="post" action="<?= e(admin_url('security')) ?>"><?= csrf_field() ?><input type="hidden" name="_do" value="save-encryption">
            <label class="checkbox"><input type="checkbox" name="encrypt_secrets" value="1" <?= (int) get_setting('encrypt_secrets', 0) === 1 ? 'checked' : '' ?> <?= sec_has_encryption() ? '' : 'disabled' ?>> Encrypt sensitive settings (backup credentials, tokens)</label>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save</button></div>
        </form>
    </div></div>
</section>

<!-- ===================== BACKUPS ===================== -->
<section class="sc-panel" id="tab-backups">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('database-backup') ?> Database Backups</h2></div><div class="panel-body">
        <?php if ($dumpBin === null): ?>
            <div class="alert alert-warning"><?= lucide('triangle-alert') ?> <code>mysqldump</code> was not found. Set <code>BACKUP_MYSQLDUMP</code> in <code>config.php</code> to enable backups.</div>
        <?php elseif (!sec_exec_available()): ?>
            <div class="alert alert-warning"><?= lucide('triangle-alert') ?> PHP <code>exec()</code> is disabled on this server; on-demand backups are unavailable.</div>
        <?php else: ?>
            <div class="sc-btn-row">
                <form method="post" action="<?= e(admin_url('security')) ?>" class="sc-inline"><?= csrf_field() ?><input type="hidden" name="_do" value="run-backup"><input type="hidden" name="destination" value="local"><button class="btn btn-primary" type="submit"><?= lucide('database-backup') ?> Back up now</button></form>
                <form method="post" action="<?= e(admin_url('security')) ?>" class="sc-inline"><?= csrf_field() ?><input type="hidden" name="_do" value="prune-backups"><button class="btn btn-ghost" type="submit"><?= lucide('archive') ?> Prune old</button></form>
            </div>
        <?php endif; ?>
        <?php /* Name the REAL directory. This used to read "/storage/backups",
                 which is inside the app folder and has never been where backups
                 go — an admin looking for their dump would have found an empty
                 directory and concluded the backup had failed. The path comes
                 from BACKUP_DIR (config.php), which deliberately sits one level
                 ABOVE the app so a gzipped copy of the whole database is not
                 sitting in a served tree. */ ?>
        <p class="form-hint">Backups are gzip-compressed SQL dumps stored in
            <code><?= e(function_exists('sec_backup_dir') ? sec_backup_dir() : (defined('BACKUP_DIR') ? BACKUP_DIR : '')) ?></code>,
            outside the public web root.</p>

        <?php if ($backups): ?>
        <div class="table-wrap sc-gap-t"><table class="admin-table"><thead><tr><th>File</th><th>Size</th><th>Status</th><th>When</th><th class="sc-ta-r">Actions</th></tr></thead><tbody>
            <?php foreach ($backups as $b): ?>
                <tr><td><code class="sc-file"><?= e($b['filename']) ?></code></td>
                    <td><?= e(human_filesize((int) $b['size_bytes'])) ?></td>
                    <td><span class="pill <?= $b['status'] === 'success' ? 'pill-green' : ($b['status'] === 'failed' ? 'pill-red' : 'pill-amber') ?>"><?= e($b['status']) ?></span></td>
                    <td><small class="text-muted"><?= e(format_datetime($b['created_at'], 'd M Y, H:i')) ?></small></td>
                    <td class="sc-ta-r"><div class="actions">
                        <?php if ($b['status'] === 'success'): ?><a class="icon-btn" href="<?= e(admin_url('security?action=download-backup&id=' . $b['id'])) ?>" title="Download"><?= lucide('download') ?></a><?php endif; ?>
                        <form method="post" action="<?= e(admin_url('security')) ?>" data-confirm="Delete this backup?" class="sc-inline"><?= csrf_field() ?><input type="hidden" name="_do" value="delete-backup"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button></form>
                    </div></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
            <div class="empty-state sc-gap-t"><div class="icon"><?= lucide('database') ?></div>
                <p class="es-title">No backups yet</p>
                <p class="es-text">Run one from “Back up now” above, or schedule it with the cron command below.</p>
            </div>
        <?php endif; ?>
    </div></div>

    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('settings-2') ?> Backup Configuration</h2></div><div class="panel-body">
        <form method="post" action="<?= e(admin_url('security')) ?>" class="admin-form sc-form">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save-backup-config">
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Default destination</label>
                    <select class="form-select" name="backup_destination">
                        <?php foreach (['local' => 'Local storage', 'ftp' => 'Local + FTP'] as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= get_setting('backup_destination', 'local') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Retention (keep newest N)</label>
                    <input class="form-control" type="number" name="backup_retention" min="1" value="<?= e(get_setting('backup_retention', '7')) ?>"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">FTP host</label><input class="form-control" name="backup_ftp_host" value="<?= e(get_setting('backup_ftp_host', '')) ?>" placeholder="ftp.example.com"></div>
                <div class="form-group"><label class="form-label">FTP user</label><input class="form-control" name="backup_ftp_user" value="<?= e(get_setting('backup_ftp_user', '')) ?>"></div>
                <div class="form-group"><label class="form-label">FTP password</label><input class="form-control" type="password" name="backup_ftp_pass" placeholder="<?= sec_setting_secret('backup_ftp_pass') !== '' ? '•••••••• (saved)' : '' ?>">
                    <span class="form-hint">Leave blank to keep the saved value. Stored encrypted.</span></div>
                <div class="form-group"><label class="form-label">FTP remote path</label><input class="form-control" name="backup_ftp_path" value="<?= e(get_setting('backup_ftp_path', '')) ?>" placeholder="/backups"></div>
            </div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save configuration</button></div>
        </form>
        <details class="sc-details"><summary class="text-muted">Automate with a scheduled task</summary>
            <p class="form-hint">Run a daily backup on Windows Task Scheduler:<br>
            <code>C:\xampp\php\php.exe C:\xampp\htdocs\pwf\cron\backup.php</code> (add a token-gated cron like the membership reminders).</p></details>
    </div></div>
</section>

<!-- ===================== AUDIT LOG ===================== -->
<section class="sc-panel" id="tab-audit">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('scroll-text') ?> Security Audit Feed</h2>
        <a class="btn btn-sm btn-secondary" href="<?= e(admin_url('activity-logs')) ?>"><?= lucide('external-link') ?> Full activity log</a></div>
        <div class="panel-body">
        <?php if ($auditFeed): ?>
            <div class="table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>Action</th><th>Detail</th><th>Severity</th><th>IP</th></tr></thead><tbody>
            <?php foreach ($auditFeed as $log): $sev = $log['severity'] ?? 'info'; ?>
                <tr><td><small class="text-muted"><?= e(format_datetime($log['created_at'], 'd M, H:i')) ?></small></td>
                    <td><?= e($log['action']) ?></td><td><?= e($log['description'] ?: '—') ?></td>
                    <td><span class="pill <?= $sev === 'critical' ? 'pill-red' : ($sev === 'warning' ? 'pill-amber' : 'pill-gray') ?>"><?= e($sev) ?></span></td>
                    <td><code><?= e($log['ip_address'] ?: '—') ?></code></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('scroll-text') ?></div>
                <p class="es-title">No security events yet</p>
                <p class="es-text">Policy changes, 2FA events, backups and anything logged as a warning or critical land here.</p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</section>
</div>

<script>
(function () {
    // Shared .tabs component (admin-pro.css) — the local .sc-tab copy is gone.
    var tabs = document.querySelectorAll('.sc-wrap .tabs .tab'), panels = document.querySelectorAll('.sc-panel');
    function show(name) {
        var found = false;
        tabs.forEach(function (t) { t.classList.toggle('is-active', t.dataset.tab === name); found = found || t.dataset.tab === name; });
        if (!found) return;
        panels.forEach(function (p) { p.classList.toggle('is-active', p.id === 'tab-' + name); });
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); history.replaceState(null, '', '#' + t.dataset.tab); }); });
    document.querySelectorAll('.sc-card[data-goto]').forEach(function (c) {
        c.addEventListener('click', function (e) { e.preventDefault(); show(c.dataset.goto); window.scrollTo({ top: 0, behavior: 'smooth' }); });
    });
    var h = (location.hash || '').replace('#', '');
    if (h) show(h);
})();
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
