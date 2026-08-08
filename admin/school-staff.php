<?php
/**
 * =============================================================================
 *  Admin — School Staff (assign NGO staff / teachers to schools)
 * =============================================================================
 *  list · create · edit · delete. Each row assigns a person (optionally linked
 *  to a team_members record) to a partner school with a role, subject and
 *  status. Honours a ?school context: forms/filters default to it and it is
 *  preserved through "+ Add" links and after-save redirects. Standard admin
 *  pattern: CSRF, prepared statements, pagination, activity logging, flash.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'school_staff';
$action = get('action', 'list');
$id     = (int) get('id', 0);
$ctx    = (int) request('school', 0);           // school context (GET filter or POST hidden)

$roles      = school_role_labels();             // coordinator / teacher / volunteer / administrator / counselor
$statuses   = ['active' => 'Active', 'inactive' => 'Inactive'];
$schoolOpts = school_options();                 // [id => name]
$validCtx   = ($ctx && isset($schoolOpts[$ctx])) ? $ctx : 0;

/** Suffix to keep the ?school context in links / redirects. */
$ctxLink = $validCtx ? '&school=' . $validCtx : '';
$ctxRedir = $validCtx ? '?school=' . $validCtx : '';

/** Status pill class (no shared helper for staff). */
$staffStatusPill = static fn(string $s): string => $s === 'active' ? 'pill-green' : 'pill-gray';

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'Staff record not found.');
        redirect('/admin/school-staff' . $ctxRedir);
    }

    $schoolId = (int) post('school_id', 0);
    $name     = clean(post('name'));

    if ($name === '' || !isset($schoolOpts[$schoolId])) {
        flash_old($_POST);
        set_flash('error', 'A valid school and a person name are required.');
        redirect('/admin/school-staff?action=' . ($editId ? 'edit&id=' . $editId : 'create') . $ctxLink);
    }

    $teamId = (int) post('team_id', 0);

    $data = [
        'school_id'     => $schoolId,
        'team_id'       => $teamId ?: null,
        'name'          => $name,
        'email'         => clean(post('email')) ?: null,
        'phone'         => clean(post('phone')) ?: null,
        'role'          => isset($roles[(string) post('role')]) ? post('role') : 'teacher',
        'subject'       => clean(post('subject')) ?: null,
        'assigned_date' => clean(post('assigned_date')) ?: null,
        'status'        => isset($statuses[(string) post('status')]) ? post('status') : 'active',
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'school_staff', 'Updated staff #' . $editId . ' (' . $name . ')');
        set_flash('success', 'Staff member updated.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'school_staff', 'Assigned staff #' . $newId . ' (' . $name . ') to school #' . $schoolId);
        set_flash('success', 'Staff member assigned.');
    }
    redirect('/admin/school-staff' . $ctxRedir);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'school_staff', 'Removed staff #' . $delId . ' (' . ($row['name'] ?? '') . ')');
        set_flash('success', 'Staff member removed.');
    }
    redirect('/admin/school-staff' . $ctxRedir);
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Staff record not found.');
        redirect('/admin/school-staff' . $ctxRedir);
    }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));

    $defaultSchool = (int) ($row['school_id'] ?? $validCtx);
    $defaultDate   = $row['assigned_date'] ?? date('Y-m-d');
    $teamMembers   = db_all("SELECT id, name, designation, email, phone FROM team_members WHERE status = 1 ORDER BY name");

    $page_title = $action === 'edit' ? 'Edit Staff Member' : 'Assign Staff Member';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">School staff / <?= $action === 'edit' ? 'Edit' : 'Assign' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('school-staff' . $ctxRedir)) ?>">&larr; Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('school-staff')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
        <input type="hidden" name="school" value="<?= (int) $validCtx ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Assignment</h3>
                <div class="form-group"><label class="form-label">School <span class="req">*</span></label>
                    <select class="form-select" name="school_id" required>
                        <option value="">Select school…</option>
                        <?php foreach ($schoolOpts as $sid => $sname): ?>
                            <option value="<?= (int) $sid ?>" <?= (int) old('school_id', $defaultSchool) === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option>
                        <?php endforeach; ?>
                    </select></div>

                <div class="form-group"><label class="form-label">Link to team member</label>
                    <select class="form-select" name="team_id" id="team-link">
                        <option value="">— None (manual entry) —</option>
                        <?php foreach ($teamMembers as $tm): ?>
                            <option value="<?= (int) $tm['id'] ?>"
                                    data-name="<?= e($tm['name']) ?>"
                                    data-email="<?= e($tm['email']) ?>"
                                    data-phone="<?= e($tm['phone']) ?>"
                                    <?= (int) old('team_id', $row['team_id'] ?? 0) === (int) $tm['id'] ? 'selected' : '' ?>>
                                <?= e($tm['name']) ?><?= $tm['designation'] ? ' — ' . e($tm['designation']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Optionally link an existing team member — their details can prefill the fields below.</small>
                </div>

                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['role'] ?? 'teacher') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Subject</label>
                        <input class="form-control" name="subject" value="<?= $o('subject') ?>" placeholder="e.g. Mathematics"></div>
                    <div class="form-group"><label class="form-label">Assigned date</label>
                        <input class="form-control" type="date" name="assigned_date" value="<?= e(old('assigned_date', $defaultDate)) ?>"></div>
                </div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Person details</h3>
                <div class="form-group"><label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" id="staff-name" required value="<?= $o('name') ?>"></div>
                <div class="form-group"><label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" id="staff-email" value="<?= $o('email') ?>"></div>
                <div class="form-group"><label class="form-label">Phone</label>
                    <input class="form-control" name="phone" id="staff-phone" value="<?= $o('phone') ?>"></div>
                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Assign' ?> staff</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('school-staff' . $ctxRedir)) ?>">Cancel</a>
                </div>
            </div></div>
        </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var link = document.getElementById('team-link');
        if (!link) return;
        link.addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) return;
            var fill = function (id, val) {
                var el = document.getElementById(id);
                if (el && el.value.trim() === '' && val) { el.value = val; }
            };
            fill('staff-name', opt.getAttribute('data-name'));
            fill('staff-email', opt.getAttribute('data-email'));
            fill('staff-phone', opt.getAttribute('data-phone'));
        });
    });
    </script>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title  = 'School Staff';
$search      = trim((string) get('q', ''));
$roleFilter  = (string) get('role', '');

$where  = '1=1';
$params = [];
if ($validCtx)                     { $where .= ' AND st.school_id = :sch'; $params[':sch'] = $validCtx; }
if (isset($roles[$roleFilter]))    { $where .= ' AND st.role = :role';    $params[':role'] = $roleFilter; }
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', st.name, st.email) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$p = paginate(
    "SELECT st.*, sc.name AS school_name, sc.code AS school_code
       FROM school_staff st
       JOIN schools sc ON sc.id = st.school_id
      WHERE $where
      ORDER BY st.name ASC",
    $params, 20
);

/* stat cards (scoped to the ?school context when present) */
$statWhere  = $validCtx ? 'school_id = :s' : '';
$statParams = $validCtx ? [':s' => $validCtx] : [];
$prefix     = $statWhere !== '' ? $statWhere . ' AND ' : '';
$counts = [
    'all'      => db_count('school_staff', $statWhere, $statParams),
    'active'   => db_count('school_staff', $prefix . "status = 'active'", $statParams),
    'teachers' => db_count('school_staff', $prefix . "role = 'teacher'", $statParams),
];

$hasFilters = $search !== '' || $roleFilter !== '';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>School Staff</h1>
        <span class="muted"><?= $validCtx ? e($schoolOpts[$validCtx]) . ' · ' : '' ?><?= (int) $counts['all'] ?> staff assigned</span>
    </div>
    <div class="flex" style="gap:.5rem;">
        <?php if ($validCtx): ?><a class="btn btn-secondary" href="<?= e(admin_url('school-dashboard?id=' . $validCtx)) ?>">&larr; Dashboard</a><?php endif; ?>
        <a class="btn btn-primary" href="<?= e(admin_url('school-staff?action=create' . $ctxLink)) ?>">+ Assign Staff</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('user-cog') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Total staff</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['active'] ?></div><div class="stat-label">Active</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('graduation-cap') ?></div><div><div class="stat-value"><?= (int) $counts['teachers'] ?></div><div class="stat-label">Teachers</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('school-staff')) ?>">
            <?php if ($validCtx): ?><input type="hidden" name="school" value="<?= (int) $validCtx ?>"><?php endif; ?>
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email…"></form>
        <form method="get" action="<?= e(admin_url('school-staff')) ?>" class="flex" style="gap:.5rem;">
            <select class="form-select" name="school" onchange="this.form.submit()">
                <option value="">All schools</option>
                <?php foreach ($schoolOpts as $sid => $sname): ?><option value="<?= (int) $sid ?>" <?= $validCtx === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="role" onchange="this.form.submit()">
                <option value="">All roles</option>
                <?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>" <?= $roleFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($hasFilters || $validCtx): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('school-staff')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Person</th><th>School</th><th>Contact</th><th>Assigned</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): ?>
            <tr>
                <td>
                    <strong><?= e($r['name']) ?></strong><br>
                    <small class="text-muted"><?= e($roles[$r['role']] ?? $r['role']) ?><?= $r['subject'] ? ' · ' . e($r['subject']) : '' ?></small>
                </td>
                <td><a href="<?= e(admin_url('school-dashboard?id=' . $r['school_id'])) ?>"><?= e($r['school_name']) ?></a></td>
                <td>
                    <?php if ($r['email']): ?><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><br><?php endif; ?>
                    <?php if ($r['phone']): ?><small class="text-muted"><?= e($r['phone']) ?></small><?php endif; ?>
                    <?= (!$r['email'] && !$r['phone']) ? '<span class="text-muted">—</span>' : '' ?>
                </td>
                <td><?= $r['assigned_date'] ? e(format_date($r['assigned_date'])) : '<span class="text-muted">—</span>' ?></td>
                <td><span class="pill <?= e($staffStatusPill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('school-staff?action=edit&id=' . $r['id'] . $ctxLink)) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('school-staff')) ?>" data-confirm="Remove this staff member from the school?" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="school" value="<?= (int) $validCtx ?>">
                        <button class="icon-btn danger" type="submit" title="Remove"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('user-cog') ?></div>
            <?= ($hasFilters || $validCtx) ? 'No staff match your filters. ' : 'No staff assigned yet. ' ?>
            <a href="<?= e(admin_url('school-staff?action=create' . $ctxLink)) ?>">Assign one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
