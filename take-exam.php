<?php
/**
 * =============================================================================
 *  Take Exam / Quiz — student portal
 * =============================================================================
 *  Behind require_member(). Access is gated on enrolment in the exam's course.
 *  Attempts belong to the member; MCQ/True-False auto-grade instantly, short
 *  answers are flagged for the teacher. A JS timer auto-submits at time-up.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_member('/login');

$sess = current_member();
$mid  = (int) ($sess['id'] ?? 0);

$examId = (int) get('exam', 0);
$exam   = find('exams', $examId);
if (!$exam || $exam['status'] !== 'published') {
    set_flash('error', 'This exam is not available.');
    redirect('/my-learning');
}
$student = db_row('SELECT * FROM school_students WHERE member_id = :m LIMIT 1', [':m' => $mid]);
$sid     = $student ? (int) $student['id'] : 0;

// Access: if the exam belongs to a course, require enrolment in it.
if (!empty($exam['course_id'])) {
    $enrolled = db_row("SELECT id FROM course_enrollments WHERE course_id = :c AND status <> 'dropped' AND (member_id = :m OR student_id = :s) LIMIT 1", [':c' => (int) $exam['course_id'], ':m' => $mid, ':s' => $sid]);
    if (!$enrolled) {
        set_flash('error', 'Enrol in the course to take this exam.');
        redirect('/my-learning');
    }
}

$attempt = db_row("SELECT * FROM exam_attempts WHERE exam_id = :e AND member_id = :m AND status = 'in_progress' ORDER BY id DESC LIMIT 1", [':e' => $examId, ':m' => $mid]);

/* -------- Start an attempt -------- */
if (is_post() && post('_do') === 'start') {
    require_csrf();
    $r = exam_start($examId, $mid, $sid ?: null);
    if (!$r['ok']) {
        set_flash('error', $r['message']);
        redirect('/take-exam?exam=' . $examId);
    }
    redirect('/take-exam?exam=' . $examId);
}

/* -------- Submit an attempt -------- */
if (is_post() && post('_do') === 'submit' && $attempt) {
    require_csrf();
    $answers = is_array(post('q')) ? post('q') : [];
    exam_submit((int) $attempt['id'], $answers);
    redirect('/take-exam?exam=' . $examId . '&done=1');
}

$questions = db_all('SELECT * FROM exam_questions WHERE exam_id = :e ORDER BY sort_order ASC, id ASC', [':e' => $examId]);
if (!empty($exam['shuffle'])) { shuffle($questions); }
$usedAttempts = exam_attempt_count($examId, $mid, $sid ?: null);

seo_set(['title' => $exam['title'], 'page_key' => 'take-exam', 'robots' => 'noindex,nofollow']);
include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width:820px;">
        <?php if (get('done')):
            $last = db_row("SELECT * FROM exam_attempts WHERE exam_id = :e AND member_id = :m ORDER BY id DESC LIMIT 1", [':e' => $examId, ':m' => $mid]);
        ?>
            <!-- Result -->
            <div class="card-3d reveal" style="text-align:center;padding:2.4rem;">
                <?php $ok = $last && (int) $last['passed'] === 1; $review = $last && (int) $last['needs_review'] === 1; ?>
                <div class="icon-badge" style="margin:0 auto 1rem;font-size:2rem;color:#fff;background:<?= $review ? '#F15A24' : ($ok ? '#2F8065' : '#dc2626') ?>;">
                    <?= lucide($review ? 'clock' : ($ok ? 'check' : 'x')) ?></div>
                <h2 class="mb-1"><?= $review ? 'Submitted — under review' : ($ok ? 'Passed!' : 'Completed') ?></h2>
                <p class="text-muted">You scored <strong><?= e($last['score']) ?></strong> / <?= e($last['total']) ?><?= $review ? ' so far (short answers pending teacher review)' : '' ?>.</p>
                <a class="btn btn-primary mt-2" href="<?= e(url('my-learning')) ?>">Back to My Learning</a>
            </div>

        <?php elseif ($attempt): ?>
            <!-- In-progress: questions -->
            <div class="flex items-center justify-between mb-2">
                <h2 class="mb-0"><?= e($exam['title']) ?></h2>
                <div id="timer" class="badge badge-brand" style="font-size:1rem;"></div>
            </div>
            <form method="post" action="<?= e(url('take-exam?exam=' . $examId)) ?>" id="examForm">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="submit">
                <?php foreach ($questions as $i => $q): $opts = json_column($q['options']); ?>
                    <div class="glass-card reveal mb-3">
                        <div class="flex items-center justify-between">
                            <strong>Q<?= $i + 1 ?>.</strong><small class="text-muted"><?= e($q['marks']) ?> mark(s)</small>
                        </div>
                        <p style="margin:.3rem 0 .8rem;"><?= nl2br(e((string) $q['question'])) ?></p>
                        <?php if ($q['type'] === 'mcq'): ?>
                            <?php foreach ($opts as $idx => $opt): ?>
                                <label class="checkbox" style="display:block;margin:.3rem 0;"><input type="checkbox" name="q[<?= (int) $q['id'] ?>][]" value="<?= (int) $idx ?>"> <?= e($opt) ?></label>
                            <?php endforeach; ?>
                        <?php elseif ($q['type'] === 'truefalse'): ?>
                            <label class="checkbox" style="display:block;margin:.3rem 0;"><input type="radio" name="q[<?= (int) $q['id'] ?>]" value="true"> True</label>
                            <label class="checkbox" style="display:block;margin:.3rem 0;"><input type="radio" name="q[<?= (int) $q['id'] ?>]" value="false"> False</label>
                        <?php else: ?>
                            <textarea class="form-textarea" name="q[<?= (int) $q['id'] ?>]" style="min-height:90px;" placeholder="Your answer"></textarea>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button class="btn btn-3d btn-lg" type="submit"><?= lucide('send') ?> Submit exam</button>
            </form>
            <script>
            (function () {
                var mins = <?= (int) $exam['duration_min'] ?>, started = <?= (int) strtotime((string) $attempt['started_at']) ?>;
                var ends = (started + mins * 60) * 1000, el = document.getElementById('timer');
                function tick() {
                    var left = Math.max(0, Math.floor((ends - Date.now()) / 1000));
                    var m = Math.floor(left / 60), s = left % 60;
                    el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                    if (left <= 0) { document.getElementById('examForm').submit(); return; }
                    setTimeout(tick, 1000);
                }
                if (mins > 0) tick(); else el.style.display = 'none';
            })();
            </script>

        <?php else: ?>
            <!-- Start screen -->
            <div class="glass-card reveal" style="text-align:center;padding:2.2rem;">
                <h2 class="mb-1"><?= e($exam['title']) ?></h2>
                <?php if (!empty($exam['description'])): ?><p class="text-muted"><?= e($exam['description']) ?></p><?php endif; ?>
                <div class="flex justify-center gap-3 flex-wrap my-3">
                    <div><div class="text-muted" style="font-size:.75rem;">DURATION</div><strong><?= (int) $exam['duration_min'] ?> min</strong></div>
                    <div><div class="text-muted" style="font-size:.75rem;">TOTAL MARKS</div><strong><?= e($exam['total_marks']) ?></strong></div>
                    <div><div class="text-muted" style="font-size:.75rem;">PASS MARKS</div><strong><?= e($exam['pass_marks']) ?></strong></div>
                    <div><div class="text-muted" style="font-size:.75rem;">ATTEMPTS</div><strong><?= (int) $usedAttempts ?> / <?= (int) $exam['max_attempts'] ?></strong></div>
                </div>
                <?php if ($usedAttempts < (int) $exam['max_attempts']): ?>
                    <form method="post" action="<?= e(url('take-exam?exam=' . $examId)) ?>">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="start">
                        <button class="btn btn-3d btn-lg" type="submit"><?= lucide('play') ?> Start exam</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">You have used all your attempts.</div>
                    <a class="btn btn-outline" href="<?= e(url('my-learning')) ?>">Back</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
