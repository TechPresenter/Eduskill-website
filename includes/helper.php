<?php
/**
 * =============================================================================
 *  View helpers — URLs, assets, navigation state, breadcrumbs, images
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Build an internal URL relative to the site root (BASE_URI).
 *   url('about')            => /pwf/about
 *   url('blog-details?slug=x')
 */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $url  = BASE_URI . '/' . $path;
    return preg_replace('#(?<!:)//+#', '/', rtrim($url, '/')) ?: BASE_URI . '/';
}

/** Absolute URL (for canonical, OG, emails, sitemap). */
function abs_url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/** Admin-area URL. */
function admin_url(string $path = ''): string
{
    return url('admin/' . ltrim($path, '/'));
}

/**
 * Versioned asset URL under /assets.
 *   asset('css/tailwind.css') => /pwf/assets/css/tailwind.css?v=1.0.0
 */
function asset(string $path): string
{
    return ASSET_URI . '/' . ltrim($path, '/') . '?v=' . ASSET_VERSION;
}

/**
 * Public URL for an uploaded file (stored path is relative to /uploads).
 * Returns a placeholder when the path is empty.
 */
function upload_url(?string $path, string $placeholder = 'images/placeholder.svg'): string
{
    if (empty($path)) {
        return asset($placeholder);
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return UPLOAD_URI . '/' . ltrim($path, '/');
}

/**
 * URL for a SENSITIVE upload (applicant résumés and similar PII).
 *
 * Routes through admin/secure-file.php, which enforces require_admin() before
 * streaming the bytes, instead of upload_url()'s plain static /uploads path
 * that Apache serves to anyone holding the link. uploads/resumes/ additionally
 * carries its own deny .htaccess, so the static route is closed off entirely.
 */
function secure_upload_url(?string $path): string
{
    if (empty($path)) {
        return '#';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return admin_url('secure-file') . '?f=' . rawurlencode(ltrim($path, '/'));
}

/**
 * Render a country selector + phone input as one control.
 *
 * Emits a real <select> and a real <input>, so the form still submits without
 * JavaScript; assets/js/country-select.js upgrades it to a searchable listbox.
 * Three hidden fields carry the country through to the handler so the name,
 * ISO code and dial code are stored alongside the number.
 *
 *   country_field(['name' => 'phone', 'required' => true])
 *
 * Posts:  phone, phone_country_iso, phone_country_name, phone_country_dial
 */
function country_field(array $o = []): string
{
    require_once __DIR__ . '/countries.php';

    // Flag it so includes/footer.php emits the JS automatically. Pages often
    // have several exit branches (events.php has three separate footer
    // includes), and hand-placing the script before each one is easy to get
    // wrong — the field would render but stay un-enhanced.
    $GLOBALS['__pwf_country_used'] = true;

    $name     = $o['name']     ?? 'phone';
    $label    = $o['label']    ?? 'Mobile Number';
    $required = !empty($o['required']);
    $value    = (string) ($o['value'] ?? post($name, ''));
    $hint     = (string) ($o['hint'] ?? '');
    $id       = $o['id'] ?? ('cs_' . preg_replace('/[^a-z0-9_]/i', '_', $name) . '_' . substr(md5($name . mt_rand()), 0, 5));

    /* Ship the stylesheet with the first field on the page rather than relying
       on includes/header.php. The standalone auth pages (signup, login,
       forgot/reset-password) do not include that header, so the control
       rendered completely unstyled there — native <select> visible and no
       shared shell. Emitting it here makes the component self-contained, so it
       cannot be missed by any page that uses it. */
    static $cssOut = false;
    $cssLink = '';
    if (!$cssOut) {
        $cssOut  = true;
        $cssLink = '<link rel="stylesheet" href="' . e(asset('css/country-select.css')) . '">';
    }

    // Pre-selected country: a prior post, else the visitor's detected country.
    $iso = strtoupper((string) ($o['country'] ?? post($name . '_country_iso', '')));
    if ($iso === '' || !country_valid($iso)) {
        $iso = country_detect();
    }
    $c = country_get($iso);

    ob_start();
    echo $cssLink; ?>
    <div class="cs-field" data-country-select>
        <label class="cs-label" for="<?= e($id) ?>">
            <?= e($label) ?><?= $required ? '<span class="cs-req" aria-hidden="true">*</span>' : '' ?>
        </label>

        <div class="cs-control">
            <button type="button" class="cs-toggle" data-cs-toggle
                    aria-haspopup="listbox" aria-expanded="false"
                    aria-label="Country: <?= e($c['name']) ?> (+<?= e($c['dial']) ?>)">
                <span class="cs-flag" data-cs-flag><?= e($c['iso']) ?></span>
                <span data-cs-dial>+<?= e($c['dial']) ?></span>
                <svg class="cs-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>

            <!-- Real control for the no-JS case; the script keeps it in sync. -->
            <select class="cs-native" name="<?= e($name) ?>_country_iso" data-cs-select
                    aria-label="Country calling code" tabindex="-1">
                <?php foreach (countries_all() as $code => [$cn, $cd, , ]): ?>
                    <option value="<?= e($code) ?>"<?= $code === $c['iso'] ? ' selected' : '' ?>>
                        <?= e($cn) ?> (+<?= e($cd) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <input class="cs-phone" type="tel" id="<?= e($id) ?>" name="<?= e($name) ?>"
                   value="<?= e($value) ?>" data-cs-phone
                   <?= $required ? 'required' : '' ?>
                   inputmode="tel" autocomplete="tel-national">

            <div class="cs-pop" data-cs-pop hidden>
                <div class="cs-search-wrap">
                    <input type="text" class="cs-search" data-cs-search placeholder="Search country or code…"
                           aria-label="Search country" autocomplete="off">
                </div>
                <ul class="cs-list" data-cs-list role="listbox" aria-label="Countries"></ul>
            </div>
        </div>

        <!-- Carried through so the handler can store name + ISO + dial code. -->
        <input type="hidden" name="<?= e($name) ?>_country_name" value="<?= e($c['name']) ?>" data-cs-name>
        <input type="hidden" name="<?= e($name) ?>_country_dial" value="<?= e($c['dial']) ?>" data-cs-dialval>
        <input type="hidden" name="<?= e($name) ?>_iso_mirror"   value="<?= e($c['iso']) ?>" data-cs-iso>

        <span class="cs-error" id="<?= e($id) ?>_err" data-cs-error role="alert" aria-live="polite"></span>
        <?php if ($hint !== ''): ?><small class="cs-hint"><?= e($hint) ?></small><?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Read a submitted country selector back out of $_POST, validate the number,
 * and return columns ready to merge into a db_insert().
 *
 *   $c = country_capture('phone');
 *   if (!$c['ok']) { $errors[] = $c['error']; }
 *   db_insert('volunteers', ['name' => ..., 'phone' => $c['phone']] + $c['columns']);
 *
 * 'phone'   — the number as the visitor typed it (national digits)
 * 'e164'    — normalised +<dial><national>, handy for SMS/WhatsApp
 * 'columns' — ['country_name','country_iso','country_dial'] for the DB
 */
function country_capture(string $name = 'phone', bool $required = false): array
{
    require_once __DIR__ . '/countries.php';

    // Trust only the ISO code; the name and dial code are re-derived server-side
    // so a tampered hidden field cannot store a bogus country against a number.
    $iso = strtoupper((string) post($name . '_country_iso', ''));
    $res = country_validate_phone($iso, (string) post($name, ''), $required);

    return [
        'ok'      => $res['ok'],
        'error'   => $res['error'],
        'phone'   => $res['national'],
        'e164'    => $res['e164'],
        'columns' => [
            'country_name' => $res['name'],
            'country_iso'  => $res['iso'],
            'country_dial' => $res['dial'],
        ],
    ];
}

/**
 * One-time assets for country_field(). Call before </body> on any page with a
 * country selector; safe to call repeatedly.
 */
function country_field_assets(): string
{
    static $done = false;
    // No field on this page (or already emitted) — nothing to load.
    if ($done || empty($GLOBALS['__pwf_country_used'])) {
        return '';
    }
    $done = true;
    require_once __DIR__ . '/countries.php';
    return '<script type="application/json" id="cs-country-data">' . countries_json() . '</script>'
         . '<script src="' . e(asset('js/country-select.js')) . '" defer></script>';
}

/**
 * Image URL with graceful fallback to a placeholder asset.
 */
function image_url(?string $path, string $type = 'general'): string
{
    if (!empty($path)) {
        return upload_url($path);
    }
    $placeholders = [
        'avatar'  => 'images/avatar-placeholder.svg',
        'blog'    => 'images/blog-placeholder.svg',
        'general' => 'images/placeholder.svg',
    ];
    return asset($placeholders[$type] ?? $placeholders['general']);
}

/** The current request path relative to BASE_URI, without query string. */
function current_path(): string
{
    $uri  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $base = rtrim(BASE_URI, '/');
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    return '/' . trim($uri, '/');
}

/**
 * Is the given path the current page? Accepts 'about' or '/about' or 'about.php'.
 */
function is_current(string $path): bool
{
    $path = '/' . trim(str_replace('.php', '', $path), '/');
    $now  = str_replace('.php', '', current_path());
    if ($path === '/' || $path === '/index') {
        return $now === '/' || $now === '/index';
    }
    return $now === $path;
}

/**
 * Return $class when $path is the active page (for nav highlighting).
 */
function active_menu(string $path, string $class = 'is-active'): string
{
    return is_current($path) ? $class : '';
}

/**
 * Render a breadcrumb trail.
 * @param array $items List of ['label' => '', 'url' => '' (optional for last)]
 */
function breadcrumb(array $items): string
{
    $html = '<nav class="breadcrumb" aria-label="Breadcrumb"><ol>';
    $html .= '<li><a href="' . e(url('/')) . '">Home</a></li>';
    $count = count($items);
    foreach ($items as $i => $item) {
        $isLast = ($i === $count - 1);
        $label  = e($item['label'] ?? '');
        if (!$isLast && !empty($item['url'])) {
            $html .= '<li><a href="' . e($item['url']) . '">' . $label . '</a></li>';
        } else {
            $html .= '<li aria-current="page">' . $label . '</li>';
        }
    }
    $html .= '</ol></nav>';
    return $html;
}

/** Star-rating markup (filled/empty) for testimonials/feedback. */
function star_rating(int $rating, int $max = 5): string
{
    $rating = max(0, min($max, $rating));
    $html = '<span class="stars" aria-label="' . $rating . ' out of ' . $max . ' stars">';
    for ($i = 1; $i <= $max; $i++) {
        $html .= '<span class="star ' . ($i <= $rating ? 'is-filled' : '') . '"><i data-lucide="star"></i></span>';
    }
    return $html . '</span>';
}

/**
 * Return a value from a JSON column decoded as an array (safe).
 */
function json_column(?string $json): array
{
    if (empty($json)) {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Extract a YouTube video id from various URL formats (or return the id as-is).
 */
function youtube_id(?string $urlOrId): ?string
{
    if (empty($urlOrId)) {
        return null;
    }
    if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_\-]{11})#', $urlOrId, $m)) {
        return $m[1];
    }
    if (preg_match('/^[A-Za-z0-9_\-]{11}$/', $urlOrId)) {
        return $urlOrId;
    }
    return null;
}

/**
 * Social brand mark as inline SVG.
 *
 * Replaces the Font Awesome dependency: the whole 6.5.2 CDN stylesheet was
 * being pulled in for what amounted to a handful of social glyphs, which cost
 * an extra third-party request and a CSP allowance for cdn.jsdelivr.net. These
 * are self-contained (matching the project's no-CDN convention) and inherit
 * currentColor so they take brand colour from CSS like every other icon.
 *
 * Brand marks are solid by design — that is how the platforms specify them —
 * while the UI icon set (lucide) stays outlined. Unknown platforms fall back
 * to the outlined lucide link glyph.
 */
function social_svg(string $platform, string $class = 'soc-ico'): string
{
    $p = strtolower(trim($platform));
    $alias = [
        'fb' => 'facebook', 'insta' => 'instagram', 'yt' => 'youtube',
        'twitter' => 'x', 'tw' => 'x',
    ];
    $p = $alias[$p] ?? $p;

    $paths = [
        'facebook'  => 'M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.5-3.91 3.77-3.91 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.89h2.78l-.45 2.91h-2.33V22C18.34 21.21 22 17.06 22 12.06Z',
        'x'         => 'M17.53 3h3.02l-6.6 7.54L21.75 21h-5.9l-4.62-6.04L5.94 21H2.92l7.06-8.07L2.5 3h6.05l4.18 5.52L17.53 3Zm-1.06 16.2h1.67L7.6 4.72H5.81L16.47 19.2Z',
        'instagram' => 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9-.42-.42-.68-.82-.9-1.38-.16-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41 1.27-.06 1.65-.07 4.85-.07M12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.3-1.46.72-2.12 1.39C1.35 2.68.93 3.35.63 4.14.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.3.79.72 1.46 1.39 2.12.66.67 1.33 1.09 2.12 1.39.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56.79-.3 1.46-.72 2.12-1.39.67-.66 1.09-1.33 1.39-2.12.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91-.3-.79-.72-1.46-1.39-2.12C21.32 1.35 20.65.93 19.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0Zm0 5.84a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32ZM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm7.85-10.4a1.44 1.44 0 1 1-2.88 0 1.44 1.44 0 0 1 2.88 0Z',
        'linkedin'  => 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05a3.74 3.74 0 0 1 3.37-1.85c3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14ZM7.12 20.45H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.72v20.55C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.72C24 .77 23.2 0 22.22 0Z',
        'youtube'   => 'M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.5A3.02 3.02 0 0 0 .5 6.19C0 8.07 0 12 0 12s0 3.93.5 5.81a3.02 3.02 0 0 0 2.12 2.14c1.88.5 9.38.5 9.38.5s7.5 0 9.38-.5a3.02 3.02 0 0 0 2.12-2.14C24 15.93 24 12 24 12s0-3.93-.5-5.81ZM9.55 15.57V8.43L15.82 12l-6.27 3.57Z',
        'whatsapp'  => 'M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.42-.07-.12-.27-.2-.57-.35ZM12.04 21.5h-.01a9.4 9.4 0 0 1-4.79-1.31l-.34-.2-3.56.93.95-3.47-.22-.36a9.38 9.38 0 0 1-1.44-5.01c0-5.18 4.22-9.4 9.41-9.4a9.34 9.34 0 0 1 6.65 2.76 9.34 9.34 0 0 1 2.75 6.65c0 5.18-4.22 9.41-9.4 9.41ZM20.5 3.49A11.75 11.75 0 0 0 12.04 0C5.5 0 .18 5.32.18 11.86c0 2.09.55 4.13 1.59 5.93L.08 24l6.36-1.67a11.85 11.85 0 0 0 5.6 1.43h.01c6.53 0 11.85-5.32 11.85-11.86 0-3.17-1.23-6.15-3.47-8.4Z',
        'telegram'  => 'M23.91 3.79 20.3 20.84c-.25 1.21-.98 1.5-2 .94l-5.5-4.07-2.66 2.57c-.3.3-.55.56-1.1.56l.38-5.56 10.15-9.17c.44-.39-.1-.61-.68-.22L6.24 13.51.99 11.87c-1.14-.36-1.16-1.14.24-1.69l20.52-7.91c.95-.34 1.78.23 1.47 1.52Z',
        'pinterest' => 'M12 0C5.37 0 0 5.37 0 12c0 5.08 3.16 9.42 7.63 11.17-.11-.95-.2-2.4.04-3.44.22-.93 1.4-5.94 1.4-5.94s-.36-.72-.36-1.78c0-1.67.97-2.92 2.17-2.92 1.02 0 1.52.77 1.52 1.69 0 1.03-.66 2.57-1 4-.28 1.19.6 2.16 1.77 2.16 2.12 0 3.76-2.24 3.76-5.47 0-2.86-2.06-4.86-5-4.86-3.4 0-5.4 2.55-5.4 5.19 0 1.03.4 2.13.89 2.73.1.12.11.22.08.34l-.33 1.37c-.05.22-.17.27-.4.16-1.5-.7-2.43-2.89-2.43-4.65 0-3.77 2.74-7.25 7.9-7.25 4.15 0 7.38 2.96 7.38 6.91 0 4.12-2.6 7.44-6.2 7.44-1.21 0-2.35-.63-2.74-1.37l-.75 2.85c-.27 1.04-1 2.35-1.49 3.15A12 12 0 0 0 24 12c0-6.63-5.37-12-12-12Z',
        'github'    => 'M12 .3a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1 1.8 2.8 1.3 3.5 1 0-.8.4-1.3.7-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2 0-.4-.5-1.6.2-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0c2.3-1.5 3.3-1.2 3.3-1.2.7 1.6.2 2.8.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .3Z',
        'tiktok'    => 'M19.32 6.69a5.72 5.72 0 0 1-3.6-4.09A5.9 5.9 0 0 1 15.62 2h-3.1v13.42a3.46 3.46 0 0 1-6.22 2.08 3.46 3.46 0 0 1 4.4-5.14V9.17a6.59 6.59 0 0 0-5.65 11.5 6.59 6.59 0 0 0 10.5-5.3V8.9a8.79 8.79 0 0 0 5.13 1.64V7.44a5.28 5.28 0 0 1-1.36-.75Z',
    ];

    if (!isset($paths[$p])) {
        return lucide('link', $class);
    }
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="currentColor" '
         . 'width="18" height="18" aria-hidden="true" focusable="false">'
         . '<path d="' . $paths[$p] . '"/></svg>';
}

/**
 * Lucide UI icon markup: <i data-lucide="name"> (replaced with SVG on load).
 * Admin-entered icon values may be emoji/unicode rather than a Lucide slug —
 * data-lucide would render nothing for those, so render them as text instead.
 */
function lucide(string $name, string $class = ''): string
{
    if ($name !== '' && !preg_match('/^[a-z0-9][a-z0-9-]*$/', $name)) {
        return '<span class="' . e(trim('emoji-ico ' . $class)) . '" aria-hidden="true">' . e($name) . '</span>';
    }
    return '<i data-lucide="' . e($name) . '"' . ($class ? ' class="' . e($class) . '"' : '') . '></i>';
}

/**
 * Sliding-window rate limiter keyed by client IP + action, backed by small
 * JSON files under logs/throttle (no schema change, works on shared hosting).
 * Returns TRUE when the request is allowed, FALSE when over the limit.
 *
 *     if (!pwf_throttle('contact-form', 5, 300)) { ...reject... }
 *
 * ATOMIC. The read and the write happen inside ONE exclusive flock() on ONE
 * handle. The previous version read with file_get_contents() and wrote with
 * file_put_contents(..., LOCK_EX): the lock covered only the write, so N
 * concurrent callers all read the same pre-burst counter and all passed the
 * count() check — a limiter set to max=5 let 16 of 16 parallel callers through,
 * which defeats it entirely for anything an attacker can send in parallel
 * (member-code enumeration on /verify/member/{code} was the case that mattered).
 * Doing it under one lock costs a few hundred microseconds per call.
 *
 * KEYED ON THE HARDENED IP. sec_client_ip() only trusts X-Forwarded-For when
 * trust_proxy=1, so it is the right key both behind a proxy — where
 * REMOTE_ADDR is the SAME value for every visitor on earth, collapsing all of
 * them into one bucket (useless against an attacker, and a global
 * N-per-window denial of service against real users) — and without one, where
 * a forged header must not be able to mint a fresh bucket per request.
 *
 * $failOpen decides what happens when the counter CANNOT be maintained — a
 * read-only logs/ directory, a full disk, an flock() that never lands. The
 * default is TRUE (allow) because that is the right answer for a contact form
 * or a login: a broken log directory must not lock legitimate users out of the
 * site. It is the WRONG answer for an enumeration guard, where "cannot count"
 * silently becomes "unlimited attempts", so the verification endpoints pass
 * FALSE and refuse the lookup instead of counting nothing.
 */
function pwf_throttle(string $action, int $max = 5, int $windowSec = 300, bool $failOpen = true): bool
{
    if (function_exists('sec_client_ip')) {
        $ip = sec_client_ip();
    } elseif (function_exists('client_ip')) {
        $ip = client_ip();
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    $ip = $ip !== '' ? $ip : 'unknown';

    $dir = BASE_PATH . '/logs/throttle';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    pwf_throttle_gc($dir);

    $file = $dir . '/' . substr(hash('sha256', $action . '|' . $ip), 0, 24) . '.json';
    $now  = time();

    // 'c+' creates the file if missing and does NOT truncate, so the existing
    // window survives until we decide to rewrite it.
    $fh = @fopen($file, 'c+');
    if (!$fh) {
        return $failOpen;         // cannot account for it — see $failOpen above
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return $failOpen;
    }

    $raw  = (string) stream_get_contents($fh);
    $hits = json_decode($raw, true) ?: [];
    $hits = array_values(array_filter($hits, static fn ($t) => (int) $t > $now - $windowSec));

    $allowed = count($hits) < $max;
    if ($allowed) {
        $hits[] = $now;
    }

    // Rewrite even when rejecting: the filtered window is what the NEXT call
    // should see, and refreshing mtime is what keeps the GC below from
    // collecting a bucket that is still being hammered.
    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($hits));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    return $allowed;
}

/**
 * Collect abandoned throttle buckets. One file exists per (action, IP) pair and
 * nothing ever deleted them, so logs/throttle grew without bound — one inode per
 * distinct visitor per form, forever, on hosting that bills inodes.
 *
 * Sampled (1 call in 50) and capped per sweep so no single request pays for a
 * large directory. A bucket is stale when it has not been written for an hour,
 * which is longer than every window in the codebase (the longest is 900s).
 */
function pwf_throttle_gc(string $dir, int $ttl = 3600, int $maxPerSweep = 200): void
{
    if (random_int(1, 50) !== 1) {
        return;
    }
    $cut = time() - $ttl;
    $n   = 0;
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        if ($n >= $maxPerSweep) {
            break;
        }
        $n++;
        if (@filemtime($f) < $cut) {
            @unlink($f);
        }
    }
}

/**
 * Return the URL only when it is a safe external/site link (http, https, or
 * site-relative). Blocks javascript:, data: and other active schemes that
 * survive htmlspecialchars — use for any stored/user-supplied href.
 */
function safe_href(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    if ($url[0] === '/' && !str_starts_with($url, '//')) {
        return $url;
    }
    // Bare domain ("example.org") — normalise to https.
    if (preg_match('#^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(/|$)#i', $url)) {
        return 'https://' . $url;
    }
    return '';
}

/** Elements a rich-text field may contain. Anything else is unwrapped. */
function richtext_allowed_tags(): array
{
    return [
        'p','br','hr','strong','b','em','i','u','s','sub','sup','mark','small','abbr',
        'h1','h2','h3','h4','h5','h6','blockquote','pre','code',
        'ul','ol','li','dl','dt','dd',
        'a','img','figure','figcaption',
        'table','thead','tbody','tfoot','tr','th','td','caption','colgroup','col',
        'div','span','section','article',
    ];
}

/** Attributes allowed per element ('*' applies to every allowed element). */
function richtext_allowed_attrs(): array
{
    return [
        '*'     => ['class','id','title','dir','lang'],
        'a'     => ['href','target','rel'],
        'img'   => ['src','alt','width','height','loading','decoding'],
        'td'    => ['colspan','rowspan','headers'],
        'th'    => ['colspan','rowspan','scope','headers'],
        'col'   => ['span'],
        'colgroup' => ['span'],
        'ol'    => ['start','type'],
    ];
}

/**
 * Sanitise admin-authored rich-text HTML (WYSIWYG output) for public display.
 *
 * This is an ALLOW-LIST parser built on ext-dom, not a blacklist of dangerous
 * patterns. The previous regex approach was bypassable: it stripped event
 * handlers with `\son\w+=`, which requires whitespace before the attribute, so
 * `<svg/onload=alert(1)>` and `<body/onload=alert(1)>` passed through intact.
 * Blacklists lose this game in general — an allow-list cannot, because anything
 * not explicitly permitted is removed.
 *
 * Elements not on the allow-list are UNWRAPPED (their text is kept) rather than
 * deleted, so stripping a stray <div> never silently destroys an editor's copy.
 * Genuinely executable containers (script/style/iframe/object/embed/form/svg…)
 * are removed outright, contents included.
 */
function blog_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '' || !class_exists('DOMDocument')) {
        // Without ext-dom, fall back to escaping rather than emitting raw HTML.
        return $html === '' ? '' : nl2br(e($html));
    }

    $allowedTags  = array_flip(richtext_allowed_tags());
    $allowedAttrs = richtext_allowed_attrs();
    // Removed with their contents — never merely unwrapped.
    $strip = array_flip(['script','style','iframe','object','embed','form','svg','math',
                         'link','meta','base','template','noscript','frame','frameset','applet','audio','video','source']);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    // The XML prologue pins UTF-8 so Devanagari and accented text survive.
    $ok = $dom->loadHTML(
        '<?xml encoding="UTF-8"?><body>' . $html . '</body>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok) {
        return nl2br(e($html));                 // unparseable — escape it
    }

    $xpath = new DOMXPath($dom);
    /** @var DOMElement[] $nodes */
    $nodes = iterator_to_array($xpath->query('//*') ?: []);

    // Walk deepest-first so unwrapping a parent cannot skip its children.
    foreach (array_reverse($nodes) as $el) {
        if (!$el instanceof DOMElement || $el->parentNode === null) {
            continue;
        }
        $tag = strtolower($el->nodeName);
        if ($tag === 'body') {
            continue;
        }

        if (isset($strip[$tag])) {
            $el->parentNode->removeChild($el);   // drop element AND contents
            continue;
        }

        if (!isset($allowedTags[$tag])) {
            // Unwrap: keep the children, discard the element itself.
            while ($el->firstChild) {
                $el->parentNode->insertBefore($el->firstChild, $el);
            }
            $el->parentNode->removeChild($el);
            continue;
        }

        $permitted = array_merge($allowedAttrs['*'], $allowedAttrs[$tag] ?? []);
        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->nodeName);
            if (!in_array($name, $permitted, true)) {
                $el->removeAttribute($attr->nodeName);   // kills every on* handler
                continue;
            }
            if ($name === 'href' || $name === 'src') {
                $el->setAttribute($name, richtext_safe_url($attr->nodeValue ?? ''));
            }
        }
        // Anything opening a new tab must not be able to reach window.opener.
        if ($tag === 'a' && strtolower((string) $el->getAttribute('target')) === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return '';
    }
    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return $out;
}

/** Permit only safe URL schemes; anything else becomes an inert '#'. */
function richtext_safe_url(string $url): string
{
    $u = trim(preg_replace('/[\x00-\x20]/', '', $url) ?? $url);
    if ($u === '') {
        return '#';
    }
    // Relative, root-relative, anchor and protocol-relative URLs are fine.
    if (preg_match('#^(/|\./|\.\./|\#|\?)#', $u)) {
        return $u;
    }
    if (preg_match('#^(https?|mailto|tel):#i', $u)) {
        return $u;
    }
    // data: is allowed ONLY for inline images, never for scriptable types.
    if (preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $u)) {
        return $u;
    }
    // No scheme at all (e.g. "example.com/page") — treat as relative.
    if (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $u)) {
        return $u;
    }
    return '#';
}

/**
 * Render blog body content: sanitised HTML when it looks like rich text,
 * otherwise plain text with line breaks (legacy plain-text posts).
 */
function blog_render_content(?string $content): string
{
    $c = trim((string) $content);
    if ($c === '') {
        return '';
    }
    if (preg_match('/<(p|h[1-6]|ul|ol|li|blockquote|br|strong|em|b|i|a|img|figure|div|span)\b/i', $c)) {
        return blog_sanitize_html($c);
    }
    return nl2br(e($c));
}

/**
 * Render any CMS rich-text field for public display: sanitised HTML when the
 * value contains markup (CKEditor output), else plain text with line breaks.
 * Backward-compatible — safe to swap in for nl2br(e($x)) on legacy plain text.
 */
function rich_text(?string $content): string
{
    return blog_render_content($content);
}

/**
 * Heuristic spam check for a submitted blog comment. Returns true when the
 * comment should be auto-quarantined as spam instead of queued for review.
 */
function blog_comment_is_spam(string $name, string $comment, string $website = ''): bool
{
    $haystack = strtolower($name . ' ' . $comment . ' ' . $website);
    // 3+ links, or BBCode/markdown link spam.
    if (preg_match_all('#https?://#i', $comment . ' ' . $website) >= 3) return true;
    if (stripos($comment, '[url') !== false || preg_match('#\]\(https?://#i', $comment)) return true;
    // Common spam keywords.
    $bad = ['viagra', 'cialis', 'casino', ' porn', 'payday loan', 'forex', 'escort', 'replica watch',
            'buy cheap', 'crypto invest', 'bitcoin doubl', 'backlink', 'seo service', 'xxx', 'sex video'];
    foreach ($bad as $w) { if (strpos($haystack, $w) !== false) return true; }
    // Long runs of one repeated character (gibberish).
    if (preg_match('#(.)\1{11,}#u', $comment)) return true;
    return false;
}

/**
 * Live count of pending / actionable items per admin module.
 * Single source of truth for BOTH the sidebar badges and the topbar
 * notification bell. Each count is isolated so a missing table can never
 * break the menu. Memoised so a request queries each table only once.
 *
 * @return array<string,int>  module-slug => pending count
 */
function admin_pending_counts(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $c = static function (string $table, string $where): int {
        try { return db_count($table, $where); } catch (Throwable $e) { return 0; }
    };
    $cache = [
        'contact-messages'         => $c('contact_messages', "status='unread'"),
        'comments'                 => $c('blog_comments', "status='pending'"),
        'volunteers'               => $c('volunteers', "status='new'"),
        'internships'              => $c('internships', "status='new'"),
        'feedback'                 => $c('feedback', "status='new'"),
        'donations'                => $c('donations', "status='pending'"),
        'members'                  => $c('members', "status='pending'"),
        'event-registrations'      => $c('event_registrations', "status='pending'"),
        'job-applications'         => $c('job_applications', "status='new'"),
        'membership-applications'  => $c('membership_applications', "status='new'"),
        'membership-renewals'      => $c('membership_renewals', "status='pending'"),
        'scholarship-applications' => $c('scholarship_applications', "status='new'"),
        'partner-applications'     => $c('partner_applications', "status='new'"),
        'leave-requests'           => $c('leave_requests', "status='pending'"),
    ];
    return $cache;
}

/**
 * Actionable notifications for the topbar bell: only modules with a non-zero
 * pending count, richest first. Each item: slug, icon (Lucide), label, count, url.
 *
 * @return array<int,array{slug:string,icon:string,label:string,count:int,url:string}>
 */
function admin_notifications(): array
{
    $meta = [
        'contact-messages'         => ['mail',                'Unread contact messages'],
        'donations'                => ['coins',               'Pending donations'],
        'members'                  => ['id-card',             'Members awaiting approval'],
        'membership-applications'  => ['clipboard-pen',       'New membership applications'],
        'membership-renewals'      => ['receipt',             'Membership renewals due'],
        'event-registrations'      => ['ticket',              'New event registrations'],
        'scholarship-applications' => ['clipboard-list',      'New scholarship applications'],
        'job-applications'         => ['inbox',               'New job applications'],
        'partner-applications'     => ['handshake',           'New partner requests'],
        'leave-requests'           => ['plane',               'Pending leave requests'],
        'volunteers'               => ['hand-heart',          'New volunteer sign-ups'],
        'internships'              => ['graduation-cap',      'New internship requests'],
        'feedback'                 => ['message-square-text', 'New feedback received'],
        'comments'                 => ['message-square',      'Comments awaiting moderation'],
    ];
    $counts = admin_pending_counts();
    $out = [];
    foreach ($meta as $slug => [$icon, $label]) {
        $n = (int) ($counts[$slug] ?? 0);
        if ($n > 0) {
            $out[] = ['slug' => $slug, 'icon' => $icon, 'label' => $label, 'count' => $n, 'url' => admin_url($slug)];
        }
    }
    usort($out, static fn ($a, $b) => $b['count'] <=> $a['count']);
    return $out;
}

/** Total number of pending items across all modules (topbar bell badge). */
function admin_notifications_total(): int
{
    $sum = 0;
    foreach (admin_pending_counts() as $n) {
        $sum += (int) $n;
    }
    return $sum;
}

/**
 * The site logo URL, resolved from one place.
 *
 * Precedence: Theme Settings (brand.logo, or brand.logo_dark on a dark page)
 * -> Website Settings (site_logo) -> the shipped asset. The brand.* theme
 * tokens shipped as admin controls but nothing read them, and several
 * templates hardcoded the asset outright, so an uploaded logo appeared in
 * some places and not others. Every template now calls this.
 *
 * @param bool $dark prefer the dark-surface variant when one is configured
 */
function brand_logo_url(bool $dark = false): string
{
    $token = '';
    if (function_exists('theme_get')) {
        if ($dark) {
            $token = trim((string) theme_get('brand.logo_dark'));
        }
        if ($token === '') {
            $token = trim((string) theme_get('brand.logo'));
        }
    }
    if ($token !== '') {
        return upload_url($token);
    }
    $setting = trim((string) get_setting('site_logo', ''));
    if ($setting !== '') {
        return upload_url($setting);
    }
    return asset('images/logo-128.webp');
}
