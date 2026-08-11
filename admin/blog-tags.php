<?php
/**
 * =============================================================================
 *  Admin — Blog Tags CRUD.
 *  list + create + edit + delete, CSRF, validation, pagination, slug
 *  generation, activity logging. Follows the standard admin module pattern
 *  (see admin/programs.php). Minimal table: name + slug, no file uploads.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'blog_tags';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['name' => 'required|max:96']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/blog-tags?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'name' => clean(post('name')),
        'slug' => unique_slug($table, post('slug') ?: post('name'), $editId ?: null),
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'blog-tags', 'Updated blog tag #' . $editId);
        set_flash('success', 'Tag updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'blog-tags', 'Created blog tag #' . $newId);
        set_flash('success', 'Tag created successfully.');
    }
    redirect('/admin/blog-tags');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'blog-tags', 'Deleted blog tag #' . $delId);
        set_flash('success', 'Tag deleted.');
    }
    redirect('/admin/blog-tags');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Tag not found.');
        redirect('/admin/blog-tags');
    }
    $page_title = $action === 'edit' ? 'Edit Tag' : 'Add Tag';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Blog Tags / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('blog-tags')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('blog-tags')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" data-slug-source required maxlength="96" value="<?= e($row['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" data-slug-target maxlength="96" value="<?= e($row['slug'] ?? '') ?>" placeholder="auto-generated">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Tag</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('blog-tags')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Blog Tags';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', t.name, t.slug) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate(
    "SELECT t.*, (SELECT COUNT(*) FROM blog_tag_map m WHERE m.tag_id = t.id) AS post_count
     FROM $table t WHERE $where ORDER BY t.id DESC",
    $params,
    15
);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Blog Tags</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('blog-tags?action=create')) ?>">+ Add Tag</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('blog-tags')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search tags…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Slug</th><th>Posts</th><th>Created</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><strong><?= e($r['name']) ?></strong></td>
                        <td><code><?= e($r['slug']) ?></code></td>
                        <td><span class="pill <?= 'pill-tag' ?>"><?= (int) $r['post_count'] ?></span></td>
                        <td><small class="text-muted"><?= e(format_date($r['created_at'] ?? '')) ?></small></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('blog-tags?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('blog-tags')) ?>" data-confirm="Delete this tag permanently?" class="inline-form">
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
                            <div class="icon"><?= lucide('tag') ?></div>
                            <?php if ($search !== ''): ?>
                                <p class="es-title">No tags match &ldquo;<?= e($search) ?>&rdquo;</p>
                                <p class="es-text">No tag has that name or slug. Clear the search to see every one.</p>
                                <div class="es-actions">
                                    <a class="btn btn-secondary" href="<?= e(admin_url('blog-tags')) ?>">Clear search</a>
                                    <a class="btn btn-primary" href="<?= e(admin_url('blog-tags?action=create')) ?>"><?= lucide('plus') ?> Add your first tag</a>
                                </div>
                            <?php else: ?>
                                <p class="es-title">No tags yet</p>
                                <p class="es-text">Tags cut across categories — a post can carry several. They power the related-posts list and the tag archive pages.</p>
                                <div class="es-actions">
                                    <a class="btn btn-primary" href="<?= e(admin_url('blog-tags?action=create')) ?>"><?= lucide('plus') ?> Add your first tag</a>
                                </div>
                            <?php endif; ?>
                        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
