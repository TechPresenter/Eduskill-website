<?php
/**
 * =============================================================================
 *  Authentication & session
 * =============================================================================
 *  - Hardened session cookie parameters
 *  - login() / logout() / is_logged_in() / current_user()
 *  - require_admin() guard for the admin panel
 *  - Login-attempt throttling backed by the login_attempts table
 *  - Simple role-permission check (user_can)
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Start a hardened session. Safe to call multiple times.
 */
function pwf_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,       // only sent over HTTPS when available
        'httponly' => true,          // not readable by JavaScript
        'samesite' => 'Lax',         // CSRF mitigation
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    // Rotate the id periodically to limit fixation risk.
    if (empty($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}

/* -----------------------------------------------------------------------------
 | Account-existence timing
 | -------------------------------------------------------------------------- */

/**
 * A real bcrypt hash of a string nobody can supply, at the same cost (10) as
 * every hash stored in `users` and `members`. Verifying against it is the only
 * portable way to spend the same CPU on a miss as on a hit.
 */
const PWF_TIMING_HASH = '$2y$10$sNXqcPZ9KxHySaCF78MuxOtT.oe4hNe4uENuoge6Pz/N.Z//vKvKm';

/**
 * Spend one password_verify() when no account row was found.
 *
 * Both login handlers short-circuited on `!$user || !password_verify(...)`, so
 * PHP's own || skipped the bcrypt work whenever the address did not exist. The
 * WORDING was already identical ("Invalid email or password.") and the status
 * was already 200, but the CLOCK was a clean oracle: measured over HTTP,
 * an existing address took 177-244 ms and an absent one 39-115 ms; called
 * directly, 63-96 ms against 2.3-2.8 ms. Zero overlap in either sample, and the
 * whole gap is one bcrypt (58-64 ms).
 *
 * Always returns false, so it drops straight into the existing miss branch.
 */
function pwf_equalise_login_time(string $password): bool
{
    password_verify($password, PWF_TIMING_HASH);
    return false;
}

/* -----------------------------------------------------------------------------
 | Login-attempt throttling
 | -------------------------------------------------------------------------- */

/**
 * How many failed attempts for this email/IP within the lockout window.
 */
/** The address used for lockout decisions — the hardened, non-spoofable IP. */
function login_throttle_ip(): string
{
    // sec_client_ip() only trusts proxy headers when trust_proxy=1, so a remote
    // attacker cannot forge X-Forwarded-For to look like loopback and dodge the
    // lockout. Falls back to client_ip() only if the engine isn't loaded.
    return function_exists('sec_client_ip') ? sec_client_ip() : client_ip();
}

/**
 * How many failures an account may collect, as a multiple of the IP allowance,
 * before the ACCOUNT itself is gated.
 *
 * The counter used to be a flat `email = :email OR ip_address = :ip`, which put
 * both scopes on the same 5-attempt budget and cost two things:
 *
 *   DoS — anyone, from anywhere, could lock a known address out of login for the
 *   whole window with 5 POSTs. All nine admin accounts are named after published
 *   role words (admin@, staff@, school@…), so the addresses are not a secret.
 *
 *   Enumeration — login_needs_captcha() consults this BEFORE any password check,
 *   so a single POST from a clean IP distinguished "address with recent failed
 *   logins" from "address with none": admin@ answered captcha_shown=YES while an
 *   unseen address answered no.
 *
 * At 5x, a targeted lockout costs 25 attempts instead of 5 and a stranger's
 * CAPTCHA needs 15 instead of 3 — by which point an attack really is in progress
 * on that account and gating it is the right answer. The per-IP budget is
 * untouched, so a single attacker is slowed exactly as before.
 */
const PWF_EMAIL_LOCK_FACTOR = 5;

/** Failures from this client IP inside the window. */
function failed_login_ip_count(): int
{
    $mins  = function_exists('sec_lockout_mins') ? sec_lockout_mins() : LOGIN_LOCKOUT_MINS;
    return (int) db_value(
        'SELECT COUNT(*) FROM login_attempts
         WHERE success = 0 AND created_at >= :since AND ip_address = :ip',
        [':since' => date('Y-m-d H:i:s', time() - $mins * 60), ':ip' => login_throttle_ip()]
    );
}

/** Failures against this account inside the window, from any IP. */
function failed_login_email_count(string $email): int
{
    $mins  = function_exists('sec_lockout_mins') ? sec_lockout_mins() : LOGIN_LOCKOUT_MINS;
    return (int) db_value(
        'SELECT COUNT(*) FROM login_attempts
         WHERE success = 0 AND created_at >= :since AND email = :email',
        [':since' => date('Y-m-d H:i:s', time() - $mins * 60), ':email' => $email]
    );
}

/**
 * The effective count every threshold is compared against: whichever scope is
 * further through its own budget. Callers keep comparing it to the same
 * sec_lockout_max() / sec_captcha_after() numbers as before.
 */
function failed_login_count(string $email): int
{
    return max(
        failed_login_ip_count(),
        intdiv(failed_login_email_count($email), PWF_EMAIL_LOCK_FACTOR)
    );
}

/** Is the account/IP currently locked out? Genuine loopback is never locked out. */
function is_locked_out(string $email): bool
{
    if (function_exists('sec_is_loopback') && sec_is_loopback(login_throttle_ip())) {
        return false;
    }
    $max = function_exists('sec_lockout_max') ? sec_lockout_max() : LOGIN_MAX_ATTEMPTS;
    return failed_login_count($email) >= $max;
}

/** Record a login attempt (success or failure). */
function record_login_attempt(string $email, bool $success): void
{
    db_insert('login_attempts', [
        'email'      => $email,
        'ip_address' => login_throttle_ip(),
        'success'    => $success ? 1 : 0,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

/* -----------------------------------------------------------------------------
 | Login / logout
 | -------------------------------------------------------------------------- */

/**
 * Attempt to authenticate. Returns ['success' => bool, 'message' => string].
 */
function login(string $email, string $password): array
{
    $email = strtolower(trim($email));

    if (is_locked_out($email)) {
        $mins = function_exists('sec_lockout_mins') ? sec_lockout_mins() : LOGIN_LOCKOUT_MINS;
        return [
            'success' => false,
            'message' => 'Too many failed attempts. Please try again in ' . $mins . ' minutes.',
        ];
    }

    $user = db_row(
        'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1',
        [':email' => $email]
    );

    // The no-row branch verifies against PWF_TIMING_HASH so a miss costs the same
    // bcrypt as a hit — see pwf_equalise_login_time().
    $ok = $user ? password_verify($password, $user['password'])
                : pwf_equalise_login_time($password);
    if (!$ok) {
        record_login_attempt($email, false);
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    if ($user['status'] !== 'active') {
        record_login_attempt($email, false);
        return ['success' => false, 'message' => 'Your account is not active. Contact an administrator.'];
    }

    // Success — rehash if the algorithm/cost changed.
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        db_update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)],
            'id = :id', [':id' => $user['id']]);
    }

    // Two-factor (TOTP) — password is correct, but require the code before session.
    // The attempt is NOT recorded as a success yet: authentication is incomplete
    // until the second factor is verified, and recording success here would also
    // hide the failed-code attempts that follow from the lockout counter.
    if (!empty($user['totp_enabled']) && (int) $user['totp_enabled'] === 1) {
        $_SESSION['_2fa_user']    = (int) $user['id'];
        $_SESSION['_2fa_started'] = time();
        return ['success' => false, 'twofa' => true,
                'message' => 'Enter the 6-digit code from your authenticator app.'];
    }

    complete_admin_login($user);
    record_login_attempt($email, true);
    log_activity('login', 'auth', 'User logged in');

    return ['success' => true, 'message' => 'Welcome back, ' . $user['name'] . '!'];
}

/**
 * Establish the authenticated admin session for a user row.
 */
function complete_admin_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'      => (int) $user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
        'role_id' => $user['role_id'] !== null ? (int) $user['role_id'] : null,
        'avatar'  => $user['avatar'],
    ];
    $_SESSION['_created'] = time();
    unset($_SESSION['_2fa_user'], $_SESSION['_2fa_started']);

    db_update('users',
        ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => client_ip()],
        'id = :id', [':id' => (int) $user['id']]
    );
}

/** Abandon a pending 2FA handshake; the user must re-enter their password. */
function abort_2fa_login(): void
{
    unset($_SESSION['_2fa_user'], $_SESSION['_2fa_started']);
}

/** Standard "you are locked out" result, worded like the password-stage one. */
function twofa_lockout_result(): array
{
    $mins = function_exists('sec_lockout_mins') ? sec_lockout_mins() : LOGIN_LOCKOUT_MINS;
    return [
        'success' => false,
        'message' => 'Too many failed attempts. Please try again in ' . $mins . ' minutes.',
    ];
}

/**
 * Complete a pending two-factor login with a TOTP code.
 *
 * A correct password must not buy an unlimited number of guesses at the second
 * factor: a six-digit TOTP is only ~10^6 wide and recovery codes are checked on
 * the same path, so every wrong code is recorded in login_attempts and the
 * shared lockout budget (sec_lockout_max / LOGIN_MAX_ATTEMPTS) applies here
 * exactly as it does at the password stage. Hitting the budget ends the
 * handshake outright rather than merely rejecting one guess.
 */
function finish_2fa_login(string $code): array
{
    $id = (int) ($_SESSION['_2fa_user'] ?? 0);

    /* Re-fetch with the SAME filters the password stage used.
       This was find('users', $id), and find() (functions.php:107) is a bare
       "SELECT * FROM users WHERE id = :id" with no deleted_at and no status
       filter — so an admin who was soft-deleted or suspended AFTER passing the
       password stage could still finish the handshake and receive a full session
       from complete_admin_login(). login() filters deleted_at in SQL
       (auth.php:194) and remember_attempt() checks both (auth_security.php:121);
       this was the one path that checked neither. The window is bounded by
       TWOFA_HANDSHAKE_SECS below, but "bounded" is not "closed", and revoking an
       admin's access is exactly the moment this matters.

       The refusal is deliberately worded as an expired session: an account that
       has just been disabled must not learn which of the two it was. */
    $user = $id ? db_row(
        'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL AND status = :st LIMIT 1',
        [':id' => $id, ':st' => 'active']
    ) : null;

    if (!$user) {
        abort_2fa_login();
        return ['success' => false, 'message' => 'Your session expired. Please sign in again.'];
    }

    // The handshake is short-lived — an abandoned one must not stay attackable.
    $started = (int) ($_SESSION['_2fa_started'] ?? 0);
    if ($started > 0 && (time() - $started) > TWOFA_HANDSHAKE_SECS) {
        abort_2fa_login();
        return ['success' => false, 'message' => 'Your session expired. Please sign in again.'];
    }

    $email = strtolower((string) $user['email']);
    if (is_locked_out($email)) {
        abort_2fa_login();
        return twofa_lockout_result();
    }

    // Accept a valid TOTP code OR a one-time recovery/backup code.
    $code = trim($code);
    $ok = totp_verify((string) $user['totp_secret'], $code)
        || (function_exists('sec_recovery_verify') && sec_recovery_verify((int) $user['id'], $code));

    if (!$ok) {
        record_login_attempt($email, false);
        if (function_exists('sec_log')) {
            sec_log('2fa_failed', 'Invalid 2FA code for user #' . (int) $user['id'], 'warning');
        }
        // Re-check so the guess that exhausts the budget also ends the handshake.
        if (is_locked_out($email)) {
            abort_2fa_login();
            return twofa_lockout_result();
        }
        return ['success' => false, 'twofa' => true, 'message' => 'Invalid code. Please try again.'];
    }

    complete_admin_login($user);
    record_login_attempt($email, true);
    log_activity('login', 'auth', 'User logged in (2FA)');
    return ['success' => true, 'message' => 'Welcome back, ' . $user['name'] . '!'];
}

/** Destroy the session and log out. */
function logout(): void
{
    if (is_logged_in()) {
        log_activity('logout', 'auth', 'User logged out');
        if (function_exists('remember_forget')) {
            remember_forget('users', 'pwf_remember', current_user_id());
        }
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* -----------------------------------------------------------------------------
 | Access helpers
 | -------------------------------------------------------------------------- */

function is_logged_in(): bool
{
    return !empty($_SESSION['user']['id']);
}

/** The logged-in user array, or null. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
}

/**
 * Guard an admin page. Redirects to login when not authenticated.
 */
function require_admin(): void
{
    // Optional IP whitelist for the whole admin area. sec_ip_allowed() adds a
    // loopback bypass + CIDR support so the dev box can never self-lock; it
    // falls back to admin_ip_allowed() if the security engine isn't loaded.
    $ipOk = function_exists('sec_ip_allowed')
        ? sec_ip_allowed()
        : (function_exists('admin_ip_allowed') ? admin_ip_allowed() : true);
    if (!$ipOk) {
        http_response_code(403);
        exit('Access to the admin panel is restricted from your network.');
    }
    if (!is_logged_in()) {
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? null;
        set_flash('error', 'Please sign in to continue.');
        redirect('/admin/login');
    }
    // Default-OFF enforcement (must-change / expiry / force-2FA). Only redirects.
    if (function_exists('sec_enforce_admin')) {
        sec_enforce_admin();
    }
    // Default-OFF role gate: restricts non-super-admins to their assigned modules.
    if (function_exists('rbac_gate')) {
        rbac_gate();
    }
}

/**
 * Does the current user's role have a given permission slug?
 * Users with no role_id, or the built-in super-admin role, get everything.
 */
function user_can(string $permissionSlug): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if (empty($user['role_id'])) {
        return true; // no role assigned == unrestricted super admin
    }
    /* One definition of "super admin", shared with includes/auth_router.php and
       rbac.php:rbac_is_super() — the list used to be inlined here and there, in
       two places that could drift apart. auth_is_super_role() carries the same
       set, so behaviour is unchanged; the point is that there is now one place to
       change it. The function_exists() guard keeps auth.php usable on its own
       (bootstrap loads auth_router.php later, at line 44). */
    $role = find('roles', (int) $user['role_id']);
    $slug = (string) ($role['slug'] ?? '');
    if ($role && (function_exists('auth_is_super_role')
            ? auth_is_super_role($slug)
            : in_array($slug, ['super-admin', 'administrator'], true))) {
        return true;
    }
    return (bool) db_value(
        'SELECT COUNT(*) FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = :rid AND p.slug = :slug',
        [':rid' => (int) $user['role_id'], ':slug' => $permissionSlug]
    );
}

/** Redirect a logged-in user away from guest-only pages (e.g. login). */
function redirect_if_authenticated(string $to = '/admin/dashboard'): void
{
    if (is_logged_in()) {
        redirect($to);
    }
}
