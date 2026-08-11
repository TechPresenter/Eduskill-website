<?php
/**
 * =============================================================================
 *  Admin — Robots.txt editor (SPECIAL settings module).
 *  Edits the physical /robots.txt at the site root, with preset templates and
 *  a DB backup copy in settings. Trusted admin input, written verbatim.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$robotsFile = dirname(__DIR__) . '/robots.txt';
$sitemapUrl = rtrim(APP_URL, '/') . '/sitemap.xml';

/* "Disallow: /login/admin" is not redundant with "Disallow: /admin/".
   .htaccess aliases /login/admin -> admin/login.php, and a robots rule is a
   literal path prefix match, so /admin/ does not cover it. Without the second
   line the alias was a crawlable way into the one realm this file deliberately
   excludes. (admin/login.php also emits its own noindex,nofollow, so this is
   about scanners and crawl budget, not an indexed page.) */
$presets = [
    'standard'  => "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /login/admin\nDisallow: /forms/\nDisallow: /api/\n\nSitemap: {$sitemapUrl}\n",
    'allow_all' => "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /login/admin\n\nSitemap: {$sitemapUrl}\n",
    'block_all' => "User-agent: *\nDisallow: /\n",
];

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $content = str_replace("\r\n", "\n", (string) post('content', ''));
    set_setting('robots_txt', $content, 'seo', 'textarea');           // DB backup
    $wrote = @file_put_contents($robotsFile, $content);
    log_activity('update', 'seo', 'Updated robots.txt');
    if ($wrote === false) {
        set_flash('error', 'Saved a copy, but robots.txt is not writable on disk — check file permissions.');
    } else {
        set_flash('success', 'robots.txt updated and published.');
    }
    redirect('/admin/robots');
}

// Prefer the live file; fall back to the DB copy, then the standard preset.
$content = is_readable($robotsFile)
    ? (string) file_get_contents($robotsFile)
    : (string) (get_setting('robots_txt', '') ?: $presets['standard']);

$writable = is_writable($robotsFile) || is_writable(dirname($robotsFile));

$page_title = 'Robots.txt';
include __DIR__ . '/partials/head.php';
?>
<?php /* No page-local CSS. `.code-editor` (+ the `.is-tall` height modifier) is
         declared once in assets/css/admin-pro.css — it used to be repeated here and
         in admin/custom-code.php and admin/seo.php, and the three copies had already
         drifted apart. */ ?>
<div class="admin-page-head">
    <div><h1><?= lucide('bot') ?> Robots.txt</h1><span class="muted">Marketing &amp; SEO / Crawler rules</span></div>
    <div class="flex flex-wrap gap-1">
        <a class="btn btn-secondary" href="<?= e(admin_url('sitemap')) ?>"><?= lucide('map') ?> XML sitemap</a>
        <a class="btn btn-outline" href="<?= e($sitemapUrl) ?>" target="_blank" rel="noopener"><?= lucide('external-link') ?> View sitemap</a>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <?php /* h2, not h3: this is the first sub-level under the page <h1>, so an h3
                 here skips a level. .panel-title normalises every level to the same
                 type ramp, which is why the drift was invisible on screen. */ ?>
        <h2 class="panel-title"><?= lucide('file-code') ?> Crawler rules</h2>
        <span class="pill <?= $writable ? 'pill-green' : 'pill-amber' ?>"><?= $writable ? 'File writable' : 'Not writable' ?></span>
    </div>
    <form class="admin-form panel-body" method="post" action="<?= e(admin_url('robots')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">

        <?php if (!$writable): ?>
            <div class="alert alert-warning"><strong><?= lucide('triangle-alert') ?> Heads up.</strong> The <code>robots.txt</code> file is not writable — saves will be kept in the database but won't publish to disk until permissions are fixed.</div>
        <?php endif; ?>

        <div class="alert alert-info">
            <strong><?= lucide('info') ?> This is the only robots.txt editor.</strong>
            It writes the physical <code>robots.txt</code> at the site root and keeps a
            copy in settings as a backup. The
            <a href="<?= e(admin_url('sitemap')) ?>">XML Sitemap</a> screen generates
            <code>sitemap.xml</code> and no longer edits this file.
        </div>

        <div class="form-group">
            <label class="form-label">Presets</label>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline btn-sm" data-robots-preset="standard"><?= lucide('shield-check') ?> Standard (recommended)</button>
                <button type="button" class="btn btn-outline btn-sm" data-robots-preset="allow_all"><?= lucide('globe') ?> Allow all</button>
                <button type="button" class="btn btn-outline btn-sm" data-robots-preset="block_all"><?= lucide('ban') ?> Block all</button>
            </div>
            <span class="form-hint">Presets fill the editor below — review, then Save to publish. The <strong>Standard</strong> preset allows crawling but hides admin/forms/api and links your sitemap.</span>
        </div>

        <div class="form-group">
            <label class="form-label" for="robotsBox">robots.txt content</label>
            <textarea class="form-textarea code-editor is-tall" id="robotsBox" name="content" spellcheck="false" autocomplete="off"><?= e($content) ?></textarea>
            <span class="form-hint">Served at <code><?= e($sitemapUrl ? rtrim(APP_URL, '/') . '/robots.txt' : '/robots.txt') ?></code>.</span>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save &amp; Publish</button>
        </div>
    </form>
</div>

<script>
    (function () {
        var presets = <?= json_encode($presets, JSON_UNESCAPED_SLASHES) ?>;
        document.querySelectorAll('[data-robots-preset]').forEach(function (b) {
            b.addEventListener('click', function () {
                var box = document.getElementById('robotsBox');
                if (box && presets[b.dataset.robotsPreset] != null) box.value = presets[b.dataset.robotsPreset];
            });
        });
    })();
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
