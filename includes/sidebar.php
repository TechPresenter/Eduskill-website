<?php
/**
 * =============================================================================
 *  Admin sidebar — the navigation rail of the admin application shell.
 *  Included by admin/partials/head.php.
 * -----------------------------------------------------------------------------
 *  THREE STATES, one markup tree:
 *
 *    expanded    (default, >992px)      full labels, accordion groups
 *    collapsed   (body.sidebar-collapsed) 76px icon rail — the choice is
 *                persisted by admin.js under localStorage `pwf-admin-collapsed`
 *    drawer      (<=992px, body.sidebar-open) off-canvas over a scrim
 *
 *  ACCESSIBLE NAME IN THE COLLAPSED RAIL — the label is CLIPPED, never removed.
 *  admin.css hides `.lbl` / `.ngh-label` with `display:none` when the rail
 *  collapses, which takes them out of the accessibility tree as well. That was
 *  survivable for a plain link (HTML-AAM falls back to `title`) but WRONG for
 *  the 14 badged ones: `.badge-count` stays visible, so name-from-content became
 *  non-empty — "3 pending" — and the title fallback was never reached. Every
 *  module an admin actually needs to act on announced as a bare number with no
 *  name. admin/partials/head.php therefore overrides the collapsed rule with the
 *  .sr-only clip idiom, so the name is "Members 3 pending" in BOTH states and
 *  `title` degrades to what it should always have been: a hover hint for labels
 *  that `text-overflow: ellipsis` has truncated.
 *
 *  `data-tooltip` (the styled flyout in admin.css) is deliberately NOT used on
 *  nav links: `.sidebar-nav` is the scroll container (`overflow-x:hidden`) and
 *  `.sidebar-link` sets `overflow:hidden` for the ripple, so that flyout is
 *  clipped twice over and never reaches the screen. The keyboard-visible label
 *  for the collapsed rail is a body-level flyout built in foot.php, positioned
 *  from the focused row — the only version that escapes both clips.
 *
 *  VARIABLE NAMES ARE PREFIXED `sb`. This file is included at global scope by
 *  admin/partials/head.php, in the middle of a page that has already set up its
 *  own variables. A bare `foreach ($links as [$slug, $icon, $label])` clobbers
 *  any `$label`/`$slug`/`$icon`/`$links`/`$group` the page was holding — and
 *  several pages hold exactly those. Nothing here writes an unprefixed name.
 * =============================================================================
 */
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

/** Active class helper for a given admin module slug. */
if (!function_exists('admin_active')) {
    function admin_active(string $module): string
    {
        $current = trim(current_path(), '/');           // e.g. admin/programs
        return ($current === 'admin/' . $module) ? 'is-active' : '';
    }
}

/**
 * Sidebar structure: group => [ [slug, lucide-icon, label], ... ].
 *
 * The ONE source of truth is admin_nav_groups() (includes/rbac.php) so the menu
 * and the RBAC permission map can never drift. This file used to carry a verbatim
 * 140-line copy of that array "as a fallback", which meant every new module had
 * to be added twice; it has been reduced to the three routes rbac_core_slugs()
 * guarantees are always reachable, which is all a fallback can honestly promise.
 */
$sbGroups = function_exists('admin_nav_groups') ? admin_nav_groups() : [
    'Main'   => [['dashboard', 'layout-dashboard', 'Dashboard']],
    'System' => [['profile',   'circle-user',      'My Profile']],
];

/**
 * Live pending-action counts → badge counters on the sidebar.
 * Shared single source of truth with the topbar notification bell
 * (admin_pending_counts() in helper.php; memoised, table-isolated).
 */
$sbBadges = admin_pending_counts();

/**
 * Per-group icon name.
 *
 * This array used to carry a second column: a per-group "accent colour" piped
 * into a `--gc` custom property on each `.nav-group`. Those 18 values spent a
 * palette the brand does not have (indigo, violet, pink, rose, teal, fuchsia),
 * several were mislabelled, and 17 accents meant navigation was encoded by hue
 * alone — meaning the group label already carries in words. The colour column is
 * gone, and with it the `style="--gc:…"` attribute: admin.css:329-347 removed
 * every rule that read `--gc`, so the property now has no consumer anywhere in
 * the project (verified repo-wide) and writing it was pure dead weight.
 *
 * The icon names STAY — `.ngh-ico` is only hidden by CSS, not retired, and the
 * names are the group's identity for anything that renders it.
 */
$sbGroupMeta = [
    'Main'                    => 'layout-dashboard',
    'Website Content'         => 'layout-grid',
    'Blog'                    => 'pen-line',
    'Media'                   => 'image',
    'Engagement'              => 'heart-handshake',
    'People'                  => 'users',
    'Employee Management'     => 'id-card',
    'School Management'       => 'school',
    'Student Management'      => 'graduation-cap',
    'Learning (LMS)'          => 'book-open',
    'Programs & Applications' => 'briefcase',
    'Document Hub'            => 'stamp',
    'Communication'           => 'mail',
    'Email Marketing'         => 'send',
    'Messaging & Push'        => 'message-circle',
    'Referral & Coupons'      => 'ticket',
    'Marketing & SEO'         => 'megaphone',
    'System'                  => 'settings-2',
];

/* ---- The signed-in administrator, for the profile block pinned at the bottom. */
$sbUser  = function_exists('current_user') ? current_user() : null;
$sbRole  = 'Administrator';
if (!empty($sbUser['role_id'])) {
    $sbRoleRow = find('roles', (int) $sbUser['role_id']);
    if ($sbRoleRow) { $sbRole = $sbRoleRow['name']; }
}
$sbName  = (string) ($sbUser['name'] ?? 'Admin');
$sbEmail = (string) ($sbUser['email'] ?? '');
$sbSite  = function_exists('get_setting') ? (string) get_setting('site_name', SITE_NAME) : SITE_NAME;
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Admin sidebar">
    <div class="sidebar-head">
        <a class="sidebar-brand" href="<?= e(admin_url('dashboard')) ?>" title="<?= e($sbSite) ?> — Admin Panel">
            <img class="sb-logo" src="<?= e(asset('images/logo-128.webp')) ?>" alt="" width="38" height="38">
            <span class="sb-brand-txt"><?= e($sbSite) ?><small>Admin Panel</small></span>
        </a>
        <?php /* Desktop: collapse / expand the rail. admin.js persists the choice. */ ?>
        <button class="sidebar-collapse" type="button" data-sidebar-collapse
                aria-controls="adminSidebar" aria-expanded="true"
                aria-label="Collapse sidebar" title="Collapse sidebar"><?= lucide('chevrons-left') ?></button>
        <?php /* Mobile drawer: an in-drawer close, because the topbar hamburger
                 sits UNDER the scrim (topbar z-index 60 vs scrim 190) once the
                 drawer is open and cannot be used to close it again. */ ?>
        <button class="sidebar-close" type="button" data-sidebar-close
                aria-controls="adminSidebar" aria-label="Close navigation" title="Close navigation"><?= lucide('x') ?></button>
    </div>

    <nav class="sidebar-nav" id="adminSidebarNav" aria-label="Admin navigation">
        <?php $sbGroupIndex = 0;
        foreach ($sbGroups as $sbGroup => $sbLinks):
            /* RBAC: an unauthorised module is not rendered at all — no link, no
               label, no badge. Semantics unchanged: the same per-slug call as
               before, which is strictly finer-grained than rbac_can_group()
               because it also lets rbac_core_slugs() (dashboard, profile) through
               inside an otherwise-denied group, so no role can lose its own
               profile page. Enforcement itself is default-OFF (`rbac_enforce`),
               so with it off every item shows — that is rbac.php's decision to
               make, not this file's. */
            if (function_exists('rbac_can_slug')) {
                $sbLinks = array_values(array_filter($sbLinks, static fn ($sbL) => rbac_can_slug($sbL[0])));
            }
            if (!$sbLinks) { continue; }

            $sbGroupIndex++;
            $sbGroupId     = 'navgrp-' . (function_exists('slugify') ? slugify($sbGroup) : $sbGroupIndex);
            $sbGroupActive = false;
            $sbGroupBadges = 0;
            foreach ($sbLinks as $sbL) {
                if (admin_active($sbL[0]) !== '') { $sbGroupActive = true; }
                $sbGroupBadges += (int) ($sbBadges[$sbL[0]] ?? 0);
            }
            /* Resolved but not rendered: `.ngh-ico` is currently hidden in CSS.
               Kept so restoring the group icon is a markup change, not a data one. */
            $sbGroupIcon = $sbGroupMeta[$sbGroup] ?? 'folder'; ?>
            <?php /* data-nav-current marks the group holding the active page. The
                     accordion state is persisted per group, so a group the admin
                     folded earlier would otherwise hide the item they are looking
                     at; the shell script re-opens this one on load. It is also a
                     :has()-free hook for styling the current section. */ ?>
            <div class="nav-group is-open" data-nav-group<?= $sbGroupActive ? ' data-nav-current' : '' ?>>
                <button class="nav-group-head" type="button" data-group-toggle
                        aria-expanded="true" aria-controls="<?= e($sbGroupId) ?>" title="<?= e($sbGroup) ?>">
                    <span class="ngh-label"><?= e($sbGroup) ?></span>
                    <?php if ($sbGroupBadges > 0): ?>
                        <?php /* Surfaced when the group is CLOSED, so pending work never
                                 hides behind a folded section. Number + label text, never
                                 colour alone. NOT in the collapsed rail: there the bodies
                                 are force-open by CSS while `.is-open` is whatever
                                 localStorage holds, so a previously-folded group showed
                                 this rollup directly above its already-visible items —
                                 the same count twice. head.php scopes it accordingly. */ ?>
                        <span class="ngh-badge"><?= $sbGroupBadges > 99 ? '99+' : $sbGroupBadges ?><span class="sr-only"> items need attention in <?= e($sbGroup) ?></span></span>
                    <?php endif; ?>
                    <span class="ngh-chevron"><?= lucide('chevron-down') ?></span>
                </button>
                <div class="nav-group-body" id="<?= e($sbGroupId) ?>"><div class="nav-inner">
                    <?php foreach ($sbLinks as [$sbSlug, $sbIcon, $sbLabel]):
                        $sbIsActive = admin_active($sbSlug) !== ''; ?>
                        <?php /* data-initial is the collapsed rail's floor. lucide() emits
                                 <i data-lucide> which stays EMPTY until the CDN script in
                                 foot.php lands; collapsed, the label is clipped, so until
                                 then every row was a blank 76px box with nothing in it at
                                 all. head.php paints this letter into an :empty .ico. */ ?>
                        <a class="sidebar-link<?= $sbIsActive ? ' is-active' : '' ?>"
                           href="<?= e(admin_url($sbSlug)) ?>"
                           title="<?= e($sbLabel) ?>"<?= $sbIsActive ? ' aria-current="page"' : '' ?>>
                            <span class="ico" data-initial="<?= e(mb_strtoupper(mb_substr($sbLabel, 0, 1))) ?>"><?= lucide($sbIcon) ?></span>
                            <span class="lbl"><?= e($sbLabel) ?></span>
                            <?php if (!empty($sbBadges[$sbSlug])): $sbN = (int) $sbBadges[$sbSlug]; ?>
                                <?php /* No `pulse`: a 1.9s infinite animation on a
                                         permanently visible element with no pause/stop/hide
                                         control fails WCAG 2.2.2 (prefers-reduced-motion is
                                         a UA preference, not the mechanism the SC asks for),
                                         it ran on up to 14 rows at once, and 1.9s is far
                                         outside the design system's 150-400ms budget. The
                                         red pill, the number and the .sr-only word already
                                         carry the signal three ways. */ ?>
                                <span class="badge-count"><?= $sbN > 99 ? '99+' : $sbN ?><span class="sr-only"> pending</span></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div></div>
            </div>
        <?php endforeach; ?>

        <div class="nav-group is-open is-static">
            <div class="nav-group-body"><div class="nav-inner">
                <a class="sidebar-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener" title="View Website (opens in a new tab)">
                    <span class="ico" data-initial="V"><?= lucide('globe') ?></span><span class="lbl">View Website</span>
                </a>
                <a class="sidebar-link sb-logout" href="<?= e(admin_url('logout')) ?>" title="Logout">
                    <span class="ico" data-initial="L"><?= lucide('log-out') ?></span><span class="lbl">Logout</span>
                </a>
            </div></div>
        </div>
    </nav>

    <?php /* Profile block pinned at the bottom: photo, name, role, email. */ ?>
    <a class="sidebar-user sidebar-user-foot" href="<?= e(admin_url('profile')) ?>"
       title="<?= e($sbName) ?> — <?= e($sbRole) ?><?= $sbEmail !== '' ? ' (' . e($sbEmail) . ')' : '' ?>">
        <img class="su-avatar" src="<?= e(image_url($sbUser['avatar'] ?? null, 'avatar')) ?>" alt="" width="40" height="40">
        <span class="su-meta">
            <strong class="su-name"><?= e($sbName) ?></strong>
            <span class="su-role-badge"><?= e($sbRole) ?></span>
            <?php if ($sbEmail !== ''): ?><span class="su-email"><?= e($sbEmail) ?></span><?php endif; ?>
        </span>
        <span class="su-cog"><?= lucide('settings') ?></span>
    </a>
</aside>
