<?php
/**
 * =============================================================================
 *  Admin — Contact Messages (INBOX module).
 *  List + view + status-change + delete. NO create/edit form.
 *    - Inbox list: name / email / subject / excerpt / date + status pill,
 *      status filter (?status=), keyword search (?q), per-status stat counts.
 *    - View page (?action=view&id=): full message, auto-marks 'unread' → 'read',
 *      status-change form, reply-via-mailto link, delete.
 *  Follows the standard admin pattern (CSRF, prepared statements, e(),
 *  paginate(), activity logging, flash + redirect).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'contact_messages';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* Allowed statuses + their pill colour + display label. */
$STATUSES   = ['unread', 'read', 'replied', 'archived'];
$statusPill = [
    'unread'   => 'pill-amber',
    'read'     => 'pill-blue',
    'replied'  => 'pill-green',
    'archived' => 'pill-gray',
];

/* Where to send the browser back to after a POST action (keeps list filters
 * when the action came from the list, or returns to the view page). Only
 * internal contact-messages paths are honoured. */
$returnTo = static function (): string {
    $return = (string) post('_return', '');
    if ($return !== '' && strncmp($return, '/admin/contact-messages', 23) === 0) {
        return $return;
    }
    return '/admin/contact-messages';
};

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $rowId     = (int) post('id', 0);
    $newStatus = (string) post('status', '');
    $row       = find($table, $rowId);

    if (!$row) {
        set_flash('error', 'Message not found.');
        redirect('/admin/contact-messages');
    }
    if (!in_array($newStatus, $STATUSES, true)) {
        set_flash('error', 'Invalid status.');
        redirect($returnTo());
    }

    db_update($table, ['status' => $newStatus], 'id = :id', [':id' => $rowId]);
    log_activity('update', 'contact-messages', 'Set message #' . $rowId . ' status to ' . $newStatus);
    set_flash('success', 'Message marked as ' . $newStatus . '.');
    redirect($returnTo());
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'contact-messages', 'Deleted message #' . $delId . ' from ' . ($row['email'] ?? ''));
        set_flash('success', 'Message deleted.');
    }
    redirect('/admin/contact-messages');
}

/* -------------------------------------------------------------- VIEW (single message) */
if ($action === 'view') {
    $row = find($table, $id);
    if (!$row) {
        set_flash('error', 'Message not found.');
        redirect('/admin/contact-messages');
    }

    // Reading an unread message marks it read.
    if (($row['status'] ?? '') === 'unread') {
        db_update($table, ['status' => 'read'], 'id = :id', [':id' => $id]);
        $row['status'] = 'read';
    }

    $selfReturn = '/admin/contact-messages?action=view&id=' . (int) $row['id'];
    $mailto     = 'mailto:' . rawurlencode($row['email'])
        . '?subject=' . rawurlencode('Re: ' . ($row['subject'] ?: 'Your message to ' . SITE_NAME));

    $page_title = 'View Message';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Message from <?= e($row['name']) ?></h1><span class="muted">Contact Messages / View</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('contact-messages')) ?>">← Back to inbox</a>
    </div>

    <div class="grid-2" style="align-items:start;gap:1.25rem;">
        <div class="panel">
            <div class="panel-body">
                <div class="flex items-center justify-between" style="gap:.75rem;margin-bottom:1rem;">
                    <span class="pill <?= e($statusPill[$row['status']] ?? 'pill-gray') ?>"><?= e(ucfirst($row['status'])) ?></span>
                    <small class="text-muted"><?= e(format_datetime($row['created_at'])) ?> · <?= e(time_ago($row['created_at'])) ?></small>
                </div>

                <?php if (!empty($row['subject'])): ?>
                    <h2 style="margin:0 0 1rem;font-size:1.25rem;"><?= e($row['subject']) ?></h2>
                <?php endif; ?>

                <div class="prose" style="white-space:pre-wrap;"><?= e($row['message']) ?></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <h3 style="margin:0 0 .9rem;">Sender</h3>
                <dl class="detail-list" style="margin:0 0 1.25rem;">
                    <div style="margin-bottom:.6rem;">
                        <span class="text-muted" style="display:block;font-size:.8rem;">Name</span>
                        <strong><?= e($row['name']) ?></strong>
                    </div>
                    <div style="margin-bottom:.6rem;">
                        <span class="text-muted" style="display:block;font-size:.8rem;">Email</span>
                        <a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a>
                    </div>
                    <?php if (!empty($row['phone'])): ?>
                    <div style="margin-bottom:.6rem;">
                        <span class="text-muted" style="display:block;font-size:.8rem;">Phone</span>
                        <a href="tel:<?= e($row['phone']) ?>"><?= e($row['phone']) ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($row['ip_address'])): ?>
                    <div style="margin-bottom:.6rem;">
                        <span class="text-muted" style="display:block;font-size:.8rem;">IP Address</span>
                        <code><?= e($row['ip_address']) ?></code>
                    </div>
                    <?php endif; ?>
                </dl>

                <a class="btn btn-primary btn-block" href="<?= e($mailto) ?>"><?= lucide('mail') ?> Reply by email</a>

                <hr style="margin:1.25rem 0;border:none;border-top:1px solid var(--border);">

                <form method="post" action="<?= e(admin_url('contact-messages')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="status">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="_return" value="<?= e($selfReturn) ?>">
                    <label class="form-label">Change status</label>
                    <div class="flex" style="gap:.5rem;">
                        <select class="form-select" name="status">
                            <?php foreach ($STATUSES as $s): ?>
                                <option value="<?= e($s) ?>" <?= $row['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-secondary" type="submit">Update</button>
                    </div>
                </form>

                <hr style="margin:1.25rem 0;border:none;border-top:1px solid var(--border);">

                <form method="post" action="<?= e(admin_url('contact-messages')) ?>" data-confirm="Delete this message permanently?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-danger btn-block" type="submit"><?= lucide('trash-2') ?> Delete message</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST (inbox) */
$page_title = 'Contact Messages';

$statusFilter = (string) get('status', '');
if (!in_array($statusFilter, $STATUSES, true)) {
    $statusFilter = '';
}
$search = trim((string) get('q', ''));

$where  = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= ' AND status = :status';
    $params[':status'] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', name, email, subject, message) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$p = paginate("SELECT * FROM $table WHERE $where ORDER BY created_at DESC, id DESC", $params, 15);

/* Per-status counts for the stat panel. */
$counts = ['all' => db_count($table)];
foreach ($STATUSES as $s) {
    $counts[$s] = db_count($table, 'status = :s', [':s' => $s]);
}

$statCards = [
    ['key' => '',         'label' => 'All Messages', 'icon' => 'mailbox',   'color' => '#063566', 'count' => $counts['all']],
    ['key' => 'unread',   'label' => 'Unread',       'icon' => 'mail',      'color' => '#E67B1D', 'count' => $counts['unread']],
    ['key' => 'read',     'label' => 'Read',         'icon' => 'book-open', 'color' => '#084881', 'count' => $counts['read']],
    ['key' => 'replied',  'label' => 'Replied',      'icon' => 'reply',     'color' => '#58A42F', 'count' => $counts['replied']],
    ['key' => 'archived', 'label' => 'Archived',     'icon' => 'archive',   'color' => '#64748b', 'count' => $counts['archived']],
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Contact Messages</h1><span class="muted"><?= (int) $p['total'] ?> shown · <?= (int) $counts['all'] ?> total</span></div>
</div>

<div class="stat-grid">
    <?php foreach ($statCards as $c): ?>
        <?php $isActive = ($statusFilter === $c['key']); ?>
        <a class="stat-card" href="<?= e(admin_url('contact-messages' . ($c['key'] !== '' ? '?status=' . $c['key'] : ''))) ?>"
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
            <form class="search" method="get" action="<?= e(admin_url('contact-messages')) ?>">
                <?php if ($statusFilter !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <?php endif; ?>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, subject or message…">
            </form>
            <?php if ($statusFilter !== '' || $search !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('contact-messages')) ?>">Clear filters</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>From</th><th>Subject &amp; message</th><th>Status</th><th>Received</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <?php
                    $viewUrl = admin_url('contact-messages?action=view&id=' . $r['id']);
                    $mailto  = 'mailto:' . rawurlencode($r['email'])
                        . '?subject=' . rawurlencode('Re: ' . ($r['subject'] ?: 'Your message to ' . SITE_NAME));
                    ?>
                    <tr style="<?= $r['status'] === 'unread' ? 'font-weight:600;' : '' ?>">
                        <td>
                            <a href="<?= e($viewUrl) ?>"><strong><?= e($r['name']) ?></strong></a><br>
                            <small class="text-muted"><?= e($r['email']) ?></small>
                        </td>
                        <td>
                            <?php if (!empty($r['subject'])): ?>
                                <a href="<?= e($viewUrl) ?>" style="text-decoration:none;"><?= e($r['subject']) ?></a><br>
                            <?php endif; ?>
                            <small class="text-muted"><?= e(excerpt($r['message'], 16)) ?></small>
                        </td>
                        <td><span class="pill <?= e($statusPill[$r['status']] ?? 'pill-gray') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td>
                            <span title="<?= e(format_datetime($r['created_at'])) ?>"><?= e(format_date($r['created_at'], 'd M Y')) ?></span><br>
                            <small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e($viewUrl) ?>" title="View"><?= lucide('eye') ?></a>
                                <a class="icon-btn" href="<?= e($mailto) ?>" title="Reply by email"><?= lucide('mail') ?></a>
                                <form method="post" action="<?= e(admin_url('contact-messages')) ?>" data-confirm="Delete this message permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('inbox') ?></div>
                <?php if ($statusFilter !== '' || $search !== ''): ?>
                    No messages match your filters. <a href="<?= e(admin_url('contact-messages')) ?>">Show all</a>.
                <?php else: ?>
                    No contact messages yet. Submissions from the website contact form will appear here.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
