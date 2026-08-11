<?php
/**
 * =============================================================================
 *  Admin — Student Performance Analytics (read-only)
 * =============================================================================
 *  ?student=<id>. For a chosen student this surfaces four performance
 *  dimensions as stat cards — average exam score, 30-day attendance rate,
 *  assignment completion and latest marksheet percentage — plus a per-exam
 *  score% bar chart, the exam attempt log and the latest marksheets.
 *  With no student chosen: a picker prompt and a top-10 leaderboard ranked by
 *  each student's latest marksheet percentage.
 *
 *  Read-only: no POST handlers, no writes, no side effects. Every echoed value
 *  is escaped with e(); all queries use :param binds.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$studentId = (int) get('student', 0);

$student = $studentId ? find('school_students', $studentId) : null;
if ($studentId && !$student) {
    set_flash('error', 'Student not found.');
    redirect('/admin/student-performance');
}

// Picker source — id, name, code for the dropdown.
$pickList = db_all('SELECT id, name, student_code FROM school_students ORDER BY name ASC');

/* =============================================================================
 |  PER-STUDENT ANALYTICS (only when a student is chosen)
 |============================================================================*/
$attempts   = [];
$marksheets = [];
$att        = null;
$asg        = null;
$studentSchool = null;

// member link (may be null) and student id — bound as TWO separate params so the
// query works under native prepared statements (no placeholder reuse).
$mid = ($student && $student['member_id'] !== null && $student['member_id'] !== '')
    ? (int) $student['member_id'] : null;
$sid = $studentId;

if ($student) {
    $studentSchool = $student['school_id'] ? find('schools', (int) $student['school_id']) : null;

    // (a) Exam attempts — matched by the student's member link OR their student id.
    $attempts = db_all(
        "SELECT x.title AS exam_title, e.score, e.total, e.passed, e.submitted_at, e.status
           FROM exam_attempts e
           JOIN exams x ON x.id = e.exam_id
          WHERE ((:mid IS NOT NULL AND e.member_id = :mid2) OR e.student_id = :sid)
          ORDER BY (e.submitted_at IS NULL), e.submitted_at DESC, e.id DESC",
        [':mid' => $mid, ':mid2' => $mid, ':sid' => $sid]
    );

    // (b) Attendance rate over the last 30 days (present + late) / total.
    $att = db_row(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS present
           FROM school_attendance
          WHERE student_id = :sid AND `date` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        [':sid' => $sid]
    );

    // (c) Assignment completion — submissions, graded count, average marks.
    $asg = db_row(
        "SELECT COUNT(*) AS subs,
                SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) AS graded,
                AVG(marks) AS avg_marks
           FROM assignment_submissions
          WHERE student_id = :sid",
        [':sid' => $sid]
    );

    // (d) Latest 5 marksheets.
    $marksheets = db_all(
        "SELECT title, term, academic_year, percentage, grade, result, created_at
           FROM marksheets
          WHERE student_id = :sid
          ORDER BY id DESC LIMIT 5",
        [':sid' => $sid]
    );
}

// Derived metrics + chart series (per graded exam attempt).
$examPct     = [];
$chartLabels = [];
$chartData   = [];
foreach ($attempts as $a) {
    $tot = (float) $a['total'];
    if ($tot > 0) {
        $pct           = round(((float) $a['score'] / $tot) * 100, 1);
        $examPct[]     = $pct;
        $chartLabels[] = (string) $a['exam_title'];
        $chartData[]   = $pct;
    }
}
// Chart reads oldest → newest left-to-right (attempts came newest-first).
$chartLabels = array_reverse($chartLabels);
$chartData   = array_reverse($chartData);

$avgExam   = $examPct ? round(array_sum($examPct) / count($examPct), 1) : null;
$attTotal  = (int) ($att['total'] ?? 0);
$attRate   = $attTotal > 0 ? round(((int) $att['present'] / $attTotal) * 100, 1) : null;
$asgSubs   = (int) ($asg['subs'] ?? 0);
$asgGraded = (int) ($asg['graded'] ?? 0);
$asgAvg    = ($asg && $asg['avg_marks'] !== null) ? round((float) $asg['avg_marks'], 1) : null;
$latestPct = $marksheets ? (float) $marksheets[0]['percentage'] : null;

/* =============================================================================
 |  LEADERBOARD (only when no student is chosen)
 |  Top 10 by each student's latest marksheet percentage.
 |============================================================================*/
$leaders = [];
if (!$student) {
    $leaders = db_all(
        "SELECT s.id, s.name, s.student_code, sc.name AS school_name,
                m.percentage, m.grade, m.result
           FROM school_students s
           JOIN marksheets m ON m.id = (
                 SELECT m2.id FROM marksheets m2
                  WHERE m2.student_id = s.id
                  ORDER BY m2.id DESC LIMIT 1
           )
           LEFT JOIN schools sc ON sc.id = s.school_id
          ORDER BY m.percentage DESC, s.name ASC
          LIMIT 10"
    );
}

/* Small presentation helpers. */
$passPill = static function ($passed, string $status): array {
    if ((string) $status === 'in_progress') { return ['pill-gray', 'In progress']; }
    if ($passed === null || $passed === '')  { return ['pill-amber', 'Under review']; }
    return ((int) $passed === 1) ? ['pill-green', 'Passed'] : ['pill-red', 'Failed'];
};
$resultPill = static fn(string $r): string =>
    ['pass' => 'pill-green', 'fail' => 'pill-red', 'pending' => 'pill-amber'][$r] ?? 'pill-gray';

$jsonFlags   = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$page_title  = 'Student Performance';
$load_charts = !empty($chartData); // Chart.js only when there is a bar chart to draw.
include __DIR__ . '/partials/head.php';
?>
<style>
    /* Layout only — this screen is built entirely from shared components. */
    .sp-actions { gap: var(--sp-2); }
    .sp-pickform { gap: var(--sp-3); flex-wrap: wrap; }
    .sp-pick { min-width: 280px; }
    .sp-note { font-size: .85rem; }
    .sp-w-label { width: 180px; }
    .sp-w-rank { width: 48px; }
</style>

<div class="admin-page-head">
    <div>
        <h1>Student Performance</h1>
        <span class="muted">
            <?php if ($student): ?>
                <?= e($student['name']) ?> · <?= e($student['student_code'] ?? '—') ?><?= $studentSchool ? ' · ' . e($studentSchool['name']) : '' ?>
            <?php else: ?>
                Read-only analytics across exams, attendance, assignments &amp; marksheets
            <?php endif; ?>
        </span>
    </div>
    <div class="flex sp-actions">
        <?php if ($student): ?>
            <a class="btn btn-outline" href="<?= e(admin_url('school-students?action=edit&id=' . $studentId)) ?>"><?= lucide('user') ?> Profile</a>
            <a class="btn btn-secondary" href="<?= e(admin_url('student-performance')) ?>">← Choose another</a>
        <?php else: ?>
            <a class="btn btn-secondary" href="<?= e(admin_url('school-students')) ?>"><?= lucide('users') ?> All students</a>
        <?php endif; ?>
    </div>
</div>

<!-- Student picker -->
<div class="panel"><div class="panel-body">
    <form class="flex items-center sp-pickform" method="get" action="<?= e(admin_url('student-performance')) ?>">
        <label class="form-label mb-0" for="studentPick">Student</label>
        <select class="form-select sp-pick" id="studentPick" name="student" onchange="this.form.submit()">
            <option value="">— Select a student —</option>
            <?php foreach ($pickList as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= $studentId === (int) $s['id'] ? 'selected' : '' ?>>
                    <?= e($s['name']) ?><?= $s['student_code'] ? ' (' . e($s['student_code']) . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($student): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('student-performance')) ?>">Clear</a>
        <?php endif; ?>
    </form>
</div></div>

<?php if ($student): ?>

    <!-- Four performance dimensions -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon bg-blue"><?= lucide('clipboard-check') ?></div>
            <div>
                <div class="stat-value"><?= $avgExam === null ? '—' : e($avgExam) . '%' ?></div>
                <div class="stat-label"><?= count($attempts) ?> exam attempt<?= count($attempts) === 1 ? '' : 's' ?> · avg score</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-green"><?= lucide('calendar-check') ?></div>
            <div>
                <div class="stat-value"><?= $attRate === null ? '—' : e($attRate) . '%' ?></div>
                <div class="stat-label">Attendance (30d)<?= $attTotal ? ' · ' . (int) ($att['present'] ?? 0) . '/' . $attTotal : '' ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-violet"><?= lucide('file-check-2') ?></div>
            <div>
                <div class="stat-value"><?= (int) $asgGraded ?>/<?= (int) $asgSubs ?></div>
                <div class="stat-label">Assignments graded<?= $asgAvg === null ? '' : ' · avg ' . e($asgAvg) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-amber"><?= lucide('award') ?></div>
            <div>
                <div class="stat-value"><?= $latestPct === null ? '—' : e($latestPct) . '%' ?></div>
                <div class="stat-label"><?= count($marksheets) ?> marksheet<?= count($marksheets) === 1 ? '' : 's' ?> · latest</div>
            </div>
        </div>
    </div>

    <div class="grid-2 cols-top">
        <div>
            <!-- Exam score chart -->
            <div class="panel"><div class="panel-body">
                <h2 class="panel-title mb-2">Exam scores</h2>
                <?php if ($chartData): ?>
                    <canvas id="examChart" height="200"></canvas>
                <?php else: ?>
                    <p class="text-muted">No graded exam attempts yet.</p>
                <?php endif; ?>
            </div></div>

            <!-- Exam attempt log -->
            <div class="panel"><div class="panel-body">
                <h2 class="panel-title mb-2">Exam attempts</h2>
                <?php if ($attempts): ?>
                <div class="table-wrap"><table class="admin-table">
                    <thead><tr><th>Exam</th><th>Score</th><th>Result</th><th>Submitted</th></tr></thead>
                    <tbody>
                    <?php foreach ($attempts as $a):
                        $tot = (float) $a['total'];
                        $pct = $tot > 0 ? round(((float) $a['score'] / $tot) * 100, 1) : null;
                        [$pClass, $pLabel] = $passPill($a['passed'], (string) $a['status']);
                    ?>
                        <tr>
                            <td><strong><?= e($a['exam_title']) ?></strong></td>
                            <td><?= e(rtrim(rtrim(number_format((float) $a['score'], 2), '0'), '.')) ?> / <?= e(rtrim(rtrim(number_format($tot, 2), '0'), '.')) ?><?= $pct === null ? '' : ' <small class="text-muted">(' . e($pct) . '%)</small>' ?></td>
                            <td><span class="pill <?= e($pClass) ?>"><?= e($pLabel) ?></span></td>
                            <td><?= $a['submitted_at'] ? e(format_datetime($a['submitted_at'])) : '<span class="text-muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php else: ?>
                    <p class="text-muted">No exam attempts on record.</p>
                <?php endif; ?>
            </div></div>
        </div>

        <div>
            <!-- Latest marksheets -->
            <div class="panel"><div class="panel-body">
                <h2 class="panel-title mb-2">Latest marksheets</h2>
                <?php if ($marksheets): ?>
                <div class="table-wrap"><table class="admin-table">
                    <thead><tr><th>Marksheet</th><th>%</th><th>Grade</th><th>Result</th></tr></thead>
                    <tbody>
                    <?php foreach ($marksheets as $m):
                        $sub = trim(implode(' · ', array_filter([$m['term'], $m['academic_year']])));
                    ?>
                        <tr>
                            <td><strong><?= e($m['title']) ?></strong><?= $sub !== '' ? '<br><small class="text-muted">' . e($sub) . '</small>' : '' ?></td>
                            <td><?= e($m['percentage']) ?>%</td>
                            <td><?= $m['grade'] ? e($m['grade']) : '—' ?></td>
                            <td><span class="pill <?= e($resultPill((string) $m['result'])) ?>"><?= e(ucfirst((string) $m['result'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php else: ?>
                    <p class="text-muted">No marksheets published yet.</p>
                <?php endif; ?>
            </div></div>

            <!-- Assignment + attendance detail -->
            <div class="panel"><div class="panel-body">
                <h2 class="panel-title mb-2">Assignments &amp; attendance</h2>
                <div class="table-wrap"><table class="admin-table"><tbody>
                    <tr><th class="sp-w-label">Submissions</th><td><?= (int) $asgSubs ?></td></tr>
                    <tr><th>Graded</th><td><?= (int) $asgGraded ?><?= $asgSubs ? ' of ' . (int) $asgSubs : '' ?></td></tr>
                    <tr><th>Average marks</th><td><?= $asgAvg === null ? '<span class="text-muted">—</span>' : e($asgAvg) ?></td></tr>
                    <tr><th>Attendance (30d)</th><td><?= $attRate === null ? '<span class="text-muted">No records</span>' : e($attRate) . '% <small class="text-muted">(' . (int) ($att['present'] ?? 0) . ' of ' . (int) $attTotal . ' sessions)</small>' ?></td></tr>
                </tbody></table></div>
            </div></div>
        </div>
    </div>

<?php else: ?>

    <!-- No student chosen: prompt + leaderboard -->
    <div class="empty-state"><div class="icon"><?= lucide('bar-chart-3') ?></div>
        Choose a student above to view their exam, attendance, assignment and marksheet performance.
    </div>

    <div class="panel"><div class="panel-body">
        <div class="flex items-center justify-between mb-2">
            <h2 class="panel-title mb-0">Leaderboard</h2>
            <span class="text-muted sp-note">Top 10 by latest marksheet %</span>
        </div>
        <?php if ($leaders): ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th class="sp-w-rank">#</th><th>Student</th><th>School</th><th>Latest %</th><th>Grade</th><th>Result</th><th class="num">View</th></tr></thead>
            <tbody>
            <?php foreach ($leaders as $i => $l): ?>
                <tr>
                    <td><strong><?= (int) $i + 1 ?></strong></td>
                    <td>
                        <strong><?= e($l['name']) ?></strong><br>
                        <small class="text-muted"><?= e($l['student_code'] ?? '—') ?></small>
                    </td>
                    <td><?= $l['school_name'] ? e($l['school_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= e($l['percentage']) ?>%</td>
                    <td><?= $l['grade'] ? e($l['grade']) : '—' ?></td>
                    <td><span class="pill <?= e($resultPill((string) $l['result'])) ?>"><?= e(ucfirst((string) $l['result'])) ?></span></td>
                    <td class="num"><a class="btn btn-outline btn-sm" href="<?= e(admin_url('student-performance?student=' . (int) $l['id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <p class="text-muted">No marksheets published yet — the leaderboard will populate once students have results.</p>
        <?php endif; ?>
    </div></div>

<?php endif; ?>

<?php if (!empty($chartData)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Chart) return;
    var el = document.getElementById('examChart');
    if (!el) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels, $jsonFlags) ?>,
            datasets: [{
                label: 'Score %',
                data: <?= json_encode($chartData, $jsonFlags) ?>,
                backgroundColor: '#174D3D',
                borderRadius: 6,
                maxBarThickness: 48
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + '%'; } } }
            },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/partials/foot.php'; ?>
