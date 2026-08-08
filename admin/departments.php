<?php
/**
 * =============================================================================
 *  Admin — Departments (create · assign HOD · dept-wise counts)
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'departments';
$action = get('action', 'list');
$id     = (int) get('id', 0);
$empOpts = employee_options('');   // any employee can be HOD

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    if ($editId && !find($table, $editId)) {
        set_flash('error', 'Department not found.');
        redirect('/admin/departments');
    }
    $name = clean(post('name'));
    if ($name === '') {
        flash_old($_POST);
        set_flash('error', 'Department name is required.');
        redirect('/admin/departments?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }
    $hod = (int) post('hod_employee_id', 0);
    $data = [
        'name'            => $name,
        'code'            => clean(post('code')) ?: null,
        'description'     => clean(post('description')) ?: null,
        'hod_employee_id' => ($hod && isset($empOpts[$hod])) ? $hod : null,
        'status'          => post('status') === 'inactive' ? 'inactive' : 'active',
    ];
    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'departments', 'Updated department #' . $editId . ' (' . $name . ')');
        set_flash('success', 'Department updated.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'departments', 'Created department #' . $newId . ' (' . $name . ')');
        set_flash('success', 'Department created.');
    }
    redirect('/admin/departments');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    if ($row = find($table, $delId)) {
        db_update('employees', ['department_id' => null], 'department_id = :d', [':d' => $delId]); // unassign
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'departments', 'Deleted department #' . $delId . ' (' . ($row['name'] ?? '') . ')');
        set_flash('success', 'Department deleted; its employees were unassigned.');
    }
    redirect('/admin/departments');
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) { set_flash('error', 'Department not found.'); redirect('/admin/departments'); }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));

    $page_title = $action === 'edit' ? 'Edit Department' : 'New Department';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Departments / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('departments')) ?>">&larr; Back to list</a>
    </div>
    <form class="admin-form" method="post" action="<?= e(admin_url('departments')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
        <div class="panel" style="max-width:680px;"><div class="panel-body">
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" required value="<?= $o('name') ?>" placeholder="e.g. Programs"></div>
                <div class="form-group"><label class="form-label">Code</label>
                    <input class="form-control" name="code" value="<?= $o('code') ?>" placeholder="e.g. PRG"></div>
            </div>
            <div class="form-group"><label class="form-label">Description</label>
                <input class="form-control" name="description" value="<?= $o('description') ?>"></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Head of Department</label>
                    <select class="form-select" name="hod_employee_id">
                        <option value="">— None —</option>
                        <?php foreach ($empOpts as $eid => $label): ?>
                            <option value="<?= (int) $eid ?>" <?= (int) old('hod_employee_id', $row['hod_employee_id'] ?? 0) === (int) $eid ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?= ($row['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($row['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select></div>
            </div>
            <div class="form-actions mt-3">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> department</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('departments')) ?>">Cancel</a>
            </div>
        </div></div>
    </form>
    <?php clear_old(); include __DIR__ . '/partials/foot.php'; exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Departments';
$rows = db_all(
    "SELECT d.*, e.name AS hod_name, e.employee_code AS hod_code,
            (SELECT COUNT(*) FROM employees em WHERE em.department_id = d.id) AS emp_count
       FROM departments d
       LEFT JOIN employees e ON e.id = d.hod_employee_id
      ORDER BY d.name ASC"
);
$totalEmployees = db_count('employees', "status = 'active'");
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Departments</h1><span class="muted"><?= count($rows) ?> departments · <?= (int) $totalEmployees ?> active employees</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('departments?action=create')) ?>">+ New Department</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('building-2') ?></div><div><div class="stat-value"><?= count($rows) ?></div><div class="stat-label">Departments</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('users-round') ?></div><div><div class="stat-value"><?= (int) $totalEmployees ?></div><div class="stat-label">Active employees</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('user-check') ?></div><div><div class="stat-value"><?= count(array_filter($rows, fn($r) => $r['hod_employee_id'])) ?></div><div class="stat-label">With HOD</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Department</th><th>Code</th><th>Head</th><th>Employees</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong><?= $r['description'] ? '<br><small class="text-muted">' . e($r['description']) . '</small>' : '' ?></td>
                <td><?= $r['code'] ? '<span class="pill pill-gray">' . e($r['code']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                <td><?= $r['hod_name'] ? e($r['hod_name']) : '<span class="text-muted">—</span>' ?></td>
                <td><a href="<?= e(admin_url('employees?department=' . $r['id'])) ?>"><?= (int) $r['emp_count'] ?></a></td>
                <td><span class="pill <?= $r['status'] === 'active' ? 'pill-green' : 'pill-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('departments?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('departments')) ?>" data-confirm="Delete this department? Its employees will be unassigned." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('building-2') ?></div>No departments yet. <a href="<?= e(admin_url('departments?action=create')) ?>">Create one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
