<?php
/**
 * =============================================================================
 *  Admin — Issued Certificates CRUD.
 *  Full list + create + edit + delete for certificates that power the public
 *  verify-certificate page. Follows the standard admin module pattern
 *  (see admin/programs.php): CSRF, validation, pagination, document upload
 *  (with old-file cleanup), activity logging, flash + redirect.
 *
 *  Note: certificate_number is a UNIQUE text field — uniqueness is enforced
 *  with a COUNT check (NOT slugified).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'issued_certificates';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$types    = ['volunteer', 'internship', 'participation', 'appreciation', 'training'];
$statuses = ['valid', 'revoked'];

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'certificate_number' => 'required|max:64',
        'holder_name'        => 'required|max:191',
        'email'              => 'email|max:191',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/issued-certificates?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // Enforce unique certificate_number via a COUNT check (excluding self on edit).
    $certNumber = clean(post('certificate_number'));
    $dupWhere   = 'certificate_number = :cn';
    $dupParams  = [':cn' => $certNumber];
    if ($editId) {
        $dupWhere .= ' AND id <> :id';
        $dupParams[':id'] = $editId;
    }
    if (db_count($table, $dupWhere, $dupParams) > 0) {
        set_flash('error', 'That certificate number is already in use.');
        redirect('/admin/issued-certificates?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'certificate_number' => $certNumber,
        'holder_name'        => clean(post('holder_name')),
        'email'              => clean(post('email', '')) ?: null,
        'type'               => in_array(post('type'), $types, true) ? post('type') : 'participation',
        'program'            => clean(post('program', '')) ?: null,
        'issue_date'         => post('issue_date') ?: null,
        'expiry_date'        => post('expiry_date') ?: null,
        'status'             => in_array(post('status'), $statuses, true) ? post('status') : 'valid',
    ];

    // Optional certificate file upload (replaces + deletes the old one).
    if (!empty($_FILES['file']['name'])) {
        $up = upload_document($_FILES['file'], 'documents');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/issued-certificates?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['file_path'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['file_path'])) delete_upload($old['file_path']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'issued_certificates', 'Updated certificate #' . $editId);
        set_flash('success', 'Certificate updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'issued_certificates', 'Created certificate #' . $newId);
        set_flash('success', 'Certificate created successfully.');
    }
    redirect('/admin/issued-certificates');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['file_path'])) delete_upload($row['file_path']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'issued_certificates', 'Deleted certificate #' . $delId);
        set_flash('success', 'Certificate deleted.');
    }
    redirect('/admin/issued-certificates');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Certificate not found.');
        redirect('/admin/issued-certificates');
    }
    $page_title = $action === 'edit' ? 'Edit Certificate' : 'Add Certificate';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Issued Certificates / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('issued-certificates')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('issued-certificates')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Certificate Number <span class="req">*</span></label>
                    <input class="form-control" name="certificate_number" required maxlength="64" value="<?= e($row['certificate_number'] ?? '') ?>" placeholder="PWF-VOL-2026-0001">
                </div>
                <div class="form-group">
                    <label class="form-label">Holder Name <span class="req">*</span></label>
                    <input class="form-control" name="holder_name" required maxlength="191" value="<?= e($row['holder_name'] ?? '') ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" maxlength="191" value="<?= e($row['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type">
                        <?php foreach ($types as $t): ?>
                            <option value="<?= e($t) ?>" <?= (($row['type'] ?? 'participation') === $t) ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Program</label>
                <input class="form-control" name="program" maxlength="191" value="<?= e($row['program'] ?? '') ?>" placeholder="e.g. Volunteer Program 2026">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Issue Date</label>
                    <input class="form-control" type="date" name="issue_date" value="<?= e($row['issue_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Expiry Date</label>
                    <input class="form-control" type="date" name="expiry_date" value="<?= e($row['expiry_date'] ?? '') ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Certificate File (PDF/image)</label>
                    <input class="form-control" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png">
                    <?php if (!empty($row['file_path'])): ?>
                        <small class="form-hint">
                            Current:
                            <a href="<?= e(secure_upload_url($row['file_path'])) ?>" target="_blank" rel="noopener"><?= e(basename($row['file_path'])) ?></a>
                            — choose a new file to replace it.
                        </small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="valid"   <?= (($row['status'] ?? 'valid') === 'valid') ? 'selected' : '' ?>>Valid</option>
                        <option value="revoked" <?= (($row['status'] ?? '') === 'revoked') ? 'selected' : '' ?>>Revoked</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Certificate</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('issued-certificates')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Issued Certificates';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', certificate_number, holder_name, email, program) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Issued Certificates</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('issued-certificates?action=create')) ?>">+ Add Certificate</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('issued-certificates')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search by number, name, email…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Certificate No.</th><th>Holder</th><th>Type</th><th>Program</th><th>Issued</th><th>Expiry</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><strong><?= e($r['certificate_number']) ?></strong></td>
                        <td>
                            <?= e($r['holder_name']) ?><br>
                            <small class="text-muted"><?= e($r['email'] ?? '') ?></small>
                        </td>
                        <td><span class="pill pill-blue"><?= e(ucfirst((string) $r['type'])) ?></span></td>
                        <td><?= e($r['program'] ?? '') ?: '—' ?></td>
                        <td><?= !empty($r['issue_date']) ? e(format_date($r['issue_date'], 'd M Y')) : '—' ?></td>
                        <td><?= !empty($r['expiry_date']) ? e(format_date($r['expiry_date'], 'd M Y')) : '—' ?></td>
                        <td><span class="pill <?= $r['status'] === 'valid' ? 'pill-green' : 'pill-red' ?>"><?= e(ucfirst((string) $r['status'])) ?></span></td>
                        <td>
                            <div class="actions">
                                <?php if (!empty($r['file_path'])): ?>
                                    <a class="icon-btn" href="<?= e(secure_upload_url($r['file_path'])) ?>" target="_blank" rel="noopener" title="View file"><?= lucide('file-text') ?></a>
                                <?php endif; ?>
                                <a class="icon-btn" href="<?= e(admin_url('issued-certificates?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('issued-certificates')) ?>" data-confirm="Delete this certificate permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('award') ?></div>No certificates yet. <a href="<?= e(admin_url('issued-certificates?action=create')) ?>">Issue your first certificate</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
