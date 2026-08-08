<?php
/**
 * =============================================================================
 *  Admin — Admissions Review (INBOX module: applications → review → enrolment)
 * =============================================================================
 *  list + view + approve (enrol) + reject + mark-under-review + delete.
 *  NO create/edit form — applications arrive from the public admission form.
 *  LEFT JOINs `schools` for the school name, filters by status / school,
 *  searches name / email / application_no, shows live per-status counts, and
 *  lazily assigns an application number to any row missing one. Standard admin
 *  pattern: CSRF-guarded POST handlers, prepared statements, pagination + stat
 *  cards, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'admissions';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$statuses = [
    'new'          => 'New',
    'under_review' => 'Under review',
    'approved'     => 'Approved',
    'rejected'     => 'Rejected',
];

/* Pill colour for a given status. */
$status_pill = static function (string $s): string {
    return [
        'new'          => 'pill-blue',
        'under_review' => 'pill-amber',
        'approved'     => 'pill-green',
        'rejected'     => 'pill-red',
    ][$s] ?? 'pill-gray';
};

/* =============================================================================
 |  APPROVE (enrol the applicant as a student)
 |============================================================================*/
if (is_post() && post('_do') === 'approve') {
    require_csrf();
    $rid = (int) post('id', 0);
    $adm = find($table, $rid);
    if (!$adm) {
        set_flash('error', 'Application not found.');
        redirect('/admin/admissions');
    }

    $schoolId = (int) post('school_id', 0);
    if (!$schoolId || !find('schools', $schoolId)) {
        set_flash('error', 'Please select a valid school to enrol the applicant into.');
        redirect('/admin/admissions?action=view&id=' . $rid);
    }

    // Keep only a known NGO program id, else none.
    $programId = (int) post('program_id', 0) ?: null;
    if ($programId !== null && !isset(school_programs()[$programId])) {
        $programId = null;
    }

    $student = admission_enroll($rid, [
        'school_id'    => $schoolId,
        'program_id'   => $programId,
        'grade'        => clean(post('grade')) ?: null,
        'create_login' => (bool) post('create_login', false),
        'admin_id'     => $_SESSION['user']['id'] ?? null,
    ]);
    if (!$student) {
        set_flash('error', 'Could not enrol this applicant — please check the details and try again.');
        redirect('/admin/admissions?action=view&id=' . $rid);
    }

    log_activity('update', 'admissions', 'Approved admission #' . $rid . ' → student #' . (int) $student['id']);
    set_flash('success', 'Applicant enrolled. Student code ' . ($student['student_code'] ?? '—') . '.');
    redirect('/admin/student-profile?id=' . (int) $student['id']);
}

/* =============================================================================
 |  REJECT
 |============================================================================*/
if (is_post() && post('_do') === 'reject') {
    require_csrf();
    $rid = (int) post('id', 0);
    $adm = find($table, $rid);
    if (!$adm) {
        set_flash('error', 'Application not found.');
        redirect('/admin/admissions');
    }
    db_update($table, [
        'status'      => 'rejected',
        'review_note' => clean(post('review_note')) ?: null,
        'reviewed_by' => $_SESSION['user']['id'] ?? null,
        'reviewed_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', [':id' => $rid]);
    log_activity('update', 'admissions', 'Rejected admission #' . $rid);
    set_flash('success', 'Application rejected.');
    redirect('/admin/admissions?action=view&id=' . $rid);
}

/* =============================================================================
 |  MARK UNDER REVIEW
 |============================================================================*/
if (is_post() && post('_do') === 'under_review') {
    require_csrf();
    $rid = (int) post('id', 0);
    $adm = find($table, $rid);
    if (!$adm) {
        set_flash('error', 'Application not found.');
        redirect('/admin/admissions');
    }
    db_update($table, [
        'status'      => 'under_review',
        'reviewed_by' => $_SESSION['user']['id'] ?? null,
        'reviewed_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', [':id' => $rid]);
    log_activity('update', 'admissions', 'Marked admission #' . $rid . ' under review');
    set_flash('success', 'Application marked under review.');
    redirect('/admin/admissions?action=view&id=' . $rid);
}

/* =============================================================================
 |  DELETE
 |============================================================================*/
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'admissions', 'Deleted admission #' . $delId . ' (' . ($row['name'] ?? '') . ')');
        set_flash('success', 'Application deleted.');
    }
    redirect('/admin/admissions');
}

/* =============================================================================
 |  VIEW (application details + review actions)
 |============================================================================*/
if ($action === 'view') {
    $adm = db_row(
        "SELECT a.*, sc.name AS school_name, sc.code AS school_code
           FROM admissions a
           LEFT JOIN schools sc ON sc.id = a.school_id
          WHERE a.id = :id",
        [':id' => $id]
    );
    if (!$adm) {
        set_flash('error', 'Application not found.');
        redirect('/admin/admissions');
    }

    // Assign an application number if this row is missing one.
    if (empty($adm['application_no'])) {
        $no = admission_generate_no((int) $adm['id']);
        db_update($table, ['application_no' => $no], 'id = :id', [':id' => (int) $adm['id']]);
        $adm['application_no'] = $no;
    }

    $docs     = json_column($adm['documents'] ?? null);
    $programs = school_programs();
    $progName = !empty($adm['program_id']) ? ($programs[(int) $adm['program_id']] ?? null) : null;
    $student  = !empty($adm['student_id']) ? find('school_students', (int) $adm['student_id']) : null;

    // Ordered applicant field map (label => value), skipping empties on render.
    $fields = [
        'Application no'   => $adm['application_no'] ?: null,
        'Date of birth'    => !empty($adm['dob']) ? format_date($adm['dob']) : null,
        'Gender'           => $adm['gender'] ? ucfirst((string) $adm['gender']) : null,
        'Father'           => $adm['father_name'] ?? null,
        'Mother'           => $adm['mother_name'] ?? null,
        'Guardian'         => $adm['guardian_name'] ?? null,
        'Grade applying'   => $adm['grade_applying'] ?? null,
        'Previous school'  => $adm['previous_school'] ?? null,
        'Program'          => $progName,
        'Phone'            => $adm['phone'] ?? null,
        'Address'          => $adm['address'] ?? null,
        'City'             => $adm['city'] ?? null,
        'State'            => $adm['state'] ?? null,
        'Pincode'          => $adm['pincode'] ?? null,
    ];

    $page_title = 'Application ' . ($adm['application_no'] ?: '#' . (int) $adm['id']);
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div>
            <h1><?= e($adm['name']) ?></h1>
            <span class="muted">Admissions / <?= e($adm['application_no'] ?: '#' . (int) $adm['id']) ?>
                &middot; <span class="pill <?= e($status_pill($adm['status'])) ?>"><?= e($statuses[$adm['status']] ?? $adm['status']) ?></span></span>
        </div>
        <a class="btn btn-secondary" href="<?= e(admin_url('admissions')) ?>">&larr; Back to list</a>
    </div>

    <div class="grid-2" style="align-items:start;gap:1.25rem;">
        <div>
            <div class="panel"><div class="panel-body">
                <h3 style="margin-top:0;">Applicant</h3>
                <table class="admin-table">
                    <tbody>
                        <tr><th style="width:38%;">Name</th><td><?= e($adm['name']) ?></td></tr>
                        <tr><th>Email</th><td><?= $adm['email'] ? '<a href="mailto:' . e($adm['email']) . '">' . e($adm['email']) . '</a>' : '<span class="text-muted">—</span>' ?></td></tr>
                        <?php foreach ($fields as $label => $val): ?>
                            <?php if ($val !== null && $val !== ''): ?>
                                <tr><th><?= e($label) ?></th><td><?= e($val) ?></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <tr><th>School applied to</th><td><?= $adm['school_name'] ? e($adm['school_name']) : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Applied</th><td><?= e(format_datetime($adm['created_at'])) ?></td></tr>
                        <?php if (!empty($adm['reviewed_at'])): ?>
                            <tr><th>Reviewed</th><td><?= e(format_datetime($adm['reviewed_at'])) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!empty($adm['message'])): ?>
                    <h3>Message</h3>
                    <p class="text-muted" style="white-space:pre-wrap;"><?= e($adm['message']) ?></p>
                <?php endif; ?>

                <?php if (!empty($adm['review_note'])): ?>
                    <h3>Review note</h3>
                    <p class="text-muted" style="white-space:pre-wrap;"><?= e($adm['review_note']) ?></p>
                <?php endif; ?>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 style="margin-top:0;">Documents</h3>
                <?php if ($docs): ?>
                    <table class="admin-table"><tbody>
                    <?php foreach ($docs as $doc):
                        $dTitle = trim((string) ($doc['title'] ?? ''));
                        $dPath  = trim((string) ($doc['path'] ?? ''));
                        if ($dPath === '') { continue; }
                    ?>
                        <tr>
                            <td><?= e($dTitle !== '' ? $dTitle : 'Document') ?></td>
                            <td style="text-align:right;">
                                <a class="btn btn-outline btn-sm" href="<?= e(secure_upload_url($dPath)) ?>" target="_blank" rel="noopener"><?= lucide('external-link') ?> Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                <?php else: ?>
                    <p class="text-muted">No documents were submitted with this application.</p>
                <?php endif; ?>
            </div></div>
        </div>

        <div class="panel"><div class="panel-body">
            <?php if ($student): ?>
                <h3 style="margin-top:0;">Enrolled</h3>
                <p class="text-muted">This application has been approved and enrolled as
                    <strong><?= e($student['name']) ?></strong> (<?= e($student['student_code'] ?? '—') ?>).</p>
                <a class="btn btn-primary btn-block" href="<?= e(admin_url('student-profile?id=' . (int) $student['id'])) ?>">Open student profile &rarr;</a>
            <?php else: ?>
                <h3 style="margin-top:0;">Approve &amp; enrol</h3>
                <form class="admin-form" method="post" action="<?= e(admin_url('admissions')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="approve">
                    <input type="hidden" name="id" value="<?= (int) $adm['id'] ?>">
                    <div class="form-group">
                        <label class="form-label">School <span class="req">*</span></label>
                        <select class="form-select" name="school_id" required>
                            <option value="">Select school…</option>
                            <?php foreach (school_options() as $sid => $sname): ?>
                                <option value="<?= (int) $sid ?>" <?= (int) ($adm['school_id'] ?? 0) === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">NGO program</label>
                        <select class="form-select" name="program_id">
                            <option value="">— None —</option>
                            <?php foreach ($programs as $pid => $ptitle): ?>
                                <option value="<?= (int) $pid ?>" <?= (int) ($adm['program_id'] ?? 0) === (int) $pid ? 'selected' : '' ?>><?= e($ptitle) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$programs): ?><small class="form-hint">No active programs found.</small><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Grade / class</label>
                        <input class="form-control" name="grade" value="<?= e($adm['grade_applying'] ?? '') ?>" placeholder="e.g. Class 6">
                    </div>
                    <div class="form-group">
                        <label class="checkbox flex items-center" style="gap:.5rem;">
                            <input type="checkbox" name="create_login" value="1">
                            <span>Create a student login (emails a set-password link)</span>
                        </label>
                        <small class="form-hint">Requires a valid applicant email. A student code is generated automatically.</small>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-success" type="submit"><?= lucide('circle-check') ?> Approve &amp; enrol</button>
                    </div>
                </form>

                <hr style="border:none;border-top:1px solid var(--border,#e2e8f0);margin:1.25rem 0;">

                <h3>Reject</h3>
                <form class="admin-form" method="post" action="<?= e(admin_url('admissions')) ?>" data-confirm="Reject this application?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="reject">
                    <input type="hidden" name="id" value="<?= (int) $adm['id'] ?>">
                    <div class="form-group">
                        <label class="form-label">Review note</label>
                        <textarea class="form-textarea" name="review_note" style="min-height:80px;" placeholder="Reason (optional, kept on file)…"><?= e($adm['review_note'] ?? '') ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-danger" type="submit"><?= lucide('x') ?> Reject application</button>
                    </div>
                </form>

                <?php if ($adm['status'] !== 'under_review'): ?>
                    <hr style="border:none;border-top:1px solid var(--border,#e2e8f0);margin:1.25rem 0;">
                    <form method="post" action="<?= e(admin_url('admissions')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_do" value="under_review">
                        <input type="hidden" name="id" value="<?= (int) $adm['id'] ?>">
                        <button class="btn btn-outline btn-block" type="submit"><?= lucide('clock') ?> Mark under review</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <hr style="border:none;border-top:1px solid var(--border,#e2e8f0);margin:1.25rem 0;">
            <form method="post" action="<?= e(admin_url('admissions')) ?>" data-confirm="Delete this application permanently?">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="delete">
                <input type="hidden" name="id" value="<?= (int) $adm['id'] ?>">
                <button class="btn btn-ghost btn-block" type="submit"><?= lucide('trash-2') ?> Delete application</button>
            </form>
        </div></div>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* =============================================================================
 |  LIST
 |============================================================================*/
$page_title   = 'Admissions';
$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');
$schoolFilter = (int) get('school', 0);
if (!isset($statuses[$statusFilter])) {
    $statusFilter = '';
}

$where  = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= ' AND a.status = :st';
    $params[':st'] = $statusFilter;
}
if ($schoolFilter > 0) {
    $where .= ' AND a.school_id = :sid';
    $params[':sid'] = $schoolFilter;
}
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', a.name, a.email, a.application_no) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$p = paginate(
    "SELECT a.*, sc.name AS school_name
       FROM admissions a
       LEFT JOIN schools sc ON sc.id = a.school_id
      WHERE $where ORDER BY a.created_at DESC",
    $params, 15
);

// Lazily assign an application number to any listed row missing one.
foreach ($p['items'] as &$r) {
    if (empty($r['application_no'])) {
        $no = admission_generate_no((int) $r['id']);
        db_update($table, ['application_no' => $no], 'id = :id', [':id' => (int) $r['id']]);
        $r['application_no'] = $no;
    }
}
unset($r);

$counts = [];
foreach ($statuses as $sk => $sl) {
    $counts[$sk] = db_count($table, 'status = :s', [':s' => $sk]);
}

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Admissions</h1><span class="muted"><?= (int) $p['total'] ?> applications</span></div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('sparkles') ?></div><div><div class="stat-value"><?= (int) $counts['new'] ?></div><div class="stat-label">New</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('clock') ?></div><div><div class="stat-value"><?= (int) $counts['under_review'] ?></div><div class="stat-label">Under review</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['approved'] ?></div><div class="stat-label">Approved</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('x') ?></div><div><div class="stat-value"><?= (int) $counts['rejected'] ?></div><div class="stat-label">Rejected</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('admissions')) ?>">
            <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
            <?php if ($schoolFilter): ?><input type="hidden" name="school" value="<?= (int) $schoolFilter ?>"><?php endif; ?>
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, application no…">
        </form>
        <form method="get" action="<?= e(admin_url('admissions')) ?>" class="flex" style="gap:.5rem;">
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="school" onchange="this.form.submit()">
                <option value="">All schools</option>
                <?php foreach (school_options() as $sid => $sname): ?><option value="<?= (int) $sid ?>" <?= $schoolFilter === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($search !== '' || $statusFilter !== '' || $schoolFilter): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('admissions')) ?>">Clear</a>
        <?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Application</th><th>Applicant</th><th>Grade</th><th>School</th><th>Status</th><th>Applied</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): $view = admin_url('admissions?action=view&id=' . $r['id']); ?>
            <tr>
                <td><a href="<?= e($view) ?>"><strong><?= e($r['application_no'] ?? '—') ?></strong></a></td>
                <td>
                    <a href="<?= e($view) ?>"><strong><?= e($r['name']) ?></strong></a><br>
                    <small class="text-muted"><?= e($r['email'] ?: ($r['phone'] ?: '—')) ?></small>
                </td>
                <td><?= $r['grade_applying'] ? e($r['grade_applying']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $r['school_name'] ? e($r['school_name']) : '<span class="text-muted">—</span>' ?></td>
                <td><span class="pill <?= e($status_pill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td><small class="text-muted"><?= e(format_date($r['created_at'], 'd M Y')) ?></small></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e($view) ?>" title="View"><?= lucide('eye') ?></a>
                    <form method="post" action="<?= e(admin_url('admissions')) ?>" data-confirm="Delete this application permanently?" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('clipboard-list') ?></div>
            <?= ($search !== '' || $statusFilter !== '' || $schoolFilter) ? 'No applications match your filters.' : 'No admission applications yet.' ?></div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
