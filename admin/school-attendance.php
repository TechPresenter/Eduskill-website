<?php
/**
 * =============================================================================
 *  Admin — School Attendance (per-batch daily attendance)
 * =============================================================================
 *  Pick a batch + date, then mark each enrolled student present/absent/late/
 *  excused. Rows are upserted into school_attendance (UNIQUE batch+student+date).
 *  Standard admin pattern: CSRF, prepared statements, flash + redirect, logging.
 *  Respects an optional ?school=<id> context for filters, links and redirects.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$attStatuses = ['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'];

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    $batchId   = (int) post('batch_id', 0);
    $ctx       = (int) post('school', 0);
    $date      = (string) post('date', '');
    $batch     = $batchId ? find('school_batches', $batchId) : null;
    $dt        = DateTime::createFromFormat('Y-m-d', $date);
    $validDate = $dt && $dt->format('Y-m-d') === $date;

    $rq = ['batch_id' => $batchId, 'date' => $validDate ? $date : date('Y-m-d')];
    if ($ctx) { $rq['school'] = $ctx; }
    $backTo = '/admin/school-attendance?' . http_build_query($rq);

    if (!$batch || !$validDate) {
        set_flash('error', 'Choose a batch and a valid date before saving.');
        redirect($backTo);
    }

    $input = post('status');
    if (!is_array($input)) { $input = []; }

    $count = 0;
    foreach ($input as $sid => $st) {
        $sid = (int) $sid;
        if ($sid <= 0) { continue; }
        $st = isset($attStatuses[(string) $st]) ? (string) $st : 'present';

        // Only accept students actually enrolled in this batch.
        $student = db_row(
            'SELECT id FROM school_students WHERE id = :id AND batch_id = :b LIMIT 1',
            [':id' => $sid, ':b' => $batchId]
        );
        if (!$student) { continue; }

        $existing = db_row(
            'SELECT id FROM school_attendance WHERE batch_id = :b AND student_id = :s AND `date` = :d LIMIT 1',
            [':b' => $batchId, ':s' => $sid, ':d' => $date]
        );
        if ($existing) {
            db_update('school_attendance', ['status' => $st], 'id = :id', [':id' => (int) $existing['id']]);
        } else {
            db_insert('school_attendance', [
                'school_id'  => (int) $batch['school_id'],
                'batch_id'   => $batchId,
                'student_id' => $sid,
                'date'       => $date,
                'status'     => $st,
            ]);
        }
        $count++;
    }

    log_activity('update', 'school-attendance', 'Marked attendance for batch #' . $batchId . ' on ' . $date . ' (' . $count . ' students)');
    set_flash('success', $count . ' student' . ($count === 1 ? '' : 's') . ' marked for ' . format_date($date, 'd M Y') . '.');
    redirect($backTo);
}

/* -------------------------------------------------------------- CONTEXT + CONTROLS */
$schoolCtx  = (int) get('school', 0);
$ctxSchool  = $schoolCtx ? find('schools', $schoolCtx) : null;
if (!$ctxSchool) { $schoolCtx = 0; }
$ctxQ = $schoolCtx ? '&school=' . $schoolCtx : '';

$batchId = (int) get('batch_id', 0);
$date    = (string) get('date', date('Y-m-d'));
$dt      = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) { $date = date('Y-m-d'); }

// Batch options — scoped to the school context, else every school's batches.
if ($schoolCtx) {
    $batchOptions = db_all('SELECT id, name FROM school_batches WHERE school_id = :s ORDER BY name', [':s' => $schoolCtx]);
} else {
    $batchOptions = db_all(
        'SELECT b.id, b.name, s.name AS school_name
           FROM school_batches b JOIN schools s ON s.id = b.school_id
          ORDER BY s.name, b.name'
    );
}

// Resolve the chosen batch (respecting school context).
$batch = null;
if ($batchId) {
    $batch = db_row(
        'SELECT b.*, s.name AS school_name
           FROM school_batches b JOIN schools s ON s.id = b.school_id
          WHERE b.id = :id' . ($schoolCtx ? ' AND b.school_id = :s' : '') . ' LIMIT 1',
        $schoolCtx ? [':id' => $batchId, ':s' => $schoolCtx] : [':id' => $batchId]
    );
    if (!$batch) { $batchId = 0; }
}

$students = [];
$existing = [];
$batchRate = null;
if ($batch) {
    $students = db_all('SELECT * FROM school_students WHERE batch_id = :b ORDER BY name', [':b' => $batchId]);

    foreach (db_all('SELECT student_id, status FROM school_attendance WHERE batch_id = :b AND `date` = :d', [':b' => $batchId, ':d' => $date]) as $r) {
        $existing[(int) $r['student_id']] = $r['status'];
    }

    $rateRow = db_row(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS present
           FROM school_attendance WHERE batch_id = :b",
        [':b' => $batchId]
    );
    if ($rateRow && (int) $rateRow['total'] > 0) {
        $batchRate = round(((int) $rateRow['present'] / (int) $rateRow['total']) * 100, 1);
    }
}

$backUrl = $schoolCtx ? admin_url('school-dashboard?id=' . $schoolCtx) : admin_url('schools');

$page_title = 'Attendance';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Mark Attendance</h1>
        <span class="muted"><?= $ctxSchool ? e($ctxSchool['name']) . ' / Attendance' : 'Schools / Attendance' ?></span>
    </div>
    <a class="btn btn-secondary" href="<?= e($backUrl) ?>">← Back<?= $ctxSchool ? ' to dashboard' : ' to schools' ?></a>
</div>

<div class="panel"><div class="panel-body">
    <form class="data-toolbar" method="get" action="<?= e(admin_url('school-attendance')) ?>">
        <?php if ($schoolCtx): ?><input type="hidden" name="school" value="<?= (int) $schoolCtx ?>"><?php endif; ?>
        <div class="form-group mb-0" style="min-width:260px;">
            <label class="form-label">Batch</label>
            <select class="form-select" name="batch_id" onchange="this.form.submit()">
                <option value="">— Select batch —</option>
                <?php foreach ($batchOptions as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= $batchId === (int) $b['id'] ? 'selected' : '' ?>>
                        <?= e($schoolCtx ? $b['name'] : ($b['school_name'] . ' — ' . $b['name'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Date</label>
            <input class="form-control" type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
        </div>
        <div class="form-group mb-0" style="align-self:flex-end;">
            <button class="btn btn-secondary" type="submit"><?= lucide('search') ?> Load</button>
        </div>
    </form>
</div></div>

<?php if (!$batchOptions): ?>
    <div class="panel"><div class="panel-body">
        <div class="empty-state"><div class="icon"><?= lucide('layers') ?></div>
            No batches found<?= $ctxSchool ? ' for ' . e($ctxSchool['name']) : '' ?>.
            <a href="<?= e(admin_url('school-batches?' . ltrim($ctxQ, '&'))) ?>">Create a batch</a> first.
        </div>
    </div></div>
<?php elseif (!$batch): ?>
    <div class="panel"><div class="panel-body">
        <div class="empty-state"><div class="icon"><?= lucide('calendar-check') ?></div>
            Pick a batch and date above to mark attendance.
        </div>
    </div></div>
<?php else: ?>
    <div class="stat-grid">
        <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('users') ?></div><div><div class="stat-value"><?= count($students) ?></div><div class="stat-label">Students in batch</div></div></div>
        <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('layers') ?></div><div><div class="stat-value"><?= e($batch['name']) ?></div><div class="stat-label"><?= $schoolCtx ? e($batch['schedule'] ?: '—') : e($batch['school_name']) ?></div></div></div>
        <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('calendar-check') ?></div><div><div class="stat-value"><?= $batchRate === null ? '—' : e($batchRate) . '%' ?></div><div class="stat-label">Attendance rate</div></div></div>
        <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('calendar') ?></div><div><div class="stat-value" style="font-size:1.1rem;"><?= e(format_date($date, 'd M Y')) ?></div><div class="stat-label"><?= e(format_date($date, 'l')) ?></div></div></div>
    </div>

    <div class="panel"><div class="panel-body">
        <?php if (!$students): ?>
            <div class="empty-state"><div class="icon"><?= lucide('user-x') ?></div>
                No students are enrolled in <strong><?= e($batch['name']) ?></strong> yet.
                <a href="<?= e(admin_url('school-students?batch_id=' . $batchId . ($schoolCtx ? $ctxQ : '&school=' . (int) $batch['school_id']))) ?>">Enrol students</a> to start marking attendance.
            </div>
        <?php else: ?>
            <div class="flex items-center justify-between mb-3">
                <h3 class="mb-0"><?= e($batch['name']) ?> · <?= e(format_date($date, 'd M Y')) ?></h3>
                <span class="text-muted"><?= count($students) ?> student<?= count($students) === 1 ? '' : 's' ?></span>
            </div>
            <form class="admin-form" method="post" action="<?= e(admin_url('school-attendance')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="save">
                <input type="hidden" name="batch_id" value="<?= (int) $batchId ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <?php if ($schoolCtx): ?><input type="hidden" name="school" value="<?= (int) $schoolCtx ?>"><?php endif; ?>

                <div class="table-wrap"><table class="admin-table">
                    <thead><tr><th style="width:60px;">#</th><th>Student</th><th>Grade / Roll</th><th style="width:200px;">Status</th></tr></thead>
                    <tbody>
                    <?php $i = 1; foreach ($students as $s): $cur = $existing[(int) $s['id']] ?? 'present'; ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <strong><?= e($s['name']) ?></strong>
                                <?php if (!empty($s['student_code'])): ?><br><small class="text-muted"><?= e($s['student_code']) ?></small><?php endif; ?>
                            </td>
                            <td><?= e(trim(implode(' · ', array_filter([$s['grade'] ?? '', $s['roll_no'] ? 'Roll ' . $s['roll_no'] : ''])) ) ?: '—') ?></td>
                            <td>
                                <select class="form-select" name="status[<?= (int) $s['id'] ?>]">
                                    <?php foreach ($attStatuses as $k => $l): ?>
                                        <option value="<?= e($k) ?>" <?= $cur === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>

                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= lucide('check') ?> Save attendance</button>
                    <a class="btn btn-ghost" href="<?= e($backUrl) ?>">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div></div>
<?php endif; ?>

<?php include __DIR__ . '/partials/foot.php'; ?>
