<?php
/**
 * =============================================================================
 *  Admin — Employees (personal info, designation, department, photo, docs)
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table    = 'employees';
$action   = get('action', 'list');
$id       = (int) get('id', 0);
$deptOpts = department_options();
$types    = employment_type_labels();
$statuses = employee_status_labels();

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) { set_flash('error', 'Employee not found.'); redirect('/admin/employees'); }

    $name = clean(post('name'));
    if ($name === '') {
        flash_old($_POST);
        set_flash('error', 'Employee name is required.');
        redirect('/admin/employees?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $deptId = (int) post('department_id', 0);
    $data = [
        'name'              => $name,
        'email'             => clean(post('email')) ?: null,
        'phone'             => clean(post('phone')) ?: null,
        'designation'       => clean(post('designation')) ?: null,
        'department_id'     => isset($deptOpts[$deptId]) ? $deptId : null,
        'employment_type'   => isset($types[(string) post('employment_type')]) ? post('employment_type') : 'full_time',
        'joining_date'      => clean(post('joining_date')) ?: null,
        'date_of_birth'     => clean(post('date_of_birth')) ?: null,
        'gender'            => in_array(post('gender'), ['male', 'female', 'other'], true) ? post('gender') : null,
        'blood_group'       => clean(post('blood_group')) ?: null,
        'address'           => clean(post('address')) ?: null,
        'emergency_contact' => clean(post('emergency_contact')) ?: null,
        'bio'               => clean(post('bio')) ?: null,
        'status'            => isset($statuses[(string) post('status')]) ? post('status') : 'active',
    ];

    // Optional photo upload (replaces + deletes the old one).
    if (!empty($_FILES['photo']['name'])) {
        $up = upload_image($_FILES['photo'], 'media');
        if (!$up['success']) {
            flash_old($_POST);
            set_flash('error', $up['error']);
            redirect('/admin/employees?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['photo'] = $up['path'];
        if ($old && !empty($old['photo'])) delete_upload($old['photo']);
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'employees', 'Updated employee #' . $editId . ' (' . $name . ')');
        set_flash('success', 'Employee updated.');
        redirect('/admin/employees?action=edit&id=' . $editId);
    } else {
        $data['employee_code'] = employee_next_code();
        $newId = db_insert($table, $data);
        log_activity('create', 'employees', 'Added employee #' . $newId . ' (' . $data['employee_code'] . ' ' . $name . ')');
        set_flash('success', 'Employee added as ' . $data['employee_code'] . '.');
        redirect('/admin/employees?action=edit&id=' . $newId);
    }
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    if ($row = find($table, $delId)) {
        if (!empty($row['photo'])) delete_upload($row['photo']);
        foreach (['employee_documents', 'employee_attendance', 'salary_structures', 'payslips', 'leave_requests', 'performance_reviews'] as $t) {
            db_delete($t, 'employee_id = :e', [':e' => $delId]);
        }
        db_update('departments', ['hod_employee_id' => null], 'hod_employee_id = :e', [':e' => $delId]);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'employees', 'Deleted employee #' . $delId . ' (' . ($row['name'] ?? '') . ')');
        set_flash('success', 'Employee and all related records deleted.');
    }
    redirect('/admin/employees');
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) { set_flash('error', 'Employee not found.'); redirect('/admin/employees'); }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));

    $page_title = $action === 'edit' ? 'Edit Employee' : 'New Employee';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div>
            <h1><?= e($page_title) ?></h1>
            <span class="muted"><?= $action === 'edit' ? e($row['employee_code']) . ' · ' . e($row['name']) : 'Add a team member' ?></span>
        </div>
        <div class="flex" style="gap:.5rem;">
            <?php if ($action === 'edit'): ?>
                <a class="btn btn-secondary" href="<?= e(admin_url('employee-id-card?id=' . $row['id'])) ?>"><?= lucide('id-card') ?> ID Card</a>
                <a class="btn btn-secondary" href="<?= e(admin_url('employee-documents?employee=' . $row['id'])) ?>"><?= lucide('folder') ?> Documents</a>
                <a class="btn btn-secondary" href="<?= e(admin_url('employee-salary?employee=' . $row['id'])) ?>"><?= lucide('wallet') ?> Salary</a>
            <?php endif; ?>
            <a class="btn btn-ghost" href="<?= e(admin_url('employees')) ?>">&larr; Back</a>
        </div>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('employees')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Profile</h3>
                <div class="form-group"><label class="form-label">Photo</label>
                    <input class="form-control" type="file" name="photo" accept="image/*" data-preview="#photoPreview">
                    <img id="photoPreview" class="img-preview" src="<?= e(image_url($row['photo'] ?? null, 'avatar')) ?>" alt="" style="margin-top:.6rem;max-width:120px;border-radius:12px;">
                </div>
                <div class="form-group"><label class="form-label">Full name <span class="req">*</span></label>
                    <input class="form-control" name="name" required value="<?= $o('name') ?>"></div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" value="<?= $o('email') ?>"></div>
                    <div class="form-group"><label class="form-label">Phone</label>
                        <input class="form-control" name="phone" value="<?= $o('phone') ?>"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Date of birth</label>
                        <input class="form-control" type="date" name="date_of_birth" value="<?= $o('date_of_birth') ?>"></div>
                    <div class="form-group"><label class="form-label">Gender</label>
                        <select class="form-select" name="gender">
                            <option value="">—</option>
                            <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $k => $l): ?>
                                <option value="<?= $k ?>" <?= ($row['gender'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Blood group</label>
                        <input class="form-control" name="blood_group" value="<?= $o('blood_group') ?>" placeholder="e.g. O+"></div>
                    <div class="form-group"><label class="form-label">Emergency contact</label>
                        <input class="form-control" name="emergency_contact" value="<?= $o('emergency_contact') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Address</label>
                    <input class="form-control" name="address" value="<?= $o('address') ?>"></div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Employment</h3>
                <div class="form-group"><label class="form-label">Designation</label>
                    <input class="form-control" name="designation" value="<?= $o('designation') ?>" placeholder="e.g. Program Manager"></div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Department</label>
                        <select class="form-select" name="department_id">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($deptOpts as $did => $dname): ?>
                                <option value="<?= (int) $did ?>" <?= (int) old('department_id', $row['department_id'] ?? 0) === (int) $did ? 'selected' : '' ?>><?= e($dname) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">No department? <a href="<?= e(admin_url('departments?action=create')) ?>" target="_blank">Create one</a>.</small>
                    </div>
                    <div class="form-group"><label class="form-label">Employment type</label>
                        <select class="form-select" name="employment_type">
                            <?php foreach ($types as $k => $l): ?>
                                <option value="<?= $k ?>" <?= ($row['employment_type'] ?? 'full_time') === $k ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Joining date</label>
                        <input class="form-control" type="date" name="joining_date" value="<?= e(old('joining_date', $row['joining_date'] ?? date('Y-m-d'))) ?>"></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $k => $l): ?>
                                <option value="<?= $k ?>" <?= ($row['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>
                <div class="form-group"><label class="form-label">Bio / notes</label>
                    <textarea class="form-textarea" name="bio" style="min-height:110px;"><?= $o('bio') ?></textarea></div>
                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Add' ?> employee</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('employees')) ?>">Cancel</a>
                </div>
            </div></div>
        </div>
    </form>
    <?php clear_old(); include __DIR__ . '/partials/foot.php'; exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Employees';
$search     = trim((string) get('q', ''));
$deptFilter = (int) get('department', 0);
$statFilter = (string) get('status', '');

$where = '1=1'; $params = [];
if ($deptFilter && isset($deptOpts[$deptFilter])) { $where .= ' AND e.department_id = :d'; $params[':d'] = $deptFilter; }
if (isset($statuses[$statFilter]))                { $where .= ' AND e.status = :st'; $params[':st'] = $statFilter; }
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', e.name, e.employee_code, e.email, e.designation) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$p = paginate(
    "SELECT e.*, d.name AS dept_name
       FROM employees e
       LEFT JOIN departments d ON d.id = e.department_id
      WHERE $where
      ORDER BY e.name ASC",
    $params, 20
);

$counts = [
    'all'      => db_count('employees', ''),
    'active'   => db_count('employees', "status = 'active'"),
    'depts'    => db_count('departments', ''),
    'onleave'  => (int) db_value("SELECT COUNT(*) FROM leave_requests WHERE status='approved' AND CURDATE() BETWEEN from_date AND to_date"),
];
$hasFilters = $search !== '' || $deptFilter || $statFilter !== '';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Employees</h1><span class="muted"><?= (int) $counts['all'] ?> employees · <?= (int) $counts['depts'] ?> departments</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('employees?action=create')) ?>">+ New Employee</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('users-round') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Total employees</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['active'] ?></div><div class="stat-label">Active</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('building-2') ?></div><div><div class="stat-value"><?= (int) $counts['depts'] ?></div><div class="stat-label">Departments</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('plane') ?></div><div><div class="stat-value"><?= (int) $counts['onleave'] ?></div><div class="stat-label">On leave today</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('employees')) ?>">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, code, email…"></form>
        <form method="get" action="<?= e(admin_url('employees')) ?>" class="flex" style="gap:.5rem;">
            <select class="form-select" name="department" onchange="this.form.submit()">
                <option value="">All departments</option>
                <?php foreach ($deptOpts as $did => $dname): ?><option value="<?= (int) $did ?>" <?= $deptFilter === (int) $did ? 'selected' : '' ?>><?= e($dname) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= $statFilter === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($hasFilters): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('employees')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Employee</th><th>Department</th><th>Type</th><th>Joined</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): ?>
            <tr>
                <td>
                    <div class="flex" style="gap:.6rem;align-items:center;">
                        <img src="<?= e(image_url($r['photo'] ?? null, 'avatar')) ?>" alt="" style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex:none;">
                        <div>
                            <strong><?= e($r['name']) ?></strong><br>
                            <small class="text-muted"><?= e($r['employee_code']) ?><?= $r['designation'] ? ' · ' . e($r['designation']) : '' ?></small>
                        </div>
                    </div>
                </td>
                <td><?= $r['dept_name'] ? e($r['dept_name']) : '<span class="text-muted">—</span>' ?></td>
                <td><span class="pill pill-gray"><?= e($types[$r['employment_type']] ?? $r['employment_type']) ?></span></td>
                <td><?= $r['joining_date'] ? e(format_date($r['joining_date'])) : '<span class="text-muted">—</span>' ?></td>
                <td><span class="pill <?= e(employee_status_pill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('employee-id-card?id=' . $r['id'])) ?>" title="ID card"><?= lucide('id-card') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('employee-salary?employee=' . $r['id'])) ?>" title="Salary"><?= lucide('wallet') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('employees?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('employees')) ?>" data-confirm="Delete this employee and ALL their records (attendance, salary, leave, reviews, documents)?" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('users-round') ?></div>
            <?= $hasFilters ? 'No employees match your filters. ' : 'No employees yet. ' ?>
            <a href="<?= e(admin_url('employees?action=create')) ?>">Add one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
