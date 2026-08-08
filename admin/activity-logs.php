<?php
/**
 * =============================================================================
 *  Admin — Activity Logs (READ-ONLY log viewer / INBOX-style module).
 *  activity_logs has no `status` column, so the "status" dimension is mapped
 *  onto the log `action` type (create / update / delete / login / …):
 *    - List: user (LEFT JOIN users) · action · module · description · ip · time,
 *      newest first, paginated.
 *    - Filters: by module (?module=), by action type (?act=, shown as stat
 *      cards), and a keyword search (?q=) across user, action, module, desc, ip.
 *    - Per-action stat panel (counts), expandable row for the full description
 *      and user agent.
 *    - Maintenance: "Clear logs older than 30 days" POST button + per-row
 *      delete. NO create/edit form.
 *  Follows the standard admin pattern (CSRF, prepared statements, e(),
 *  paginate(), activity logging, flash + redirect).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table = 'activity_logs';

/* Visual meta (pill colour + stat-card icon/colour) for a given action type. */
$actionMeta = static function (string $a): array {
    $a = strtolower(trim($a));
    return match (true) {
        str_contains($a, 'create')                          => ['pill' => 'pill-green', 'icon' => 'plus', 'color' => '#58A42F'],
        str_contains($a, 'update') || str_contains($a, 'edit') => ['pill' => 'pill-blue',  'icon' => 'pencil', 'color' => '#063566'],
        str_contains($a, 'delete') || str_contains($a, 'remove') => ['pill' => 'pill-red', 'icon' => 'trash-2', 'color' => '#dc2626'],
        str_contains($a, 'login') && (str_contains($a, 'fail') || str_contains($a, 'error')) => ['pill' => 'pill-amber', 'icon' => 'triangle-alert', 'color' => '#E67B1D'],
        str_contains($a, 'login')                           => ['pill' => 'pill-blue',  'icon' => 'lock-open', 'color' => '#084881'],
        str_contains($a, 'logout')                          => ['pill' => 'pill-gray',  'icon' => 'lock', 'color' => '#64748b'],
        default                                             => ['pill' => 'pill-gray',  'icon' => 'pin', 'color' => '#6366f1'],
    };
};

/* Pretty label for an action / module slug (create_program → Create program). */
$prettyLabel = static function (string $s): string {
    return ucfirst(str_replace(['_', '-'], ' ', trim($s)));
};

/* -------------------------------------------------------------- CLEAR OLD LOGS */
if (is_post() && post('_do') === 'clear_old') {
    require_csrf();
    $cutoff  = date('Y-m-d H:i:s', strtotime('-30 days'));
    $deleted = db_count($table, 'created_at < :c', [':c' => $cutoff]);
    if ($deleted > 0) {
        db_delete($table, 'created_at < :c', [':c' => $cutoff]);
        log_activity('delete', 'activity-logs', 'Cleared ' . $deleted . ' activity log(s) older than 30 days');
        set_flash('success', 'Cleared ' . $deleted . ' log(s) older than 30 days.');
    } else {
        set_flash('info', 'No logs older than 30 days to clear.');
    }
    redirect('/admin/activity-logs');
}

/* -------------------------------------------------------------- DELETE (single row) */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'activity-logs', 'Deleted activity log #' . $delId);
        set_flash('success', 'Log entry deleted.');
    }
    redirect('/admin/activity-logs');
}

/* -------------------------------------------------------------- LIST (read-only) */
$page_title = 'Activity Logs';

/* Available modules (for the filter dropdown). */
$moduleList   = db_all("SELECT module, COUNT(*) AS c FROM $table WHERE module IS NOT NULL AND module <> '' GROUP BY module ORDER BY module ASC");
$validModules = array_column($moduleList, 'module');

/* Available action types (for the stat cards / "status" filter). */
$actionRows   = db_all("SELECT action, COUNT(*) AS c FROM $table GROUP BY action ORDER BY c DESC, action ASC");
$validActions = array_column($actionRows, 'action');

/* Sanitised filters. */
$moduleFilter = (string) get('module', '');
if ($moduleFilter !== '' && !in_array($moduleFilter, $validModules, true)) {
    $moduleFilter = '';
}
$actFilter = (string) get('act', '');
if ($actFilter !== '' && !in_array($actFilter, $validActions, true)) {
    $actFilter = '';
}
$search = trim((string) get('q', ''));

/* Build WHERE. */
$where  = '1=1';
$params = [];
if ($moduleFilter !== '') {
    $where .= ' AND al.module = :module';
    $params[':module'] = $moduleFilter;
}
if ($actFilter !== '') {
    $where .= ' AND al.action = :act';
    $params[':act'] = $actFilter;
}
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', al.description, al.action, al.module, al.ip_address, u.name, u.email) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$sql = "SELECT al.*, u.name AS user_name, u.email AS user_email
        FROM $table al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE $where
        ORDER BY al.created_at DESC, al.id DESC";

$p = paginate($sql, $params, 20);

/* Totals for the stat panel. */
$totalAll  = db_count($table);
$hasFilter = ($moduleFilter !== '' || $actFilter !== '' || $search !== '');

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Activity Logs</h1><span class="muted"><?= (int) $p['total'] ?> shown · <?= (int) $totalAll ?> total</span></div>
    <form method="post" action="<?= e(admin_url('activity-logs')) ?>" data-confirm="Permanently delete all logs older than 30 days?">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="clear_old">
        <button class="btn btn-danger" type="submit"><?= lucide('eraser') ?> Clear logs older than 30 days</button>
    </form>
</div>

<div class="stat-grid">
    <?php $allActive = ($actFilter === ''); ?>
    <a class="stat-card" href="<?= e(admin_url('activity-logs')) ?>"
       style="text-decoration:none;<?= $allActive ? 'outline:2px solid #6366f1;' : '' ?>">
        <span class="stat-icon" style="background:#6366f1;"><?= lucide('clipboard-list') ?></span>
        <span>
            <span class="stat-value"><?= (int) $totalAll ?></span>
            <span class="stat-label">All Actions</span>
        </span>
    </a>
    <?php foreach ($actionRows as $ar): ?>
        <?php
        $meta     = $actionMeta((string) $ar['action']);
        $isActive = ($actFilter === $ar['action']);
        ?>
        <a class="stat-card" href="<?= e(admin_url('activity-logs?act=' . urlencode((string) $ar['action']))) ?>"
           style="text-decoration:none;<?= $isActive ? 'outline:2px solid ' . e($meta['color']) . ';' : '' ?>">
            <span class="stat-icon" style="background:<?= e($meta['color']) ?>;"><?= lucide($meta['icon']) ?></span>
            <span>
                <span class="stat-value"><?= (int) $ar['c'] ?></span>
                <span class="stat-label"><?= e($prettyLabel((string) $ar['action'])) ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('activity-logs')) ?>" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
                <?php if ($actFilter !== ''): ?>
                    <input type="hidden" name="act" value="<?= e($actFilter) ?>">
                <?php endif; ?>
                <select class="form-select" name="module" onchange="this.form.submit()" style="max-width:220px;">
                    <option value="">All modules</option>
                    <?php foreach ($moduleList as $m): ?>
                        <option value="<?= e($m['module']) ?>" <?= $moduleFilter === $m['module'] ? 'selected' : '' ?>>
                            <?= e($prettyLabel((string) $m['module'])) ?> (<?= (int) $m['c'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search user, action, description or IP…">
                <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
            </form>
            <?php if ($hasFilter): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('activity-logs')) ?>">Clear filters</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>IP</th>
                        <th>When</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <?php
                    $meta     = $actionMeta((string) $r['action']);
                    $userName = $r['user_name'] ?? '';
                    $desc     = (string) ($r['description'] ?? '');
                    $ua       = (string) ($r['user_agent'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <?php if ($userName !== ''): ?>
                                <strong><?= e($userName) ?></strong><br>
                                <small class="text-muted"><?= e($r['user_email'] ?? '') ?></small>
                            <?php elseif (!empty($r['user_id'])): ?>
                                <span class="text-muted">User #<?= (int) $r['user_id'] ?></span>
                            <?php else: ?>
                                <span class="pill pill-gray">System</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="pill <?= e($meta['pill']) ?>"><?= lucide($meta['icon']) ?> <?= e($prettyLabel((string) $r['action'])) ?></span></td>
                        <td><?= !empty($r['module']) ? e($prettyLabel((string) $r['module'])) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <?php if ($desc !== '' || $ua !== ''): ?>
                                <details>
                                    <summary style="cursor:pointer;"><?= $desc !== '' ? e(excerpt($desc, 14)) : '<span class="text-muted">(no description)</span>' ?></summary>
                                    <div style="margin-top:.5rem;">
                                        <?php if ($desc !== ''): ?>
                                            <div style="white-space:pre-wrap;"><?= e($desc) ?></div>
                                        <?php endif; ?>
                                        <?php if ($ua !== ''): ?>
                                            <small class="text-muted" style="display:block;margin-top:.4rem;"><?= lucide('monitor') ?> <?= e($ua) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($r['ip_address']) ? '<code>' . e($r['ip_address']) . '</code>' : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <span title="<?= e(format_datetime($r['created_at'])) ?>"><?= e(format_date($r['created_at'], 'd M Y')) ?></span><br>
                            <small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small>
                        </td>
                        <td>
                            <div class="actions">
                                <form method="post" action="<?= e(admin_url('activity-logs')) ?>" data-confirm="Delete this log entry permanently?" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
            <div class="empty-state"><div class="icon"><?= lucide('clipboard-list') ?></div>
                <?php if ($hasFilter): ?>
                    No activity matches your filters. <a href="<?= e(admin_url('activity-logs')) ?>">Show all</a>.
                <?php else: ?>
                    No activity has been logged yet. Admin actions across the panel will appear here.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
