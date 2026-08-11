<?php
/**
 * =============================================================================
 *  Navbar — glassmorphism sticky bar. Menu items come from the `menus` table
 *  (location header/both, top level = parent_id NULL). Falls back to a sensible
 *  default set so the site is navigable before any menu is seeded.
 * =============================================================================
 */
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

/**
 * Resolve a stored menu url. Convention:
 *   '#'                       -> in-page/no link
 *   'http(s)://...'           -> external, opens as stored
 *   'about' | '/about'        -> internal, routed through url()
 */
if (!function_exists('nav_href')) {
    function nav_href(?string $u): string
    {
        $u = trim((string) $u);
        if ($u === '' || $u === '#') return '#';
        if (preg_match('#^https?://#i', $u)) return $u;

        // Defensive: never emit a local filesystem path as a link. A menu row
        // once held the literal 'C:/Program Files/Git/' (a shell mangling a bare
        // '/' argument), which url() happily turned into
        // /pwf/C:/Program%20Files/Git and broke the Home link sitewide. Treat any
        // Windows drive path, UNC path or MSYS-style /c/ prefix as "site root"
        // rather than rendering a dead link.
        if (preg_match('#^(?:[A-Za-z]:[\\\\/]|\\\\\\\\|/[A-Za-z]/(?:Program |Windows|Users)|file://)#', $u)) {
            return url('/');
        }
        return url($u);
    }
}

/**
 * Translated label for a menu row.
 *
 * Menu titles live in the `menus` table and were echoed raw, so switching to
 * Hindi left the whole navigation in English. The key is derived from the TITLE
 * rather than the url, because several rows deliberately share a target —
 * "About" and its child "Who We Are" both point at /about — and keying on the
 * url would collapse them onto one translation. Falls back to the
 * admin-entered title whenever no translation exists, so custom menu items and
 * newly added pages keep working untouched.
 *
 *   "Mission & Vision" -> nav.mission_vision
 *   "Who We Are"       -> nav.who_we_are
 */
if (!function_exists('nav_label')) {
    function nav_label(array $m): string
    {
        $title = trim((string) ($m['title'] ?? ''));
        if ($title === '') {
            return '';
        }
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $title) ?? '');
        $slug = trim($slug, '_');
        return $slug === '' ? $title : t('nav.' . $slug, $title);
    }
}

// Load header menus (with one level of children).
$headerMenus = [];
try {
    $tops = db_all(
        "SELECT * FROM menus
         WHERE status = 1 AND location IN ('header','both') AND parent_id IS NULL
         ORDER BY sort_order ASC, id ASC"
    );
    foreach ($tops as $top) {
        $top['children'] = db_all(
            "SELECT * FROM menus WHERE status = 1 AND parent_id = :pid ORDER BY sort_order ASC, id ASC",
            [':pid' => $top['id']]
        );
        $headerMenus[] = $top;
    }
} catch (Throwable $e) {
    $headerMenus = [];
}

// Social links for the top utility bar (best-effort).
$navSocials = [];
try {
    $navSocials = db_all("SELECT * FROM social_links WHERE status = 1 ORDER BY sort_order ASC");
} catch (Throwable $e) {}
$navSocialIcons = ['facebook' => 'fa-facebook-f', 'twitter' => 'fa-x-twitter', 'x' => 'fa-x-twitter', 'instagram' => 'fa-instagram', 'linkedin' => 'fa-linkedin-in', 'youtube' => 'fa-youtube', 'whatsapp' => 'fa-whatsapp'];
$navEmail = get_setting('contact_email', SITE_EMAIL);
$navPhone = get_setting('contact_phone', SITE_PHONE);

// Fallback default navigation.
if (empty($headerMenus)) {
    $headerMenus = [
        ['title' => 'Home', 'url' => '/', 'children' => []],
        ['title' => 'About', 'url' => 'about', 'children' => [
            ['title' => 'Who We Are', 'url' => 'about'],
            ['title' => 'Mission & Vision', 'url' => 'mission-vision'],
            ['title' => 'Management Body', 'url' => 'management-body'],
            ['title' => 'NGO Details', 'url' => 'ngo-details'],
            ['title' => 'Our Team', 'url' => 'team'],
        ]],
        ['title' => 'Programs', 'url' => 'programs', 'children' => [
            ['title' => 'All Programs', 'url' => 'programs'],
            ['title' => 'Skill Development', 'url' => 'skill-development'],
            ['title' => 'Campaigns', 'url' => 'campaigns'],
            ['title' => 'Achievements', 'url' => 'achievements'],
        ]],
        ['title' => 'Media', 'url' => 'gallery', 'children' => [
            ['title' => 'Gallery', 'url' => 'gallery'],
            ['title' => 'Videos', 'url' => 'media'],
            ['title' => 'Blogs', 'url' => 'blogs'],
            ['title' => 'Success Stories', 'url' => 'success-stories'],
            ['title' => 'Resources', 'url' => 'resources'],
        ]],
        ['title' => 'Events', 'url' => 'events', 'children' => [
            ['title' => 'Events', 'url' => 'events'],
            ['title' => 'Awareness Calendar', 'url' => 'calendar'],
        ]],
        ['title' => 'Get Involved', 'url' => 'volunteer', 'children' => [
            ['title' => 'Volunteer', 'url' => 'volunteer'],
            ['title' => 'Internship', 'url' => 'internship'],
            ['title' => 'Feedback', 'url' => 'feedback'],
        ]],
        ['title' => 'Contact', 'url' => 'contact', 'children' => []],
    ];
}
?>
<!-- Top utility bar -->
<?php if (get_setting('topbar_enabled', '1') === '1'): ?>
<div class="topbar-utility">
    <div class="container topbar-utility-inner">
        <div class="tu-left">
            <a href="mailto:<?= e($navEmail) ?>"><?= lucide('mail') ?> <span class="tu-hide-sm"><?= e($navEmail) ?></span></a>
            <span class="tu-sep"></span>
            <a href="tel:<?= e(preg_replace('/\s+/', '', $navPhone)) ?>"><?= lucide('phone') ?> <?= e($navPhone) ?></a>
        </div>
        <div class="tu-right">
            <span class="tu-note"><?php
                $tuTagline = get_setting('site_tagline', SITE_TAGLINE);
                $tuParts = array_filter(array_map('trim', preg_split('/[•·|]/u', $tuTagline)));
                echo implode('<span class="tu-dot" aria-hidden="true"></span>', array_map('e', $tuParts));
            ?></span>
            <?php if (get_setting('topbar_show_lang', '1') === '1'): ?>
            <?php /* Google Translate language selector (English <-> Hindi only).
                     The visible control is ours; Google's own <select> is kept in
                     the DOM but visually hidden, because driving it is the only
                     supported way to translate without a reload. Markup + script
                     live in includes/gtranslate.php. */ ?>
            <?php include __DIR__ . '/gtranslate.php'; ?>
            <?php endif; ?>
            <div class="tu-social">
                <?php if ($navSocials): foreach ($navSocials as $s): ?>
                    <a href="<?= e(safe_href($s['url'])) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['platform']) ?>"><?= social_svg($s['platform']) ?></a>
                <?php endforeach; else: ?>
                    <a href="#" aria-label="Facebook"><?= social_svg('facebook') ?></a>
                    <a href="#" aria-label="Twitter"><?= social_svg('x') ?></a>
                    <a href="#" aria-label="Instagram"><?= social_svg('instagram') ?></a>
                    <a href="#" aria-label="YouTube"><?= social_svg('youtube') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<header class="navbar <?= get_setting('header_sticky', '1') === '1' ? '' : 'nav-static' ?>" data-navbar>
    <div class="container nav-inner">
        <a class="nav-brand" href="<?= e(url('/')) ?>" aria-label="<?= e($siteName ?? SITE_NAME) ?> home">
            <img class="brand-logo" src="<?= e(brand_logo_url()) ?>" alt="<?= e($siteName ?? SITE_NAME) ?> logo" width="52" height="52">
            <span class="brand-name">
                <?= e($siteName ?? SITE_NAME) ?>
                <small>Empowering Communities</small>
            </span>
        </a>

        <nav class="mm-nav" aria-label="Primary">
            <?php require_once __DIR__ . '/megamenu.php'; echo mega_menu_render(); ?>
        </nav>

        <div class="nav-actions">
            <?php /* Collapsed by default — just an icon. Tapping it expands a compact
                     search field with optional voice input (Web Speech API). */ ?>
            <div class="nav-search is-collapsed" data-nav-search data-endpoint="<?= e(url('forms/search')) ?>">
                <button class="nav-search-trigger" type="button" data-search-trigger
                        aria-label="Open search" aria-expanded="false" aria-controls="navSearchBox"><?= lucide('search') ?></button>
                <form class="nav-search-box" id="navSearchBox" action="<?= e(url('search')) ?>" method="get" role="search">
                    <span class="nsb-ico" aria-hidden="true"><?= lucide('search') ?></span>
                    <input type="search" name="q" placeholder="Search the site…" autocomplete="off"
                           aria-label="Search the site" data-nav-search-input>
                    <button class="nsb-mic" type="button" data-search-mic hidden
                            aria-label="Search by voice" title="Search by voice"><?= lucide('mic') ?></button>
                    <button class="nsb-clear" type="button" data-search-close aria-label="Close search"><?= lucide('x') ?></button>
                </form>
                <div class="nav-search-panel" data-nav-search-panel hidden></div>
            </div>
            <button class="theme-toggle" data-theme-toggle aria-label="Toggle theme"><?= lucide('moon') ?></button>
            <?php if (is_member_logged_in()): $mem = current_member(); ?>
                <a class="btn btn-outline btn-sm desktop-only" href="<?= e(url('account')) ?>"><?= lucide('user') ?> <?= e(explode(' ', $mem['name'])[0]) ?></a>
            <?php else: ?>
                <?php require_once __DIR__ . '/portal-sidebar.php'; echo portal_sidebar_trigger(); ?>
            <?php endif; ?>
            <?php $hcText = get_setting('header_cta_text', 'Donate'); $hcUrl = get_setting('header_cta_url', 'donate'); ?>
            <a class="btn btn-glow btn-sm desktop-only" href="<?= e(preg_match('#^https?://#i', $hcUrl) ? $hcUrl : url($hcUrl)) ?>" aria-label="<?= e($hcText) ?>" title="<?= e($hcText) ?>"><?= lucide('heart') ?> <?= e($hcText) ?></a>
        </div>
    </div>
</header>

<?php /* The off-canvas drawer was removed: navigation is now one
         icon-led panel (includes/megamenu.php) at every breakpoint,
         so there is no sidebar to maintain or trap focus in. */ ?>
