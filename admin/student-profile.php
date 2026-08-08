<?php
/**
 * =============================================================================
 *  Admin — Student profile hub (per-student, ?id=)
 * =============================================================================
 *  A single student's full record: editable profile (personal, academic,
 *  family, contact + photo), uploaded documents, and read-only rollups of
 *  their certificates, marksheets, course enrolments and assignment
 *  submissions. Quick actions link out to the ID card, certificate and
 *  marksheet builders. Standard admin pattern: CSRF-guarded POST handlers,
 *  prepared statements, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table = 'school_students';
$id    = (int) get('id', 0);

$student = find($table, $id);
if (!$student) {
    set_flash('error', 'Student not found.');
    redirect(admin_url('school-students'));
}
$student = student_ensure_full($student);
$id      = (int) $student['id'];
$selfUrl = admin_url('student-profile?id=' . $id);

$genders      = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];
$bloodGroups  = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
$categories   = ['General', 'OBC', 'SC', 'ST', 'EWS', 'Minority', 'Other'];

/* =============================================================================
 |  SAVE profile
 |============================================================================*/
if (is_post() && post('_do') === 'save') {
    require_csrf();

    $programId = (int) post('program_id', 0) ?: null;
    if ($programId !== null && !isset(school_programs()[$programId])) {
        $programId = null;
    }

    $data = [
        // Personal
        'dob'             => clean(post('dob')) ?: null,
        'gender'          => isset($genders[(string) post('gender')]) ? post('gender') : null,
        'blood_group'     => in_array(post('blood_group'), $bloodGroups, true) ? post('blood_group') : null,
        'category'        => clean(post('category')) ?: null,
        // Academic
        'grade'           => clean(post('grade')) ?: null,
        'section'         => clean(post('section')) ?: null,
        'academic_year'   => clean(post('academic_year')) ?: null,
        'previous_school' => clean(post('previous_school')) ?: null,
        'program_id'      => $programId,
        // Family
        'father_name'     => clean(post('father_name')) ?: null,
        'mother_name'     => clean(post('mother_name')) ?: null,
        'guardian_name'   => clean(post('guardian_name')) ?: null,
        'guardian_phone'  => clean(post('guardian_phone')) ?: null,
        'guardian_email'  => strtolower(clean(post('guardian_email'))) ?: null,
        // Contact
        'phone'           => clean(post('phone')) ?: null,
        'email'           => strtolower(clean(post('email'))) ?: null,
        'address'         => clean(post('address')) ?: null,
        'city'            => clean(post('city')) ?: null,
        'state'           => clean(post('state')) ?: null,
        'pincode'         => clean(post('pincode')) ?: null,
    ];

    if (!empty($_FILES['photo']['name'])) {
        // Raster formats only — exclude SVG (which upload.php would otherwise
        // accept unsanitised, enabling stored XSS from a crafted .svg).
        $up = upload_image($_FILES['photo'], 'students', ['allowed' => 'jpg,jpeg,png,gif,webp']);
        if (!$up['success']) {
            flash_old($_POST);
            set_flash('error', 'Photo: ' . $up['error']);
            redirect($selfUrl);
        }
        $data['photo'] = $up['path'];
        if (!empty($student['photo'])) { delete_upload($student['photo']); }
    }

    db_update($table, $data, 'id = :id', [':id' => $id]);
    log_activity('update', 'students', 'Updated student #' . $id . ' (' . ($student['name'] ?? '') . ')');
    set_flash('success', 'Student profile updated.');
    redirect($selfUrl);
}

/* =============================================================================
 |  DOCUMENTS — add
 |============================================================================*/
if (is_post() && post('_do') === 'doc_add') {
    require_csrf();
    $docTitle = clean(post('title'));
    if ($docTitle === '') {
        set_flash('error', 'Please give the document a title.');
        redirect($selfUrl);
    }
    if (empty($_FILES['file']['name'])) {
        set_flash('error', 'Please choose a file to upload.');
        redirect($selfUrl);
    }
    // 'student-documents', not 'students': that folder holds the student's
    // photograph, which verify-student.php renders publicly by design. Personal
    // documents must not sit in a publicly-served directory alongside it.
    $up = upload_document($_FILES['file'], 'student-documents');
    if (!$up['success']) {
        set_flash('error', 'Document: ' . $up['error']);
        redirect($selfUrl);
    }
    db_insert('student_documents', [
        'student_id' => $id,
        'title'      => $docTitle,
        'file_path'  => $up['path'],
    ]);
    log_activity('update', 'students', 'Added document to student #' . $id);
    set_flash('success', 'Document uploaded.');
    redirect($selfUrl);
}

/* =============================================================================
 |  DOCUMENTS — delete
 |============================================================================*/
if (is_post() && post('_do') === 'doc_delete') {
    require_csrf();
    $docId = (int) post('doc_id', 0);
    $doc   = find('student_documents', $docId);
    if ($doc && (int) $doc['student_id'] === $id) {
        delete_upload($doc['file_path']);
        db_delete('student_documents', 'id = :id', [':id' => $docId]);
        log_activity('update', 'students', 'Deleted document from student #' . $id);
        set_flash('success', 'Document removed.');
    }
    redirect($selfUrl);
}

/* =============================================================================
 |  VIEW
 |============================================================================*/
$school  = find('schools', (int) $student['school_id']);
$member  = !empty($student['member_id']) ? find('members', (int) $student['member_id']) : null;
$program = !empty($student['program_id']) ? (school_programs()[(int) $student['program_id']] ?? null) : null;

$documents    = db_all('SELECT * FROM student_documents WHERE student_id = :s ORDER BY id DESC', [':s' => $id]);
$certificates = db_all('SELECT * FROM student_certificates WHERE student_id = :s ORDER BY id DESC', [':s' => $id]);
$marksheets   = db_all('SELECT * FROM marksheets WHERE student_id = :s ORDER BY id DESC', [':s' => $id]);
$enrollments  = db_all(
    "SELECT e.*, c.title AS course_title
       FROM course_enrollments e
       JOIN courses c ON c.id = e.course_id
      WHERE e.student_id = :s ORDER BY e.id DESC",
    [':s' => $id]
);
$submissions  = db_all(
    "SELECT sub.*, a.title AS assignment_title, a.max_marks AS assignment_max
       FROM assignment_submissions sub
       JOIN assignments a ON a.id = sub.assignment_id
      WHERE sub.student_id = :s ORDER BY sub.id DESC",
    [':s' => $id]
);

// Local status-pill maps for the read-only rollups.
$certPill   = static fn(string $s): string => ['valid' => 'pill-green', 'revoked' => 'pill-red'][$s] ?? 'pill-gray';
$resultPill = static fn(string $s): string => ['pass' => 'pill-green', 'fail' => 'pill-red', 'pending' => 'pill-amber'][$s] ?? 'pill-gray';
$enrPill    = static fn(string $s): string => ['active' => 'pill-blue', 'completed' => 'pill-green', 'dropped' => 'pill-red'][$s] ?? 'pill-gray';
$subPill    = static fn(string $s): string => ['submitted' => 'pill-blue', 'graded' => 'pill-green', 'returned' => 'pill-amber', 'late' => 'pill-red'][$s] ?? 'pill-gray';

$o = static fn(string $k, $d = '') => e(old($k, $student[$k] ?? $d));

$page_title = $student['name'];
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div class="flex items-center" style="gap:.85rem;">
        <img src="<?= e(image_url($student['photo'] ?? null, 'avatar')) ?>" alt=""
             style="width:52px;height:52px;border-radius:12px;object-fit:cover;flex:none;background:#eef2f7;">
        <div>
            <h1><?= e($student['name']) ?></h1>
            <span class="muted">
                <?= e($student['student_code'] ?? '—') ?>
                <?php if ($school): ?> · <a href="<?= e(admin_url('school-dashboard?id=' . (int) $school['id'])) ?>"><?= e($school['name']) ?></a><?php endif; ?>
                <?php if (!empty($student['grade'])): ?> · <?= e($student['grade']) ?><?= !empty($student['section']) ? ' - ' . e($student['section']) : '' ?><?php endif; ?>
            </span>
        </div>
    </div>
    <div class="flex items-center" style="gap:.5rem;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= e(admin_url('student-card?id=' . $id)) ?>" target="_blank" rel="noopener"><?= lucide('id-card') ?> ID card</a>
        <a class="btn btn-outline" href="<?= e(admin_url('certificates?action=create&student=' . $id)) ?>"><?= lucide('award') ?> Issue certificate</a>
        <a class="btn btn-outline" href="<?= e(admin_url('marksheets?action=create&student=' . $id)) ?>"><?= lucide('file-spreadsheet') ?> New marksheet</a>
        <a class="btn btn-secondary" href="<?= e(admin_url('school-students' . ($school ? '?school=' . (int) $school['id'] : ''))) ?>">← Back</a>
    </div>
</div>

<form class="admin-form" method="post" action="<?= e($selfUrl) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="_do" value="save">

    <div class="panel"><div class="panel-body">
        <div class="grid-3" style="align-items:start;">
            <div class="form-group">
                <label class="form-label">Photo</label>
                <input class="form-control" type="file" name="photo" accept="image/*">
                <small class="form-hint">Current photo kept unless replaced.</small>
            </div>
            <div class="form-group">
                <label class="form-label">Identity</label>
                <div class="text-muted" style="font-size:.9rem;line-height:1.8;">
                    Code: <strong><?= e($student['student_code'] ?? '—') ?></strong><br>
                    Admission no: <strong><?= e($student['admission_no'] ?? '—') ?></strong>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Portal login</label>
                <?php if ($member): ?>
                    <div><span class="pill pill-green">Linked</span></div>
                    <small class="form-hint"><a href="<?= e(admin_url('members?action=view&id=' . (int) $member['id'])) ?>"><?= e($member['email'] ?? $member['name'] ?? ('#' . (int) $member['id'])) ?></a></small>
                <?php else: ?>
                    <div><span class="pill pill-gray">Not linked</span></div>
                    <small class="form-hint">No learning-portal account.</small>
                <?php endif; ?>
            </div>
        </div>
    </div></div>

    <div class="grid-2" style="align-items:start;gap:1.25rem;">
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Personal</h3>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Date of birth</label>
                    <input class="form-control" type="date" name="dob" value="<?= $o('dob') ?>"></div>
                <div class="form-group"><label class="form-label">Gender</label>
                    <select class="form-select" name="gender">
                        <option value="">— Select —</option>
                        <?php foreach ($genders as $gk => $gl): ?><option value="<?= e($gk) ?>" <?= ($student['gender'] ?? '') === $gk ? 'selected' : '' ?>><?= e($gl) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Blood group</label>
                    <select class="form-select" name="blood_group">
                        <option value="">— Select —</option>
                        <?php foreach ($bloodGroups as $bg): ?><option value="<?= e($bg) ?>" <?= ($student['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= e($bg) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Category</label>
                    <input class="form-control" name="category" list="student-categories" value="<?= $o('category') ?>">
                    <datalist id="student-categories">
                        <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
                    </datalist></div>
            </div>
        </div></div>

        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Academic</h3>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Grade / class</label>
                    <input class="form-control" name="grade" value="<?= $o('grade') ?>" placeholder="e.g. Class 6"></div>
                <div class="form-group"><label class="form-label">Section</label>
                    <input class="form-control" name="section" value="<?= $o('section') ?>" placeholder="e.g. A"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Academic year</label>
                    <input class="form-control" name="academic_year" value="<?= $o('academic_year') ?>" placeholder="e.g. 2024-25"></div>
                <div class="form-group"><label class="form-label">NGO program</label>
                    <select class="form-select" name="program_id">
                        <option value="">— None —</option>
                        <?php foreach (school_programs() as $pid => $ptitle): ?>
                            <option value="<?= (int) $pid ?>" <?= (int) ($student['program_id'] ?? 0) === (int) $pid ? 'selected' : '' ?>><?= e($ptitle) ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>
            <div class="form-group"><label class="form-label">Previous school</label>
                <input class="form-control" name="previous_school" value="<?= $o('previous_school') ?>"></div>
        </div></div>

        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Family</h3>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Father's name</label>
                    <input class="form-control" name="father_name" value="<?= $o('father_name') ?>"></div>
                <div class="form-group"><label class="form-label">Mother's name</label>
                    <input class="form-control" name="mother_name" value="<?= $o('mother_name') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Guardian name</label>
                <input class="form-control" name="guardian_name" value="<?= $o('guardian_name') ?>"></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Guardian phone</label>
                    <input class="form-control" name="guardian_phone" value="<?= $o('guardian_phone') ?>"></div>
                <div class="form-group"><label class="form-label">Guardian email</label>
                    <input class="form-control" type="email" name="guardian_email" value="<?= $o('guardian_email') ?>"></div>
            </div>
        </div></div>

        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Contact</h3>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Phone</label>
                    <input class="form-control" name="phone" value="<?= $o('phone') ?>"></div>
                <div class="form-group"><label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" value="<?= $o('email') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Address</label>
                <input class="form-control" name="address" value="<?= $o('address') ?>"></div>
            <div class="grid-3">
                <div class="form-group"><label class="form-label">City</label>
                    <input class="form-control" name="city" value="<?= $o('city') ?>"></div>
                <div class="form-group"><label class="form-label">State</label>
                    <input class="form-control" name="state" value="<?= $o('state') ?>"></div>
                <div class="form-group"><label class="form-label">Pincode</label>
                    <input class="form-control" name="pincode" value="<?= $o('pincode') ?>"></div>
            </div>
        </div></div>
    </div>

    <div class="form-actions mt-3">
        <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save profile</button>
        <a class="btn btn-ghost" href="<?= e($selfUrl) ?>">Reset</a>
    </div>
</form>

<?php clear_old(); ?>

<!-- ===================================================================== DOCUMENTS -->
<div class="panel"><div class="panel-body">
    <h3 class="mb-3">Documents <span class="text-muted">(<?= count($documents) ?>)</span></h3>

    <?php if ($documents): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Title</th><th>Uploaded</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($documents as $d): ?>
            <tr>
                <td><strong><?= e($d['title']) ?></strong></td>
                <td><?= e(format_date($d['created_at'])) ?></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(secure_upload_url($d['file_path'])) ?>" target="_blank" rel="noopener" title="Open"><?= lucide('external-link') ?></a>
                    <form method="post" action="<?= e($selfUrl) ?>" data-confirm="Delete this document? This cannot be undone." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="doc_delete"><input type="hidden" name="doc_id" value="<?= (int) $d['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('file-text') ?></div>No documents uploaded yet.</div>
    <?php endif; ?>

    <form method="post" action="<?= e($selfUrl) ?>" enctype="multipart/form-data" class="mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="doc_add">
        <div class="grid-3" style="align-items:end;">
            <div class="form-group"><label class="form-label">Document title <span class="req">*</span></label>
                <input class="form-control" name="title" required placeholder="e.g. Birth certificate"></div>
            <div class="form-group"><label class="form-label">File <span class="req">*</span></label>
                <input class="form-control" type="file" name="file" required></div>
            <div class="form-group">
                <button class="btn btn-primary btn-block" type="submit"><?= lucide('upload') ?> Add document</button>
            </div>
        </div>
    </form>
</div></div>

<!-- ===================================================================== CERTIFICATES -->
<div class="panel"><div class="panel-body">
    <h3 class="mb-3">Certificates <span class="text-muted">(<?= count($certificates) ?>)</span></h3>
    <?php if ($certificates): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Serial</th><th>Title</th><th>Type</th><th>Issued</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($certificates as $c): ?>
            <tr>
                <td><?= e($c['serial'] ?? '—') ?></td>
                <td><strong><?= e($c['title']) ?></strong></td>
                <td><?= e(ucfirst((string) $c['type'])) ?></td>
                <td><?= e(format_date($c['issue_date'])) ?: '—' ?></td>
                <td><span class="pill <?= e($certPill((string) $c['status'])) ?>"><?= e(ucfirst((string) $c['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('award') ?></div>No certificates issued yet.</div>
    <?php endif; ?>
</div></div>

<!-- ===================================================================== MARKSHEETS -->
<div class="panel"><div class="panel-body">
    <h3 class="mb-3">Marksheets <span class="text-muted">(<?= count($marksheets) ?>)</span></h3>
    <?php if ($marksheets): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Title</th><th>Term</th><th>Year</th><th>Percentage</th><th>Grade</th><th>Result</th><th>Published</th></tr></thead>
        <tbody>
        <?php foreach ($marksheets as $m): ?>
            <tr>
                <td><strong><?= e($m['title']) ?></strong><?php if (!empty($m['serial'])): ?><br><small class="text-muted"><?= e($m['serial']) ?></small><?php endif; ?></td>
                <td><?= $m['term'] ? e($m['term']) : '—' ?></td>
                <td><?= $m['academic_year'] ? e($m['academic_year']) : '—' ?></td>
                <td><?= e(number_format((float) $m['percentage'], 2)) ?>%</td>
                <td><?= $m['grade'] ? e($m['grade']) : '—' ?></td>
                <td><span class="pill <?= e($resultPill((string) $m['result'])) ?>"><?= e(ucfirst((string) $m['result'])) ?></span></td>
                <td><?= !empty($m['published']) ? '<span class="pill pill-green">Published</span>' : '<span class="pill pill-gray">Draft</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('file-spreadsheet') ?></div>No marksheets yet.</div>
    <?php endif; ?>
</div></div>

<!-- ===================================================================== COURSE ENROLMENTS -->
<div class="panel"><div class="panel-body">
    <h3 class="mb-3">Course enrolments <span class="text-muted">(<?= count($enrollments) ?>)</span></h3>
    <?php if ($enrollments): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Course</th><th>Progress</th><th>Enrolled</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($enrollments as $en): ?>
            <tr>
                <td><strong><?= e($en['course_title']) ?></strong></td>
                <td><?= e(number_format((float) $en['progress_percent'], 0)) ?>%</td>
                <td><?= e(format_date($en['enrolled_at'])) ?: '—' ?></td>
                <td><span class="pill <?= e($enrPill((string) $en['status'])) ?>"><?= e(ucfirst((string) $en['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('book-open') ?></div>Not enrolled in any courses.</div>
    <?php endif; ?>
</div></div>

<!-- ===================================================================== ASSIGNMENT SUBMISSIONS -->
<div class="panel"><div class="panel-body">
    <h3 class="mb-3">Assignment submissions <span class="text-muted">(<?= count($submissions) ?>)</span></h3>
    <?php if ($submissions): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Assignment</th><th>Submitted</th><th>Marks</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($submissions as $sub): ?>
            <tr>
                <td><strong><?= e($sub['assignment_title']) ?></strong></td>
                <td><?= e(format_datetime($sub['submitted_at'])) ?: '—' ?></td>
                <td><?= $sub['marks'] !== null ? e(number_format((float) $sub['marks'], 0)) . ' / ' . e(number_format((float) $sub['assignment_max'], 0)) : '—' ?></td>
                <td><span class="pill <?= e($subPill((string) $sub['status'])) ?>"><?= e(ucfirst((string) $sub['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('clipboard-list') ?></div>No assignment submissions.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
