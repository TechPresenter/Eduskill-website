<?php
/**
 * =============================================================================
 *  Admin — Course Enrolments (students enrolled on a course)
 * =============================================================================
 *  Scoped to a single course via ?course=<id> (required). Enrol a school
 *  student (optionally into a course batch) via lms_enroll(), list enrolments
 *  with progress / status / certificate, and drop or delete an enrolment.
 *  Standard admin pattern: CSRF, prepared statements, pagination, stat cards,
 *  activity logging, flash + redirect. The ?course context is preserved in
 *  every link and redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table    = 'course_enrollments';
$courseId = (int) get('course', 0);

/* Parent course is mandatory — bail to the courses list if missing/stale. */
$course = $courseId ? find('courses', $courseId) : null;
if (!$course) {
    set_flash('error', 'Select a course to manage its enrolments.');
    redirect('/admin/courses');
}

$statuses = [
    'active'    => 'Active',
    'completed' => 'Completed',
    'dropped'   => 'Dropped',
];

function enrollment_status_pill(string $s): string
{
    return ['active' => 'pill-green', 'completed' => 'pill-blue', 'dropped' => 'pill-red'][$s] ?? 'pill-gray';
}

/* Course batches for the enrol form + list filter: [id => name]. */
$batches = db_all('SELECT id, name FROM course_batches WHERE course_id = :c ORDER BY name', [':c' => $courseId]);
$batchMap = [];
foreach ($batches as $b) { $batchMap[(int) $b['id']] = $b['name']; }

$backQ = '?course=' . $courseId;

/* -------------------------------------------------------------- ENROL */
if (is_post() && post('_do') === 'enroll') {
    require_csrf();

    $studentId = (int) post('student_id', 0);
    $batchId   = (int) post('batch_id', 0) ?: null;

    // Student must exist.
    $student = $studentId ? find('school_students', $studentId) : null;
    if (!$student) {
        set_flash('error', 'Please select a valid student.');
        redirect('/admin/course-enrollments' . $backQ);
    }
    // Batch (if given) must belong to this course.
    if ($batchId !== null && !isset($batchMap[$batchId])) {
        $batchId = null;
    }

    $existing = lms_find_enrollment($courseId, null, $studentId);
    lms_enroll($courseId, ['student_id' => $studentId, 'batch_id' => $batchId]);

    if ($existing) {
        set_flash('error', $student['name'] . ' is already enrolled on this course.');
    } else {
        log_activity('create', 'courses', 'Enrolled student #' . $studentId . ' on course #' . $courseId);
        set_flash('success', $student['name'] . ' enrolled.');
    }
    redirect('/admin/course-enrollments' . $backQ);
}

/* -------------------------------------------------------------- DROP */
if (is_post() && post('_do') === 'drop') {
    require_csrf();
    $eid = (int) post('id', 0);
    $enr = find($table, $eid);
    if ($enr && (int) $enr['course_id'] === $courseId) {
        db_update($table, ['status' => 'dropped'], 'id = :id', [':id' => $eid]);
        log_activity('update', 'courses', 'Dropped enrolment #' . $eid . ' on course #' . $courseId);
        set_flash('success', 'Enrolment dropped.');
    }
    redirect('/admin/course-enrollments' . $backQ);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $eid = (int) post('id', 0);
    $enr = find($table, $eid);
    if ($enr && (int) $enr['course_id'] === $courseId) {
        db_delete($table, 'id = :id', [':id' => $eid]); // lesson_progress cascades
        log_activity('delete', 'courses', 'Deleted enrolment #' . $eid . ' on course #' . $courseId);
        set_flash('success', 'Enrolment deleted.');
    }
    redirect('/admin/course-enrollments' . $backQ);
}

/* -------------------------------------------------------------- LIST */
$page_title   = 'Course Enrolments';
$statusFilter = (string) get('status', '');
$batchFilter  = (int) get('batch', 0);

$where  = 'ce.course_id = :course';
$params = [':course' => $courseId];
if (isset($statuses[$statusFilter])) {
    $where .= ' AND ce.status = :st';
    $params[':st'] = $statusFilter;
}
if ($batchFilter && isset($batchMap[$batchFilter])) {
    $where .= ' AND ce.batch_id = :bt';
    $params[':bt'] = $batchFilter;
}

$p = paginate(
    "SELECT ce.*, ss.name AS student_name, ss.student_code, cb.name AS batch_name
       FROM course_enrollments ce
       LEFT JOIN school_students ss ON ss.id = ce.student_id
       LEFT JOIN course_batches cb ON cb.id = ce.batch_id
      WHERE $where
      ORDER BY ce.enrolled_at DESC, ce.id DESC",
    $params, 20
);

$counts = [
    'enrolled'  => db_count($table, 'course_id = :c', [':c' => $courseId]),
    'completed' => db_count($table, "course_id = :c AND status = 'completed'", [':c' => $courseId]),
];
$avgProgress = (float) db_value(
    "SELECT COALESCE(AVG(progress_percent),0) FROM course_enrollments WHERE course_id = :c",
    [':c' => $courseId]
);

$hasFilter = $statusFilter !== '' || $batchFilter > 0;

/* All students for the enrol dropdown. */
$students = db_all('SELECT id, name, student_code FROM school_students ORDER BY name');

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Enrolments</h1>
        <span class="muted"><?= e($course['title']) ?> · <?= (int) $counts['enrolled'] ?> enrolled</span>
    </div>
    <a class="btn btn-secondary" href="<?= e(admin_url('courses')) ?>">← Courses</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $counts['enrolled'] ?></div><div class="stat-label">Enrolled</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['completed'] ?></div><div class="stat-label">Completed</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('trending-up') ?></div><div><div class="stat-value"><?= (int) round($avgProgress) ?>%</div><div class="stat-label">Avg progress</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <h3 class="mb-3">Enrol a student</h3>
    <form class="admin-form" method="post" action="<?= e(admin_url('course-enrollments' . $backQ)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="enroll">
        <div class="grid-3" style="align-items:end;">
            <div class="form-group"><label class="form-label">Student <span class="req">*</span></label>
                <select class="form-select" name="student_id" required>
                    <option value="">— Select student —</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?><?= $s['student_code'] ? ' (' . e($s['student_code']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$students): ?><small class="form-hint">No students registered yet.</small><?php endif; ?>
            </div>
            <div class="form-group"><label class="form-label">Batch</label>
                <select class="form-select" name="batch_id">
                    <option value="">None</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <button class="btn btn-primary" type="submit"><?= lucide('user-plus') ?> Enrol</button>
            </div>
        </div>
    </form>
</div></div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form method="get" action="<?= e(admin_url('course-enrollments')) ?>" class="flex" style="gap:.5rem;">
            <input type="hidden" name="course" value="<?= (int) $courseId ?>">
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($batches): ?>
            <select class="form-select" name="batch" onchange="this.form.submit()">
                <option value="0">All batches</option>
                <?php foreach ($batches as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= $batchFilter === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </form>
        <?php if ($hasFilter): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('course-enrollments' . $backQ)) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Student</th><th>Batch</th><th>Progress</th><th>Status</th><th>Enrolled</th><th>Completed</th><th>Certificate</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $name = $r['student_name'] ?: ($r['member_id'] ? 'Member #' . (int) $r['member_id'] : '—');
        ?>
            <tr>
                <td>
                    <strong><?= e($name) ?></strong>
                    <?php if (!empty($r['student_code'])): ?><br><small class="text-muted"><?= e($r['student_code']) ?></small><?php endif; ?>
                </td>
                <td><?= $r['batch_name'] ? e($r['batch_name']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= (int) round((float) $r['progress_percent']) ?>%</td>
                <td><span class="pill <?= e(enrollment_status_pill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? ucfirst($r['status'])) ?></span></td>
                <td><?= $r['enrolled_at'] ? e(format_date($r['enrolled_at'], 'd M Y')) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $r['completed_at'] ? e(format_date($r['completed_at'], 'd M Y')) : '<span class="text-muted">—</span>' ?></td>
                <td>
                    <?php if (!empty($r['certificate_id'])): ?>
                        <a class="icon-btn" href="<?= e(admin_url('certificate-view?id=' . (int) $r['certificate_id'])) ?>" title="View certificate"><?= lucide('award') ?></a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td><div class="actions">
                    <?php if ($r['status'] !== 'dropped'): ?>
                    <form method="post" action="<?= e(admin_url('course-enrollments' . $backQ)) ?>" data-confirm="Mark this enrolment as dropped?" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="drop"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn" type="submit" title="Drop"><?= lucide('user-minus') ?></button>
                    </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(admin_url('course-enrollments' . $backQ)) ?>" data-confirm="Delete this enrolment and its lesson progress? This cannot be undone." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('users') ?></div>
            <?= $hasFilter ? 'No enrolments match your filters.' : 'No students enrolled yet. Use the form above to enrol one.' ?></div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
