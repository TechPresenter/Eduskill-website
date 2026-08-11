<?php
/**
 * =============================================================================
 *  Role-aware authentication router
 * =============================================================================
 *  ONE place decides, for any authenticated identity:
 *
 *      what role am I?  ->  is my account usable?  ->  where do I land?
 *
 *  The platform has two identity tables and that is deliberate:
 *
 *    users    staff identities. Carries role_id -> roles. Signs in at
 *             /admin/login.
 *    members  public identities. Carries member_code, plan_id, expiry_date,
 *             qr_token and is referenced by the membership, card, renewal and
 *             payment code. Signs in at /login. Role held in members.role,
 *             written by the unified registration in includes/member_auth.php.
 *
 *  Folding members into users would mean migrating all of that membership
 *  machinery for no functional gain, so instead this router NORMALISES both
 *  tables onto one role vocabulary and one redirect map. Callers never care
 *  which table an identity came from.
 *
 *  SECURITY: the role is read from the authenticated row, never from the
 *  request. /login/member is only a contextual skin — signing in there as a
 *  student still lands on the student destination.
 *
 *  -----------------------------------------------------------------------------
 *  WHAT CHANGED AND WHY (v30 — unified registration)
 *  -----------------------------------------------------------------------------
 *  1. 'realm' was a single value and it was WRONG for half the table. The live
 *     `users` table holds rows for all nine role slugs (student@, volunteer@,
 *     member@, donor@ included), so a role is not owned by one table. Each role
 *     now declares 'realms' (every table that may hold it) plus 'realm' (the
 *     primary one — the table that self-registration writes to).
 *
 *  2. 'public' became 'self_register', and it is now the ONE server-side
 *     allowlist the registration form and member_register() both read. Anything
 *     false here can never be self-registered, whatever the request says.
 *
 *  3. auth_dashboard_for() used to hand back '/member/dashboard' & friends for
 *     eight of nine roles. NONE of those files exist, and .htaccess + the
 *     clean-URL guard turn a missing {role}/dashboard.php into a hard 404 — so
 *     login.php:30 was 404-ing every signed-in member in production. The
 *     resolver now checks the route against the filesystem and falls back to a
 *     surface that EXISTS. The intended targets are still declared, so the gap
 *     is visible in code (see auth_dashboard_gaps()).
 *
 *  4. Dead branches removed from auth_status_gate(): a 'blocked' status (in
 *     neither enum) and membership_status === 'pending' (the enum is
 *     none|active|expired|cancelled). Replaced with the two states that DO
 *     exist and DO matter: members.status = 'pending' (awaiting approval) and
 *     users.deleted_at (soft-deleted).
 *
 *  5. The super-admin convention is defined ONCE, in auth_is_super_role(), and
 *     auth.php:user_can() calls it. rbac.php:rbac_is_super() still inlines the
 *     same literal list — see the reported edit; it is byte-for-byte the same
 *     set, so the two agree today.
 * =============================================================================
 */

declare(strict_types=1);

/* -----------------------------------------------------------------------------
 | 1. The role vocabulary
 | -------------------------------------------------------------------------- */

/**
 * Every role, with where it lives, where it lands, and how the contextual
 * login page introduces itself.
 *
 * 'realm'         primary table — the one self-registration writes to
 * 'realms'        every table that may legitimately hold this role
 * 'dashboard'     the INTENDED destination (may not exist yet — see below)
 * 'self_register' may a visitor create this role from the public signup form?
 * 'approval'      'auto'  — may be auto-approved when the policy allows it
 *                 'admin' — ALWAYS requires a human, whatever the policy says
 */
function auth_roles(): array
{
    return [
        'super-admin' => [
            'realm' => 'users', 'realms' => ['users'],
            'dashboard' => '/admin/dashboard', 'self_register' => false, 'approval' => 'admin',
            'label' => 'Admin Portal',     'icon' => 'shield',
            'title' => 'Admin Portal',     'blurb' => 'Manage the EDUSKILL INDIA FOUNDATION platform.',
        ],
        'staff' => [
            'realm' => 'users', 'realms' => ['users'],
            'dashboard' => '/staff/dashboard', 'self_register' => false, 'approval' => 'admin',
            'label' => 'Staff Portal',     'icon' => 'briefcase',
            'title' => 'Welcome back',     'blurb' => 'Access your assigned administrative workspace.',
        ],
        'editor' => [
            'realm' => 'users', 'realms' => ['users'],
            'dashboard' => '/staff/dashboard', 'self_register' => false, 'approval' => 'admin',
            'label' => 'Editor Portal',    'icon' => 'pen-line',
            'title' => 'Welcome back',     'blurb' => 'Manage content, stories and media.',
        ],
        /* Teacher is staff-created: a teacher account is attached to a school's
           roster, so there is nothing for a stranger to self-register into. */
        'teacher' => [
            'realm' => 'users', 'realms' => ['users'],
            'dashboard' => '/teacher/dashboard', 'self_register' => false, 'approval' => 'admin',
            'label' => 'Teacher Portal',   'icon' => 'graduation-cap',
            'title' => 'Welcome back, Teacher', 'blurb' => 'Manage your classes, students and learning activities.',
        ],
        /* SCHOOL — self-registerable, but 'approval' => 'admin' is HARDCODED and
           is deliberately not a setting. A school account governs other
           people's data (student rosters, fees, attendance, results), so
           possession of an institutional-looking email address must never be
           enough to open it. registration_auto_approve does not reach this row.

           Note the realm: public self-registration writes a `members` row with
           role='school', NEVER a `users` row. Nothing in this codebase can
           create an admin-realm identity from a public form, and adding that
           capability is not something a signup page should own. An approved
           school is provisioned into `users` by an admin. */
        'school' => [
            'realm' => 'members', 'realms' => ['users', 'members'],
            'dashboard' => '/school/dashboard', 'self_register' => true, 'approval' => 'admin',
            'label' => 'School Portal',    'icon' => 'school',
            'title' => 'Welcome back, School', 'blurb' => 'Manage your students, teachers and programmes.',
        ],
        'student' => [
            'realm' => 'members', 'realms' => ['users', 'members'],
            'dashboard' => '/student/dashboard', 'self_register' => true, 'approval' => 'auto',
            'label' => 'Student Portal',   'icon' => 'book-open',
            'title' => 'Welcome back, Student', 'blurb' => 'Continue your learning journey.',
        ],
        'volunteer' => [
            'realm' => 'members', 'realms' => ['users', 'members'],
            'dashboard' => '/volunteer/dashboard', 'self_register' => true, 'approval' => 'auto',
            'label' => 'Volunteer Portal', 'icon' => 'hand-heart',
            'title' => 'Welcome back, Volunteer', 'blurb' => 'Continue making an impact with EduSkill India.',
        ],
        'member' => [
            'realm' => 'members', 'realms' => ['users', 'members'],
            'dashboard' => '/member/dashboard', 'self_register' => true, 'approval' => 'auto',
            'label' => 'Member Portal',    'icon' => 'id-card',
            'title' => 'Welcome back, Member', 'blurb' => 'Manage your membership, digital ID card, benefits and events.',
        ],
        'donor' => [
            'realm' => 'members', 'realms' => ['users', 'members'],
            'dashboard' => '/donor/dashboard', 'self_register' => true, 'approval' => 'auto',
            'label' => 'Donor Portal',     'icon' => 'heart',
            'title' => 'Welcome back, Donor', 'blurb' => 'Manage your donations, receipts and supported campaigns.',
        ],
    ];
}

/** Is this a role slug we know? */
function auth_role_exists(string $role): bool
{
    return isset(auth_roles()[strtolower(trim($role))]);
}

/**
 * THE allowlist. Every role a visitor may create for themselves, in the order
 * the signup form should offer them. Nothing else may ever be self-registered —
 * super-admin, staff, editor and teacher are absent by construction, not by a
 * filter somebody could forget to apply.
 */
function auth_self_registerable_roles(): array
{
    $out = [];
    foreach (auth_roles() as $slug => $cfg) {
        if (!empty($cfg['self_register'])) {
            $out[] = $slug;
        }
    }
    return $out;
}

/** May a visitor self-register into this role? Case/space tolerant, never throws. */
function auth_role_is_self_registerable(?string $role): bool
{
    $slug = strtolower(trim((string) $role));
    return $slug !== '' && in_array($slug, auth_self_registerable_roles(), true);
}

/**
 * Does this role ALWAYS need a human to approve the account?
 *
 * True for every staff role (they are admin-created anyway) and for 'school',
 * which is hardcoded here so no settings change can auto-approve it. Unknown
 * slugs answer true: fail closed.
 */
function auth_role_requires_admin_approval(?string $role): bool
{
    $cfg = auth_roles()[strtolower(trim((string) $role))] ?? null;
    return $cfg === null || ($cfg['approval'] ?? 'admin') !== 'auto';
}

/**
 * The one definition of "super admin", shared with auth.php:user_can() and
 * (once the reported edit lands) rbac.php:rbac_is_super().
 *
 * 'administrator' is kept because both existing call sites list it and dropping
 * it here while they still carry it would create the very divergence this
 * function exists to remove. There is no 'administrator' row in `roles`, so the
 * literal is inert — it is compatibility ballast, not a second convention.
 *
 * A NULL/empty slug is NOT super admin. The "no role_id at all == unrestricted
 * super admin" convention lives at the call sites that can actually see
 * role_id (auth.php:408, rbac.php:311, auth_current_role() below); conflating
 * the two here would turn "role row is missing/corrupt" into "full access".
 */
function auth_is_super_role(?string $slug): bool
{
    $slug = strtolower(trim((string) $slug));
    return $slug !== '' && in_array($slug, ['super-admin', 'administrator'], true);
}

/** Contextual copy for /login/{role}; falls back to the neutral member portal. */
function auth_role_context(?string $role): array
{
    $roles = auth_roles();
    $key   = strtolower(trim((string) $role));
    if ($key !== '' && isset($roles[$key])) {
        return $roles[$key] + ['slug' => $key];
    }
    return [
        'slug'  => '', 'realm' => '', 'realms' => [], 'dashboard' => '',
        'self_register' => false, 'approval' => 'admin',
        'label' => 'Sign in', 'icon' => 'log-in',
        'title' => 'Welcome back',
        'blurb' => 'Sign in to continue to your EduSkill India portal.',
    ];
}

/* -----------------------------------------------------------------------------
 | 2. Resolving an authenticated identity's role
 | -------------------------------------------------------------------------- */

/** Which identity table is driving this request? '' when signed out. */
function auth_current_realm(): string
{
    if (function_exists('is_logged_in') && is_logged_in()) {
        return 'users';
    }
    if (function_exists('is_member_logged_in') && is_member_logged_in()) {
        return 'members';
    }
    return '';
}

/**
 * Routing label for a users-realm row whose role_id points at a slug this
 * router does not know — i.e. somebody added a `roles` row without adding it
 * here. It decides WHERE THEY LAND, nothing else: what they may DO is still
 * decided by role_permissions via rbac_gate() and user_can(), which never
 * consult this value. 'staff' is the users-realm label whose fallback
 * destination exists, so the person reaches a working page instead of a 404.
 */
function auth_unknown_users_role(): string
{
    return 'staff';
}

/**
 * The role of the currently authenticated identity, or '' when signed out.
 *
 * Staff take precedence: if somebody holds both a staff account and a member
 * account in the same browser, the staff session is the privileged one and
 * deciding anything else would be surprising.
 */
function auth_current_role(): string
{
    if (function_exists('is_logged_in') && is_logged_in()) {
        $user = current_user();
        $rid  = $user['role_id'] ?? null;
        if ($rid === null) {
            return 'super-admin';           // role-less legacy admin == super admin
        }
        try {
            $role = find('roles', (int) $rid);
        } catch (Throwable $e) {
            $role = null;
        }
        $slug = strtolower((string) ($role['slug'] ?? ''));
        if (auth_role_exists($slug)) {
            return $slug;
        }
        // Unknown slug is a data-integrity problem, not a routine event: say so
        // once rather than silently relabelling the identity.
        if (function_exists('sec_log')) {
            sec_log('auth_unknown_role',
                'users.role_id ' . (int) $rid . ' resolves to slug "' . $slug . '", which auth_roles() does not define',
                'warning');
        }
        return auth_unknown_users_role();
    }

    if (function_exists('is_member_logged_in') && is_member_logged_in()) {
        $m = current_member();
        // The session is thin (id/name/email/avatar only), so read the
        // authoritative role from the table on every call.
        try {
            $row = db_row('SELECT role, type FROM members WHERE id = :i LIMIT 1',
                [':i' => (int) ($m['id'] ?? 0)]);
        } catch (Throwable $e) {
            $row = null;
        }
        $slug = strtolower(trim((string) ($row['role'] ?? '')));
        if ($slug === '') {
            // Legacy rows predate members.role; members.type is the next best
            // signal and overlaps the vocabulary for member/donor/volunteer.
            $slug = strtolower(trim((string) ($row['type'] ?? '')));
        }
        // A members row can only hold a members-realm role. A row claiming
        // 'staff' (however it got there) must not be routed as staff.
        $cfg = auth_roles()[$slug] ?? null;
        if ($cfg !== null && in_array('members', $cfg['realms'], true)) {
            return $slug;
        }
        return 'member';
    }

    return '';
}

/* -----------------------------------------------------------------------------
 | 3. Destinations that actually exist
 | -------------------------------------------------------------------------- */

/**
 * Is this site-relative path served by a real file?
 *
 * .htaccess maps /foo -> foo.php only when foo.php exists; AcceptPathInfo is Off
 * and the clean-URL guard tests the whole path, so a route with no file behind
 * it is a hard 404 rather than a lenient match. That makes is_file() the honest
 * test for "will this redirect land somewhere".
 */
function auth_route_exists(string $path): bool
{
    $rel = trim((string) (parse_url($path, PHP_URL_PATH) ?: ''), '/');
    if ($rel === '' || !preg_match('#^[A-Za-z0-9/_-]+$#', $rel)) {
        return false;
    }

    /* is_file() alone answered TRUE for things the web server refuses to serve.
       .htaccess returns 403 for ^(includes|database|logs|docs)/ and for
       config.php, so '/includes/mailer' and '/config' both "existed" as far as
       this function was concerned. Nothing consumes that yet — but this function
       is what decides whether a saved deep link is honoured after login
       (auth_post_login_target), so a route that 403s must read as missing, or
       wiring that up would send people to a Forbidden page.

       Partials are the same class of thing without an .htaccess rule: they are
       fragments that assume a parent scope and render broken on their own. */
    $segments = explode('/', $rel);
    $blockedTop = ['includes', 'database', 'logs', 'docs', 'vendor', 'config', 'node_modules'];
    if (in_array(strtolower($segments[0]), $blockedTop, true)) {
        return false;
    }
    foreach ($segments as $seg) {
        if (strtolower($seg) === 'partials') {
            return false;
        }
    }

    return is_file(BASE_PATH . '/' . $rel . '.php')
        || is_file(BASE_PATH . '/' . $rel . '/index.php');
}

/**
 * The real surface a realm can always reach.
 *
 *   users   -> /admin/dashboard   (admin/dashboard.php)
 *   members -> /account           (account.php, require_member()-gated)
 *
 * Both are verified to exist by auth_dashboard_for() before use, so a future
 * rename cannot turn this into a 404 either.
 */
function auth_realm_home(string $realm): string
{
    return $realm === 'users' ? '/admin/dashboard' : '/account';
}

/**
 * Which realm should decide the destination for this role on this request?
 *
 * Prefer the realm the caller names, then the realm actually signed in (so a
 * users-realm 'school' account lands in the admin area while a self-registered
 * members-realm 'school' account lands on /account), then the role's primary.
 */
function auth_realm_for_role(string $role, ?string $realm = null): string
{
    $cfg    = auth_roles()[$role] ?? null;
    $realms = $cfg['realms'] ?? ['members'];

    foreach ([$realm, auth_current_realm()] as $candidate) {
        $candidate = strtolower(trim((string) $candidate));
        if ($candidate !== '' && in_array($candidate, $realms, true)) {
            return $candidate;
        }
    }
    return (string) ($cfg['realm'] ?? 'members');
}

/**
 * Where the identity belongs — GUARANTEED to be a route that resolves.
 *
 * The role's declared 'dashboard' is the intended home. Seven of the nine are
 * not built yet (see auth_dashboard_gaps()), so when the file is missing this
 * returns the realm's real home instead of a 404. $realm is optional and only
 * matters for roles that live in both tables.
 */
function auth_dashboard_for(string $role, ?string $realm = null): string
{
    $role = strtolower(trim($role));
    $cfg  = auth_roles()[$role] ?? null;
    if ($cfg === null) {
        return auth_realm_home(auth_current_realm() === 'users' ? 'users' : 'members');
    }

    $intended = (string) $cfg['dashboard'];
    if (auth_route_exists($intended)) {
        return $intended;
    }

    $home = auth_realm_home(auth_realm_for_role($role, $realm));
    // Belt and braces: if even the realm home were renamed away, land on '/'
    // rather than a broken redirect.
    return auth_route_exists($home) ? $home : '/';
}

/**
 * TODO MAP — every role whose real dashboard is still missing, with the file
 * that has to exist for auth_dashboard_for() to stop substituting.
 *
 * This is not decoration: it is the build queue, and it is computed from the
 * filesystem, so an entry disappears the moment the page is created. Rendered
 * in admin/registration-settings.php so the gap is visible to whoever is
 * deciding whether to switch the registration policy on.
 *
 *   [role => ['intended' => '/student/dashboard',
 *             'file'     => 'student/dashboard.php',
 *             'serving'  => '/account']]
 */
function auth_dashboard_gaps(): array
{
    $out = [];
    foreach (auth_roles() as $slug => $cfg) {
        $intended = (string) $cfg['dashboard'];
        if (auth_route_exists($intended)) {
            continue;
        }
        $out[$slug] = [
            'intended' => $intended,
            'file'     => trim($intended, '/') . '.php',
            'serving'  => auth_realm_home((string) $cfg['realm']),
        ];
    }
    return $out;
}

/**
 * The sign-in URL for a role — the form that can actually authenticate it.
 *
 * .htaccess maps /login/{role} to login.php, which is the MEMBERS realm
 * (member_login() reads the `members` table). So a role that only ever lives in
 * `users` must be sent to /login/admin instead: 'staff' and 'teacher' declare
 * realms => ['users'] only, and auth_current_role() actively refuses to route a
 * members row as either, so /login/staff and /login/teacher could never
 * authenticate anybody. They rendered "Welcome back, Teacher", took the real
 * teacher@ credentials, found no `members` row and answered "Invalid email or
 * password" — a portal unusable by the only identities that can hold the role.
 * ('editor' fell through to the bare /login, which is the same page and the same
 * problem.)
 *
 * DERIVED from the realms declaration rather than a second hand-maintained list,
 * so adding a role to auth_roles() cannot leave this behind:
 *   can live in `members`  -> the members portal skin for that role
 *   `users` only           -> /login/admin
 *
 * The path list still has to match .htaccess, which whitelists exactly
 * /login/(staff|school|teacher|student|volunteer|member|donor) — see the
 * reported edit removing staff|teacher from it, which is cosmetic once this
 * function stops pointing at them.
 */
function auth_login_url_for(string $role): string
{
    $role = strtolower(trim($role));
    $cfg  = auth_roles()[$role] ?? null;

    // Every users-realm-only role signs in at the admin door, super-admin included.
    if ($cfg !== null && !in_array('members', $cfg['realms'], true)) {
        return '/login/admin';
    }
    if ($role === 'super-admin') {
        return '/login/admin';
    }

    // Members-realm portals that .htaccess actually routes to login.php.
    $portals = ['school', 'student', 'volunteer', 'member', 'donor'];
    return in_array($role, $portals, true) ? '/login/' . $role : '/login';
}

/**
 * Which session key the login page for this role will read back as "the page you
 * were trying to reach".
 *
 * The two realms use different slots and always have: admin/login.php and
 * require_admin() use '_intended', login.php and require_member() use
 * '_member_intended'. require_role() wrote '_intended' for every role, so a
 * member's deep link was silently dropped by login.php — and worse, it was left
 * sitting in the slot the ADMIN login consumes, where the next admin to sign in
 * on that session would be redirected to it.
 */
function auth_intended_key(string $role): string
{
    return str_starts_with(auth_login_url_for($role), '/login/admin')
        ? '_intended'
        : '_member_intended';
}

/* -----------------------------------------------------------------------------
 | 4. Account status gate
 | -------------------------------------------------------------------------- */

/**
 * Decide whether an authenticated row may proceed. Called by member_login()
 * (includes/member_auth.php) so there is one description of "usable account"
 * for the members realm, and available to the admin realm on the same terms.
 *
 * Returns ['ok' => bool, 'code' => string, 'message' => string]. Messages are
 * deliberately plain: they tell the account holder what to do without
 * disclosing administrative detail.
 *
 * Only states that EXIST in the schema are tested:
 *   members.status  enum('active','pending','suspended')
 *   users.status    enum('active','inactive','suspended')
 *   users.deleted_at  timestamp NULL  (soft delete)
 * There is no 'blocked' anywhere in this database, and membership_status is
 * none|active|expired|cancelled — a billing fact, never an auth fact. Both were
 * tested here before and neither branch could ever run.
 */
function auth_status_gate(array $row, string $realm): array
{
    $status = strtolower((string) ($row['status'] ?? 'active'));

    if ($status === 'suspended') {
        return ['ok' => false, 'code' => 'suspended',
                'message' => 'Your account has been suspended. Please contact us if you think this is a mistake.'];
    }

    if ($realm === 'users') {
        // Soft-deleted admins must not authenticate. login() filters this in
        // SQL; the 2FA stage did not (fixed in auth.php), and any future caller
        // that fetches a row by id gets the check here for free.
        if (!empty($row['deleted_at'])) {
            return ['ok' => false, 'code' => 'deleted',
                    'message' => 'This account is no longer active. Please contact an administrator.'];
        }
        if ($status === 'inactive') {
            return ['ok' => false, 'code' => 'inactive',
                    'message' => 'Your account is not active. Please contact us to reactivate it.'];
        }
        return ['ok' => true, 'code' => 'active', 'message' => ''];
    }

    /* ---- members realm ---- */

    // Proof the person controls the address comes first: it is what stops a
    // registration in somebody else's name from becoming a usable account.
    if (empty($row['email_verified_at'])) {
        return ['ok' => false, 'code' => 'email_unverified',
                'message' => 'Please verify your email address before signing in.'];
    }

    // 'pending' is the approval queue. It is written by registration when
    // registration_auto_approve is off, and always for approval-restricted
    // roles (school). Verification promotes pending -> active on its own for
    // roles that do not need a human, so this only refuses accounts that are
    // genuinely still waiting on one.
    if ($status === 'pending') {
        return ['ok' => false, 'code' => 'awaiting_approval',
                'message' => 'Your account is awaiting approval. We will email you as soon as it is reviewed.'];
    }

    return ['ok' => true, 'code' => 'active', 'message' => ''];
}

/* -----------------------------------------------------------------------------
 | 5. Post-login destination
 | -------------------------------------------------------------------------- */

/**
 * Where to send a just-authenticated identity.
 *
 * $contextRole is whatever /login/{role} the person happened to use. It is
 * ONLY used to decide whether to show the "you're actually a Student" notice —
 * it can never change the destination. The destination always comes from the
 * authenticated role.
 *
 * An $intended path is honoured only when the resolved role is actually allowed
 * to be there AND the path resolves to a real file, so neither a saved deep
 * link nor a stale one can become a way into another role's area or a 404.
 */
function auth_post_login_target(string $role, ?string $contextRole = null, ?string $intended = null, ?string $realm = null): array
{
    $dashboard = auth_dashboard_for($role, $realm);

    $notice = '';
    $ctx = strtolower(trim((string) $contextRole));
    if ($ctx !== '' && auth_role_exists($ctx) && $ctx !== $role) {
        $label  = auth_roles()[$role]['label'] ?? 'your portal';
        $notice = 'You are signed in as ' . ucfirst(str_replace('-', ' ', $role))
                . '. Taking you to your ' . $label . '.';
    }

    $target = $dashboard;
    if ($intended !== null && $intended !== '' && $intended[0] === '/' && !str_starts_with($intended, '//')
        && auth_may_access($role, $intended) && auth_route_exists($intended)) {
        $target = $intended;
    }

    return ['target' => $target, 'notice' => $notice, 'dashboard' => $dashboard];
}

/**
 * Which roles own each /{role}/ namespace. DERIVED from auth_roles() so the two
 * cannot drift: a role owns the namespace its declared dashboard sits in.
 *
 * /admin/ is widened to every users-realm role on purpose. It is not a
 * per-role namespace — it is the admin realm, and who may actually open a page
 * inside it is decided by require_admin() plus rbac_gate() against
 * role_permissions. Listing only super-admin here (as this table used to) said
 * the opposite of what the application does and would have sent every staff
 * member away from the only dashboard that exists.
 */
function auth_namespace_owners(): array
{
    static $owners = null;
    if ($owners !== null) {
        return $owners;
    }
    $owners = [];
    foreach (auth_roles() as $slug => $cfg) {
        $path = '/' . trim((string) $cfg['dashboard'], '/');
        $seg  = explode('/', trim($path, '/'))[0] ?? '';
        if ($seg === '') {
            continue;
        }
        $owners['/' . $seg . '/'][] = $slug;
    }
    foreach (auth_roles() as $slug => $cfg) {
        if (in_array('users', $cfg['realms'], true) && !in_array($slug, $owners['/admin/'] ?? [], true)) {
            $owners['/admin/'][] = $slug;
        }
    }
    return $owners;
}

/**
 * May this role reach this path? Used both for honouring an intended URL and
 * for guarding role-owned pages.
 *
 * Anything outside the role namespaces is treated as public — /account and the
 * other require_member() pages gate themselves.
 */
function auth_may_access(string $role, string $path): bool
{
    $role = strtolower(trim($role));
    $path = '/' . ltrim((string) (parse_url($path, PHP_URL_PATH) ?: $path), '/');

    if (auth_is_super_role($role)) {
        return true;
    }

    foreach (auth_namespace_owners() as $prefix => $allowed) {
        if (str_starts_with(rtrim($path, '/') . '/', $prefix)) {
            return in_array($role, $allowed, true);
        }
    }
    return true;    // not a role namespace
}

/* -----------------------------------------------------------------------------
 | 6. Guard for role-owned pages
 | -------------------------------------------------------------------------- */

/**
 * Gate a role-owned page. Signed out -> the contextual login for that role.
 * Signed in as the wrong role -> their OWN destination, never a 403 dead end
 * and never a 404.
 *
 * Nothing calls this yet because no /{role}/dashboard.php exists — it is the
 * guard the pages in auth_dashboard_gaps() are expected to open with.
 */
function require_role(string $required): array
{
    $role = auth_current_role();

    if ($role === '') {
        // Write the slot the login page we are about to send them to actually
        // reads — see auth_intended_key(). This used to always be '_intended',
        // which login.php never looks at and admin/login.php does.
        $_SESSION[auth_intended_key($required)] = $_SERVER['REQUEST_URI'] ?? '';
        set_flash('error', 'Please sign in to continue.');
        redirect(auth_login_url_for($required));
    }
    if ($role !== $required && !auth_is_super_role($role)) {
        redirect(auth_dashboard_for($role));
    }
    return ['role' => $role, 'realm' => auth_current_realm()];
}
