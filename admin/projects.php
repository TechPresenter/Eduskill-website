<?php
/**
 * =============================================================================
 *  Admin — Projects CRUD.
 *  Full list + create + edit + delete for foundation projects, following the
 *  standard admin module pattern (CSRF, validation, pagination, image upload
 *  with old-file cleanup, slug generation, activity logging). Each project may
 *  optionally belong to a program.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'projects';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/** Allowed status values for the projects table (matches the ENUM in schema). */
$statuses = ['ongoing', 'completed', 'upcoming', 'paused'];

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/projects?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $programId = (int) post('program_id', 0);

    $data = [
        'program_id'    => $programId ?: null,
        'title'         => clean(post('title')),
        'slug'          => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'summary'       => clean(post('summary', '')),
        'description'   => post('description', ''), // rich text allowed
        'location'      => clean(post('location', '')),
        'start_date'    => post('start_date') ?: null,
        'end_date'      => post('end_date') ?: null,
        'budget'        => post('budget') !== '' ? (float) post('budget') : null,
        'beneficiaries' => post('beneficiaries') !== '' ? (int) post('beneficiaries') : null,
        'progress'      => max(0, min(100, (int) post('progress', 0))),
        'status'        => in_array(post('status'), $statuses, true) ? post('status') : 'ongoing',
        'is_featured'   => post('is_featured') ? 1 : 0,
        'sort_order'    => (int) post('sort_order', 0),
    ];

    // Optional image upload (replaces + deletes the old one).
    if (!empty($_FILES['image']['name'])) {
        $up = upload_image($_FILES['image'], 'projects');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/projects?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['image'])) delete_upload($old['image']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'projects', 'Updated project #' . $editId);
        set_flash('success', 'Project updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'projects', 'Created project #' . $newId);
        set_flash('success', 'Project created successfully.');
    }
    redirect('/admin/projects');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['image'])) delete_upload($row['image']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'projects', 'Deleted project #' . $delId);
        set_flash('success', 'Project deleted.');
    }
    redirect('/admin/projects');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Project not found.');
        redirect('/admin/projects');
    }
    $programs = db_all("SELECT id, title FROM programs ORDER BY title ASC");
    $page_title = $action === 'edit' ? 'Edit Project' : 'Add Project';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Projects / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('projects')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('projects')) ?>">
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
                    <label class="form-label">Program</label>
                    <select class="form-select" name="program_id">
                        <option value="0">— None —</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= (int) $prog['id'] ?>" <?= ((int) ($row['program_id'] ?? 0) === (int) $prog['id']) ? 'selected' : '' ?>><?= e($prog['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input class="form-control" name="location" value="<?= e($row['location'] ?? '') ?>" placeholder="City / District, State">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Summary</label>
                <textarea class="form-textarea" name="summary" style="min-height:80px;" placeholder="Short one-line summary shown in listings."><?= e($row['summary'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Full Description</label>
                <textarea class="form-textarea" name="description" style="min-height:180px;"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input class="form-control" type="date" name="start_date" value="<?= e($row['start_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input class="form-control" type="date" name="end_date" value="<?= e($row['end_date'] ?? '') ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Budget (₹)</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="budget" value="<?= e($row['budget'] ?? '') ?>" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Beneficiaries</label>
                    <input class="form-control" type="number" min="0" name="beneficiaries" value="<?= e($row['beneficiaries'] ?? '') ?>" placeholder="0">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Progress (%)</label>
                    <input class="form-control" type="number" min="0" max="100" name="progress" value="<?= (int) ($row['progress'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $st): ?>
                            <option value="<?= e($st) ?>" <?= (($row['status'] ?? 'ongoing') === $st) ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Project Image</label>
                <input class="form-control" type="file" name="image" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['image']) ? upload_url($row['image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['image']) ? 'display:none;' : '' ?>">
            </div>

            <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= !empty($row['is_featured']) ? 'checked' : '' ?>> Feature this project on the homepage</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Project</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('projects')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Projects';
$search = trim((string) get('q', ''));
$where  = 'deleted_at IS NULL';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, location) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

/* Map program ids → titles for the current page of rows (avoids N+1 in the loop). */
$programNames = [];
$progIds = array_filter(array_map(static fn($r) => (int) ($r['program_id'] ?? 0), $p['items']));
if ($progIds) {
    $in = implode(',', array_map('intval', array_unique($progIds)));
    foreach (db_all("SELECT id, title FROM programs WHERE id IN ($in)") as $pr) {
        $programNames[(int) $pr['id']] = $pr['title'];
    }
}

/** Map a project status to a coloured pill class. */
$statusPill = static function (string $s): string {
    return match ($s) {
        'ongoing'   => 'pill-blue',
        'completed' => 'pill-green',
        'upcoming'  => 'pill-amber',
        'paused'    => 'pill-red',
        default     => 'pill-gray',
    };
};

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Projects</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('projects?action=create')) ?>">+ Add Project</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('projects')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search projects…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Title</th><th>Program</th><th>Status</th><th>Progress</th><th>Featured</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['image']) ? upload_url($r['image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['summary'] ?? '', 12)) ?></small>
                        </td>
                        <td><?= e($programNames[(int) ($r['program_id'] ?? 0)] ?? '—') ?></td>
                        <td><span class="pill <?= e($statusPill($r['status'])) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><?= (int) $r['progress'] ?>%</td>
                        <td><?= !empty($r['is_featured']) ? lucide('star') : '—' ?></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('projects?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('projects')) ?>" data-confirm="Delete this project permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('folder') ?></div>No projects yet. <a href="<?= e(admin_url('projects?action=create')) ?>">Add your first project</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
