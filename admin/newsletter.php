<?php
/**
 * =============================================================================
 *  Admin — Newsletter Subscribers (INBOX / list module).
 *  list + status toggle + delete + CSV export. NO create/edit form.
 *    - Inbox list: name / email / status / subscribed date + status pill,
 *      status filter (?status=), keyword search (?q) on name/email,
 *      per-status stat counts.
 *    - Inline status toggle (subscribed ↔ unsubscribed) via a POST <select>.
 *    - CSV export (?export=csv) streams a text/csv download of the current
 *      (optionally filtered) subscriber list.
 *  Follows the standard admin pattern (CSRF, prepared statements, e(),
 *  paginate(), activity logging, flash + redirect).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table    = 'newsletter_subscribers';
$statuses = ['subscribed', 'unsubscribed'];

/* Pill colour for a given status. */
$status_pill = static function (string $s): string {
    return [
        'subscribed'   => 'pill-green',
        'unsubscribed' => 'pill-gray',
    ][$s] ?? 'pill-gray';
};

/* Build a safe internal return URL that preserves the current filters (from POST). */
$back_to = static function () use ($statuses): string {
    $qs = [];
    $s  = clean(post('r_status', ''));
    $q  = clean(post('r_q', ''));
    if (in_array($s, $statuses, true)) $qs['status'] = $s;
    if ($q !== '')                     $qs['q'] = $q;
    return '/admin/newsletter' . ($qs ? '?' . http_build_query($qs) : '');
};

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $rid = (int) post('id', 0);
    $new = (string) post('status', '');
    if (!in_array($new, $statuses, true)) {
        set_flash('error', 'Invalid status.');
        redirect($back_to());
    }
    $row = find($table, $rid);
    if ($row) {
        db_update($table, ['status' => $new], 'id = :id', [':id' => $rid]);
        log_activity('update', 'newsletter', 'Set subscriber #' . $rid . ' to ' . $new);
        set_flash('success', 'Subscriber marked as ' . ucfirst($new) . '.');
    } else {
        set_flash('error', 'Subscriber not found.');
    }
    redirect($back_to());
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'newsletter', 'Deleted subscriber #' . $delId . ' (' . ($row['email'] ?? '') . ')');
        set_flash('success', 'Subscriber deleted.');
    }
    redirect($back_to());
}

/* -------------------------------------------------------------- FILTERS (shared by list + export) */
$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');
if (!in_array($statusFilter, $statuses, true)) {
    $statusFilter = '';
}

$where  = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= ' AND status = :status';
    $params[':status'] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', name, email) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

/* -------------------------------------------------------------- CSV EXPORT */
if (get('export') === 'csv') {
    $rows = db_all("SELECT name, email, status, created_at FROM $table WHERE $where ORDER BY created_at DESC, id DESC", $params);
    log_activity('export', 'newsletter', 'Exported ' . count($rows) . ' newsletter subscribers to CSV');

    $filename = 'newsletter-subscribers-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly.
    fputcsv($out, ['Name', 'Email', 'Status', 'Subscribed On']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['name'] ?? '',
            $r['email'],
            $r['status'],
            !empty($r['created_at']) ? format_datetime($r['created_at']) : '',
        ]);
    }
    fclose($out);
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Newsletter Subscribers';

/* Live counts per status (for the stat cards / filter tabs). */
$counts = ['all' => 0];
foreach ($statuses as $st) {
    $counts[$st] = db_count($table, 'status = :s', [':s' => $st]);
    $counts['all'] += $counts[$st];
}

$p = paginate("SELECT * FROM $table WHERE $where ORDER BY created_at DESC, id DESC", $params, 20);

/* URL that reruns the export with the exact same filters currently applied. */
$exportQs = ['export' => 'csv'];
if ($statusFilter !== '') $exportQs['status'] = $statusFilter;
if ($search !== '')       $exportQs['q'] = $search;
$exportUrl = admin_url('newsletter?' . http_build_query($exportQs));

$statCards = [
    ['key' => '',             'label' => 'All Subscribers', 'icon' => 'mail',         'color' => '#063566', 'count' => $counts['all']],
    ['key' => 'subscribed',   'label' => 'Subscribed',      'icon' => 'circle-check', 'color' => '#58A42F', 'count' => $counts['subscribed']],
    ['key' => 'unsubscribed', 'label' => 'Unsubscribed',    'icon' => 'ban',          'color' => '#64748b', 'count' => $counts['unsubscribed']],
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Newsletter Subscribers</h1><span class="muted"><?= (int) $p['total'] ?> shown · <?= (int) $counts['all'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e($exportUrl) ?>"><?= lucide('download') ?> Export CSV</a>
</div>

<div class="stat-grid">
    <?php foreach ($statCards as $c): ?>
        <?php $isActive = ($statusFilter === $c['key']); ?>
        <a class="stat-card" href="<?= e(admin_url('newsletter' . ($c['key'] !== '' ? '?status=' . $c['key'] : ''))) ?>"
           style="text-decoration:none;<?= $isActive ? 'outline:2px solid ' . e($c['color']) . ';' : '' ?>">
            <span class="stat-icon" style="background:<?= e($c['color']) ?>;"><?= lucide($c['icon']) ?></span>
            <span>
                <span class="stat-value"><?= (int) $c['count'] ?></span>
                <span class="stat-label"><?= e($c['label']) ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('newsletter')) ?>">
                <?php if ($statusFilter !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <?php endif; ?>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name or email…">
            </form>
            <?php if ($statusFilter !== '' || $search !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('newsletter')) ?>">Clear filters</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Subscribed</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><?= ($r['name'] !== null && $r['name'] !== '') ? e($r['name']) : '<span class="muted">—</span>' ?></td>
                        <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td>
                        <td>
                            <form method="post" action="<?= e(admin_url('newsletter')) ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_do" value="status">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="r_status" value="<?= e($statusFilter) ?>">
                                <input type="hidden" name="r_q" value="<?= e($search) ?>">
                                <select class="form-select" name="status" onchange="this.form.submit()" style="padding:.3rem .5rem;font-weight:700;">
                                    <?php foreach ($statuses as $st): ?>
                                        <option value="<?= e($st) ?>" <?= $r['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <span title="<?= e(format_datetime($r['created_at'])) ?>"><?= e(format_date($r['created_at'], 'd M Y')) ?></span><br>
                            <small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="mailto:<?= e($r['email']) ?>" title="Email subscriber"><?= lucide('mail') ?></a>
                                <form method="post" action="<?= e(admin_url('newsletter')) ?>" data-confirm="Delete this subscriber permanently?" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="r_status" value="<?= e($statusFilter) ?>">
                                    <input type="hidden" name="r_q" value="<?= e($search) ?>">
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
            <div class="empty-state"><div class="icon"><?= lucide('mailbox') ?></div>
                <?php if ($statusFilter !== '' || $search !== ''): ?>
                    No subscribers match your filters. <a href="<?= e(admin_url('newsletter')) ?>">Show all</a>.
                <?php else: ?>
                    No newsletter subscribers yet. Sign-ups from the website newsletter form will appear here.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
