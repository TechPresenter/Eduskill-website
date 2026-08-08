<?php
/**
 * =============================================================================
 *  My Assignments — student portal (view + submit)
 * =============================================================================
 *  Behind require_member(). Shows assignments for the student's enrolled
 *  courses / batch / school, with the student's own submission + a submit form.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_member('/login');

$sess    = current_member();
$mid     = (int) ($sess['id'] ?? 0);
$student = db_row('SELECT * FROM school_students WHERE member_id = :m LIMIT 1', [':m' => $mid]);
$sid     = $student ? (int) $student['id'] : 0;

$enrolledCourseIds = array_map(static fn($r) => (int) $r['course_id'],
    db_all('SELECT course_id FROM course_enrollments WHERE member_id = :m OR student_id = :s', [':m' => $mid, ':s' => $sid]));

/* -------- Submit an assignment -------- */
if (is_post() && post('_do') === 'submit') {
    require_csrf();
    if (!$sid) {
        set_flash('error', 'A student record is required to submit assignments.');
        redirect('/my-assignments');
    }
    $assignmentId = (int) post('assignment_id', 0);
    $assignment   = find('assignments', $assignmentId);
    if (!$assignment) {
        set_flash('error', 'Assignment not found.');
        redirect('/my-assignments');
    }
    // Object-level authz: only submit to an assignment the student can actually
    // see (mirror the list scope), so a crafted assignment_id can't be attached to.
    $inScope =
        (!empty($assignment['course_id']) && in_array((int) $assignment['course_id'], $enrolledCourseIds, true))
        || ($student && !empty($student['batch_id'])  && (int) $assignment['batch_id']  === (int) $student['batch_id'])
        || ($student && !empty($student['school_id']) && (int) $assignment['school_id'] === (int) $student['school_id']);
    if (!$inScope) {
        set_flash('error', 'Assignment not found.');
        redirect('/my-assignments');
    }
    $path = null;
    if (!empty($_FILES['file']['name'])) {
        $up = upload_document($_FILES['file'], 'assignments');
        if (!$up['success']) {
            set_flash('error', 'File: ' . $up['error']);
            redirect('/my-assignments');
        }
        $path = $up['path'];
    }
    $late    = !empty($assignment['due_date']) && strtotime((string) $assignment['due_date']) < time();
    $existing = db_row('SELECT id FROM assignment_submissions WHERE assignment_id = :a AND student_id = :s LIMIT 1', [':a' => $assignmentId, ':s' => $sid]);
    $data = [
        'text'         => clean(post('text')) ?: null,
        'submitted_at' => date('Y-m-d H:i:s'),
        'status'       => $late ? 'late' : 'submitted',
    ];
    if ($path) { $data['file_path'] = $path; }
    if ($existing) {
        db_update('assignment_submissions', $data, 'id = :id', [':id' => (int) $existing['id']]);
    } else {
        db_insert('assignment_submissions', array_merge($data, ['assignment_id' => $assignmentId, 'student_id' => $sid]));
    }
    set_flash('success', 'Submitted successfully.');
    redirect('/my-assignments');
}

// Relevant assignments.
$conds  = [];
$params = [];
if ($enrolledCourseIds) {
    $conds[] = 'course_id IN (' . implode(',', array_map('intval', $enrolledCourseIds)) . ')';
}
if ($student && !empty($student['batch_id'])) { $conds[] = 'batch_id = :b'; $params[':b'] = (int) $student['batch_id']; }
if ($student && !empty($student['school_id'])) { $conds[] = 'school_id = :sc'; $params[':sc'] = (int) $student['school_id']; }

$assignments = [];
if ($conds) {
    $assignments = db_all(
        "SELECT * FROM assignments WHERE (" . implode(' OR ', $conds) . ") ORDER BY COALESCE(due_date, created_at) DESC",
        $params
    );
}
// Existing submissions for this student.
$subs = [];
if ($sid) {
    foreach (db_all('SELECT * FROM assignment_submissions WHERE student_id = :s', [':s' => $sid]) as $s) {
        $subs[(int) $s['assignment_id']] = $s;
    }
}

seo_set(['title' => 'My Assignments', 'page_key' => 'my-assignments', 'robots' => 'noindex,nofollow']);
$page_hero = ['title' => 'My Assignments', 'subtitle' => 'Submit your work and track feedback.', 'breadcrumb' => [['label' => 'My Learning', 'url' => url('my-learning')], ['label' => 'Assignments']]];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width:860px;">
        <?php if (!$assignments): ?>
            <div class="glass-card reveal" style="text-align:center;padding:2.2rem;"><p class="text-muted mb-0">No assignments for your courses yet.</p></div>
        <?php else: foreach ($assignments as $a): $sub = $subs[(int) $a['id']] ?? null; $overdue = !empty($a['due_date']) && strtotime((string) $a['due_date']) < time(); ?>
            <div class="glass-card reveal mb-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h3 class="mb-0"><?= e($a['title']) ?></h3>
                        <small class="text-muted"><?= $a['due_date'] ? 'Due ' . e(format_datetime($a['due_date'])) : 'No due date' ?> · <?= e($a['max_marks']) ?> marks</small>
                    </div>
                    <?php if ($sub): ?>
                        <span class="badge <?= $sub['status'] === 'graded' ? 'badge-success' : 'badge-brand' ?>"><?= e(ucfirst($sub['status'])) ?><?= $sub['status'] === 'graded' && $sub['marks'] !== null ? ' · ' . e($sub['marks']) . '/' . e($a['max_marks']) : '' ?></span>
                    <?php elseif ($overdue): ?><span class="badge badge-danger">Overdue</span><?php endif; ?>
                </div>
                <?php if (!empty($a['description'])): ?><p class="text-muted mt-2"><?= nl2br(e((string) $a['description'])) ?></p><?php endif; ?>
                <?php if (!empty($a['attachment'])): ?><p><a class="btn btn-outline btn-sm" target="_blank" href="<?= e(upload_url($a['attachment'])) ?>"><?= lucide('paperclip') ?> Assignment file</a></p><?php endif; ?>

                <?php if ($sub && $sub['status'] === 'graded'): ?>
                    <div class="alert alert-success" style="margin-top:.6rem;"><strong>Graded: <?= e($sub['marks']) ?>/<?= e($a['max_marks']) ?></strong><?= $sub['feedback'] ? ' — ' . e($sub['feedback']) : '' ?></div>
                <?php else: ?>
                    <form method="post" action="<?= e(url('my-assignments')) ?>" enctype="multipart/form-data" class="mt-2">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="submit"><input type="hidden" name="assignment_id" value="<?= (int) $a['id'] ?>">
                        <div class="form-group"><textarea class="form-textarea" name="text" placeholder="Type your answer (or attach a file)" style="min-height:80px;"><?= e($sub['text'] ?? '') ?></textarea></div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <input class="form-control" type="file" name="file" style="max-width:280px;">
                            <button class="btn btn-primary" type="submit" <?= $sid ? '' : 'disabled title="Student record required"' ?>><?= lucide('upload') ?> <?= $sub ? 'Resubmit' : 'Submit' ?></button>
                            <?php if ($sub && !empty($sub['file_path'])): ?><a class="text-muted" target="_blank" href="<?= e(upload_url($sub['file_path'])) ?>">Current file</a><?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
