<?php
/**
 * =============================================================================
 *  Admin — Team Members CRUD.
 *  Full list + create + edit + delete, following the programs.php exemplar:
 *  CSRF, validation, pagination, photo upload (with old-file cleanup),
 *  slug generation, activity logging, flash + redirect.
 *
 *  Social links (facebook/twitter/linkedin/instagram) are entered as separate
 *  inputs, assembled into a JSON object on save (only non-empty ones) and
 *  stored in the `socials` column; decoded via json_column() when editing.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'team_members';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/** Social networks captured for the `socials` JSON column. */
$socialNetworks = ['facebook', 'twitter', 'linkedin', 'instagram'];

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'name'        => 'required|max:128',
        'designation' => 'max:128',
        'department'  => 'max:128',
        'email'       => 'email|max:191',
        'phone'       => 'max:32',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/team?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // Assemble the social links into a JSON object (skip empty ones).
    $socials = [];
    foreach ($socialNetworks as $net) {
        $val = clean(post($net, ''));
        if ($val !== '') {
            $socials[$net] = $val;
        }
    }

    $data = [
        'name'          => clean(post('name')),
        'slug'          => unique_slug($table, post('slug') ?: post('name'), $editId ?: null),
        'designation'   => clean(post('designation', '')),
        'department'    => clean(post('department', '')),
        'bio'           => clean(post('bio', '')),
        'email'         => clean(post('email', '')),
        'phone'         => clean(post('phone', '')),
        'socials'       => $socials ? json_encode($socials, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        'is_leadership' => post('is_leadership') ? 1 : 0,
        'sort_order'    => (int) post('sort_order', 0),
        'status'        => post('status') ? 1 : 0,
    ];

    // Optional photo upload (replaces + deletes the old one).
    if (!empty($_FILES['photo']['name'])) {
        $up = upload_image($_FILES['photo'], 'team');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/team?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['photo'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['photo'])) delete_upload($old['photo']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'team_members', 'Updated team member #' . $editId);
        set_flash('success', 'Team member updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'team_members', 'Created team member #' . $newId);
        set_flash('success', 'Team member created successfully.');
    }
    redirect('/admin/team');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['photo'])) delete_upload($row['photo']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'team_members', 'Deleted team member #' . $delId);
        set_flash('success', 'Team member deleted.');
    }
    redirect('/admin/team');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Team member not found.');
        redirect('/admin/team');
    }
    $socials = json_column($row['socials'] ?? null);
    $page_title = $action === 'edit' ? 'Edit Team Member' : 'Add Team Member';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Team Members / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('team')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('team')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" data-slug-source required maxlength="128" value="<?= e($row['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" data-slug-target maxlength="128" value="<?= e($row['slug'] ?? '') ?>" placeholder="auto-generated">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Designation</label>
                    <input class="form-control" name="designation" maxlength="128" value="<?= e($row['designation'] ?? '') ?>" placeholder="e.g. Founder &amp; President">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input class="form-control" name="department" maxlength="128" value="<?= e($row['department'] ?? '') ?>" placeholder="e.g. Operations">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea class="form-textarea" name="bio" style="min-height:140px;"><?= e($row['bio'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Photo</label>
                <input class="form-control" type="file" name="photo" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['photo']) ? upload_url($row['photo']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['photo']) ? 'display:none;' : '' ?>">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" maxlength="191" value="<?= e($row['email'] ?? '') ?>" placeholder="name@example.org">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="phone" maxlength="32" value="<?= e($row['phone'] ?? '') ?>" placeholder="+91 90000 00000">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Facebook URL</label>
                    <input class="form-control" name="facebook" value="<?= e($socials['facebook'] ?? '') ?>" placeholder="https://facebook.com/…">
                </div>
                <div class="form-group">
                    <label class="form-label">Twitter / X URL</label>
                    <input class="form-control" name="twitter" value="<?= e($socials['twitter'] ?? '') ?>" placeholder="https://twitter.com/…">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">LinkedIn URL</label>
                    <input class="form-control" name="linkedin" value="<?= e($socials['linkedin'] ?? '') ?>" placeholder="https://linkedin.com/in/…">
                </div>
                <div class="form-group">
                    <label class="form-label">Instagram URL</label>
                    <input class="form-control" name="instagram" value="<?= e($socials['instagram'] ?? '') ?>" placeholder="https://instagram.com/…">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <label class="checkbox" style="margin-top:10px;"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (show on the website)</label>
                </div>
            </div>

            <label class="checkbox"><input type="checkbox" name="is_leadership" value="1" <?= !empty($row['is_leadership']) ? 'checked' : '' ?>> Leadership team member</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Member</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('team')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Team Members';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', name, designation, department, email) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Team Members</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('team?action=create')) ?>">+ Add Member</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('team')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search team members…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Photo</th><th>Name</th><th>Department</th><th>Leadership</th><th>Status</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['photo']) ? upload_url($r['photo']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['name']) ?></strong><br>
                            <small class="text-muted"><?= e($r['designation'] ?? '') ?></small>
                        </td>
                        <td><?= e($r['department'] ?? '') ?: '—' ?></td>
                        <td><?= !empty($r['is_leadership']) ? lucide('star') : '—' ?></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('team?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('team')) ?>" data-confirm="Delete this team member permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('users') ?></div>No team members yet. <a href="<?= e(admin_url('team?action=create')) ?>">Add your first member</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
