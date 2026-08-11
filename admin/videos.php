<?php
/**
 * =============================================================================
 *  Admin — Videos CRUD (video gallery / embeds).
 *  Full list + create + edit + delete, following the programs.php exemplar:
 *  CSRF, validation, slug generation, pagination, thumbnail upload (with
 *  old-file cleanup), activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'videos';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'title'     => 'required|max:191',
        'video_url' => 'max:255',
        'category'  => 'max:64',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/videos?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // Accept either a full YouTube URL or a bare 11-char id; normalise to the id
    // so the value always fits the youtube_id column and stays canonical.
    $ytRaw = clean(post('youtube_id', ''));
    $ytId  = '';
    if ($ytRaw !== '') {
        $ytId = youtube_id($ytRaw);
        if ($ytId === null) {
            set_flash('error', 'Enter a valid YouTube URL or 11-character video ID.');
            redirect('/admin/videos?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
    }

    $data = [
        'title'       => clean(post('title')),
        'slug'        => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'description' => clean(post('description', '')),
        'youtube_id'  => $ytId,
        'video_url'   => clean(post('video_url', '')),
        'category'    => clean(post('category', '')),
        'sort_order'  => (int) post('sort_order', 0),
        'status'      => post('status') ? 1 : 0,
    ];

    // Optional thumbnail upload (replaces + deletes the old one).
    if (!empty($_FILES['thumbnail']['name'])) {
        $up = upload_image($_FILES['thumbnail'], 'videos');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/videos?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['thumbnail'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['thumbnail'])) delete_upload($old['thumbnail']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'videos', 'Updated video #' . $editId);
        set_flash('success', 'Video updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'videos', 'Created video #' . $newId);
        set_flash('success', 'Video created successfully.');
    }
    redirect('/admin/videos');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['thumbnail'])) delete_upload($row['thumbnail']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'videos', 'Deleted video #' . $delId);
        set_flash('success', 'Video deleted.');
    }
    redirect('/admin/videos');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Video not found.');
        redirect('/admin/videos');
    }
    $page_title = $action === 'edit' ? 'Edit Video' : 'Add Video';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Videos / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('videos')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('videos')) ?>">
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

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">YouTube ID or URL</label>
                    <input class="form-control" name="youtube_id" maxlength="255" value="<?= e($row['youtube_id'] ?? '') ?>" placeholder="dQw4w9WgXcQ or https://youtu.be/…">
                    <small class="form-hint">Paste a full YouTube link or just the 11-character video ID.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Video URL</label>
                    <input class="form-control" name="video_url" maxlength="255" value="<?= e($row['video_url'] ?? '') ?>" placeholder="https://… (external / self-hosted)">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input class="form-control" name="category" maxlength="64" value="<?= e($row['category'] ?? '') ?>" placeholder="e.g. Events, Interviews">
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Thumbnail</label>
                <input class="form-control" type="file" name="thumbnail" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['thumbnail']) ? upload_url($row['thumbnail']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['thumbnail']) ? 'display:none;' : '' ?>">
                <small class="form-hint">Optional — if left blank, the YouTube thumbnail is used automatically.</small>
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (show this video on the site)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Video</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('videos')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Videos';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, category) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Videos</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('videos?action=create')) ?>">+ Add Video</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('videos')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search videos…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Thumb</th><th>Title</th><th>Category</th><th>Status</th><th>Order</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <?php
                    $thumbSrc = !empty($r['thumbnail'])
                        ? upload_url($r['thumbnail'])
                        : (!empty($r['youtube_id'])
                            ? 'https://img.youtube.com/vi/' . rawurlencode($r['youtube_id']) . '/mqdefault.jpg'
                            : asset('images/placeholder.svg'));
                    ?>
                    <tr>
                        <td><img class="thumb" src="<?= e($thumbSrc) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['description'] ?? '', 12)) ?></small>
                        </td>
                        <td><?= !empty($r['category']) ? '<span class="pill pill-tag">' . e($r['category']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('videos?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('videos')) ?>" data-confirm="Delete this video permanently?" class="inline-form">
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
                            <div class="icon"><?= lucide('clapperboard') ?></div>
                            <?php if ($search !== ''): ?>
                                <p class="es-title">No videos match &ldquo;<?= e($search) ?>&rdquo;</p>
                                <p class="es-text">No video has that title. Clear the search to see every one.</p>
                                <div class="es-actions">
                                    <a class="btn btn-secondary" href="<?= e(admin_url('videos')) ?>">Clear search</a>
                                    <a class="btn btn-primary" href="<?= e(admin_url('videos?action=create')) ?>"><?= lucide('plus') ?> Add your first video</a>
                                </div>
                            <?php else: ?>
                                <p class="es-title">No videos yet</p>
                                <p class="es-text">Videos are embedded by URL — YouTube or Vimeo — and shown on the public video gallery.</p>
                                <div class="es-actions">
                                    <a class="btn btn-primary" href="<?= e(admin_url('videos?action=create')) ?>"><?= lucide('plus') ?> Add your first video</a>
                                </div>
                            <?php endif; ?>
                        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
