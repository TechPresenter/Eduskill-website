<?php
/**
 * =============================================================================
 *  Admin — School Fees (fee structures + fee collection / dues / receipts)
 * =============================================================================
 *  Scoped to one partner school via ?school=<id>. When no school is selected a
 *  picker/list is shown. For the active school this page renders:
 *    (a) a compact Fee Structures mini-CRUD (struct_save / struct_delete), and
 *    (b) a "Record payment" form plus a filterable, paginated payments table
 *        with collect-more / waive / delete actions and receipt links.
 *  Standard admin pattern: CSRF-guarded POST handlers, prepared statements,
 *  pagination, stat cards, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$schoolId = (int) (is_post() ? post('school', 0) : get('school', 0));

$frequencies     = ['one-time' => 'One-time', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'term' => 'Term', 'annual' => 'Annual'];
$methods         = ['cash' => 'Cash', 'upi' => 'UPI', 'cheque' => 'Cheque', 'bank' => 'Bank', 'online' => 'Online', 'other' => 'Other'];
$paymentStatuses = ['pending' => 'Pending', 'partial' => 'Partial', 'paid' => 'Paid', 'waived' => 'Waived'];

/** Resolve the active school for a POST handler or bail back to the picker. */
$requireSchool = static function () use ($schoolId): array {
    $s = $schoolId ? find('schools', $schoolId) : null;
    if (!$s) {
        set_flash('error', 'Select a school first.');
        redirect('/admin/school-fees');
    }
    return $s;
};

/** Query string that preserves the school context + list filters for redirects. */
$back = static function () use ($schoolId): string {
    $qs = http_build_query(array_filter([
        'school' => $schoolId,
        'status' => clean((string) post('f_status', '')),
        'q'      => clean((string) post('f_q', '')),
        'page'   => (int) post('f_page', 0) ?: '',
    ], static fn($v) => $v !== '' && $v !== null));
    return $qs !== '' ? '?' . $qs : '';
};

/* =============================================================================
 |  FEE STRUCTURE — SAVE (create / edit)
 |============================================================================*/
if (is_post() && post('_do') === 'struct_save') {
    require_csrf();
    $school   = $requireSchool();
    $structId = (int) post('struct_id', 0);

    if ($structId) {
        $old = db_row("SELECT * FROM school_fee_structures WHERE id = :i AND school_id = :s", [':i' => $structId, ':s' => $schoolId]);
        if (!$old) {
            set_flash('error', 'Fee structure not found.');
            redirect('/admin/school-fees' . $back());
        }
    }

    $name = clean(post('name'));
    if ($name === '') {
        set_flash('error', 'Fee structure name is required.');
        redirect('/admin/school-fees' . $back());
    }

    $data = [
        'school_id'  => $schoolId,
        'name'       => $name,
        'amount'     => round((float) post('amount', 0), 2),
        'frequency'  => isset($frequencies[(string) post('frequency')]) ? post('frequency') : 'one-time',
        'applies_to' => clean(post('applies_to')) ?: null,
        'status'     => post('status') === '0' ? 0 : 1,
    ];

    if ($structId) {
        db_update('school_fee_structures', $data, 'id = :id', [':id' => $structId]);
        log_activity('update', 'school-fees', 'Updated fee structure #' . $structId);
        set_flash('success', 'Fee structure updated.');
    } else {
        $nid = db_insert('school_fee_structures', $data);
        log_activity('create', 'school-fees', 'Created fee structure #' . $nid);
        set_flash('success', 'Fee structure added.');
    }
    redirect('/admin/school-fees' . $back());
}

/* =============================================================================
 |  FEE STRUCTURE — DELETE
 |============================================================================*/
if (is_post() && post('_do') === 'struct_delete') {
    require_csrf();
    $school = $requireSchool();
    $delId  = (int) post('struct_id', 0);
    $row    = db_row("SELECT id FROM school_fee_structures WHERE id = :i AND school_id = :s", [':i' => $delId, ':s' => $schoolId]);
    if ($row) {
        db_delete('school_fee_structures', 'id = :id', [':id' => $delId]);
        log_activity('delete', 'school-fees', 'Deleted fee structure #' . $delId);
        set_flash('success', 'Fee structure deleted.');
    }
    redirect('/admin/school-fees' . $back());
}

/* =============================================================================
 |  PAYMENT — RECORD (new collection)
 |============================================================================*/
if (is_post() && post('_do') === 'record') {
    require_csrf();
    $school = $requireSchool();

    $title       = clean(post('title'));
    $studentId   = (int) post('student_id', 0) ?: null;
    $structureId = (int) post('structure_id', 0) ?: null;

    // Only accept a student that belongs to this school.
    if ($studentId !== null && !db_row("SELECT id FROM school_students WHERE id = :i AND school_id = :s", [':i' => $studentId, ':s' => $schoolId])) {
        $studentId = null;
    }
    // Only accept a structure that belongs to this school; use it to fill a blank title.
    $structRow = null;
    if ($structureId !== null) {
        $structRow = db_row("SELECT * FROM school_fee_structures WHERE id = :i AND school_id = :s", [':i' => $structureId, ':s' => $schoolId]);
        if (!$structRow) {
            $structureId = null;
        }
    }
    if ($title === '' && $structRow) {
        $title = (string) $structRow['name'];
    }

    if ($title === '') {
        flash_old($_POST);
        set_flash('error', 'A fee title is required.');
        redirect('/admin/school-fees' . $back());
    }

    $amount = max(0.0, round((float) post('amount', 0), 2));
    $paid   = max(0.0, round((float) post('amount_paid', 0), 2));
    $method = isset($methods[(string) post('method')]) ? post('method') : 'cash';
    $status = school_fee_status($amount, $paid);

    $newId = db_insert('school_fee_payments', [
        'school_id'    => $schoolId,
        'student_id'   => $studentId,
        'structure_id' => $structureId,
        'title'        => $title,
        'amount'       => $amount,
        'amount_paid'  => $paid,
        'status'       => $status,
        'method'       => $method,
        'receipt_no'   => null,
        'paid_at'      => $paid > 0 ? date('Y-m-d H:i:s') : null,
        'note'         => clean(post('note')) ?: null,
        'created_by'   => $_SESSION['user']['id'] ?? null,
    ]);

    $receiptNo = school_receipt_no($newId);
    db_update('school_fee_payments', ['receipt_no' => $receiptNo], 'id = :id', [':id' => $newId]);
    log_activity('create', 'school-fees', 'Recorded fee payment #' . $newId . ' for school #' . $schoolId);
    set_flash('success', 'Fee recorded. Receipt ' . $receiptNo . '.');
    redirect('/admin/school-fees' . $back());
}

/* =============================================================================
 |  PAYMENT — COLLECT MORE (add to amount_paid)
 |============================================================================*/
if (is_post() && post('_do') === 'collect') {
    require_csrf();
    $school = $requireSchool();
    $payId  = (int) post('id', 0);
    $row    = db_row("SELECT * FROM school_fee_payments WHERE id = :i AND school_id = :s", [':i' => $payId, ':s' => $schoolId]);
    if (!$row) {
        set_flash('error', 'Payment not found.');
        redirect('/admin/school-fees' . $back());
    }
    if ($row['status'] === 'waived') {
        set_flash('error', 'Cannot collect on a waived fee.');
        redirect('/admin/school-fees' . $back());
    }
    $add = round((float) post('amount', 0), 2);
    if ($add <= 0) {
        set_flash('error', 'Enter a valid amount to collect.');
        redirect('/admin/school-fees' . $back());
    }
    $newPaid = round((float) $row['amount_paid'] + $add, 2);
    $status  = school_fee_status((float) $row['amount'], $newPaid);
    db_update('school_fee_payments', [
        'amount_paid' => $newPaid,
        'status'      => $status,
        'paid_at'     => date('Y-m-d H:i:s'),
    ], 'id = :id', [':id' => $payId]);
    log_activity('update', 'school-fees', 'Collected ' . money($add) . ' on fee #' . $payId);
    set_flash('success', money($add) . ' collected on fee #' . $payId . '.');
    redirect('/admin/school-fees' . $back());
}

/* =============================================================================
 |  PAYMENT — WAIVE
 |============================================================================*/
if (is_post() && post('_do') === 'waive') {
    require_csrf();
    $school = $requireSchool();
    $payId  = (int) post('id', 0);
    $row    = db_row("SELECT id FROM school_fee_payments WHERE id = :i AND school_id = :s", [':i' => $payId, ':s' => $schoolId]);
    if ($row) {
        db_update('school_fee_payments', ['status' => 'waived'], 'id = :id', [':id' => $payId]);
        log_activity('update', 'school-fees', 'Waived fee #' . $payId);
        set_flash('success', 'Fee waived.');
    }
    redirect('/admin/school-fees' . $back());
}

/* =============================================================================
 |  PAYMENT — DELETE
 |============================================================================*/
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $school = $requireSchool();
    $payId  = (int) post('id', 0);
    $row    = db_row("SELECT id FROM school_fee_payments WHERE id = :i AND school_id = :s", [':i' => $payId, ':s' => $schoolId]);
    if ($row) {
        db_delete('school_fee_payments', 'id = :id', [':id' => $payId]);
        log_activity('delete', 'school-fees', 'Deleted fee payment #' . $payId);
        set_flash('success', 'Fee payment deleted.');
    }
    redirect('/admin/school-fees' . $back());
}

/* =============================================================================
 |  RESOLVE SCHOOL FOR RENDERING
 |============================================================================*/
$school = $schoolId ? find('schools', $schoolId) : null;
if ($schoolId && !$school) {
    set_flash('error', 'School not found.');
    redirect('/admin/school-fees');
}

/* =============================================================================
 |  PICKER — no school selected
 |============================================================================*/
if (!$school) {
    $page_title  = 'School Fees';
    $schoolsList = db_all(
        "SELECT s.*,
                (SELECT COALESCE(SUM(amount_paid),0) FROM school_fee_payments p WHERE p.school_id = s.id) AS collected,
                (SELECT COALESCE(SUM(amount - amount_paid),0) FROM school_fee_payments p WHERE p.school_id = s.id AND p.status IN ('pending','partial')) AS dues
           FROM schools s ORDER BY s.name ASC"
    );
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>School Fees</h1><span class="muted">Pick a school to manage its fee structures &amp; collection</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('schools')) ?>">← All schools</a>
    </div>

    <div class="panel"><div class="panel-body">
        <?php if ($schoolsList): ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th>School</th><th>City</th><th>Collected</th><th>Dues</th><th style="text-align:right;">Fees</th></tr></thead>
            <tbody>
            <?php foreach ($schoolsList as $s): $link = admin_url('school-fees?school=' . $s['id']); ?>
                <tr>
                    <td><a href="<?= e($link) ?>"><strong><?= e($s['name']) ?></strong></a><br><small class="text-muted"><?= e($s['code'] ?? '—') ?></small></td>
                    <td><?= $s['city'] ? e($s['city']) : '—' ?></td>
                    <td><?= e(money($s['collected'])) ?></td>
                    <td><?= (float) $s['dues'] > 0 ? '<span class="pill pill-amber">' . e(money($s['dues'])) . '</span>' : '—' ?></td>
                    <td style="text-align:right;"><a class="btn btn-primary btn-sm" href="<?= e($link) ?>"><?= lucide('receipt-indian-rupee') ?> Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('school') ?></div>No schools yet. <a href="<?= e(admin_url('schools?action=create')) ?>">Register one</a>.</div>
        <?php endif; ?>
    </div></div>

    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* =============================================================================
 |  SCHOOL CONTEXT — data for rendering
 |============================================================================*/
$school = school_ensure_code($school);
$q      = 'school=' . $schoolId;

$statusFilter = (string) get('status', '');
$search       = trim((string) get('q', ''));
$hasFilter    = ($search !== '' || $statusFilter !== '');

$collected    = (float) db_value("SELECT COALESCE(SUM(amount_paid),0) FROM school_fee_payments WHERE school_id = :s", [':s' => $schoolId]);
$dues         = (float) db_value("SELECT COALESCE(SUM(amount - amount_paid),0) FROM school_fee_payments WHERE school_id = :s AND status IN ('pending','partial')", [':s' => $schoolId]);
$pendingCount = db_count('school_fee_payments', "school_id = :s AND status IN ('pending','partial')", [':s' => $schoolId]);

$students   = db_all("SELECT id, name, student_code FROM school_students WHERE school_id = :s ORDER BY name ASC", [':s' => $schoolId]);
$structures = db_all("SELECT * FROM school_fee_structures WHERE school_id = :s ORDER BY name ASC", [':s' => $schoolId]);

// Structure being edited (inline prefill of the mini-CRUD form).
$structEditId = (int) get('struct_edit', 0);
$sEdit = $structEditId ? db_row("SELECT * FROM school_fee_structures WHERE id = :i AND school_id = :s", [':i' => $structEditId, ':s' => $schoolId]) : null;

// Payments (filtered + paginated).
$where  = 'p.school_id = :s';
$params = [':s' => $schoolId];
if (isset($paymentStatuses[$statusFilter])) {
    $where .= ' AND p.status = :st';
    $params[':st'] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', st.name, p.title, p.receipt_no) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$pg = paginate(
    "SELECT p.*, st.name AS student_name, st.student_code
       FROM school_fee_payments p
       LEFT JOIN school_students st ON st.id = p.student_id
      WHERE $where ORDER BY p.id DESC",
    $params, 20
);

// Hidden fields echoed into every POST form so redirects return to this view.
$ff = '<input type="hidden" name="school" value="' . (int) $schoolId . '">'
    . '<input type="hidden" name="f_status" value="' . e($statusFilter) . '">'
    . '<input type="hidden" name="f_q" value="' . e($search) . '">'
    . '<input type="hidden" name="f_page" value="' . (int) $pg['current'] . '">';

$o = static fn(string $k, $d = '') => e(old($k, $d));

$page_title = 'Fees · ' . $school['name'];
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Fees — <?= e($school['name']) ?></h1>
        <span class="muted">Schools / <?= e($school['code']) ?> / Fees</span>
    </div>
    <div class="flex" style="gap:.5rem;">
        <a class="btn btn-outline" href="<?= e(admin_url('school-dashboard?id=' . $schoolId)) ?>"><?= lucide('layout-dashboard') ?> Dashboard</a>
        <a class="btn btn-secondary" href="<?= e(admin_url('school-fees')) ?>">← All schools</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('indian-rupee') ?></div><div><div class="stat-value"><?= e(money($collected)) ?></div><div class="stat-label">Collected</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('wallet') ?></div><div><div class="stat-value"><?= e(money($dues)) ?></div><div class="stat-label">Outstanding dues</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('clock') ?></div><div><div class="stat-value"><?= (int) $pendingCount ?></div><div class="stat-label">Pending / partial</div></div></div>
</div>

<div class="grid-2" style="align-items:start;gap:1.25rem;">
    <!-- ---------------------------------------------------------- Fee structures -->
    <div class="panel"><div class="panel-body">
        <h3 class="mb-3"><?= $sEdit ? 'Edit fee structure' : 'Fee structures' ?></h3>

        <form class="admin-form mb-3" method="post" action="<?= e(admin_url('school-fees')) ?>">
            <?= csrf_field() ?>
            <?= $ff ?>
            <input type="hidden" name="_do" value="struct_save">
            <input type="hidden" name="struct_id" value="<?= (int) ($sEdit['id'] ?? 0) ?>">
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" required value="<?= e($sEdit['name'] ?? '') ?>" placeholder="Tuition fee"></div>
                <div class="form-group"><label class="form-label">Amount (₹)</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="amount" value="<?= e($sEdit['amount'] ?? '') ?>"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Frequency</label>
                    <select class="form-select" name="frequency">
                        <?php foreach ($frequencies as $k => $l): ?><option value="<?= e($k) ?>" <?= ($sEdit['frequency'] ?? 'one-time') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Applies to</label>
                    <input class="form-control" name="applies_to" value="<?= e($sEdit['applies_to'] ?? '') ?>" placeholder="All / Grade 6…"></div>
            </div>
            <div class="form-group"><label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="1" <?= (int) ($sEdit['status'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= (int) ($sEdit['status'] ?? 1) === 0 ? 'selected' : '' ?>>Inactive</option>
                </select></div>
            <div class="form-actions">
                <button class="btn btn-primary btn-sm" type="submit"><?= $sEdit ? 'Update' : 'Add' ?> structure</button>
                <?php if ($sEdit): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('school-fees?' . $q)) ?>">Cancel</a><?php endif; ?>
            </div>
        </form>

        <?php if ($structures): ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th>Name</th><th>Amount</th><th>Frequency</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($structures as $st): ?>
                <tr>
                    <td><strong><?= e($st['name']) ?></strong><?= $st['applies_to'] ? '<br><small class="text-muted">' . e($st['applies_to']) . '</small>' : '' ?></td>
                    <td><?= e(money($st['amount'])) ?></td>
                    <td><?= e($frequencies[$st['frequency']] ?? $st['frequency']) ?></td>
                    <td><span class="pill <?= (int) $st['status'] === 1 ? 'pill-green' : 'pill-gray' ?>"><?= (int) $st['status'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                    <td><div class="actions">
                        <a class="icon-btn" href="<?= e(admin_url('school-fees?' . $q . '&struct_edit=' . $st['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                        <form method="post" action="<?= e(admin_url('school-fees')) ?>" data-confirm="Delete this fee structure?" style="display:inline;">
                            <?= csrf_field() ?><?= $ff ?><input type="hidden" name="_do" value="struct_delete"><input type="hidden" name="struct_id" value="<?= (int) $st['id'] ?>">
                            <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <p class="text-muted">No fee structures yet. Add one above.</p>
        <?php endif; ?>
    </div></div>

    <!-- ---------------------------------------------------------- Record payment -->
    <div class="panel"><div class="panel-body">
        <h3 class="mb-3">Record payment</h3>
        <form class="admin-form" method="post" action="<?= e(admin_url('school-fees')) ?>">
            <?= csrf_field() ?>
            <?= $ff ?>
            <input type="hidden" name="_do" value="record">
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Student</label>
                    <select class="form-select" name="student_id">
                        <option value="">General / none</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?><?= $s['student_code'] ? ' (' . e($s['student_code']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Fee structure</label>
                    <select class="form-select" name="structure_id">
                        <option value="">— None —</option>
                        <?php foreach ($structures as $st): if ((int) $st['status'] !== 1) continue; ?>
                            <option value="<?= (int) $st['id'] ?>"><?= e($st['name']) ?> · <?= e(money($st['amount'])) ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>
            <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                <input class="form-control" name="title" required value="<?= $o('title') ?>" placeholder="e.g. Tuition fee — July"></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Amount due (₹)</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="amount" value="<?= $o('amount') ?>"></div>
                <div class="form-group"><label class="form-label">Collected now (₹)</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="amount_paid" value="<?= $o('amount_paid') ?>"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Method</label>
                    <select class="form-select" name="method">
                        <?php foreach ($methods as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Note</label>
                    <input class="form-control" name="note" value="<?= $o('note') ?>" placeholder="Optional"></div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= lucide('receipt-indian-rupee') ?> Record payment</button>
            </div>
        </form>
    </div></div>
</div>

<!-- ---------------------------------------------------------------- Payments -->
<div class="panel mt-3"><div class="panel-body">
    <div class="flex items-center justify-between mb-2"><h3 class="mb-0">Payments</h3><span class="text-muted"><?= (int) $pg['total'] ?> record<?= (int) $pg['total'] === 1 ? '' : 's' ?></span></div>

    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('school-fees')) ?>">
            <input type="hidden" name="school" value="<?= (int) $schoolId ?>">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search student, title, receipt…"></form>
        <form method="get" action="<?= e(admin_url('school-fees')) ?>" class="flex" style="gap:.5rem;">
            <input type="hidden" name="school" value="<?= (int) $schoolId ?>">
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($paymentStatuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($hasFilter): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('school-fees?' . $q)) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($pg['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Date</th><th>Student</th><th>For</th><th>Amount</th><th>Paid</th><th>Due</th><th>Method</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($pg['items'] as $r):
            $due     = max(0.0, (float) $r['amount'] - (float) $r['amount_paid']);
            $settled = in_array($r['status'], ['paid', 'waived'], true);
        ?>
            <tr>
                <td><?= e(format_date($r['created_at'], 'd M Y')) ?><?= $r['receipt_no'] ? '<br><small class="text-muted">' . e($r['receipt_no']) . '</small>' : '' ?></td>
                <td><?= $r['student_name'] ? e($r['student_name']) : '<span class="text-muted">General</span>' ?></td>
                <td><?= e($r['title']) ?></td>
                <td><?= e(money($r['amount'])) ?></td>
                <td><?= e(money($r['amount_paid'])) ?></td>
                <td><?= $r['status'] === 'waived' ? '—' : ($due > 0 ? '<span class="text-muted">' . e(money($due)) . '</span>' : '—') ?></td>
                <td><?= e($methods[$r['method']] ?? $r['method']) ?></td>
                <td><span class="pill <?= e(fee_status_pill($r['status'])) ?>"><?= e($paymentStatuses[$r['status']] ?? ucfirst($r['status'])) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('school-receipt?id=' . $r['id'] . '&' . $q)) ?>" target="_blank" title="Receipt"><?= lucide('receipt') ?></a>
                    <?php if (!$settled): ?>
                        <form method="post" action="<?= e(admin_url('school-fees')) ?>" style="display:inline;" onsubmit="var v=prompt('Amount to collect now (₹):','');if(v===null||v.trim()===''){return false;}this.amount.value=v;">
                            <?= csrf_field() ?><?= $ff ?><input type="hidden" name="_do" value="collect"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="amount" value="">
                            <button class="icon-btn" type="submit" title="Collect more"><?= lucide('hand-coins') ?></button>
                        </form>
                        <form method="post" action="<?= e(admin_url('school-fees')) ?>" data-confirm="Mark this fee as waived?" style="display:inline;">
                            <?= csrf_field() ?><?= $ff ?><input type="hidden" name="_do" value="waive"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button class="icon-btn" type="submit" title="Waive"><?= lucide('ban') ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(admin_url('school-fees')) ?>" data-confirm="Delete this fee payment? This cannot be undone." style="display:inline;">
                        <?= csrf_field() ?><?= $ff ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $pg['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('receipt-indian-rupee') ?></div>
            <?= $hasFilter ? 'No payments match your filters.' : 'No fee payments recorded yet. Use the form above to record one.' ?></div>
    <?php endif; ?>
</div></div>

<?php
clear_old();
include __DIR__ . '/partials/foot.php';
