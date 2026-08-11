<?php
/**
 * =============================================================================
 *  Admin — Gallery Media CRUD.
 *  Full list + create + edit + delete for images/videos that belong to a
 *  gallery album, following the standard admin module pattern (CSRF, validation,
 *  pagination, file upload with old-file cleanup, activity logging).
 *  Each media item MUST belong to a gallery album (FK, ON DELETE CASCADE).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'gallery_media';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/** Allowed media types (matches the ENUM in the schema). */
$types = ['image', 'video'];

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId  = (int) post('id', 0);
    $albumId = (int) post('album_id', 0);

    // Album is required and must reference an existing album (NOT NULL FK).
    if (!$albumId || !find('gallery_albums', $albumId)) {
        set_flash('error', 'Please choose a valid album.');
        redirect('/admin/gallery-media?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $title   = clean(post('title', ''));
    $caption = clean(post('caption', ''));

    $data = [
        'album_id'   => $albumId,
        'title'      => $title !== '' ? $title : null,
        'type'       => in_array(post('type'), $types, true) ? post('type') : 'image',
        'caption'    => $caption !== '' ? $caption : null,
        'sort_order' => (int) post('sort_order', 0),
    ];

    // Media file: required on create, optional on edit (replaces + deletes old).
    if (!empty($_FILES['file_path']['name'])) {
        $up = upload_image($_FILES['file_path'], 'gallery');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/gallery-media?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['file_path'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['file_path'])) delete_upload($old['file_path']);
        }
    } elseif (!$editId) {
        set_flash('error', 'A media file is required.');
        redirect('/admin/gallery-media?action=create');
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'gallery_media', 'Updated gallery media #' . $editId);
        set_flash('success', 'Media updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'gallery_media', 'Created gallery media #' . $newId);
        set_flash('success', 'Media added successfully.');
    }
    redirect('/admin/gallery-media');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['file_path'])) delete_upload($row['file_path']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'gallery_media', 'Deleted gallery media #' . $delId);
        set_flash('success', 'Media deleted.');
    }
    redirect('/admin/gallery-media');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Media not found.');
        redirect('/admin/gallery-media');
    }
    $albums = db_all("SELECT id, title FROM gallery_albums ORDER BY title ASC");
    $page_title = $action === 'edit' ? 'Edit Media' : 'Add Media';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Gallery Media / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('gallery-media')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('gallery-media')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Album <span class="req">*</span></label>
                    <select class="form-select" name="album_id" required>
                        <option value="">— Select album —</option>
                        <?php foreach ($albums as $album): ?>
                            <option value="<?= (int) $album['id'] ?>" <?= ((int) ($row['album_id'] ?? 0) === (int) $album['id']) ? 'selected' : '' ?>><?= e($album['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$albums): ?><small class="form-hint">Create a gallery album first before adding media.</small><?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type">
                        <?php foreach ($types as $t): ?>
                            <option value="<?= e($t) ?>" <?= (($row['type'] ?? 'image') === $t) ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Title</label>
                <input class="form-control" name="title" value="<?= e($row['title'] ?? '') ?>" placeholder="Optional media title">
            </div>

            <div class="form-group">
                <label class="form-label">Caption</label>
                <textarea class="form-textarea" name="caption" style="min-height:80px;" placeholder="Optional caption shown under the media."><?= e($row['caption'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Media File <?php if ($action === 'create'): ?><span class="req">*</span><?php endif; ?></label>
                <input class="form-control" type="file" name="file_path" accept="image/*" data-preview="#imgPreview" <?= $action === 'create' ? 'required' : '' ?>>
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['file_path']) ? upload_url($row['file_path']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['file_path']) ? 'display:none;' : '' ?>">
                <?php if ($action === 'edit'): ?><small class="form-hint">Leave empty to keep the current file.</small><?php endif; ?>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Add' ?> Media</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('gallery-media')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Gallery Media';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, caption) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

/* Map album ids → titles for the current page of rows (avoids N+1 in the loop). */
$albumNames = [];
$albumIds = array_filter(array_map(static fn($r) => (int) ($r['album_id'] ?? 0), $p['items']));
if ($albumIds) {
    $in = implode(',', array_map('intval', array_unique($albumIds)));
    foreach (db_all("SELECT id, title FROM gallery_albums WHERE id IN ($in)") as $al) {
        $albumNames[(int) $al['id']] = $al['title'];
    }
}

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Gallery Media</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('gallery-media?action=create')) ?>">+ Add Media</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('gallery-media')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search media…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Media</th><th>Title</th><th>Album</th><th>Type</th><th>Order</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['file_path']) ? upload_url($r['file_path']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title'] ?: '(untitled)') ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['caption'] ?? '', 12)) ?></small>
                        </td>
                        <td><?= e($albumNames[(int) ($r['album_id'] ?? 0)] ?? '—') ?></td>
                        <td><span class="pill <?= 'pill-tag' ?>"><?= e(ucfirst($r['type'])) ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('gallery-media?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('gallery-media')) ?>" data-confirm="Delete this media permanently?" class="inline-form">
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
            <div class="empty-state">
                            <div class="icon"><?= lucide('image') ?></div>
                            <?php if ($search !== ''): ?>
                                <p class="es-title">No media match &ldquo;<?= e($search) ?>&rdquo;</p>
                                <p class="es-text">No image or video has that title or caption. Clear the search to see every one.</p>
                                <div class="es-actions">
                                    <a class="btn btn-secondary" href="<?= e(admin_url('gallery-media')) ?>">Clear search</a>
                                    <a class="btn btn-primary" href="<?= e(admin_url('gallery-media?action=create')) ?>"><?= lucide('plus') ?> Add your first media</a>
                                </div>
                            <?php else: ?>
                                <p class="es-title">No media yet</p>
                                <p class="es-text">Photos and videos live inside gallery albums. Add an album first if you have not already, then upload media into it.</p>
                                <div class="es-actions">
                                    <a class="btn btn-primary" href="<?= e(admin_url('gallery-media?action=create')) ?>"><?= lucide('plus') ?> Add your first media</a>
                                </div>
                            <?php endif; ?>
                        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
