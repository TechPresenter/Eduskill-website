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
                    <a href="<?= e(safe_href($s['url'])) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['platform']) ?>"><?= social_fa($s['platform']) ?></a>
                <?php endforeach; else: ?>
                    <a href="#" aria-label="Facebook"><?= social_fa('facebook') ?></a>
                    <a href="#" aria-label="Twitter"><?= social_fa('x') ?></a>
                    <a href="#" aria-label="Instagram"><?= social_fa('instagram') ?></a>
                    <a href="#" aria-label="YouTube"><?= social_fa('youtube') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<header class="navbar <?= get_setting('header_sticky', '1') === '1' ? '' : 'nav-static' ?>" data-navbar>
    <div class="container nav-inner">
        <a class="nav-brand" href="<?= e(url('/')) ?>" aria-label="<?= e($siteName ?? SITE_NAME) ?> home">
            <img class="brand-logo" src="<?= e(!empty($logo) ? upload_url($logo) : asset('images/logo-128.webp')) ?>" alt="<?= e($siteName ?? SITE_NAME) ?> logo" width="52" height="52">
            <span class="brand-name">
                <?= e($siteName ?? SITE_NAME) ?>
                <small>Empowering Communities</small>
            </span>
        </a>

        <nav aria-label="Primary">
            <ul class="nav-menu">
                <?php
                // Small icon hint for mega-menu links, matched by keyword.
                $menuIcon = static function (string $t): string {
                    $t = strtolower($t);
                    foreach ([
                        'home'=>'home','about'=>'info','media'=>'clapperboard','contact'=>'phone','involve'=>'hand-heart','get involved'=>'hand-heart',
                        'who'=>'users','mission'=>'target','vision'=>'eye','management'=>'landmark','ngo'=>'clipboard-list','team'=>'users',
                        'program'=>'target','skill'=>'wrench','campaign'=>'megaphone','achievement'=>'trophy','story'=>'book-open',
                        'gallery'=>'image','video'=>'video','blog'=>'pen-line','resource'=>'download','event'=>'calendar-days','calendar'=>'calendar-heart',
                        'volunteer'=>'hand-heart','internship'=>'graduation-cap','feedback'=>'message-square','member'=>'id-card','career'=>'briefcase','cause'=>'heart',
                    ] as $k=>$ic) { if (str_contains($t,$k)) return $ic; }
                    return 'chevron-right';
                };
                // Short description hint for mega-menu links, matched by keyword.
                $menuDesc = static function (string $t): string {
                    $t = strtolower($t);
                    foreach ([
                        'who'=>'Our story & identity','mission'=>'What drives us','vision'=>"Where we're headed",
                        'management'=>'Board & governance','ngo'=>'Registration & legal','team'=>'The people behind us',
                        'program'=>'How we create impact','skill'=>'Training for livelihoods','campaign'=>'Active fundraisers',
                        'achievement'=>'Milestones & awards','certificate'=>'Recognition & trust','story'=>"Lives we've changed",
                        'gallery'=>'Photos from the field','video'=>'Watch our work','blog'=>'News & articles',
                        'resource'=>'Reports & downloads','event'=>'Upcoming & past','calendar'=>'Awareness days',
                        'volunteer'=>'Give your time','internship'=>'Learn & contribute','feedback'=>'Share your thoughts',
                        'member'=>'Join the community','career'=>'Work with us','cause'=>'Issues we support','contact'=>'Get in touch',
                    ] as $k=>$d) { if (str_contains($t,$k)) return $d; }
                    return '';
                };
                foreach ($headerMenus as $m):
                    $kids = $m['children'] ?? [];
                    $isMega = count($kids) >= 3;
                    $hasKids = !empty($kids); ?>
                    <li class="<?= $isMega ? 'has-mega' : ($hasKids ? 'has-dropdown' : '') ?>">
                        <a class="nav-link <?= active_menu(ltrim($m['url'] === '/' ? 'index' : $m['url'], '/')) ?>"
                           href="<?= e(nav_href($m['url'])) ?>"><?= e(nav_label($m)) ?></a>
                        <?php if ($isMega): $cols = array_chunk($kids, (int) ceil(count($kids) / 2)); ?>
                            <div class="mega-menu">
                                <div class="mega-grid">
                                    <?php foreach ($cols as $col): ?>
                                        <div class="mega-col">
                                            <?php foreach ($col as $c): ?>
                                                <a class="mega-link" href="<?= e(nav_href($c['url'])) ?>">
                                                    <span class="mi"><?= lucide($menuIcon($c['title'])) ?></span>
                                                    <span class="mtxt">
                                                        <strong><?= e(nav_label($c)) ?></strong>
                                                        <?php $d = $menuDesc($c['title']); if ($d): ?><small><?= e($d) ?></small><?php endif; ?>
                                                    </span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mega-feature">
                                    <img src="<?= e(asset('images/logo-128.webp')) ?>" alt="" width="46" height="46">
                                    <div class="mf-text">
                                        <strong><?= e(nav_label($m)) ?> — get involved</strong>
                                        <span>Your support helps us reach more communities across Bihar.</span>
                                    </div>
                                    <a class="btn btn-white btn-sm" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate</a>
                                </div>
                            </div>
                        <?php elseif ($hasKids): ?>
                            <ul class="nav-dropdown">
                                <?php foreach ($kids as $c): ?>
                                    <li><a href="<?= e(nav_href($c['url'])) ?>"><?= e(nav_label($c)) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
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
                <a class="nav-link desktop-only" href="<?= e(url('login')) ?>">Login</a>
            <?php endif; ?>
            <?php $hcText = get_setting('header_cta_text', 'Donate'); $hcUrl = get_setting('header_cta_url', 'donate'); ?>
            <a class="btn btn-glow btn-sm desktop-only" href="<?= e(preg_match('#^https?://#i', $hcUrl) ? $hcUrl : url($hcUrl)) ?>" aria-label="<?= e($hcText) ?>" title="<?= e($hcText) ?>"><?= lucide('heart') ?> <?= e($hcText) ?></a>
            <button class="nav-toggle" data-drawer-toggle aria-label="Open menu" aria-expanded="false"><?= lucide('menu') ?></button>
        </div>
    </div>
</header>

<!-- Mobile drawer -->
<div class="drawer-overlay" data-drawer-overlay></div>
<aside class="nav-drawer" data-drawer aria-label="Mobile menu">
    <div class="drawer-head">
        <a class="drawer-brand" href="<?= e(url('/')) ?>">
            <img src="<?= e(!empty($logo) ? upload_url($logo) : asset('images/logo-128.webp')) ?>" alt="" width="40" height="40">
            <span class="drawer-brand-name"><?= e($siteName ?? SITE_NAME) ?><small>Rise With Compassion</small></span>
        </a>
        <button class="drawer-close" data-drawer-close aria-label="Close menu"><?= lucide('x') ?></button>
    </div>
    <nav class="drawer-nav" aria-label="Mobile">
        <?php foreach ($headerMenus as $m):
            $kids = $m['children'] ?? [];
            $topActive = active_menu(ltrim($m['url'] === '/' ? 'index' : $m['url'], '/'), 'is-active'); ?>
            <a class="drawer-link <?= $topActive ?>" href="<?= e(nav_href($m['url'])) ?>">
                <span class="di"><?= lucide($menuIcon($m['title'])) ?></span>
                <span class="dl"><?= e(nav_label($m)) ?></span>
                <?php if ($kids): ?><span class="dc"><?= lucide('chevron-down') ?></span><?php endif; ?>
            </a>
            <?php if ($kids): ?>
                <div class="drawer-sub">
                    <?php foreach ($kids as $c): ?>
                        <a class="drawer-sublink" href="<?= e(nav_href($c['url'])) ?>">
                            <span class="dtile"><?= lucide($menuIcon($c['title'])) ?></span>
                            <span><?= e(nav_label($c)) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <a class="btn btn-glow drawer-cta" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
</aside>
