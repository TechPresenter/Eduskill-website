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

/*
 | Visual meta for a given action type: the shared pill class, a lucide glyph and a
 | TONE NAME. The tone used to be a raw hex per branch — a red, a slate grey and an
 | indigo, none of them in the palette — written straight into a style attribute on
 | both the stat card and its outline. It is now a token name resolved in CSS, so the
 | action types sit on the three status hues plus brand and nothing else.
 */
$actionMeta = static function (string $a): array {
    $a = strtolower(trim($a));
    return match (true) {
        str_contains($a, 'create')                          => ['pill' => 'pill-green', 'icon' => 'plus',           'tone' => 'ok'],
        str_contains($a, 'update') || str_contains($a, 'edit') => ['pill' => 'pill-blue',  'icon' => 'pencil',        'tone' => 'brand'],
        str_contains($a, 'delete') || str_contains($a, 'remove') => ['pill' => 'pill-red', 'icon' => 'trash-2',      'tone' => 'err'],
        str_contains($a, 'login') && (str_contains($a, 'fail') || str_contains($a, 'error')) => ['pill' => 'pill-amber', 'icon' => 'triangle-alert', 'tone' => 'warn'],
        str_contains($a, 'login')                           => ['pill' => 'pill-blue',  'icon' => 'lock-open',      'tone' => 'deep'],
        str_contains($a, 'logout')                          => ['pill' => 'pill-gray',  'icon' => 'lock',           'tone' => 'muted'],
        default                                             => ['pill' => 'pill-gray',  'icon' => 'pin',            'tone' => 'neutral'],
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
<?php /* Per-screen rules that replace what used to be style attributes on every
         stat card and table cell. TOKENS ONLY — the action-type hues resolve to the
         three status tokens plus brand, so no colour literal survives in this file.
         The tone → token map belongs in assets/css/admin.css next to .stat-card,
         which this change does not own. */ ?>
<style>
/* Action-type filter cards. The link reset and the selected-filter outline are
   NOT here: `a.stat-card` and `.stat-card.is-active` are declared once in
   assets/css/admin-pro.css, because five screens had five different mechanisms for
   "this stat card is the current filter". Only the tone map is page-local. */
body.admin .al-tone-ok      .stat-icon { background: var(--st-ok); }
body.admin .al-tone-warn    .stat-icon { background: var(--st-warn); }
body.admin .al-tone-err     .stat-icon { background: var(--st-err); }
body.admin .al-tone-brand   .stat-icon { background: var(--brand-600, #0B4E3D); }
body.admin .al-tone-deep    .stat-icon { background: var(--secondary, #174D3D); }
/* Fixed palette values, not --muted / --text-soft: those flip light in dark mode
   and .stat-icon draws a white glyph on them. #4B6754 and #372C22 stay dark in
   both themes, so the glyph keeps its contrast. */
body.admin .al-tone-muted   .stat-icon { background: #4B6754; }
body.admin .al-tone-neutral .stat-icon { background: #372C22; }

/* Log filters + expandable description cell. */
body.admin .al-filters { flex: 1; min-width: 260px; }
body.admin .al-module { max-width: 220px; }
body.admin .al-desc > summary { cursor: pointer; }
body.admin .al-desc-full { white-space: pre-wrap; }
</style>

<div class="admin-page-head">
    <div><h1><?= lucide('history') ?> Activity Logs</h1><span class="muted"><?= (int) $p['total'] ?> shown · <?= (int) $totalAll ?> total</span></div>
    <form method="post" action="<?= e(admin_url('activity-logs')) ?>" data-confirm="Permanently delete all logs older than 30 days?">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="clear_old">
        <button class="btn btn-danger" type="submit"><?= lucide('eraser') ?> Clear logs older than 30 days</button>
    </form>
</div>

<div class="stat-grid">
    <?php $allActive = ($actFilter === ''); ?>
    <a class="stat-card al-tone-brand"
       href="<?= e(admin_url('activity-logs')) ?>" <?= $allActive ? 'aria-current="page"' : '' ?>>
        <span class="stat-icon"><?= lucide('clipboard-list') ?></span>
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
        <a class="stat-card al-tone-<?= e($meta['tone']) ?>"
           href="<?= e(admin_url('activity-logs?act=' . urlencode((string) $ar['action']))) ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>>
            <span class="stat-icon"><?= lucide($meta['icon']) ?></span>
            <span>
                <span class="stat-value"><?= (int) $ar['c'] ?></span>
                <span class="stat-label"><?= e($prettyLabel((string) $ar['action'])) ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title"><?= lucide('clipboard-list') ?> Audit trail</h2>
        <?php if ($hasFilter): ?>
            <span class="pill pill-tag">Filtered · <?= (int) $p['total'] ?> of <?= (int) $totalAll ?></span>
        <?php else: ?>
            <span class="pill pill-tag"><?= (int) $totalAll ?> entries</span>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search al-filters flex flex-wrap items-center gap-1" method="get" action="<?= e(admin_url('activity-logs')) ?>">
                <?php if ($actFilter !== ''): ?>
                    <input type="hidden" name="act" value="<?= e($actFilter) ?>">
                <?php endif; ?>
                <select class="form-select al-module" name="module" onchange="this.form.submit()">
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
                        <th class="num">Actions</th>
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
                                <span class="pill pill-tag">System</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="pill <?= e($meta['pill']) ?>"><?= lucide($meta['icon']) ?> <?= e($prettyLabel((string) $r['action'])) ?></span></td>
                        <td><?= !empty($r['module']) ? e($prettyLabel((string) $r['module'])) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <?php if ($desc !== '' || $ua !== ''): ?>
                                <details class="al-desc">
                                    <summary><?= $desc !== '' ? e(excerpt($desc, 14)) : '<span class="text-muted">(no description)</span>' ?></summary>
                                    <div class="mt-1">
                                        <?php if ($desc !== ''): ?>
                                            <div class="al-desc-full"><?= e($desc) ?></div>
                                        <?php endif; ?>
                                        <?php if ($ua !== ''): ?>
                                            <small class="form-hint"><?= lucide('monitor') ?> <?= e($ua) ?></small>
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
                                <form method="post" action="<?= e(admin_url('activity-logs')) ?>" data-confirm="Delete this log entry permanently?">
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
            <?php /* Shared .empty-state shape — swap for the admin_ui.php helper once it ships. */ ?>
            <div class="empty-state">
                <div class="icon"><?= lucide('clipboard-list') ?></div>
                <?php if ($hasFilter): ?>
                    <p class="es-title">No activity matches these filters</p>
                    <p class="es-text">Nothing in the log matches the module, action type and search term you have combined. Widen one of them, or clear all three.</p>
                    <div class="es-actions">
                        <a class="btn btn-primary" href="<?= e(admin_url('activity-logs')) ?>">Show all activity</a>
                    </div>
                <?php else: ?>
                    <p class="es-title">Nothing logged yet</p>
                    <p class="es-text">Every create, update, delete and sign-in across the panel is recorded here with the user, module, IP address and time. Entries appear as soon as someone acts.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
