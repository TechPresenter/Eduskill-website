<?php
/**
 * =============================================================================
 *  Admin — Event Registrations (INBOX module).
 *  list + view + status change + delete. NO create/edit form.
 *  Joins `events` for the event title/date, filters by status (?status=),
 *  searches name/email/event title, shows live counts per status, and lets
 *  the admin move a registration through pending → confirmed → attended /
 *  cancelled. CSRF-guarded POST handlers, prepared statements, activity log.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table   = 'event_registrations';
$action  = get('action', 'list');
$id      = (int) get('id', 0);
$statuses = ['pending', 'confirmed', 'cancelled', 'attended'];

/* Pill colour for a given status. */
$status_pill = static function (string $s): string {
    return [
        'pending'   => 'pill-amber',
        'confirmed' => 'pill-green',
        'cancelled' => 'pill-red',
        'attended'  => 'pill-blue',
    ][$s] ?? 'pill-gray';
};

/* Build a safe internal return URL that preserves the current filters (from POST). */
$back_to = static function () use ($statuses): string {
    $qs = [];
    $s  = clean(post('r_status', ''));
    $q  = clean(post('r_q', ''));
    if (in_array($s, $statuses, true)) $qs['status'] = $s;
    if ($q !== '')                     $qs['q'] = $q;
    return '/admin/event-registrations' . ($qs ? '?' . http_build_query($qs) : '');
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
        log_activity('update', 'event_registrations', 'Set registration #' . $rid . ' to ' . $new);
        set_flash('success', 'Registration marked as ' . ucfirst($new) . '.');
    } else {
        set_flash('error', 'Registration not found.');
    }
    redirect($back_to());
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'event_registrations', 'Deleted registration #' . $delId);
        set_flash('success', 'Registration deleted.');
    }
    redirect($back_to());
}

/* -------------------------------------------------------------- VIEW (details) */
if ($action === 'view') {
    $row = db_row(
        "SELECT er.*, e.title AS event_title, e.slug AS event_slug,
                e.start_datetime AS event_start, e.location AS event_location,
                e.venue AS event_venue
           FROM $table er
           JOIN events e ON e.id = er.event_id
          WHERE er.id = :id",
        [':id' => $id]
    );
    if (!$row) {
        set_flash('error', 'Registration not found.');
        redirect('/admin/event-registrations');
    }
    $page_title = 'Registration Details';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Registration #<?= (int) $row['id'] ?></h1><span class="muted">Event Registrations / View</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('event-registrations')) ?>">← Back to list</a>
    </div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-body">
                <h3 style="margin-top:0;">Registrant</h3>
                <table class="admin-table">
                    <tbody>
                        <tr><th style="width:38%;">Name</th><td><?= e($row['name']) ?></td></tr>
                        <tr><th>Email</th><td><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></td></tr>
                        <tr><th>Phone</th><td><?= $row['phone'] !== null && $row['phone'] !== '' ? e($row['phone']) : '<span class="muted">—</span>' ?></td></tr>
                        <tr><th>Guests</th><td><?= (int) $row['guests'] ?></td></tr>
                        <tr><th>Registered</th><td><?= e(format_datetime($row['created_at'])) ?></td></tr>
                        <tr><th>Status</th><td><span class="pill <?= e($status_pill($row['status'])) ?>"><?= e(ucfirst($row['status'])) ?></span></td></tr>
                    </tbody>
                </table>

                <?php if ($row['message'] !== null && $row['message'] !== ''): ?>
                    <h3>Message</h3>
                    <p class="text-muted" style="white-space:pre-wrap;"><?= e($row['message']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <h3 style="margin-top:0;">Event</h3>
                <table class="admin-table">
                    <tbody>
                        <tr><th style="width:38%;">Title</th><td><?= e($row['event_title']) ?></td></tr>
                        <tr><th>Date</th><td><?= e(format_datetime($row['event_start'])) ?></td></tr>
                        <tr><th>Location</th><td><?= $row['event_location'] !== null && $row['event_location'] !== '' ? e($row['event_location']) : '<span class="muted">—</span>' ?></td></tr>
                        <tr><th>Venue</th><td><?= $row['event_venue'] !== null && $row['event_venue'] !== '' ? e($row['event_venue']) : '<span class="muted">—</span>' ?></td></tr>
                    </tbody>
                </table>
                <a class="btn btn-outline btn-sm" href="<?= e(admin_url('events?action=edit&id=' . (int) $row['event_id'])) ?>">Open event →</a>

                <h3>Update Status</h3>
                <form method="post" action="<?= e(admin_url('event-registrations')) ?>" class="admin-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="status">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <div class="form-group">
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?= e($st) ?>" <?= $row['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Save Status</button>
                </form>

                <div class="form-actions" style="margin-top:1rem;">
                    <form method="post" action="<?= e(admin_url('event-registrations')) ?>" data-confirm="Delete this registration permanently?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_do" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button class="btn btn-danger" type="submit">Delete Registration</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title   = 'Event Registrations';
$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');
if (!in_array($statusFilter, $statuses, true)) {
    $statusFilter = '';
}

/* Live counts per status (for the stat cards / filter tabs). */
$counts = ['all' => 0];
foreach ($statuses as $st) {
    $counts[$st] = db_count($table, 'status = :s', [':s' => $st]);
    $counts['all'] += $counts[$st];
}

$where  = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= ' AND er.status = :status';
    $params[':status'] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', er.name, er.email, e.title) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$sql = "SELECT er.*, e.title AS event_title, e.start_datetime AS event_start
          FROM $table er
          JOIN events e ON e.id = er.event_id
         WHERE $where
         ORDER BY er.created_at DESC";
$p = paginate($sql, $params, 15);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Event Registrations</h1><span class="muted"><?= (int) $counts['all'] ?> total</span></div>
</div>

<!-- Status filter cards -->
<div class="stat-grid">
    <a class="stat-card" href="<?= e(admin_url('event-registrations')) ?>">
        <div class="stat-icon bg-blue"><?= lucide('ticket') ?></div>
        <div>
            <div class="stat-value"><?= e(number_format($counts['all'])) ?></div>
            <div class="stat-label">All Registrations</div>
        </div>
    </a>
    <?php
    $iconMap = ['pending' => 'hourglass', 'confirmed' => 'circle-check', 'cancelled' => 'x', 'attended' => 'party-popper'];
    foreach ($statuses as $st): ?>
        <a class="stat-card" href="<?= e(admin_url('event-registrations?status=' . $st)) ?>" style="<?= $statusFilter === $st ? 'outline:2px solid var(--brand-600);' : '' ?>">
            <div class="stat-icon bg-violet"><?= lucide($iconMap[$st]) ?></div>
            <div>
                <div class="stat-value"><?= e(number_format($counts[$st])) ?></div>
                <div class="stat-label"><?= e(ucfirst($st)) ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('event-registrations')) ?>">
                <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email or event…">
            </form>
            <?php if ($statusFilter !== '' || $search !== ''): ?>
                <a class="btn btn-ghost" href="<?= e(admin_url('event-registrations')) ?>">Clear filters</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Event</th><th>Registrant</th><th>Phone</th><th>Guests</th><th>Registered</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['event_title']) ?></strong><br>
                            <small class="text-muted"><?= e(format_datetime($r['event_start'], 'd M Y, h:i A')) ?></small>
                        </td>
                        <td>
                            <strong><?= e($r['name']) ?></strong><br>
                            <small class="text-muted"><?= e($r['email']) ?></small>
                        </td>
                        <td><?= $r['phone'] !== null && $r['phone'] !== '' ? e($r['phone']) : '<span class="muted">—</span>' ?></td>
                        <td><?= (int) $r['guests'] ?></td>
                        <td><small class="text-muted"><?= e(format_date($r['created_at'], 'd M Y')) ?></small></td>
                        <td>
                            <form method="post" action="<?= e(admin_url('event-registrations')) ?>" style="display:inline;">
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
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('event-registrations?action=view&id=' . (int) $r['id'])) ?>" title="View"><?= lucide('eye') ?></a>
                                <form method="post" action="<?= e(admin_url('event-registrations')) ?>" data-confirm="Delete this registration permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('ticket') ?></div>No registrations found<?= ($statusFilter !== '' || $search !== '') ? ' for this filter' : ' yet' ?>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
