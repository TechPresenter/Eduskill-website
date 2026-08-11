<?php
/**
 * =============================================================================
 *  Admin — Tracking & Integrations (SPECIAL settings module).
 *  Stores marketing/analytics IDs in the 'tracking' settings group. The public
 *  layout (includes/header.php) emits the matching snippets via tracking_head()
 *  / tracking_body_open() in includes/tracking.php. Only ids are stored here;
 *  raw <head>/<body>/<footer> scripts live in admin/custom-code.php.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$fields = [
    'ga4_id' => [
        'label' => 'Google Analytics 4 — Measurement ID', 'placeholder' => 'G-XXXXXXXXXX',
        'hint'  => 'Loads gtag.js and configures GA4. Enhanced events fire through <code>pwfTrack()</code>.',
        'pattern' => '^G-[A-Z0-9]+$',
    ],
    'gtm_id' => [
        'label' => 'Google Tag Manager — Container ID', 'placeholder' => 'GTM-XXXXXX',
        'hint'  => 'Injects the GTM container in &lt;head&gt; + the &lt;noscript&gt; after &lt;body&gt;. Conversion events are pushed to <code>dataLayer</code>.',
        'pattern' => '^GTM-[A-Z0-9]+$',
    ],
    'meta_pixel_id' => [
        'label' => 'Meta (Facebook) Pixel ID', 'placeholder' => '000000000000000',
        'hint'  => 'Fires PageView automatically; Lead / Donate / Purchase fire on the relevant actions.',
        'pattern' => '^[0-9]+$',
    ],
    'clarity_id' => [
        'label' => 'Microsoft Clarity — Project ID', 'placeholder' => 'abcdef1234',
        'hint'  => 'Free heatmaps &amp; session recordings from Microsoft Clarity.',
        'pattern' => '^[a-z0-9]+$',
    ],
    'hotjar_id' => [
        'label' => 'Hotjar — Site ID', 'placeholder' => '1234567',
        'hint'  => 'Heatmaps &amp; recordings from Hotjar (numeric site id).',
        'pattern' => '^[0-9]+$',
    ],
];

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    foreach (array_keys($fields) as $key) {
        set_setting($key, trim((string) post($key, '')), 'tracking', 'text');
    }
    log_activity('update', 'settings', 'Updated tracking & integrations');
    set_flash('success', 'Tracking settings saved.');
    redirect('/admin/tracking');
}

$current = [];
foreach (array_keys($fields) as $key) {
    $current[$key] = (string) get_setting($key, '');
}
$activeCount = count(array_filter($current, static fn ($v) => trim($v) !== ''));

$page_title = 'Tracking & Integrations';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1><?= lucide('radar') ?> Tracking &amp; Integrations</h1><span class="muted">Marketing / Analytics, Tag Manager, Pixel &amp; Heatmaps</span></div>
    <div class="flex flex-wrap items-center gap-1">
        <span class="pill <?= $activeCount ? 'pill-green' : 'pill-gray' ?>"><?= (int) $activeCount ?> of <?= count($fields) ?> active</span>
        <a class="btn btn-secondary" href="<?= e(admin_url('custom-code')) ?>"><?= lucide('code-xml') ?> Custom Code</a>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title"><?= lucide('key-round') ?> Provider IDs</h2>
        <span class="pill <?= $activeCount ? 'pill-green' : 'pill-gray' ?>"><?= $activeCount ? (int) $activeCount . ' enabled' : 'None enabled' ?></span>
    </div>
    <form class="admin-form panel-body" method="post" action="<?= e(admin_url('tracking')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">

        <div class="alert alert-info">
            <strong><?= lucide('info') ?> How it works.</strong>
            Paste each provider's ID below — leave any blank to disable it. Snippets load on the
            <strong>public site only</strong> (never in this admin panel). A single helper,
            <code>window.pwfTrack('Lead'|'Donate'|'Purchase', {…})</code>, forwards conversions to
            GA4, GTM's dataLayer and the Meta Pixel at once. For raw custom scripts use
            <a href="<?= e(admin_url('custom-code')) ?>">Custom Code</a>.
        </div>

        <?php foreach ($fields as $key => $meta): ?>
            <?php $isOn = trim($current[$key]) !== ''; ?>
            <div class="form-group">
                <label class="form-label" for="fld-<?= e($key) ?>">
                    <?= e($meta['label']) ?>
                    <span class="pill <?= $isOn ? 'pill-green' : 'pill-gray' ?>"><?= $isOn ? 'On' : 'Off' ?></span>
                </label>
                <input class="form-control" id="fld-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($current[$key]) ?>"
                       placeholder="<?= e($meta['placeholder']) ?>" pattern="<?= e($meta['pattern']) ?>" spellcheck="false" autocomplete="off">
                <span class="form-hint"><?= $meta['hint'] ?></span>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save Tracking Settings</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
