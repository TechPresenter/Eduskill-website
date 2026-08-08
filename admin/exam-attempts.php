<?php
/**
 * =============================================================================
 *  Admin — Exam Attempts & Manual Grading
 * =============================================================================
 *  Scoped to a single exam via ?exam=<id> (required). Lists every learner
 *  attempt (school student or member) with score, pass/fail, review flag and
 *  status. Short-answer questions are graded by hand from the grade view: the
 *  admin awards marks per short question and the attempt is re-scored — MCQ /
 *  True-False are auto-compared (via exam_norm(), the same logic as
 *  exam_grade()), short answers take the posted marks — then finalised
 *  (status = graded, needs_review = 0).
 *  Standard admin pattern: CSRF, prepared statements, pagination, stat cards,
 *  activity logging, flash + redirect. The ?exam context is preserved in every
 *  link and redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'exam_attempts';
$action = get('action', 'list');
$id     = (int) get('id', 0);
$examId = (int) get('exam', 0);

/* Parent exam is mandatory — bail to the exams list if missing/stale. */
$exam = $examId ? find('exams', $examId) : null;
if (!$exam) {
    set_flash('error', 'Select an exam to manage its attempts.');
    redirect('/admin/exams');
}

$backQ = '?exam=' . $examId;

$statuses = [
    'in_progress' => 'In progress',
    'submitted'   => 'Submitted',
    'graded'      => 'Graded',
];

function attempt_status_pill(string $s): string
{
    return ['in_progress' => 'pill-gray', 'submitted' => 'pill-amber', 'graded' => 'pill-green'][$s] ?? 'pill-gray';
}

/** Display name for an attempt row (school student or member, whichever set). */
function attempt_taker_name(array $r): string
{
    $name = (string) ($r['student_name'] ?? '');
    if ($name === '') { $name = (string) ($r['member_name'] ?? ''); }
    return $name !== '' ? $name : '—';
}

/* -------------------------------------------------------------- GRADE (save) */
if (is_post() && post('_do') === 'grade') {
    require_csrf();
    $attemptId = (int) post('id', 0);
    $attempt   = find($table, $attemptId);
    if (!$attempt || (int) $attempt['exam_id'] !== $examId) {
        set_flash('error', 'Attempt not found.');
        redirect('/admin/exam-attempts' . $backQ);
    }

    $questions   = db_all('SELECT * FROM exam_questions WHERE exam_id = :e', [':e' => $examId]);
    $answers     = json_column($attempt['answers']);
    $postedMarks = (array) post('marks', []);

    // Recompute the whole score: auto-grade MCQ/True-False, take the awarded
    // marks for short answers (clamped to each question's maximum).
    $score = 0.0;
    $total = 0.0;
    foreach ($questions as $q) {
        $qMarks = (float) $q['marks'];
        $total += $qMarks;
        $qid    = (int) $q['id'];
        $given  = $answers[$qid] ?? ($answers[(string) $qid] ?? null);

        if ($q['type'] === 'short') {
            $awarded = (float) ($postedMarks[$qid] ?? ($postedMarks[(string) $qid] ?? 0));
            $score  += max(0.0, min($qMarks, $awarded));
            continue;
        }

        // MCQ / True-False — compare normalised given vs stored correct.
        $correctRaw = json_decode((string) $q['correct'], true);
        if ($correctRaw === null && $q['correct'] !== null && $q['correct'] !== '') {
            $correctRaw = $q['correct']; // stored as a plain string
        }
        if (exam_norm($given) === exam_norm($correctRaw)) {
            $score += $qMarks;
        }
    }

    $pass = (float) ($exam['pass_marks'] ?? 0);
    db_update($table, [
        'score'        => $score,
        'total'        => $total,
        'passed'       => $score >= $pass ? 1 : 0,
        'auto_graded'  => 1,
        'needs_review' => 0,
        'status'       => 'graded',
    ], 'id = :id', [':id' => $attemptId]);

    log_activity('update', 'exams', 'Graded attempt #' . $attemptId . ' on exam #' . $examId);
    set_flash('success', 'Attempt graded.');
    redirect('/admin/exam-attempts' . $backQ);
}

/* -------------------------------------------------------------- GRADE (view) */
if ($action === 'grade') {
    $attempt = find($table, $id);
    if (!$attempt || (int) $attempt['exam_id'] !== $examId) {
        set_flash('error', 'Attempt not found.');
        redirect('/admin/exam-attempts' . $backQ);
    }

    $taker = $attempt['student_id']
        ? db_value('SELECT name FROM school_students WHERE id = :i', [':i' => (int) $attempt['student_id']])
        : ($attempt['member_id']
            ? db_value('SELECT name FROM members WHERE id = :i', [':i' => (int) $attempt['member_id']])
            : null);
    $taker = $taker ?: '—';

    $shorts    = db_all("SELECT * FROM exam_questions WHERE exam_id = :e AND type = 'short' ORDER BY sort_order, id", [':e' => $examId]);
    $answers   = json_column($attempt['answers']);
    $autoCount = (int) db_value("SELECT COUNT(*) FROM exam_questions WHERE exam_id = :e AND type <> 'short'", [':e' => $examId]);

    $page_title = 'Grade Attempt';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div>
            <h1>Grade Attempt</h1>
            <span class="muted"><?= e($exam['title']) ?> · <?= e($taker) ?></span>
        </div>
        <a class="btn btn-secondary" href="<?= e(admin_url('exam-attempts' . $backQ)) ?>">← Back to attempts</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('exam-attempts' . $backQ)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="grade">
        <input type="hidden" name="id" value="<?= (int) $attempt['id'] ?>">

        <div class="panel"><div class="panel-body">
            <div class="flex items-center justify-between mb-3">
                <h3>Short-answer questions</h3>
                <span class="text-muted">Pass mark: <?= e(money((float) ($exam['pass_marks'] ?? 0), '', 2)) ?></span>
            </div>

            <?php if ($shorts): ?>
                <?php foreach ($shorts as $q):
                    $qid   = (int) $q['id'];
                    $given = $answers[$qid] ?? ($answers[(string) $qid] ?? '');
                    if (is_array($given)) { $given = implode(', ', $given); }
                ?>
                    <div class="form-group" style="border:1px solid #eef2f7;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                        <label class="form-label"><?= e($q['question']) ?></label>
                        <div class="form-hint" style="margin-bottom:.5rem;">Max marks: <?= e(money((float) $q['marks'], '', 2)) ?></div>
                        <div class="panel" style="margin-bottom:.75rem;"><div class="panel-body">
                            <div class="text-muted" style="margin-bottom:.25rem;">Student's answer</div>
                            <?php if (trim((string) $given) !== ''): ?>
                                <div style="white-space:pre-wrap;"><?= e($given) ?></div>
                            <?php else: ?>
                                <em class="text-muted">No answer submitted.</em>
                            <?php endif; ?>
                        </div></div>
                        <label class="form-label">Marks awarded</label>
                        <input class="form-control" type="number" name="marks[<?= $qid ?>]"
                               min="0" max="<?= e((string) (float) $q['marks']) ?>" step="0.01"
                               placeholder="0" style="max-width:180px;">
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><div class="icon"><?= lucide('check-check') ?></div>
                    This attempt has no short-answer questions — grading will simply finalise the auto-graded score.</div>
            <?php endif; ?>

            <p class="form-hint"><?= (int) $autoCount ?> auto-graded question<?= $autoCount === 1 ? '' : 's' ?> (MCQ / True-False) will be re-scored automatically.</p>

            <div class="form-actions mt-3">
                <button class="btn btn-primary" type="submit">Save grade</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('exam-attempts' . $backQ)) ?>">Cancel</a>
            </div>
        </div></div>
    </form>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title   = 'Exam Attempts';
$statusFilter = (string) get('status', '');

$where  = 'a.exam_id = :exam';
$params = [':exam' => $examId];
if (isset($statuses[$statusFilter])) {
    $where .= ' AND a.status = :st';
    $params[':st'] = $statusFilter;
}

$p = paginate(
    "SELECT a.*, ss.name AS student_name, m.name AS member_name
       FROM exam_attempts a
       LEFT JOIN school_students ss ON ss.id = a.student_id
       LEFT JOIN members m ON m.id = a.member_id
      WHERE $where
      ORDER BY a.submitted_at DESC, a.id DESC",
    $params, 20
);

$counts = [
    'all'    => db_count($table, 'exam_id = :e', [':e' => $examId]),
    'review' => db_count($table, 'exam_id = :e AND needs_review = 1', [':e' => $examId]),
    'graded' => db_count($table, "exam_id = :e AND status = 'graded'", [':e' => $examId]),
    'passed' => db_count($table, 'exam_id = :e AND passed = 1', [':e' => $examId]),
];

$hasFilter = $statusFilter !== '';

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Attempts</h1>
        <span class="muted"><?= e($exam['title']) ?> · <?= (int) $counts['all'] ?> attempts</span>
    </div>
    <a class="btn btn-secondary" href="<?= e(admin_url('exams')) ?>">← Exams</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Attempts</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('clipboard-pen') ?></div><div><div class="stat-value"><?= (int) $counts['review'] ?></div><div class="stat-label">Needs review</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['graded'] ?></div><div class="stat-label">Graded</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('award') ?></div><div><div class="stat-value"><?= (int) $counts['passed'] ?></div><div class="stat-label">Passed</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form method="get" action="<?= e(admin_url('exam-attempts')) ?>" class="flex" style="gap:.5rem;">
            <input type="hidden" name="exam" value="<?= (int) $examId ?>">
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($hasFilter): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('exam-attempts' . $backQ)) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Taker</th><th>Score</th><th>Result</th><th>Review</th><th>Status</th><th>Submitted</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $gradeQ = 'exam-attempts?action=grade&id=' . $r['id'] . '&exam=' . $examId;
        ?>
            <tr>
                <td><strong><?= e(attempt_taker_name($r)) ?></strong></td>
                <td><?= e(money((float) $r['score'], '', 2)) ?> / <?= e(money((float) $r['total'], '', 2)) ?></td>
                <td>
                    <?php if ($r['passed'] === null || $r['passed'] === ''): ?>
                        <span class="text-muted">—</span>
                    <?php elseif ((int) $r['passed'] === 1): ?>
                        <span class="pill pill-green">Passed</span>
                    <?php else: ?>
                        <span class="pill pill-red">Failed</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int) $r['needs_review'] === 1): ?>
                        <span class="pill pill-amber">Review</span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="pill <?= e(attempt_status_pill((string) $r['status'])) ?>"><?= e($statuses[$r['status']] ?? ucfirst((string) $r['status'])) ?></span></td>
                <td><?= $r['submitted_at'] ? e(format_datetime($r['submitted_at'])) : '<span class="text-muted">—</span>' ?></td>
                <td><div class="actions">
                    <?php if ((int) $r['needs_review'] === 1): ?>
                        <a class="icon-btn" href="<?= e(admin_url($gradeQ)) ?>" title="Grade"><?= lucide('clipboard-pen') ?></a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('users') ?></div>
            <?= $hasFilter ? 'No attempts match your filter.' : 'No attempts on this exam yet.' ?></div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
