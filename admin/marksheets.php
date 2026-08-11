<?php
/**
 * =============================================================================
 *  Admin — Marksheets (student result sheets: marksheets + marksheet_subjects)
 * =============================================================================
 *  list · create · edit · publish · delete. Each marksheet owns a set of subject
 *  rows (a parallel-input editor); totals/percentage/grade/result are recomputed
 *  from those rows on every save via marksheet_recompute(). Publishing assigns a
 *  serial + QR token (marksheet_ensure_identity) and powers the public
 *  verify-award page and the printable marksheet-view endpoint. Standard admin
 *  pattern: CSRF-guarded POST handlers, prepared statements, pagination + search
 *  + filters, stat cards, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'marksheets';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$results       = ['pass' => 'Pass', 'fail' => 'Fail', 'pending' => 'Pending'];
$resultPill    = ['pass' => 'pill-green', 'fail' => 'pill-red', 'pending' => 'pill-gray'];
$publishedOpts = ['1' => 'Published', '0' => 'Draft'];

/* =============================================================================
 |  SAVE (create / edit) — writes the marksheet then rebuilds its subject rows
 |============================================================================*/
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'Marksheet not found.');
        redirect('/admin/marksheets');
    }

    $studentId = (int) post('student_id', 0);
    $title     = clean(post('title'));

    $errors = [];
    if ($title === '')                                        { $errors[] = 'Title is required.'; }
    if (!$studentId || !find('school_students', $studentId)) { $errors[] = 'Please select a valid student.'; }

    if ($errors) {
        flash_old($_POST);
        set_flash('error', implode(' ', $errors));
        redirect('/admin/marksheets?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'student_id'    => $studentId,
        'title'         => $title,
        'term'          => clean(post('term')) ?: null,
        'academic_year' => clean(post('academic_year')) ?: null,
        'remarks'       => clean(post('remarks')) ?: null,
        'rank'          => (int) post('rank', 0) ?: null,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        $msId = $editId;
    } else {
        $data['created_by'] = $_SESSION['user']['id'] ?? null;
        $msId = db_insert($table, $data);
    }

    // Rebuild subject rows: wipe then re-insert every row with a non-empty subject.
    $subjects = (array) post('subject', []);
    $maxMarks = (array) post('max_marks', []);
    $obtMarks = (array) post('obtained_marks', []);
    db_delete('marksheet_subjects', 'marksheet_id = :m', [':m' => $msId]);
    $sort = 0;
    foreach ($subjects as $i => $subjRaw) {
        $subj = clean((string) $subjRaw);
        if ($subj === '') {
            continue;
        }
        db_insert('marksheet_subjects', [
            'marksheet_id'   => $msId,
            'subject'        => $subj,
            'max_marks'      => (float) ($maxMarks[$i] ?? 0),
            'obtained_marks' => (float) ($obtMarks[$i] ?? 0),
            'sort_order'     => $sort++,
        ]);
    }

    // Recompute totals/percentage/grade/result (and per-subject grades) from rows.
    marksheet_recompute($msId);

    log_activity($editId ? 'update' : 'create', 'marksheets',
        ($editId ? 'Updated' : 'Created') . ' marksheet #' . $msId);
    set_flash('success', 'Marksheet ' . ($editId ? 'updated' : 'created') . '.');
    redirect('/admin/marksheets');
}

/* =============================================================================
 |  PUBLISH / UNPUBLISH — publishing assigns serial + QR token
 |============================================================================*/
if (is_post() && post('_do') === 'publish') {
    require_csrf();
    $pubId = (int) post('id', 0);
    $row   = find($table, $pubId);
    if ($row) {
        $newState = ((int) $row['published']) ? 0 : 1;
        if ($newState === 1) {
            marksheet_ensure_identity($row); // serial + QR before it goes public
        }
        db_update($table, ['published' => $newState], 'id = :id', [':id' => $pubId]);
        log_activity('update', 'marksheets',
            ($newState ? 'Published' : 'Unpublished') . ' marksheet #' . $pubId);
        set_flash('success', $newState ? 'Marksheet published.' : 'Marksheet unpublished.');
    }
    redirect('/admin/marksheets');
}

/* =============================================================================
 |  DELETE
 |============================================================================*/
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]); // subject rows cascade
        log_activity('delete', 'marksheets', 'Deleted marksheet #' . $delId);
        set_flash('success', 'Marksheet deleted.');
    }
    redirect('/admin/marksheets');
}

/* =============================================================================
 |  CREATE / EDIT FORM
 |============================================================================*/
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Marksheet not found.');
        redirect('/admin/marksheets');
    }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));

    $students   = db_all(
        "SELECT s.id, s.name, s.student_code, sc.name AS school_name
           FROM school_students s
           LEFT JOIN schools sc ON sc.id = s.school_id
          ORDER BY s.name"
    );
    $selStudent = (int) old('student_id', $row['student_id'] ?? (int) get('student', 0));

    // Subject editor rows: prefer re-posted values after a validation bounce,
    // else the saved rows; always pad with 5 trailing blanks.
    $oldSubjects = old('subject');
    if (is_array($oldSubjects)) {
        $oldMax = (array) old('max_marks');
        $oldObt = (array) old('obtained_marks');
        $editorRows = [];
        foreach ($oldSubjects as $i => $sv) {
            $editorRows[] = [
                'subject'        => (string) $sv,
                'max_marks'      => (string) ($oldMax[$i] ?? ''),
                'obtained_marks' => (string) ($oldObt[$i] ?? ''),
            ];
        }
    } else {
        $editorRows = $action === 'edit'
            ? db_all('SELECT subject, max_marks, obtained_marks FROM marksheet_subjects WHERE marksheet_id = :m ORDER BY sort_order, id', [':m' => $id])
            : [];
    }
    for ($i = 0; $i < 5; $i++) {
        $editorRows[] = ['subject' => '', 'max_marks' => '', 'obtained_marks' => ''];
    }

    $page_title = $action === 'edit' ? 'Edit Marksheet' : 'New Marksheet';
    include __DIR__ . '/partials/head.php';
    ?>
    <style>
        /* Layout only — column widths for the parallel-input subject editor. */
        .ms-remarks { min-height: 90px; }
        .ms-w-mark { width: 110px; }
        .ms-w-act { width: 44px; }
    </style>

    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Marksheets / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('marksheets')) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('marksheets')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="grid-2 cols-top">
            <div class="panel"><div class="panel-body">
                <h2 class="panel-title mb-3">Marksheet details</h2>
                <div class="form-group"><label class="form-label">Student <span class="req">*</span></label>
                    <select class="form-select" name="student_id" required>
                        <option value="">Select student…</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $selStudent === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['name']) ?><?= $s['student_code'] ? ' · ' . e($s['student_code']) : '' ?><?= $s['school_name'] ? ' — ' . e($s['school_name']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$students): ?><small class="form-hint">No students enrolled yet.</small><?php endif; ?>
                </div>
                <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" required value="<?= $o('title') ?>" placeholder="e.g. Term 1 Report Card"></div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Term</label>
                        <input class="form-control" name="term" value="<?= $o('term') ?>" placeholder="e.g. Term 1 / Annual"></div>
                    <div class="form-group"><label class="form-label">Academic year</label>
                        <input class="form-control" name="academic_year" value="<?= $o('academic_year') ?>" placeholder="e.g. 2025-26"></div>
                </div>
                <div class="form-group"><label class="form-label">Rank</label>
                    <input class="form-control" type="number" min="1" name="rank" value="<?= $o('rank') ?>" placeholder="Class rank (optional)"></div>
                <div class="form-group"><label class="form-label">Remarks</label>
                    <textarea class="form-textarea ms-remarks" name="remarks"><?= e(old('remarks', $row['remarks'] ?? '')) ?></textarea></div>
                <small class="form-hint">Totals, percentage, grade and pass/fail are calculated automatically from the subjects.</small>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h2 class="panel-title mb-3">Subjects &amp; marks</h2>
                <div class="table-wrap"><table class="admin-table" id="subjectsTable">
                    <thead><tr><th>Subject</th><th class="ms-w-mark">Max</th><th class="ms-w-mark">Obtained</th><th class="ms-w-act"></th></tr></thead>
                    <tbody>
                    <?php foreach ($editorRows as $sr): ?>
                        <tr>
                            <td><input class="form-control" name="subject[]" value="<?= e($sr['subject']) ?>" placeholder="e.g. Mathematics"></td>
                            <td><input class="form-control" type="number" step="0.01" min="0" name="max_marks[]" value="<?= e($sr['max_marks']) ?>"></td>
                            <td><input class="form-control" type="number" step="0.01" min="0" name="obtained_marks[]" value="<?= e($sr['obtained_marks']) ?>"></td>
                            <td><button type="button" class="icon-btn danger" onclick="this.closest('tr').remove()" title="Remove row"><?= lucide('trash-2') ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <button type="button" class="btn btn-outline btn-sm" id="addSubjectRow"><?= lucide('plus') ?> Add subject</button>
                <small class="form-hint">Empty rows are ignored on save.</small>
                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> marksheet</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('marksheets')) ?>">Cancel</a>
                </div>
            </div></div>
        </div>
    </form>

    <script>
    document.getElementById('addSubjectRow')?.addEventListener('click', function () {
        var body = document.querySelector('#subjectsTable tbody');
        if (!body || !body.rows.length) { return; }
        var clone = body.rows[0].cloneNode(true);
        clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        body.appendChild(clone);
    });
    </script>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* =============================================================================
 |  LIST
 |============================================================================*/
$page_title      = 'Marksheets';
$search          = trim((string) get('q', ''));
$resultFilter    = (string) get('result', '');
$publishedFilter = (string) get('published', '');

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', st.name, st.student_code, m.title, m.serial) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
if (isset($results[$resultFilter])) {
    $where .= ' AND m.result = :res';
    $params[':res'] = $resultFilter;
}
if ($publishedFilter === '1' || $publishedFilter === '0') {
    $where .= ' AND m.published = :pub';
    $params[':pub'] = (int) $publishedFilter;
}

$p = paginate(
    "SELECT m.*, st.name AS student_name, st.student_code
       FROM marksheets m
       LEFT JOIN school_students st ON st.id = m.student_id
      WHERE $where ORDER BY m.id DESC",
    $params, 15
);

$counts = [
    'total'     => db_count('marksheets'),
    'published' => db_count('marksheets', 'published = 1'),
    'pass'      => db_count('marksheets', "result = 'pass'"),
    'fail'      => db_count('marksheets', "result = 'fail'"),
];

include __DIR__ . '/partials/head.php';
?>
<style>
    /* Layout only. */
    .ms-filters { gap: var(--sp-2); }
</style>

<div class="admin-page-head">
    <div><h1>Marksheets</h1><span class="muted"><?= (int) $p['total'] ?> result sheets</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('marksheets?action=create')) ?>">+ New Marksheet</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('file-spreadsheet') ?></div><div><div class="stat-value"><?= (int) $counts['total'] ?></div><div class="stat-label">Marksheets</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('badge-check') ?></div><div><div class="stat-value"><?= (int) $counts['published'] ?></div><div class="stat-label">Published</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['pass'] ?></div><div class="stat-label">Passed</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('circle-x') ?></div><div><div class="stat-value"><?= (int) $counts['fail'] ?></div><div class="stat-label">Failed</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('marksheets')) ?>">
            <?php if ($resultFilter !== ''): ?><input type="hidden" name="result" value="<?= e($resultFilter) ?>"><?php endif; ?>
            <?php if ($publishedFilter !== ''): ?><input type="hidden" name="published" value="<?= e($publishedFilter) ?>"><?php endif; ?>
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search student, code, title, serial…">
        </form>
        <form method="get" action="<?= e(admin_url('marksheets')) ?>" class="flex ms-filters">
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            <select class="form-select" name="result" onchange="this.form.submit()">
                <option value="">All results</option>
                <?php foreach ($results as $k => $l): ?><option value="<?= e($k) ?>" <?= $resultFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="published" onchange="this.form.submit()">
                <option value="">All states</option>
                <?php foreach ($publishedOpts as $k => $l): ?><option value="<?= e($k) ?>" <?= $publishedFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($search !== '' || $resultFilter !== '' || $publishedFilter !== ''): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('marksheets')) ?>">Clear</a>
        <?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Serial</th><th>Student</th><th>Title</th><th>Term / Year</th><th class="num">%</th><th>Grade</th><th>Result</th><th>Published</th><th class="num">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): ?>
            <tr>
                <td><?= $r['serial'] ? '<strong>' . e($r['serial']) . '</strong>' : '<span class="text-muted">—</span>' ?></td>
                <td><?= e($r['student_name'] ?? '—') ?><?php if (!empty($r['student_code'])): ?><br><small class="text-muted"><?= e($r['student_code']) ?></small><?php endif; ?></td>
                <td><?= e($r['title']) ?></td>
                <td><?php
                    $tp = array_filter([$r['term'] ?? '', $r['academic_year'] ?? '']);
                    echo $tp ? e(implode(' · ', $tp)) : '<span class="text-muted">—</span>';
                ?></td>
                <td class="num"><?= e(number_format((float) $r['percentage'], 2)) ?>%</td>
                <td><?= $r['grade'] ? e($r['grade']) : '<span class="text-muted">—</span>' ?></td>
                <td><span class="pill <?= e($resultPill[$r['result']] ?? 'pill-gray') ?>"><?= e($results[$r['result']] ?? ucfirst((string) $r['result'])) ?></span></td>
                <td><span class="pill <?= ((int) $r['published']) ? 'pill-green' : 'pill-gray' ?>"><?= ((int) $r['published']) ? 'Published' : 'Draft' ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('marksheet-view?id=' . $r['id'])) ?>" target="_blank" rel="noopener" title="View / print"><?= lucide('eye') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('marksheets?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('marksheets')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="publish"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn" type="submit" title="<?= ((int) $r['published']) ? 'Unpublish' : 'Publish' ?>"><?= lucide(((int) $r['published']) ? 'eye-off' : 'send') ?></button>
                    </form>
                    <form method="post" action="<?= e(admin_url('marksheets')) ?>" data-confirm="Delete this marksheet and all its subject rows? This cannot be undone.">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('file-spreadsheet') ?></div>
            <?= ($search !== '' || $resultFilter !== '' || $publishedFilter !== '') ? 'No marksheets match your filters. ' : 'No marksheets yet. ' ?>
            <a href="<?= e(admin_url('marksheets?action=create')) ?>">Create one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
