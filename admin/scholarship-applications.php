<?php
/**
 * =============================================================================
 *  Admin — Scholarship Applications  (INBOX module).
 *  list + view + status change + delete.  NO create/edit form.
 *  LEFT JOINs `scholarships` for the applied-for scholarship title, filters by
 *  status (?status=), searches name/email/institution/scholarship, shows live
 *  counts per status, links the uploaded supporting document, and moves an
 *  application through new → under_review → approved / rejected.
 *  CSRF-guarded POST handlers, prepared statements, activity logging,
 *  flash + redirect, and a read-only detail view.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'scholarship_applications';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* Allowed workflow statuses + their pill colours. */
$STATUSES = ['new', 'under_review', 'approved', 'rejected'];
$PILLS    = ['new' => 'pill-blue', 'under_review' => 'pill-amber', 'approved' => 'pill-green', 'rejected' => 'pill-red'];

/** Human-readable label for a status key ("under_review" → "Under Review"). */
$status_label = static function (string $s): string {
    return ucwords(str_replace('_', ' ', $s));
};

/**
 * Sanitise a caller-supplied return path so we only ever redirect back inside
 * this module (guards against open redirects).
 */
$safe_return = static function (string $ret): string {
    return strncmp($ret, 'scholarship-applications', 24) === 0 ? $ret : 'scholarship-applications';
};

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $sid    = (int) post('id', 0);
    $status = (string) post('status', '');
    $return = $safe_return((string) post('_return', 'scholarship-applications'));

    if (!in_array($status, $STATUSES, true)) {
        set_flash('error', 'Invalid status value.');
        redirect('/admin/' . $return);
    }
    $row = find($table, $sid);
    if ($row) {
        db_update($table, ['status' => $status], 'id = :id', [':id' => $sid]);
        log_activity('update', 'scholarship_applications', 'Set scholarship application #' . $sid . ' to ' . $status);
        set_flash('success', 'Application status updated to "' . $status_label($status) . '".');
    } else {
        set_flash('error', 'Application not found.');
    }
    redirect('/admin/' . $return);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId  = (int) post('id', 0);
    $return = $safe_return((string) post('_return', 'scholarship-applications'));
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['document'])) delete_upload($row['document']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'scholarship_applications', 'Deleted scholarship application #' . $delId);
        set_flash('success', 'Application deleted.');
    }
    // After deleting from a detail view there is nothing to return to.
    if (strncmp($return, 'scholarship-applications?action=view', 36) === 0) $return = 'scholarship-applications';
    redirect('/admin/' . $return);
}

/* -------------------------------------------------------------- VIEW (detail) */
if ($action === 'view') {
    $row = db_row(
        "SELECT sa.*, s.title AS scholarship_title, s.id AS scholarship_ref
           FROM $table sa
           LEFT JOIN scholarships s ON s.id = sa.scholarship_id
          WHERE sa.id = :id",
        [':id' => $id]
    );
    if (!$row) {
        set_flash('error', 'Application not found.');
        redirect('/admin/scholarship-applications');
    }
    $page_title = 'Scholarship Application';
    $backView   = 'scholarship-applications?action=view&id=' . (int) $row['id'];
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div>
            <h1><?= e($row['name']) ?></h1>
            <span class="muted">Scholarship Applications / View</span>
        </div>
        <a class="btn btn-secondary" href="<?= e(admin_url('scholarship-applications')) ?>">&larr; Back to list</a>
    </div>

    <div class="grid grid-2 gap-3" style="align-items:start;">
        <div class="panel">
            <div class="panel-head"><h3>Applicant Details</h3></div>
            <div class="panel-body">
                <table class="admin-table">
                    <tbody>
                        <tr><th style="width:180px;">Status</th>
                            <td><span class="pill <?= e($PILLS[$row['status']] ?? 'pill-gray') ?>"><?= e($status_label($row['status'])) ?></span></td></tr>
                        <tr><th>Scholarship</th>
                            <td><?php if (!empty($row['scholarship_title'])): ?>
                                <a href="<?= e(admin_url('scholarships?action=edit&id=' . (int) $row['scholarship_ref'])) ?>"><?= e($row['scholarship_title']) ?></a>
                            <?php else: ?><span class="text-muted">— (not linked)</span><?php endif; ?></td></tr>
                        <tr><th>Full Name</th><td><?= e($row['name']) ?></td></tr>
                        <tr><th>Email</th><td><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></td></tr>
                        <tr><th>Phone</th>
                            <td><?= !empty($row['phone']) ? '<a href="tel:' . e($row['phone']) . '">' . e($row['phone']) . '</a>' : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Institution</th><td><?= !empty($row['institution']) ? e($row['institution']) : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Course</th><td><?= !empty($row['course']) ? e($row['course']) : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Guardian Name</th><td><?= !empty($row['guardian_name']) ? e($row['guardian_name']) : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Annual Income</th><td><?= !empty($row['annual_income']) ? e($row['annual_income']) : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Document</th>
                            <td><?php if (!empty($row['document'])): ?>
                                <a class="btn btn-outline btn-sm" href="<?= e(secure_upload_url($row['document'])) ?>" target="_blank" rel="noopener"><?= lucide('file-text') ?> View / Download</a>
                            <?php else: ?><span class="text-muted">Not provided</span><?php endif; ?></td></tr>
                        <tr><th>Submitted</th><td><?= e(format_datetime($row['created_at'])) ?> <span class="text-muted">(<?= e(time_ago($row['created_at'])) ?>)</span></td></tr>
                    </tbody>
                </table>

                <?php if (!empty($row['message'])): ?>
                    <div class="form-group" style="margin-top:1.25rem;">
                        <label class="form-label">Message</label>
                        <div class="panel" style="white-space:pre-wrap;padding:1rem 1.15rem;line-height:1.6;"><?= nl2br(e($row['message'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h3>Manage</h3></div>
            <div class="panel-body">
                <form method="post" action="<?= e(admin_url('scholarship-applications')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="status">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="_return" value="<?= e($backView) ?>">
                    <div class="form-group">
                        <label class="form-label">Update Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($STATUSES as $st): ?>
                                <option value="<?= e($st) ?>" <?= $row['status'] === $st ? 'selected' : '' ?>><?= e($status_label($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Save Status</button>
                    </div>
                </form>

                <div class="divider" style="margin:1.25rem 0;"></div>

                <form method="post" action="<?= e(admin_url('scholarship-applications')) ?>" data-confirm="Delete this application permanently?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="_return" value="scholarship-applications">
                    <button class="btn btn-danger" type="submit"><?= lucide('trash-2') ?> Delete Application</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Scholarship Applications';

$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');
if (!in_array($statusFilter, $STATUSES, true)) $statusFilter = '';

$where  = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= ' AND sa.status = :status';
    $params[':status'] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', sa.name, sa.email, sa.institution, s.title) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$sql = "SELECT sa.*, s.title AS scholarship_title
          FROM $table sa
          LEFT JOIN scholarships s ON s.id = sa.scholarship_id
         WHERE $where
         ORDER BY sa.id DESC";
$p = paginate($sql, $params, 15);

/* Per-status counts for the stat cards (unfiltered totals). */
$counts = ['all' => (int) db_value("SELECT COUNT(*) FROM $table")];
foreach ($STATUSES as $st) {
    $counts[$st] = (int) db_value("SELECT COUNT(*) FROM $table WHERE status = :s", [':s' => $st]);
}

/* Path used by row forms to return to the current filtered/paged list. */
$listReturn = 'scholarship-applications';
$listQs = http_build_query(array_filter([
    'status' => $statusFilter,
    'q'      => $search,
    'page'   => (int) $p['current'] > 1 ? (int) $p['current'] : '',
], static fn($v) => $v !== '' && $v !== null));
if ($listQs !== '') $listReturn .= '?' . $listQs;

$statCards = [
    ['key' => '',             'label' => 'Total',        'value' => $counts['all'],          'icon' => 'inbox',        'bg' => 'bg-blue'],
    ['key' => 'new',          'label' => 'New',          'value' => $counts['new'],          'icon' => 'sparkles',     'bg' => 'bg-cyan'],
    ['key' => 'under_review', 'label' => 'Under Review', 'value' => $counts['under_review'], 'icon' => 'search',       'bg' => 'bg-amber'],
    ['key' => 'approved',     'label' => 'Approved',     'value' => $counts['approved'],     'icon' => 'circle-check', 'bg' => 'bg-green'],
    ['key' => 'rejected',     'label' => 'Rejected',     'value' => $counts['rejected'],     'icon' => 'ban',          'bg' => 'bg-rose'],
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Scholarship Applications</h1><span class="muted"><?= (int) $p['total'] ?> matching · <?= (int) $counts['all'] ?> total</span></div>
</div>

<div class="stat-grid">
    <?php foreach ($statCards as $c): ?>
        <a class="stat-card" href="<?= e(admin_url('scholarship-applications' . ($c['key'] !== '' ? '?status=' . $c['key'] : ''))) ?>" style="<?= $statusFilter === $c['key'] ? 'border-color:var(--brand-600);box-shadow:var(--shadow);' : '' ?>">
            <div class="stat-icon <?= e($c['bg']) ?>"><?= lucide($c['icon']) ?></div>
            <div>
                <div class="stat-value"><?= e(number_format($c['value'])) ?></div>
                <div class="stat-label"><?= e($c['label']) ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('scholarship-applications')) ?>">
                <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, institution, scholarship…">
            </form>
            <form method="get" action="<?= e(admin_url('scholarship-applications')) ?>">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach ($STATUSES as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($status_label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($statusFilter !== '' || $search !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('scholarship-applications')) ?>">Clear</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr>
                    <th>Applicant</th><th>Scholarship</th><th>Institution</th><th>Document</th><th>Submitted</th><th style="width:160px;">Status</th><th style="text-align:right;">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['name']) ?></strong><br>
                            <small class="text-muted"><?= e($r['email']) ?><?= !empty($r['phone']) ? ' · ' . e($r['phone']) : '' ?></small>
                        </td>
                        <td><?= !empty($r['scholarship_title']) ? e($r['scholarship_title']) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <?= !empty($r['institution']) ? e($r['institution']) : '<span class="text-muted">—</span>' ?>
                            <?php if (!empty($r['course'])): ?><br><small class="text-muted"><?= e(excerpt($r['course'], 8)) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['document'])): ?>
                                <a href="<?= e(secure_upload_url($r['document'])) ?>" target="_blank" rel="noopener" title="Open document"><?= lucide('file-text') ?></a>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small></td>
                        <td>
                            <form method="post" action="<?= e(admin_url('scholarship-applications')) ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_do" value="status">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="_return" value="<?= e($listReturn) ?>">
                                <select class="form-select" name="status" onchange="this.form.submit()" style="padding:.35rem .5rem;">
                                    <?php foreach ($STATUSES as $st): ?>
                                        <option value="<?= e($st) ?>" <?= $r['status'] === $st ? 'selected' : '' ?>><?= e($status_label($st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('scholarship-applications?action=view&id=' . (int) $r['id'])) ?>" title="View"><?= lucide('eye') ?></a>
                                <form method="post" action="<?= e(admin_url('scholarship-applications')) ?>" data-confirm="Delete this application permanently?" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="_return" value="<?= e($listReturn) ?>">
                                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $p['links'] ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon"><?= lucide('graduation-cap') ?></div>
                <?php if ($statusFilter !== '' || $search !== ''): ?>
                    No scholarship applications match your filters. <a href="<?= e(admin_url('scholarship-applications')) ?>">Clear filters</a>.
                <?php else: ?>
                    No scholarship applications have been received yet.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
