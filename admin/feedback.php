<?php
/**
 * =============================================================================
 *  Admin — Feedback (INBOX module).
 *  list + view + status-change + delete (NO create/edit form).
 *  Follows the standard admin pattern: CSRF-guarded POST handlers, prepared
 *  statements, pagination with search + status filter, per-status stat cards,
 *  star ratings, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'feedback';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* Allowed statuses + display + pill colour ------------------------------------ */
$statuses = [
    'new'      => 'New',
    'reviewed' => 'Reviewed',
    'archived' => 'Archived',
];
$statusPill = static fn (string $s): string => match ($s) {
    'reviewed' => 'pill-green',
    'archived' => 'pill-gray',
    'new'      => 'pill-blue',
    default    => 'pill-gray',
};

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $fid    = (int) post('id', 0);
    $status = (string) post('status', '');
    $row    = find($table, $fid);

    if (!$row) {
        set_flash('error', 'Feedback entry not found.');
        redirect('/admin/feedback');
    }
    if (!isset($statuses[$status])) {
        set_flash('error', 'Invalid status.');
        redirect('/admin/feedback');
    }

    db_update($table, ['status' => $status], 'id = :id', [':id' => $fid]);
    log_activity('update', 'feedback', 'Set feedback #' . $fid . ' status to ' . $status);
    set_flash('success', 'Status updated to “' . $statuses[$status] . '”.');

    if (post('from') === 'view') {
        redirect('/admin/feedback?action=view&id=' . $fid);
    }
    $qs = http_build_query(array_filter([
        'status' => isset($statuses[(string) post('f_status')]) ? (string) post('f_status') : '',
        'q'      => clean(post('f_q', '')),
        'page'   => (int) post('f_page', 0) ?: '',
    ]));
    redirect('/admin/feedback' . ($qs !== '' ? '?' . $qs : ''));
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'feedback', 'Deleted feedback #' . $delId);
        set_flash('success', 'Feedback deleted.');
    }
    if (post('from') === 'view') {
        redirect('/admin/feedback');
    }
    $qs = http_build_query(array_filter([
        'status' => isset($statuses[(string) post('f_status')]) ? (string) post('f_status') : '',
        'q'      => clean(post('f_q', '')),
        'page'   => (int) post('f_page', 0) ?: '',
    ]));
    redirect('/admin/feedback' . ($qs !== '' ? '?' . $qs : ''));
}

/* -------------------------------------------------------------- VIEW (details) */
if ($action === 'view') {
    $row = find($table, $id);
    if (!$row) {
        set_flash('error', 'Feedback entry not found.');
        redirect('/admin/feedback');
    }

    // Mark unread feedback as reviewed the first time it is opened.
    if ($row['status'] === 'new') {
        db_update($table, ['status' => 'reviewed'], 'id = :id', [':id' => (int) $row['id']]);
        log_activity('update', 'feedback', 'Auto-marked feedback #' . $row['id'] . ' as reviewed on view');
        $row['status'] = 'reviewed';
    }

    $page_title = 'Feedback';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div>
            <h1><?= e($row['subject'] ?: 'Feedback from ' . $row['name']) ?></h1>
            <span class="muted">Feedback / Details</span>
        </div>
        <a class="btn btn-secondary" href="<?= e(admin_url('feedback')) ?>">← Back to list</a>
    </div>

    <div class="grid-2" style="align-items:start;">
        <!-- Details -->
        <div class="panel">
            <div class="panel-body">
                <div class="table-wrap">
                    <table class="admin-table">
                        <tbody>
                            <tr><th style="width:170px;">Status</th>
                                <td><span class="pill <?= e($statusPill($row['status'])) ?>"><?= e($statuses[$row['status']] ?? ucfirst($row['status'])) ?></span></td></tr>
                            <tr><th>Name</th><td><?= e($row['name']) ?></td></tr>
                            <tr><th>Email</th><td><?= !empty($row['email']) ? '<a href="mailto:' . e($row['email']) . '">' . e($row['email']) . '</a>' : '—' ?></td></tr>
                            <tr><th>Subject</th><td><?= e($row['subject'] ?: '—') ?></td></tr>
                            <tr><th>Rating</th><td><?= !empty($row['rating']) ? star_rating((int) $row['rating']) . ' <span class="text-muted">(' . (int) $row['rating'] . '/5)</span>' : '—' ?></td></tr>
                            <tr><th>Message</th><td><?= nl2br(e($row['message'])) ?></td></tr>
                            <tr><th>IP Address</th><td><?= e($row['ip_address'] ?: '—') ?></td></tr>
                            <tr><th>Received</th><td><?= e(format_datetime($row['created_at'])) ?> <span class="text-muted">(<?= e(time_ago($row['created_at'])) ?>)</span></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="panel">
            <div class="panel-body">
                <h3 class="mb-2">Update Status</h3>
                <form method="post" action="<?= e(admin_url('feedback')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="status">
                    <input type="hidden" name="from" value="view">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <div class="form-group">
                        <label class="form-label">Feedback Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $row['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Save Status</button>
                </form>

                <?php if (!empty($row['email'])): ?>
                    <hr style="margin:1.4rem 0;border:none;border-top:1px solid var(--border);">
                    <h3 class="mb-2">Respond</h3>
                    <a class="btn btn-outline" href="mailto:<?= e($row['email']) ?><?= !empty($row['subject']) ? '?subject=' . rawurlencode('Re: ' . $row['subject']) : '' ?>"><?= lucide('mail') ?> Reply by Email</a>
                <?php endif; ?>

                <hr style="margin:1.4rem 0;border:none;border-top:1px solid var(--border);">

                <h3 class="mb-2">Danger Zone</h3>
                <form method="post" action="<?= e(admin_url('feedback')) ?>" data-confirm="Delete this feedback permanently?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="delete">
                    <input type="hidden" name="from" value="view">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-danger" type="submit"><?= lucide('trash-2') ?> Delete Feedback</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title   = 'Feedback';
$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', name, email, subject, message) LIKE :q";
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
$statBg   = ['new' => 'bg-blue', 'reviewed' => 'bg-green', 'archived' => 'bg-violet'];
$statIcon = ['new' => 'sparkles', 'reviewed' => 'circle-check', 'archived' => 'archive'];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Feedback</h1><span class="muted"><?= (int) $counts['all'] ?> total</span></div>
</div>

<!-- Per-status stat cards (click to filter) -->
<div class="stat-grid">
    <a class="stat-card" href="<?= e(admin_url('feedback')) ?>">
        <div class="stat-icon bg-cyan"><?= lucide('message-square') ?></div>
        <div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">All Feedback</div></div>
    </a>
    <?php foreach ($statuses as $key => $label): ?>
        <a class="stat-card" href="<?= e(admin_url('feedback?status=' . $key)) ?>">
            <div class="stat-icon <?= e($statBg[$key]) ?>"><?= lucide($statIcon[$key]) ?></div>
            <div><div class="stat-value"><?= (int) $counts[$key] ?></div><div class="stat-label"><?= e($label) ?></div></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('feedback')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, subject, message…">
            </form>
            <form method="get" action="<?= e(admin_url('feedback')) ?>">
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
                    <th>From</th><th>Subject</th><th>Rating</th><th>Message</th><th>Received</th><th>Status</th><th style="text-align:right;">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['name']) ?></strong>
                            <?php if (!empty($r['email'])): ?><br><small class="text-muted"><?= e($r['email']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($r['subject'] ?: '—') ?></td>
                        <td><?= !empty($r['rating']) ? star_rating((int) $r['rating']) : '<span class="text-muted">—</span>' ?></td>
                        <td><small class="text-muted"><?= e(excerpt($r['message'] ?? '', 14)) ?></small></td>
                        <td><?= e(format_date($r['created_at'])) ?></td>
                        <td>
                            <form method="post" action="<?= e(admin_url('feedback')) ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_do" value="status">
                                <input type="hidden" name="from" value="list">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>">
                                <input type="hidden" name="f_q" value="<?= e($search) ?>">
                                <input type="hidden" name="f_page" value="<?= (int) $p['current'] ?>">
                                <select class="form-select" name="status" onchange="this.form.submit()" style="min-width:125px;padding:.3rem .5rem;">
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $r['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('feedback?action=view&id=' . $r['id'])) ?>" title="View details"><?= lucide('eye') ?></a>
                                <form method="post" action="<?= e(admin_url('feedback')) ?>" data-confirm="Delete this feedback permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('message-square') ?></div>
                <?= ($search !== '' || $statusFilter !== '') ? 'No feedback matches your filters.' : 'No feedback yet.' ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
