<?php
/**
 * =============================================================================
 *  Admin — Internship Applications  (INBOX module).
 *  list + view + status change + delete.  NO create/edit form.
 *  Follows the standard admin pattern: CSRF-guarded POST handlers, activity
 *  logging, flash + redirect, paginated list with status filter + search,
 *  per-status stat cards, and a read-only detail view.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'internships';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* Allowed workflow statuses + their pill colours / labels. */
$STATUSES = ['new', 'reviewing', 'approved', 'rejected'];
$PILLS    = ['new' => 'pill-blue', 'reviewing' => 'pill-amber', 'approved' => 'pill-green', 'rejected' => 'pill-red'];

/**
 * Sanitise a caller-supplied return path so we only ever redirect back inside
 * this module (guards against open redirects).
 */
$safe_return = static function (string $ret): string {
    return strncmp($ret, 'internships', 11) === 0 ? $ret : 'internships';
};

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $sid    = (int) post('id', 0);
    $status = (string) post('status', '');
    $return = $safe_return((string) post('_return', 'internships'));

    if (!in_array($status, $STATUSES, true)) {
        set_flash('error', 'Invalid status value.');
        redirect('/admin/' . $return);
    }
    $row = find($table, $sid);
    if ($row) {
        db_update($table, ['status' => $status], 'id = :id', [':id' => $sid]);
        log_activity('update', 'internships', 'Set internship application #' . $sid . ' to ' . $status);
        set_flash('success', 'Application status updated to "' . ucfirst($status) . '".');
    } else {
        set_flash('error', 'Application not found.');
    }
    redirect('/admin/' . $return);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId  = (int) post('id', 0);
    $return = $safe_return((string) post('_return', 'internships'));
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['resume'])) delete_upload($row['resume']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'internships', 'Deleted internship application #' . $delId);
        set_flash('success', 'Application deleted.');
    }
    // After deleting from a detail view there is nothing to return to.
    if (strncmp($return, 'internships?action=view', 23) === 0) $return = 'internships';
    redirect('/admin/' . $return);
}

/* -------------------------------------------------------------- VIEW (detail) */
if ($action === 'view') {
    $row = find($table, $id);
    if (!$row) {
        set_flash('error', 'Application not found.');
        redirect('/admin/internships');
    }
    $page_title = 'Internship Application';
    $backView   = 'internships?action=view&id=' . (int) $row['id'];
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div>
            <h1><?= e($row['name']) ?></h1>
            <span class="muted">Internship Applications / View</span>
        </div>
        <a class="btn btn-secondary" href="<?= e(admin_url('internships')) ?>">&larr; Back to list</a>
    </div>

    <div class="grid grid-2 gap-3" style="align-items:start;">
        <div class="panel">
            <div class="panel-head"><h3>Applicant Details</h3></div>
            <div class="panel-body">
                <table class="admin-table">
                    <tbody>
                        <tr><th style="width:180px;">Status</th>
                            <td><span class="pill <?= e($PILLS[$row['status']] ?? 'pill-gray') ?>"><?= e(ucfirst($row['status'])) ?></span></td></tr>
                        <tr><th>Full Name</th><td><?= e($row['name']) ?></td></tr>
                        <tr><th>Email</th><td><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></td></tr>
                        <tr><th>Phone</th>
                            <td><?= !empty($row['phone']) ? '<a href="tel:' . e($row['phone']) . '">' . e($row['phone']) . '</a>' : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Education</th><td><?= !empty($row['education']) ? e($row['education']) : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Institution</th><td><?= !empty($row['institution']) ? e($row['institution']) : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Area of Interest</th><td><?= !empty($row['area_of_interest']) ? e($row['area_of_interest']) : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Preferred Duration</th><td><?= !empty($row['duration']) ? e($row['duration']) : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Preferred Start</th><td><?= !empty($row['start_date']) ? e(format_date($row['start_date'], 'd M Y')) : '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><th>Résumé</th>
                            <td><?php if (!empty($row['resume'])): ?>
                                <a class="btn btn-outline btn-sm" href="<?= e(secure_upload_url($row['resume'])) ?>" target="_blank" rel="noopener"><?= lucide('file-text') ?> View / Download</a>
                            <?php else: ?><span class="text-muted">Not provided</span><?php endif; ?></td></tr>
                        <tr><th>Submitted</th><td><?= e(format_datetime($row['created_at'])) ?> <span class="text-muted">(<?= e(time_ago($row['created_at'])) ?>)</span></td></tr>
                    </tbody>
                </table>

                <?php if (!empty($row['cover_letter'])): ?>
                    <div class="form-group" style="margin-top:1.25rem;">
                        <label class="form-label">Cover Letter</label>
                        <div class="panel" style="white-space:pre-wrap;padding:1rem 1.15rem;line-height:1.6;"><?= nl2br(e($row['cover_letter'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h3>Manage</h3></div>
            <div class="panel-body">
                <form method="post" action="<?= e(admin_url('internships')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="status">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="_return" value="<?= e($backView) ?>">
                    <div class="form-group">
                        <label class="form-label">Update Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($STATUSES as $st): ?>
                                <option value="<?= e($st) ?>" <?= $row['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Save Status</button>
                    </div>
                </form>

                <div class="divider" style="margin:1.25rem 0;"></div>

                <form method="post" action="<?= e(admin_url('internships')) ?>" data-confirm="Delete this application permanently?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="_return" value="internships">
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
$page_title = 'Internship Applications';

$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');
if (!in_array($statusFilter, $STATUSES, true)) $statusFilter = '';

$where  = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= ' AND status = :status';
    $params[':status'] = $statusFilter;
}
if ($search !== '') {
    $where .= ' AND (name LIKE :q OR email LIKE :q2 OR area_of_interest LIKE :q3)';
    $params[':q']  = '%' . $search . '%';
    $params[':q2'] = '%' . $search . '%';
    $params[':q3'] = '%' . $search . '%';
}

$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 15);

/* Per-status counts for the stat cards (unfiltered totals). */
$counts = ['all' => (int) db_value("SELECT COUNT(*) FROM $table")];
foreach ($STATUSES as $st) {
    $counts[$st] = (int) db_value("SELECT COUNT(*) FROM $table WHERE status = :s", [':s' => $st]);
}

/* Path used by row forms to return to the current filtered/paged list. */
$listReturn = 'internships';
$listQs = http_build_query(array_filter([
    'status' => $statusFilter,
    'q'      => $search,
    'page'   => (int) $p['current'] > 1 ? (int) $p['current'] : '',
], static fn($v) => $v !== '' && $v !== null));
if ($listQs !== '') $listReturn .= '?' . $listQs;

$statCards = [
    ['key' => '',          'label' => 'Total',     'value' => $counts['all'],       'icon' => 'inbox',        'bg' => 'bg-blue'],
    ['key' => 'new',       'label' => 'New',       'value' => $counts['new'],       'icon' => 'sparkles',     'bg' => 'bg-cyan'],
    ['key' => 'reviewing', 'label' => 'Reviewing', 'value' => $counts['reviewing'], 'icon' => 'search',       'bg' => 'bg-amber'],
    ['key' => 'approved',  'label' => 'Approved',  'value' => $counts['approved'],  'icon' => 'circle-check', 'bg' => 'bg-green'],
    ['key' => 'rejected',  'label' => 'Rejected',  'value' => $counts['rejected'],  'icon' => 'ban',          'bg' => 'bg-rose'],
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Internship Applications</h1><span class="muted"><?= (int) $p['total'] ?> matching · <?= (int) $counts['all'] ?> total</span></div>
</div>

<div class="stat-grid">
    <?php foreach ($statCards as $c): ?>
        <a class="stat-card" href="<?= e(admin_url('internships' . ($c['key'] !== '' ? '?status=' . $c['key'] : ''))) ?>" style="<?= $statusFilter === $c['key'] ? 'border-color:var(--brand-600);box-shadow:var(--shadow);' : '' ?>">
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
            <form class="search" method="get" action="<?= e(admin_url('internships')) ?>">
                <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, interest…">
            </form>
            <form method="get" action="<?= e(admin_url('internships')) ?>">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach ($STATUSES as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($statusFilter !== '' || $search !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('internships')) ?>">Clear</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr>
                    <th>Applicant</th><th>Area of Interest</th><th>Résumé</th><th>Submitted</th><th style="width:150px;">Status</th><th style="text-align:right;">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['name']) ?></strong><br>
                            <small class="text-muted"><?= e($r['email']) ?><?= !empty($r['phone']) ? ' · ' . e($r['phone']) : '' ?></small>
                        </td>
                        <td>
                            <?= !empty($r['area_of_interest']) ? e($r['area_of_interest']) : '<span class="text-muted">—</span>' ?>
                            <?php if (!empty($r['education'])): ?><br><small class="text-muted"><?= e(excerpt($r['education'], 8)) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['resume'])): ?>
                                <a href="<?= e(secure_upload_url($r['resume'])) ?>" target="_blank" rel="noopener" title="Open résumé"><?= lucide('file-text') ?></a>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small></td>
                        <td>
                            <form method="post" action="<?= e(admin_url('internships')) ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_do" value="status">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="_return" value="<?= e($listReturn) ?>">
                                <select class="form-select" name="status" onchange="this.form.submit()" style="padding:.35rem .5rem;">
                                    <?php foreach ($STATUSES as $st): ?>
                                        <option value="<?= e($st) ?>" <?= $r['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('internships?action=view&id=' . (int) $r['id'])) ?>" title="View"><?= lucide('eye') ?></a>
                                <form method="post" action="<?= e(admin_url('internships')) ?>" data-confirm="Delete this application permanently?" style="display:inline;">
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
                    No internship applications match your filters. <a href="<?= e(admin_url('internships')) ?>">Clear filters</a>.
                <?php else: ?>
                    No internship applications have been received yet.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
