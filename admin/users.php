<?php
/**
 * =============================================================================
 *  Admin — Admin Users CRUD.
 *  List + create + edit + delete for the `users` table (admin/staff accounts).
 *  Follows the standard admin module pattern: CSRF, validation, unique email,
 *  avatar upload (with old-file cleanup), password hashing (kept on blank),
 *  activity logging, pagination + search. Soft-deletes via `deleted_at`.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'users';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$STATUSES = ['active', 'inactive', 'suspended'];

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $back   = '/admin/users?action=' . ($editId ? 'edit&id=' . $editId : 'create');

    // Base validation.
    $rules = [
        'name'  => 'required|max:128',
        'email' => 'required|email|max:191',
    ];
    if (!$editId) {
        $rules['password'] = 'required'; // length/complexity enforced by the password policy below
    }
    $errors = validate($_POST, $rules);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect($back);
    }

    $email = strtolower(clean(post('email')));

    // Email must be unique across all accounts (the column carries a UNIQUE key).
    $dupeSql    = "SELECT id FROM $table WHERE email = :e" . ($editId ? " AND id <> :id" : '');
    $dupeParams = [':e' => $email];
    if ($editId) {
        $dupeParams[':id'] = $editId;
    }
    if (db_value($dupeSql, $dupeParams) !== null) {
        set_flash('error', 'That email address is already in use.');
        redirect($back);
    }

    // Optional password (only hashed + stored when provided). Validated against
    // the Security Center password policy (falls back to a length check).
    $password = (string) post('password', '');
    if ($password !== '') {
        if (function_exists('sec_password_validate')) {
            $pv = sec_password_validate($password);
            if (!$pv['ok']) {
                set_flash('error', implode(' ', $pv['errors']));
                redirect($back);
            }
        } elseif (mb_strlen($password) < 8) {
            set_flash('error', 'Password must be at least 8 characters.');
            redirect($back);
        }
    }

    $roleId = (int) post('role_id', 0);

    $data = [
        'name'    => clean(post('name')),
        'email'   => $email,
        'phone'   => clean(post('phone', '')),
        'role_id' => $roleId > 0 ? $roleId : null,
        'status'  => in_array(post('status'), $STATUSES, true) ? post('status') : 'active',
    ];

    if ($password !== '') {
        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    // Optional avatar upload (replaces + deletes the old one).
    if (!empty($_FILES['avatar']['name'])) {
        $up = upload_image($_FILES['avatar'], 'media');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect($back);
        }
        $data['avatar'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['avatar'])) delete_upload($old['avatar']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        if ($password !== '' && function_exists('sec_password_record')) {
            sec_password_record($editId, $data['password']);
        }
        log_activity('update', 'users', 'Updated user #' . $editId);
        set_flash('success', 'User updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        if ($password !== '' && function_exists('sec_password_record')) {
            sec_password_record($newId, $data['password']);
        }
        log_activity('create', 'users', 'Created user #' . $newId);
        set_flash('success', 'User created successfully.');
    }
    redirect('/admin/users');
}

/* -------------------------------------------------------------- DELETE (soft) */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);

    // Never let an admin delete their own account (avoids self-lockout).
    if ($delId === (int) current_user_id()) {
        set_flash('error', 'You cannot delete your own account.');
        redirect('/admin/users');
    }

    $row = db_row("SELECT * FROM $table WHERE id = :id AND deleted_at IS NULL", [':id' => $delId]);
    if ($row) {
        db_update($table, ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $delId]);
        log_activity('delete', 'users', 'Deleted user #' . $delId);
        set_flash('success', 'User deleted.');
    }
    redirect('/admin/users');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit'
        ? db_row("SELECT * FROM $table WHERE id = :id AND deleted_at IS NULL", [':id' => $id])
        : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'User not found.');
        redirect('/admin/users');
    }
    $roles      = get_all('roles', [], 'name ASC');
    $page_title = $action === 'edit' ? 'Edit User' : 'Add User';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Admin Users / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('users')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('users')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" required maxlength="128" value="<?= e($row['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="req">*</span></label>
                    <input class="form-control" type="email" name="email" required maxlength="191" value="<?= e($row['email'] ?? '') ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="phone" maxlength="32" value="<?= e($row['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role_id">
                        <option value="0">— None —</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>" <?= ((int) ($row['role_id'] ?? 0) === (int) $role['id']) ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Password <?= $action === 'edit' ? '' : '<span class="req">*</span>' ?></label>
                    <input class="form-control" type="password" name="password" autocomplete="new-password" <?= $action === 'edit' ? '' : 'required' ?> placeholder="<?= $action === 'edit' ? 'Leave blank to keep current' : 'At least 6 characters' ?>">
                    <?php if ($action === 'edit'): ?><small class="text-muted">Leave blank to keep the current password.</small><?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach ($STATUSES as $st): ?>
                            <option value="<?= e($st) ?>" <?= (($row['status'] ?? 'active') === $st) ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Avatar</label>
                <input class="form-control" type="file" name="avatar" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(image_url($row['avatar'] ?? null, 'avatar')) ?>" alt="preview">
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> User</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('users')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Admin Users';
$search = trim((string) get('q', ''));
$where  = 'u.deleted_at IS NULL';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', u.name, u.email, u.phone) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate(
    "SELECT u.*, r.name AS role_name
       FROM $table u
       LEFT JOIN roles r ON r.id = u.role_id
      WHERE $where
      ORDER BY u.id DESC",
    $params,
    12
);

$statusPill = [
    'active'    => 'pill-green',
    'inactive'  => 'pill-gray',
    'suspended' => 'pill-red',
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Admin Users</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('users?action=create')) ?>">+ Add User</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('users')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search users…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>User</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <img class="thumb" src="<?= e(image_url($r['avatar'] ?? null, 'avatar')) ?>" alt="">
                                <strong><?= e($r['name']) ?></strong>
                            </div>
                        </td>
                        <td><?= e($r['email']) ?></td>
                        <td><?= e($r['phone'] ?: '—') ?></td>
                        <td><?= e($r['role_name'] ?? '') ?: '—' ?></td>
                        <td><span class="pill <?= e($statusPill[$r['status']] ?? 'pill-gray') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('users?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <?php if ((int) $r['id'] !== (int) current_user_id()): ?>
                                <form method="post" action="<?= e(admin_url('users')) ?>" data-confirm="Delete this user permanently?" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $p['links'] ?>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('user') ?></div>No users found. <a href="<?= e(admin_url('users?action=create')) ?>">Add your first user</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
