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
        /* Plain text, not pre-encoded: this label is printed through e(), so the
           entities have to live in the escaper's output, not in the constant. */
        'label' => 'Custom JavaScript (Body — after <body>)',
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

/* How many blocks are currently injected on the public site — display only, so the
   screen can say out loud that code IS live rather than looking like a blank form. */
$activeCount = count(array_filter($current, static fn ($v) => trim($v) !== ''));

$page_title = 'Custom Code';
include __DIR__ . '/partials/head.php';
?>
<?php /* `.code-editor` (+ the `.is-tall` height modifier) now lives once in
         assets/css/admin-pro.css. It used to be repeated verbatim here and in
         admin/robots.php and admin/seo.php; the three copies had already drifted
         (this one carried "SF Mono", robots.php added its own min-height, seo.php
         had neither white-space nor tab-size). */ ?>
<style>
/* Trust-boundary facts list. tailwind.css zeroes ul padding, so a bare list has
   no visible markers. Tokens only — no colour literal, radius or shadow. */
body.admin .cc-facts {
    display: grid;
    gap: var(--sp-2);
    margin: 0;
    padding-left: var(--sp-5);
    list-style: disc;
    color: var(--text-soft);
    font-size: .9rem;
    line-height: 1.6;
}
</style>

<div class="admin-page-head">
    <div><h1><?= lucide('code-xml') ?> Custom Code</h1><span class="muted">System / Custom CSS &amp; JS</span></div>
    <div class="flex flex-wrap items-center gap-1">
        <span class="pill <?= $activeCount ? 'pill-amber' : 'pill-gray' ?>"><?= $activeCount ? (int) $activeCount . ' of ' . count($fields) . ' blocks live' : 'No code injected' ?></span>
        <a class="btn btn-secondary" href="<?= e(admin_url('tracking')) ?>"><?= lucide('radar') ?> Tracking IDs</a>
    </div>
</div>

<?php /* TRUST BOUNDARY, stated on the screen rather than only in the file header.
         Every value below is echoed unescaped into the public page — see
         includes/header.php (custom_css, custom_js_head), includes/tracking.php
         (custom_js_body) and includes/footer.php (custom_js_footer). That is the
         feature, not a bug; the point of the panel is to say so out loud, name who
         can reach it, and say what happens if the code is wrong. */ ?>
<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title"><?= lucide('shield-alert') ?> What this screen can break</h2>
        <span class="pill pill-amber">Unescaped output</span>
    </div>
    <div class="panel-body">
        <div class="alert alert-warning">
            <strong><?= lucide('triangle-alert') ?> Code here runs on every public page, exactly as written.</strong>
            Nothing is sanitised, validated or escaped — that is deliberate, because escaping
            would corrupt CSS and JavaScript. It means this form is a script-injection channel
            by design, and anyone who can open this screen can run code in every visitor's
            browser: read cookies, rewrite the donate form, redirect traffic.
        </div>
        <ul class="cc-facts">
            <li><strong>Who can edit this:</strong> any signed-in admin whose role can reach
                <em>System → Custom CSS / JS</em>. Restrict the role rather than trusting the habit.</li>
            <li><strong>Where it lands:</strong> CSS in a <code>&lt;style&gt;</code> in
                <code>&lt;head&gt;</code>; the three JS blocks in <code>&lt;script&gt;</code> tags in
                <code>&lt;head&gt;</code>, just after <code>&lt;body&gt;</code>, and before
                <code>&lt;/body&gt;</code>. Never in this admin panel.</li>
            <li><strong>Do not include the tags:</strong> the wrapper
                <code>&lt;style&gt;</code>/<code>&lt;script&gt;</code> is added for you. Pasting your
                own closes the tag early and dumps raw code onto the page.</li>
            <li><strong>If a block breaks the site:</strong> clear that field here and save — the
                public page recovers on the next request. A JS syntax error in the head block can
                stop later scripts from running at all, so test on one page first.</li>
            <li><strong>Third-party snippets:</strong> a tag that loads a remote script hands that
                domain the same access. Prefer <a href="<?= e(admin_url('tracking')) ?>">Tracking &amp;
                Integrations</a> for GA4, GTM, Meta Pixel, Clarity and Hotjar — those are ID-only
                fields with no free-form code.</li>
        </ul>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title"><?= lucide('code-xml') ?> Code blocks</h2>
    </div>
    <form class="admin-form panel-body" method="post" action="<?= e(admin_url('custom-code')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">

        <?php foreach ($fields as $key => $meta): ?>
            <?php $isLive = trim($current[$key]) !== ''; ?>
            <div class="form-group">
                <label class="form-label" for="fld-<?= e($key) ?>">
                    <?= e($meta['label']) ?>
                    <span class="pill <?= $isLive ? 'pill-amber' : 'pill-gray' ?>"><?= $isLive ? 'Live on site' : 'Empty' ?></span>
                </label>
                <textarea class="form-textarea code-editor is-tall" id="fld-<?= e($key) ?>" name="<?= e($key) ?>" spellcheck="false" autocomplete="off" autocapitalize="off" placeholder="/* Paste your code here… */"><?= e($current[$key]) ?></textarea>
                <span class="form-hint"><?= $meta['hint'] ?></span>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save Custom Code</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
