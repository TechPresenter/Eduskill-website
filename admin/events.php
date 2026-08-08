<?php
/**
 * =============================================================================
 *  Admin — Events CRUD.
 *  List + create + edit + delete, following the standard admin module pattern
 *  (see admin/programs.php): CSRF, validation, pagination, image upload with
 *  old-file cleanup, slug generation, datetime-local normalisation, activity
 *  logging.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'events';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'title'          => 'required|max:191',
        'start_datetime' => 'required',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/events?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // Normalise datetime-local values (YYYY-MM-DDTHH:MM) into DATETIME strings.
    $startRaw = trim((string) post('start_datetime', ''));
    $startAt  = null;
    if ($startRaw !== '') {
        $ts = strtotime($startRaw);
        if ($ts !== false) $startAt = date('Y-m-d H:i:s', $ts);
    }
    if ($startAt === null) {
        set_flash('error', 'A valid start date & time is required.');
        redirect('/admin/events?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $endRaw = trim((string) post('end_datetime', ''));
    $endAt  = null;
    if ($endRaw !== '') {
        $ts = strtotime($endRaw);
        if ($ts !== false) $endAt = date('Y-m-d H:i:s', $ts);
    }
    if ($endAt !== null && $endAt < $startAt) {
        set_flash('error', 'The end date & time cannot be before the start.');
        redirect('/admin/events?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $capacityRaw = trim((string) post('capacity', ''));

    $data = [
        'title'                 => clean(post('title')),
        'slug'                  => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'excerpt'               => clean(post('excerpt', '')),
        'description'           => post('description', ''), // rich text allowed
        'location'              => clean(post('location', '')),
        'venue'                 => clean(post('venue', '')),
        'start_datetime'        => $startAt,
        'end_datetime'          => $endAt,
        'capacity'              => $capacityRaw === '' ? null : (int) $capacityRaw,
        'registration_required' => post('registration_required') ? 1 : 0,
        'is_featured'           => post('is_featured') ? 1 : 0,
        'status'                => in_array(post('status'), ['draft', 'published', 'cancelled'], true) ? post('status') : 'published',
    ];

    // Optional image upload (replaces + deletes the old one).
    if (!empty($_FILES['image']['name'])) {
        $up = upload_image($_FILES['image'], 'events');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/events?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['image'])) delete_upload($old['image']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'events', 'Updated event #' . $editId);
        set_flash('success', 'Event updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'events', 'Created event #' . $newId);
        set_flash('success', 'Event created successfully.');
    }
    redirect('/admin/events');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['image'])) delete_upload($row['image']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'events', 'Deleted event #' . $delId);
        set_flash('success', 'Event deleted.');
    }
    redirect('/admin/events');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Event not found.');
        redirect('/admin/events');
    }

    // datetime-local values for the inputs.
    $startValue = '';
    if (!empty($row['start_datetime'])) {
        $ts = strtotime((string) $row['start_datetime']);
        if ($ts !== false) $startValue = date('Y-m-d\TH:i', $ts);
    }
    $endValue = '';
    if (!empty($row['end_datetime'])) {
        $ts = strtotime((string) $row['end_datetime']);
        if ($ts !== false) $endValue = date('Y-m-d\TH:i', $ts);
    }

    $page_title = $action === 'edit' ? 'Edit Event' : 'Add Event';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Events / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('events')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('events')) ?>">
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
                <label class="form-label">Excerpt</label>
                <textarea class="form-textarea" name="excerpt" style="min-height:80px;" maxlength="500"><?= e($row['excerpt'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" data-wysiwyg style="min-height:200px;"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Start Date &amp; Time <span class="req">*</span></label>
                    <input class="form-control" type="datetime-local" name="start_datetime" required value="<?= e($startValue) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date &amp; Time</label>
                    <input class="form-control" type="datetime-local" name="end_datetime" value="<?= e($endValue) ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input class="form-control" name="location" value="<?= e($row['location'] ?? '') ?>" placeholder="City / area">
                </div>
                <div class="form-group">
                    <label class="form-label">Venue</label>
                    <input class="form-control" name="venue" value="<?= e($row['venue'] ?? '') ?>" placeholder="Building / hall">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Capacity</label>
                    <input class="form-control" type="number" name="capacity" min="0" value="<?= e($row['capacity'] ?? '') ?>" placeholder="Leave blank for unlimited">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="published" <?= (($row['status'] ?? 'published') === 'published') ? 'selected' : '' ?>>Published</option>
                        <option value="draft"     <?= (($row['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="cancelled" <?= (($row['status'] ?? '') === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Featured Image</label>
                <input class="form-control" type="file" name="image" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['image']) ? upload_url($row['image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['image']) ? 'display:none;' : '' ?>">
            </div>

            <label class="checkbox"><input type="checkbox" name="registration_required" value="1" <?= !empty($row['registration_required']) ? 'checked' : '' ?>> Require registration for this event</label>
            <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= !empty($row['is_featured']) ? 'checked' : '' ?>> Feature this event on the homepage</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Event</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('events')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Events';
$search = trim((string) get('q', ''));
$where  = 'deleted_at IS NULL';
$params = [];
if ($search !== '') {
    $where .= ' AND title LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY start_datetime DESC, id DESC", $params, 12);

$statusPill = ['published' => 'pill-green', 'draft' => 'pill-amber', 'cancelled' => 'pill-red'];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Events</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('events?action=create')) ?>">+ Add Event</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('events')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search events…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Title</th><th>When</th><th>Registration</th><th>Status</th><th>Featured</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['image']) ? upload_url($r['image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['excerpt'] ?? '', 12)) ?><?php if (!empty($r['location'])): ?> · <?= lucide('map-pin') ?> <?= e($r['location']) ?><?php endif; ?></small>
                        </td>
                        <td>
                            <?= e(format_datetime($r['start_datetime'])) ?>
                            <?php if (!empty($r['end_datetime'])): ?><br><small class="text-muted">→ <?= e(format_datetime($r['end_datetime'])) ?></small><?php endif; ?>
                        </td>
                        <td><?= !empty($r['registration_required']) ? '<span class="pill pill-blue">Required</span>' : '<span class="pill pill-gray">Open</span>' ?></td>
                        <td><span class="pill <?= $statusPill[$r['status']] ?? 'pill-gray' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><?= !empty($r['is_featured']) ? lucide('star') : '—' ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('events?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('events')) ?>" data-confirm="Delete this event permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('calendar-days') ?></div>No events yet. <a href="<?= e(admin_url('events?action=create')) ?>">Add your first event</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
