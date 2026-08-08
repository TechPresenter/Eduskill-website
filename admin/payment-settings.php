<?php
/**
 * =============================================================================
 *  Admin — Payment & Donation Settings
 * =============================================================================
 *  Configure the online-donation payment gateways (Razorpay, Stripe, PayPal)
 *  and the donation / receipt / 80G tax details. Secret fields are preserved
 *  when left blank so live credentials are never accidentally wiped, and each
 *  gateway's webhook endpoint URL is shown read-only for easy copy/paste.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    $plain = [
        'razorpay_key_id'         => 'payments',
        'stripe_publishable'      => 'payments',
        'paypal_env'              => 'payments',
        'paypal_client_id'        => 'payments',
        'paypal_webhook_id'       => 'payments',
        'donation_receipt_prefix' => 'donations',
        'donation_currency'       => 'donations',
        'org_pan'                 => 'donations',
        'org_80g_number'          => 'donations',
        'org_12a_number'          => 'donations',
    ];
    foreach ($plain as $key => $group) {
        set_setting($key, clean(post($key)), $group, 'text');
    }

    $bools = [
        'razorpay_enabled' => 'payments',
        'stripe_enabled'   => 'payments',
        'paypal_enabled'   => 'payments',
    ];
    foreach ($bools as $key => $group) {
        set_setting($key, post($key) ? '1' : '0', $group, 'boolean');
    }

    // Secrets: only overwrite when a new value is supplied.
    $secrets = [
        'razorpay_key_secret'      => 'payments',
        'razorpay_webhook_secret'  => 'payments',
        'stripe_secret'            => 'payments',
        'stripe_webhook_secret'    => 'payments',
        'paypal_secret'            => 'payments',
    ];
    foreach ($secrets as $key => $group) {
        $val = (string) post($key);
        if ($val !== '') {
            set_setting($key, trim($val), $group, 'text');
        }
    }

    log_activity('update', 'payment-settings', 'Updated payment & donation settings');
    set_flash('success', 'Payment settings saved.');
    redirect('/admin/payment-settings');
}

$mask = static fn (string $key): string => get_setting($key, '') !== '' ? '•••••••• (saved)' : '';

$page_title = 'Payment Settings';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Payment &amp; Donation Settings</h1><span class="muted">Razorpay · Stripe · PayPal · receipts &amp; 80G</span></div>
</div>

<form method="post" action="<?= e(admin_url('payment-settings')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_do" value="save">

    <div class="grid-2" style="align-items:start;gap:1.25rem;">
        <!-- Razorpay -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Razorpay</h3>
            <label class="checkbox mb-2"><input type="checkbox" name="razorpay_enabled" value="1" <?= (int) get_setting('razorpay_enabled', '0') === 1 ? 'checked' : '' ?>> Enable donations via Razorpay</label>
            <div class="form-group"><label class="form-label">Key ID</label>
                <input class="form-control" name="razorpay_key_id" value="<?= e(get_setting('razorpay_key_id', '')) ?>"></div>
            <div class="form-group"><label class="form-label">Key secret</label>
                <input class="form-control" type="password" name="razorpay_key_secret" placeholder="<?= e($mask('razorpay_key_secret') ?: 'Enter key secret') ?>"></div>
            <div class="form-group"><label class="form-label">Webhook secret</label>
                <input class="form-control" type="password" name="razorpay_webhook_secret" placeholder="<?= e($mask('razorpay_webhook_secret') ?: 'Enter webhook secret') ?>"></div>
            <small class="form-hint">Webhook URL: <code><?= e(abs_url('api/v1/razorpay-webhook')) ?></code></small>
        </div></div>

        <!-- Stripe -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Stripe</h3>
            <label class="checkbox mb-2"><input type="checkbox" name="stripe_enabled" value="1" <?= (int) get_setting('stripe_enabled', '0') === 1 ? 'checked' : '' ?>> Enable donations via Stripe</label>
            <div class="form-group"><label class="form-label">Publishable key</label>
                <input class="form-control" name="stripe_publishable" value="<?= e(get_setting('stripe_publishable', '')) ?>"></div>
            <div class="form-group"><label class="form-label">Secret key</label>
                <input class="form-control" type="password" name="stripe_secret" placeholder="<?= e($mask('stripe_secret') ?: 'Enter secret key') ?>"></div>
            <div class="form-group"><label class="form-label">Webhook signing secret</label>
                <input class="form-control" type="password" name="stripe_webhook_secret" placeholder="<?= e($mask('stripe_webhook_secret') ?: 'Enter webhook secret') ?>"></div>
            <small class="form-hint">Webhook URL: <code><?= e(abs_url('api/v1/stripe-webhook')) ?></code></small>
        </div></div>

        <!-- PayPal -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">PayPal</h3>
            <label class="checkbox mb-2"><input type="checkbox" name="paypal_enabled" value="1" <?= (int) get_setting('paypal_enabled', '0') === 1 ? 'checked' : '' ?>> Enable donations via PayPal</label>
            <div class="form-group"><label class="form-label">Environment</label>
                <select class="form-select" name="paypal_env">
                    <option value="sandbox" <?= get_setting('paypal_env', 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (test)</option>
                    <option value="live" <?= get_setting('paypal_env', 'sandbox') === 'live' ? 'selected' : '' ?>>Live (production)</option>
                </select></div>
            <div class="form-group"><label class="form-label">Client ID</label>
                <input class="form-control" name="paypal_client_id" value="<?= e(get_setting('paypal_client_id', '')) ?>"></div>
            <div class="form-group"><label class="form-label">Secret</label>
                <input class="form-control" type="password" name="paypal_secret" placeholder="<?= e($mask('paypal_secret') ?: 'Enter secret') ?>"></div>
            <div class="form-group"><label class="form-label">Webhook ID</label>
                <input class="form-control" name="paypal_webhook_id" value="<?= e(get_setting('paypal_webhook_id', '')) ?>"></div>
            <small class="form-hint">Webhook URL: <code><?= e(abs_url('api/v1/paypal-webhook')) ?></code></small>
        </div></div>

        <!-- Donations / receipts -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Donations &amp; receipts</h3>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Receipt number prefix</label>
                    <input class="form-control" name="donation_receipt_prefix" value="<?= e(get_setting('donation_receipt_prefix', 'DON')) ?>">
                    <small class="form-hint">e.g. DON → DON-2026-00042</small></div>
                <div class="form-group"><label class="form-label">Currency code</label>
                    <input class="form-control" name="donation_currency" value="<?= e(get_setting('donation_currency', 'INR')) ?>">
                    <small class="form-hint">ISO code, e.g. INR</small></div>
            </div>
            <div class="form-group"><label class="form-label">Organisation PAN</label>
                <input class="form-control" name="org_pan" value="<?= e(get_setting('org_pan', '')) ?>"></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">80G registration number</label>
                    <input class="form-control" name="org_80g_number" value="<?= e(get_setting('org_80g_number', '')) ?>"></div>
                <div class="form-group"><label class="form-label">12A registration number</label>
                    <input class="form-control" name="org_12a_number" value="<?= e(get_setting('org_12a_number', '')) ?>"></div>
            </div>
            <small class="form-hint">Shown on donation receipts and 80G tax certificates.</small>
        </div></div>
    </div>

    <div class="form-actions" style="margin-top:1rem;">
        <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save settings</button>
    </div>
</form>

<?php include __DIR__ . '/partials/foot.php'; ?>
