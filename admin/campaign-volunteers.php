<?php
/**
 * =============================================================================
 *  Admin — Campaign Volunteers (per-campaign volunteer assignments)
 * =============================================================================
 *  assign · edit · delete. Always scoped to a single campaign via
 *  ?campaign=<id> (required — flash + redirect to the campaigns list when it is
 *  missing or invalid). A volunteer row may optionally link to an existing
 *  volunteers-table application (volunteer_id); a small progressive script
 *  prefills the blank name / email / phone fields from that link. Standard admin
 *  pattern: CSRF-guarded POST handlers, prepared statements, pagination +
 *  search + filter, stat cards, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'campaign_volunteers';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$statuses = [
    'assigned'  => 'Assigned',
    'active'    => 'Active',
    'completed' => 'Completed',
    'withdrawn' => 'Withdrawn',
];
$statusPill = [
    'assigned'  => 'pill-amber',
    'active'    => 'pill-green',
    'completed' => 'pill-blue',
    'withdrawn' => 'pill-gray',
];

/* -------------------------------------------------------------- CAMPAIGN CONTEXT (required) */
$campaignId = (int) (is_post() ? post('campaign', 0) : get('campaign', 0));
$campaign   = $campaignId ? find('campaigns', $campaignId) : null;
if (!$campaign) {
    set_flash('error', 'Select a campaign to manage its volunteers.');
    redirect(admin_url('campaigns'));
}

$q       = 'campaign=' . $campaignId;
$ctxQS   = '&campaign=' . $campaignId;
$listUrl = admin_url('campaign-volunteers?' . $q);

/* -------------------------------------------------------------- SAVE (assign / edit) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && (!$old || (int) $old['campaign_id'] !== $campaignId)) {
        set_flash('error', 'Volunteer assignment not found.');
        redirect($listUrl);
    }

    if (clean(post('name')) === '') {
        flash_old($_POST);
        set_flash('error', 'Volunteer name is required.');
        redirect(admin_url('campaign-volunteers?action=' . ($editId ? 'edit&id=' . $editId : 'create') . $ctxQS));
    }

    // Optional link to a volunteers-table application — keep only a valid id.
    $volunteerId = (int) post('volunteer_id', 0) ?: null;
    if ($volunteerId !== null && !find('volunteers', $volunteerId)) {
        $volunteerId = null;
    }

    $data = [
        'campaign_id'  => $campaignId,
        'volunteer_id' => $volunteerId,
        'name'         => clean(post('name')),
        'email'        => clean(post('email')) ?: null,
        'phone'        => clean(post('phone')) ?: null,
        'task'         => clean(post('task')) ?: null,
        'status'       => isset($statuses[(string) post('status')]) ? post('status') : 'assigned',
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'campaigns', 'Updated volunteer #' . $editId . ' on campaign #' . $campaignId);
        set_flash('success', 'Volunteer assignment updated.');
        redirect($listUrl);
    }

    $newId = db_insert($table, $data);
    log_activity('create', 'campaigns', 'Assigned volunteer #' . $newId . ' to campaign #' . $campaignId);
    set_flash('success', 'Volunteer assigned.');
    redirect($listUrl);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row && (int) $row['campaign_id'] === $campaignId) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'campaigns', 'Removed volunteer #' . $delId . ' (' . ($row['name'] ?? '') . ') from campaign #' . $campaignId);
        set_flash('success', 'Volunteer removed from campaign.');
    }
    redirect($listUrl);
}

/* -------------------------------------------------------------- FORM (assign / edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && (!$row || (int) $row['campaign_id'] !== $campaignId)) {
        set_flash('error', 'Volunteer assignment not found.');
        redirect($listUrl);
    }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));

    // Existing volunteer applications that can be linked (task-supplied query).
    $volunteers = db_all("SELECT id,name,email,phone FROM volunteers WHERE status IN ('new','active') ORDER BY name");

    $page_title = $action === 'edit' ? 'Edit Volunteer' : 'Assign Volunteer';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted"><?= e($campaign['title']) ?> · Volunteers / <?= $action === 'edit' ? 'Edit' : 'Assign' ?></span></div>
        <a class="btn btn-secondary" href="<?= e($listUrl) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('campaign-volunteers')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
        <input type="hidden" name="campaign" value="<?= (int) $campaignId ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Volunteer</h3>
                <div class="form-group"><label class="form-label">Link existing volunteer</label>
                    <select class="form-select" name="volunteer_id" id="volLink">
                        <option value="">— No link (enter manually) —</option>
                        <?php foreach ($volunteers as $vo): ?>
                            <option value="<?= (int) $vo['id'] ?>"
                                data-name="<?= e($vo['name']) ?>" data-email="<?= e($vo['email']) ?>" data-phone="<?= e($vo['phone']) ?>"
                                <?= (int) ($row['volunteer_id'] ?? 0) === (int) $vo['id'] ? 'selected' : '' ?>>
                                <?= e($vo['name']) ?><?= $vo['email'] ? ' · ' . e($vo['email']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Picking a volunteer fills any blank name / email / phone below.</small>
                </div>
                <div class="form-group"><label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" required value="<?= $o('name') ?>"></div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" value="<?= $o('email') ?>"></div>
                    <div class="form-group"><label class="form-label">Phone</label>
                        <input class="form-control" name="phone" value="<?= $o('phone') ?>"></div>
                </div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Assignment</h3>
                <div class="form-group"><label class="form-label">Task / role</label>
                    <input class="form-control" name="task" value="<?= $o('task') ?>" placeholder="e.g. Field coordinator, Social media…"></div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['status'] ?? 'assigned') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Assign' ?> volunteer</button>
                    <a class="btn btn-ghost" href="<?= e($listUrl) ?>">Cancel</a>
                </div>
            </div></div>
        </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var link = document.getElementById('volLink');
        if (!link) return;
        link.addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) return;
            var map = {
                name:  opt.getAttribute('data-name')  || '',
                email: opt.getAttribute('data-email') || '',
                phone: opt.getAttribute('data-phone') || ''
            };
            Object.keys(map).forEach(function (k) {
                var input = document.querySelector('[name="' + k + '"]');
                if (input && input.value.trim() === '' && map[k]) { input.value = map[k]; }
            });
        });
    });
    </script>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title   = 'Campaign Volunteers';
$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');

$where  = 'v.campaign_id = :cid';
$params = [':cid' => $campaignId];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', v.name, v.email, v.phone, v.task) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
if (isset($statuses[$statusFilter])) {
    $where .= ' AND v.status = :st';
    $params[':st'] = $statusFilter;
}

$p = paginate(
    "SELECT v.* FROM campaign_volunteers v WHERE $where ORDER BY v.id DESC",
    $params, 15
);

$cp = [':c' => $campaignId];
$counts = [
    'total'     => db_count('campaign_volunteers', 'campaign_id = :c', $cp),
    'assigned'  => db_count('campaign_volunteers', "campaign_id = :c AND status = 'assigned'", $cp),
    'active'    => db_count('campaign_volunteers', "campaign_id = :c AND status = 'active'", $cp),
    'completed' => db_count('campaign_volunteers', "campaign_id = :c AND status = 'completed'", $cp),
];

$addUrl = admin_url('campaign-volunteers?action=create' . $ctxQS);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Campaign Volunteers</h1>
        <span class="muted"><?= e($campaign['title']) ?> · <?= (int) $p['total'] ?> volunteers</span>
    </div>
    <div class="flex" style="gap:.5rem;">
        <a class="btn btn-secondary" href="<?= e(admin_url('campaigns')) ?>">← All campaigns</a>
        <a class="btn btn-primary" href="<?= e($addUrl) ?>">+ Assign Volunteer</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $counts['total'] ?></div><div class="stat-label">Volunteers</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('user-plus') ?></div><div><div class="stat-value"><?= (int) $counts['assigned'] ?></div><div class="stat-label">Assigned</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('user-check') ?></div><div><div class="stat-value"><?= (int) $counts['active'] ?></div><div class="stat-label">Active</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['completed'] ?></div><div class="stat-label">Completed</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('campaign-volunteers')) ?>">
            <input type="hidden" name="campaign" value="<?= (int) $campaignId ?>">
            <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone, task…">
        </form>
        <form method="get" action="<?= e(admin_url('campaign-volunteers')) ?>" class="flex" style="gap:.5rem;">
            <input type="hidden" name="campaign" value="<?= (int) $campaignId ?>">
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($search !== '' || $statusFilter !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= e($listUrl) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Name</th><th>Task</th><th>Contact</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): $editUrl = admin_url('campaign-volunteers?action=edit&id=' . $r['id'] . $ctxQS); ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong><?= $r['volunteer_id'] ? ' <small class="text-muted">· linked</small>' : '' ?></td>
                <td><?= $r['task'] ? e($r['task']) : '<span class="text-muted">—</span>' ?></td>
                <td>
                    <?php if ($r['email'] || $r['phone']): ?>
                        <?= $r['email'] ? '<a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>' : '' ?>
                        <?= ($r['email'] && $r['phone']) ? '<br>' : '' ?>
                        <?= $r['phone'] ? '<a href="tel:' . e($r['phone']) . '">' . e($r['phone']) . '</a>' : '' ?>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td><span class="pill <?= e($statusPill[$r['status']] ?? 'pill-gray') ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e($editUrl) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('campaign-volunteers')) ?>" data-confirm="Remove this volunteer from the campaign?" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="campaign" value="<?= (int) $campaignId ?>">
                        <button class="icon-btn danger" type="submit" title="Remove"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('users') ?></div>
            <?= ($search !== '' || $statusFilter !== '') ? 'No volunteers match your filters. ' : 'No volunteers assigned to this campaign yet. ' ?>
            <a href="<?= e($addUrl) ?>">Assign one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
