<?php
/**
 * =============================================================================
 *  Admin — Gallery Albums CRUD.
 *  Full list + create + edit + delete, following the programs.php exemplar:
 *  CSRF, validation, pagination, cover-image upload (with old-file cleanup),
 *  slug generation, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'gallery_albums';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/gallery-albums?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'title'       => clean(post('title')),
        'slug'        => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'description' => clean(post('description', '')),
        'event_date'  => post('event_date') ?: null,
        'sort_order'  => (int) post('sort_order', 0),
        'status'      => post('status') ? 1 : 0,
    ];

    // Optional cover image upload (replaces + deletes the old one).
    if (!empty($_FILES['cover_image']['name'])) {
        $up = upload_image($_FILES['cover_image'], 'gallery');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/gallery-albums?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['cover_image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['cover_image'])) delete_upload($old['cover_image']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'gallery_albums', 'Updated gallery album #' . $editId);
        set_flash('success', 'Gallery album updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'gallery_albums', 'Created gallery album #' . $newId);
        set_flash('success', 'Gallery album created successfully.');
    }
    redirect('/admin/gallery-albums');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['cover_image'])) delete_upload($row['cover_image']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'gallery_albums', 'Deleted gallery album #' . $delId);
        set_flash('success', 'Gallery album deleted.');
    }
    redirect('/admin/gallery-albums');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Gallery album not found.');
        redirect('/admin/gallery-albums');
    }
    $page_title = $action === 'edit' ? 'Edit Album' : 'Add Album';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Gallery Albums / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('gallery-albums')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('gallery-albums')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" data-slug-source required maxlength="191" value="<?= e($row['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" data-slug-target maxlength="191" value="<?= e($row['slug'] ?? '') ?>" placeholder="auto-generated">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" style="min-height:140px;"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Cover Image</label>
                <input class="form-control" type="file" name="cover_image" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['cover_image']) ? upload_url($row['cover_image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['cover_image']) ? 'display:none;' : '' ?>">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Event Date</label>
                    <input class="form-control" type="date" name="event_date" value="<?= e($row['event_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                </div>
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (publish this album in the gallery)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Album</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('gallery-albums')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Gallery Albums';
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
    <div><h1>Gallery Albums</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('gallery-albums?action=create')) ?>">+ Add Album</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('gallery-albums')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search albums…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Cover</th><th>Title</th><th>Event Date</th><th>Status</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['cover_image']) ? upload_url($r['cover_image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['description'] ?? '', 12)) ?></small>
                        </td>
                        <td><?= !empty($r['event_date']) ? e(format_date($r['event_date'], 'd M Y')) : '—' ?></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('gallery-albums?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('gallery-albums')) ?>" data-confirm="Delete this album permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('image') ?></div>No gallery albums yet. <a href="<?= e(admin_url('gallery-albums?action=create')) ?>">Add your first album</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
