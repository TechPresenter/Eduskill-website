<?php
/**
 * Authentication + authorization (RBAC) as plain functions over the users / roles / permissions
 * tables. Sessions store only the user id; the row, roles, and permissions load on demand.
 *
 * Lockout is keyed on (identifier, ip) with a per-ip cap, so an attacker can't lock a victim out by
 * spamming their email, nor spray many accounts from one host. Passwords are bcrypt (universally
 * available; argon2 isn't guaranteed on shared hosts).
 */

declare(strict_types=1);

defined('ESK') || exit('No direct access.');

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* ---------------------------------------------------------------- state */

function is_logged_in(): bool
{
    return !empty($_SESSION['uid']);
}

function current_user(): ?array
{
    static $user = null;
    if ($user !== null) {
        return $user ?: null;
    }
    if (empty($_SESSION['uid'])) {
        return $user = null;
    }
    $row = db_one('SELECT * FROM users WHERE id = ? LIMIT 1', [(int) $_SESSION['uid']]);
    if ($row === null || $row['status'] !== 'active') {
        logout_user();
        return $user = null;
    }
    return $user = $row;
}

/** @return array<int,string> */
function current_roles(): array
{
    static $roles = null;
    if ($roles !== null) {
        return $roles;
    }
    if (empty($_SESSION['uid'])) {
        return $roles = [];
    }
    $rows = db_all(
        'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        [(int) $_SESSION['uid']]
    );
    return $roles = array_column($rows, 'name');
}

function has_role(string $role): bool
{
    return in_array($role, current_roles(), true);
}

/** @return array<int,string> */
function current_permissions(): array
{
    static $perms = null;
    if ($perms !== null) {
        return $perms;
    }
    if (empty($_SESSION['uid'])) {
        return $perms = [];
    }
    $rows = db_all(
        'SELECT DISTINCT p.name FROM permissions p
         JOIN role_permissions rp ON rp.permission_id = p.id
         JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?',
        [(int) $_SESSION['uid']]
    );
    return $perms = array_column($rows, 'name');
}

/** Authorization gate. super_admin implicitly holds everything. */
function user_can(string $permission): bool
{
    if (has_role('super_admin')) {
        return true;
    }
    return in_array($permission, current_permissions(), true);
}

/** Guard an admin page: redirect to login if not authenticated, 403 if lacking a permission. */
function require_admin(?string $permission = null): void
{
    if (!is_logged_in()) {
        flash('intended', $_SERVER['REQUEST_URI'] ?? '');
        redirect('admin/login.php');
    }
    // Admin area is for staff/super_admin (and any role holding admin permissions).
    if ($permission !== null && !user_can($permission)) {
        http_response_code(403);
        require INCLUDES_PATH . '/../admin/includes/403.php';
        exit;
    }
}

/* ---------------------------------------------------------------- login flow */

/** Verify credentials without logging in. Returns the user row or null (generic — no enumeration). */
function attempt_login(string $login, string $password): ?array
{
    $user = db_one('SELECT * FROM users WHERE (email = ? OR mobile = ?) LIMIT 1', [$login, $login]);
    if ($user === null) {
        password_verify($password, '$2y$12$0000000000000000000000000000000000000000000000000000o');
        return null;
    }
    if (!password_verify($password, (string) $user['password']) || $user['status'] !== 'active') {
        return null;
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['_csrf'] = bin2hex(random_bytes(32)); // rotate CSRF on privilege change
    $_SESSION['_born'] = time();
    db_exec('UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [client_ip(), (int) $user['id']]);
    activity_log('auth.login', (int) $user['id']);
}

function logout_user(): void
{
    if (!empty($_SESSION['uid'])) {
        activity_log('auth.logout', (int) $_SESSION['uid']);
    }
    $_SESSION = [];
    session_regenerate_id(true);
}

/* ---------------------------------------------------------------- lockout */

function login_locked(string $identifier, string $ip): bool
{
    $byPair = (int) db_val(
        "SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND ip = ? AND successful = 0
         AND attempted_at > (NOW() - INTERVAL 15 MINUTE)",
        [$identifier, $ip]
    );
    if ($byPair >= 5) {
        return true;
    }
    $byIp = (int) db_val(
        "SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND successful = 0
         AND attempted_at > (NOW() - INTERVAL 15 MINUTE)",
        [$ip]
    );
    return $byIp >= 20;
}

function record_login_attempt(string $identifier, string $ip, bool $success): void
{
    db_insert(
        'INSERT INTO login_attempts (identifier, ip, successful, user_agent) VALUES (?, ?, ?, ?)',
        [$identifier, $ip, $success ? 1 : 0, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
    );
    if ($success) {
        db_exec('DELETE FROM login_attempts WHERE identifier = ? AND ip = ? AND successful = 0', [$identifier, $ip]);
    }
}

/* ---------------------------------------------------------------- activity log */

function activity_log(string $action, ?int $userId = null): void
{
    try {
        db_insert(
            'INSERT INTO user_activity_logs (user_id, action, ip, user_agent) VALUES (?, ?, ?, ?)',
            [$userId, substr($action, 0, 100), client_ip(), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
        );
    } catch (Throwable $e) {
        error_log('[activity_log] ' . $e->getMessage());
    }
}
