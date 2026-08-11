<?php
/**
 * =============================================================================
 *  Admin — Documents / Downloads CRUD.
 *  Full list + create + edit + delete for downloadable resources.
 *  Follows the standard admin module pattern (see admin/programs.php):
 *    CSRF, validation, pagination, document upload (with old-file cleanup),
 *    slug generation, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'documents';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/documents?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // A file is mandatory when creating a new document.
    if (!$editId && empty($_FILES['file']['name'])) {
        set_flash('error', 'Please choose a file to upload.');
        redirect('/admin/documents?action=create');
    }

    $data = [
        'title'       => clean(post('title')),
        'slug'        => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'description' => clean(post('description', '')),
        'category'    => clean(post('category', '')) ?: 'general',
        'status'      => post('status') ? 1 : 0,
    ];

    // Optional file upload (replaces + deletes the old one). Required on create (checked above).
    if (!empty($_FILES['file']['name'])) {
        $up = upload_document($_FILES['file'], 'documents');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/documents?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['file_path'] = $up['path'];
        $data['file_type'] = $up['ext'];
        $data['file_size'] = (int) $up['size'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['file_path'])) delete_upload($old['file_path']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'documents', 'Updated document #' . $editId);
        set_flash('success', 'Document updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'documents', 'Created document #' . $newId);
        set_flash('success', 'Document created successfully.');
    }
    redirect('/admin/documents');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['file_path'])) delete_upload($row['file_path']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'documents', 'Deleted document #' . $delId);
        set_flash('success', 'Document deleted.');
    }
    redirect('/admin/documents');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Document not found.');
        redirect('/admin/documents');
    }
    $page_title = $action === 'edit' ? 'Edit Document' : 'Add Document';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= lucide('file-text') ?> <?= e($page_title) ?></h1><span class="muted">Documents / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('documents')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2 class="panel-title"><?= lucide('file-up') ?> Document details</h2>
            <?php if ($action === 'edit'): ?>
                <span class="pill <?= !empty($row['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($row['status']) ? 'Active' : 'Inactive' ?></span>
            <?php endif; ?>
        </div>
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('documents')) ?>">
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
                <textarea class="form-textarea" name="description" rows="4"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input class="form-control" name="category" value="<?= e($row['category'] ?? 'general') ?>" placeholder="general">
                </div>
                <div class="form-group">
                    <label class="form-label">File <?= $action === 'edit' ? '' : '<span class="req">*</span>' ?></label>
                    <input class="form-control" type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" <?= $action === 'edit' ? '' : 'required' ?>>
                    <?php if (!empty($row['file_path'])): ?>
                        <small class="form-hint">
                            Current:
                            <a href="<?= e(secure_upload_url($row['file_path'])) ?>" target="_blank" rel="noopener"><?= e(basename($row['file_path'])) ?></a>
                            <?= e(strtoupper((string) ($row['file_type'] ?? ''))) ?> · <?= e(human_filesize((int) ($row['file_size'] ?? 0))) ?>
                            — choose a new file to replace it.
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (available for download)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Document</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('documents')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Documents / Downloads';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, category) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1><?= lucide('file-text') ?> Documents / Downloads</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('documents?action=create')) ?>"><?= lucide('plus') ?> Add Document</a>
</div>

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title"><?= lucide('folder-down') ?> Downloadable files</h2>
        <span class="pill pill-tag"><?= (int) $p['total'] ?> files</span>
    </div>
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('documents')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search documents…">
            </form>
            <?php if ($search !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('documents')) ?>">Clear search</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Title</th><th>Category</th><th>Type</th><th>Size</th><th>Downloads</th><th>Status</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['description'] ?? '', 12)) ?></small>
                        </td>
                        <td><span class="pill pill-tag"><?= e($r['category']) ?></span></td>
                        <td><?= !empty($r['file_type']) ? '<span class="pill pill-tag">' . e(strtoupper((string) $r['file_type'])) . '</span>' : '—' ?></td>
                        <td><?= e(human_filesize((int) ($r['file_size'] ?? 0))) ?></td>
                        <td><?= (int) $r['downloads'] ?></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <div class="actions">
                                <?php if (!empty($r['file_path'])): ?>
                                    <a class="icon-btn" href="<?= e(secure_upload_url($r['file_path'])) ?>" target="_blank" rel="noopener" title="Download"><?= lucide('download') ?></a>
                                <?php endif; ?>
                                <a class="icon-btn" href="<?= e(admin_url('documents?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pen-line') ?></a>
                                <form method="post" action="<?= e(admin_url('documents')) ?>" data-confirm="Delete this document permanently?">
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
            <?php /* Shared .empty-state shape — swap for the admin_ui.php helper once it ships. */ ?>
            <div class="empty-state">
                <div class="icon"><?= lucide('file-text') ?></div>
                <?php if ($search !== ''): ?>
                    <p class="es-title">No documents match &ldquo;<?= e($search) ?>&rdquo;</p>
                    <p class="es-text">Nothing here has that title or category. Clear the search to see every file.</p>
                    <div class="es-actions">
                        <a class="btn btn-secondary" href="<?= e(admin_url('documents')) ?>">Clear search</a>
                        <a class="btn btn-primary" href="<?= e(admin_url('documents?action=create')) ?>"><?= lucide('plus') ?> Add Document</a>
                    </div>
                <?php else: ?>
                    <p class="es-title">No documents yet</p>
                    <p class="es-text">Upload the PDFs, forms and reports you want visitors to be able to download. Each one gets a slug, a category and a download counter.</p>
                    <div class="es-actions">
                        <a class="btn btn-primary" href="<?= e(admin_url('documents?action=create')) ?>"><?= lucide('plus') ?> Add your first document</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
