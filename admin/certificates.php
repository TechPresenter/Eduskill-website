<?php
/**
 * =============================================================================
 *  Admin — Student Certificates (issue · list · revoke · delete)
 * =============================================================================
 *  Issue completion / participation / merit / achievement certificates to
 *  enrolled students, each with an auto-generated serial and QR verification.
 *  The printable certificate lives at admin/certificate-view. Standard admin
 *  pattern: CSRF-guarded POST handlers, prepared statements, pagination +
 *  search + filters, stat cards, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'student_certificates';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$types = [
    'completion'    => 'Completion',
    'participation' => 'Participation',
    'merit'         => 'Merit',
    'achievement'   => 'Achievement',
];
$statuses  = ['valid' => 'Valid', 'revoked' => 'Revoked'];
$templates = ['classic' => 'Classic', 'modern' => 'Modern', 'elegant' => 'Elegant'];

/* =============================================================================
 |  ISSUE (create)
 |============================================================================*/
if (is_post() && post('_do') === 'issue') {
    require_csrf();
    $studentId = (int) post('student_id', 0);
    $title     = clean(post('title'));

    $errors = [];
    if (!$studentId || !find('school_students', $studentId)) { $errors[] = 'Please select a valid student.'; }
    if ($title === '')                                       { $errors[] = 'Certificate title is required.'; }

    if ($errors) {
        flash_old($_POST);
        set_flash('error', implode(' ', $errors));
        redirect('/admin/certificates?action=create' . ($studentId ? '&student=' . $studentId : ''));
    }

    $type     = isset($types[(string) post('type')]) ? post('type') : 'completion';
    $template = isset($templates[(string) post('template')]) ? post('template') : 'classic';

    // Course: keep only a known course id, else null.
    $courseId = (int) post('course_id', 0) ?: null;
    if ($courseId !== null && !isset(course_options()[$courseId])) {
        $courseId = null;
    }

    // Signature: raster formats only (exclude SVG — upload.php would accept it
    // unsanitised, enabling stored XSS from a crafted .svg).
    $signaturePath = null;
    if (!empty($_FILES['signature']['name'])) {
        $up = upload_image($_FILES['signature'], 'certificates', ['allowed' => 'jpg,jpeg,png,gif,webp']);
        if (!$up['success']) {
            flash_old($_POST);
            set_flash('error', 'Signature: ' . $up['error']);
            redirect('/admin/certificates?action=create&student=' . $studentId);
        }
        $signaturePath = $up['path'];
    }

    $certId = certificate_issue($studentId, [
        'course_id'       => $courseId,
        'type'            => $type,
        'title'           => $title,
        'template'        => $template,
        'issue_date'      => clean(post('issue_date')) ?: date('Y-m-d'),
        'signed_by'       => clean(post('signed_by')) ?: null,
        'signature_image' => $signaturePath,
        'created_by'      => current_user_id(),
    ]);

    if (!$certId) {
        if ($signaturePath) { delete_upload($signaturePath); }
        set_flash('error', 'Could not issue the certificate.');
        redirect('/admin/certificates?action=create');
    }

    $cert = find($table, $certId);
    log_activity('create', 'certificates', 'Issued certificate #' . $certId . ' (' . ($cert['serial'] ?? '') . ')');
    set_flash('success', 'Certificate issued. Serial ' . ($cert['serial'] ?? '') . '.');
    redirect(admin_url('certificate-view?id=' . $certId));
}

/* =============================================================================
 |  REVOKE
 |============================================================================*/
if (is_post() && post('_do') === 'revoke') {
    require_csrf();
    $revId = (int) post('id', 0);
    $row   = find($table, $revId);
    if ($row) {
        db_update($table, ['status' => 'revoked'], 'id = :id', [':id' => $revId]);
        log_activity('update', 'certificates', 'Revoked certificate #' . $revId . ' (' . ($row['serial'] ?? '') . ')');
        set_flash('success', 'Certificate revoked.');
    }
    redirect('/admin/certificates');
}

/* =============================================================================
 |  DELETE
 |============================================================================*/
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        if (!empty($row['signature_image'])) { delete_upload($row['signature_image']); }
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'certificates', 'Deleted certificate #' . $delId . ' (' . ($row['serial'] ?? '') . ')');
        set_flash('success', 'Certificate deleted.');
    }
    redirect('/admin/certificates');
}

/* =============================================================================
 |  ISSUE FORM (create)
 |============================================================================*/
if ($action === 'create') {
    $prefillStudent = (int) get('student', 0);
    $students       = db_all('SELECT id, name, student_code FROM school_students ORDER BY name');
    $courses        = course_options();

    $page_title = 'Issue Certificate';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Issue Certificate</h1><span class="muted">Certificates / Issue</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('certificates')) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('certificates')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="issue">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Recipient & award</h3>
                <div class="form-group"><label class="form-label">Student <span class="req">*</span></label>
                    <select class="form-select" name="student_id" required>
                        <option value="">Select student…</option>
                        <?php foreach ($students as $s):
                            $selected = (int) old('student_id', $prefillStudent) === (int) $s['id']; ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                                <?= e($s['name']) ?><?= $s['student_code'] ? ' · ' . e($s['student_code']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$students): ?><small class="form-hint">No students enrolled yet.</small><?php endif; ?>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= old('type', 'completion') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Template</label>
                        <select class="form-select" name="template">
                            <?php foreach ($templates as $k => $l): ?><option value="<?= e($k) ?>" <?= old('template', 'classic') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" required value="<?= e(old('title')) ?>" placeholder="e.g. Certificate of Completion — Spoken English">
                </div>
                <div class="form-group"><label class="form-label">Course</label>
                    <select class="form-select" name="course_id">
                        <option value="">— None —</option>
                        <?php foreach ($courses as $cid => $ctitle): ?>
                            <option value="<?= (int) $cid ?>" <?= (int) old('course_id', 0) === (int) $cid ? 'selected' : '' ?>><?= e($ctitle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$courses): ?><small class="form-hint">No courses found.</small><?php endif; ?>
                </div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Issue & signature</h3>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Issue date</label>
                        <input class="form-control" type="date" name="issue_date" value="<?= e(old('issue_date', date('Y-m-d'))) ?>"></div>
                    <div class="form-group"><label class="form-label">Signed by</label>
                        <input class="form-control" name="signed_by" value="<?= e(old('signed_by')) ?>" placeholder="Authorised signatory"></div>
                </div>
                <div class="form-group"><label class="form-label">Signature image</label>
                    <input class="form-control" type="file" name="signature" accept="image/*">
                    <small class="form-hint">Optional. PNG on a transparent background works best.</small>
                </div>
                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= lucide('award') ?> Issue certificate</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('certificates')) ?>">Cancel</a>
                </div>
            </div></div>
        </div>
    </form>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* =============================================================================
 |  LIST
 |============================================================================*/
$page_title   = 'Certificates';
$search       = trim((string) get('q', ''));
$typeFilter   = (string) get('type', '');
$statusFilter = (string) get('status', '');

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', c.serial, c.title, st.name) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
if (isset($types[$typeFilter]))       { $where .= ' AND c.type = :ty';   $params[':ty'] = $typeFilter; }
if (isset($statuses[$statusFilter]))  { $where .= ' AND c.status = :st';  $params[':st'] = $statusFilter; }

$p = paginate(
    "SELECT c.*, st.name AS student_name, st.student_code AS student_code
       FROM student_certificates c
       LEFT JOIN school_students st ON st.id = c.student_id
      WHERE $where ORDER BY c.id DESC",
    $params, 15
);

$counts = [
    'total'   => db_count('student_certificates'),
    'valid'   => db_count('student_certificates', "status = 'valid'"),
    'revoked' => db_count('student_certificates', "status = 'revoked'"),
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Certificates</h1><span class="muted"><?= (int) $counts['total'] ?> issued</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('certificates?action=create')) ?>">+ Issue Certificate</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('award') ?></div><div><div class="stat-value"><?= (int) $counts['total'] ?></div><div class="stat-label">Total issued</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['valid'] ?></div><div class="stat-label">Valid</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('circle-x') ?></div><div><div class="stat-value"><?= (int) $counts['revoked'] ?></div><div class="stat-label">Revoked</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('certificates')) ?>">
            <?php if ($typeFilter !== ''): ?><input type="hidden" name="type" value="<?= e($typeFilter) ?>"><?php endif; ?>
            <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search serial, title, student…">
        </form>
        <form method="get" action="<?= e(admin_url('certificates')) ?>" class="flex" style="gap:.5rem;">
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            <select class="form-select" name="type" onchange="this.form.submit()">
                <option value="">All types</option>
                <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $typeFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($search !== '' || $typeFilter !== '' || $statusFilter !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('certificates')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Serial</th><th>Student</th><th>Title</th><th>Type</th><th>Issued</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $viewUrl = admin_url('certificate-view?id=' . $r['id']);
        ?>
            <tr>
                <td><a href="<?= e($viewUrl) ?>"><strong><?= e($r['serial'] ?? '—') ?></strong></a></td>
                <td><?= e($r['student_name'] ?? '—') ?><?= $r['student_code'] ? '<br><small class="text-muted">' . e($r['student_code']) . '</small>' : '' ?></td>
                <td><?= e($r['title']) ?></td>
                <td><?= e($types[$r['type']] ?? $r['type']) ?></td>
                <td><?= $r['issue_date'] ? e(format_date($r['issue_date'])) : '<span class="text-muted">—</span>' ?></td>
                <td><span class="pill <?= $r['status'] === 'valid' ? 'pill-green' : 'pill-red' ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e($viewUrl) ?>" title="View"><?= lucide('eye') ?></a>
                    <?php if ($r['status'] === 'valid'): ?>
                        <form method="post" action="<?= e(admin_url('certificates')) ?>" data-confirm="Revoke this certificate? It will show as invalid on verification." style="display:inline;">
                            <?= csrf_field() ?><input type="hidden" name="_do" value="revoke"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button class="icon-btn" type="submit" title="Revoke"><?= lucide('ban') ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(admin_url('certificates')) ?>" data-confirm="Delete this certificate permanently? This cannot be undone." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('award') ?></div>
            <?= ($search !== '' || $typeFilter !== '' || $statusFilter !== '') ? 'No certificates match your filters. ' : 'No certificates issued yet. ' ?>
            <a href="<?= e(admin_url('certificates?action=create')) ?>">Issue one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
