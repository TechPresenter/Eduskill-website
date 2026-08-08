<?php
/**
 * =============================================================================
 *  Admin — Membership Plans CRUD.
 *  list + create + edit + delete, CSRF, validation, pagination, slug
 *  generation, activity logging. No file uploads for this module.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'membership_plans';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['name' => 'required|max:96']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/membership-plans?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'name'          => clean(post('name')),
        'slug'          => unique_slug($table, post('slug') ?: post('name'), $editId ?: null),
        'price'         => (float) post('price', 0),
        'duration'      => clean(post('duration', '')) ?: 'Annual',
        'duration_days' => max(1, (int) post('duration_days', 365)),
        'tier_level'    => max(1, (int) post('tier_level', 1)),
        'color'         => preg_match('/^#[0-9a-fA-F]{6}$/', (string) post('color')) ? post('color') : null,
        'benefits'      => trim((string) post('benefits', '')), // one benefit per line
        'is_featured'   => post('is_featured') ? 1 : 0,
        'sort_order'    => (int) post('sort_order', 0),
        'status'        => post('status') ? 1 : 0,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'membership-plans', 'Updated membership plan #' . $editId);
        set_flash('success', 'Membership plan updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'membership-plans', 'Created membership plan #' . $newId);
        set_flash('success', 'Membership plan created successfully.');
    }
    redirect('/admin/membership-plans');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'membership-plans', 'Deleted membership plan #' . $delId);
        set_flash('success', 'Membership plan deleted.');
    }
    redirect('/admin/membership-plans');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Membership plan not found.');
        redirect('/admin/membership-plans');
    }
    $page_title = $action === 'edit' ? 'Edit Membership Plan' : 'Add Membership Plan';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Membership Plans / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('membership-plans')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('membership-plans')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Plan Name <span class="req">*</span></label>
                    <input class="form-control" name="name" data-slug-source required value="<?= e($row['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" data-slug-target value="<?= e($row['slug'] ?? '') ?>" placeholder="auto-generated">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Price (₹)</label>
                    <input class="form-control" type="number" name="price" step="0.01" min="0" value="<?= e($row['price'] ?? '0.00') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Duration label</label>
                    <input class="form-control" name="duration" value="<?= e($row['duration'] ?? 'Annual') ?>" placeholder="e.g. Annual">
                </div>
            </div>

            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Duration (days)</label>
                    <input class="form-control" type="number" name="duration_days" min="1" value="<?= (int) ($row['duration_days'] ?? 365) ?>">
                    <small class="form-hint">Used to compute the expiry date on activation/renewal.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Tier level</label>
                    <input class="form-control" type="number" name="tier_level" min="1" value="<?= (int) ($row['tier_level'] ?? 1) ?>">
                    <small class="form-hint">Higher = better tier (Basic 1 → Platinum 4).</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Card accent colour</label>
                    <input class="form-control" type="color" name="color" value="<?= e($row['color'] ?? '#063566') ?>">
                    <small class="form-hint">Shown on the membership card & tier badge.</small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Benefits</label>
                <textarea class="form-textarea" name="benefits" style="min-height:140px;" placeholder="One benefit per line"><?= e($row['benefits'] ?? '') ?></textarea>
                <small class="form-hint">Enter one benefit per line.</small>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (visible on the site)</label>
                </div>
            </div>

            <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= !empty($row['is_featured']) ? 'checked' : '' ?>> Feature this plan (highlight as recommended)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Plan</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('membership-plans')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Membership Plans';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND name LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Membership Plans</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('membership-plans?action=create')) ?>">+ Add Plan</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('membership-plans')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search plans…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Price</th><th>Duration</th><th>Status</th><th>Featured</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><strong><?= e($r['name']) ?></strong></td>
                        <td><?= e(money($r['price'], '₹', 2)) ?></td>
                        <td><?= e($r['duration']) ?></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= !empty($r['is_featured']) ? lucide('star') : '—' ?></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('membership-plans?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('membership-plans')) ?>" data-confirm="Delete this membership plan permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('id-card') ?></div>No membership plans yet. <a href="<?= e(admin_url('membership-plans?action=create')) ?>">Add your first plan</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
