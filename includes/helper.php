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
 * Font Awesome brand <i> markup for a social platform name.
 * (Font Awesome is used ONLY for social icons; Lucide is used for UI icons.)
 */
function social_fa(string $platform): string
{
    $p = strtolower(trim($platform));
    $map = [
        'facebook' => 'fa-facebook-f', 'fb' => 'fa-facebook-f',
        'twitter' => 'fa-x-twitter', 'x' => 'fa-x-twitter',
        'instagram' => 'fa-instagram', 'insta' => 'fa-instagram',
        'linkedin' => 'fa-linkedin-in', 'youtube' => 'fa-youtube', 'yt' => 'fa-youtube',
        'whatsapp' => 'fa-whatsapp', 'telegram' => 'fa-telegram',
        'pinterest' => 'fa-pinterest-p', 'threads' => 'fa-threads', 'github' => 'fa-github',
        'tiktok' => 'fa-tiktok',
    ];
    $cls   = $map[$p] ?? 'fa-link';
    $style = ($cls === 'fa-link') ? 'fas' : 'fab';
    return '<i class="' . $style . ' ' . $cls . '" aria-hidden="true"></i>';
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
 */
function pwf_throttle(string $action, int $max = 5, int $windowSec = 300): bool
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir = BASE_PATH . '/logs/throttle';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . substr(hash('sha256', $action . '|' . $ip), 0, 24) . '.json';
    $now  = time();
    $hits = [];
    if (is_file($file)) {
        $hits = json_decode((string) @file_get_contents($file), true) ?: [];
    }
    $hits = array_values(array_filter($hits, static fn ($t) => (int) $t > $now - $windowSec));
    if (count($hits) >= $max) {
        return false;
    }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return true;
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
