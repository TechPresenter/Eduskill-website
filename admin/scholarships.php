<?php
/**
 * =============================================================================
 *  Admin — Scholarships CRUD.
 *  list + create + edit + delete, CSRF, validation, pagination,
 *  image upload (with old-file cleanup), slug generation, activity logging.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'scholarships';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/scholarships?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'title'       => clean(post('title')),
        'slug'        => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'description' => post('description', ''), // rich text allowed
        'eligibility' => clean(post('eligibility', '')),
        'amount'      => clean(post('amount', '')),
        'level'       => clean(post('level', '')),
        'deadline'    => post('deadline') ?: null,
        'sort_order'  => (int) post('sort_order', 0),
        'status'      => in_array(post('status'), ['open', 'closed'], true) ? post('status') : 'open',
    ];

    // Optional image upload (replaces + deletes the old one).
    if (!empty($_FILES['image']['name'])) {
        $up = upload_image($_FILES['image'], 'images');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/scholarships?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['image'])) delete_upload($old['image']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'scholarships', 'Updated scholarship #' . $editId);
        set_flash('success', 'Scholarship updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'scholarships', 'Created scholarship #' . $newId);
        set_flash('success', 'Scholarship created successfully.');
    }
    redirect('/admin/scholarships');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['image'])) delete_upload($row['image']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'scholarships', 'Deleted scholarship #' . $delId);
        set_flash('success', 'Scholarship deleted.');
    }
    redirect('/admin/scholarships');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Scholarship not found.');
        redirect('/admin/scholarships');
    }
    $page_title = $action === 'edit' ? 'Edit Scholarship' : 'Add Scholarship';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Scholarships / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('scholarships')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('scholarships')) ?>">
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

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" style="min-height:180px;"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Eligibility</label>
                <textarea class="form-textarea" name="eligibility" style="min-height:120px;"><?= e($row['eligibility'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Amount</label>
                    <input class="form-control" name="amount" value="<?= e($row['amount'] ?? '') ?>" placeholder="e.g. ₹50,000">
                </div>
                <div class="form-group">
                    <label class="form-label">Level</label>
                    <input class="form-control" name="level" value="<?= e($row['level'] ?? '') ?>" placeholder="e.g. Undergraduate">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Deadline</label>
                    <input class="form-control" type="date" name="deadline" value="<?= e($row['deadline'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="open"   <?= (($row['status'] ?? 'open') === 'open') ? 'selected' : '' ?>>Open</option>
                        <option value="closed" <?= (($row['status'] ?? '') === 'closed') ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Featured Image</label>
                    <input class="form-control" type="file" name="image" accept="image/*" data-preview="#imgPreview">
                </div>
            </div>

            <div class="form-group">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['image']) ? upload_url($row['image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['image']) ? 'display:none;' : '' ?>">
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Scholarship</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('scholarships')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Scholarships';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND title LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Scholarships</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('scholarships?action=create')) ?>">+ Add Scholarship</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('scholarships')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search scholarships…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Title</th><th>Amount</th><th>Level</th><th>Deadline</th><th>Status</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['image']) ? upload_url($r['image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['description'] ?? '', 12)) ?></small>
                        </td>
                        <td><?= e($r['amount'] ?? '') ?: '—' ?></td>
                        <td><?= e($r['level'] ?? '') ?: '—' ?></td>
                        <td><?= !empty($r['deadline']) ? e(date('d M Y', strtotime($r['deadline']))) : '—' ?></td>
                        <td><span class="pill <?= $r['status'] === 'open' ? 'pill-green' : 'pill-red' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('scholarships?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('scholarships')) ?>" data-confirm="Delete this scholarship permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('graduation-cap') ?></div>No scholarships yet. <a href="<?= e(admin_url('scholarships?action=create')) ?>">Add your first scholarship</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
