<?php
/**
 * =============================================================================
 *  Admin sidebar — collapsible navigation. Included by admin/partials/head.php.
 *  Every admin module has a link here; the active item is highlighted.
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

/** Sidebar structure: group => [ [slug, lucide-icon, label], ... ].
 *  Sourced from admin_nav_groups() (includes/rbac.php) so the nav and the RBAC
 *  permission map share one definition; the inline array is a fallback only. */
$sidebar = function_exists('admin_nav_groups') ? admin_nav_groups() : [
    'Main' => [
        ['dashboard', 'layout-dashboard', 'Dashboard'],
    ],
    'Website Content' => [
        ['settings',        'settings',        'Website Settings'],
        ['theme',           'palette',         'Theme Settings'],
        ['header-settings', 'panel-top',       'Header Designer'],
        ['footer-settings', 'panel-bottom',    'Footer Builder'],
        ['menus',           'menu',            'Menu Manager'],
        ['hero-slides',     'images',          'Hero Slider'],
        ['sections',        'layout-grid',     'Homepage Sections'],
        ['pages',           'file-text',       'Pages'],
        ['programs',        'target',          'Programs'],
        ['projects',        'folder',          'Projects'],
        ['page-builder',    'layout-template', 'Page Builder'],
        ['widgets',         'layout-panel-left','Widgets'],
    ],
    'Blog' => [
        ['blogs',           'pen-line',        'Blogs'],
        ['blog-categories', 'folder-tree',     'Categories'],
        ['blog-tags',       'tags',            'Tags'],
        ['comments',        'message-square',  'Comments'],
    ],
    'Media' => [
        ['gallery-albums',  'images',          'Gallery Albums'],
        ['gallery-media',   'image',           'Gallery Media'],
        ['videos',          'video',           'Videos'],
        ['media-library',   'library',         'Media Library'],
        ['documents',       'file-down',       'Documents'],
    ],
    'Engagement' => [
        ['events',          'calendar-days',   'Events'],
        ['event-registrations', 'ticket',      'Registrations'],
        ['awareness-calendar', 'calendar-heart', 'Awareness Calendar'],
        ['campaigns',       'megaphone',       'Campaigns'],
        ['donations',       'coins',           'Donations'],
        ['payments',        'wallet-cards',    'All Payments'],
        ['donation-reports','bar-chart-4',     'Donation Reports'],
        ['tax-certificates','file-badge',      '80G Certificates'],
        ['payment-settings','credit-card',     'Payment Settings'],
    ],
    'People' => [
        ['team',            'users',           'Team Members'],
        ['testimonials',    'star',            'Testimonials'],
        ['partners',        'handshake',       'Partners'],
        ['sponsors',        'gem',             'Sponsors'],
        ['volunteers',      'hand-heart',      'Volunteers'],
        ['internships',     'graduation-cap',  'Internships'],
        ['members',         'id-card',         'Members'],
    ],
    'School Management' => [
        ['schools',            'school',          'Schools'],
        ['school-students',    'users-round',     'Students'],
        ['school-batches',     'layers',          'Batches'],
        ['school-staff',       'user-cog',        'Staff'],
        ['school-fees',        'receipt-indian-rupee', 'Fees'],
        ['school-attendance',  'calendar-check',  'Attendance'],
    ],
    'Student Management' => [
        ['admissions',    'file-check',       'Admissions'],
        ['certificates',  'award',            'Certificates'],
        ['marksheets',    'file-spreadsheet', 'Marksheets'],
        ['assignments',   'clipboard-check',  'Assignments'],
        ['notices',       'bell',             'Notices'],
        ['student-performance', 'chart-column', 'Performance'],
    ],
    'Learning (LMS)' => [
        ['courses',       'book-open',        'Courses'],
        ['exams',         'file-question',    'Exams & Quizzes'],
        ['live-sessions', 'video',            'Live Classes'],
    ],
    'Employee Management' => [
        ['employees',            'users-round',    'Employees'],
        ['departments',          'building-2',     'Departments'],
        ['employee-attendance',  'calendar-check', 'Attendance'],
        ['leave-requests',       'plane',          'Leave Requests'],
        ['employee-salary',      'wallet',         'Salary & Payslips'],
        ['performance-reviews',  'star',           'Performance'],
    ],
    'Programs & Applications' => [
        ['schemes',                  'scroll',         'Schemes'],
        ['scholarships',             'graduation-cap', 'Scholarships'],
        ['scholarship-applications', 'clipboard-list', 'Scholarship Apps'],
        ['careers',                  'briefcase',      'Careers'],
        ['job-applications',         'inbox',          'Job Applications'],
        ['membership-plans',         'badge-dollar-sign', 'Membership Plans'],
        ['membership-applications',  'clipboard-pen',  'Membership Apps'],
        ['membership-renewals',      'receipt',        'Renewals'],
        ['membership-settings',      'credit-card',    'Membership Settings'],
        ['partner-applications',     'handshake',      'Partner Requests'],
        ['issued-certificates',      'award',          'Certificates (Issued)'],
    ],
    'Document Hub' => [
        ['document-hub',             'stamp',          'Templates & Documents'],
        ['document-builder',         'file-cog',       'Template Builder'],
    ],
    'Communication' => [
        ['contact-messages','mail',            'Contact Messages'],
        ['feedback',        'message-square-text', 'Feedback'],
        ['newsletter',      'send',            'Newsletter'],
        ['faqs',            'circle-help',     'FAQs'],
    ],
    'Email Marketing' => [
        ['email-dashboard',        'layout-dashboard', 'Email Dashboard'],
        ['email-mailbox',          'inbox',            'Mailbox'],
        ['email-compose',          'pencil',           'Compose'],
        ['email-campaigns',        'send',             'Campaigns'],
        ['email-template-library', 'layout-template',  'Template Library'],
        ['email-contacts',         'contact',          'Contacts'],
        ['subscribers',            'users',            'Subscribers'],
        ['email-automations',      'workflow',         'Automations'],
        ['email-analytics',        'bar-chart-3',      'Analytics'],
        ['email-templates',        'mail-plus',        'Templates (Raw)'],
        ['smtp-profiles',          'server',           'SMTP Profiles'],
        ['email-settings',         'settings-2',       'Email Settings'],
    ],
    'Messaging & Push' => [
        ['sms',           'message-square-more', 'SMS Gateway'],
        ['whatsapp',      'message-circle',      'WhatsApp'],
        ['push',          'bell-ring',           'Push (OneSignal)'],
        ['notifications', 'inbox',               'Notifications'],
    ],
    'Marketing & SEO' => [
        ['analytics',       'trending-up',     'Visitor Analytics'],
        ['seo',             'search',          'SEO Manager'],
        ['tracking',        'radar',           'Tracking & Pixels'],
        ['robots',          'bot',             'Robots.txt'],
        ['redirects',       'route',           'Redirects'],
        ['sitemap',         'map',             'Sitemap'],
        ['social-links',    'share-2',         'Social Media'],
        ['announcements',   'megaphone',       'Announcement Bar'],
        ['popups',          'app-window',      'Popups'],
    ],
    'Referral & Coupons' => [
        ['referral-codes',   'link',            'Referral Codes'],
        ['referrals',        'git-branch',      'Referral Tracking'],
        ['coupons',          'ticket',          'Coupons'],
        ['coupon-analytics', 'bar-chart-3',     'Coupon Analytics'],
    ],
    'System' => [
        ['users',           'user',            'Users'],
        ['roles',           'shield-check',    'Roles & Permissions'],
        ['security',        'shield-alert',    'Security Center'],
        ['custom-code',     'code-xml',        'Custom CSS / JS'],
        ['activity-logs',   'history',         'Activity Logs'],
        ['profile',         'circle-user',     'My Profile'],
    ],
];

/**
 * Live pending-action counts → badge counters on the sidebar.
 * Shared single source of truth with the topbar notification bell
 * (admin_pending_counts() in helper.php; memoised, table-isolated).
 */
$badges = admin_pending_counts();
?>
<?php
/** Per-group icon + accent colour (curated professional palette). */
$groupMeta = [
    'Main'                    => ['layout-dashboard', '#6366f1'], // indigo
    'Website Content'         => ['layout-grid',       '#58A42F'], // emerald
    'Blog'                    => ['pen-line',          '#E67B1D'], // amber
    'Media'                   => ['image',             '#8b5cf6'], // violet
    'Engagement'              => ['heart-handshake',   '#ec4899'], // pink
    'People'                  => ['users',             '#084881'], // cyan
    'Employee Management'     => ['id-card',           '#084881'], // teal
    'School Management'       => ['school',            '#084881'], // blue
    'Student Management'      => ['graduation-cap',    '#084881'], // sky
    'Learning (LMS)'          => ['book-open',         '#8b5cf6'], // violet
    'Programs & Applications' => ['briefcase',         '#E67B1D'], // orange
    'Document Hub'            => ['stamp',             '#e11d48'], // rose
    'Communication'           => ['mail',              '#14b8a6'], // teal
    'Email Marketing'         => ['send',              '#f43f5e'], // rose
    'Messaging & Push'        => ['message-circle',    '#58A42F'], // green
    'Referral & Coupons'      => ['ticket',            '#a855f7'], // purple
    'Marketing & SEO'         => ['megaphone',         '#d946ef'], // fuchsia
    'System'                  => ['settings-2',        '#084881'], // sky
];
$sbUser = function_exists('current_user') ? current_user() : null;
$sbRole = 'Administrator';
if (!empty($sbUser['role_id'])) { $r = find('roles', (int) $sbUser['role_id']); if ($r) $sbRole = $r['name']; }
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Admin sidebar">
    <div class="sidebar-head">
        <a class="sidebar-brand" href="<?= e(admin_url('dashboard')) ?>">
            <img class="sb-logo" src="<?= e(asset('images/logo-128.webp')) ?>" alt="" width="38" height="38">
            <span class="sb-brand-txt">EDUSKILL INDIA FOUNDATION<small>Admin Panel</small></span>
        </a>
        <button class="sidebar-collapse" data-sidebar-collapse aria-label="Collapse sidebar" title="Collapse sidebar"><?= lucide('chevrons-left') ?></button>
    </div>

    <nav class="sidebar-nav" aria-label="Admin navigation">
        <?php foreach ($sidebar as $group => $links):
            // RBAC: hide items (and empty groups) the current role can't access.
            if (function_exists('rbac_can_slug')) {
                $links = array_values(array_filter($links, static fn ($l) => rbac_can_slug($l[0])));
            }
            if (!$links) { continue; }
            $groupActive = false;
            foreach ($links as $l) { if (admin_active($l[0]) !== '') { $groupActive = true; break; } }
            // Sum badges in this group (shown on the collapsed group header).
            $groupBadges = 0;
            foreach ($links as $l) { $groupBadges += (int) ($badges[$l[0]] ?? 0); }
            $gm = $groupMeta[$group] ?? ['folder', '#063566']; ?>
            <div class="nav-group is-open" data-nav-group style="--gc:<?= e($gm[1]) ?>">
                <button class="nav-group-head" type="button" data-group-toggle aria-expanded="true">
                    <span class="ngh-label"><?= e($group) ?></span>
                    <?php if ($groupBadges > 0): ?><span class="ngh-badge"><?= $groupBadges > 99 ? '99+' : $groupBadges ?></span><?php endif; ?>
                    <span class="ngh-chevron"><?= lucide('chevron-down') ?></span>
                </button>
                <div class="nav-group-body"><div class="nav-inner">
                    <?php foreach ($links as [$slug, $icon, $label]): ?>
                        <a class="sidebar-link<?= admin_active($slug) !== '' ? ' is-active' : '' ?>" href="<?= e(admin_url($slug)) ?>" data-tooltip="<?= e($label) ?>">
                            <span class="ico"><?= lucide($icon) ?></span>
                            <span class="lbl"><?= e($label) ?></span>
                            <?php if (!empty($badges[$slug])): ?>
                                <span class="badge-count pulse" title="<?= (int) $badges[$slug] ?> pending"><?= (int) $badges[$slug] > 99 ? '99+' : (int) $badges[$slug] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div></div>
            </div>
        <?php endforeach; ?>

        <div class="nav-group is-open is-static">
            <div class="nav-group-body"><div class="nav-inner">
                <a class="sidebar-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener" data-tooltip="View Website"><span class="ico"><?= lucide('globe') ?></span><span class="lbl">View Website</span></a>
                <a class="sidebar-link sb-logout" href="<?= e(admin_url('logout')) ?>" data-tooltip="Logout"><span class="ico"><?= lucide('log-out') ?></span><span class="lbl">Logout</span></a>
            </div></div>
        </div>
    </nav>

    <a class="sidebar-user sidebar-user-foot" href="<?= e(admin_url('profile')) ?>" data-tooltip="<?= e($sbUser['name'] ?? 'Admin') ?>">
        <img class="su-avatar" src="<?= e(image_url($sbUser['avatar'] ?? null, 'avatar')) ?>" alt="">
        <span class="su-meta">
            <strong class="su-name"><?= e($sbUser['name'] ?? 'Admin') ?></strong>
            <span class="su-role-badge"><?= e($sbRole) ?></span>
        </span>
        <span class="su-cog"><?= lucide('settings') ?></span>
    </a>
</aside>
