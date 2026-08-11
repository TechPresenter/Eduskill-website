<?php
/**
 * =============================================================================
 *  Admin — Roles & Permissions CRUD.
 *  Standard admin CRUD (list + create + edit + delete) for the `roles` table,
 *  plus a permission-assignment matrix: every permission (grouped by module) is
 *  rendered as a checkbox. On save the role_permissions pivot is fully rebuilt
 *  (existing rows deleted, then the checked ones re-inserted). On edit the
 *  currently-assigned permissions are pre-checked. Works even when the
 *  permissions table is empty (name/slug/description only).
 *
 *  Follows the exemplar pattern in admin/programs.php (CSRF, validation,
 *  unique_slug, activity logging, flash + redirect, pagination + search).
 *  System roles (is_system = 1) may be edited but never deleted.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

// Ensure the 8 standard roles + per-module permission catalogue exist (idempotent).
if (function_exists('rbac_seed_roles')) { rbac_seed_roles(); }

$table  = 'roles';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* ---- Toggle role-permission enforcement (default OFF; super-admins bypass) ---- */
if (is_post() && post('_do') === 'toggle-enforce') {
    require_csrf();
    $on = post('rbac_enforce') ? '1' : '0';
    set_setting('rbac_enforce', $on, 'security', 'boolean');
    log_activity('update', 'roles', 'Role enforcement ' . ($on === '1' ? 'enabled' : 'disabled'));
    set_flash('success', 'Role enforcement ' . ($on === '1' ? 'enabled — non-super-admins are now restricted to assigned modules.' : 'disabled.'));
    redirect('/admin/roles');
}

/**
 * Rebuild the role_permissions pivot for one role from posted checkbox ids.
 * Only ids that exist in the permissions table are kept (guards the FK and
 * any tampered input); the pivot is fully rebuilt so unchecked perms unlink.
 *
 * @param int   $roleId The role whose permissions are being rebuilt.
 * @param array $posted Raw posted permission ids (mixed types).
 */
function roles_sync_permissions(int $roleId, array $posted): void
{
    $validIds = array_map('intval', array_column(db_all("SELECT id FROM permissions"), 'id'));
    $wanted   = array_values(array_unique(array_intersect(array_map('intval', $posted), $validIds)));

    db_delete('role_permissions', 'role_id = :r', [':r' => $roleId]);
    foreach ($wanted as $permId) {
        db_insert('role_permissions', ['role_id' => $roleId, 'permission_id' => $permId]);
    }
}

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['name' => 'required|max:64']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/roles?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'name'        => clean(post('name')),
        'slug'        => unique_slug($table, post('slug') ?: post('name'), $editId ?: null),
        'description' => clean(post('description', '')),
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        roles_sync_permissions($editId, (array) post('permissions', []));
        log_activity('update', 'roles', 'Updated role #' . $editId);
        set_flash('success', 'Role updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        roles_sync_permissions($newId, (array) post('permissions', []));
        log_activity('create', 'roles', 'Created role #' . $newId);
        set_flash('success', 'Role created successfully.');
    }
    redirect('/admin/roles');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['is_system'])) {
            set_flash('error', 'System roles cannot be deleted.');
            redirect('/admin/roles');
        }
        db_delete('role_permissions', 'role_id = :r', [':r' => $delId]);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'roles', 'Deleted role #' . $delId);
        set_flash('success', 'Role deleted.');
    }
    redirect('/admin/roles');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Role not found.');
        redirect('/admin/roles');
    }

    // All permissions grouped by module for the assignment matrix.
    $allPerms = db_all("SELECT * FROM permissions ORDER BY module ASC, name ASC");
    $grouped  = [];
    foreach ($allPerms as $perm) {
        $grouped[$perm['module']][] = $perm;
    }

    // Currently-assigned permission ids (for pre-checking on edit).
    $checkedIds = [];
    if ($action === 'edit') {
        $checkedIds = array_map('intval', array_column(
            db_all("SELECT permission_id FROM role_permissions WHERE role_id = :r", [':r' => (int) $row['id']]),
            'permission_id'
        ));
    }

    $page_title = $action === 'edit' ? 'Edit Role' : 'Add Role';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Roles &amp; Permissions / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('roles')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('roles')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" data-slug-source required value="<?= e($row['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" data-slug-target value="<?= e($row['slug'] ?? '') ?>" placeholder="auto-generated">
                    <?php if (!empty($row['is_system'])): ?>
                        <small class="form-hint">This is a system role. Changing its slug may affect access checks.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" style="min-height:90px;" placeholder="What this role is for…"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Permissions</label>
                <?php if (!$grouped): ?>
                    <div class="empty-state" style="margin:0;">
                        <div class="icon"><?= lucide('shield') ?></div>
                        No permissions have been defined yet. You can still save the role now and assign permissions later.
                    </div>
                <?php else: ?>
                    <small class="form-hint">Tick the permissions this role should have. Grouped by module.</small>
                    <?php foreach ($grouped as $module => $perms): ?>
                        <div style="border:1px solid var(--border);border-radius:12px;padding:1rem 1.1rem;margin-top:.9rem;">
                            <div class="flex items-center justify-between" style="margin-bottom:.6rem;">
                                <strong><?= e(ucwords(str_replace(['_', '-'], ' ', $module))) ?></strong>
                                <label class="checkbox" style="font-weight:500;"><input type="checkbox" data-check-group="mod-<?= e($module) ?>"> Select all</label>
                            </div>
                            <div class="grid-2">
                                <?php foreach ($perms as $perm): ?>
                                    <label class="checkbox">
                                        <input type="checkbox" name="permissions[]" value="<?= (int) $perm['id'] ?>"
                                               data-check-item="mod-<?= e($module) ?>"
                                               <?= in_array((int) $perm['id'], $checkedIds, true) ? 'checked' : '' ?>>
                                        <?= e($perm['name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Role</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('roles')) ?>">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    // Per-module "Select all" toggle for the permission matrix.
    document.querySelectorAll('[data-check-group]').forEach(function (master) {
        var group = master.getAttribute('data-check-group');
        var items = document.querySelectorAll('[data-check-item="' + group + '"]');
        var sync = function () {
            var all = items.length > 0;
            items.forEach(function (i) { if (!i.checked) all = false; });
            master.checked = all;
        };
        master.addEventListener('change', function () {
            items.forEach(function (i) { i.checked = master.checked; });
        });
        items.forEach(function (i) { i.addEventListener('change', sync); });
        sync();
    });
    </script>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Roles & Permissions';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', name, slug) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate(
    "SELECT roles.*, (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = roles.id) AS perm_count
     FROM roles WHERE $where ORDER BY is_system DESC, name ASC",
    $params,
    12
);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Roles &amp; Permissions</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('roles?action=create')) ?>">+ Add Role</a>
</div>

<?php $enf = function_exists('rbac_enforcement_on') && rbac_enforcement_on(); ?>
<div class="panel" style="margin-bottom:1rem;"><div class="panel-body" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:.85rem;">
        <span style="width:46px;height:46px;border-radius:13px;display:grid;place-items:center;background:var(--grad-brand-soft,rgba(11,78,61,.12));color:var(--brand-600);"><?= lucide('shield-check') ?></span>
        <div>
            <strong>Role permission enforcement</strong>
            <span class="pill <?= $enf ? 'pill-green' : 'pill-gray' ?>" style="margin-left:.4rem;"><?= $enf ? 'On' : 'Off' ?></span>
            <br><small class="text-muted">When on, non-super-admins see and can open <strong>only their assigned modules</strong>. Super admins (and role-less admins) always keep full access, so this cannot lock you out.</small>
        </div>
    </div>
    <form method="post" action="<?= e(admin_url('roles')) ?>">
        <?= csrf_field() ?><input type="hidden" name="_do" value="toggle-enforce">
        <input type="hidden" name="rbac_enforce" value="<?= $enf ? '0' : '1' ?>">
        <button class="btn <?= $enf ? 'btn-secondary' : 'btn-primary' ?>" type="submit"><?= lucide('power') ?> <?= $enf ? 'Disable enforcement' : 'Enable enforcement' ?></button>
    </form>
</div></div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('roles')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search roles…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Permissions</th><th>Type</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><strong><?= e($r['name']) ?></strong></td>
                        <td><code><?= e($r['slug']) ?></code></td>
                        <td><small class="text-muted"><?= e(excerpt($r['description'] ?? '', 12)) ?></small></td>
                        <td><span class="pill <?= (int) $r['perm_count'] > 0 ? 'pill-blue' : 'pill-gray' ?>"><?= (int) $r['perm_count'] ?> perm<?= (int) $r['perm_count'] === 1 ? '' : 's' ?></span></td>
                        <td><span class="pill <?= !empty($r['is_system']) ? 'pill-amber' : 'pill-gray' ?>"><?= !empty($r['is_system']) ? 'System' : 'Custom' ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('roles?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <?php if (empty($r['is_system'])): ?>
                                <form method="post" action="<?= e(admin_url('roles')) ?>" data-confirm="Delete this role permanently? Users with this role will be unassigned." style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                                </form>
                                <?php else: ?>
                                <span class="icon-btn" title="System role — protected" style="opacity:.4;cursor:not-allowed;"><?= lucide('lock') ?></span>
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
            <div class="empty-state"><div class="icon"><?= lucide('shield') ?></div>No roles yet. <a href="<?= e(admin_url('roles?action=create')) ?>">Add your first role</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
