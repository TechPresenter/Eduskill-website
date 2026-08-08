<?php
/**
 * =============================================================================
 *  Admin — Blog Categories CRUD.
 *  list + create + edit + delete, CSRF, validation, pagination, slug
 *  generation, activity logging. Follows the standard admin module pattern
 *  (see admin/programs.php). No file uploads for this module.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'blog_categories';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['name' => 'required|max:128']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/blog-categories?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $parentId = (int) post('parent_id', 0);
    if ($parentId === $editId) { $parentId = 0; }   // a category cannot be its own parent

    $data = [
        'name'        => clean(post('name')),
        'slug'        => unique_slug($table, post('slug') ?: post('name'), $editId ?: null),
        'parent_id'   => $parentId ?: null,
        'description' => clean(post('description', '')),
        'sort_order'  => (int) post('sort_order', 0),
        'status'      => post('status') ? 1 : 0,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'blog-categories', 'Updated blog category #' . $editId);
        set_flash('success', 'Category updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'blog-categories', 'Created blog category #' . $newId);
        set_flash('success', 'Category created successfully.');
    }
    redirect('/admin/blog-categories');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'blog-categories', 'Deleted blog category #' . $delId);
        set_flash('success', 'Category deleted.');
    }
    redirect('/admin/blog-categories');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Category not found.');
        redirect('/admin/blog-categories');
    }
    $parentOpts = db_all("SELECT id, name FROM blog_categories WHERE id <> :self ORDER BY name", [':self' => (int) ($row['id'] ?? 0)]);
    $page_title = $action === 'edit' ? 'Edit Category' : 'Add Category';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Blog Categories / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('blog-categories')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('blog-categories')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" data-slug-source required value="<?= e($row['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" data-slug-target value="<?= e($row['slug'] ?? '') ?>" placeholder="auto-generated">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Parent category</label>
                <select class="form-select" name="parent_id">
                    <option value="">— None (top level) —</option>
                    <?php foreach ($parentOpts as $po): ?>
                        <option value="<?= (int) $po['id'] ?>" <?= (int) ($row['parent_id'] ?? 0) === (int) $po['id'] ? 'selected' : '' ?>><?= e($po['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint">Choose a parent to make this a sub-category.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" style="min-height:100px;" maxlength="255"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!array_key_exists('status', (array) $row) || !empty($row['status'])) ? 'checked' : '' ?>> Active (visible on the site)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Category</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('blog-categories')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Blog Categories';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', c.name, c.slug) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate(
    "SELECT c.*, p.name AS parent_name
       FROM $table c LEFT JOIN $table p ON p.id = c.parent_id
      WHERE $where ORDER BY c.sort_order ASC, c.id DESC",
    $params, 12
);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Blog Categories</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('blog-categories?action=create')) ?>">+ Add Category</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('blog-categories')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search categories…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Status</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><strong><?= e($r['name']) ?></strong><?= !empty($r['parent_name']) ? '<br><small class="text-muted">under ' . e($r['parent_name']) . '</small>' : '' ?></td>
                        <td><code><?= e($r['slug']) ?></code></td>
                        <td><small class="text-muted"><?= e(excerpt($r['description'] ?? '', 14)) ?></small></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('blog-categories?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('blog-categories')) ?>" data-confirm="Delete this category permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('folders') ?></div>No categories yet. <a href="<?= e(admin_url('blog-categories?action=create')) ?>">Add your first category</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
