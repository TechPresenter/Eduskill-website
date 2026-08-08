<?php
/**
 * =============================================================================
 *  Dynamic XML sitemap — served at /sitemap.xml (see .htaccess rewrite).
 *  Always reflects currently-published content.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

/** Collect URLs as [loc, lastmod, changefreq, priority]. */
$urls = [];
$add = static function (string $path, ?string $lastmod = null, string $freq = 'weekly', string $priority = '0.6') use (&$urls) {
    $urls[] = [
        'loc'      => abs_url(ltrim($path, '/')),
        'lastmod'  => $lastmod ? date('Y-m-d', strtotime($lastmod)) : date('Y-m-d'),
        'freq'     => $freq,
        'priority' => $priority,
    ];
};

// Static high-value pages.
$add('/', null, 'daily', '1.0');
foreach ([
    'about', 'our-story', 'mission-vision', 'leadership-team', 'management-body', 'team', 'ngo-details',
    'programs', 'projects', 'causes', 'schemes', 'campaigns', 'skill-development', 'achievements', 'certificates', 'courses',
    'gallery', 'media', 'news-media', 'blogs', 'success-stories', 'testimonials', 'resources',
    'events', 'calendar', 'volunteer', 'internship', 'membership', 'career', 'become-partner',
    'scholarship', 'verify-certificate', 'donate', 'contact', 'feedback', 'faqs',
    'privacy-policy', 'terms', 'refund-policy', 'disclaimer', 'cookie-policy', 'sitemap-page',
] as $p) {
    $add('/' . $p, null, 'weekly', '0.7');
}

// Dynamic published content.
try {
    foreach (db_all("SELECT slug, updated_at FROM programs WHERE status='active'") as $r) {
        $add('/programs?slug=' . $r['slug'], $r['updated_at'], 'monthly', '0.6');
    }
    foreach (db_all("SELECT slug, updated_at, published_at FROM blogs WHERE status='published'") as $r) {
        $add('/blog-details?slug=' . $r['slug'], $r['updated_at'] ?: $r['published_at'], 'monthly', '0.6');
    }
    foreach (db_all("SELECT slug, updated_at FROM events WHERE status='published'") as $r) {
        $add('/events?slug=' . $r['slug'], $r['updated_at'], 'weekly', '0.6');
    }
    foreach (db_all("SELECT slug, updated_at FROM campaigns WHERE status IN ('active','completed')") as $r) {
        $add('/campaigns?slug=' . $r['slug'], $r['updated_at'], 'weekly', '0.7');
    }
    foreach (db_all("SELECT slug, updated_at FROM schemes WHERE status='active'") as $r) {
        $add('/schemes?slug=' . $r['slug'], $r['updated_at'], 'monthly', '0.5');
    }
    foreach (db_all("SELECT slug, updated_at FROM careers WHERE status='open'") as $r) {
        $add('/career?slug=' . $r['slug'], $r['updated_at'], 'weekly', '0.5');
    }
    foreach (db_all("SELECT slug, updated_at FROM projects WHERE deleted_at IS NULL") as $r) {
        $add('/projects?slug=' . $r['slug'], $r['updated_at'], 'monthly', '0.6');
    }
    foreach (db_all("SELECT slug, updated_at FROM courses WHERE status='published'") as $r) {
        $add('/course?slug=' . $r['slug'], $r['updated_at'], 'monthly', '0.6');
    }
} catch (Throwable $e) {
    // Tables may be partly seeded; static URLs still emit.
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . e($u['loc']) . "</loc>\n";
    echo '    <lastmod>' . e($u['lastmod']) . "</lastmod>\n";
    echo '    <changefreq>' . e($u['freq']) . "</changefreq>\n";
    echo '    <priority>' . e($u['priority']) . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
