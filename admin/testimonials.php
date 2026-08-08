<?php
/**
 * =============================================================================
 *  Admin — Testimonials CRUD.
 *  Full list + create + edit + delete, following the programs.php exemplar:
 *  CSRF, validation, pagination, photo upload (with old-file cleanup),
 *  activity logging, flash + redirect. The testimonials table has no slug.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'testimonials';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'name'    => 'required|max:128',
        'message' => 'required',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/testimonials?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // Clamp the rating into the 1..5 range the schema expects.
    $rating = (int) post('rating', 5);
    if ($rating < 1) $rating = 1;
    if ($rating > 5) $rating = 5;

    $data = [
        'name'        => clean(post('name')),
        'designation' => clean(post('designation', '')),
        'message'     => clean(post('message')),
        'rating'      => $rating,
        'sort_order'  => (int) post('sort_order', 0),
        'status'      => post('status') ? 1 : 0,
    ];

    // Optional photo upload (replaces + deletes the old one).
    if (!empty($_FILES['photo']['name'])) {
        $up = upload_image($_FILES['photo'], 'testimonials');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/testimonials?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['photo'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['photo'])) delete_upload($old['photo']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'testimonials', 'Updated testimonial #' . $editId);
        set_flash('success', 'Testimonial updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'testimonials', 'Created testimonial #' . $newId);
        set_flash('success', 'Testimonial created successfully.');
    }
    redirect('/admin/testimonials');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['photo'])) delete_upload($row['photo']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'testimonials', 'Deleted testimonial #' . $delId);
        set_flash('success', 'Testimonial deleted.');
    }
    redirect('/admin/testimonials');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Testimonial not found.');
        redirect('/admin/testimonials');
    }
    $page_title = $action === 'edit' ? 'Edit Testimonial' : 'Add Testimonial';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Testimonials / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('testimonials')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('testimonials')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" required maxlength="128" value="<?= e($row['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Designation</label>
                    <input class="form-control" name="designation" maxlength="128" value="<?= e($row['designation'] ?? '') ?>" placeholder="e.g. Volunteer, Beneficiary">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Message <span class="req">*</span></label>
                <textarea class="form-textarea" name="message" required style="min-height:160px;"><?= e($row['message'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Rating</label>
                    <?php $curRating = (int) ($row['rating'] ?? 5); ?>
                    <select class="form-select" name="rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>" <?= ($curRating === $i) ? 'selected' : '' ?>><?= $i ?> Star<?= $i === 1 ? '' : 's' ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Photo</label>
                <input class="form-control" type="file" name="photo" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['photo']) ? upload_url($row['photo']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['photo']) ? 'display:none;' : '' ?>">
                <small class="form-hint">Optional — a square headshot works best.</small>
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (show this testimonial on the site)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Testimonial</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('testimonials')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Testimonials';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', name, designation, message) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Testimonials</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('testimonials?action=create')) ?>">+ Add Testimonial</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('testimonials')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search testimonials…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Photo</th><th>Name</th><th>Message</th><th>Rating</th><th>Status</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['photo']) ? upload_url($r['photo']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['name']) ?></strong>
                            <?php if (!empty($r['designation'])): ?><br><small class="text-muted"><?= e($r['designation']) ?></small><?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= e(excerpt($r['message'] ?? '', 14)) ?></small></td>
                        <td><?= star_rating((int) $r['rating']) ?></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('testimonials?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('testimonials')) ?>" data-confirm="Delete this testimonial permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('message-square') ?></div>No testimonials yet. <a href="<?= e(admin_url('testimonials?action=create')) ?>">Add your first testimonial</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
