<?php
/**
 * =============================================================================
 *  Admin — Careers CRUD.
 *  Job openings management: list + create + edit + delete, CSRF, validation,
 *  pagination, slug generation and activity logging. Follows the standard
 *  admin CRUD pattern (see admin/programs.php). No file uploads for this table.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'careers';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$types    = ['full-time', 'part-time', 'contract', 'internship', 'volunteer'];
$statuses = ['open', 'closed'];

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/careers?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $deadline = trim((string) post('deadline', ''));

    $data = [
        'title'        => clean(post('title')),
        'slug'         => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'department'   => clean(post('department', '')),
        'location'     => clean(post('location', '')),
        'type'         => in_array(post('type'), $types, true) ? post('type') : 'full-time',
        'description'  => post('description', ''), // rich text allowed
        'requirements' => post('requirements', ''),
        'salary_range' => clean(post('salary_range', '')),
        'openings'     => max(0, (int) post('openings', 1)),
        'deadline'     => $deadline !== '' ? $deadline : null,
        'status'       => in_array(post('status'), $statuses, true) ? post('status') : 'open',
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'careers', 'Updated career #' . $editId);
        set_flash('success', 'Career updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'careers', 'Created career #' . $newId);
        set_flash('success', 'Career created successfully.');
    }
    redirect('/admin/careers');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'careers', 'Deleted career #' . $delId);
        set_flash('success', 'Career deleted.');
    }
    redirect('/admin/careers');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Career not found.');
        redirect('/admin/careers');
    }
    $page_title = $action === 'edit' ? 'Edit Career' : 'Add Career';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Careers / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('careers')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('careers')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" data-slug-source required value="<?= e($row['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" data-slug-target value="<?= e($row['slug'] ?? '') ?>" placeholder="auto-generated">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input class="form-control" name="department" value="<?= e($row['department'] ?? '') ?>" placeholder="e.g. Programs">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input class="form-control" name="location" value="<?= e($row['location'] ?? '') ?>" placeholder="e.g. Delhi / Remote">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type">
                        <?php foreach ($types as $t): ?>
                            <option value="<?= e($t) ?>" <?= (($row['type'] ?? 'full-time') === $t) ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $t))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= (($row['status'] ?? 'open') === $s) ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Salary Range</label>
                    <input class="form-control" name="salary_range" value="<?= e($row['salary_range'] ?? '') ?>" placeholder="e.g. ₹25,000 – ₹35,000 / month">
                </div>
                <div class="form-group">
                    <label class="form-label">Openings</label>
                    <input class="form-control" type="number" name="openings" min="0" value="<?= (int) ($row['openings'] ?? 1) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Application Deadline</label>
                <input class="form-control" type="date" name="deadline" value="<?= e($row['deadline'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" data-wysiwyg style="min-height:180px;"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Requirements</label>
                <textarea class="form-textarea" name="requirements" data-wysiwyg style="min-height:120px;"><?= e($row['requirements'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Career</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('careers')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Careers';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, department, location) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Careers</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('careers?action=create')) ?>">+ Add Career</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('careers')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search careers…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Title</th><th>Department</th><th>Type</th><th>Location</th><th>Openings</th><th>Deadline</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><strong><?= e($r['title']) ?></strong></td>
                        <td><?= e($r['department'] ?: '—') ?></td>
                        <td><span class="pill pill-blue"><?= e(ucwords(str_replace('-', ' ', $r['type']))) ?></span></td>
                        <td><?= e($r['location'] ?: '—') ?></td>
                        <td><?= (int) $r['openings'] ?></td>
                        <td><?= !empty($r['deadline']) ? e(format_date($r['deadline'], 'd M Y')) : '—' ?></td>
                        <td><span class="pill <?= $r['status'] === 'open' ? 'pill-green' : 'pill-gray' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('careers?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pen-line') ?></a>
                                <form method="post" action="<?= e(admin_url('careers')) ?>" data-confirm="Delete this career permanently?" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $p['links'] ?>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('briefcase') ?></div>No careers yet. <a href="<?= e(admin_url('careers?action=create')) ?>">Add your first job opening</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
