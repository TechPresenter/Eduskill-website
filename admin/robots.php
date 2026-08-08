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

$presets = [
    'standard'  => "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /forms/\nDisallow: /api/\n\nSitemap: {$sitemapUrl}\n",
    'allow_all' => "User-agent: *\nAllow: /\n\nSitemap: {$sitemapUrl}\n",
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
<div class="admin-page-head">
    <div><h1>Robots.txt</h1><span class="muted">Marketing &amp; SEO / Crawler rules</span></div>
    <a class="btn btn-secondary" href="<?= e($sitemapUrl) ?>" target="_blank" rel="noopener"><?= lucide('map') ?> View sitemap</a>
</div>

<div class="panel">
    <form class="admin-form panel-body" method="post" action="<?= e(admin_url('robots')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">

        <?php if (!$writable): ?>
            <div class="alert alert-warning"><strong><?= lucide('triangle-alert') ?> Heads up.</strong> The <code>robots.txt</code> file is not writable — saves will be kept in the database but won't publish to disk until permissions are fixed.</div>
        <?php endif; ?>

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
            <textarea class="form-textarea code-editor" id="robotsBox" name="content" spellcheck="false" autocomplete="off" style="font-family:ui-monospace,Menlo,Consolas,monospace;min-height:280px;white-space:pre;"><?= e($content) ?></textarea>
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
