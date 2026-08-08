<?php
/**
 * =============================================================================
 *  Admin — Schemes & Initiatives CRUD.
 *  Standard admin module: list + create + edit + delete, CSRF, validation,
 *  pagination, image upload (with old-file cleanup), slug generation,
 *  activity logging. Mirrors admin/programs.php.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'schemes';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/schemes?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $deadline = trim((string) post('deadline', ''));

    $data = [
        'title'              => clean(post('title')),
        'slug'               => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'category'           => clean(post('category', '')),
        'department'         => clean(post('department', '')),
        'short_description'  => clean(post('short_description', '')),
        'description'        => post('description', ''), // rich text allowed
        'eligibility'        => clean(post('eligibility', '')),
        'benefits'           => clean(post('benefits', '')),
        'documents_required' => clean(post('documents_required', '')),
        'apply_url'          => clean(post('apply_url', '')),
        'deadline'           => $deadline !== '' ? $deadline : null,
        'is_featured'        => post('is_featured') ? 1 : 0,
        'sort_order'         => (int) post('sort_order', 0),
        'status'             => in_array(post('status'), ['active', 'closed'], true) ? post('status') : 'active',
    ];

    // Optional image upload (replaces + deletes the old one).
    if (!empty($_FILES['image']['name'])) {
        $up = upload_image($_FILES['image'], 'images');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/schemes?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['image'])) delete_upload($old['image']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'schemes', 'Updated scheme #' . $editId);
        set_flash('success', 'Scheme updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'schemes', 'Created scheme #' . $newId);
        set_flash('success', 'Scheme created successfully.');
    }
    redirect('/admin/schemes');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['image'])) delete_upload($row['image']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'schemes', 'Deleted scheme #' . $delId);
        set_flash('success', 'Scheme deleted.');
    }
    redirect('/admin/schemes');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Scheme not found.');
        redirect('/admin/schemes');
    }
    $page_title = $action === 'edit' ? 'Edit Scheme' : 'Add Scheme';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Schemes &amp; Initiatives / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('schemes')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('schemes')) ?>">
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
                    <label class="form-label">Category</label>
                    <input class="form-control" name="category" value="<?= e($row['category'] ?? '') ?>" placeholder="e.g. Education, Health, Skill">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input class="form-control" name="department" value="<?= e($row['department'] ?? '') ?>" placeholder="e.g. State Government">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Short Description</label>
                <textarea class="form-textarea" name="short_description" style="min-height:80px;" maxlength="500"><?= e($row['short_description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Full Description</label>
                <textarea class="form-textarea" name="description" style="min-height:180px;"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Eligibility</label>
                    <textarea class="form-textarea" name="eligibility" style="min-height:120px;" placeholder="Who can apply…"><?= e($row['eligibility'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Benefits</label>
                    <textarea class="form-textarea" name="benefits" style="min-height:120px;" placeholder="What applicants get…"><?= e($row['benefits'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Documents Required</label>
                <textarea class="form-textarea" name="documents_required" style="min-height:100px;" placeholder="One document per line…"><?= e($row['documents_required'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Apply URL</label>
                    <input class="form-control" type="url" name="apply_url" value="<?= e($row['apply_url'] ?? '') ?>" placeholder="https://…">
                </div>
                <div class="form-group">
                    <label class="form-label">Deadline</label>
                    <input class="form-control" type="date" name="deadline" value="<?= e($row['deadline'] ?? '') ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?= (($row['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="closed" <?= (($row['status'] ?? '') === 'closed') ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Featured Image</label>
                <input class="form-control" type="file" name="image" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['image']) ? upload_url($row['image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['image']) ? 'display:none;' : '' ?>">
            </div>

            <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= !empty($row['is_featured']) ? 'checked' : '' ?>> Feature this scheme</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Scheme</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('schemes')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Schemes & Initiatives';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, category, department) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Schemes &amp; Initiatives</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('schemes?action=create')) ?>">+ Add Scheme</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('schemes')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search schemes…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Title</th><th>Category</th><th>Deadline</th><th>Status</th><th>Featured</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['image']) ? upload_url($r['image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['short_description'] ?? '', 12)) ?></small>
                        </td>
                        <td><?= !empty($r['category']) ? e($r['category']) : '—' ?></td>
                        <td><?= !empty($r['deadline']) ? e(format_date($r['deadline'], 'd M Y')) : '—' ?></td>
                        <td><span class="pill <?= $r['status'] === 'active' ? 'pill-green' : 'pill-red' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><?= !empty($r['is_featured']) ? lucide('star') : '—' ?></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('schemes?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('schemes')) ?>" data-confirm="Delete this scheme permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('clipboard-list') ?></div>No schemes yet. <a href="<?= e(admin_url('schemes?action=create')) ?>">Add your first scheme</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
