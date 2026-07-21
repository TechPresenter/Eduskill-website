<?php
/**
 * REST: /api/users.php — user management (custom, since it handles password hashing + role assignment).
 *   GET (users.view) · POST create (users.create) · PUT ?id= (users.edit) · DELETE ?id= (users.delete)
 */
require __DIR__ . '/../includes/config.php';

$method = request_method();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($method === 'GET') {
    api_require('users.view');
    if ($id > 0) {
        $u = db_one('SELECT id, name, email, mobile, status FROM users WHERE id = ?', [$id]);
        if ($u === null) {
            json_error('Not found.', 404);
        }
        $u['role'] = db_val('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? LIMIT 1', [$id]);
        json_ok(['data' => $u]);
    }
    json_ok(['data' => db_all(
        "SELECT u.id, u.name, u.email, u.status,
                (SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=u.id LIMIT 1) AS role
         FROM users u ORDER BY u.id DESC"
    )]);
}

$in = api_body();
if (!verify_csrf($in['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    json_error('Invalid or expired security token.', 400);
}

if ($method === 'DELETE') {
    api_require('users.delete');
    if ($id <= 0) {
        json_error('Missing id.', 400);
    }
    if ($id === (int) ($_SESSION['uid'] ?? 0)) {
        json_error('You cannot delete your own account.', 422);
    }
    // Never remove the last super admin.
    $isSuper = (int) db_val("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.name='super_admin'", [$id]);
    if ($isSuper > 0 && (int) db_val("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE r.name='super_admin'") <= 1) {
        json_error('You cannot delete the last super admin.', 422);
    }
    db_exec('DELETE FROM users WHERE id = ?', [$id]);   // user_roles cascade via FK
    activity_log('user.deleted', (int) ($_SESSION['uid'] ?? 0));
    json_ok([], 'User deleted.');
}

// create / update
$name = trim((string) ($in['name'] ?? ''));
$email = trim((string) ($in['email'] ?? ''));
$password = (string) ($in['password'] ?? '');
$role = trim((string) ($in['role'] ?? ''));
$status = in_array($in['status'] ?? '', ['active', 'inactive', 'pending', 'suspended'], true) ? (string) $in['status'] : 'active';

if ($name === '') {
    json_error('Name is required.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('A valid email is required.', 422);
}
$roleId = $role !== '' ? (int) db_val('SELECT id FROM roles WHERE name = ?', [$role]) : 0;

if ($method === 'POST') {
    api_require('users.create');
    if (strlen($password) < 8) {
        json_error('Password must be at least 8 characters.', 422);
    }
    if ((int) db_val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
        json_error('That email is already registered.', 422);
    }
    $newId = db_insert(
        "INSERT INTO users (name, email, password, status, email_verified_at, created_by) VALUES (?, ?, ?, ?, NOW(), ?)",
        [$name, $email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $status, (int) ($_SESSION['uid'] ?? 0)]
    );
    if ($roleId > 0) {
        db_exec('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$newId, $roleId]);
    }
    activity_log('user.created', (int) ($_SESSION['uid'] ?? 0));
    json_ok(['id' => $newId], 'User created.');
}

if ($method === 'PUT' || $method === 'PATCH') {
    api_require('users.edit');
    if ($id <= 0) {
        json_error('Missing id.', 400);
    }
    if ((int) db_val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $id]) > 0) {
        json_error('That email belongs to another user.', 422);
    }
    db_exec('UPDATE users SET name = ?, email = ?, status = ? WHERE id = ?', [$name, $email, $status, $id]);
    if ($password !== '') {
        if (strlen($password) < 8) {
            json_error('Password must be at least 8 characters.', 422);
        }
        db_exec('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
    }
    // Reset to the single selected role.
    db_exec('DELETE FROM user_roles WHERE user_id = ?', [$id]);
    if ($roleId > 0) {
        db_exec('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$id, $roleId]);
    }
    activity_log('user.updated', (int) ($_SESSION['uid'] ?? 0));
    json_ok([], 'User updated.');
}

json_error('Method not allowed.', 405);
