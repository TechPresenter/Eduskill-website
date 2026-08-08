<?php
/**
 * =============================================================================
 *  Admin — Exams & Quizzes (LMS assessment CRUD)
 * =============================================================================
 *  list · create · edit · delete. Each exam/quiz may belong to a course and
 *  owns a question bank (exam-questions) and a set of learner attempts
 *  (exam-attempts), both reached from the row actions. total_marks is derived
 *  from the question bank via exam_sync_total().
 *  Standard admin pattern: CSRF, prepared statements, pagination, stat cards,
 *  activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'exams';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$types = [
    'exam' => 'Exam',
    'quiz' => 'Quiz',
];
$statuses = [
    'draft'     => 'Draft',
    'published' => 'Published',
    'closed'    => 'Closed',
];

$courses = course_options();

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'Exam not found.');
        redirect('/admin/exams');
    }

    $ret = '/admin/exams?action=' . ($editId ? 'edit&id=' . $editId : 'create');

    if (clean(post('title')) === '') {
        flash_old($_POST);
        set_flash('error', 'Title is required.');
        redirect($ret);
    }

    // course_id must be a known course (or none).
    $courseId = (int) post('course_id', 0);
    if (!isset($courses[$courseId])) {
        $courseId = null;
    }

    // Normalise datetime-local values (YYYY-MM-DDTHH:MM) into DATETIME strings.
    $fromRaw   = trim((string) post('available_from', ''));
    $availFrom = null;
    if ($fromRaw !== '') {
        $ts = strtotime($fromRaw);
        if ($ts !== false) { $availFrom = date('Y-m-d H:i:s', $ts); }
    }
    $toRaw   = trim((string) post('available_to', ''));
    $availTo = null;
    if ($toRaw !== '') {
        $ts = strtotime($toRaw);
        if ($ts !== false) { $availTo = date('Y-m-d H:i:s', $ts); }
    }

    $data = [
        'title'          => clean(post('title')),
        'type'           => isset($types[(string) post('type')]) ? post('type') : 'exam',
        'course_id'      => $courseId,
        'description'    => trim((string) post('description')) ?: null,
        'duration_min'   => max(0, (int) post('duration_min')) ?: 30,
        'pass_marks'     => (float) post('pass_marks'),
        'shuffle'        => post('shuffle') ? 1 : 0,
        'max_attempts'   => max(1, (int) post('max_attempts')),
        'available_from' => $availFrom,
        'available_to'   => $availTo,
        'status'         => isset($statuses[(string) post('status')]) ? post('status') : 'draft',
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        exam_sync_total($editId); // keep total_marks in step with the question bank
        log_activity('update', 'exams', 'Updated exam #' . $editId . ' (' . $data['title'] . ')');
        set_flash('success', 'Exam updated.');
        redirect('/admin/exams');
    }
    $newId = db_insert($table, $data);
    exam_sync_total($newId);
    log_activity('create', 'exams', 'Created exam #' . $newId . ' (' . $data['title'] . ')');
    set_flash('success', 'Exam created. Add questions to build the paper.');
    redirect('/admin/exam-questions?exam=' . $newId);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]); // questions + attempts cascade
        log_activity('delete', 'exams', 'Deleted exam #' . $delId . ' (' . ($row['title'] ?? '') . ')');
        set_flash('success', 'Exam and all its questions and attempts deleted.');
    }
    redirect('/admin/exams');
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Exam not found.');
        redirect('/admin/exams');
    }

    $curType   = (string) old('type', $row['type'] ?? 'exam');
    $curCourse = (int) old('course_id', $row['course_id'] ?? 0);
    $curStatus = (string) old('status', $row['status'] ?? 'draft');

    // datetime-local values for the inputs (prefer flashed old input).
    $fromValue = old('available_from', '');
    if ($fromValue === '' && !empty($row['available_from'])) {
        $ts = strtotime((string) $row['available_from']);
        if ($ts !== false) { $fromValue = date('Y-m-d\TH:i', $ts); }
    }
    $toValue = old('available_to', '');
    if ($toValue === '' && !empty($row['available_to'])) {
        $ts = strtotime((string) $row['available_to']);
        if ($ts !== false) { $toValue = date('Y-m-d\TH:i', $ts); }
    }

    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));
    $page_title = $action === 'edit' ? 'Edit Exam' : 'New Exam';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Exams &amp; quizzes / <?= $action === 'edit' ? 'Edit' : 'New' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('exams')) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('exams')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Assessment</h3>

                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                        <input class="form-control" name="title" required value="<?= $o('title') ?>" placeholder="e.g. Unit 1 — Fractions Quiz"></div>
                    <div class="form-group"><label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <?php foreach ($types as $k => $l): ?>
                                <option value="<?= e($k) ?>" <?= $curType === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>

                <div class="form-group"><label class="form-label">Course</label>
                    <select class="form-select" name="course_id">
                        <option value="">None</option>
                        <?php foreach ($courses as $cid => $ctitle): ?>
                            <option value="<?= (int) $cid ?>" <?= $curCourse === (int) $cid ? 'selected' : '' ?>><?= e($ctitle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$courses): ?><small class="form-hint">No courses yet — this assessment can stand alone.</small><?php endif; ?>
                </div>

                <div class="form-group"><label class="form-label">Description</label>
                    <textarea class="form-textarea" name="description" style="min-height:110px;" placeholder="Instructions shown to learners before they start."><?= e($row['description'] ?? old('description', '')) ?></textarea></div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Rules &amp; availability</h3>

                <div class="grid-3">
                    <div class="form-group"><label class="form-label">Duration (min)</label>
                        <input class="form-control" type="number" name="duration_min" min="0" value="<?= $o('duration_min', 30) ?>"></div>
                    <div class="form-group"><label class="form-label">Pass marks</label>
                        <input class="form-control" type="number" name="pass_marks" min="0" step="0.01" value="<?= $o('pass_marks', '0') ?>"></div>
                    <div class="form-group"><label class="form-label">Max attempts</label>
                        <input class="form-control" type="number" name="max_attempts" min="1" value="<?= $o('max_attempts', 1) ?>"></div>
                </div>

                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Available from</label>
                        <input class="form-control" type="datetime-local" name="available_from" value="<?= e($fromValue) ?>"></div>
                    <div class="form-group"><label class="form-label">Available to</label>
                        <input class="form-control" type="datetime-local" name="available_to" value="<?= e($toValue) ?>"></div>
                </div>

                <div class="form-group"><label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $k => $l): ?>
                            <option value="<?= e($k) ?>" <?= $curStatus === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select></div>

                <div class="form-group">
                    <label class="checkbox"><input type="checkbox" name="shuffle" value="1" <?= !empty($row['shuffle']) ? 'checked' : '' ?>> Shuffle question order for each attempt</label>
                </div>

                <?php if ($action === 'edit'): ?>
                    <p class="form-hint">Total marks (<?= e(money((float) ($row['total_marks'] ?? 0), '', 2)) ?>) are calculated from the question bank.</p>
                <?php endif; ?>

                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> exam</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('exams')) ?>">Cancel</a>
                </div>
            </div></div>
        </div>
    </form>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title   = 'Exams & Quizzes';
$search       = trim((string) get('q', ''));
$typeFilter   = (string) get('type', '');
$statusFilter = (string) get('status', '');

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND e.title LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
if (isset($types[$typeFilter]))      { $where .= ' AND e.type = :ty';   $params[':ty'] = $typeFilter; }
if (isset($statuses[$statusFilter])) { $where .= ' AND e.status = :st'; $params[':st'] = $statusFilter; }

$p = paginate(
    "SELECT e.*,
            c.title AS course_title,
            (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) AS question_count,
            (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.id) AS attempt_count
       FROM exams e
       LEFT JOIN courses c ON c.id = e.course_id
      WHERE $where
      ORDER BY e.id DESC",
    $params, 15
);

$counts = [
    'all'       => db_count('exams'),
    'published' => db_count('exams', "status = 'published'"),
    'quizzes'   => db_count('exams', "type = 'quiz'"),
    'attempts'  => db_count('exam_attempts'),
];

$hasFilter = $search !== '' || $typeFilter !== '' || $statusFilter !== '';

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Exams &amp; Quizzes</h1><span class="muted"><?= (int) $counts['all'] ?> assessments</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('exams?action=create')) ?>">+ New Exam</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('clipboard-list') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Assessments</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['published'] ?></div><div class="stat-label">Published</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('list-checks') ?></div><div><div class="stat-value"><?= (int) $counts['quizzes'] ?></div><div class="stat-label">Quizzes</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $counts['attempts'] ?></div><div class="stat-label">Attempts</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('exams')) ?>">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search title…">
        </form>
        <form method="get" action="<?= e(admin_url('exams')) ?>" class="flex" style="gap:.5rem;">
            <select class="form-select" name="type" onchange="this.form.submit()">
                <option value="">All types</option>
                <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $typeFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($hasFilter): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('exams')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Title</th><th>Type</th><th>Course</th><th>Questions</th><th>Marks</th><th>Attempts</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): ?>
            <tr>
                <td><strong><?= e($r['title']) ?></strong></td>
                <td><?= e($types[$r['type']] ?? ucfirst((string) $r['type'])) ?></td>
                <td><?= $r['course_title'] ? e($r['course_title']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= (int) $r['question_count'] ?></td>
                <td><?= e(money((float) $r['total_marks'], '', 2)) ?></td>
                <td><?= (int) $r['attempt_count'] ?></td>
                <td><span class="pill <?= e(exam_status_pill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? ucfirst((string) $r['status'])) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('exam-questions?exam=' . $r['id'])) ?>" title="Questions"><?= lucide('list-checks') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('exam-attempts?exam=' . $r['id'])) ?>" title="Attempts"><?= lucide('users') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('exams?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('exams')) ?>" data-confirm="Delete this exam and ALL its questions and attempts? This cannot be undone." style="display:inline;">
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
            <?= $hasFilter ? 'No assessments match your filters. ' : 'No exams or quizzes yet. ' ?>
            <a href="<?= e(admin_url('exams?action=create')) ?>">Create one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
