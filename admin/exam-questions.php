<?php
/**
 * =============================================================================
 *  Admin — Exam Question Bank (questions for one exam)
 * =============================================================================
 *  list · create · edit · delete, all scoped to a single parent exam via
 *  ?exam=<id>. Supports MCQ (multi-correct), True/False and Short-answer
 *  questions. MCQ + True/False auto-grade; short-answer is graded manually.
 *  After every save/delete the exam's total_marks is re-synced from the bank.
 *  Standard admin pattern: CSRF, prepared statements, activity logging,
 *  flash + redirect. The ?exam context is preserved in every link/redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'exam_questions';
$action = get('action', 'list');
$id     = (int) get('id', 0);
$examId = (int) (get('exam', 0) ?: post('exam', 0)); // parent exam context

$types = [
    'mcq'       => 'Multiple choice',
    'truefalse' => 'True / False',
    'short'     => 'Short answer',
];
$typePill = ['mcq' => 'pill-blue', 'truefalse' => 'pill-green', 'short' => 'pill-amber'];

/* -------------------------------------------------------------- PARENT */
$exam = $examId ? find('exams', $examId) : null;
if (!$exam) {
    set_flash('error', 'Exam not found.');
    redirect(admin_url('exams'));
}
$listUrl = '/admin/exam-questions?exam=' . $examId;

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && (!$old || (int) $old['exam_id'] !== $examId)) {
        set_flash('error', 'Question not found.');
        redirect($listUrl);
    }

    $type     = isset($types[(string) post('type')]) ? (string) post('type') : 'mcq';
    $question = clean(post('question'));

    $ret = '/admin/exam-questions?exam=' . $examId . '&action=' . ($editId ? 'edit&id=' . $editId : 'create');

    if ($question === '') {
        flash_old($_POST);
        set_flash('error', 'Question text is required.');
        redirect($ret);
    }

    // Build type-specific options + correct payloads.
    $options = null;
    $correct = null;

    if ($type === 'mcq') {
        // Compact non-empty options in slot order, remapping the checked slot
        // indices (correct[]) onto the compacted positions.
        $rawOptions = (array) post('option', []);
        $rawCorrect = array_map('intval', (array) post('correct', []));
        $clean = [];
        $map   = []; // original slot => compacted index
        foreach ($rawOptions as $slot => $opt) {
            $opt = clean($opt);
            if ($opt === '') { continue; }
            $map[(int) $slot] = count($clean);
            $clean[] = $opt;
        }
        $correctIdx = [];
        foreach ($rawCorrect as $slot) {
            if (isset($map[$slot])) { $correctIdx[] = $map[$slot]; }
        }
        $correctIdx = array_values(array_unique($correctIdx));
        sort($correctIdx);

        if (count($clean) < 2) {
            flash_old($_POST);
            set_flash('error', 'Add at least two options for a multiple-choice question.');
            redirect($ret);
        }
        if (!$correctIdx) {
            flash_old($_POST);
            set_flash('error', 'Mark at least one correct option.');
            redirect($ret);
        }
        $options = json_encode($clean, JSON_UNESCAPED_UNICODE);
        $correct = json_encode($correctIdx);
    } elseif ($type === 'truefalse') {
        $tf      = post('correct_tf') === 'false' ? 'false' : 'true';
        $correct = json_encode($tf);
    } else { // short — model answer is optional and informational (manual grading)
        $model   = clean(post('model_answer'));
        $correct = $model !== '' ? json_encode($model, JSON_UNESCAPED_UNICODE) : null;
    }

    $marks = (float) post('marks', 1);
    if ($marks < 0) { $marks = 0.0; }

    $data = [
        'exam_id'    => $examId,
        'type'       => $type,
        'question'   => $question,
        'options'    => $options,
        'correct'    => $correct,
        'marks'      => $marks,
        'sort_order' => (int) post('sort_order', 0),
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'exams', 'Updated question #' . $editId . ' of exam #' . $examId);
        set_flash('success', 'Question updated.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'exams', 'Added question #' . $newId . ' to exam #' . $examId);
        set_flash('success', 'Question added.');
    }
    exam_sync_total($examId); // keep exams.total_marks correct
    redirect($listUrl);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row && (int) $row['exam_id'] === $examId) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'exams', 'Deleted question #' . $delId . ' of exam #' . $examId);
        exam_sync_total($examId);
        set_flash('success', 'Question deleted.');
    }
    redirect($listUrl);
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && (!$row || (int) $row['exam_id'] !== $examId)) {
        set_flash('error', 'Question not found.');
        redirect($listUrl);
    }

    $curType = (string) old('type', $row['type'] ?? 'mcq');
    if (!isset($types[$curType])) { $curType = 'mcq'; }

    // Stored `correct` decodes differently per type: array (mcq),
    // 'true'/'false' string (truefalse), or model text string (short).
    $storedCorrect = $row ? json_decode((string) ($row['correct'] ?? ''), true) : null;

    // MCQ options: repopulated form array wins, else the stored JSON; pad to 6 slots.
    $oldOptions = old('option', null);
    $curOptions = is_array($oldOptions) ? array_values($oldOptions) : ($row ? json_column($row['options']) : []);
    $curOptions = array_slice(array_pad($curOptions, 6, ''), 0, 6);

    // MCQ correct: slot indices to pre-check.
    $oldCorrect = old('correct', null);
    $curCorrect = is_array($oldCorrect)
        ? array_map('intval', $oldCorrect)
        : (is_array($storedCorrect) ? array_map('intval', $storedCorrect) : []);

    $curTf    = (string) old('correct_tf', is_string($storedCorrect) ? $storedCorrect : 'true');
    $curModel = (string) old('model_answer', ($curType === 'short' && is_string($storedCorrect)) ? $storedCorrect : '');

    $nextSort = (int) db_value('SELECT COALESCE(MAX(sort_order),0)+1 FROM exam_questions WHERE exam_id = :e', [':e' => $examId]);
    $curMarks = (string) (float) old('marks', $row['marks'] ?? 1);
    $curSort  = (string) (int) old('sort_order', $row['sort_order'] ?? $nextSort);
    $curQ     = (string) old('question', $row['question'] ?? '');

    $page_title = $action === 'edit' ? 'Edit Question' : 'Add Question';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted"><?= e($exam['title']) ?> — question bank</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('exam-questions?exam=' . $examId)) ?>">← Back to questions</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('exam-questions?exam=' . $examId)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="exam" value="<?= (int) $examId ?>">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Question details</h3>

            <div class="grid-3">
                <div class="form-group"><label class="form-label">Type</label>
                    <select class="form-select" name="type" id="q-type">
                        <?php foreach ($types as $k => $l): ?>
                            <option value="<?= e($k) ?>" <?= $curType === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Marks</label>
                    <input class="form-control" type="number" name="marks" min="0" step="0.5" value="<?= e($curMarks) ?>"></div>
                <div class="form-group"><label class="form-label">Sort order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= e($curSort) ?>"></div>
            </div>

            <div class="form-group"><label class="form-label">Question <span class="req">*</span></label>
                <textarea class="form-textarea" name="question" required style="min-height:90px;" placeholder="Enter the question…"><?= e($curQ) ?></textarea>
                <small class="form-hint">MCQ and True/False questions auto-grade; short-answer questions are graded manually.</small>
            </div>

            <!-- MCQ options -->
            <div data-qtype="mcq">
                <label class="form-label">Options <span class="req">*</span></label>
                <small class="form-hint">Tick every correct option (multiple allowed). Blank rows are ignored.</small>
                <div class="mt-3">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <div class="flex items-center" style="gap:.6rem;margin-bottom:.5rem;">
                            <label class="checkbox" style="margin:0;flex:none;" title="Mark correct">
                                <input type="checkbox" name="correct[]" value="<?= $i ?>" <?= in_array($i, $curCorrect, true) ? 'checked' : '' ?>>
                            </label>
                            <input class="form-control" name="option[]" value="<?= e($curOptions[$i] ?? '') ?>" placeholder="Option <?= $i + 1 ?>">
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- True / False -->
            <div data-qtype="truefalse">
                <div class="form-group"><label class="form-label">Correct answer</label>
                    <select class="form-select" name="correct_tf">
                        <option value="true"  <?= $curTf !== 'false' ? 'selected' : '' ?>>True</option>
                        <option value="false" <?= $curTf === 'false' ? 'selected' : '' ?>>False</option>
                    </select></div>
            </div>

            <!-- Short answer -->
            <div data-qtype="short">
                <div class="form-group"><label class="form-label">Model answer</label>
                    <textarea class="form-textarea" name="model_answer" style="min-height:80px;" placeholder="Optional — shown to the grader as reference"><?= e($curModel) ?></textarea>
                    <small class="form-hint">Optional. Short-answer questions are scored manually during review.</small>
                </div>
            </div>

            <div class="form-actions mt-3">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Add' ?> question</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('exam-questions?exam=' . $examId)) ?>">Cancel</a>
            </div>
        </div></div>
    </form>

    <script>
    (function () {
        var sel = document.getElementById('q-type');
        if (!sel) { return; }
        function sync() {
            var t = sel.value;
            document.querySelectorAll('[data-qtype]').forEach(function (el) {
                el.style.display = el.getAttribute('data-qtype') === t ? '' : 'none';
            });
        }
        sel.addEventListener('change', sync);
        sync();
    })();
    </script>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Question Bank';

$questions = db_all(
    'SELECT * FROM exam_questions WHERE exam_id = :e ORDER BY sort_order ASC, id ASC',
    [':e' => $examId]
);

$counts = [
    'all'    => db_count('exam_questions', 'exam_id = :e', [':e' => $examId]),
    'auto'   => db_count('exam_questions', "exam_id = :e AND type IN ('mcq','truefalse')", [':e' => $examId]),
    'manual' => db_count('exam_questions', "exam_id = :e AND type = 'short'", [':e' => $examId]),
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Question Bank</h1>
        <span class="muted"><?= e($exam['title']) ?> · <?= (int) $counts['all'] ?> questions · <?= e((string) (float) $exam['total_marks']) ?> marks</span>
    </div>
    <div class="flex" style="gap:.5rem;">
        <a class="btn btn-secondary" href="<?= e(admin_url('exams')) ?>">← Back to exams</a>
        <a class="btn btn-primary" href="<?= e(admin_url('exam-questions?action=create&exam=' . $examId)) ?>">+ Add Question</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('list-checks') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Questions</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('scroll') ?></div><div><div class="stat-value"><?= e((string) (float) $exam['total_marks']) ?></div><div class="stat-label">Total marks</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['auto'] ?></div><div class="stat-label">Auto-graded</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('pencil') ?></div><div><div class="stat-value"><?= (int) $counts['manual'] ?></div><div class="stat-label">Manual (short)</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <p class="text-muted" style="margin-bottom:1rem;">MCQ and True/False questions auto-grade; short-answer questions are graded manually during review.</p>

    <?php if ($questions): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th style="width:3rem;">#</th><th>Question</th><th>Type</th><th>Marks</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php $i = 0; foreach ($questions as $q): $i++;
            $editQ = 'exam-questions?action=edit&id=' . $q['id'] . '&exam=' . $examId;
        ?>
            <tr>
                <td><?= (int) $i ?></td>
                <td><?= e(excerpt($q['question'], 16)) ?></td>
                <td><span class="pill <?= e($typePill[$q['type']] ?? 'pill-gray') ?>"><?= e($types[$q['type']] ?? $q['type']) ?></span></td>
                <td><?= e((string) (float) $q['marks']) ?></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url($editQ)) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('exam-questions?exam=' . $examId)) ?>" data-confirm="Delete this question? This cannot be undone." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="exam" value="<?= (int) $examId ?>"><input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('list-checks') ?></div>
            No questions yet. <a href="<?= e(admin_url('exam-questions?action=create&exam=' . $examId)) ?>">Add one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
