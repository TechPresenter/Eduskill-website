<?php
/**
 * =============================================================================
 *  Admin — Volunteer Applications (INBOX module).
 *  list + view + status-change + delete (NO create/edit form).
 *  Follows the standard admin pattern: CSRF-guarded POST handlers, prepared
 *  statements, pagination with search + status filter, per-status stat cards,
 *  résumé download link, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'volunteers';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* Allowed statuses + display + pill colour ------------------------------------ */
$statuses = [
    'new'       => 'New',
    'reviewing' => 'Reviewing',
    'approved'  => 'Approved',
    'rejected'  => 'Rejected',
];
$statusPill = static fn (string $s): string => match ($s) {
    'approved'  => 'pill-green',
    'reviewing' => 'pill-amber',
    'rejected'  => 'pill-red',
    'new'       => 'pill-blue',
    default     => 'pill-gray',
};
$genderLabels = [
    'male'       => 'Male',
    'female'     => 'Female',
    'other'      => 'Other',
    'prefer_not' => 'Prefer not to say',
];

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $vid    = (int) post('id', 0);
    $status = (string) post('status', '');
    $row    = find($table, $vid);

    if (!$row) {
        set_flash('error', 'Volunteer application not found.');
        redirect('/admin/volunteers');
    }
    if (!isset($statuses[$status])) {
        set_flash('error', 'Invalid status.');
        redirect('/admin/volunteers');
    }

    db_update($table, ['status' => $status], 'id = :id', [':id' => $vid]);
    log_activity('update', 'volunteers', 'Set volunteer #' . $vid . ' status to ' . $status);
    set_flash('success', 'Status updated to “' . $statuses[$status] . '”.');

    if (post('from') === 'view') {
        redirect('/admin/volunteers?action=view&id=' . $vid);
    }
    $qs = http_build_query(array_filter([
        'status' => isset($statuses[(string) post('f_status')]) ? (string) post('f_status') : '',
        'q'      => clean(post('f_q', '')),
        'page'   => (int) post('f_page', 0) ?: '',
    ]));
    redirect('/admin/volunteers' . ($qs !== '' ? '?' . $qs : ''));
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        if (!empty($row['resume'])) delete_upload($row['resume']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'volunteers', 'Deleted volunteer #' . $delId);
        set_flash('success', 'Volunteer application deleted.');
    }
    if (post('from') === 'view') {
        redirect('/admin/volunteers');
    }
    $qs = http_build_query(array_filter([
        'status' => isset($statuses[(string) post('f_status')]) ? (string) post('f_status') : '',
        'q'      => clean(post('f_q', '')),
        'page'   => (int) post('f_page', 0) ?: '',
    ]));
    redirect('/admin/volunteers' . ($qs !== '' ? '?' . $qs : ''));
}

/* -------------------------------------------------------------- VIEW (details) */
if ($action === 'view') {
    $row = find($table, $id);
    if (!$row) {
        set_flash('error', 'Volunteer application not found.');
        redirect('/admin/volunteers');
    }
    $page_title = 'Volunteer Application';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div>
            <h1><?= e($row['name']) ?></h1>
            <span class="muted">Volunteer Applications / Details</span>
        </div>
        <a class="btn btn-secondary" href="<?= e(admin_url('volunteers')) ?>">← Back to list</a>
    </div>

    <div class="grid-2" style="align-items:start;">
        <!-- Details -->
        <div class="panel">
            <div class="panel-body">
                <div class="table-wrap">
                    <table class="admin-table">
                        <tbody>
                            <tr><th style="width:190px;">Status</th>
                                <td><span class="pill <?= e($statusPill($row['status'])) ?>"><?= e($statuses[$row['status']] ?? ucfirst($row['status'])) ?></span></td></tr>
                            <tr><th>Full Name</th><td><?= e($row['name']) ?></td></tr>
                            <tr><th>Email</th><td><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></td></tr>
                            <tr><th>Phone</th><td><?= $row['phone'] !== null && $row['phone'] !== '' ? '<a href="tel:' . e($row['phone']) . '">' . e($row['phone']) . '</a>' : '—' ?></td></tr>
                            <tr><th>Occupation</th><td><?= e($row['occupation'] ?: '—') ?></td></tr>
                            <tr><th>Gender</th><td><?= e($row['gender'] ? ($genderLabels[$row['gender']] ?? ucfirst($row['gender'])) : '—') ?></td></tr>
                            <tr><th>Date of Birth</th><td><?= e(!empty($row['date_of_birth']) ? format_date($row['date_of_birth']) : '—') ?></td></tr>
                            <tr><th>Address</th><td><?= e($row['address'] ?: '—') ?></td></tr>
                            <tr><th>City</th><td><?= e($row['city'] ?: '—') ?></td></tr>
                            <tr><th>Area of Interest</th><td><?= e($row['area_of_interest'] ?: '—') ?></td></tr>
                            <tr><th>Availability</th><td><?= e($row['availability'] ?: '—') ?></td></tr>
                            <tr><th>Message</th><td><?= nl2br(e($row['message'] ?: '—')) ?></td></tr>
                            <tr><th>Résumé</th><td>
                                <?php if (!empty($row['resume'])): ?>
                                    <a class="btn btn-outline btn-sm" href="<?= e(secure_upload_url($row['resume'])) ?>" target="_blank" rel="noopener"><?= lucide('file-text') ?> Download résumé</a>
                                <?php else: ?>—<?php endif; ?>
                            </td></tr>
                            <tr><th>Applied</th><td><?= e(format_datetime($row['created_at'])) ?> <span class="text-muted">(<?= e(time_ago($row['created_at'])) ?>)</span></td></tr>
                            <tr><th>Last Updated</th><td><?= e(format_datetime($row['updated_at'])) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="panel">
            <div class="panel-body">
                <h3 class="mb-2">Update Status</h3>
                <form method="post" action="<?= e(admin_url('volunteers')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="status">
                    <input type="hidden" name="from" value="view">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <div class="form-group">
                        <label class="form-label">Application Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $row['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Save Status</button>
                </form>

                <hr style="margin:1.4rem 0;border:none;border-top:1px solid var(--border);">

                <h3 class="mb-2">Danger Zone</h3>
                <form method="post" action="<?= e(admin_url('volunteers')) ?>" data-confirm="Delete this volunteer application permanently?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="delete">
                    <input type="hidden" name="from" value="view">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
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
$page_title   = 'Volunteer Applications';
$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', name, email, area_of_interest, city) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
if (isset($statuses[$statusFilter])) {
    $where .= ' AND status = :status';
    $params[':status'] = $statusFilter;
}

$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 15);

/* Per-status counts for the stat cards. */
$counts = ['all' => db_count($table)];
foreach ($statuses as $key => $label) {
    $counts[$key] = db_count($table, 'status = :s', [':s' => $key]);
}
$statBg = ['new' => 'bg-blue', 'reviewing' => 'bg-amber', 'approved' => 'bg-green', 'rejected' => 'bg-rose'];
$statIcon = ['new' => 'sparkles', 'reviewing' => 'search', 'approved' => 'circle-check', 'rejected' => 'ban'];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Volunteer Applications</h1><span class="muted"><?= (int) $counts['all'] ?> total</span></div>
</div>

<!-- Per-status stat cards (click to filter) -->
<div class="stat-grid">
    <a class="stat-card" href="<?= e(admin_url('volunteers')) ?>">
        <div class="stat-icon bg-violet"><?= lucide('users') ?></div>
        <div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">All Applications</div></div>
    </a>
    <?php foreach ($statuses as $key => $label): ?>
        <a class="stat-card" href="<?= e(admin_url('volunteers?status=' . $key)) ?>">
            <div class="stat-icon <?= e($statBg[$key]) ?>"><?= lucide($statIcon[$key]) ?></div>
            <div><div class="stat-value"><?= (int) $counts[$key] ?></div><div class="stat-label"><?= e($label) ?></div></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('volunteers')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, interest…">
            </form>
            <form method="get" action="<?= e(admin_url('volunteers')) ?>">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr>
                    <th>Applicant</th><th>Area of Interest</th><th>Availability</th><th>Résumé</th><th>Applied</th><th>Status</th><th style="text-align:right;">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['name']) ?></strong><br>
                            <small class="text-muted"><?= e($r['email']) ?><?= !empty($r['phone']) ? ' · ' . e($r['phone']) : '' ?></small>
                        </td>
                        <td><?= e($r['area_of_interest'] ?: '—') ?></td>
                        <td><?= e($r['availability'] ?: '—') ?></td>
                        <td>
                            <?php if (!empty($r['resume'])): ?>
                                <a class="icon-btn" href="<?= e(secure_upload_url($r['resume'])) ?>" target="_blank" rel="noopener" title="Download résumé"><?= lucide('file-text') ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= e(format_date($r['created_at'])) ?></td>
                        <td>
                            <form method="post" action="<?= e(admin_url('volunteers')) ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_do" value="status">
                                <input type="hidden" name="from" value="list">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>">
                                <input type="hidden" name="f_q" value="<?= e($search) ?>">
                                <input type="hidden" name="f_page" value="<?= (int) $p['current'] ?>">
                                <select class="form-select" name="status" onchange="this.form.submit()" style="min-width:130px;padding:.3rem .5rem;">
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $r['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('volunteers?action=view&id=' . $r['id'])) ?>" title="View details"><?= lucide('eye') ?></a>
                                <form method="post" action="<?= e(admin_url('volunteers')) ?>" data-confirm="Delete this volunteer application permanently?" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="from" value="list">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>">
                                    <input type="hidden" name="f_q" value="<?= e($search) ?>">
                                    <input type="hidden" name="f_page" value="<?= (int) $p['current'] ?>">
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
            <div class="empty-state"><div class="icon"><?= lucide('hand-heart') ?></div>
                <?= ($search !== '' || $statusFilter !== '') ? 'No volunteer applications match your filters.' : 'No volunteer applications yet.' ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
