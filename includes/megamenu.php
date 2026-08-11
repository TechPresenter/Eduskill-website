<?php
/**
 * =============================================================================
 *  Header navigation — mixed single links and mega dropdowns.
 * =============================================================================
 *  Six top-level entries, each an icon + label. Two are plain links (Home,
 *  Contact); four open a mega panel beneath the header.
 *
 *  Why six and not seventeen: nine labelled items needed ~990px, which the
 *  header row cannot afford next to the brand and the action cluster, so the
 *  labels had to be dropped and the bar became icon-only. Grouping the same
 *  destinations under four dropdowns buys the room back — every page is still
 *  one hover and one click away, and now every entry is legible.
 *
 *  Each dropdown is a single column of icon links (no multi-column grid, no
 *  sidebar). On tablet and mobile the whole thing collapses into one full-width
 *  accordion using the same data.
 * =============================================================================
 */

declare(strict_types=1);

/**
 * The navigation tree.
 *
 * 'children' present  -> mega dropdown
 * 'children' absent   -> single link
 */
function mega_nav_tree(): array
{
    return [
        [
            'icon' => 'home', 'label' => 'Home', 'url' => '/',
        ],
        [
            'icon' => 'users', 'label' => 'About Us', 'url' => 'about',
            'blurb' => 'Who we are, how we work and what we have achieved.',
            'children' => [
                ['icon' => 'info',           'label' => 'Who We Are',        'url' => 'about',            'desc' => 'Our story and purpose'],
                ['icon' => 'target',         'label' => 'Mission & Vision',  'url' => 'mission-vision',   'desc' => 'What we are working toward'],
                ['icon' => 'landmark',       'label' => 'Leadership',        'url' => 'leadership-team',  'desc' => 'The people who guide us'],
                ['icon' => 'users',          'label' => 'Our Team',          'url' => 'team',             'desc' => 'Staff and field workers'],
                ['icon' => 'clipboard-list', 'label' => 'NGO Details',       'url' => 'ngo-details',      'desc' => 'Registration and compliance'],
                ['icon' => 'trending-up',    'label' => 'Impact',            'url' => 'achievements',     'desc' => 'What your support has achieved'],
            ],
        ],
        [
            'icon' => 'target', 'label' => 'Programs', 'url' => 'programs',
            'blurb' => 'Education, health, skills, relief and the appeals that fund them.',
            'children' => [
                ['icon' => 'target',         'label' => 'All Programs',      'url' => 'programs',          'desc' => 'Everything we run'],
                ['icon' => 'megaphone',      'label' => 'Campaigns',         'url' => 'campaigns',         'desc' => 'Active appeals you can support'],
                ['icon' => 'scroll',         'label' => 'Schemes',           'url' => 'schemes',           'desc' => 'Government-linked initiatives'],
                ['icon' => 'book-open',      'label' => 'Courses',           'url' => 'courses',           'desc' => 'Free training you can enrol in today.'],
                ['icon' => 'file-pen',       'label' => 'Admissions',        'url' => 'admission-apply',   'desc' => 'Apply online for a place on a programme.'],
                ['icon' => 'graduation-cap', 'label' => 'Scholarships',      'url' => 'scholarship',       'desc' => 'Support for students'],
                ['icon' => 'wrench',         'label' => 'Skill Development', 'url' => 'skill-development', 'desc' => 'Vocational training'],
            ],
        ],
        [
            'icon' => 'images', 'label' => 'Media', 'url' => 'gallery',
            'blurb' => 'Stories, photographs and what is coming up.',
            'children' => [
                ['icon' => 'images',        'label' => 'Gallery',         'url' => 'gallery',         'desc' => 'Photographs from our work'],
                ['icon' => 'pen-line',      'label' => 'Blog',            'url' => 'blogs',           'desc' => 'Updates from the field'],
                ['icon' => 'book-open',     'label' => 'Success Stories', 'url' => 'success-stories', 'desc' => 'Change in people\'s own words'],
                ['icon' => 'calendar-days', 'label' => 'Events',          'url' => 'events',          'desc' => 'Camps, drives and gatherings'],
                ['icon' => 'video',         'label' => 'Videos',          'url' => 'media',           'desc' => 'Films and coverage'],
            ],
        ],
        [
            'icon' => 'heart', 'label' => 'Support Us', 'url' => 'donate',
            'blurb' => 'Give, join or lend your time — every route is tracked and reported.',
            'children' => [
                ['icon' => 'heart',      'label' => 'Donate',        'url' => 'donate',         'desc' => 'Give once or monthly'],
                ['icon' => 'id-card',    'label' => 'Membership',    'url' => 'membership',     'desc' => 'Join as a formal member'],
                ['icon' => 'hand-heart', 'label' => 'Volunteer',     'url' => 'volunteer',      'desc' => 'Give your time and skills'],
                ['icon' => 'handshake',  'label' => 'Partner With Us','url' => 'become-partner', 'desc' => 'Corporate and CSR partnerships'],
                ['icon' => 'briefcase',  'label' => 'Careers',       'url' => 'career',         'desc' => 'Work with us'],
            ],
        ],
        [
            'icon' => 'log-in', 'label' => 'Portals', 'url' => 'portals',
            'blurb' => 'Sign in to the dashboard built for your role.',
            'children' => [
                ['icon' => 'book-open',  'label' => 'Student Portal',   'url' => 'login/student',   'desc' => 'Courses, results and certificates'],
                ['icon' => 'id-card',    'label' => 'Member Portal',    'url' => 'login/member',    'desc' => 'Membership, ID card and benefits'],
                ['icon' => 'hand-heart', 'label' => 'Volunteer Portal', 'url' => 'login/volunteer', 'desc' => 'Campaigns, tasks and hours'],
                ['icon' => 'heart',      'label' => 'Donor Portal',     'url' => 'login/donor',     'desc' => 'Donations, receipts and tax documents'],
                ['icon' => 'shield',     'label' => 'Admin Portal',     'url' => 'login/admin',     'desc' => 'Staff and administrators'],
            ],
        ],
        [
            'icon' => 'mail', 'label' => 'Contact', 'url' => 'contact',
        ],
    ];
}

/** True when $url is the page currently being viewed. */
function mega_is_active(string $url, string $here): bool
{
    $t = trim($url, '/');
    return ($t === '' && $here === '') || ($t !== '' && $here !== '' && $here === $t);
}

/** Render the header navigation (bar + dropdowns + mobile accordion). */
function mega_menu_render(): string
{
    $tree = mega_nav_tree();
    $here = function_exists('current_path') ? trim((string) current_path(), '/') : '';

    /* ---------------- desktop bar ---------------- */
    $out = '<ul class="mm-bar">';
    foreach ($tree as $i => $top) {
        $kids   = $top['children'] ?? [];
        $href   = $top['url'] === '/' ? url('/') : url($top['url']);
        $active = mega_is_active((string) $top['url'], $here);
        if (!$active) {
            foreach ($kids as $k) {
                if (mega_is_active((string) $k['url'], $here)) { $active = true; break; }
            }
        }
        $id = 'mmd-' . $i;

        $out .= '<li class="mm-item' . ($kids ? ' has-drop' : '') . '">';

        if ($kids) {
            // A button, not a link: it opens a panel rather than navigating.
            $out .= '<button class="mm-bar-link' . ($active ? ' is-active' : '') . '" type="button"'
                  . ' data-mm-drop="' . e($id) . '" aria-expanded="false" aria-controls="' . e($id) . '">'
                  . '<span class="mm-bar-ico" aria-hidden="true">' . lucide($top['icon']) . '</span>'
                  . '<span class="mm-bar-txt">' . e($top['label']) . '</span>'
                  . '<span class="mm-caret" aria-hidden="true">' . lucide('chevron-down') . '</span>'
                  . '</button>';

            /* Buffered, NOT inlined here. .navbar carries backdrop-filter, which
               makes it the containing block for position:fixed descendants — a
               dropdown rendered inside the header positions against the 74px
               header box instead of the viewport. Its z-index:900 stacking
               context also traps the panel beneath the cookie banner. These are
               emitted at end-of-body by mega_menu_overlays(). */
            $drop = '<div class="mm-drop" id="' . e($id) . '" data-mm-dropdown hidden>'
                  . '<div class="container mm-drop-inner">'
                  . '<div class="mm-drop-head">'
                  . '<span class="mm-drop-ico" aria-hidden="true">' . lucide($top['icon']) . '</span>'
                  . '<span><strong>' . e($top['label']) . '</strong>'
                  . '<small>' . e($top['blurb'] ?? '') . '</small></span>'
                  . '<a class="mm-drop-all" href="' . e($href) . '">View all ' . lucide('arrow-right') . '</a>'
                  . '</div><ul class="mm-drop-list">';

            foreach ($kids as $k) {
                $kActive = mega_is_active((string) $k['url'], $here);
                $drop .= '<li><a class="mm-link' . ($kActive ? ' is-active' : '') . '" href="' . e(url($k['url'])) . '"'
                       . ($kActive ? ' aria-current="page"' : '') . '>'
                       . '<span class="mm-ico" aria-hidden="true">' . lucide($k['icon']) . '</span>'
                       . '<span class="mm-txt"><strong>' . e($k['label']) . '</strong>'
                       . '<small>' . e($k['desc'] ?? '') . '</small></span>'
                       . '<span class="mm-arrow" aria-hidden="true">' . lucide('arrow-right') . '</span>'
                       . '</a></li>';
            }
            $drop .= '</ul></div></div>';
            $GLOBALS['__mm_overlays'][] = $drop;
        } else {
            $out .= '<a class="mm-bar-link' . ($active ? ' is-active' : '') . '" href="' . e($href) . '"'
                  . ($active ? ' aria-current="page"' : '') . '>'
                  . '<span class="mm-bar-ico" aria-hidden="true">' . lucide($top['icon']) . '</span>'
                  . '<span class="mm-bar-txt">' . e($top['label']) . '</span></a>';
        }
        $out .= '</li>';
    }
    $out .= '</ul>';

    /* ---------------- mobile trigger + accordion ---------------- */
    $out .= '<button class="mm-trigger" type="button" data-mm-toggle aria-expanded="false" '
          . 'aria-controls="mega-menu" aria-haspopup="true">'
          . '<span class="mm-bars" aria-hidden="true"><i></i><i></i><i></i></span>'
          . '<span class="mm-trigger-txt">Menu</span></button>';

    /* Scrim + mobile panel are overlays too — buffered for the same reason. */
    /* ---------------- mobile drawer ----------------
       Built as three regions rather than a bare list of links:

         head   the brand plus an explicit Close button. The drawer previously
                opened straight onto "Home" with no way out except tapping the
                dim area beside it, which is not discoverable and is the first
                thing people report about slide-out menus.
         body   the navigation, with the current page marked. Knowing where you
                are is the cheapest orientation a menu can offer and it cost
                nothing here — mega_is_active() already existed for the desktop
                bar and was simply never applied to the mobile panel.
         foot   the two things a visitor most often came for (sign in, donate)
                and how to reach a human. Without it the panel just stopped
                after the last link and read as unfinished.

       Each row carries --i, consumed by the stagger in design-system.css. */
    $panel  = '<div class="mm-scrim" data-mm-scrim hidden></div>';
    $panel .= '<div class="mm-panel" id="mega-menu" data-mm-panel hidden>';

    /* ---- head ---- */
    $siteName = function_exists('get_setting') ? (string) get_setting('site_name', SITE_NAME) : SITE_NAME;
    $panel .= '<div class="mm-head">'
          . '<a class="mm-brand" href="' . e(url('/')) . '">';
    if (function_exists('brand_logo_url')) {
        $panel .= '<img class="mm-brand-logo" src="' . e(brand_logo_url()) . '" alt="" width="40" height="40">';
    }
    $panel .= '<span class="mm-brand-name">' . e($siteName) . '</span></a>'
          . '<button class="mm-close" type="button" data-drawer-close aria-label="Close menu">'
          . lucide('x') . '</button>'
          . '</div>';

    $panel .= '<div class="mm-inner">';

    $row = 0;
    foreach ($tree as $i => $top) {
        $kids = $top['children'] ?? [];
        $href = $top['url'] === '/' ? url('/') : url($top['url']);

        $active = mega_is_active((string) $top['url'], $here);
        if (!$active) {
            foreach ($kids as $k) {
                if (mega_is_active((string) $k['url'], $here)) { $active = true; break; }
            }
        }
        $style = ' style="--i:' . $row++ . '"';

        if (!$kids) {
            $panel .= '<a class="mm-link mm-acc-solo' . ($active ? ' is-active' : '') . '" href="' . e($href) . '"' . $style
                  . ($active ? ' aria-current="page"' : '') . '>'
                  . '<span class="mm-ico" aria-hidden="true">' . lucide($top['icon']) . '</span>'
                  . '<span class="mm-txt"><strong>' . e($top['label']) . '</strong></span>'
                  . '<span class="mm-arrow" aria-hidden="true">' . lucide('arrow-right') . '</span></a>';
            continue;
        }

        $accId = 'mma-' . $i;
        $panel .= '<div class="mm-acc' . ($active ? ' is-active' : '') . '"' . $style . '>'
              . '<button class="mm-acc-head" type="button" data-mm-acc="' . e($accId) . '"'
              . ' aria-expanded="' . ($active ? 'true' : 'false') . '" aria-controls="' . e($accId) . '">'
              . '<span class="mm-ico" aria-hidden="true">' . lucide($top['icon']) . '</span>'
              . '<span class="mm-txt"><strong>' . e($top['label']) . '</strong></span>'
              . '<span class="mm-caret" aria-hidden="true">' . lucide('chevron-down') . '</span>'
              . '</button><ul class="mm-acc-body" id="' . e($accId) . '"' . ($active ? '' : ' hidden') . '>';

        $panel .= '<li><a class="mm-link" href="' . e($href) . '">'
              . '<span class="mm-ico" aria-hidden="true">' . lucide('layout-grid') . '</span>'
              . '<span class="mm-txt"><strong>All ' . e($top['label']) . '</strong></span></a></li>';

        foreach ($kids as $k) {
            $kActive = mega_is_active((string) $k['url'], $here);
            $panel .= '<li><a class="mm-link' . ($kActive ? ' is-active' : '') . '" href="' . e(url($k['url'])) . '"'
                  . ($kActive ? ' aria-current="page"' : '') . '>'
                  . '<span class="mm-ico" aria-hidden="true">' . lucide($k['icon']) . '</span>'
                  . '<span class="mm-txt"><strong>' . e($k['label']) . '</strong></span></a></li>';
        }
        $panel .= '</ul></div>';
    }

    $panel .= '</div>';   /* /mm-inner */

    /* ---- foot ---- */
    $signedIn = function_exists('is_member_logged_in') && is_member_logged_in();
    $panel .= '<div class="mm-foot">'
          . '<div class="mm-cta-row">'
          . '<a class="btn btn-outline mm-cta-half" href="' . e(url($signedIn ? 'account' : 'login')) . '">'
          . lucide($signedIn ? 'user-round' : 'log-in') . ' ' . ($signedIn ? 'My account' : 'Sign in') . '</a>'
          . '<a class="btn btn-primary mm-cta-half" href="' . e(url('donate')) . '">'
          . lucide('heart') . ' Donate</a>'
          . '</div>';

    $phone = function_exists('get_setting') ? trim((string) get_setting('contact_phone', '')) : '';
    $mail  = function_exists('get_setting') ? trim((string) get_setting('contact_email', '')) : '';
    if ($phone !== '' || $mail !== '') {
        $panel .= '<div class="mm-contact">';
        if ($phone !== '') {
            $panel .= '<a href="tel:' . e(preg_replace('/[^\d+]/', '', $phone)) . '">'
                   . lucide('phone') . '<span>' . e($phone) . '</span></a>';
        }
        if ($mail !== '') {
            $panel .= '<a href="mailto:' . e($mail) . '">' . lucide('mail') . '<span>' . e($mail) . '</span></a>';
        }
        $panel .= '</div>';
    }
    $panel .= '</div>';   /* /mm-foot */

    $panel .= '</div>';   /* /mm-panel */

    $GLOBALS['__mm_overlays'][] = $panel;

    return $out;   // the bar only; overlays come from mega_menu_overlays()
}

/**
 * The buffered overlays (dropdowns, scrim, mobile panel), emitted at end-of-body.
 *
 * They must live OUTSIDE <header class="navbar">: that element sets
 * backdrop-filter, which makes it the containing block for position:fixed
 * descendants, and z-index:900, which creates a stacking context. Rendered
 * inside it, the scrim covered only the header strip, the dropdowns positioned
 * against the header box, and every panel sat beneath the cookie banner.
 */
function mega_menu_overlays(): string
{
    $parts = $GLOBALS['__mm_overlays'] ?? [];
    return $parts ? implode('', $parts) : '';
}