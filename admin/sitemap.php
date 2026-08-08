<?php
/**
 * =============================================================================
 *  Admin — Sitemap & Robots  (SPECIAL module).
 *  Two panels:
 *    1. Regenerate a static XML sitemap at BASE_PATH/sitemap.xml from all
 *       currently-published content (home, main static pages, programs,
 *       blogs, events, campaigns).
 *    2. Edit robots.txt (BASE_PATH/robots.txt) directly in a textarea.
 *  Both write to disk with @-guarded calls and report success/error via flash.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$sitemapPath = BASE_PATH . '/sitemap.xml';
$robotsPath  = BASE_PATH . '/robots.txt';

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

/* -------------------------------------------------------------- SAVE ROBOTS.TXT */
if (is_post() && post('_do') === 'save_robots') {
    require_csrf();
    // Normalise line endings; robots.txt is plain text (no tag stripping).
    $content = str_replace("\r\n", "\n", (string) post('robots', ''));
    $bytes   = @file_put_contents($robotsPath, $content);
    if ($bytes === false) {
        set_flash('error', 'Could not write robots.txt. Check file permissions on the site root.');
    } else {
        log_activity('update', 'sitemap', 'Updated robots.txt');
        set_flash('success', 'robots.txt saved.');
    }
    redirect('/admin/sitemap');
}

/* -------------------------------------------------------------- VIEW */
$page_title = 'Sitemap & Robots';

$sitemapExists = is_file($sitemapPath);
$sitemapMtime  = $sitemapExists ? filemtime($sitemapPath) : 0;
$sitemapSize   = $sitemapExists ? filesize($sitemapPath) : 0;

$defaultRobots = "User-agent: *\nAllow: /\n\nDisallow: /admin/\nDisallow: /includes/\nDisallow: /uploads/tmp/\n\nSitemap: " . abs_url('sitemap.xml') . "\n";
$robotsContent = is_file($robotsPath) ? (string) @file_get_contents($robotsPath) : $defaultRobots;

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Sitemap &amp; Robots</h1><span class="muted">SEO / Crawler files</span></div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('sitemap.xml')) ?>" target="_blank"><?= lucide('globe') ?> View sitemap.xml</a>
</div>

<div class="grid-2">
    <!-- Panel 1: Regenerate XML sitemap -->
    <div class="panel">
        <div class="panel-body">
            <h2 style="margin:0 0 4px;">Regenerate XML sitemap</h2>
            <p class="muted" style="margin:0 0 16px;">
                Writes a fresh <code>sitemap.xml</code> at the site root, listing the homepage,
                main static pages and every published program, blog, event and campaign.
            </p>

            <?php if ($sitemapExists): ?>
                <div class="data-toolbar" style="gap:8px;flex-wrap:wrap;">
                    <span class="pill pill-green">Generated</span>
                    <span class="muted">Last built: <strong><?= e(date('d M Y, g:i A', $sitemapMtime)) ?></strong></span>
                    <span class="muted">·</span>
                    <span class="muted"><?= e(human_filesize($sitemapSize)) ?></span>
                </div>
            <?php else: ?>
                <div class="data-toolbar">
                    <span class="pill pill-amber">Not generated yet</span>
                    <span class="muted">A dynamic fallback is served until you generate a static file.</span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(admin_url('sitemap')) ?>" style="margin-top:16px;">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="regenerate">
                <div class="form-actions" style="margin:0;">
                    <button class="btn btn-primary" type="submit"><?= lucide('refresh-cw') ?> Regenerate sitemap.xml</button>
                    <a class="btn btn-ghost" href="<?= e(url('sitemap.xml')) ?>" target="_blank">Open</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Panel 2: robots.txt editor -->
    <div class="panel">
        <div class="panel-body">
            <h2 style="margin:0 0 4px;">robots.txt</h2>
            <p class="muted" style="margin:0 0 16px;">
                Controls how search engines crawl the site. Saved to
                <code>robots.txt</code> at the site root.
                <a href="<?= e(url('robots.txt')) ?>" target="_blank">View live file ↗</a>
            </p>

            <form method="post" action="<?= e(admin_url('sitemap')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="save_robots">
                <div class="form-group">
                    <label class="form-label" for="robots">File contents</label>
                    <textarea class="form-textarea" id="robots" name="robots" spellcheck="false"
                              style="min-height:280px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;"><?= e($robotsContent) ?></textarea>
                    <p class="form-hint">One directive per line. Include a <code>Sitemap:</code> line pointing to your sitemap URL.</p>
                </div>
                <div class="form-actions" style="margin:0;">
                    <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save robots.txt</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
