<?php
/**
 * =============================================================================
 *  Admin — Assignments & Grading
 * =============================================================================
 *  list · create · edit · view (submissions + grade) · delete. An assignment may
 *  be scoped to a school, a batch and/or a course. Students submit a file and/or
 *  text; staff grade each submission from the view page. Standard admin pattern:
 *  CSRF-guarded POST handlers, prepared statements, pagination + search + filters,
 *  stat cards, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'assignments';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$statuses    = ['open' => 'Open', 'closed' => 'Closed'];
$subStatuses = ['submitted' => 'Submitted', 'graded' => 'Graded', 'returned' => 'Returned', 'late' => 'Late'];

/** Submission status → admin pill class. */
$subPill = static fn(string $s): string =>
    ['submitted' => 'pill-blue', 'graded' => 'pill-green', 'returned' => 'pill-amber', 'late' => 'pill-red'][$s] ?? 'pill-gray';

/** Normalise a datetime-local value ("Y-m-dTH:i") to a DB datetime, or null. */
function assignment_due_datetime($v): ?string
{
    $v = trim((string) $v);
    if ($v === '') {
        return null;
    }
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) { // Y-m-d H:i → add seconds
        $v .= ':00';
    }
    return $v;
}

/* =============================================================================
 |  SAVE (create / edit)
 |============================================================================*/
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'Assignment not found.');
        redirect('/admin/assignments');
    }

    $title = clean(post('title'));
    if ($title === '') {
        flash_old($_POST);
        set_flash('error', 'Assignment title is required.');
        redirect('/admin/assignments?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // Scope: keep each id only when it resolves to a real row, else null.
    $schoolId = (int) post('school_id', 0) ?: null;
    if ($schoolId !== null && !find('schools', $schoolId)) {
        $schoolId = null;
    }
    $batchId = (int) post('batch_id', 0) ?: null;
    if ($batchId !== null && !find('school_batches', $batchId)) {
        $batchId = null;
    }
    $courseId = (int) post('course_id', 0) ?: null;
    if ($courseId !== null && !find('courses', $courseId)) {
        $courseId = null;
    }

    $maxMarks = (float) post('max_marks', 100);
    if ($maxMarks <= 0) {
        $maxMarks = 100.0;
    }

    $data = [
        'school_id'   => $schoolId,
        'batch_id'    => $batchId,
        'course_id'   => $courseId,
        'title'       => $title,
        'description' => trim((string) post('description')) ?: null,
        'max_marks'   => $maxMarks,
        'due_date'    => assignment_due_datetime(post('due_date')),
        'status'      => isset($statuses[(string) post('status')]) ? post('status') : 'open',
    ];

    if (!empty($_FILES['attachment']['name'])) {
        $up = upload_document($_FILES['attachment'], 'assignments');
        if (!$up['success']) {
            flash_old($_POST);
            set_flash('error', 'Attachment: ' . $up['error']);
            redirect('/admin/assignments?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['attachment'] = $up['path'];
        if ($editId && !empty($old['attachment'])) { delete_upload($old['attachment']); }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'assignments', 'Updated assignment #' . $editId);
        set_flash('success', 'Assignment updated.');
        redirect('/admin/assignments?action=view&id=' . $editId);
    }

    $data['created_by'] = $_SESSION['user']['id'] ?? null;
    $newId = db_insert($table, $data);
    log_activity('create', 'assignments', 'Created assignment #' . $newId . ' (' . $title . ')');
    set_flash('success', 'Assignment created.');
    redirect('/admin/assignments?action=view&id=' . $newId);
}

/* =============================================================================
 |  DELETE
 |============================================================================*/
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        if (!empty($row['attachment'])) { delete_upload($row['attachment']); }
        db_delete($table, 'id = :id', [':id' => $delId]); // submissions cascade
        log_activity('delete', 'assignments', 'Deleted assignment #' . $delId . ' (' . ($row['title'] ?? '') . ')');
        set_flash('success', 'Assignment and all its submissions deleted.');
    }
    redirect('/admin/assignments');
}

/* =============================================================================
 |  GRADE a submission
 |============================================================================*/
if (is_post() && post('_do') === 'grade') {
    require_csrf();
    $subId = (int) post('sub_id', 0);
    $sub   = find('assignment_submissions', $subId);
    if (!$sub) {
        set_flash('error', 'Submission not found.');
        redirect('/admin/assignments');
    }
    $asgId    = (int) $sub['assignment_id'];
    $asg      = find($table, $asgId);
    $maxMarks = (float) ($asg['max_marks'] ?? 100);

    $marks = max(0.0, min($maxMarks, (float) post('marks', 0)));
    db_update('assignment_submissions', [
        'marks'     => $marks,
        'feedback'  => clean(post('feedback')) ?: null,
        'status'    => 'graded',
        'graded_by' => $_SESSION['user']['id'] ?? null,
        'graded_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', [':id' => $subId]);
    log_activity('grade', 'assignments', 'Graded submission #' . $subId . ' (assignment #' . $asgId . ')');
    set_flash('success', 'Submission graded.');
    redirect('/admin/assignments?action=view&id=' . $asgId);
}

/* =============================================================================
 |  CREATE / EDIT FORM
 |============================================================================*/
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Assignment not found.');
        redirect('/admin/assignments');
    }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));

    $schoolsList = school_options();
    $batches     = db_all('SELECT id, name FROM school_batches ORDER BY name');
    $courses     = course_options();

    $dueRaw   = old('due_date', $row['due_date'] ?? '');
    $dueLocal = $dueRaw ? str_replace(' ', 'T', substr((string) $dueRaw, 0, 16)) : '';

    $page_title = $action === 'edit' ? 'Edit Assignment' : 'New Assignment';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Assignments / <?= $action === 'edit' ? 'Edit' : 'New' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('assignments')) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('assignments')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Assignment</h3>
                <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" required value="<?= $o('title') ?>"></div>
                <div class="form-group"><label class="form-label">Description</label>
                    <textarea class="form-textarea" name="description" style="min-height:120px;"><?= e(old('description', $row['description'] ?? '')) ?></textarea></div>
                <div class="form-group"><label class="form-label">Attachment</label>
                    <input class="form-control" type="file" name="attachment">
                    <?php if (!empty($row['attachment'])): ?>
                        <small class="form-hint">Current: <a href="<?= e(upload_url($row['attachment'])) ?>" target="_blank" rel="noopener">view file</a> — kept unless replaced.</small>
                    <?php endif; ?>
                </div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Scope & grading</h3>
                <div class="form-group"><label class="form-label">School</label>
                    <select class="form-select" name="school_id">
                        <option value="">— None —</option>
                        <?php foreach ($schoolsList as $sid => $sname): ?>
                            <option value="<?= (int) $sid ?>" <?= (int) old('school_id', $row['school_id'] ?? 0) === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Batch</label>
                    <select class="form-select" name="batch_id">
                        <option value="">— None —</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= (int) old('batch_id', $row['batch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Course</label>
                    <select class="form-select" name="course_id">
                        <option value="">— None —</option>
                        <?php foreach ($courses as $cid => $ctitle): ?>
                            <option value="<?= (int) $cid ?>" <?= (int) old('course_id', $row['course_id'] ?? 0) === (int) $cid ? 'selected' : '' ?>><?= e($ctitle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Leave the scope fields blank for a general assignment.</small>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Max marks</label>
                        <input class="form-control" type="number" name="max_marks" min="0" step="0.01" value="<?= e(old('max_marks', $row['max_marks'] ?? '100')) ?>"></div>
                    <div class="form-group"><label class="form-label">Due date</label>
                        <input class="form-control" type="datetime-local" name="due_date" value="<?= e($dueLocal) ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['status'] ?? 'open') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> assignment</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('assignments')) ?>">Cancel</a>
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
 |  VIEW (submissions + grade)
 |============================================================================*/
if ($action === 'view') {
    $asg = db_row(
        "SELECT a.*, sc.name AS school_name, b.name AS batch_name, c.title AS course_title
           FROM assignments a
           LEFT JOIN schools sc        ON sc.id = a.school_id
           LEFT JOIN school_batches b  ON b.id  = a.batch_id
           LEFT JOIN courses c         ON c.id  = a.course_id
          WHERE a.id = :id",
        [':id' => $id]
    );
    if (!$asg) {
        set_flash('error', 'Assignment not found.');
        redirect('/admin/assignments');
    }

    $subs = db_all(
        "SELECT sub.*, st.name AS student_name, st.student_code AS student_code
           FROM assignment_submissions sub
           LEFT JOIN school_students st ON st.id = sub.student_id
          WHERE sub.assignment_id = :a
          ORDER BY (sub.submitted_at IS NULL), sub.submitted_at DESC, sub.id DESC",
        [':a' => $id]
    );
    $maxMarks = (float) $asg['max_marks'];
    $gradedCt = 0;
    foreach ($subs as $s) { if (($s['status'] ?? '') === 'graded') { $gradedCt++; } }

    $scope = array_filter([$asg['school_name'] ?? null, $asg['batch_name'] ?? null, $asg['course_title'] ?? null]);

    $page_title = 'Assignment · ' . $asg['title'];
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($asg['title']) ?></h1>
            <span class="muted"><?= $scope ? e(implode(' · ', $scope)) : 'General' ?> · <?= count($subs) ?> submission<?= count($subs) === 1 ? '' : 's' ?></span></div>
        <div class="flex" style="gap:.5rem;">
            <a class="btn btn-outline" href="<?= e(admin_url('assignments?action=edit&id=' . $id)) ?>"><?= lucide('pencil') ?> Edit</a>
            <a class="btn btn-secondary" href="<?= e(admin_url('assignments')) ?>">← Back to list</a>
        </div>
    </div>

    <div class="grid-3" style="align-items:start;gap:1.25rem;margin-bottom:1.25rem;">
        <div class="panel" style="grid-column:span 2;"><div class="panel-body">
            <h3 class="mb-3">Details</h3>
            <?php if (!empty($asg['description'])): ?>
                <p style="white-space:pre-wrap;margin-top:0;"><?= e($asg['description']) ?></p>
            <?php else: ?>
                <p class="text-muted">No description.</p>
            <?php endif; ?>
            <?php if (!empty($asg['attachment'])): ?>
                <p><a class="btn btn-outline btn-sm" href="<?= e(upload_url($asg['attachment'])) ?>" target="_blank" rel="noopener"><?= lucide('paperclip') ?> Attachment</a></p>
            <?php endif; ?>
        </div></div>
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Summary</h3>
            <p class="flex items-center justify-between"><span class="text-muted">Status</span>
                <span class="pill <?= ($asg['status'] === 'open') ? 'pill-green' : 'pill-gray' ?>"><?= e($statuses[$asg['status']] ?? $asg['status']) ?></span></p>
            <p class="flex items-center justify-between"><span class="text-muted">Due date</span>
                <strong><?= $asg['due_date'] ? e(format_datetime($asg['due_date'])) : '—' ?></strong></p>
            <p class="flex items-center justify-between"><span class="text-muted">Max marks</span>
                <strong><?= e((string) (float) $maxMarks) ?></strong></p>
            <p class="flex items-center justify-between"><span class="text-muted">Graded</span>
                <strong><?= (int) $gradedCt ?> / <?= count($subs) ?></strong></p>
        </div></div>
    </div>

    <div class="panel"><div class="panel-body">
        <h3 class="mb-3">Submissions</h3>
        <?php if ($subs): ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th>Student</th><th>Submission</th><th>Submitted</th><th>Status</th><th style="width:340px;">Grade</th></tr></thead>
            <tbody>
            <?php foreach ($subs as $s): ?>
                <tr>
                    <td><strong><?= e($s['student_name'] ?? '—') ?></strong><br><small class="text-muted"><?= e($s['student_code'] ?? '—') ?></small></td>
                    <td>
                        <?php if (!empty($s['file_path'])): ?>
                            <a href="<?= e(upload_url($s['file_path'])) ?>" target="_blank" rel="noopener"><?= lucide('file') ?> File</a>
                        <?php endif; ?>
                        <?php if (!empty($s['text'])): ?>
                            <div class="text-muted" style="white-space:pre-wrap;max-width:320px;font-size:.85rem;margin-top:.25rem;"><?= e($s['text']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($s['file_path']) && empty($s['text'])): ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td><?= $s['submitted_at'] ? e(format_datetime($s['submitted_at'])) : '—' ?></td>
                    <td><span class="pill <?= e($subPill((string) $s['status'])) ?>"><?= e($subStatuses[$s['status']] ?? $s['status']) ?></span>
                        <?php if ($s['marks'] !== null): ?><br><small class="text-muted"><?= e((string) (float) $s['marks']) ?> / <?= e((string) (float) $maxMarks) ?></small><?php endif; ?></td>
                    <td>
                        <form method="post" action="<?= e(admin_url('assignments')) ?>" class="flex items-center" style="gap:.4rem;flex-wrap:wrap;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_do" value="grade">
                            <input type="hidden" name="sub_id" value="<?= (int) $s['id'] ?>">
                            <input class="form-control" type="number" name="marks" min="0" max="<?= e((string) (float) $maxMarks) ?>" step="0.01"
                                   value="<?= $s['marks'] !== null ? e((string) (float) $s['marks']) : '' ?>" placeholder="Marks" style="width:90px;" required>
                            <input class="form-control" name="feedback" value="<?= e((string) ($s['feedback'] ?? '')) ?>" placeholder="Feedback" style="flex:1;min-width:120px;">
                            <button class="btn btn-primary btn-sm" type="submit">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('inbox') ?></div>No submissions yet.</div>
        <?php endif; ?>
    </div></div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* =============================================================================
 |  LIST
 |============================================================================*/
$page_title   = 'Assignments';
$search       = trim((string) get('q', ''));
$schoolFilter = (int) get('school', 0);
$statusFilter = (string) get('status', '');

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', a.title, a.description) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
if ($schoolFilter > 0) {
    $where .= ' AND a.school_id = :sid';
    $params[':sid'] = $schoolFilter;
}
if (isset($statuses[$statusFilter])) {
    $where .= ' AND a.status = :st';
    $params[':st'] = $statusFilter;
}

$p = paginate(
    "SELECT a.*, sc.name AS school_name, b.name AS batch_name, c.title AS course_title,
            (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.id) AS submission_count
       FROM assignments a
       LEFT JOIN schools sc        ON sc.id = a.school_id
       LEFT JOIN school_batches b  ON b.id  = a.batch_id
       LEFT JOIN courses c         ON c.id  = a.course_id
      WHERE $where ORDER BY a.id DESC",
    $params, 15
);

$counts = [
    'all'         => db_count('assignments'),
    'open'        => db_count('assignments', "status = 'open'"),
    'closed'      => db_count('assignments', "status = 'closed'"),
    'submissions' => db_count('assignment_submissions'),
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Assignments</h1><span class="muted"><?= (int) $counts['all'] ?> assignments</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('assignments?action=create')) ?>">+ New Assignment</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('clipboard-list') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Assignments</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('book-open') ?></div><div><div class="stat-value"><?= (int) $counts['open'] ?></div><div class="stat-label">Open</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('lock') ?></div><div><div class="stat-value"><?= (int) $counts['closed'] ?></div><div class="stat-label">Closed</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('inbox') ?></div><div><div class="stat-value"><?= (int) $counts['submissions'] ?></div><div class="stat-label">Submissions</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('assignments')) ?>">
            <?php if ($schoolFilter): ?><input type="hidden" name="school" value="<?= (int) $schoolFilter ?>"><?php endif; ?>
            <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search title, description…">
        </form>
        <form method="get" action="<?= e(admin_url('assignments')) ?>" class="flex" style="gap:.5rem;">
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            <select class="form-select" name="school" onchange="this.form.submit()">
                <option value="">All schools</option>
                <?php foreach (school_options() as $sid => $sname): ?><option value="<?= (int) $sid ?>" <?= $schoolFilter === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($search !== '' || $schoolFilter || $statusFilter !== ''): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('assignments')) ?>">Clear</a>
        <?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Assignment</th><th>Scope</th><th>Due</th><th>Max</th><th>Submissions</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $viewUrl = admin_url('assignments?action=view&id=' . $r['id']);
            $scope   = array_filter([$r['school_name'] ?? null, $r['batch_name'] ?? null, $r['course_title'] ?? null]);
        ?>
            <tr>
                <td><a href="<?= e($viewUrl) ?>"><strong><?= e($r['title']) ?></strong></a></td>
                <td><?= $scope ? e(implode(' · ', $scope)) : '<span class="text-muted">General</span>' ?></td>
                <td><?= $r['due_date'] ? e(format_datetime($r['due_date'], 'd M Y, h:i A')) : '<span class="text-muted">—</span>' ?></td>
                <td><?= e((string) (float) $r['max_marks']) ?></td>
                <td><?= (int) $r['submission_count'] ?></td>
                <td><span class="pill <?= $r['status'] === 'open' ? 'pill-green' : 'pill-gray' ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e($viewUrl) ?>" title="View"><?= lucide('eye') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('assignments?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('assignments')) ?>" data-confirm="Delete this assignment and ALL its submissions? This cannot be undone." style="display:inline;">
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
            <?= ($search !== '' || $schoolFilter || $statusFilter !== '') ? 'No assignments match your filters. ' : 'No assignments yet. ' ?>
            <a href="<?= e(admin_url('assignments?action=create')) ?>">Create one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
