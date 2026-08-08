<?php
/**
 * =============================================================================
 *  Admin — Job Applications  (INBOX module).
 *  list + view + status change + delete.  NO create/edit form.
 *  Applications are submitted against a `careers` opening (LEFT JOIN careers)
 *  so the applied-for position is shown alongside the applicant.
 *  Follows the standard admin pattern: CSRF-guarded POST handlers, activity
 *  logging, flash + redirect, paginated list with status filter + search,
 *  per-status stat cards, and a read-only detail view with résumé + cover letter.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'job_applications';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* Allowed workflow statuses + their pill colours. */
$STATUSES = ['new', 'shortlisted', 'rejected', 'hired'];
$PILLS    = ['new' => 'pill-blue', 'shortlisted' => 'pill-amber', 'rejected' => 'pill-red', 'hired' => 'pill-green'];

/**
 * Sanitise a caller-supplied return path so we only ever redirect back inside
 * this module (guards against open redirects).
 */
$safe_return = static function (string $ret): string {
    return strncmp($ret, 'job-applications', 16) === 0 ? $ret : 'job-applications';
};

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $sid    = (int) post('id', 0);
    $status = (string) post('status', '');
    $return = $safe_return((string) post('_return', 'job-applications'));

    if (!in_array($status, $STATUSES, true)) {
        set_flash('error', 'Invalid status value.');
        redirect('/admin/' . $return);
    }
    $row = find($table, $sid);
    if ($row) {
        db_update($table, ['status' => $status], 'id = :id', [':id' => $sid]);
        log_activity('update', 'job_applications', 'Set job application #' . $sid . ' to ' . $status);
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
    $return = $safe_return((string) post('_return', 'job-applications'));
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['resume'])) delete_upload($row['resume']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'job_applications', 'Deleted job application #' . $delId);
        set_flash('success', 'Application deleted.');
    }
    // After deleting from a detail view there is nothing to return to.
    if (strncmp($return, 'job-applications?action=view', 28) === 0) $return = 'job-applications';
    redirect('/admin/' . $return);
}

/* -------------------------------------------------------------- VIEW (detail) */
if ($action === 'view') {
    $row = db_row(
        "SELECT ja.*, c.title AS position_title, c.slug AS career_slug, c.department AS position_department
           FROM job_applications ja
           LEFT JOIN careers c ON c.id = ja.career_id
          WHERE ja.id = :id",
        [':id' => $id]
    );
    if (!$row) {
        set_flash('error', 'Application not found.');
        redirect('/admin/job-applications');
    }
    $page_title = 'Job Application';
    $backView   = 'job-applications?action=view&id=' . (int) $row['id'];
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div>
            <h1><?= e($row['name']) ?></h1>
            <span class="muted">Job Applications / View</span>
        </div>
        <a class="btn btn-secondary" href="<?= e(admin_url('job-applications')) ?>">&larr; Back to list</a>
    </div>

    <div class="grid grid-2 gap-3" style="align-items:start;">
        <div class="panel">
            <div class="panel-head"><h3>Applicant Details</h3></div>
            <div class="panel-body">
                <table class="admin-table">
                    <tbody>
                        <tr><th style="width:180px;">Status</th>
                            <td><span class="pill <?= e($PILLS[$row['status']] ?? 'pill-gray') ?>"><?= e(ucfirst($row['status'])) ?></span></td></tr>
                        <tr><th>Position Applied For</th>
                            <td><?php if (!empty($row['position_title'])): ?>
                                <a href="<?= e(admin_url('careers?action=edit&id=' . (int) $row['career_id'])) ?>"><?= e($row['position_title']) ?></a>
                                <?php if (!empty($row['position_department'])): ?><small class="text-muted"> · <?= e($row['position_department']) ?></small><?php endif; ?>
                            <?php else: ?><span class="text-muted">General / opening removed</span><?php endif; ?></td></tr>
                        <tr><th>Full Name</th><td><?= e($row['name']) ?></td></tr>
                        <tr><th>Email</th><td><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></td></tr>
                        <tr><th>Phone</th>
                            <td><?php if (!empty($row['phone'])):
                                    // Dial the E.164 form when we know the country.
                                    $tel = !empty($row['country_dial']) ? '+' . $row['country_dial'] . $row['phone'] : $row['phone']; ?>
                                    <a href="tel:<?= e($tel) ?>"><?= e($tel) ?></a>
                                    <?php if (!empty($row['country_name'])): ?>
                                        <small class="text-muted"> · <?= e($row['country_name']) ?><?= !empty($row['country_iso']) ? ' (' . e($row['country_iso']) . ')' : '' ?></small>
                                    <?php endif; ?>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?></td></tr>
                        <?php
                        /* Everything the application form collects now has its own column
                           (schema_v27.sql). Rows are only rendered when filled, so a short
                           general application still reads cleanly. */
                        $extraRows = [
                            'Location'         => trim(implode(', ', array_filter([$row['address'] ?? '', $row['city'] ?? '', $row['state'] ?? '']))),
                            'Applying For'     => $row['position_applied'] ?? '',
                            'Qualification'    => $row['qualification'] ?? '',
                            'Experience'       => $row['experience'] ?? '',
                            'Current Company'  => $row['current_company'] ?? '',
                            'Current Salary'   => $row['current_salary'] ?? '',
                            'Expected Salary'  => $row['expected_salary'] ?? '',
                            'Notice Period'    => $row['notice_period'] ?? '',
                        ];
                        foreach ($extraRows as $label => $value):
                            if (trim((string) $value) === '') { continue; } ?>
                            <tr><th><?= e($label) ?></th><td><?= e($value) ?></td></tr>
                        <?php endforeach; ?>
                        <?php foreach (['LinkedIn' => $row['linkedin'] ?? '', 'Portfolio' => $row['portfolio'] ?? ''] as $label => $link):
                            if (trim((string) $link) === '') { continue; } ?>
                            <tr><th><?= e($label) ?></th>
                                <td><a href="<?= e($link) ?>" target="_blank" rel="noopener nofollow"><?= e($link) ?></a></td></tr>
                        <?php endforeach; ?>
                        <tr><th>Résumé</th>
                            <td><?php if (!empty($row['resume'])): ?>
                                <a class="btn btn-outline btn-sm" href="<?= e(secure_upload_url($row['resume'])) ?>" target="_blank" rel="noopener"><?= lucide('file-text') ?> View / Download</a>
                            <?php else: ?><span class="text-muted">Not provided</span><?php endif; ?></td></tr>
                        <tr><th>Submitted</th><td><?= e(format_datetime($row['created_at'])) ?> <span class="text-muted">(<?= e(time_ago($row['created_at'])) ?>)</span></td></tr>
                    </tbody>
                </table>

                <?php foreach (['Cover Letter' => $row['cover_letter'] ?? '', 'Additional Notes' => $row['message'] ?? ''] as $label => $body):
                    if (trim((string) $body) === '') { continue; } ?>
                    <div class="form-group" style="margin-top:1.25rem;">
                        <label class="form-label"><?= e($label) ?></label>
                        <div class="panel" style="white-space:pre-wrap;padding:1rem 1.15rem;line-height:1.6;"><?= nl2br(e($body)) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h3>Manage</h3></div>
            <div class="panel-body">
                <form method="post" action="<?= e(admin_url('job-applications')) ?>">
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

                <?php if (!empty($row['email'])): ?>
                    <a class="btn btn-outline btn-block" href="mailto:<?= e($row['email']) ?>?subject=<?= e(rawurlencode('Your application' . (!empty($row['position_title']) ? ' for ' . $row['position_title'] : ''))) ?>"><?= lucide('mail') ?> Email Applicant</a>
                    <div class="divider" style="margin:1.25rem 0;"></div>
                <?php endif; ?>

                <form method="post" action="<?= e(admin_url('job-applications')) ?>" data-confirm="Delete this application permanently?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="_return" value="job-applications">
                    <button class="btn btn-danger btn-block" type="submit"><?= lucide('trash-2') ?> Delete Application</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Job Applications';

$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');
if (!in_array($statusFilter, $STATUSES, true)) $statusFilter = '';

$where  = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= ' AND ja.status = :status';
    $params[':status'] = $statusFilter;
}
if ($search !== '') {
    $where .= ' AND (ja.name LIKE :q OR ja.email LIKE :q2 OR ja.phone LIKE :q3 OR c.title LIKE :q4)';
    $params[':q']  = '%' . $search . '%';
    $params[':q2'] = '%' . $search . '%';
    $params[':q3'] = '%' . $search . '%';
    $params[':q4'] = '%' . $search . '%';
}

$p = paginate(
    "SELECT ja.*, c.title AS position_title, c.department AS position_department
       FROM job_applications ja
       LEFT JOIN careers c ON c.id = ja.career_id
      WHERE $where
      ORDER BY ja.id DESC",
    $params,
    15
);

/* Per-status counts for the stat cards (unfiltered totals). */
$counts = ['all' => (int) db_value("SELECT COUNT(*) FROM $table")];
foreach ($STATUSES as $st) {
    $counts[$st] = (int) db_value("SELECT COUNT(*) FROM $table WHERE status = :s", [':s' => $st]);
}

/* Path used by row forms to return to the current filtered/paged list. */
$listReturn = 'job-applications';
$listQs = http_build_query(array_filter([
    'status' => $statusFilter,
    'q'      => $search,
    'page'   => (int) $p['current'] > 1 ? (int) $p['current'] : '',
], static fn($v) => $v !== '' && $v !== null));
if ($listQs !== '') $listReturn .= '?' . $listQs;

$statCards = [
    ['key' => '',            'label' => 'Total',       'value' => $counts['all'],         'icon' => 'inbox',        'bg' => 'bg-blue'],
    ['key' => 'new',         'label' => 'New',         'value' => $counts['new'],         'icon' => 'sparkles',     'bg' => 'bg-cyan'],
    ['key' => 'shortlisted', 'label' => 'Shortlisted', 'value' => $counts['shortlisted'], 'icon' => 'star',         'bg' => 'bg-amber'],
    ['key' => 'hired',       'label' => 'Hired',       'value' => $counts['hired'],       'icon' => 'circle-check', 'bg' => 'bg-green'],
    ['key' => 'rejected',    'label' => 'Rejected',    'value' => $counts['rejected'],    'icon' => 'ban',          'bg' => 'bg-rose'],
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Job Applications</h1><span class="muted"><?= (int) $p['total'] ?> matching · <?= (int) $counts['all'] ?> total</span></div>
</div>

<div class="stat-grid">
    <?php foreach ($statCards as $c): ?>
        <a class="stat-card" href="<?= e(admin_url('job-applications' . ($c['key'] !== '' ? '?status=' . $c['key'] : ''))) ?>" style="<?= $statusFilter === $c['key'] ? 'border-color:var(--brand-600);box-shadow:var(--shadow);' : '' ?>">
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
            <form class="search" method="get" action="<?= e(admin_url('job-applications')) ?>">
                <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone, position…">
            </form>
            <form method="get" action="<?= e(admin_url('job-applications')) ?>">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach ($STATUSES as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($statusFilter !== '' || $search !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('job-applications')) ?>">Clear</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr>
                    <th>Applicant</th><th>Position</th><th>Résumé</th><th>Submitted</th><th style="width:160px;">Status</th><th style="text-align:right;">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['name']) ?></strong><br>
                            <small class="text-muted"><?= e($r['email']) ?><?= !empty($r['phone']) ? ' · ' . e($r['phone']) : '' ?></small>
                        </td>
                        <td>
                            <?php if (!empty($r['position_title'])): ?>
                                <?= e($r['position_title']) ?>
                                <?php if (!empty($r['position_department'])): ?><br><small class="text-muted"><?= e($r['position_department']) ?></small><?php endif; ?>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['resume'])): ?>
                                <a href="<?= e(secure_upload_url($r['resume'])) ?>" target="_blank" rel="noopener" title="Open résumé"><?= lucide('file-text') ?></a>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small></td>
                        <td>
                            <form method="post" action="<?= e(admin_url('job-applications')) ?>" style="display:inline;">
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
                                <a class="icon-btn" href="<?= e(admin_url('job-applications?action=view&id=' . (int) $r['id'])) ?>" title="View"><?= lucide('eye') ?></a>
                                <form method="post" action="<?= e(admin_url('job-applications')) ?>" data-confirm="Delete this application permanently?" style="display:inline;">
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
                <div class="icon"><?= lucide('briefcase') ?></div>
                <?php if ($statusFilter !== '' || $search !== ''): ?>
                    No job applications match your filters. <a href="<?= e(admin_url('job-applications')) ?>">Clear filters</a>.
                <?php else: ?>
                    No job applications have been received yet.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
