<?php
/**
 * =============================================================================
 *  SEO — meta tags, Open Graph, Twitter cards, and JSON-LD schema
 * =============================================================================
 *  A page sets its metadata BEFORE including the header:
 *
 *      seo_set([
 *          'title'       => 'About Us',
 *          'description' => '...',
 *          'page_key'    => 'about',     // optional: pulls overrides from seo_meta
 *          'image'       => upload_url($x),
 *          'type'        => 'website',   // or 'article'
 *      ]);
 *
 *  The header then calls render_meta() to output the tags.
 * =============================================================================
 */

declare(strict_types=1);

/** Internal store for the current page's SEO data. */
function &seo_store(): array
{
    static $store = [];
    return $store;
}

/**
 * Absolutize a site URL for consumers that require it (Open Graph images,
 * JSON-LD): asset()/upload_url() return root-relative /pwf/… paths, which the
 * OG spec forbids and social scrapers cannot fetch.
 */
function seo_abs_url(?string $url): string
{
    $url = (string) $url;
    if ($url === '' || preg_match('#^https?://#i', $url)) {
        return $url;
    }
    $p = parse_url(APP_URL);
    $origin = ($p['scheme'] ?? 'http') . '://' . ($p['host'] ?? 'localhost') . (isset($p['port']) ? ':' . $p['port'] : '');
    if ($url[0] === '/') {
        return $origin . $url;
    }
    return abs_url($url);
}

/**
 * Canonical URL for the current request: the extensionless path plus the
 * identity-defining ?slug= param (detail pages), never search/filter params.
 * /index canonicalizes to the homepage.
 */
function seo_canonical_url(): string
{
    $path = ltrim(current_path(), '/');
    if ($path === 'index' || $path === 'index.php') {
        $path = '';
    }
    $slug = isset($_GET['slug']) && is_string($_GET['slug']) ? trim($_GET['slug']) : '';
    $suffix = ($slug !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $slug)) ? '?slug=' . rawurlencode($slug) : '';
    return abs_url($path) . $suffix;
}

/** Set/merge SEO data for the current page. */
function seo_set(array $data): void
{
    $store = &seo_store();
    $store = array_merge($store, $data);
}

/**
 * Resolve final SEO values by merging: defaults (settings) < seo_meta row < page overrides.
 */
function seo_resolve(): array
{
    $store = seo_store();

    $siteName = get_setting('site_name', SITE_NAME);
    $defaults = [
        'title'       => $siteName,
        'description' => get_setting('site_description', SITE_TAGLINE),
        'keywords'    => get_setting('site_keywords', 'NGO, Patna, Bihar, welfare, charity, donation, volunteer'),
        'image'       => get_setting('site_og_image') ? upload_url(get_setting('site_og_image')) : asset('images/logo-256.webp'),
        'canonical'   => seo_canonical_url(),
        'robots'      => 'index,follow',
        'type'        => 'website',
        'page_key'    => null,
    ];

    // Per-page overrides stored in the seo_meta table.
    $dbMeta = [];
    if (!empty($store['page_key'])) {
        try {
            $row = find_by('seo_meta', 'page_key', $store['page_key']);
            if ($row) {
                $dbMeta = array_filter([
                    'title'       => $row['meta_title'],
                    'description' => $row['meta_description'],
                    'keywords'    => $row['meta_keywords'],
                    'image'       => $row['og_image'] ? upload_url($row['og_image']) : null,
                    'canonical'   => $row['canonical'],
                    'robots'      => $row['robots'],
                    'schema_json' => $row['schema_json'],
                    'og_title'    => $row['og_title'],
                    'og_desc'     => $row['og_description'],
                ], static fn($v) => $v !== null && $v !== '');
            }
        } catch (Throwable $e) {
            // seo_meta table may be absent during install — ignore.
        }
    }

    $meta = array_merge($defaults, $dbMeta, array_filter($store, static fn($v) => $v !== null && $v !== ''));

    // Compose the final <title>.
    if (!empty($store['title']) && $store['title'] !== $siteName) {
        $meta['title'] = $store['title'] . ' — ' . $siteName;
    }
    return $meta;
}

/**
 * Output all SEO meta tags. Call once inside <head>.
 */
function render_meta(): string
{
    $m = seo_resolve();
    $t = static fn($v) => e($v);

    $out  = "\n";
    $out .= '<title>' . $t($m['title']) . "</title>\n";
    $out .= '<meta name="description" content="' . $t($m['description']) . "\">\n";
    if (!empty($m['keywords'])) {
        $out .= '<meta name="keywords" content="' . $t($m['keywords']) . "\">\n";
    }
    $out .= '<meta name="robots" content="' . $t($m['robots']) . "\">\n";
    $out .= '<link rel="canonical" href="' . $t($m['canonical']) . "\">\n";

    // Open Graph
    $ogTitle = $m['og_title'] ?? $m['title'];
    $ogDesc  = $m['og_desc']  ?? $m['description'];
    $out .= '<meta property="og:site_name" content="' . $t(get_setting('site_name', SITE_NAME)) . "\">\n";
    $out .= '<meta property="og:type" content="' . $t($m['type']) . "\">\n";
    $out .= '<meta property="og:title" content="' . $t($ogTitle) . "\">\n";
    $out .= '<meta property="og:description" content="' . $t($ogDesc) . "\">\n";
    $out .= '<meta property="og:url" content="' . $t($m['canonical']) . "\">\n";
    $out .= '<meta property="og:image" content="' . $t(seo_abs_url($m['image'])) . "\">\n";

    // Twitter
    $out .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $out .= '<meta name="twitter:title" content="' . $t($ogTitle) . "\">\n";
    $out .= '<meta name="twitter:description" content="' . $t($ogDesc) . "\">\n";
    $out .= '<meta name="twitter:image" content="' . $t(seo_abs_url($m['image'])) . "\">\n";

    // JSON-LD: Organization (site-wide) + any page-specific schema.
    $out .= json_ld_organization();
    if (!empty($m['schema_json'])) {
        $out .= '<script type="application/ld+json">' . $m['schema_json'] . "</script>\n";
    }
    return $out;
}

/**
 * JSON-LD Organization block (rendered on every page).
 */
function json_ld_organization(): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@type'    => 'NGO',
        'name'     => get_setting('site_name', SITE_NAME),
        'url'      => rtrim(APP_URL, '/') . '/',
        'email'    => get_setting('contact_email', SITE_EMAIL),
        'telephone'=> get_setting('contact_phone', SITE_PHONE),
        'logo'     => seo_abs_url(get_setting('site_logo') ? upload_url(get_setting('site_logo')) : asset('images/logo-256.webp')),
        'address'  => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => get_setting('contact_address', SITE_ADDRESS),
            'addressLocality' => 'Patna',
            'addressRegion'   => 'Bihar',
            'postalCode'      => '840007',
            'addressCountry'  => 'IN',
        ],
        'identifier' => get_setting('cin', SITE_CIN),
    ];
    $socials = get_all('social_links', ['status' => 1], 'sort_order ASC');
    if ($socials) {
        $data['sameAs'] = array_column($socials, 'url');
    }
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . "</script>\n";
}

/**
 * Build a BreadcrumbList JSON-LD block.
 * @param array $items [['label'=>'', 'url'=>''], ...]
 */
function json_ld_breadcrumb(array $items): string
{
    $list = [[
        '@type'    => 'ListItem',
        'position' => 1,
        'name'     => 'Home',
        'item'     => rtrim(APP_URL, '/') . '/',
    ]];
    $pos = 2;
    foreach ($items as $item) {
        $entry = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $item['label'] ?? ''];
        if (!empty($item['url'])) {
            $entry['item'] = preg_match('#^https?://#', $item['url']) ? $item['url'] : abs_url(ltrim($item['url'], '/'));
        }
        $list[] = $entry;
    }
    $data = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . "</script>\n";
}

/**
 * Build an Article JSON-LD block from a blog row.
 */
function json_ld_article(array $blog, ?string $authorName = null): string
{
    $iso = static fn(?string $d) => $d ? date('c', strtotime($d)) : null;
    $data = [
        '@context'      => 'https://schema.org',
        '@type'         => 'Article',
        'headline'      => $blog['title'] ?? '',
        'description'   => $blog['excerpt'] ?? '',
        'image'         => seo_abs_url(!empty($blog['featured_image']) ? upload_url($blog['featured_image']) : asset('images/logo-256.webp')),
        'datePublished' => $iso($blog['published_at'] ?? ($blog['created_at'] ?? null)),
        'dateModified'  => $iso($blog['updated_at'] ?? null),
        'author'        => ['@type' => 'Person', 'name' => $authorName ?: get_setting('site_name', SITE_NAME)],
        'publisher'     => [
            '@type' => 'Organization',
            'name'  => get_setting('site_name', SITE_NAME),
            'logo'  => ['@type' => 'ImageObject', 'url' => seo_abs_url(asset('images/logo-256.webp'))],
        ],
    ];
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . "</script>\n";
}

/**
 * Build an Event JSON-LD block from an event row (columns detected defensively).
 */
function json_ld_event(array $ev): string
{
    $img = $ev['image'] ?? $ev['featured_image'] ?? $ev['cover_image'] ?? null;
    $data = [
        '@context'            => 'https://schema.org',
        '@type'               => 'Event',
        'name'                => $ev['title'] ?? '',
        'description'         => strip_tags((string) ($ev['excerpt'] ?? $ev['description'] ?? '')),
        'startDate'           => $ev['start_date'] ?? $ev['event_date'] ?? $ev['starts_at'] ?? null,
        'endDate'             => $ev['end_date'] ?? $ev['ends_at'] ?? null,
        'eventStatus'         => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'image'               => seo_abs_url($img ? upload_url($img) : asset('images/logo-256.webp')),
        'organizer'           => [
            '@type' => 'Organization',
            'name'  => get_setting('site_name', SITE_NAME),
            'url'   => rtrim(APP_URL, '/') . '/',
        ],
    ];
    $place = $ev['venue'] ?? $ev['location'] ?? '';
    if ($place !== '') {
        $data['location'] = ['@type' => 'Place', 'name' => $place, 'address' => $place];
    }
    $data = array_filter($data, static fn ($v) => $v !== null && $v !== '');
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . "</script>\n";
}

/**
 * Build a Course JSON-LD block from a course row.
 */
function json_ld_course(array $course): string
{
    $img = $course['featured_image'] ?? $course['image'] ?? $course['thumbnail'] ?? null;
    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Course',
        'name'        => $course['title'] ?? '',
        'description' => strip_tags((string) ($course['short_description'] ?? $course['description'] ?? '')),
        'provider'    => [
            '@type'  => 'Organization',
            'name'   => get_setting('site_name', SITE_NAME),
            'sameAs' => rtrim(APP_URL, '/') . '/',
        ],
    ];
    if ($img) {
        $data['image'] = seo_abs_url(upload_url($img));
    }
    $data = array_filter($data, static fn ($v) => $v !== null && $v !== '');
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . "</script>\n";
}
