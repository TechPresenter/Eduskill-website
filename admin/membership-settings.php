<?php
/**
 * =============================================================================
 *  Admin — Membership Settings
 * =============================================================================
 *  Configure the membership module: ID prefix, default card template, expiry
 *  reminders, the Cashfree payment gateway and SMS (MSG91 / Fast2SMS). Secret
 *  fields are preserved when left blank so they are never accidentally wiped.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/sms.php';
require_admin();

$cardTemplates = ['classic' => 'Classic', 'modern' => 'Modern', 'dark' => 'Dark'];

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    $plain = [
        'membership_code_prefix'   => 'membership',
        'membership_card_template' => 'membership',
        'membership_reminder_days' => 'membership',
        'cashfree_env'             => 'payments',
        'cashfree_app_id'          => 'payments',
        'sms_provider'             => 'sms',
        'msg91_sender'             => 'sms',
        'msg91_route'              => 'sms',
        'msg91_dlt_template_id'    => 'sms',
        'fast2sms_sender'          => 'sms',
    ];
    foreach ($plain as $key => $group) {
        set_setting($key, clean(post($key)), $group, 'text');
    }

    $bools = [
        'membership_reminders_enabled' => 'membership',
        'cashfree_enabled'             => 'payments',
        'sms_enabled'                  => 'sms',
    ];
    foreach ($bools as $key => $group) {
        set_setting($key, post($key) ? '1' : '0', $group, 'boolean');
    }

    // Secrets: only overwrite when a new value is supplied.
    $secrets = [
        'cashfree_secret_key'     => 'payments',
        'cashfree_webhook_secret' => 'payments',
        'msg91_authkey'           => 'sms',
        'fast2sms_key'            => 'sms',
    ];
    foreach ($secrets as $key => $group) {
        $val = (string) post($key);
        if ($val !== '') {
            set_setting($key, trim($val), $group, 'text');
        }
    }

    if (post('regen_token') || (string) get_setting('membership_cron_token', '') === '') {
        set_setting('membership_cron_token', bin2hex(random_bytes(20)), 'membership', 'text');
    }

    log_activity('update', 'membership-settings', 'Updated membership settings');
    set_flash('success', 'Membership settings saved.');
    redirect('/admin/membership-settings');
}

/* -------------------------------------------------------------- TEST SMS */
if (is_post() && post('_do') === 'test_sms') {
    require_csrf();
    $to  = clean(post('test_number'));
    $res = send_sms($to, 'Test SMS from ' . get_setting('site_name', SITE_NAME) . '. Your setup works.');
    set_flash($res['success'] ? 'success' : 'error', 'SMS test: ' . $res['message']);
    redirect('/admin/membership-settings');
}

$mask = static fn (string $key): string => get_setting($key, '') !== '' ? '•••••••• (saved)' : '';
$cronToken = (string) get_setting('membership_cron_token', '');
$cronPath  = str_replace('/', DIRECTORY_SEPARATOR, BASE_PATH . '/cron/membership-reminders.php');
$cronUrl   = abs_url('cron/membership-reminders?token=' . $cronToken); // extensionless (clean URL)

$page_title = 'Membership Settings';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Membership Settings</h1><span class="muted">Cards · reminders · payments · SMS</span></div>
</div>

<form method="post" action="<?= e(admin_url('membership-settings')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_do" value="save">

    <div class="grid-2" style="align-items:start;gap:1.25rem;">
        <!-- General + reminders -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">General & reminders</h3>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Membership ID prefix</label>
                    <input class="form-control" name="membership_code_prefix" value="<?= e(get_setting('membership_code_prefix', 'PWF')) ?>">
                    <small class="form-hint">e.g. PWF → PWF-2026-00042</small></div>
                <div class="form-group"><label class="form-label">Default card template</label>
                    <select class="form-select" name="membership_card_template">
                        <?php foreach ($cardTemplates as $k => $l): ?><option value="<?= e($k) ?>" <?= get_setting('membership_card_template', 'classic') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="form-group"><label class="form-label">Reminder days before expiry</label>
                <input class="form-control" name="membership_reminder_days" value="<?= e(get_setting('membership_reminder_days', '30,15,7')) ?>">
                <small class="form-hint">Comma-separated, e.g. 30,15,7</small></div>
            <label class="checkbox"><input type="checkbox" name="membership_reminders_enabled" value="1" <?= (int) get_setting('membership_reminders_enabled', '1') === 1 ? 'checked' : '' ?>> Send expiry reminders</label>
        </div></div>

        <!-- Cashfree -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Cashfree payments</h3>
            <label class="checkbox mb-2"><input type="checkbox" name="cashfree_enabled" value="1" <?= (int) get_setting('cashfree_enabled', '0') === 1 ? 'checked' : '' ?>> Enable online renewals via Cashfree</label>
            <div class="form-group"><label class="form-label">Environment</label>
                <select class="form-select" name="cashfree_env">
                    <option value="sandbox" <?= get_setting('cashfree_env', 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (test)</option>
                    <option value="production" <?= get_setting('cashfree_env', 'sandbox') === 'production' ? 'selected' : '' ?>>Production (live)</option>
                </select></div>
            <div class="form-group"><label class="form-label">App ID</label>
                <input class="form-control" name="cashfree_app_id" value="<?= e(get_setting('cashfree_app_id', '')) ?>"></div>
            <div class="form-group"><label class="form-label">Secret key</label>
                <input class="form-control" type="password" name="cashfree_secret_key" placeholder="<?= e($mask('cashfree_secret_key') ?: 'Enter secret key') ?>"></div>
            <div class="form-group"><label class="form-label">Webhook secret</label>
                <input class="form-control" type="password" name="cashfree_webhook_secret" placeholder="<?= e($mask('cashfree_webhook_secret') ?: 'Optional (defaults to secret key)') ?>"></div>
            <small class="form-hint">Webhook URL: <code><?= e(abs_url('api/v1/cashfree-webhook')) ?></code></small>
        </div></div>

        <!-- SMS -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">SMS notifications</h3>
            <label class="checkbox mb-2"><input type="checkbox" name="sms_enabled" value="1" <?= (int) get_setting('sms_enabled', '0') === 1 ? 'checked' : '' ?>> Enable SMS reminders</label>
            <div class="form-group"><label class="form-label">Provider</label>
                <select class="form-select" name="sms_provider">
                    <option value="msg91" <?= get_setting('sms_provider', 'msg91') === 'msg91' ? 'selected' : '' ?>>MSG91</option>
                    <option value="fast2sms" <?= get_setting('sms_provider', 'msg91') === 'fast2sms' ? 'selected' : '' ?>>Fast2SMS</option>
                </select></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">MSG91 Auth Key</label>
                    <input class="form-control" type="password" name="msg91_authkey" placeholder="<?= e($mask('msg91_authkey') ?: 'Auth key') ?>"></div>
                <div class="form-group"><label class="form-label">MSG91 Sender ID</label>
                    <input class="form-control" name="msg91_sender" value="<?= e(get_setting('msg91_sender', '')) ?>"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">MSG91 DLT template id</label>
                    <input class="form-control" name="msg91_dlt_template_id" value="<?= e(get_setting('msg91_dlt_template_id', '')) ?>"></div>
                <div class="form-group"><label class="form-label">MSG91 route</label>
                    <input class="form-control" name="msg91_route" value="<?= e(get_setting('msg91_route', '4')) ?>"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Fast2SMS API key</label>
                    <input class="form-control" type="password" name="fast2sms_key" placeholder="<?= e($mask('fast2sms_key') ?: 'API key') ?>"></div>
                <div class="form-group"><label class="form-label">Fast2SMS sender</label>
                    <input class="form-control" name="fast2sms_sender" value="<?= e(get_setting('fast2sms_sender', '')) ?>"></div>
            </div>
        </div></div>

        <!-- Cron -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Reminder scheduler (cron)</h3>
            <p class="text-muted" style="font-size:.9rem;">Run this daily to send expiry reminders. Use Windows Task Scheduler locally, or a cron job / cron-URL service on your host.</p>
            <div class="form-group"><label class="form-label">CLI command (recommended)</label>
                <?php
                /* Use the interpreter actually running this request rather than a
                   hardcoded C:/xampp path, so the copy-paste command is correct on
                   the server it is being read on (Linux hosting included). */
                $phpBin = PHP_BINARY !== '' ? PHP_BINARY : 'php';
                $cronCmd = '"' . str_replace('/', DIRECTORY_SEPARATOR, $phpBin) . '" "' . $cronPath . '"';
                ?>
                <input class="form-control" readonly value="<?= e($cronCmd) ?>" onclick="this.select()"></div>
            <div class="form-group"><label class="form-label">Or trigger by URL (token-protected)</label>
                <input class="form-control" readonly value="<?= e($cronUrl) ?>" onclick="this.select()"></div>
            <label class="checkbox"><input type="checkbox" name="regen_token" value="1"> Regenerate the cron token</label>
        </div></div>
    </div>

    <div class="form-actions" style="margin-top:1rem;">
        <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save settings</button>
    </div>
</form>

<!-- Test SMS -->
<div class="panel" style="margin-top:1rem;"><div class="panel-body">
    <h3 class="mb-2">Send a test SMS</h3>
    <form method="post" action="<?= e(admin_url('membership-settings')) ?>" class="flex" style="gap:.5rem;align-items:end;">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="test_sms">
        <div class="form-group" style="margin:0;"><label class="form-label">Mobile number</label>
            <input class="form-control" name="test_number" placeholder="10-digit mobile" required></div>
        <button class="btn btn-outline" type="submit" <?= sms_enabled() ? '' : 'disabled title="Enable SMS and save first"' ?>><?= lucide('send') ?> Send test</button>
    </form>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
