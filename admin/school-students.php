<?php
/**
 * =============================================================================
 *  Admin — School Students (per-school enrolment management)
 * =============================================================================
 *  list · create · edit · delete · CSV import/export. Works stand-alone or in a
 *  single-school context via ?school=<id> (defaults forms/filters and is kept
 *  through "+ Add" links and post-save redirects). Standard admin pattern:
 *  CSRF-guarded POST handlers, prepared statements, pagination + search +
 *  filters, stat cards, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table     = 'school_students';
$action    = get('action', 'list');
$id        = (int) get('id', 0);
$schoolCtx = (int) get('school', 0);

$statuses = ['enrolled' => 'Enrolled', 'active' => 'Active', 'completed' => 'Completed', 'dropped' => 'Dropped'];
$genders  = ['' => '— Select —', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

/** Build the list URL, preserving the school context when set. */
$listUrl = static function (int $sid): string {
    return '/admin/school-students' . ($sid ? '?school=' . $sid : '');
};

/** Small local CSV date parser: accepts YYYY-MM-DD or DD/MM/YYYY (or DD-MM-YYYY). */
function student_csv_date(string $v): ?string
{
    $v = trim($v);
    if ($v === '') {
        return null;
    }
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $v, $m)) {
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $v : null;
    }
    if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $v, $m)) {
        [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }
    return null;
}

/* =============================================================================
 |  SAVE (create / edit)
 |============================================================================*/
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $ctx    = (int) post('school', 0);
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'Student not found.');
        redirect($listUrl($ctx));
    }

    $name     = clean(post('name'));
    $schoolId = (int) post('school_id', 0);

    $errors = [];
    if ($name === '')                          { $errors[] = 'Student name is required.'; }
    if (!$schoolId || !find('schools', $schoolId)) { $errors[] = 'Please select a valid school.'; }

    if ($errors) {
        flash_old($_POST);
        set_flash('error', implode(' ', $errors));
        $backSchool = $schoolId ?: $ctx;
        redirect('/admin/school-students?action=' . ($editId ? 'edit&id=' . $editId : 'create')
            . ($backSchool ? '&school=' . $backSchool : ''));
    }

    // Program: keep only a known NGO program id, else null.
    $programId = (int) post('program_id', 0) ?: null;
    if ($programId !== null && !isset(school_programs()[$programId])) {
        $programId = null;
    }

    // Batch: must belong to the chosen school, else null it.
    $batchId = (int) post('batch_id', 0) ?: null;
    if ($batchId !== null) {
        $batch = find('school_batches', $batchId);
        if (!$batch || (int) $batch['school_id'] !== $schoolId) {
            $batchId = null;
        }
    }

    $data = [
        'school_id'       => $schoolId,
        'name'            => $name,
        'guardian_name'   => clean(post('guardian_name')) ?: null,
        'gender'          => in_array(post('gender'), ['male', 'female', 'other'], true) ? post('gender') : null,
        'dob'             => clean(post('dob')) ?: null,
        'grade'           => clean(post('grade')) ?: null,
        'roll_no'         => clean(post('roll_no')) ?: null,
        'phone'           => clean(post('phone')) ?: null,
        'email'           => strtolower(clean(post('email'))) ?: null,
        'address'         => clean(post('address')) ?: null,
        'program_id'      => $programId,
        'batch_id'        => $batchId,
        'enrollment_date' => clean(post('enrollment_date')) ?: null,
        'status'          => isset($statuses[(string) post('status')]) ? post('status') : 'enrolled',
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'schools', 'Updated student #' . $editId);
        set_flash('success', 'Student updated.');
        redirect($listUrl($ctx));
    }

    $newId   = db_insert($table, $data);
    $student = student_ensure_code(find($table, $newId));
    log_activity('create', 'schools', 'Enrolled student #' . $newId . ' (' . $name . ')');
    set_flash('success', 'Student enrolled. Code ' . $student['student_code'] . '.');
    redirect($listUrl($ctx));
}

/* =============================================================================
 |  DELETE
 |============================================================================*/
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $ctx   = (int) post('school', 0);
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]); // attendance/fee links cascade / null
        log_activity('delete', 'schools', 'Deleted student #' . $delId . ' (' . ($row['name'] ?? '') . ')');
        set_flash('success', 'Student deleted.');
    }
    redirect($listUrl($ctx));
}

/* =============================================================================
 |  CSV IMPORT
 |============================================================================*/
if (is_post() && post('_do') === 'import') {
    require_csrf();
    $schoolId = (int) post('school_id', 0);
    if (!$schoolId || !find('schools', $schoolId)) {
        set_flash('error', 'Please choose a school to import into.');
        redirect('/admin/school-students?action=import' . ($schoolId ? '&school=' . $schoolId : ''));
    }
    if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        set_flash('error', 'Please choose a CSV file to import.');
        redirect('/admin/school-students?action=import&school=' . $schoolId);
    }
    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    if (!$fh) {
        set_flash('error', 'Could not read the uploaded file.');
        redirect('/admin/school-students?action=import&school=' . $schoolId);
    }

    // NGO program lookup by lower-cased title, plus valid ids.
    $programs       = school_programs(); // [id => title]
    $programsByName = [];
    foreach ($programs as $pid => $ptitle) {
        $programsByName[strtolower(trim((string) $ptitle))] = (int) $pid;
    }

    $header = fgetcsv($fh);
    if (!$header) {
        fclose($fh);
        set_flash('error', 'The CSV appears to be empty.');
        redirect('/admin/school-students?action=import&school=' . $schoolId);
    }
    // Strip a UTF-8 BOM from the first header cell (Excel-saved CSVs).
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
    }
    $cols = array_map(static fn($h) => strtolower(trim((string) $h)), $header);
    $idx  = array_flip($cols);
    $val  = static function (array $row, array $idx, string $key): string {
        return isset($idx[$key]) ? trim((string) ($row[$idx[$key]] ?? '')) : '';
    };

    $created = 0; $skipped = 0;
    while (($row = fgetcsv($fh)) !== false) {
        $name = $val($row, $idx, 'name');
        if ($name === '') { $skipped++; continue; }

        $programRaw = strtolower($val($row, $idx, 'program'));
        $programId  = $programsByName[$programRaw]
            ?? (ctype_digit($programRaw) && isset($programs[(int) $programRaw]) ? (int) $programRaw : null);

        $fields = [
            'school_id'     => $schoolId,
            'name'          => $name,
            'guardian_name' => $val($row, $idx, 'guardian_name') ?: null,
            'gender'        => in_array($val($row, $idx, 'gender'), ['male', 'female', 'other'], true) ? $val($row, $idx, 'gender') : null,
            'dob'           => student_csv_date($val($row, $idx, 'dob')),
            'grade'         => $val($row, $idx, 'grade') ?: null,
            'roll_no'       => $val($row, $idx, 'roll_no') ?: null,
            'phone'         => $val($row, $idx, 'phone') ?: null,
            'email'         => strtolower($val($row, $idx, 'email')) ?: null,
            'address'       => $val($row, $idx, 'address') ?: null,
            'program_id'    => $programId,
            'status'        => isset($statuses[$val($row, $idx, 'status')]) ? $val($row, $idx, 'status') : 'enrolled',
        ];

        $nid = db_insert($table, $fields);
        student_ensure_code(find($table, $nid));
        $created++;
    }
    fclose($fh);
    log_activity('import', 'schools', "Student CSV import: $created created, $skipped skipped (school #$schoolId)");
    set_flash('success', "Import complete — $created created, $skipped skipped.");
    redirect('/admin/school-students?school=' . $schoolId);
}

/* =============================================================================
 |  CSV EXPORT (this school, or all schools when no context)
 |============================================================================*/
if ($action === 'export') {
    $exportSchool = (int) get('school', 0);
    $where  = '1=1';
    $params = [];
    if ($exportSchool) {
        $where .= ' AND s.school_id = :sid';
        $params[':sid'] = $exportSchool;
    }
    $rows = db_all(
        "SELECT s.*, sc.name AS school_name, b.name AS batch_name
           FROM school_students s
           LEFT JOIN schools sc        ON sc.id = s.school_id
           LEFT JOIN school_batches b  ON b.id  = s.batch_id
          WHERE $where ORDER BY s.id DESC",
        $params
    );
    $programs = school_programs();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="school-students-' . date('Y-m-d') . '.csv"');
    // Neutralise spreadsheet formula injection (=,+,-,@,tab,CR) on export.
    $safe = static function ($v): string {
        $v = (string) $v;
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };
    $out = fopen('php://output', 'w');
    fputcsv($out, ['student_code', 'school', 'name', 'guardian_name', 'gender', 'dob', 'grade',
        'roll_no', 'phone', 'email', 'address', 'program', 'batch', 'enrollment_date', 'status']);
    foreach ($rows as $r) {
        $prog = $r['program_id'] ? ($programs[(int) $r['program_id']] ?? '') : '';
        fputcsv($out, array_map($safe, [
            $r['student_code'], $r['school_name'], $r['name'], $r['guardian_name'], $r['gender'],
            $r['dob'], $r['grade'], $r['roll_no'], $r['phone'], $r['email'], $r['address'],
            $prog, $r['batch_name'], $r['enrollment_date'], $r['status'],
        ]));
    }
    fclose($out);
    exit;
}

/* Sample CSV download. */
if ($action === 'sample') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="school-students-sample.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['name', 'guardian_name', 'gender', 'dob', 'grade', 'roll_no', 'phone', 'email', 'address', 'program', 'status']);
    fputcsv($out, ['Ravi Kumar', 'Suresh Kumar', 'male', '2012-06-15', 'Class 6', 'A-14', '9876543210', 'ravi@example.com', '15 Station Road, Patna', 'Remedial English', 'enrolled']);
    fclose($out);
    exit;
}

/* =============================================================================
 |  CSV IMPORT FORM
 |============================================================================*/
if ($action === 'import') {
    $schoolsList = school_options();
    $page_title  = 'Import Students (CSV)';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Import Students</h1><span class="muted">Students / CSV import</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('school-students' . ($schoolCtx ? '?school=' . $schoolCtx : ''))) ?>">← Back to list</a>
    </div>
    <div class="panel"><div class="panel-body">
        <p>Upload a <code>.csv</code> with a header row. Recognised columns (extra columns are ignored):</p>
        <p><code>name, guardian_name, gender, dob, grade, roll_no, phone, email, address, program, status</code></p>
        <ul class="text-muted" style="font-size:.9rem;">
            <li><strong>name</strong> is required; rows without it are skipped. Every row is imported into the school you choose below.</li>
            <li><strong>program</strong> may be the NGO program title or its id. <strong>gender</strong> = male / female / other; <strong>status</strong> = enrolled / active / completed / dropped.</li>
            <li>Dates (<strong>dob</strong>) accept <code>YYYY-MM-DD</code> or <code>DD/MM/YYYY</code>. A student code is generated automatically.</li>
        </ul>
        <form method="post" action="<?= e(admin_url('school-students')) ?>" enctype="multipart/form-data" class="mt-3">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="import">
            <div class="grid-2">
                <div class="form-group"><label class="form-label">School <span class="req">*</span></label>
                    <select class="form-select" name="school_id" required>
                        <option value="">Select school…</option>
                        <?php foreach ($schoolsList as $sid => $sname): ?>
                            <option value="<?= (int) $sid ?>" <?= $schoolCtx === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">CSV file <span class="req">*</span></label>
                    <input class="form-control" type="file" name="csv" accept=".csv,text/csv" required></div>
            </div>
            <div class="form-actions mt-3">
                <button class="btn btn-primary" type="submit"><?= lucide('upload') ?> Import</button>
                <a class="btn btn-outline" href="<?= e(admin_url('school-students?action=sample')) ?>"><?= lucide('file-down') ?> Download sample CSV</a>
            </div>
        </form>
    </div></div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* =============================================================================
 |  CREATE / EDIT FORM
 |============================================================================*/
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Student not found.');
        redirect($listUrl($schoolCtx));
    }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));

    // School driving the batch list: context param wins, else the row's school.
    $formSchool  = (int) ($schoolCtx ?: (int) ($row['school_id'] ?? 0));
    $schoolsList = school_options();
    $programs    = school_programs();
    $batches     = $formSchool
        ? db_all('SELECT id, name FROM school_batches WHERE school_id = :s ORDER BY name', [':s' => $formSchool])
        : [];
    // URL the school <select> reloads to so batch options refresh for the picked school.
    $reloadBase = admin_url('school-students?action=' . $action . ($id ? '&id=' . $id : ''));

    $page_title = $action === 'edit' ? 'Edit Student' : 'Enrol Student';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Students / <?= $action === 'edit' ? 'Edit' : 'Enrol' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('school-students' . ($schoolCtx ? '?school=' . $schoolCtx : ''))) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('school-students')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
        <input type="hidden" name="school" value="<?= (int) $schoolCtx ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Student details</h3>
                <div class="form-group"><label class="form-label">School <span class="req">*</span></label>
                    <select class="form-select" name="school_id" required
                        onchange="location.href='<?= e($reloadBase) ?>&school=' + this.value">
                        <option value="">Select school…</option>
                        <?php foreach ($schoolsList as $sid => $sname): ?>
                            <option value="<?= (int) $sid ?>" <?= $formSchool === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Changing the school updates the available batches.</small>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Full name <span class="req">*</span></label>
                        <input class="form-control" name="name" required value="<?= $o('name') ?>"></div>
                    <div class="form-group"><label class="form-label">Guardian name</label>
                        <input class="form-control" name="guardian_name" value="<?= $o('guardian_name') ?>"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Gender</label>
                        <select class="form-select" name="gender">
                            <?php foreach ($genders as $gk => $gl): ?><option value="<?= e($gk) ?>" <?= ($row['gender'] ?? '') === $gk ? 'selected' : '' ?>><?= e($gl) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Date of birth</label>
                        <input class="form-control" type="date" name="dob" value="<?= $o('dob') ?>"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Grade / class</label>
                        <input class="form-control" name="grade" value="<?= $o('grade') ?>" placeholder="e.g. Class 6"></div>
                    <div class="form-group"><label class="form-label">Roll no.</label>
                        <input class="form-control" name="roll_no" value="<?= $o('roll_no') ?>"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Phone</label>
                        <input class="form-control" name="phone" value="<?= $o('phone') ?>"></div>
                    <div class="form-group"><label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" value="<?= $o('email') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Address</label>
                    <input class="form-control" name="address" value="<?= $o('address') ?>"></div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Enrolment</h3>
                <div class="form-group"><label class="form-label">NGO program</label>
                    <select class="form-select" name="program_id">
                        <option value="">— None —</option>
                        <?php foreach ($programs as $pid => $ptitle): ?>
                            <option value="<?= (int) $pid ?>" <?= (int) ($row['program_id'] ?? 0) === (int) $pid ? 'selected' : '' ?>><?= e($ptitle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$programs): ?><small class="form-hint">No active programs found.</small><?php endif; ?>
                </div>
                <div class="form-group"><label class="form-label">Batch</label>
                    <select class="form-select" name="batch_id">
                        <option value="">— None —</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= (int) ($row['batch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($formSchool && !$batches): ?><small class="form-hint">This school has no batches yet.</small>
                    <?php elseif (!$formSchool): ?><small class="form-hint">Select a school to list its batches.</small><?php endif; ?>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Enrollment date</label>
                        <input class="form-control" type="date" name="enrollment_date"
                            value="<?= e(old('enrollment_date', $row['enrollment_date'] ?? ($action === 'create' ? date('Y-m-d') : ''))) ?>"></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['status'] ?? 'enrolled') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Enrol' ?> student</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('school-students' . ($schoolCtx ? '?school=' . $schoolCtx : ''))) ?>">Cancel</a>
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
 |  LIST
 |============================================================================*/
$page_title    = 'Students';
$search        = trim((string) get('q', ''));
$programFilter = (int) get('program', 0);
$statusFilter  = (string) get('status', '');
$ctxSchool     = $schoolCtx ? find('schools', $schoolCtx) : null;

$where  = '1=1';
$params = [];
if ($schoolCtx) {
    $where .= ' AND s.school_id = :sid';
    $params[':sid'] = $schoolCtx;
}
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', s.name, s.student_code, s.roll_no, s.phone) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
if ($programFilter > 0) {
    $where .= ' AND s.program_id = :prog';
    $params[':prog'] = $programFilter;
}
if (isset($statuses[$statusFilter])) {
    $where .= ' AND s.status = :st';
    $params[':st'] = $statusFilter;
}

$p = paginate(
    "SELECT s.*, sc.name AS school_name, b.name AS batch_name
       FROM school_students s
       LEFT JOIN schools sc       ON sc.id = s.school_id
       LEFT JOIN school_batches b ON b.id  = s.batch_id
      WHERE $where ORDER BY s.id DESC",
    $params, 15
);

// Stat cards — scoped to the school filter when one is set.
$sp   = $schoolCtx ? [':s' => $schoolCtx] : [];
$base = $schoolCtx ? 'school_id = :s' : '';
$and  = $base ? $base . ' AND ' : '';
$counts = [
    'total'     => db_count('school_students', $base, $sp),
    'active'    => db_count('school_students', $and . "status IN ('enrolled','active')", $sp),
    'completed' => db_count('school_students', $and . "status = 'completed'", $sp),
    'dropped'   => db_count('school_students', $and . "status = 'dropped'", $sp),
];

$ctxQS     = $schoolCtx ? '&school=' . $schoolCtx : '';
$addUrl    = admin_url('school-students?action=create' . $ctxQS);
$importUrl = admin_url('school-students?action=import' . $ctxQS);
$exportUrl = admin_url('school-students?action=export' . $ctxQS);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Students</h1>
        <span class="muted"><?php if ($ctxSchool): ?><?= e($ctxSchool['name']) ?> · <?php endif; ?><?= (int) $p['total'] ?> enrolled</span>
    </div>
    <div class="flex" style="gap:.5rem;">
        <?php if ($ctxSchool): ?><a class="btn btn-secondary" href="<?= e(admin_url('school-dashboard?id=' . $schoolCtx)) ?>">← Dashboard</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= e($exportUrl) ?>"><?= lucide('download') ?> Export</a>
        <a class="btn btn-outline" href="<?= e($importUrl) ?>"><?= lucide('upload') ?> Import</a>
        <a class="btn btn-primary" href="<?= e($addUrl) ?>">+ Enrol Student</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $counts['total'] ?></div><div class="stat-label">Total students</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('user-check') ?></div><div><div class="stat-value"><?= (int) $counts['active'] ?></div><div class="stat-label">Active</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('graduation-cap') ?></div><div><div class="stat-value"><?= (int) $counts['completed'] ?></div><div class="stat-label">Completed</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('user-x') ?></div><div><div class="stat-value"><?= (int) $counts['dropped'] ?></div><div class="stat-label">Dropped</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('school-students')) ?>">
            <?php if ($schoolCtx): ?><input type="hidden" name="school" value="<?= (int) $schoolCtx ?>"><?php endif; ?>
            <?php if ($programFilter): ?><input type="hidden" name="program" value="<?= (int) $programFilter ?>"><?php endif; ?>
            <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, code, roll no, phone…">
        </form>
        <form method="get" action="<?= e(admin_url('school-students')) ?>" class="flex" style="gap:.5rem;">
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            <select class="form-select" name="school" onchange="this.form.submit()">
                <option value="">All schools</option>
                <?php foreach (school_options() as $sid => $sname): ?><option value="<?= (int) $sid ?>" <?= $schoolCtx === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="program" onchange="this.form.submit()">
                <option value="">All programs</option>
                <?php foreach (school_programs() as $pid => $ptitle): ?><option value="<?= (int) $pid ?>" <?= $programFilter === (int) $pid ? 'selected' : '' ?>><?= e($ptitle) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($search !== '' || $schoolCtx || $programFilter || $statusFilter !== ''): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('school-students')) ?>">Clear</a>
        <?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Student</th><th>Guardian</th><th>Program</th><th>Batch</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $editUrl = admin_url('school-students?action=edit&id=' . $r['id'] . $ctxQS);
            $prog    = $r['program_id'] ? (school_programs()[(int) $r['program_id']] ?? null) : null;
        ?>
            <tr>
                <td>
                    <strong><?= e($r['name']) ?></strong><br>
                    <small class="text-muted"><?= e($r['student_code'] ?? '—') ?><?= $r['grade'] ? ' · ' . e($r['grade']) : '' ?><?= (!$schoolCtx && $r['school_name']) ? ' · ' . e($r['school_name']) : '' ?></small>
                </td>
                <td><?= $r['guardian_name'] ? e($r['guardian_name']) : '—' ?></td>
                <td><?= $prog ? e($prog) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $r['batch_name'] ? e($r['batch_name']) : '<span class="text-muted">—</span>' ?></td>
                <td><span class="pill <?= e(student_status_pill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e($editUrl) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('school-students')) ?>" data-confirm="Delete this student and their attendance & fee links? This cannot be undone." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="school" value="<?= (int) $schoolCtx ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('users') ?></div>
            <?= ($search !== '' || $schoolCtx || $programFilter || $statusFilter !== '') ? 'No students match your filters. ' : 'No students enrolled yet. ' ?>
            <a href="<?= e($addUrl) ?>">Enrol one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
