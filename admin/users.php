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

/* =============================================================================
 |  VERTICAL AUTHORITY CEILING
 | =============================================================================
 |  This screen assigns users.role_id, which is the single most privileged write
 |  in the application. require_admin() alone is NOT authorisation for it:
 |
 |    - rbac_enforce USED to ship at '0', which made rbac_gate() a no-op: every
 |      active `users` row reached this page — editor, staff, and the
 |      public-realm rows that existed for all nine slugs. It now ships at '1'
 |      (and rbac_enforcement_on() defaults to 1 when the key is missing), so
 |      this page is gated to the System group. That is defence in depth, not a
 |      substitute for the write-path rules below: an operator can turn
 |      enforcement off again from Admin -> Roles, and the ceiling must hold
 |      when they do.
 |    - role_id = NULL is not "no role", it is UNRESTRICTED SUPER ADMIN
 |      (auth.php:user_can and rbac.php:rbac_is_super both return true for it).
 |
 |  With no ceiling, one request was a complete privilege escalation:
 |
 |      POST /admin/users   _do=save&id=<self>&role_id=1     -> I am super-admin
 |      POST /admin/users   _do=save&id=<self>&role_id=0     -> better than that
 |      POST /admin/users   _do=save&id=<super>&password=x   -> own the super admin
 |
 |  The last one is the worst and is not about role_id at all: setting another
 |  account's password is equivalent to holding its role. Grouping the select
 |  options (below) did nothing about any of this — an attacker POSTs the field
 |  directly and never renders the form.
 |
 |  So the rules, enforced on the WRITE PATH:
 |    1. Only a super admin may grant the super-admin role, or the role-less
 |       (= unrestricted) state.
 |    2. Only a super admin may edit or delete an account that is itself super
 |       admin / role-less.
 |    3. Nobody may change their OWN role_id unless they are already super admin
 |       (a super admin gains nothing by it; anyone else would be promoting
 |       themselves).
 |    4. role_id must name a real `roles` row.
 |
 |  Every refusal is REJECTED and logged, never silently coerced: a coercion
 |  turns an attack in progress into a successful-looking save.
 |
 |  A super admin is unrestricted here, exactly as before — this adds a ceiling,
 |  it does not add a new gate for the people who legitimately run the site.
 |========================================================================== */

$actorId    = (int) current_user_id();
$actorSuper = function_exists('rbac_is_super') ? rbac_is_super() : true;

/**
 * Is this `users` row a super admin (explicit super-admin role, or role-less)?
 * Uses the same convention as the two permission checks, so it cannot drift.
 */
$isSuperRow = static function (?array $row): bool {
    if (!$row) {
        return false;
    }
    if (empty($row['role_id'])) {
        return true;                       // role_id NULL/0 == unrestricted
    }
    if (function_exists('rbac_is_super')) {
        return rbac_is_super($row);
    }
    $r = find('roles', (int) $row['role_id']);
    return $r && function_exists('auth_is_super_role') && auth_is_super_role((string) $r['slug']);
};

/** Refuse, tell the actor why, and leave an audit trail. */
$refuse = static function (string $why, string $detail, string $back): void {
    if (function_exists('sec_log')) {
        sec_log('admin_role_escalation_blocked', $detail, 'critical');
    }
    log_activity('privilege_escalation_blocked', 'users', $detail);
    set_flash('error', $why);
    redirect($back);
};

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

    /* ---------------------------------------------------------------------
       AUTHORITY CEILING — see the block at the top of this file.
       Everything here runs BEFORE the first write.
       --------------------------------------------------------------------- */
    $target = $editId ? db_row("SELECT * FROM $table WHERE id = :id AND deleted_at IS NULL", [':id' => $editId]) : null;
    if ($editId && !$target) {
        set_flash('error', 'User not found.');
        redirect('/admin/users');
    }

    // Rule 2 — an account that is already super admin may only be touched by a
    // super admin. This covers the password field, not just the role: setting
    // somebody's password is equivalent to holding their privileges.
    if (!$actorSuper && $isSuperRow($target)) {
        $refuse('You do not have permission to edit a super-admin account.',
            'User #' . $actorId . ' attempted to edit super-admin account #' . $editId
            . ' (password change attempted: ' . (post('password', '') !== '' ? 'yes' : 'no') . ')',
            '/admin/users');
    }

    $roleId    = (int) post('role_id', 0);
    $roleIdNew = $roleId > 0 ? $roleId : null;

    // Rule 4 — role_id must name a real role. A dangling id is fail-closed for
    // permissions but routes as an unknown role and is impossible to audit.
    $roleRow = $roleIdNew !== null ? find('roles', $roleIdNew) : null;
    if ($roleIdNew !== null && !$roleRow) {
        $refuse('That role does not exist.',
            'User #' . $actorId . ' posted role_id ' . $roleIdNew . ', which is not a row in `roles`',
            $back);
    }

    $grantsSuper = $roleIdNew === null
        || (function_exists('auth_is_super_role') && auth_is_super_role((string) ($roleRow['slug'] ?? '')));

    // Rule 1 — only a super admin may grant super admin, in either form.
    if (!$actorSuper && $grantsSuper) {
        $refuse($roleIdNew === null
                ? 'Only a super admin can create an account with no role — that is unrestricted access, not restricted access.'
                : 'Only a super admin can grant the super-admin role.',
            'User #' . $actorId . ' attempted to grant '
            . ($roleIdNew === null ? 'the role-less (unrestricted super admin) state' : 'role "' . (string) ($roleRow['slug'] ?? '') . '"')
            . ' to ' . ($editId ? 'user #' . $editId : 'a new account'),
            $back);
    }

    // Rule 3 — no self-promotion. Comparing against the CURRENT value so that
    // saving your own profile without touching the dropdown still works.
    if (!$actorSuper && $editId === $actorId) {
        $roleIdOld = ($target['role_id'] ?? null) !== null ? (int) $target['role_id'] : null;
        if ($roleIdNew !== $roleIdOld) {
            $refuse('You cannot change your own role.',
                'User #' . $actorId . ' attempted to change their own role_id from '
                . var_export($roleIdOld, true) . ' to ' . var_export($roleIdNew, true),
                $back);
        }
    }

    $data = [
        'name'    => clean(post('name')),
        'email'   => $email,
        'phone'   => clean(post('phone', '')),
        'role_id' => $roleIdNew,
        'status'  => in_array(post('status'), $STATUSES, true) ? post('status') : 'active',
        /* The bio is the author byline shown under every blog post this person
           writes (blog-details.php:23 reads users.bio). Length-capped to match
           the textarea, and cleaned like every other free-text field — it is
           rendered escaped on the public page, never as HTML. */
        'bio'     => mb_substr(clean(post('bio', '')), 0, 600),
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
        /* The role transition is spelled out, and a password reset on somebody
           else's account is called what it is. "Updated user #11" told an
           incident review nothing about the one field that matters. */
        $roleIdOld = ($target['role_id'] ?? null) !== null ? (int) $target['role_id'] : null;
        $note = 'Updated user #' . $editId
              . ($roleIdNew !== $roleIdOld
                    ? ' role_id ' . var_export($roleIdOld, true) . ' -> ' . var_export($roleIdNew, true)
                      . ($roleRow ? ' (' . $roleRow['slug'] . ')' : ' (none = unrestricted)')
                    : '')
              . ($password !== '' ? ($editId === $actorId ? ' [own password changed]' : ' [PASSWORD RESET BY ANOTHER ADMIN]') : '');
        log_activity('update', 'users', $note);
        if ($roleIdNew !== $roleIdOld && $grantsSuper && function_exists('sec_log')) {
            sec_log('admin_super_granted',
                'User #' . $actorId . ' granted super-admin authority to user #' . $editId, 'critical');
        }
        set_flash('success', 'User updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        if ($password !== '' && function_exists('sec_password_record')) {
            sec_password_record($newId, $data['password']);
        }
        log_activity('create', 'users', 'Created user #' . $newId
            . ' role_id=' . var_export($roleIdNew, true)
            . ($roleRow ? ' (' . $roleRow['slug'] . ')' : ' (none = unrestricted)'));
        if ($grantsSuper && function_exists('sec_log')) {
            sec_log('admin_super_granted',
                'User #' . $actorId . ' created user #' . $newId . ' with super-admin authority', 'critical');
        }
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

    /* Same ceiling as the save path (rule 2). Deleting the super admin is not a
       promotion, but it is a lockout — and if it is the LAST super admin it is
       an unrecoverable one, so a non-super admin must not be able to do it. */
    if ($row && !$actorSuper && $isSuperRow($row)) {
        $refuse('You do not have permission to delete a super-admin account.',
            'User #' . $actorId . ' attempted to delete super-admin account #' . $delId,
            '/admin/users');
    }

    if ($row) {
        db_update($table, ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $delId]);
        log_activity('delete', 'users', 'Deleted user #' . $delId
            . ' (role_id ' . var_export($row['role_id'] !== null ? (int) $row['role_id'] : null, true) . ')');
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
    /* Don't render a form whose save is guaranteed to be refused (rule 2). The
       POST handler enforces this independently — this only avoids wasting the
       admin's time. */
    if ($action === 'edit' && !$actorSuper && $isSuperRow($row)) {
        set_flash('error', 'You do not have permission to edit a super-admin account.');
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
                <?php
                /* ROLE — presentation only; the POST whitelist is unchanged.
                   Two things about this control were quietly dangerous and are
                   now stated rather than implied:

                   1. "— None —" stores role_id = NULL, and BOTH permission
                      checks (auth.php:user_can and rbac.php:rbac_is_super) treat
                      a NULL role as an UNRESTRICTED SUPER ADMIN. It read as the
                      safest option in the list and is in fact the most
                      privileged one.

                   2. The list is every row in `roles`, which includes the
                      public-realm roles (student, volunteer, member, donor).
                      Those belong on `members`, not here: a `users` row exists
                      to work in the admin panel, and require_admin() admits any
                      active user, so giving an admin account a 'donor' role does
                      not restrict it to donor things — it just labels it.

                   Grouped, not filtered: live rows already hold these role_ids
                   (ids 15-18), and silently dropping them from the select would
                   reassign somebody's role on the next save. */
                $adminRealm = [];
                $publicRealm = [];
                foreach ($roles as $role) {
                    $slug = strtolower((string) $role['slug']);
                    $cfg  = function_exists('auth_roles') ? (auth_roles()[$slug] ?? null) : null;
                    /* Grouped on the role's PRIMARY realm — the table that owns
                       it — not on whether a `users` row *can* hold it. Every
                       public role lists 'users' in its realms (live rows exist
                       for all nine slugs), so testing membership of that list
                       would put every role in the admin group and the warning
                       would never appear. Unknown slugs sit with the admin
                       realm: this table IS the admin realm. */
                    if ($cfg !== null && ($cfg['realm'] ?? 'users') !== 'users') {
                        $publicRealm[] = $role;
                    } else {
                        $adminRealm[] = $role;
                    }
                }
                $curRoleId = (int) ($row['role_id'] ?? 0);
                ?>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role_id">
                        <?php /* Only a super admin may pick this, and only a super admin may pick
                                 the super-admin role below. Both are refused on the write path
                                 (the ceiling at the top of this file) whether or not they appear
                                 here — this is the courtesy, not the control. */ ?>
                        <?php if ($actorSuper): ?>
                        <option value="0" <?= ($action === 'edit' && $curRoleId === 0) ? 'selected' : '' ?>>
                            — None — (unrestricted super admin)
                        </option>
                        <?php elseif ($action === 'edit' && $curRoleId === 0): ?>
                        <option value="0" selected>— None — (unrestricted super admin)</option>
                        <?php endif; ?>
                        <optgroup label="Admin realm">
                        <?php foreach ($adminRealm as $role): ?>
                            <option value="<?= (int) $role['id'] ?>" <?= ($curRoleId === (int) $role['id']) ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                        <?php endforeach; ?>
                        </optgroup>
                        <?php if ($publicRealm): ?>
                        <optgroup label="Public-realm roles — normally belong on Members, not here">
                        <?php foreach ($publicRealm as $role): ?>
                            <option value="<?= (int) $role['id'] ?>" <?= ($curRoleId === (int) $role['id']) ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                        <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                    <small class="form-hint">
                        <strong>— None — is not "no access": it is full access.</strong> A user with no role
                        bypasses every permission check. Pick an explicit role unless you mean to create
                        another super admin. Public-facing people (students, volunteers, members, donors)
                        belong on <a href="<?= e(admin_url('members')) ?>">Members</a> — a row here is an
                        admin-panel account.
                        <?php if (!$actorSuper): ?>
                        <br><strong>You are not a super admin</strong>, so you cannot grant the super-admin
                        role or the role-less state, cannot change your own role, and cannot edit or delete
                        a super-admin account. Attempts are refused and logged.
                        <?php endif; ?>
                    </small>
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

            <?php /* Author bio. `users.bio` has always existed, and
                     blog-details.php:23 selects it as author_bio and renders it
                     under every article — but no screen anywhere could SET it,
                     so it was NULL for every user and the byline showed a name
                     and an avatar with nothing beneath them. */ ?>
            <div class="form-group">
                <label class="form-label" for="u-bio">Short bio</label>
                <textarea class="form-control" id="u-bio" name="bio" rows="3" maxlength="600"
                          placeholder="One or two sentences — shown under articles this person writes."><?= e($row['bio'] ?? '') ?></textarea>
                <p class="form-hint">Appears on the public blog byline, beside the avatar.</p>
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
