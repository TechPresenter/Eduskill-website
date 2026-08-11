<?php
/**
 * =============================================================================
 *  Shared page header — opens <html>, <head> (SEO), <body>, announcement bar,
 *  navbar, and <main>. Pages include this after calling seo_set().
 *
 *  Optional page variables (set before including):
 *    $page_hero   => ['title' => '', 'subtitle' => '', 'breadcrumb' => [...]]
 * =============================================================================
 */
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

$flashes  = get_flashes();
$siteName = get_setting('site_name', SITE_NAME);
$logo     = get_setting('site_logo');

// Active announcement bar (optional).
$announcement = db_row(
    "SELECT * FROM announcements
     WHERE status = 1
       AND (start_date IS NULL OR start_date <= CURDATE())
       AND (end_date   IS NULL OR end_date   >= CURDATE())
     ORDER BY id DESC LIMIT 1"
);

/* ---- Admin-customizable typography + brand colours (from Settings) ---- */
$fontMap = [
    'Outfit'            => 'Outfit:wght@400;500;600;700;800;900',
    'Plus Jakarta Sans' => 'Plus+Jakarta+Sans:wght@400;500;600;700;800',
    'Inter'             => 'Inter:wght@400;500;600;700',
    'Manrope'           => 'Manrope:wght@400;500;600;700;800',
    'Poppins'           => 'Poppins:wght@400;500;600;700;800',
    'Montserrat'        => 'Montserrat:wght@400;500;600;700;800',
    'Lato'              => 'Lato:wght@400;700;900',
];
$headingFont = function_exists('theme_get') ? (theme_get('font.heading') ?: 'Plus Jakarta Sans') : get_setting('heading_font', 'Plus Jakarta Sans');
$bodyFont    = function_exists('theme_get') ? (theme_get('font.body') ?: 'Plus Jakarta Sans') : get_setting('body_font', 'Plus Jakarta Sans');
if (!isset($fontMap[$headingFont])) { $fontMap[$headingFont] = $headingFont; }
if (!isset($fontMap[$bodyFont]))    { $fontMap[$bodyFont] = $bodyFont; }
$fontFamilies = [];
foreach (array_unique([$headingFont, $bodyFont, 'Manrope']) as $f) {
    $fontFamilies[] = 'family=' . $fontMap[$f];
}
$fontUrl = 'https://fonts.googleapis.com/css2?' . implode('&', $fontFamilies) . '&display=swap';

/* The three legacy `settings` colour keys (theme_primary_color / _secondary_ /
   _accent_) were read here into variables that nothing ever consumed — three
   queries per page render for values that never reached the output. They also
   still hold the pre-rebrand navy palette (#276db0 / #123740), so anyone
   reading this block would reasonably conclude the site colour came from there.
   Colour now comes exclusively from the Theme Engine (theme_settings, 69
   tokens) via theme_get() / theme_style_tag(), the same source the fonts on
   lines 38-39 already use. */
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>" dir="<?= e(current_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= e(theme_get('adv.theme_color') ?: theme_get('color.primary') ?: '#0B4E3D') ?>">
    <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= e(get_setting('site_name', SITE_NAME)) ?>">
    <?= csrf_meta() ?>
    <?= render_meta() ?>

    <!-- Prevent theme flash: apply saved/system theme before paint -->
    <script>
        (function () {
            try {
                var t = localStorage.getItem('pwf-theme');
                if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>

    <?php
    /* Favicon / app icon come from Theme Settings when set, falling back to the
       shipped asset. These were previously hardcoded, so uploading a favicon or
       app icon through Theme Settings had no effect anywhere on the site. */
    $themeIcon = trim((string) theme_get('brand.favicon'));
    $themeApp  = trim((string) theme_get('brand.app_icon')) ?: $themeIcon;
    $iconHref  = $themeIcon !== '' ? upload_url($themeIcon) : asset('images/favicon.jpg');
    $appHref   = $themeApp  !== '' ? upload_url($themeApp)  : asset('images/favicon.jpg');
    $iconType  = str_ends_with(strtolower($iconHref), '.png') ? 'image/png'
               : (str_ends_with(strtolower($iconHref), '.webp') ? 'image/webp'
               : (str_ends_with(strtolower($iconHref), '.svg') ? 'image/svg+xml' : 'image/jpeg'));
    ?>
    <link rel="icon" href="<?= e($iconHref) ?>" type="<?= e($iconType) ?>">
    <link rel="apple-touch-icon" href="<?= e($appHref) ?>">

    <!-- Google Fonts (admin-selectable) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php /* Fonts are emitted by the Theme Engine (theme_style_tag) — a second
             request here would be render-blocking duplication. */ ?>

    <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
    <?php /* country-select.css is emitted by country_field() itself, so pages
             that do not use this header (the standalone auth pages) still get
             it. Loading it here too would duplicate the request. */ ?>
    <link rel="stylesheet" href="<?= e(asset('css/blocks.css')) ?>">
    <?php /* Design system loads last so its scales win over the accumulated
             per-component radii and shadows in the stylesheets above. */ ?>
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">

    <?php /* Admin-authored custom code (trusted) */
    $customCss = (string) get_setting('custom_css', '');
    $customJsHead = (string) get_setting('custom_js_head', '');
    if (trim($customCss) !== ''): ?><style id="pwf-custom-css"><?= $customCss ?></style><?php endif;
    if (trim($customJsHead) !== ''): ?><script id="pwf-custom-js-head"><?= $customJsHead ?></script><?php endif; ?>

    <?php /* Analytics & marketing tags (GA4 / GTM / Meta Pixel / Clarity / Hotjar) */ ?>
    <?= tracking_head() ?>

    <!-- Font Awesome (social icons only) — loaded non-render-blocking; the
         page is fully usable before (or without) it. -->
    <?php /* Font Awesome removed: the entire 6.5.2 stylesheet was loaded from a
             third-party CDN for a handful of social glyphs. Those are now inline
             SVG (social_svg() in includes/helper.php), leaving lucide as the one
             icon family — one less external request, one less CSP allowance. */ ?>

    <!-- Mark JS availability so CSS-only visitors keep scroll-reveal content visible -->
    <script>document.documentElement.classList.add('js');</script>

    <?php /* Global Theme Engine — every design token as CSS custom properties.
             Emitted last so it wins the cascade over the base stylesheets. */
    echo function_exists('theme_style_tag') ? theme_style_tag('site') : ''; ?>
</head>
<body>
    <?= tracking_body_open() ?>
    <a class="skip-link" href="#main-content">Skip to content</a>

    <?php if ($announcement): ?>
    <?php
    /* Premium announcement bar. If the admin left the default colours we render
       the animated brand gradient; any custom colour they pick still wins via
       the inline custom properties below. */
    $annBg      = trim((string) ($announcement['bg_color'] ?? ''));
    $annFg      = trim((string) ($announcement['text_color'] ?? ''));
    $annCustom  = ($annBg !== '' && strtolower($annBg) !== '#111827');
    $annStyle   = ($annCustom ? '--ann-bg:' . e($annBg) . ';' : '') . ($annFg !== '' ? '--ann-fg:' . e($annFg) . ';' : '');
    ?>
    <div class="announcement-bar ann-premium<?= $annCustom ? ' ann-solid' : '' ?>" id="announcementBar"
         role="region" aria-label="Site announcement"
         data-ann-id="<?= (int) $announcement['id'] ?>"<?= $annStyle !== '' ? ' style="' . $annStyle . '"' : '' ?>>
        <div class="ann-inner">
            <span class="ann-text"><?= e($announcement['message']) ?></span>
            <?php if (!empty($announcement['link_url'])):
                // Stored value may be a bare slug ("events") — resolve site-relative
                // so it works from any page depth; absolute URLs pass through.
                $annHref = preg_match('#^https?://#i', $announcement['link_url'])
                    ? $announcement['link_url'] : url(ltrim($announcement['link_url'], '/')); ?>
                <a class="ann-cta" href="<?= e($annHref) ?>"><?= e($announcement['link_text'] ?: 'Learn more') ?></a>
                <span class="ann-doodle" aria-hidden="true">
                    <svg viewBox="0 0 46 22" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 11c6-7 15-9 24-6 5 2 8 6 6 9-2 2-6 1-6-3 1-5 7-8 16-8"/>
                        <path d="M38 3l4 0.2-0.6 4"/>
                    </svg>
                </span>
            <?php endif; ?>
        </div>
        <button class="ann-close" type="button" aria-label="Dismiss announcement" data-ann-close>&times;</button>
    </div>
    <script>
    (function () {
        var b = document.getElementById('announcementBar');
        if (!b) return;
        var x = b.querySelector('[data-ann-close]');
        if (x) x.addEventListener('click', function () {
            try { localStorage.setItem('pwf-ann-' + b.dataset.annId, '1'); } catch (e) {}
            b.style.height = b.offsetHeight + 'px';
            requestAnimationFrame(function () { b.classList.add('is-dismissed'); });
            setTimeout(function () { b.remove(); }, 320);
        });
    })();
    </script>
    <script>(function(){var b=document.getElementById('announcementBar');try{if(b&&localStorage.getItem('pwf-ann-'+b.dataset.annId))b.remove();}catch(e){}})();</script>
    <?php endif; ?>

    <?php include __DIR__ . '/navbar.php'; ?>

    <?php if (!empty($flashes)): ?>
    <div class="flash-stack" aria-live="polite">
        <?php foreach ($flashes as $flashType => $flashList): foreach ($flashList as $msg): ?>
            <div class="flash-toast <?= e($flashType) ?>"><?= e($msg) ?></div>
        <?php endforeach; endforeach; ?>
    </div>
    <?php endif; ?>

    <main id="main-content">
    <?php if (!empty($page_hero)): ?>
    <section class="page-hero">
        <div class="container">
            <?php if (!empty($page_hero['breadcrumb'])) echo breadcrumb($page_hero['breadcrumb']); ?>
            <h1><?= e($page_hero['title'] ?? '') ?></h1>
            <?php if (!empty($page_hero['subtitle'])): ?>
                <p class="lead text-white" style="opacity:.92;max-width:640px;"><?= e($page_hero['subtitle']) ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

