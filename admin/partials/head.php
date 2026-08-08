<?php
/**
 * =============================================================================
 *  Admin layout head — opens the page, sidebar, topbar and content area.
 *  Every admin page (except login) does:
 *      require_once __DIR__ . '/../includes/bootstrap.php';
 *      require_admin();
 *      $page_title = 'Programs';
 *      include __DIR__ . '/partials/head.php';
 *      ... page ...
 *      include __DIR__ . '/partials/foot.php';
 * =============================================================================
 */
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}
require_admin();

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
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <script>(function(){try{var t=localStorage.getItem('pwf-theme');if(!t){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
    <?= csrf_meta() ?>
    <script>window.PWF_CKUPLOAD = <?= json_encode(admin_url('ckeditor-upload')) ?>;</script>
    <title><?= e($title) ?> · <?= e($siteName) ?> Admin</title>
    <link rel="icon" href="<?= e(asset('images/favicon.jpg')) ?>" type="image/jpeg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/premium.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin-pro.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/ui.css')) ?>">
    <?php echo function_exists('theme_style_tag') ? theme_style_tag('admin') : ''; ?>
    <?php if (!empty($load_analytics_css)): ?><link rel="stylesheet" href="<?= e(asset('css/analytics.css')) ?>"><?php endif; ?>
</head>
<body class="admin">
<div class="admin-layout">
    <div class="admin-overlay" id="adminOverlay"></div>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-topbar" id="adminTopbar">
            <div class="tb-left">
                <button class="tb-icon-btn tb-hamburger" id="sidebarToggle" aria-label="Toggle sidebar"><?= lucide('menu') ?></button>
                <h1 class="tb-title"><?= e($title) ?></h1>

                <div class="tb-search" id="tbSearch">
                    <span class="tb-search-ico" aria-hidden="true"><?= lucide('search') ?></span>
                    <input type="text" id="tbSearchInput" class="tb-search-input" placeholder="Search menu&hellip;"
                           autocomplete="off" spellcheck="false" role="combobox" aria-expanded="false"
                           aria-controls="tbSearchResults" aria-autocomplete="list" aria-label="Search admin menu">
                    <kbd class="tb-search-kbd" aria-hidden="true">Ctrl K</kbd>
                    <div class="tb-search-results" id="tbSearchResults" role="listbox" aria-label="Search results"></div>
                </div>
            </div>

            <div class="tb-right">
                <div class="tb-clock" id="tbClock" aria-hidden="true">
                    <span class="tb-clock-date"></span>
                    <span class="tb-clock-time"></span>
                </div>

                <button class="tb-icon-btn tb-theme" data-theme-toggle aria-label="Toggle theme" title="Toggle day / night"><?= lucide('moon') ?></button>

                <div class="tb-pop" id="tbNotif">
                    <button class="tb-icon-btn tb-bell" data-pop-toggle="tbNotif" aria-expanded="false" aria-label="Notifications">
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
                                    <span class="tb-notif-count"><?= $n['count'] > 99 ? '99+' : (int) $n['count'] ?></span>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
                        <a class="tb-menu-foot" href="<?= e(admin_url('dashboard')) ?>">Go to dashboard <?= lucide('arrow-right') ?></a>
                    </div>
                </div>

                <a class="tb-icon-btn tb-viewsite" href="<?= e(url('/')) ?>" target="_blank" rel="noopener" title="View website" aria-label="View website"><?= lucide('external-link') ?></a>

                <div class="tb-pop tb-profile-pop" id="tbProfile">
                    <button class="tb-profile-btn" data-pop-toggle="tbProfile" aria-expanded="false">
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
                                <span class="tb-menu-email"><?= e($admin['email'] ?? '') ?></span>
                            </div>
                        </div>
                        <a class="tb-menu-item" href="<?= e(admin_url('profile')) ?>"><?= lucide('circle-user') ?><span>My Profile</span></a>
                        <a class="tb-menu-item" href="<?= e(admin_url('settings')) ?>"><?= lucide('settings') ?><span>Settings</span></a>
                        <a class="tb-menu-item" href="<?= e(admin_url('profile')) ?>#change-password"><?= lucide('key-round') ?><span>Change Password</span></a>
                        <div class="tb-menu-sep" role="separator"></div>
                        <a class="tb-menu-item tb-menu-danger" href="<?= e(admin_url('logout')) ?>"><?= lucide('log-out') ?><span>Logout</span></a>
                    </div>
                </div>
            </div>
        </header>

        <div class="admin-content">
            <?php if (!empty($flashes)): foreach ($flashes as $flashType => $flashList): foreach ($flashList as $flashMsg): ?>
                <div class="alert alert-<?= e($flashType) ?>"><?= e($flashMsg) ?></div>
            <?php endforeach; endforeach; endif; ?>
