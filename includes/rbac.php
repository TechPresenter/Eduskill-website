<?php
/**
 * =============================================================================
 *  RBAC — Role-Based Access Control for the admin panel
 * =============================================================================
 *  Builds on the existing roles / permissions / role_permissions tables and
 *  user_can(). Adds:
 *    - a single source of truth for the admin navigation (admin_nav_groups),
 *    - a permission catalogue with one permission per sidebar group (module),
 *    - an 8-role seeder,
 *    - a central page gate + sidebar filter that restricts non-super-admins to
 *      only their assigned modules.
 *
 *  SAFETY (mirrors the Security Center): enforcement is DEFAULT-OFF (setting
 *  `rbac_enforce`), super-admins (and users with no role) ALWAYS bypass, and
 *  the dashboard / profile / logout are always reachable — so no role change
 *  can lock an administrator out of the panel.
 * =============================================================================
 */

declare(strict_types=1);

/* -----------------------------------------------------------------------------
 | Single source of truth for the admin navigation. sidebar.php renders this;
 | rbac derives the module map + permission catalogue from it, so the two never
 | drift apart.
 | -------------------------------------------------------------------------- */
function admin_nav_groups(): array
{
    return [
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
            ['coordinator-applications', 'users-round',    'Coordinator Apps'],
            ['kanyadaan-applications',   'heart-handshake', 'Kanya Daan Apps'],
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
}

/* -----------------------------------------------------------------------------
 | Module map: one permission per nav group (except Main, which is ungated).
 | -------------------------------------------------------------------------- */

/** Display category for a group, used to organise the Roles permission matrix. */
function rbac_group_category(string $group): string
{
    return match ($group) {
        'Website Content', 'Blog', 'Media', 'Document Hub' => 'Content',
        'Engagement', 'People', 'Communication'            => 'Engagement',
        'School Management', 'Student Management', 'Learning (LMS)' => 'Education',
        'Employee Management', 'Programs & Applications'   => 'Operations',
        'Email Marketing', 'Messaging & Push', 'Marketing & SEO', 'Referral & Coupons' => 'Marketing',
        'System'                                           => 'System',
        default                                            => 'General',
    };
}

/** Permission slug for a group, or null for always-allowed groups. */
function rbac_group_perm(string $group): ?string
{
    if ($group === 'Main') {
        return null;
    }
    return 'grp-' . slugify($group);
}

/** [permSlug => ['name' => groupLabel, 'module' => category]] for all gated groups. */
function rbac_permission_catalogue(): array
{
    $out = [];
    foreach (array_keys(admin_nav_groups()) as $group) {
        $perm = rbac_group_perm($group);
        if ($perm === null) {
            continue;
        }
        $out[$perm] = ['name' => $group, 'module' => rbac_group_category($group)];
    }
    return $out;
}

/**
 * Admin pages that exist on disk but have no nav entry of their own — detail
 * views, sub-resources and print endpoints reached from a parent screen. Each
 * inherits the permission of the group that owns its parent, so a restricted
 * user cannot reach (say) exam questions without access to Learning (LMS).
 *
 * Anything not listed here and not in the nav is denied outright — see
 * rbac_can_slug(). Add new admin pages to admin_nav_groups() or to this map.
 */
function rbac_slug_aliases(): array
{
    return [
        // Engagement — campaigns, donations, tax certificates
        'campaign-gallery'     => 'Engagement',
        'campaign-reports'     => 'Engagement',
        'campaign-updates'     => 'Engagement',
        'campaign-volunteers'  => 'Engagement',
        'donation-receipt'     => 'Engagement',
        'tax-certificate-view' => 'Engagement',
        // Learning (LMS) — course and exam sub-resources
        'course-batches'       => 'Learning (LMS)',
        'course-enrollments'   => 'Learning (LMS)',
        'course-lessons'       => 'Learning (LMS)',
        'exam-attempts'        => 'Learning (LMS)',
        'exam-questions'       => 'Learning (LMS)',
        // Student Management — printable student artefacts
        'certificate-view'     => 'Student Management',
        'marksheet-view'       => 'Student Management',
        'student-card'         => 'Student Management',
        'student-profile'      => 'Student Management',
        // Employee Management
        'employee-documents'   => 'Employee Management',
        'employee-id-card'     => 'Employee Management',
        'payslip'              => 'Employee Management',
        // School Management
        'school-dashboard'     => 'School Management',
        'school-receipt'       => 'School Management',
        // People / Marketing
        'member-card'          => 'People',
        'analytics-data'       => 'Marketing & SEO',
        /* Governs who may self-register and whether approval is automatic —
           an access-control surface, so it belongs to System, not Programs. */
        'registration-settings' => 'System',
    ];
}

/**
 * Slugs every authenticated admin may reach regardless of role: the landing
 * page, their own profile, the auth routes, and two shared endpoints that carry
 * their own authorisation (secure-file re-checks ownership per file;
 * ckeditor-upload backs the editor on every content screen).
 */
function rbac_core_slugs(): array
{
    return ['', 'dashboard', 'profile', 'login', 'logout', 'secure-file', 'ckeditor-upload'];
}

/** Reverse map: which nav group owns a page slug (null = unknown to RBAC). */
function rbac_slug_group(string $slug): ?string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (admin_nav_groups() as $group => $links) {
            foreach ($links as $l) {
                $map[$l[0]] = $group;
            }
        }
        // Nav entries win; aliases only fill gaps.
        foreach (rbac_slug_aliases() as $child => $group) {
            $map[$child] ??= $group;
        }
    }
    return $map[$slug] ?? null;
}

/* -----------------------------------------------------------------------------
 | Enforcement checks (default-OFF, super-admin bypass)
 | -------------------------------------------------------------------------- */

function rbac_enforcement_on(): bool
{
    /* Fail CLOSED. The default was 0, which made role assignment cosmetic: with
       the setting absent, rbac_gate() returned immediately and EVERY row in
       `users` was an unrestricted administrator regardless of its role. A fresh
       install, or a database where the row was never written, silently ran with
       no access control at all. Defaulting to 1 means an operator must opt OUT
       deliberately rather than discover they never opted in. Super-admins and
       role-less legacy admins still bypass, so this cannot lock anyone out. */
    return (int) get_setting('rbac_enforce', 1) === 1;
}

/** Is the user a super admin (or role-less legacy admin)? Always full access. */
function rbac_is_super(?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (empty($user['role_id'])) {
        return true; // no role assigned == unrestricted super admin (existing convention)
    }
    $role = find('roles', (int) $user['role_id']);
    return $role && in_array($role['slug'], ['super-admin', 'administrator'], true);
}

/** May the current user access a given nav group? */
function rbac_can_group(?string $group): bool
{
    if ($group === null || $group === 'Main') {
        return true;
    }
    if (!rbac_enforcement_on() || rbac_is_super()) {
        return true;
    }
    $perm = rbac_group_perm($group);
    return $perm === null ? true : user_can($perm);
}

/** May the current user access a page slug? Core routes are always allowed. */
function rbac_can_slug(string $slug): bool
{
    if (in_array($slug, rbac_core_slugs(), true)) {
        return true;
    }
    if (!rbac_enforcement_on() || rbac_is_super()) {
        return true;
    }
    $group = rbac_slug_group($slug);
    if ($group !== null) {
        return rbac_can_group($group);
    }
    /* Unknown to RBAC. Fail CLOSED when the slug names a real admin page, so a
       newly added screen is never silently ungated; unknown slugs that match no
       file are left alone for the normal 404 path. */
    return !is_file(BASE_PATH . '/admin/' . $slug . '.php');
}

/**
 * The admin page slug for the current request (e.g. 'blogs').
 *
 * Normalised so that URL variants which resolve to the same file also resolve
 * to the same slug: query/fragment dropped, empty and '.'/'..' segments
 * discarded ('/admin/./blogs'), the 'admin' segment matched case-insensitively,
 * a trailing '.php' stripped ('/admin/blogs.php'), and the result lower-cased —
 * Windows and macOS serve 'BLOGS.php' for 'blogs.php', so the gate must not
 * treat differing case as a different page.
 */
function rbac_current_slug(): string
{
    /* SOURCE OF TRUTH: the script Apache actually resolved, not the request line.
       -----------------------------------------------------------------------
       Deriving the slug from REQUEST_URI let the gate and the executed file
       disagree. Apache normalises '..' BEFORE choosing the script, so
       GET /admin/blogs/%2e%2e/security executes admin/security.php while the
       gate saw the slug 'blogs' — any granted module became a key to every
       other one. SCRIPT_NAME is post-normalisation, so the two can no longer
       diverge. REQUEST_URI stays only as a fallback for exotic SAPIs, and the
       '..' handling below now POPS rather than discards. */
    $path = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($path === '') {
        $path = function_exists('current_path') ? current_path() : ($_SERVER['REQUEST_URI'] ?? '');
    }
    $path = (string) $path;
    foreach (['?', '#'] as $cut) {
        if (($p = strpos($path, $cut)) !== false) {
            $path = substr($path, 0, $p);
        }
    }
    $path = rawurldecode(str_replace('\\', '/', $path));

    $parts = [];
    foreach (explode('/', $path) as $seg) {
        $seg = trim($seg);
        if ($seg === '..') {
            array_pop($parts);          // resolve, do not discard
            continue;
        }
        if ($seg === '' || $seg === '.') {
            continue;                   // collapse '//' and './'
        }
        $parts[] = $seg;
    }

    // The page is the segment after the 'admin' directory (if present at all).
    $i = 0;
    foreach ($parts as $k => $seg) {
        if (strcasecmp($seg, 'admin') === 0) {
            $i = $k + 1;
            break;
        }
    }

    $slug = strtolower($parts[$i] ?? '');
    if (str_ends_with($slug, '.php')) {
        $slug = substr($slug, 0, -4);
    }
    $slug = preg_replace('/[^a-z0-9_-]/', '', $slug);
    return $slug === '' ? 'dashboard' : $slug;
}

/**
 * Central admin gate — called from require_admin() AFTER authentication.
 * No-op unless enforcement is on and the user is a restricted (non-super) admin.
 */
function rbac_gate(): void
{
    if (!rbac_enforcement_on() || !is_logged_in() || rbac_is_super()) {
        return;
    }
    $slug = rbac_current_slug();
    if (rbac_can_slug($slug)) {
        return;
    }
    http_response_code(403);
    echo rbac_403_page($slug);
    exit;
}

/** Standalone 403 page (require_admin runs before the admin layout is loaded). */
function rbac_403_page(string $slug): string
{
    $dash = e(admin_url('dashboard'));
    $out  = e(admin_url('logout'));
    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Access denied</title><style>body{margin:0;font-family:system-ui,Segoe UI,sans-serif;background:#f1f5f9;color:#0f172a;display:grid;place-items:center;min-height:100vh}'
        . '.box{max-width:440px;text-align:center;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:2.5rem 2rem;box-shadow:0 20px 46px -24px rgba(11,78,61,.4)}'
        . '.ico{width:64px;height:64px;border-radius:16px;margin:0 auto 1.1rem;display:grid;place-items:center;background:linear-gradient(135deg,#fee2e2,#fecaca);color:#dc2626;font-size:1.8rem}'
        . 'h1{font-size:1.35rem;margin:.2rem 0 .5rem}p{color:#64748b;line-height:1.6;margin:0 0 1.4rem}'
        . 'a{display:inline-block;text-decoration:none;font-weight:650;padding:.6rem 1.1rem;border-radius:11px;margin:0 .3rem}'
        . '.p{background:linear-gradient(135deg,#0B4E3D,#174D3D);color:#fff}.s{background:#f1f5f9;color:#334155}</style></head>'
        . '<body><div class="box"><div class="ico">&#9888;</div><h1>Access restricted</h1>'
        . '<p>Your role does not have permission to open this section. Contact a super administrator if you need access.</p>'
        . '<a class="p" href="' . $dash . '">Back to dashboard</a><a class="s" href="' . $out . '">Sign out</a></div></body></html>';
}

/* -----------------------------------------------------------------------------
 | Seeding — permissions catalogue + the 8 standard roles (idempotent)
 | -------------------------------------------------------------------------- */

/** Insert any missing permission rows from the catalogue. Returns count added. */
function rbac_sync_permissions(): int
{
    $added = 0;
    foreach (rbac_permission_catalogue() as $slug => $meta) {
        $exists = db_value('SELECT id FROM permissions WHERE slug = :s LIMIT 1', [':s' => $slug]);
        if ($exists === null) {
            db_insert('permissions', ['name' => $meta['name'], 'slug' => $slug, 'module' => $meta['module']]);
            $added++;
        }
    }
    return $added;
}

/** Grant a set of group-permissions to a role by slug (used for role defaults). */
function rbac_grant_groups(int $roleId, array $groups): void
{
    foreach ($groups as $group) {
        $perm = rbac_group_perm($group);
        if ($perm === null) {
            continue;
        }
        $pid = db_value('SELECT id FROM permissions WHERE slug = :s LIMIT 1', [':s' => $perm]);
        if ($pid !== null) {
            $has = db_value('SELECT 1 FROM role_permissions WHERE role_id = :r AND permission_id = :p', [':r' => $roleId, ':p' => (int) $pid]);
            if ($has === null) {
                db_insert('role_permissions', ['role_id' => $roleId, 'permission_id' => (int) $pid]);
            }
        }
    }
}

/**
 * Seed the 8 standard roles (super-admin already exists). Only creates roles
 * that are missing and applies sensible default module grants to new ones —
 * never touches an existing role's configuration.
 */
function rbac_seed_roles(): int
{
    rbac_sync_permissions();

    $contentAll = ['Website Content', 'Blog', 'Media', 'Document Hub'];
    $everythingButSystem = array_values(array_filter(array_keys(admin_nav_groups()), static fn ($g) => !in_array($g, ['Main', 'System'], true)));

    $roles = [
        ['name' => 'Staff / Admin', 'slug' => 'staff',     'description' => 'Configurable administrator — access limited to assigned modules.', 'grants' => $everythingButSystem],
        ['name' => 'School',        'slug' => 'school',     'description' => 'School-scoped: own students, batches, fees and staff.',           'grants' => ['School Management']],
        ['name' => 'Teacher',       'slug' => 'teacher',    'description' => 'Course-scoped: course content, grades, attendance, assignments.',  'grants' => ['Learning (LMS)', 'Student Management']],
        ['name' => 'Student',       'slug' => 'student',    'description' => 'Self-scoped portal role (own courses, results, certificates).',     'grants' => []],
        ['name' => 'Volunteer',     'slug' => 'volunteer',  'description' => 'Limited portal role (tasks, schedule, hours).',                     'grants' => []],
        ['name' => 'Member',        'slug' => 'member',      'description' => 'Member-scoped portal role (card, events, downloads, profile).',    'grants' => []],
        ['name' => 'Donor',         'slug' => 'donor',      'description' => 'Donor-scoped portal role (donations, receipts, tax certificates).', 'grants' => []],
    ];

    $created = 0;
    foreach ($roles as $r) {
        $existing = db_value('SELECT id FROM roles WHERE slug = :s LIMIT 1', [':s' => $r['slug']]);
        if ($existing !== null) {
            continue; // never modify an existing role
        }
        $rid = db_insert('roles', ['name' => $r['name'], 'slug' => $r['slug'], 'description' => $r['description'], 'is_system' => 0]);
        rbac_grant_groups($rid, $r['grants']);
        $created++;
    }
    return $created;
}
