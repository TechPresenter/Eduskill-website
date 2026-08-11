<?php
/**
 * =============================================================================
 *  Admin — XML Sitemap  (SPECIAL module).
 *  Regenerates a static XML sitemap at BASE_PATH/sitemap.xml from all currently
 *  published content (home, main static pages, programs, blogs, events,
 *  campaigns). Writes to disk with an @-guarded call and reports success/error
 *  via flash.
 *
 *  robots.txt is NOT edited here. This screen used to carry a second panel that
 *  wrote BASE_PATH/robots.txt with its own default template, while
 *  admin/robots.php wrote the SAME file with a different UI and a different
 *  default — whichever screen an admin saved last silently won, and only
 *  robots.php kept the settings-table backup copy. The duplicate half was
 *  removed; admin/robots.php is the single editor and is linked from here.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$sitemapPath = BASE_PATH . '/sitemap.xml';

/**
 * Build the sitemap XML string from published content.
 * Returns [xmlString, urlCount].
 *
 * @return array{0:string,1:int}
 */
function build_sitemap_xml(): array
{
    $urls = [];
    $add  = static function (string $path, ?string $lastmod, string $freq, string $priority) use (&$urls): void {
        $urls[] = [
            'loc'      => abs_url(ltrim($path, '/')),
            'lastmod'  => date('Y-m-d', $lastmod ? (strtotime($lastmod) ?: time()) : time()),
            'freq'     => $freq,
            'priority' => $priority,
        ];
    };

    // Home + main static pages.
    $add('/', null, 'daily', '1.0');
    foreach ([
        'about', 'our-story', 'mission-vision', 'leadership-team', 'team',
        'programs', 'causes', 'schemes', 'campaigns', 'skill-development',
        'achievements', 'certificates', 'gallery', 'media', 'blogs',
        'success-stories', 'testimonials', 'resources', 'events', 'calendar',
        'volunteer', 'internship', 'membership', 'career', 'become-partner',
        'scholarship', 'verify-certificate', 'donate', 'contact', 'feedback',
        'faqs', 'privacy-policy', 'terms', 'refund-policy', 'disclaimer',
        'cookie-policy', 'sitemap-page',
    ] as $p) {
        $add('/' . $p, null, 'weekly', '0.7');
    }

    // Dynamic published content (tables may be partly seeded — stay resilient).
    try {
        foreach (db_all("SELECT slug, updated_at FROM programs WHERE status='active' AND deleted_at IS NULL") as $r) {
            $add('/program/' . $r['slug'], $r['updated_at'], 'monthly', '0.6');
        }
        foreach (db_all("SELECT slug, updated_at, published_at FROM blogs WHERE status='published' AND deleted_at IS NULL") as $r) {
            $add('/blog/' . $r['slug'], $r['updated_at'] ?: $r['published_at'], 'monthly', '0.6');
        }
        foreach (db_all("SELECT slug, updated_at FROM events WHERE status='published' AND deleted_at IS NULL") as $r) {
            $add('/event/' . $r['slug'], $r['updated_at'], 'weekly', '0.6');
        }
        foreach (db_all("SELECT slug, updated_at FROM campaigns WHERE status <> 'draft' AND deleted_at IS NULL") as $r) {
            $add('/campaign/' . $r['slug'], $r['updated_at'], 'weekly', '0.7');
        }
    } catch (Throwable $e) {
        // Static URLs still emit even if a content table is missing.
    }

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . e($u['loc']) . "</loc>\n";
        $xml .= '    <lastmod>' . e($u['lastmod']) . "</lastmod>\n";
        $xml .= '    <changefreq>' . e($u['freq']) . "</changefreq>\n";
        $xml .= '    <priority>' . e($u['priority']) . "</priority>\n";
        $xml .= "  </url>\n";
    }
    $xml .= '</urlset>' . "\n";

    return [$xml, count($urls)];
}

/* -------------------------------------------------------------- REGENERATE SITEMAP */
if (is_post() && post('_do') === 'regenerate') {
    require_csrf();
    [$xml, $count] = build_sitemap_xml();
    $bytes = @file_put_contents($sitemapPath, $xml);
    if ($bytes === false) {
        set_flash('error', 'Could not write sitemap.xml. Check file permissions on the site root.');
    } else {
        log_activity('update', 'sitemap', 'Regenerated sitemap.xml (' . $count . ' URLs)');
        set_flash('success', 'Sitemap regenerated with ' . $count . ' URLs.');
    }
    redirect('/admin/sitemap');
}

/* -------------------------------------------------------------- VIEW */
$page_title = 'XML Sitemap';

$sitemapExists = is_file($sitemapPath);
$sitemapMtime  = $sitemapExists ? filemtime($sitemapPath) : 0;
$sitemapSize   = $sitemapExists ? filesize($sitemapPath) : 0;

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1><?= lucide('map') ?> XML Sitemap</h1><span class="muted">Marketing &amp; SEO / Crawler files</span></div>
    <div class="flex flex-wrap gap-1">
        <a class="btn btn-secondary" href="<?= e(admin_url('robots')) ?>"><?= lucide('bot') ?> Robots.txt</a>
        <a class="btn btn-outline" href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener"><?= lucide('external-link') ?> View sitemap.xml</a>
    </div>
</div>

<?php /* `grid grid-2`, not the bare `grid-2` this screen used to carry: tailwind.css
         puts display:grid on .grid and only the column count on .grid-2, and the
         .admin-form .grid-2 rule that supplies display:grid elsewhere does not apply
         here — so the two panels were stacking instead of sitting side by side. */ ?>
<div class="grid grid-2">
    <!-- Panel 1: Regenerate XML sitemap -->
    <div class="panel">
        <div class="panel-head">
            <h2 class="panel-title"><?= lucide('refresh-cw') ?> Regenerate XML sitemap</h2>
            <?php if ($sitemapExists): ?>
                <span class="pill pill-green">Generated</span>
            <?php else: ?>
                <span class="pill pill-amber">Not generated</span>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <p class="text-muted mb-2">
                Writes a fresh <code>sitemap.xml</code> at the site root, listing the homepage,
                main static pages and every published program, blog, event and campaign.
            </p>

            <?php if ($sitemapExists): ?>
                <div class="data-toolbar">
                    <span class="text-muted">Last built: <strong><?= e(date('d M Y, g:i A', $sitemapMtime)) ?></strong></span>
                    <span class="text-muted">·</span>
                    <span class="text-muted"><?= e(human_filesize($sitemapSize)) ?></span>
                </div>

                <form method="post" action="<?= e(admin_url('sitemap')) ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="regenerate">
                    <div class="flex flex-wrap items-center gap-1">
                        <button class="btn btn-primary" type="submit"><?= lucide('refresh-cw') ?> Regenerate sitemap.xml</button>
                        <a class="btn btn-ghost" href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener">Open</a>
                    </div>
                </form>
            <?php else: ?>
                <?php /* No static file on disk yet — the shared .empty-state shape.
                         Swap for the admin_ui.php empty-state helper once it ships. */ ?>
                <div class="empty-state">
                    <div class="icon"><?= lucide('map') ?></div>
                    <p class="es-title">No sitemap file yet</p>
                    <p class="es-text">A dynamic fallback is served until you generate a static
                        <code>sitemap.xml</code>. Generating one lets search engines fetch the
                        whole site in a single request.</p>
                    <div class="es-actions">
                        <form method="post" action="<?= e(admin_url('sitemap')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_do" value="regenerate">
                            <button class="btn btn-primary" type="submit"><?= lucide('refresh-cw') ?> Generate sitemap.xml</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Panel 2: pointer to the single robots.txt editor (this screen no longer edits it) -->
    <div class="panel">
        <div class="panel-head">
            <h2 class="panel-title"><?= lucide('bot') ?> Crawler rules (robots.txt)</h2>
        </div>
        <div class="panel-body">
            <p class="text-muted mb-2">
                <code>robots.txt</code> is edited on its own screen, which also keeps a backup
                copy in settings and offers ready-made presets. It used to be editable here as
                well — two forms writing one file — so that duplicate was removed.
            </p>
            <div class="flex flex-wrap items-center gap-1">
                <a class="btn btn-primary" href="<?= e(admin_url('robots')) ?>"><?= lucide('file-code') ?> Edit robots.txt</a>
                <a class="btn btn-ghost" href="<?= e(url('robots.txt')) ?>" target="_blank" rel="noopener"><?= lucide('external-link') ?> View live file</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
