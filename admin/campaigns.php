<?php
/**
 * =============================================================================
 *  Admin — Campaigns CRUD  (fundraising campaigns).
 *  list + create + edit + delete, CSRF, validation, pagination,
 *  image upload (with old-file cleanup), slug generation, activity logging.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'campaigns';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$statuses = ['active', 'completed', 'paused', 'draft'];

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/campaigns?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // Milestone markers: comma-separated amounts → JSON array.
    $milestoneAmts = array_values(array_filter(
        array_map(static fn($v) => round((float) trim($v), 2), explode(',', (string) post('milestones', ''))),
        static fn($v) => $v > 0
    ));

    $data = [
        'title'             => clean(post('title')),
        'slug'              => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'short_description' => clean(post('short_description', '')),
        'category'          => clean(post('category', '')) ?: null,
        'location'          => clean(post('location', '')) ?: null,
        'description'       => post('description', ''), // rich text allowed
        'goal_amount'       => round((float) post('goal_amount', 0), 2),
        'raised_amount'     => round((float) post('raised_amount', 0), 2),
        'milestones'        => $milestoneAmts ? json_encode($milestoneAmts) : null,
        'start_date'        => post('start_date') ?: null,
        'end_date'          => post('end_date') ?: null,
        'is_featured'       => post('is_featured') ? 1 : 0,
        'status'            => in_array(post('status'), $statuses, true) ? post('status') : 'active',
    ];

    // Optional image upload (replaces + deletes the old one).
    if (!empty($_FILES['image']['name'])) {
        $up = upload_image($_FILES['image'], 'campaigns');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/campaigns?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['image'])) delete_upload($old['image']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'campaigns', 'Updated campaign #' . $editId);
        set_flash('success', 'Campaign updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'campaigns', 'Created campaign #' . $newId);
        set_flash('success', 'Campaign created successfully.');
    }
    redirect('/admin/campaigns');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['image'])) delete_upload($row['image']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'campaigns', 'Deleted campaign #' . $delId);
        set_flash('success', 'Campaign deleted.');
    }
    redirect('/admin/campaigns');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Campaign not found.');
        redirect('/admin/campaigns');
    }
    $page_title = $action === 'edit' ? 'Edit Campaign' : 'Add Campaign';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Campaigns / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('campaigns')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('campaigns')) ?>">
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
                <label class="form-label">Short Description</label>
                <textarea class="form-textarea" name="short_description" style="min-height:80px;"><?= e($row['short_description'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group"><label class="form-label">Category</label>
                    <input class="form-control" name="category" value="<?= e($row['category'] ?? '') ?>" placeholder="e.g. Education, Health"></div>
                <div class="form-group"><label class="form-label">Location</label>
                    <input class="form-control" name="location" value="<?= e($row['location'] ?? '') ?>"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Milestone markers (₹, comma-separated)</label>
                <input class="form-control" name="milestones" value="<?= e(implode(', ', json_column($row['milestones'] ?? null))) ?>" placeholder="e.g. 50000, 100000, 250000">
                <small class="form-hint">Shown as markers on the campaign progress bar.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Full Description</label>
                <textarea class="form-textarea" name="description" data-wysiwyg style="min-height:180px;"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Goal Amount (₹)</label>
                    <input class="form-control" type="number" name="goal_amount" min="0" step="0.01" value="<?= e($row['goal_amount'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Raised Amount (₹)</label>
                    <input class="form-control" type="number" name="raised_amount" min="0" step="0.01" value="<?= e($row['raised_amount'] ?? '0') ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input class="form-control" type="date" name="start_date" value="<?= e($row['start_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input class="form-control" type="date" name="end_date" value="<?= e($row['end_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <?php $cur = $row['status'] ?? 'active'; foreach ($statuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $cur === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Cover Image</label>
                <input class="form-control" type="file" name="image" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['image']) ? upload_url($row['image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['image']) ? 'display:none;' : '' ?>">
            </div>

            <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= !empty($row['is_featured']) ? 'checked' : '' ?>> Feature this campaign on the homepage</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Campaign</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('campaigns')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Campaigns';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND title LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY is_featured DESC, id DESC", $params, 12);

$statusPill = [
    'active'    => 'pill-green',
    'completed' => 'pill-blue',
    'paused'    => 'pill-amber',
    'draft'     => 'pill-gray',
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Campaigns</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('campaigns?action=create')) ?>">+ Add Campaign</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('campaigns')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search campaigns…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Title</th><th>Progress</th><th>Status</th><th>Featured</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <?php
                        $goal   = (float) ($r['goal_amount'] ?? 0);
                        $raised = (float) ($r['raised_amount'] ?? 0);
                        $pct    = $goal > 0 ? min(100, round($raised / $goal * 100)) : 0;
                    ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['image']) ? upload_url($r['image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['short_description'] ?? '', 12)) ?></small>
                        </td>
                        <td style="min-width:180px;">
                            <div class="progress" style="height:8px;background:var(--surface-2,#e5e7eb);border-radius:999px;overflow:hidden;">
                                <div class="progress-bar" style="width:<?= (int) $pct ?>%;height:100%;background:#58A42F;"></div>
                            </div>
                            <small class="text-muted"><?= e(money($raised)) ?> / <?= e(money($goal)) ?> (<?= (int) $pct ?>%)</small>
                        </td>
                        <td><span class="pill <?= e($statusPill[$r['status']] ?? 'pill-gray') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><?= !empty($r['is_featured']) ? lucide('star') : '—' ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('campaign-reports?campaign=' . $r['id'])) ?>" title="Reports"><?= lucide('chart-column') ?></a>
                                <a class="icon-btn" href="<?= e(admin_url('campaign-updates?campaign=' . $r['id'])) ?>" title="Updates"><?= lucide('megaphone') ?></a>
                                <a class="icon-btn" href="<?= e(admin_url('campaign-gallery?campaign=' . $r['id'])) ?>" title="Gallery"><?= lucide('images') ?></a>
                                <a class="icon-btn" href="<?= e(admin_url('campaign-volunteers?campaign=' . $r['id'])) ?>" title="Volunteers"><?= lucide('users') ?></a>
                                <a class="icon-btn" href="<?= e(admin_url('campaigns?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('campaigns')) ?>" data-confirm="Delete this campaign permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('heart') ?></div>No campaigns yet. <a href="<?= e(admin_url('campaigns?action=create')) ?>">Add your first campaign</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
