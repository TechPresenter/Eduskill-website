<?php
/**
 * =============================================================================
 *  Admin — Pages CRUD  (CMS pages editor: privacy, terms, disclaimer, etc.).
 *  list + create + edit + delete, CSRF, validation, pagination,
 *  banner-image upload (with old-file cleanup), slug generation, activity log.
 *  Follows the standard admin CRUD pattern (see admin/programs.php).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'pages';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/pages?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'title'    => clean(post('title')),
        'slug'     => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'subtitle' => clean(post('subtitle', '')),
        'content'  => post('content', ''), // rich text / HTML allowed
        'status'   => in_array(post('status'), ['draft', 'published'], true) ? post('status') : 'published',
    ];

    // Optional banner image upload (replaces + deletes the old one).
    if (!empty($_FILES['banner_image']['name'])) {
        $up = upload_image($_FILES['banner_image'], 'pages');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/pages?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['banner_image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['banner_image'])) delete_upload($old['banner_image']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'pages', 'Updated page #' . $editId);
        set_flash('success', 'Page updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'pages', 'Created page #' . $newId);
        set_flash('success', 'Page created successfully.');
    }
    redirect('/admin/pages');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['banner_image'])) delete_upload($row['banner_image']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'pages', 'Deleted page #' . $delId);
        set_flash('success', 'Page deleted.');
    }
    redirect('/admin/pages');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Page not found.');
        redirect('/admin/pages');
    }
    $page_title = $action === 'edit' ? 'Edit Page' : 'Add Page';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Pages / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('pages')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('pages')) ?>">
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
                <label class="form-label">Subtitle</label>
                <input class="form-control" name="subtitle" value="<?= e($row['subtitle'] ?? '') ?>" placeholder="Optional short tagline shown under the title">
            </div>

            <div class="form-group">
                <label class="form-label">Content</label>
                <textarea class="form-textarea" name="content" data-wysiwyg style="min-height:320px;"><?= e($row['content'] ?? '') ?></textarea>
                <small class="form-hint">HTML is allowed. This is the body of the page (privacy policy, terms, etc.).</small>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="published" <?= (($row['status'] ?? 'published') === 'published') ? 'selected' : '' ?>>Published</option>
                        <option value="draft"     <?= (($row['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Banner Image</label>
                    <input class="form-control" type="file" name="banner_image" accept="image/*" data-preview="#imgPreview">
                    <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['banner_image']) ? upload_url($row['banner_image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['banner_image']) ? 'display:none;' : '' ?>">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Page</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('pages')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Pages';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, slug) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Pages</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('pages?action=create')) ?>">+ Add Page</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('pages')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search pages…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Banner</th><th>Title</th><th>Slug</th><th>Status</th><th>Updated</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['banner_image']) ? upload_url($r['banner_image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['subtitle'] ?? '', 12)) ?></small>
                        </td>
                        <td><code><?= e($r['slug']) ?></code></td>
                        <td><span class="pill <?= $r['status'] === 'published' ? 'pill-green' : 'pill-gray' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><small class="text-muted"><?= e(format_date($r['updated_at'] ?? $r['created_at'], 'd M Y')) ?></small></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('pages?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('pages')) ?>" data-confirm="Delete this page permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('file-text') ?></div>No pages yet. <a href="<?= e(admin_url('pages?action=create')) ?>">Add your first page</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
