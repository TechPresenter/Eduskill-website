<?php
/**
 * =============================================================================
 *  Admin — Partners CRUD (partner logos / organisations).
 *  Full list + create + edit + delete, following the programs.php exemplar:
 *  CSRF, validation, pagination, logo upload (with old-file cleanup),
 *  activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'partners';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'name'        => 'required|max:191',
        'website'     => 'max:255',
        'description' => 'max:255',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/partners?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'name'        => clean(post('name')),
        'website'     => clean(post('website', '')),
        'description' => clean(post('description', '')),
        'sort_order'  => (int) post('sort_order', 0),
        'status'      => post('status') ? 1 : 0,
    ];

    // Optional logo upload (replaces + deletes the old one).
    if (!empty($_FILES['logo']['name'])) {
        $up = upload_image($_FILES['logo'], 'partners');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/partners?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['logo'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['logo'])) delete_upload($old['logo']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'partners', 'Updated partner #' . $editId);
        set_flash('success', 'Partner updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'partners', 'Created partner #' . $newId);
        set_flash('success', 'Partner created successfully.');
    }
    redirect('/admin/partners');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['logo'])) delete_upload($row['logo']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'partners', 'Deleted partner #' . $delId);
        set_flash('success', 'Partner deleted.');
    }
    redirect('/admin/partners');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Partner not found.');
        redirect('/admin/partners');
    }
    $page_title = $action === 'edit' ? 'Edit Partner' : 'Add Partner';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Partners / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('partners')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('partners')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="form-group">
                <label class="form-label">Name <span class="req">*</span></label>
                <input class="form-control" name="name" required maxlength="191" value="<?= e($row['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Website</label>
                <input class="form-control" type="url" name="website" maxlength="255" value="<?= e($row['website'] ?? '') ?>" placeholder="https://example.org">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" maxlength="255" style="min-height:100px;"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Logo</label>
                <input class="form-control" type="file" name="logo" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['logo']) ? upload_url($row['logo']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['logo']) ? 'display:none;' : '' ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (show this partner on the site)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Partner</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('partners')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Partners';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', name, website) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Partners</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('partners?action=create')) ?>">+ Add Partner</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('partners')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search partners…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Logo</th><th>Name</th><th>Website</th><th>Status</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['logo']) ? upload_url($r['logo']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['name']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['description'] ?? '', 12)) ?></small>
                        </td>
                        <td>
                            <?php if (!empty($r['website'])): ?>
                                <a href="<?= e(safe_href($r['website'])) ?>" target="_blank" rel="noopener noreferrer"><?= e($r['website']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('partners?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('partners')) ?>" data-confirm="Delete this partner permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('handshake') ?></div>No partners yet. <a href="<?= e(admin_url('partners?action=create')) ?>">Add your first partner</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
