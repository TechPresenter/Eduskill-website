<?php
/**
 * =============================================================================
 *  Admin layout head — opens the page, sidebar, topbar and content area.
 *  Every admin page (except login) does:
 *      require_once __DIR__ . '/../includes/bootstrap.php';
 *      require_admin();
 *      $page_title = 'Programs';
 *      // optional: $page_crumbs = [['label' => 'Blog', 'url' => admin_url('blogs')]];
 *      include __DIR__ . '/partials/head.php';
 *      ... page ...
 *      include __DIR__ . '/partials/foot.php';
 * =============================================================================
 */
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}
require_admin();

/* The shared admin-UI component renderers (empty/error state, skeleton, toast,
   drawer, breadcrumb, bulk bar, KPI card, timeline, confirm) that back the class
   API declared in assets/css/admin-ds.css. Loaded here rather than in
   bootstrap.php because it is admin-only. Guarded with is_file so the panel
   still boots if the helper has not been deployed yet. */
if (is_file(BASE_PATH . '/includes/admin_ui.php')) {
    require_once BASE_PATH . '/includes/admin_ui.php';
}

$admin    = current_user();
$title    = $page_title ?? 'Dashboard';
$siteName = get_setting('site_name', SITE_NAME);
$flashes  = get_flashes();

// Role label + live notification feed for the topbar.
$adminRole = 'Administrator';
if (!empty($admin['role_id'])) {
    $rr = find('roles', (int) $admin['role_id']);
    if ($rr) { $adminRole = $rr['name']; }
}
$notifs     = admin_notifications();
$notifTotal = admin_notifications_total();
$adminSlug  = function_exists('rbac_current_slug') ? rbac_current_slug() : 'dashboard';

/** May the current role reach an admin page slug? (RBAC is default-OFF.) */
$adminCan = static function (string $slug): bool {
    return !function_exists('rbac_can_slug') || rbac_can_slug($slug);
};

/* -----------------------------------------------------------------------------
 | BREADCRUMBS
 | A page may set $page_crumbs before including this file; otherwise the trail is
 | derived from the RBAC slug map, which already knows which nav group owns every
 | admin page (including detail views via rbac_slug_aliases()).
 |
 | Trail shape: [ ['label' => string, 'url' => ?string], ... ] — last item is the
 | current page and carries no url. A ['Label' => 'url'] map is accepted too and
 | normalised below, because pages in this codebase are written both ways.
 | -------------------------------------------------------------------------- */
$adminCrumbTrail = [];
foreach ((array) ($page_crumbs ?? []) as $crumbKey => $crumbVal) {
    if (is_array($crumbVal)) {
        $adminCrumbTrail[] = ['label' => (string) ($crumbVal['label'] ?? ''), 'url' => $crumbVal['url'] ?? null];
    } elseif (is_string($crumbKey)) {
        $adminCrumbTrail[] = ['label' => $crumbKey, 'url' => $crumbVal !== '' ? (string) $crumbVal : null];
    } else {
        $adminCrumbTrail[] = ['label' => (string) $crumbVal, 'url' => null];
    }
}
if (!$adminCrumbTrail) {
    $adminCrumbTrail = [['label' => 'Dashboard', 'url' => admin_url('dashboard')]];
    if ($adminSlug !== 'dashboard') {
        $crumbGroup = function_exists('rbac_slug_group') ? rbac_slug_group($adminSlug) : null;
        if ($crumbGroup !== null && $crumbGroup !== 'Main') {
            $adminCrumbTrail[] = ['label' => $crumbGroup, 'url' => null];
        }
        $adminCrumbTrail[] = ['label' => $title, 'url' => null];
    }
}

/* Render through admin_crumbs() when includes/admin_ui.php provides it, so every
   screen gets one breadcrumb shape. That helper is owned by another change, so
   this call tolerates either an echoing or a returning implementation and falls
   back to rendering the documented .ds-crumbs markup itself. */
$crumbHtml = '';
if (function_exists('admin_crumbs')) {
    $crumbLevel = ob_get_level();
    try {
        ob_start();
        $crumbReturn = admin_crumbs($adminCrumbTrail);
        $crumbBuffer = (string) ob_get_clean();
        $crumbHtml   = $crumbBuffer !== '' ? $crumbBuffer : (is_string($crumbReturn) ? $crumbReturn : '');
    } catch (Throwable $crumbError) {
        while (ob_get_level() > $crumbLevel) { ob_end_clean(); }
        $crumbHtml = '';
    }
}
if ($crumbHtml === '') {
    /* Every name here is $crumb*-prefixed on purpose. This file is included at
       global scope in the middle of a page that has already set up its own
       variables, and this loop only runs when admin_crumbs() is UNAVAILABLE —
       exactly the state the is_file guard above exists to support. Writing bare
       $i / $c / $label at that point clobbered them: admin/email-campaigns.php
       assigns $c = find($table, $id) before including this file and prints
       e($c['name']) after it, so it would have rendered a breadcrumb array. */
    $crumbHtml = '<nav aria-label="Breadcrumb"><ol class="ds-crumbs tb-crumbs">';
    $crumbLast = count($adminCrumbTrail) - 1;
    foreach ($adminCrumbTrail as $crumbIndex => $crumbItem) {
        $crumbLabel = e((string) $crumbItem['label']);
        if ($crumbIndex === $crumbLast) {
            $crumbHtml .= '<li aria-current="page">' . $crumbLabel . '</li>';
        } elseif (!empty($crumbItem['url'])) {
            $crumbHtml .= '<li><a href="' . e((string) $crumbItem['url']) . '">' . $crumbLabel . '</a></li>';
        } else {
            $crumbHtml .= '<li>' . $crumbLabel . '</li>';
        }
    }
    $crumbHtml .= '</ol></nav>';
    unset($crumbIndex, $crumbItem, $crumbLabel, $crumbLast);
}

/* -----------------------------------------------------------------------------
 | QUICK-CREATE MENU — the create routes admins reach most, RBAC-filtered.
 | Each target is the module's own documented create URL (?action=create); no new
 | routes are introduced here.
 | -------------------------------------------------------------------------- */
$quickCreate = array_values(array_filter([
    ['blogs',          'pen-line',      'Blog post',     'blogs?action=create'],
    ['pages',          'file-text',     'Page',          'pages?action=create'],
    ['events',         'calendar-days', 'Event',         'events?action=create'],
    ['campaigns',      'megaphone',     'Campaign',      'campaigns?action=create'],
    ['programs',       'target',        'Program',       'programs?action=create'],
    ['gallery-albums', 'images',        'Gallery album', 'gallery-albums?action=create'],
    ['members',        'id-card',       'Member',        'members?action=create'],
    ['users',          'user',          'User',          'users?action=create'],
    ['email-compose',  'pencil',        'Email',         'email-compose'],
], static fn ($q) => $adminCan($q[0])));

/* -----------------------------------------------------------------------------
 | USER MENU — gated per module so a role never sees a link it cannot open.
 | [gate slug, icon, label, href]
 |
 | Two targets that the previous menu had must stay reachable from here:
 |
 |   Change Password → profile#change-password. admin/profile.php renders
 |     <div class="panel" id="change-password">, so this deep link works. An
 |     earlier draft replaced it with "Account Settings" → profile#account-settings
 |     and there is no id="account-settings" anywhere in that file — a working
 |     deep link swapped for an inert one. Until profile.php grows that id (see
 |     the notes shipped with this change), Account Settings points at the page
 |     itself and Change Password keeps the fragment that resolves.
 |   Settings → the site settings module. Still in the sidebar under Website
 |     Content, but it was in this menu before and admins reach for it here.
 | -------------------------------------------------------------------------- */
$accountItems = array_values(array_filter([
    ['profile',       'circle-user',       'Profile',          admin_url('profile')],
    ['dashboard',     'layout-dashboard',  'My Dashboard',     admin_url('dashboard')],
    ['profile',       'settings',          'Account Settings', admin_url('profile')],
    ['profile',       'key-round',         'Change Password',  admin_url('profile') . '#change-password'],
    ['settings',      'sliders-horizontal', 'Settings',        admin_url('settings')],
    ['security',      'shield-check',      'Security',         admin_url('security')],
    ['activity-logs', 'history',           'Login Activity',   admin_url('activity-logs')],
    ['notifications', 'bell',              'Notifications',    admin_url('notifications')],
], static fn ($m) => $adminCan($m[0])));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light dark">
    <script>(function(){try{var t=localStorage.getItem('pwf-theme');if(!t){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
    <?= csrf_meta() ?>
    <script>window.PWF_CKUPLOAD = <?= json_encode(admin_url('ckeditor-upload')) ?>;</script>
    <title><?= e($title) ?> · <?= e($siteName) ?> Admin</title>
    <link rel="icon" href="<?= e(asset('images/favicon.jpg')) ?>" type="image/jpeg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <?php /* Stylesheet order is load-bearing — see the header of admin-ds.css.
             premium.css was dropped from this list: 69 KB of public-site
             decoration (glass cards, blobs, marquees, tilt/reveal effects) of
             which the admin used exactly ONE rule, on one screen. Every admin
             page was paying for it.

             admin-ds.css loads LAST, after the Theme Engine's inline block, so
             the design scales (radius, spacing, elevation, motion) resolve
             deterministically instead of losing a specificity race between
             admin.css, admin-pro.css and ui.css. */ ?>
    <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin-pro.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
    <?php echo function_exists('theme_style_tag') ? theme_style_tag('admin') : ''; ?>
    <link rel="stylesheet" href="<?= e(asset('css/admin-ds.css')) ?>">
    <?php if (!empty($load_analytics_css)): ?><link rel="stylesheet" href="<?= e(asset('css/analytics.css')) ?>"><?php endif; ?>

    <?php /* ---------------------------------------------------------------------
       APPLICATION SHELL LAYER — the sidebar/topbar rules that admin.css does not
       have and that only the shell needs. It sits after admin-ds.css because the
       cascade order above is fixed; it uses ONLY admin-ds.css tokens (radius,
       spacing, motion, status) and adds no new colour literal beyond the brand
       palette. Every rule below is either (a) a new element this shell
       introduces, (b) a WCAG fix, or (c) a design-law correction that lives in a
       stylesheet this change does not own. It belongs in admin-ds.css / admin.css
       and should be lifted there — see the notes shipped with this change.
       ------------------------------------------------------------------------ */ ?>
    <style>
    /* (c) DESIGN LAW: no glassmorphism. admin.css:502-516 gives the topbar
       backdrop-filter blur(18px) saturate(180%) over a 72%-alpha fill; the
       settings shell repeats it. Flattened onto an opaque surface. */
    body.admin .admin-topbar,
    html[data-theme="dark"] body.admin .admin-topbar {
        -webkit-backdrop-filter: none; backdrop-filter: none;
        background: var(--surface); border-bottom: 1px solid var(--border);
        box-shadow: var(--elev-1); height: auto; min-height: 68px;
    }
    /* (c) DESIGN LAW: no decorative gradients. admin.css:352 washes the rail with
       a radial gradient. The rail keeps its (Theme-Engine driven) field. */
    body.admin .admin-sidebar::before { background: none; }

    /* (c) DESIGN LAW: one rule under the topbar, not two. admin.css paints
       .admin-topbar::after as a 2px brand bar and the border-bottom above adds a
       1px --border line directly beneath it. */
    body.admin .admin-topbar::after { display: none; }

    /* (b) WCAG: the DS focus ring is brand green (#0B4E3D) and the rail's field is
       brand green — the ring was invisible on every sidebar control. Gold on the
       rail, brand green everywhere else.
       The gold is only guaranteed against the DEFAULT rail: --sb-bg is a Theme
       Studio token an admin can set to anything, and #FFE987 on a light rail is
       ~1.3:1. The dark halo underneath is the layer that survives any field. */
    body.admin .admin-sidebar a:focus-visible,
    body.admin .admin-sidebar button:focus-visible {
        outline: 2px solid #FFE987; outline-offset: -2px; border-radius: var(--r-md);
        box-shadow: 0 0 0 4px rgba(0, 0, 0, .55);
    }

    /* (b) WCAG 2.4.7 / 1.4.11: .tb-search-input is the one control in the panel
       with no focus indicator — admin.css sets `outline: none` on it, and the DS
       ring matches a/button/.btn/.form-control/.form-select, not a bare <input>.
       All that was left was a 1px border tint plus a 14%-alpha wash, nowhere near
       3:1 — on the field Ctrl+K lands in, so keyboard users hit it most.
       NB a ring must be an OUTLINE, not a box-shadow, anywhere inside this shell:
       admin-pro.css substitutes box-shadow for .admin-content [tabindex], and any
       ancestor with overflow:hidden (.sidebar-link, .panel) clips it away. */
    body.admin .tb-search:focus-within {
        outline: 2px solid var(--brand-600, #0B4E3D); outline-offset: 2px;
    }
    body.admin input:focus-visible,
    body.admin select:focus-visible,
    body.admin textarea:focus-visible,
    body.admin summary:focus-visible {
        outline: 2px solid var(--brand-600, #0B4E3D); outline-offset: 2px;
    }

    /* (b) Skip link — the rail is 100+ links deep before the content starts. */
    body.admin .admin-skip:focus, body.admin .admin-skip:focus-visible {
        position: fixed; top: var(--sp-2); left: var(--sp-2); z-index: 1300;
        width: auto; height: auto; margin: 0; padding: var(--sp-3) var(--sp-4);
        clip: auto; clip-path: none; overflow: visible; white-space: nowrap;
        background: var(--surface); color: var(--text); font-weight: 700;
        border: 1px solid var(--border); border-radius: var(--r-md); box-shadow: var(--elev-3);
    }

    /* (a) Topbar heading stack: breadcrumbs above the page title. The overrides
       are keyed off .tb-heading so they also land on markup produced by
       admin_crumbs(), which does not know it is being rendered in a topbar. */
    body.admin .tb-heading { display: flex; flex-direction: column; gap: 2px; min-width: 0; margin-right: var(--sp-2); }
    body.admin .tb-heading .ds-crumbs { margin: 0; font-size: .74rem; flex-wrap: nowrap; }
    body.admin .tb-heading .ds-crumbs li { white-space: nowrap; }
    body.admin .tb-heading .ds-crumbs li:not(:last-child) { overflow: hidden; text-overflow: ellipsis; max-width: 18ch; }
    body.admin .tb-title { font-size: 1rem; line-height: 1.2; }
    @media (max-width: 1100px) { body.admin .tb-heading > nav, body.admin .tb-heading > .ds-crumbs { display: none; } }

    /* (b) admin.css sets `.tb-title { display: none }` at <=992px. This <h1> is
       the ONLY top-level heading on the 9 admin pages that do not emit one of
       their own (email-dashboard, email-mailbox, email-compose, email-analytics
       and 5 print views), so on a phone those pages had no top-level heading at
       all — and heading navigation, the primary screen-reader wayfinding tool,
       had nothing to land on. Clip it instead of removing it: same visual, name
       intact. (The other half of this finding — 123 pages that emit an h1 of
       their own and so have two — is fixed by DEMOTING this one to a p, but only
       AFTER those 9 pages grow a heading of their own. Doing it in the other
       order makes it worse. See the notes shipped with this change.) */
    @media (max-width: 992px) {
        body.admin .tb-title {
            display: block; position: absolute; width: 1px; height: 1px;
            padding: 0; margin: -1px; overflow: hidden;
            clip: rect(0 0 0 0); clip-path: inset(50%); white-space: nowrap; border: 0;
        }
    }

    /* (a) Quick-create popover reuses .tb-pop / .tb-menu from admin.css. */
    body.admin .tb-quick-menu { min-width: 244px; }
    body.admin .tb-menu-label {
        display: block; padding: var(--sp-2) var(--sp-2) var(--sp-1);
        font-size: .68rem; font-weight: 800; letter-spacing: .08em;
        text-transform: uppercase; color: var(--muted);
    }

    /* (a) The user menu's header carries name, role AND email. */
    body.admin .tb-menu-userinfo .tb-profile-role { display: block; }

    /* (a) Email on the pinned profile block. */
    body.admin .su-email {
        font-size: 11px; color: var(--sb-muted); max-width: 148px;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    body.admin .sidebar-user-foot .su-meta { flex: 1; min-width: 0; }

    /* (a) A folded group still reports its pending work (number + text, not hue).
       admin.css declares .ngh-badge display:none unconditionally, so the rollup
       the markup has always computed could never be seen. It is shown only while
       the group is closed AND the rail is expanded: in the collapsed rail the
       bodies are force-open by CSS while `.is-open` is whatever localStorage
       holds, so a previously-folded group showed this rollup directly above its
       already-visible items — the same count printed twice — and, because
       name-from-content beats nothing else on that button, the badge text became
       the group head's entire accessible name. */
    body.admin:not(.sidebar-collapsed) .nav-group:not(.is-open) > .nav-group-head .ngh-badge {
        display: inline-flex;
    }

    /* ---------------------------------------------------------------------
       (b) THE COLLAPSED RAIL'S ACCESSIBLE NAME.
       admin.css hides .lbl / .ngh-label with `display: none` when the rail
       collapses, which removes them from the accessibility tree too. For a plain
       link HTML-AAM then falls back to `title`; for a BADGED link it does not,
       because .badge-count stays visible, so name-from-content is non-empty —
       "3 pending" — and the link announced with no module name at all. That hit
       every slug in admin_pending_counts(): the 14 rows an admin needs to act on
       were the 14 that went anonymous. Clip instead of remove and the name is
       "Members 3 pending" in both states, with `title` back to being a hover hint
       for ellipsised labels rather than the only name source (several SR/browser
       pairs suppress title when object descriptions are off).
       ------------------------------------------------------------------- */
    @media (min-width: 993px) {
        body.admin.sidebar-collapsed .sidebar-link .lbl,
        body.admin.sidebar-collapsed .nav-group-head .ngh-label {
            display: block; position: absolute; width: 1px; height: 1px;
            padding: 0; margin: -1px; overflow: hidden;
            clip: rect(0 0 0 0); clip-path: inset(50%);
            white-space: nowrap; border: 0;
        }
    }

    /* (b) …and the SIGHTED keyboard user's half of the same problem. `title`
       renders on hover only, never on focus, so tabbing the collapsed rail was
       100+ unnamed icons. The styled flyout in admin.css is dead — clipped by
       .sidebar-nav{overflow-x:hidden} and .sidebar-link{overflow:hidden} — so
       foot.php builds one at <body> level, positioned from the focused row, and
       toggles [data-sb-flyout]. A CSS-only reveal of the clipped .lbl is not
       possible here: it needs overflow:visible on both the link and the scroller,
       which breaks the ripple and the scroll container. */
    body.admin .sb-flyout {
        position: fixed; z-index: 400; pointer-events: none;
        padding: var(--sp-2) var(--sp-3); max-width: 260px;
        border-radius: var(--r-md); background: #0B4E3D; color: #fff;
        font-size: .8rem; font-weight: 600; line-height: 1.3; white-space: nowrap;
        box-shadow: var(--elev-3);
    }

    /* (b) With Lucide unavailable (CDN blocked, offline, or simply slow) lucide()
       leaves an EMPTY <i data-lucide> behind, and collapsed the label is clipped —
       so until a third-party request landed, every row was a blank 76px box with
       no text and no glyph. sidebar.php carries the first letter as data-initial
       on .ico and foot.php paints it into the <i> if Lucide never arrives. (CSS
       alone cannot: .ico is never :empty — it always holds the <i> — and attr()
       only reads its own element's attributes.) */
    body.admin .sidebar-link .ico > i[data-lucide] {
        font-style: normal; font-size: .82rem; font-weight: 800; letter-spacing: .02em;
        display: inline-flex; align-items: center; justify-content: center;
        width: 100%; color: var(--sb-brand);
    }

    /* (b) WCAG 2.5.8 (24x24): no stylesheet in the admin bundle styles
       input[type=checkbox] at all, so the most-clicked control in the new table
       pattern renders at the UA default 13-16px. The padded cell supplies the
       spacing exception; accent-color also brings the checked state into the
       palette instead of the UA blue. */
    body.admin .admin-table input[type="checkbox"],
    body.admin .table-wrap input[type="checkbox"] {
        width: 18px; height: 18px; margin: 0;
        accent-color: var(--brand-600, #0B4E3D);
    }
    body.admin .admin-table th:has(> input[type="checkbox"]),
    body.admin .admin-table td:has(> input[type="checkbox"]) {
        padding: var(--sp-2) var(--sp-3);
    }
    @media (max-width: 1024px), (pointer: coarse) {
        body.admin .admin-table input[type="checkbox"],
        body.admin .table-wrap input[type="checkbox"] { width: 24px; height: 24px; }
    }
    /* A selected row gets a band as a REDUNDANT cue — the checkbox carries the
       state — kept above 3:1 against --surface for low-vision users. */
    body.admin .admin-table tbody tr.is-selected { background: var(--surface-2); }

    /* (b) admin-ds.css gives .icon-btn min-height:44px on touch but leaves its
       width at 34, so the target was 34x44. */
    @media (max-width: 1024px), (pointer: coarse) {
        body.admin .icon-btn { min-width: 44px; }
    }

    /* (a+b) The confirm dialog's destructive cue. .ds-confirm.is-destructive only
       recolours the title (--st-err), so the state needs the glyph and the word
       too — and they must live OUTSIDE the <h2>, because admin-ui.js overwrites
       the heading's text whenever a trigger supplies data-confirm-title. Rendered
       by admin_confirm() and by admin-ui.js's fillDialog(), one element, one
       code path. */
    body.admin .ds-confirm-warn {
        display: flex; align-items: center; gap: var(--sp-2); margin: 0;
        font-size: .78rem; font-weight: 800; letter-spacing: .06em;
        text-transform: uppercase; color: var(--st-err);
    }
    body.admin .ds-confirm-warn svg { flex: none; width: 18px; height: 18px; }

    /* (a) Drawer close lives in the drawer: once open, the scrim (z-index 190)
       covers the topbar hamburger (z-index 60), so that button cannot close it. */
    body.admin .sidebar-close {
        display: none; width: 40px; height: 40px; flex-shrink: 0; place-items: center;
        border: 1px solid var(--sb-border); border-radius: var(--r-md);
        background: transparent; color: var(--sb-muted); cursor: pointer;
    }
    body.admin .sidebar-close:hover { color: #fff; background: var(--sb-hover); }
    @media (max-width: 992px) {
        body.admin .sidebar-close { display: grid; }
        /* Collapsing is a desktop affordance; ≤992px the rail is a drawer and the
           collapse button is inert (admin.css:129-137 neutralises the state). */
        body.admin .sidebar-collapse { display: none; }
    }

    /* (b) 44px minimum touch target — the rail's rows are ~28px and the topbar's
       chips are 42px. Applied where the pointer is a thumb. */
    @media (max-width: 1024px), (pointer: coarse) {
        body.admin .sidebar-link,
        body.admin .tb-menu-item,
        body.admin .tb-notif-item { min-height: 44px; }
        /* Collapsed, a group head is a decorative spacer with no target in it. */
        body.admin:not(.sidebar-collapsed) .nav-group-head { min-height: 44px; }
        body.admin .tb-icon-btn,
        body.admin .sidebar-close,
        body.admin .sidebar-collapse { min-width: 44px; min-height: 44px; }
        body.admin .tb-profile-btn { min-height: 44px; }
        body.admin .tb-search { height: 44px; }
        body.admin .toast .t-close { width: 44px; height: 44px; }
    }
    </style>

    <?php /* assets/js/admin-ui.js is NOT loaded here. It lives in
             admin/partials/foot.php as a classic script right after admin.js,
             which is the contract its own header states. With `defer` in <head> it
             executed after the whole document was parsed, so window.AdminUI was
             undefined for foot.php's shell wiring — and that wiring now calls
             AdminUI._trapFocus / _hideBackground to make the off-canvas rail a
             real modal instead of a second implementation of one. */ ?>
</head>
<body class="admin">
<a class="sr-only sr-only-focusable admin-skip" href="#adminContent">Skip to main content</a>
<div class="admin-layout">
    <?php /* No aria-hidden: this carries [data-sidebar-close], so it is an
             interactive close target, and hiding it from assistive tech while the
             drawer is open was a contradiction. It is a <div> with no tabindex, so
             it was never a tab stop either way — foot.php inert-s the background
             and traps focus inside the rail, which is what actually makes the
             drawer modal for keyboard and screen-reader users. */ ?>
    <div class="admin-overlay" id="adminOverlay" data-sidebar-close></div>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-topbar" id="adminTopbar">
            <div class="tb-left">
                <button class="tb-icon-btn tb-hamburger" type="button" id="sidebarToggle"
                        aria-controls="adminSidebar" aria-expanded="false"
                        aria-label="Toggle sidebar" title="Toggle sidebar"><?= lucide('menu') ?></button>

                <div class="tb-heading">
                    <?= $crumbHtml ?>
                    <h1 class="tb-title"><?= e($title) ?></h1>
                </div>

                <div class="tb-search" id="tbSearch">
                    <span class="tb-search-ico" aria-hidden="true"><?= lucide('search') ?></span>
                    <?php /* aria-keyshortcuts is announced only once focus is already
                             here — which is what the shortcut was for. The <kbd> hint is
                             aria-hidden AND hidden below 640px, and the `/` binding is
                             documented nowhere at all. The .sr-only description carries
                             both into the accessibility tree without changing the visual. */ ?>
                    <input type="text" id="tbSearchInput" class="tb-search-input" placeholder="Search menu&hellip;"
                           autocomplete="off" spellcheck="false" role="combobox" aria-expanded="false"
                           aria-controls="tbSearchResults" aria-autocomplete="list"
                           aria-keyshortcuts="Control+K Meta+K" aria-describedby="tbSearchHint"
                           aria-label="Search admin menu">
                    <kbd class="tb-search-kbd" id="tbSearchKbd" aria-hidden="true">Ctrl K</kbd>
                    <span class="sr-only" id="tbSearchHint">Press Control plus K, Command plus K, or slash, to jump to this search from anywhere in the admin panel.</span>
                    <div class="tb-search-results" id="tbSearchResults" role="listbox" aria-label="Search results"></div>
                </div>
            </div>

            <div class="tb-right">
                <div class="tb-clock" id="tbClock" aria-hidden="true">
                    <span class="tb-clock-date"></span>
                    <span class="tb-clock-time"></span>
                </div>

                <?php if ($quickCreate): ?>
                <div class="tb-pop" id="tbQuick">
                    <button class="tb-icon-btn tb-quick" type="button" data-pop-toggle="tbQuick"
                            aria-expanded="false" aria-label="Create new" title="Create new"><?= lucide('plus') ?></button>
                    <div class="tb-menu tb-quick-menu" aria-label="Create new">
                        <span class="tb-menu-label">Create new</span>
                        <?php foreach ($quickCreate as [$qSlug, $qIcon, $qLabel, $qHref]): ?>
                            <a class="tb-menu-item" href="<?= e(admin_url($qHref)) ?>"><?= lucide($qIcon) ?><span><?= e($qLabel) ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <button class="tb-icon-btn tb-theme" type="button" data-theme-toggle aria-label="Toggle theme" title="Toggle day / night"><?= lucide('moon') ?></button>

                <div class="tb-pop" id="tbNotif">
                    <button class="tb-icon-btn tb-bell" type="button" data-pop-toggle="tbNotif" aria-expanded="false"
                            aria-label="Notifications<?= $notifTotal > 0 ? ' — ' . (int) $notifTotal . ' pending' : ' — nothing pending' ?>"
                            title="Notifications">
                        <?= lucide('bell') ?>
                        <?php if ($notifTotal > 0): ?><span class="tb-badge" aria-hidden="true"><?= $notifTotal > 99 ? '99+' : (int) $notifTotal ?></span><?php endif; ?>
                    </button>
                    <div class="tb-menu tb-notif-menu" aria-label="Notifications">
                        <div class="tb-menu-head">
                            <strong>Notifications</strong>
                            <?php if ($notifTotal > 0): ?><span class="tb-menu-count"><?= (int) $notifTotal ?> pending</span><?php endif; ?>
                        </div>
                        <div class="tb-notif-list">
                            <?php if (empty($notifs)): ?>
                                <div class="tb-notif-empty"><?= lucide('check-check') ?><span>You&rsquo;re all caught up</span></div>
                            <?php else: foreach ($notifs as $n): ?>
                                <a class="tb-notif-item" href="<?= e($n['url']) ?>">
                                    <span class="tb-notif-ico"><?= lucide($n['icon']) ?></span>
                                    <span class="tb-notif-text"><?= e($n['label']) ?></span>
                                    <span class="tb-notif-count"><?= $n['count'] > 99 ? '99+' : (int) $n['count'] ?><span class="sr-only"> pending</span></span>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
                        <?php $notifAll = $adminCan('notifications') ? admin_url('notifications') : admin_url('dashboard'); ?>
                        <a class="tb-menu-foot" href="<?= e($notifAll) ?>">All notifications <?= lucide('arrow-right') ?></a>
                    </div>
                </div>

                <a class="tb-icon-btn tb-viewsite" href="<?= e(url('/')) ?>" target="_blank" rel="noopener" title="View website" aria-label="View website (opens in a new tab)"><?= lucide('external-link') ?></a>

                <div class="tb-pop tb-profile-pop" id="tbProfile">
                    <button class="tb-profile-btn" type="button" data-pop-toggle="tbProfile" aria-expanded="false"
                            aria-label="Account menu — <?= e($admin['name'] ?? 'Admin') ?>, <?= e($adminRole) ?>">
                        <img class="tb-avatar" src="<?= e(image_url($admin['avatar'] ?? null, 'avatar')) ?>" alt="" width="38" height="38">
                        <span class="tb-profile-meta">
                            <strong class="tb-profile-name"><?= e($admin['name'] ?? 'Admin') ?></strong>
                            <span class="tb-profile-role"><?= e($adminRole) ?></span>
                        </span>
                        <span class="tb-profile-chev" aria-hidden="true"><?= lucide('chevron-down') ?></span>
                    </button>
                    <div class="tb-menu tb-profile-menu" aria-label="Account">
                        <div class="tb-menu-userhead">
                            <img class="tb-avatar-lg" src="<?= e(image_url($admin['avatar'] ?? null, 'avatar')) ?>" alt="" width="46" height="46">
                            <div class="tb-menu-userinfo">
                                <strong><?= e($admin['name'] ?? 'Admin') ?></strong>
                                <span class="tb-profile-role"><?= e($adminRole) ?></span>
                                <span class="tb-menu-email"><?= e($admin['email'] ?? '') ?></span>
                            </div>
                        </div>
                        <?php foreach ($accountItems as [$mSlug, $mIcon, $mLabel, $mHref]): ?>
                            <a class="tb-menu-item" href="<?= e($mHref) ?>"><?= lucide($mIcon) ?><span><?= e($mLabel) ?></span></a>
                        <?php endforeach; ?>
                        <a class="tb-menu-item" href="<?= e(url('faqs')) ?>" target="_blank" rel="noopener"><?= lucide('circle-help') ?><span>Help</span></a>
                        <div class="tb-menu-sep" role="separator"></div>
                        <a class="tb-menu-item tb-menu-danger" href="<?= e(admin_url('logout')) ?>"><?= lucide('log-out') ?><span>Logout</span></a>
                    </div>
                </div>
            </div>
        </header>

        <main class="admin-content" id="adminContent" tabindex="-1">
            <?php /* -----------------------------------------------------------------
              FLASH MESSAGES — routed through the toast component.

              `.alert alert-success` / `.alert alert-danger` differ ONLY by a
              background tint and a border tint: identical text colour, no glyph,
              no word (admin-pro.css even sets display:flex; gap for an icon that
              was never emitted). And there was no role, so after a POST-redirect
              success and failure were indistinguishable to a colour-blind admin
              and silent to a screen-reader one — on the panel's most-seen status
              surface. admin_toast_stack() consumes get_flashes()'s
              `type => [msg, …]` shape directly and adds glyph + tone word +
              role=alert|status; admin-ui.js adopts these on load, which is what
              gives them a working close button and a timer (errors persist).

              One stack per document: admin_toast_stack() records that it printed
              one and admin/partials/foot.php skips its own mount accordingly.
              The inline fallback below is for a deployment where
              includes/admin_ui.php is not present — same three cues, by hand.
              ----------------------------------------------------------------- */ ?>
            <?php if (!empty($flashes)):
                if (function_exists('admin_toast_stack')):
                    echo admin_toast_stack($flashes);
                else:
                    foreach ($flashes as $flashType => $flashList):
                        $flashBad = in_array($flashType, ['error', 'danger', 'warning', 'warn'], true);
                        foreach ($flashList as $flashMsg): ?>
                            <div class="alert alert-<?= e($flashType) ?>" role="<?= $flashBad ? 'alert' : 'status' ?>">
                                <strong><?= e(ucfirst((string) $flashType)) ?>:</strong> <?= e($flashMsg) ?>
                            </div>
                        <?php endforeach;
                    endforeach;
                endif;
            endif; ?>
