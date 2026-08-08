<?php
/**
 * =============================================================================
 *  Admin — Schools (partner school registration + CRUD)
 * =============================================================================
 *  list · create · edit · delete. Each school links to its dashboard, students,
 *  batches, staff and fees. Standard admin pattern: CSRF, prepared statements,
 *  pagination, stat cards, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'schools';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$types    = ['government' => 'Government', 'private' => 'Private', 'aided' => 'Aided', 'other' => 'Other'];
$statuses = ['active' => 'Active', 'inactive' => 'Inactive', 'prospective' => 'Prospective'];

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'School not found.');
        redirect('/admin/schools');
    }

    if (clean(post('name')) === '') {
        flash_old($_POST);
        set_flash('error', 'School name is required.');
        redirect('/admin/schools?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'name'             => clean(post('name')),
        'slug'             => unique_slug($table, post('slug') ?: post('name'), $editId ?: null),
        'type'             => isset($types[(string) post('type')]) ? post('type') : 'government',
        'board'            => clean(post('board')) ?: null,
        'accreditation'    => clean(post('accreditation')) ?: null,
        'udise_code'       => clean(post('udise_code')) ?: null,
        'contact_person'   => clean(post('contact_person')) ?: null,
        'email'            => clean(post('email')) ?: null,
        'phone'            => clean(post('phone')) ?: null,
        'website'          => clean(post('website')) ?: null,
        'address'          => clean(post('address')) ?: null,
        'city'             => clean(post('city')) ?: null,
        'state'            => clean(post('state')) ?: null,
        'pincode'          => clean(post('pincode')) ?: null,
        'established_year' => (int) post('established_year') ?: null,
        'status'           => isset($statuses[(string) post('status')]) ? post('status') : 'active',
        'notes'            => trim((string) post('notes')) ?: null,
    ];

    if (!empty($_FILES['logo']['name'])) {
        // Raster formats only — exclude SVG (which upload.php would otherwise
        // accept unsanitised, enabling stored XSS from a crafted .svg).
        $up = upload_image($_FILES['logo'], 'schools', ['allowed' => 'jpg,jpeg,png,gif,webp']);
        if (!$up['success']) {
            flash_old($_POST);
            set_flash('error', 'Logo: ' . $up['error']);
            redirect('/admin/schools?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['logo'] = $up['path'];
        if ($editId && !empty($old['logo'])) { delete_upload($old['logo']); }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'schools', 'Updated school #' . $editId);
        set_flash('success', 'School updated.');
        redirect('/admin/schools');
    }
    $newId = db_insert($table, $data);
    $school = school_ensure_code(find($table, $newId));
    log_activity('create', 'schools', 'Created school #' . $newId);
    set_flash('success', 'School registered. Code ' . $school['code'] . '.');
    redirect('/admin/school-dashboard?id=' . $newId);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        if (!empty($row['logo'])) { delete_upload($row['logo']); }
        db_delete($table, 'id = :id', [':id' => $delId]); // children cascade
        log_activity('delete', 'schools', 'Deleted school #' . $delId . ' (' . ($row['name'] ?? '') . ')');
        set_flash('success', 'School and all its records deleted.');
    }
    redirect('/admin/schools');
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'School not found.');
        redirect('/admin/schools');
    }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));
    $page_title = $action === 'edit' ? 'Edit School' : 'Register School';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Schools / <?= $action === 'edit' ? 'Edit' : 'Register' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('schools')) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('schools')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">School profile</h3>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Name <span class="req">*</span></label>
                        <input class="form-control" name="name" data-slug-source required value="<?= $o('name') ?>"></div>
                    <div class="form-group"><label class="form-label">Slug</label>
                        <input class="form-control" name="slug" data-slug-target value="<?= $o('slug') ?>" placeholder="auto"></div>
                </div>
                <div class="grid-3">
                    <div class="form-group"><label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['type'] ?? 'government') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Board / affiliation</label>
                        <input class="form-control" name="board" value="<?= $o('board') ?>" placeholder="CBSE / State…"></div>
                    <div class="form-group"><label class="form-label">Established year</label>
                        <input class="form-control" type="number" name="established_year" value="<?= $o('established_year') ?>"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Accreditation</label>
                        <input class="form-control" name="accreditation" value="<?= $o('accreditation') ?>"></div>
                    <div class="form-group"><label class="form-label">UDISE code</label>
                        <input class="form-control" name="udise_code" value="<?= $o('udise_code') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Logo</label>
                    <input class="form-control" type="file" name="logo" accept="image/*">
                    <?php if (!empty($row['logo'])): ?><small class="form-hint">Current logo kept unless replaced.</small><?php endif; ?>
                </div>
                <div class="form-group"><label class="form-label">Notes</label>
                    <textarea class="form-textarea" name="notes" style="min-height:90px;"><?= e($row['notes'] ?? '') ?></textarea></div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Contact & address</h3>
                <div class="form-group"><label class="form-label">Contact person</label>
                    <input class="form-control" name="contact_person" value="<?= $o('contact_person') ?>"></div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" value="<?= $o('email') ?>"></div>
                    <div class="form-group"><label class="form-label">Phone</label>
                        <input class="form-control" name="phone" value="<?= $o('phone') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Website</label>
                    <input class="form-control" name="website" value="<?= $o('website') ?>"></div>
                <div class="form-group"><label class="form-label">Address</label>
                    <input class="form-control" name="address" value="<?= $o('address') ?>"></div>
                <div class="grid-3">
                    <div class="form-group"><label class="form-label">City</label><input class="form-control" name="city" value="<?= $o('city') ?>"></div>
                    <div class="form-group"><label class="form-label">State</label><input class="form-control" name="state" value="<?= $o('state') ?>"></div>
                    <div class="form-group"><label class="form-label">Pincode</label><input class="form-control" name="pincode" value="<?= $o('pincode') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Register' ?> school</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('schools')) ?>">Cancel</a>
                </div>
            </div></div>
        </div>
    </form>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title  = 'Schools';
$search      = trim((string) get('q', ''));
$typeFilter  = (string) get('type', '');
$statusFilter = (string) get('status', '');

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', s.name, s.code, s.city, s.contact_person) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
if (isset($types[$typeFilter]))       { $where .= ' AND s.type = :ty';  $params[':ty'] = $typeFilter; }
if (isset($statuses[$statusFilter]))  { $where .= ' AND s.status = :st'; $params[':st'] = $statusFilter; }

$p = paginate(
    "SELECT s.*, (SELECT COUNT(*) FROM school_students ss WHERE ss.school_id = s.id) AS student_count
       FROM schools s WHERE $where ORDER BY s.name ASC",
    $params, 15
);

$counts = [
    'all'      => db_count('schools'),
    'active'   => db_count('schools', "status = 'active'"),
    'students' => db_count('school_students'),
];
$feeCollected = (float) db_value("SELECT COALESCE(SUM(amount_paid),0) FROM school_fee_payments");

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Schools</h1><span class="muted"><?= (int) $counts['all'] ?> partner schools</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('schools?action=create')) ?>">+ Register School</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('school') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Schools</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['active'] ?></div><div class="stat-label">Active</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $counts['students'] ?></div><div class="stat-label">Students</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('indian-rupee') ?></div><div><div class="stat-value"><?= e(money($feeCollected, '₹', 0)) ?></div><div class="stat-label">Fees collected</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('schools')) ?>">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, code, city…"></form>
        <form method="get" action="<?= e(admin_url('schools')) ?>" class="flex" style="gap:.5rem;">
            <select class="form-select" name="type" onchange="this.form.submit()">
                <option value="">All types</option>
                <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $typeFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($search !== '' || $typeFilter !== '' || $statusFilter !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('schools')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>School</th><th>Type</th><th>City</th><th>Students</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): $dash = admin_url('school-dashboard?id=' . $r['id']); ?>
            <tr>
                <td><div class="flex items-center" style="gap:.6rem;">
                    <img src="<?= e(image_url($r['logo'] ?? null, 'general')) ?>" alt="" style="width:34px;height:34px;border-radius:8px;object-fit:cover;flex:none;background:#eef2f7;">
                    <div><a href="<?= e($dash) ?>"><strong><?= e($r['name']) ?></strong></a><br><small class="text-muted"><?= e($r['code'] ?? '—') ?><?= $r['board'] ? ' · ' . e($r['board']) : '' ?></small></div>
                </div></td>
                <td><?= e($types[$r['type']] ?? $r['type']) ?></td>
                <td><?= $r['city'] ? e($r['city']) : '—' ?></td>
                <td><?= (int) $r['student_count'] ?></td>
                <td><span class="pill <?= e(school_status_pill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e($dash) ?>" title="Dashboard"><?= lucide('layout-dashboard') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('schools?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('schools')) ?>" data-confirm="Delete this school and ALL its students, batches, fees and attendance? This cannot be undone." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('school') ?></div>
            <?= ($search !== '' || $typeFilter !== '' || $statusFilter !== '') ? 'No schools match your filters. ' : 'No schools yet. ' ?>
            <a href="<?= e(admin_url('schools?action=create')) ?>">Register one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
