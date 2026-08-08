<?php
/**
 * =============================================================================
 *  Admin — Custom Code (Custom CSS / JS)  (SPECIAL settings module).
 *  A single settings form that stores three raw code blocks in the 'custom'
 *  settings group:
 *      custom_css        -> injected inside a <style> tag in includes/header.php
 *      custom_js_head    -> injected inside a <script> in includes/header.php
 *      custom_js_footer  -> injected inside a <script> in includes/footer.php
 *  Those files already read + emit these values on every PUBLIC page, so this
 *  module is ONLY the editor + save handler. The code is trusted admin input
 *  and is stored VERBATIM (no clean()/strip_tags — that would corrupt CSS/JS).
 *  Follows the theme.php save pattern: CSRF, set_setting, activity log, flash,
 *  redirect; then the settings form.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

/*
 | Stored key => human label + textarea help. Each is saved to the 'custom'
 | group with type 'textarea'. Values are NOT sanitised: they are raw code the
 | trusted admin wants injected as-is on the public site.
 */
$fields = [
    'custom_css' => [
        'label' => 'Custom CSS',
        'hint'  => 'Injected inside a &lt;style&gt; tag in the site &lt;head&gt; on every public page. Do not include the &lt;style&gt; tags yourself.',
    ],
    'custom_js_head' => [
        'label' => 'Custom JavaScript (Head)',
        'hint'  => 'Injected inside a &lt;script&gt; tag in the &lt;head&gt;. Use for analytics/verification snippets that must load early. Do not include the &lt;script&gt; tags yourself.',
    ],
    'custom_js_body' => [
        'label' => 'Custom JavaScript (Body — after &lt;body&gt;)',
        'hint'  => 'Injected inside a &lt;script&gt; immediately after the opening &lt;body&gt; tag. Do not include the &lt;script&gt; tags yourself.',
    ],
    'custom_js_footer' => [
        'label' => 'Custom JavaScript (Footer)',
        'hint'  => 'Injected inside a &lt;script&gt; tag just before &lt;/body&gt;. Best place for most tracking/widget code. Do not include the &lt;script&gt; tags yourself.',
    ],
];

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    foreach (array_keys($fields) as $key) {
        // Store raw code verbatim — trusted admin input injected as-is.
        set_setting($key, (string) post($key, ''), 'custom', 'textarea');
    }

    log_activity('update', 'settings', 'Updated custom code (CSS/JS)');
    set_flash('success', 'Custom code saved.');
    redirect('/admin/custom-code');
}

/* -------------------------------------------------------------- CURRENT VALUES */
$current = [];
foreach (array_keys($fields) as $k) {
    $current[$k] = (string) get_setting($k, '');
}

$page_title = 'Custom Code';
include __DIR__ . '/partials/head.php';
?>
<style>
/* scoped to this page — monospace code editors */
.custom-code-page .code-editor {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
    font-size: 13px;
    line-height: 1.6;
    min-height: 220px;
    white-space: pre;
    tab-size: 4;
    -moz-tab-size: 4;
}
.custom-code-page .code-editor:first-of-type { min-height: 260px; }
</style>

<div class="admin-page-head">
    <div><h1>Custom Code</h1><span class="muted">Settings / Custom CSS &amp; JS</span></div>
</div>

<div class="panel custom-code-page">
    <form class="admin-form panel-body" method="post" action="<?= e(admin_url('custom-code')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">

        <div class="alert alert-warning">
            <strong><?= lucide('triangle-alert') ?> Advanced — trusted code only.</strong>
            Anything entered here is stored verbatim and <strong>injected on every public
            page</strong> exactly as written (CSS inside a <code>&lt;style&gt;</code> tag,
            JS inside a <code>&lt;script&gt;</code> tag). There is no sanitising or
            validation — a single mistake can break the layout or expose the site to
            script injection. Only paste code from sources you fully trust, and do not
            wrap it in <code>&lt;style&gt;</code>/<code>&lt;script&gt;</code> tags yourself.
        </div>

        <?php foreach ($fields as $key => $meta): ?>
            <div class="form-group">
                <label class="form-label" for="fld-<?= e($key) ?>"><?= e($meta['label']) ?></label>
                <textarea class="form-textarea code-editor" id="fld-<?= e($key) ?>" name="<?= e($key) ?>" spellcheck="false" autocomplete="off" autocapitalize="off" placeholder="/* Paste your code here… */"><?= e($current[$key]) ?></textarea>
                <span class="form-hint"><?= $meta['hint'] ?></span>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save Custom Code</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
