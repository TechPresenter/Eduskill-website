<?php
/**
 * =============================================================================
 *  Admin — Members (full Member Management)
 * =============================================================================
 *  list · view · create · edit · delete · CSV import/export, plus membership
 *  activation, manual/offline renewal recording and a per-member audit trail.
 *  Follows the standard admin pattern: CSRF-guarded POST handlers, prepared
 *  statements, pagination + search + filters, stat cards, flash + redirect.
 *
 *  Card download / print / QR live in admin/member-card.php.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/membership_card.php';
require_admin();

$table  = 'members';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* Account status (login state) — distinct from membership status. */
$statuses = ['active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'];
$statusPill = static fn (string $s): string => match ($s) {
    'active' => 'pill-green', 'pending' => 'pill-amber', 'suspended' => 'pill-red', default => 'pill-gray',
};
$memberTypes = ['member', 'donor', 'volunteer', 'intern', 'partner', 'supporter'];
$genders     = ['' => '—', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

/* -----------------------------------------------------------------------------
 | ROLE — what decides where this person lands after signing in.
 | -----------------------------------------------------------------------------
 | Distinct from `type`, which is a membership category the card/CSV code reads.
 | members.role is what includes/auth_router.php routes on, and until now this
 | screen could not set it at all — the column was written by nothing and every
 | row carried the 'member' default.
 |
 | The list is DERIVED from auth_roles(): only roles that may legitimately live
 | in the members table are offered, so a members row can never be given a
 | users-realm role like 'staff' or 'super-admin' from here.
 */
$memberRoles = [];
foreach (auth_roles() as $roleSlug => $roleCfg) {
    if (in_array('members', $roleCfg['realms'], true)) {
        $memberRoles[$roleSlug] = ucfirst(str_replace('-', ' ', $roleSlug));
    }
}
$rolePill = static fn (string $r): string => match ($r) {
    'school' => 'pill-violet', 'student' => 'pill-blue', 'volunteer' => 'pill-green',
    'donor'  => 'pill-amber',  default    => 'pill-gray',
};

/** Normalise a CSV date cell to YYYY-MM-DD, or null when unparseable/blank. */
function csv_date(string $v): ?string
{
    $v = trim($v);
    if ($v === '') {
        return null;
    }
    if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) {
        return $v;
    }
    if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $v, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}

/** Build a query string preserving the current list filters (for redirects). */
$listQS = static function (): string {
    $qs = http_build_query(array_filter([
        'status'  => clean(post('f_status', get('status', ''))),
        'mstatus' => clean(post('f_mstatus', get('mstatus', ''))),
        'plan'    => (int) post('f_plan', (int) get('plan', 0)) ?: '',
        'q'       => clean(post('f_q', get('q', ''))),
        'page'    => (int) post('f_page', 0) ?: '',
    ]));
    return $qs !== '' ? '?' . $qs : '';
};

/* =============================================================================
 |  SAVE (create / edit)
 |============================================================================*/
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'Member not found.');
        redirect('/admin/members');
    }

    $name  = clean(post('name'));
    $email = strtolower(clean(post('email')));

    $errors = [];
    if ($name === '')                 { $errors[] = 'Name is required.'; }
    if (!is_email($email))            { $errors[] = 'A valid email is required.'; }
    if (!$editId && mb_strlen((string) post('password')) < 8) { $errors[] = 'Password must be at least 8 characters.'; }
    // Email uniqueness.
    $clash = db_row('SELECT id FROM members WHERE email = :e' . ($editId ? ' AND id <> :id' : ''),
        $editId ? [':e' => $email, ':id' => $editId] : [':e' => $email]);
    if ($clash) { $errors[] = 'Another member already uses that email.'; }

    if ($errors) {
        flash_old($_POST);
        set_flash('error', implode(' ', $errors));
        redirect('/admin/members?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $planId = (int) post('plan_id', 0) ?: null;
    $data = [
        'name'              => $name,
        'email'             => $email,
        'phone'             => clean(post('phone')) ?: null,
        'type'              => in_array(post('type'), $memberTypes, true) ? post('type') : 'member',
        // Whitelisted against the members-realm roles only — see $memberRoles.
        'role'              => isset($memberRoles[(string) post('role')]) ? (string) post('role') : ($old['role'] ?? 'member'),
        'gender'            => in_array(post('gender'), ['male', 'female', 'other'], true) ? post('gender') : null,
        'dob'              => clean(post('dob')) ?: null,
        'occupation'        => clean(post('occupation')) ?: null,
        'address'           => clean(post('address')) ?: null,
        'city'              => clean(post('city')) ?: null,
        'state'             => clean(post('state')) ?: null,
        'pincode'           => clean(post('pincode')) ?: null,
        'plan_id'           => $planId,
        'join_date'         => clean(post('join_date')) ?: null,
        'expiry_date'       => clean(post('expiry_date')) ?: null,
        'membership_status' => in_array(post('membership_status'), ['none', 'active', 'expired', 'cancelled'], true) ? post('membership_status') : 'none',
        'auto_renew'        => post('auto_renew') ? 1 : 0,
        'status'            => isset($statuses[(string) post('status')]) ? post('status') : 'pending',
    ];

    // Optional password change.
    if (mb_strlen((string) post('password')) >= 8) {
        $data['password'] = password_hash((string) post('password'), PASSWORD_DEFAULT);
    }

    // Photo upload (optional).
    if (!empty($_FILES['avatar']['name'])) {
        $up = upload_image($_FILES['avatar'], 'members', ['allowed' => 'jpg,jpeg,png,gif,webp']);
        if (!$up['success']) {
            flash_old($_POST);
            set_flash('error', 'Photo: ' . $up['error']);
            redirect('/admin/members?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['avatar'] = $up['path'];
        if ($editId && !empty($old['avatar'])) { delete_upload($old['avatar']); }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        $member = member_ensure_identity(find($table, $editId));
        // Audit diff of meaningful fields.
        $changes = [];
        foreach (['name', 'email', 'phone', 'role', 'type', 'plan_id', 'join_date', 'expiry_date', 'membership_status', 'status', 'auto_renew'] as $f) {
            $before = $old[$f] ?? null;
            $after  = $data[$f] ?? $before;
            if ((string) $before !== (string) $after) { $changes[$f] = [$before, $after]; }
        }
        member_audit_log($editId, 'updated', $changes, 'Profile updated');
        log_activity('update', 'members', 'Updated member #' . $editId . ' (' . $email . ')');
        set_flash('success', 'Member updated.');
        redirect('/admin/members?action=view&id=' . $editId);
    }

    // Create.
    $data['password'] = $data['password'] ?? password_hash(str_random(12), PASSWORD_DEFAULT);
    $data['email_verified_at'] = post('mark_verified') ? date('Y-m-d H:i:s') : null;
    $newId = db_insert($table, $data);
    $member = member_ensure_identity(find($table, $newId));
    member_audit_log($newId, 'created', [], 'Member created in admin');
    log_activity('create', 'members', 'Created member #' . $newId . ' (' . $email . ')');
    set_flash('success', 'Member created. Membership ID ' . $member['member_code'] . '.');
    redirect('/admin/members?action=view&id=' . $newId);
}

/* =============================================================================
 |  ACCOUNT STATUS QUICK CHANGE
 |============================================================================*/
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $mid    = (int) post('id', 0);
    $status = (string) post('status', '');
    $row    = find($table, $mid);
    if (!$row || !isset($statuses[$status])) {
        set_flash('error', 'Invalid request.');
        redirect('/admin/members');
    }
    db_update($table, ['status' => $status], 'id = :id', [':id' => $mid]);
    member_audit_log($mid, 'status', ['status' => [$row['status'], $status]], 'Account status changed');
    log_activity('update', 'members', 'Member #' . $mid . ' account status → ' . $status);
    set_flash('success', 'Account status updated.');
    redirect(post('from') === 'view' ? '/admin/members?action=view&id=' . $mid : '/admin/members' . $listQS());
}

/* =============================================================================
 |  APPROVE A PENDING ACCOUNT
 |=============================================================================
 |  members.status = 'pending' is the approval queue, and auth_status_gate()
 |  refuses to seat a session for it. This is the action that empties the queue.
 |
 |  Approval is NOT verification: it does not touch email_verified_at, because
 |  an admin clicking a button is not evidence that the account holder owns the
 |  address. An approved-but-unverified account still has to confirm its email
 |  before it can sign in, and the UI says so.
 |============================================================================*/
if (is_post() && post('_do') === 'approve') {
    require_csrf();
    $mid = (int) post('id', 0);
    $row = find($table, $mid);
    if (!$row) {
        set_flash('error', 'Member not found.');
        redirect('/admin/members');
    }
    if ($row['status'] !== 'pending') {
        set_flash('error', 'Only a pending account can be approved.');
        redirect('/admin/members?action=view&id=' . $mid);
    }

    db_update($table, ['status' => 'active'], 'id = :id', [':id' => $mid]);
    member_audit_log($mid, 'approved', ['status' => ['pending', 'active']], 'Account approved in admin');
    log_activity('member_approved', 'members',
        'Approved member #' . $mid . ' (' . ($row['email'] ?? '') . ') role=' . ($row['role'] ?? 'member'));
    if (function_exists('send_member_approved_notice')) {
        send_member_approved_notice((string) $row['email'], (string) $row['name'], empty($row['email_verified_at']));
    }

    set_flash('success', empty($row['email_verified_at'])
        ? 'Account approved. They still need to verify their email address before they can sign in — we have emailed them the reminder.'
        : 'Account approved. They can sign in now.');
    redirect(post('from') === 'view' ? '/admin/members?action=view&id=' . $mid : '/admin/members' . $listQS());
}

/* =============================================================================
 |  ACTIVATE MEMBERSHIP (assign plan + compute expiry from duration)
 |============================================================================*/
if (is_post() && post('_do') === 'activate') {
    require_csrf();
    $mid    = (int) post('id', 0);
    $planId = (int) post('plan_id', 0);
    $start  = clean(post('start_date')) ?: date('Y-m-d');
    if (!find($table, $mid) || !membership_plan($planId)) {
        set_flash('error', 'Select a valid plan.');
        redirect('/admin/members?action=view&id=' . $mid);
    }
    member_activate($mid, $planId, $start, $_SESSION['user']['id'] ?? null);
    log_activity('update', 'members', 'Activated membership for #' . $mid);
    set_flash('success', 'Membership activated.');
    redirect('/admin/members?action=view&id=' . $mid);
}

/* =============================================================================
 |  RECORD RENEWAL (manual / offline payment)
 |============================================================================*/
if (is_post() && post('_do') === 'renew') {
    require_csrf();
    $mid    = (int) post('id', 0);
    $planId = (int) post('plan_id', 0) ?: (int) (find($table, $mid)['plan_id'] ?? 0);
    $plan   = membership_plan($planId);
    if (!find($table, $mid) || !$plan) {
        set_flash('error', 'Select a valid plan to renew.');
        redirect('/admin/members?action=view&id=' . $mid);
    }
    $method = in_array(post('method'), ['manual', 'offline'], true) ? post('method') : 'manual';
    $renewalId = membership_create_renewal($mid, $planId, [
        'amount'     => (float) post('amount', $plan['price']),
        'method'     => $method,
        'status'     => 'paid',
        'note'       => clean(post('note')) ?: null,
        'created_by' => $_SESSION['user']['id'] ?? null,
    ]);
    if ($renewalId) {
        membership_apply_renewal($renewalId, $_SESSION['user']['id'] ?? null);
        log_activity('create', 'members', 'Recorded renewal #' . $renewalId . ' for member #' . $mid);
        set_flash('success', 'Renewal recorded and membership extended.');
    } else {
        set_flash('error', 'Could not record the renewal.');
    }
    redirect('/admin/members?action=view&id=' . $mid);
}

/* =============================================================================
 |  CANCEL MEMBERSHIP
 |============================================================================*/
if (is_post() && post('_do') === 'cancel_membership') {
    require_csrf();
    $mid = (int) post('id', 0);
    $row = find($table, $mid);
    if ($row) {
        db_update($table, ['membership_status' => 'cancelled', 'auto_renew' => 0], 'id = :id', [':id' => $mid]);
        member_audit_log($mid, 'cancelled', ['membership_status' => [$row['membership_status'], 'cancelled']], 'Membership cancelled');
        log_activity('update', 'members', 'Cancelled membership for #' . $mid);
        set_flash('success', 'Membership cancelled.');
    }
    redirect('/admin/members?action=view&id=' . $mid);
}

/* =============================================================================
 |  DELETE
 |============================================================================*/
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        if (!empty($row['avatar'])) { delete_upload($row['avatar']); }
        db_delete($table, 'id = :id', [':id' => $delId]); // renewals/audit cascade
        log_activity('delete', 'members', 'Deleted member #' . $delId . ' (' . ($row['email'] ?? '') . ')');
        set_flash('success', 'Member deleted.');
    }
    redirect(post('from') === 'view' ? '/admin/members' : '/admin/members' . $listQS());
}

/* =============================================================================
 |  CSV IMPORT
 |============================================================================*/
if (is_post() && post('_do') === 'import') {
    require_csrf();
    if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        set_flash('error', 'Please choose a CSV file to import.');
        redirect('/admin/members?action=import');
    }
    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    if (!$fh) {
        set_flash('error', 'Could not read the uploaded file.');
        redirect('/admin/members?action=import');
    }

    // Map plan names/ids for lookup.
    $plansByName = [];
    foreach (membership_plans_all(false) as $pl) {
        $plansByName[strtolower($pl['name'])] = (int) $pl['id'];
    }

    $header = fgetcsv($fh);
    if (!$header) {
        fclose($fh);
        set_flash('error', 'The CSV appears to be empty.');
        redirect('/admin/members?action=import');
    }
    // Strip a UTF-8 BOM from the first header cell (Excel-saved CSVs) so the
    // first column is still recognised.
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
    }
    $cols = array_map(static fn($h) => strtolower(trim((string) $h)), $header);
    $idx  = array_flip($cols);
    $val  = static function (array $row, array $idx, string $key) {
        return isset($idx[$key]) ? trim((string) ($row[$idx[$key]] ?? '')) : '';
    };

    $created = 0; $updated = 0; $skipped = 0; $line = 1;
    while (($row = fgetcsv($fh)) !== false) {
        $line++;
        $email = strtolower($val($row, $idx, 'email'));
        $name  = $val($row, $idx, 'name');
        if ($name === '' || !is_email($email)) { $skipped++; continue; }

        $planRaw = strtolower($val($row, $idx, 'plan'));
        $planId  = $plansByName[$planRaw] ?? (ctype_digit($planRaw) ? (int) $planRaw : null);

        $fields = [
            'name'              => $name,
            'phone'             => $val($row, $idx, 'phone') ?: null,
            'type'              => in_array($val($row, $idx, 'type'), $memberTypes, true) ? $val($row, $idx, 'type') : 'member',
            'occupation'        => $val($row, $idx, 'occupation') ?: null,
            'address'           => $val($row, $idx, 'address') ?: null,
            'city'              => $val($row, $idx, 'city') ?: null,
            'state'             => $val($row, $idx, 'state') ?: null,
            'pincode'           => $val($row, $idx, 'pincode') ?: null,
            'gender'            => in_array($val($row, $idx, 'gender'), ['male', 'female', 'other'], true) ? $val($row, $idx, 'gender') : null,
            'dob'               => csv_date($val($row, $idx, 'dob')),
            'plan_id'           => $planId,
            'join_date'         => csv_date($val($row, $idx, 'join_date')),
            'expiry_date'       => csv_date($val($row, $idx, 'expiry_date')),
            'membership_status' => in_array($val($row, $idx, 'membership_status'), ['none', 'active', 'expired', 'cancelled'], true) ? $val($row, $idx, 'membership_status') : ($planId ? 'active' : 'none'),
            'status'            => isset($statuses[$val($row, $idx, 'status')]) ? $val($row, $idx, 'status') : 'active',
        ];

        /* `role` decides where the person lands after signing in, so it is only
           written when the CSV actually names a valid members-realm role.
           Including it unconditionally would silently demote every existing
           school/student row to 'member' on any import whose sheet has no role
           column — this loop UPDATEs matched rows with the whole $fields array. */
        $roleCell = strtolower($val($row, $idx, 'role'));
        if (isset($memberRoles[$roleCell])) {
            $fields['role'] = $roleCell;
        }

        $existing = db_row('SELECT id FROM members WHERE email = :e', [':e' => $email]);
        if ($existing) {
            db_update($table, $fields, 'id = :id', [':id' => (int) $existing['id']]);
            member_ensure_identity(find($table, (int) $existing['id']));
            member_audit_log((int) $existing['id'], 'imported', [], 'Updated via CSV import');
            $updated++;
        } else {
            $fields['email']    = $email;
            $fields['password'] = password_hash(str_random(12), PASSWORD_DEFAULT);
            $nid = db_insert($table, $fields);
            member_ensure_identity(find($table, $nid));
            member_audit_log($nid, 'imported', [], 'Created via CSV import');
            $created++;
        }
    }
    fclose($fh);
    log_activity('import', 'members', "CSV import: $created created, $updated updated, $skipped skipped");
    set_flash('success', "Import complete — $created created, $updated updated, $skipped skipped.");
    redirect('/admin/members');
}

/* =============================================================================
 |  CSV EXPORT
 |============================================================================*/
if ($action === 'export') {
    $rows = db_all(
        'SELECT m.*, p.name AS plan_name FROM members m
         LEFT JOIN membership_plans p ON p.id = m.plan_id ORDER BY m.id DESC'
    );
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="members-' . date('Y-m-d') . '.csv"');
    // Neutralise spreadsheet formula injection (=,+,-,@,tab,CR) on export.
    $safe = static function ($v): string {
        $v = (string) $v;
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };
    $out = fopen('php://output', 'w');
    fputcsv($out, ['member_code', 'name', 'email', 'phone', 'role', 'type', 'plan', 'membership_status',
        'join_date', 'expiry_date', 'auto_renew', 'account_status', 'email_verified', 'city', 'state', 'created_at']);
    foreach ($rows as $r) {
        fputcsv($out, array_map($safe, [
            $r['member_code'], $r['name'], $r['email'], $r['phone'], $r['role'], $r['type'], $r['plan_name'],
            $r['membership_status'], $r['join_date'], $r['expiry_date'], $r['auto_renew'],
            $r['status'], $r['email_verified_at'] ? 'yes' : 'no', $r['city'], $r['state'], $r['created_at'],
        ]));
    }
    fclose($out);
    exit;
}

/* =============================================================================
 |  VIEW (details + membership + audit + renewals + card)
 |============================================================================*/
if ($action === 'view') {
    $row = find($table, $id);
    if (!$row) {
        set_flash('error', 'Member not found.');
        redirect('/admin/members');
    }
    $row       = member_ensure_identity($row);
    $plan      = membership_plan((int) ($row['plan_id'] ?? 0));
    $mStatus   = member_effective_status($row);
    $daysLeft  = member_days_left($row);
    $isVerified = !empty($row['email_verified_at']);
    $audit     = member_audit_trail($id, 40);
    $renewals  = member_renewals($id, 30);
    $plans     = membership_plans_all(false);

    $page_title = 'Member — ' . $row['name'];
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($row['name']) ?></h1><span class="muted">Members / <?= e($row['member_code']) ?></span></div>
        <div class="flex" style="gap:.5rem;">
            <a class="btn btn-outline" href="<?= e(admin_url('member-card?id=' . $id . '&format=print')) ?>" target="_blank"><?= lucide('id-card') ?> Card</a>
            <a class="btn btn-outline" href="<?= e(admin_url('member-card?id=' . $id . '&format=pdf')) ?>"><?= lucide('download') ?> PDF</a>
            <a class="btn btn-secondary" href="<?= e(admin_url('members?action=edit&id=' . $id)) ?>"><?= lucide('pencil') ?> Edit</a>
            <a class="btn btn-ghost" href="<?= e(admin_url('members')) ?>">← Back</a>
        </div>
    </div>

    <div class="grid-2" style="align-items:start;gap:1.25rem;">
        <div>
            <!-- Membership card preview -->
            <div class="panel"><div class="panel-body" style="display:flex;justify-content:center;">
                <?= card_html($row, card_default_template()) ?>
            </div></div>
            <?= card_html_assets() ?>

            <!-- Profile -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Profile</h3>
                <div class="table-wrap"><table class="admin-table"><tbody>
                    <tr><th style="width:170px;">Email</th><td><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a>
                        <span class="pill <?= $isVerified ? 'pill-green' : 'pill-gray' ?>"><?= $isVerified ? 'Verified' : 'Unverified' ?></span></td></tr>
                    <tr><th>Phone</th><td><?= $row['phone'] ? e($row['phone']) : '—' ?></td></tr>
                    <tr><th>Role</th><td>
                        <?php $vRole = strtolower((string) ($row['role'] ?? 'member')); ?>
                        <span class="pill <?= e($rolePill($vRole)) ?>"><?= e($memberRoles[$vRole] ?? ucfirst($vRole)) ?></span>
                        <small class="text-muted">lands on <code><?= e(auth_dashboard_for($vRole, 'members')) ?></code></small>
                    </td></tr>
                    <tr><th>Type</th><td><?= e(ucfirst($row['type'])) ?></td></tr>
                    <tr><th>Gender / DOB</th><td><?= e($genders[$row['gender'] ?? ''] ?? '—') ?> · <?= $row['dob'] ? e(format_date($row['dob'])) : '—' ?></td></tr>
                    <tr><th>Occupation</th><td><?= $row['occupation'] ? e($row['occupation']) : '—' ?></td></tr>
                    <tr><th>Address</th><td><?= e(trim(implode(', ', array_filter([$row['address'], $row['city'], $row['state'], $row['pincode']]))) ?: '—') ?></td></tr>
                    <tr><th>Account</th><td><span class="pill <?= e($statusPill($row['status'])) ?>"><?= e($statuses[$row['status']] ?? $row['status']) ?></span></td></tr>
                    <tr><th>Registered</th><td><?= e(format_datetime($row['created_at'])) ?></td></tr>
                </tbody></table></div>
            </div></div>

            <!-- Renewal history -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Renewal history</h3>
                <?php if ($renewals): ?>
                <div class="table-wrap"><table class="admin-table">
                    <thead><tr><th>Date</th><th>Plan</th><th>Amount</th><th>Method</th><th>Period</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($renewals as $rn): ?>
                        <tr>
                            <td><?= e(format_date($rn['created_at'])) ?></td>
                            <td><?= e($rn['plan_name'] ?? '—') ?></td>
                            <td><?= e(money($rn['amount'], '₹', 2)) ?></td>
                            <td><?= e(ucfirst($rn['method'])) ?></td>
                            <td><?= $rn['period_end'] ? e(format_date($rn['period_start'], 'd M y')) . ' → ' . e(format_date($rn['period_end'], 'd M y')) : '—' ?></td>
                            <td><span class="pill <?= $rn['status'] === 'paid' ? 'pill-green' : ($rn['status'] === 'pending' ? 'pill-amber' : 'pill-red') ?>"><?= e(ucfirst($rn['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php else: ?><p class="text-muted">No renewals recorded yet.</p><?php endif; ?>
            </div></div>

            <!-- Audit trail -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Audit trail</h3>
                <?php if ($audit): ?>
                <ul class="timeline" style="list-style:none;padding:0;margin:0;">
                    <?php foreach ($audit as $a): ?>
                        <li style="padding:.5rem 0;border-bottom:1px solid var(--border);">
                            <strong><?= e(ucfirst($a['action'])) ?></strong>
                            <?= $a['note'] ? '— ' . e($a['note']) : '' ?>
                            <?php $ch = json_column($a['changes']); if ($ch): ?>
                                <div class="text-muted" style="font-size:.8rem;">
                                    <?php foreach ($ch as $f => $pair): ?>
                                        <code><?= e($f) ?></code>: <?= e((string) ($pair[0] ?? '—')) ?> → <?= e((string) ($pair[1] ?? '—')) ?>;
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="text-muted" style="font-size:.75rem;"><?= e(format_datetime($a['created_at'])) ?><?= $a['admin_name'] ? ' · ' . e($a['admin_name']) : '' ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?><p class="text-muted">No audit entries yet.</p><?php endif; ?>
            </div></div>
        </div>

        <!-- Actions column -->
        <div>
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Membership</h3>
                <p style="margin:.2rem 0;">
                    <span class="pill <?= e(membership_status_pill($mStatus)) ?>"><?= e(membership_status_label($mStatus)) ?></span>
                    <?php if ($plan): ?><span class="pill pill-blue"><?= e($plan['name']) ?></span><?php endif; ?>
                </p>
                <div class="table-wrap"><table class="admin-table"><tbody>
                    <tr><th>Member ID</th><td><strong><?= e($row['member_code']) ?></strong></td></tr>
                    <tr><th>Joined</th><td><?= $row['join_date'] ? e(format_date($row['join_date'])) : '—' ?></td></tr>
                    <tr><th>Expires</th><td><?= $row['expiry_date'] ? e(format_date($row['expiry_date'])) : '—' ?>
                        <?php if ($daysLeft !== null): ?>
                            <span class="text-muted">(<?= $daysLeft < 0 ? abs($daysLeft) . ' days ago' : $daysLeft . ' days left' ?>)</span>
                        <?php endif; ?></td></tr>
                    <tr><th>Auto-renew</th><td><?= !empty($row['auto_renew']) ? 'On' : 'Off' ?></td></tr>
                </tbody></table></div>
            </div></div>

            <!-- Activate / change plan -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2"><?= $mStatus === 'active' ? 'Change plan' : 'Activate membership' ?></h3>
                <form method="post" action="<?= e(admin_url('members')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="activate">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-group"><label class="form-label">Plan / tier</label>
                        <select class="form-select" name="plan_id" required>
                            <option value="">Select plan…</option>
                            <?php foreach ($plans as $pl): ?>
                                <option value="<?= (int) $pl['id'] ?>" <?= (int) ($row['plan_id'] ?? 0) === (int) $pl['id'] ? 'selected' : '' ?>>
                                    <?= e($pl['name']) ?> — <?= e(money($pl['price'], '₹', 0)) ?> / <?= (int) $pl['duration_days'] ?>d
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Start date</label>
                        <input class="form-control" type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <button class="btn btn-primary btn-block" type="submit"><?= lucide('badge-check') ?> Set membership</button>
                </form>
            </div></div>

            <!-- Record renewal -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Record renewal (offline)</h3>
                <form method="post" action="<?= e(admin_url('members')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="renew">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-group"><label class="form-label">Plan</label>
                        <select class="form-select" name="plan_id">
                            <?php foreach ($plans as $pl): ?>
                                <option value="<?= (int) $pl['id'] ?>" <?= (int) ($row['plan_id'] ?? 0) === (int) $pl['id'] ? 'selected' : '' ?>><?= e($pl['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label class="form-label">Amount (₹)</label>
                            <input class="form-control" type="number" step="0.01" name="amount" value="<?= e($plan['price'] ?? '0') ?>"></div>
                        <div class="form-group"><label class="form-label">Method</label>
                            <select class="form-select" name="method"><option value="manual">Manual</option><option value="offline">Offline (cash/bank)</option></select></div>
                    </div>
                    <div class="form-group"><label class="form-label">Note</label><input class="form-control" name="note" placeholder="Receipt no. / reference"></div>
                    <button class="btn btn-success btn-block" type="submit"><?= lucide('receipt-indian-rupee') ?> Record & extend</button>
                </form>
            </div></div>

            <!-- Danger / account -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Account & danger zone</h3>

                <?php if ($row['status'] === 'pending'): ?>
                <?php /* The approval queue, at the point of decision. */ ?>
                <div class="alert alert-warning" style="margin-bottom:.8rem;">
                    <?= lucide('hourglass') ?>
                    <span>This account is <strong>awaiting approval</strong> and cannot sign in yet.
                    <?= $isVerified ? '' : ' Its email address has not been verified either.' ?></span>
                </div>
                <form method="post" action="<?= e(admin_url('members')) ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="approve"><input type="hidden" name="from" value="view"><input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn-success btn-block" type="submit"><?= lucide('user-check') ?> Approve this account</button>
                    <small class="form-hint">Approval does not verify the email address — that stays the account
                        holder's to prove.</small>
                </form>
                <?php endif; ?>

                <form method="post" action="<?= e(admin_url('members')) ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="status"><input type="hidden" name="from" value="view"><input type="hidden" name="id" value="<?= $id ?>">
                    <label class="form-label">Account status</label>
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $row['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                    <small class="form-hint"><strong>Pending</strong> means awaiting approval — the account cannot
                        sign in. <strong>Suspended</strong> blocks sign-in permanently until changed.</small>
                </form>
                <?php if ($mStatus !== 'cancelled' && $mStatus !== 'none'): ?>
                <form method="post" action="<?= e(admin_url('members')) ?>" class="mb-2" data-confirm="Cancel this membership?">
                    <?= csrf_field() ?><input type="hidden" name="_do" value="cancel_membership"><input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn-outline btn-block" type="submit"><?= lucide('ban') ?> Cancel membership</button>
                </form>
                <?php endif; ?>
                <form method="post" action="<?= e(admin_url('members')) ?>" data-confirm="Delete this member permanently? This cannot be undone.">
                    <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="from" value="view"><input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn-danger btn-block" type="submit"><?= lucide('trash-2') ?> Delete member</button>
                </form>
            </div></div>
        </div>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* =============================================================================
 |  CREATE / EDIT FORM
 |============================================================================*/
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Member not found.');
        redirect('/admin/members');
    }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));
    $plans = membership_plans_all(false);
    $page_title = $action === 'edit' ? 'Edit Member' : 'Add Member';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Members / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('members')) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('members')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Personal details</h3>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Full name <span class="req">*</span></label>
                        <input class="form-control" name="name" required value="<?= $o('name') ?>"></div>
                    <div class="form-group"><label class="form-label">Email <span class="req">*</span></label>
                        <input class="form-control" type="email" name="email" required value="<?= $o('email') ?>"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Phone</label>
                        <input class="form-control" name="phone" value="<?= $o('phone') ?>"></div>
                    <div class="form-group"><label class="form-label">Member type</label>
                        <select class="form-select" name="type">
                            <?php foreach ($memberTypes as $t): ?><option value="<?= e($t) ?>" <?= ($row['type'] ?? 'member') === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option><?php endforeach; ?>
                        </select>
                        <small class="form-hint">Membership category — used by the card, renewals and CSV export.</small></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Portal role</label>
                        <select class="form-select" name="role">
                            <?php $curRole = strtolower((string) ($row['role'] ?? 'member'));
                            foreach ($memberRoles as $rk => $rl): ?>
                                <option value="<?= e($rk) ?>" <?= $curRole === $rk ? 'selected' : '' ?>><?= e($rl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">Decides which dashboard they land on after signing in. Staff and
                            administrator roles are not available here — those live on <a href="<?= e(admin_url('users')) ?>">Users</a>.</small>
                    </div>
                    <div class="form-group"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Gender</label>
                        <select class="form-select" name="gender">
                            <?php foreach ($genders as $gk => $gl): ?><option value="<?= e($gk) ?>" <?= ($row['gender'] ?? '') === $gk ? 'selected' : '' ?>><?= e($gl) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Date of birth</label>
                        <input class="form-control" type="date" name="dob" value="<?= $o('dob') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Occupation</label>
                    <input class="form-control" name="occupation" value="<?= $o('occupation') ?>"></div>
                <div class="form-group"><label class="form-label">Address</label>
                    <input class="form-control" name="address" value="<?= $o('address') ?>"></div>
                <div class="grid-3">
                    <div class="form-group"><label class="form-label">City</label><input class="form-control" name="city" value="<?= $o('city') ?>"></div>
                    <div class="form-group"><label class="form-label">State</label><input class="form-control" name="state" value="<?= $o('state') ?>"></div>
                    <div class="form-group"><label class="form-label">Pincode</label><input class="form-control" name="pincode" value="<?= $o('pincode') ?>"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Photo</label>
                        <input class="form-control" type="file" name="avatar" accept="image/*">
                        <?php if (!empty($row['avatar'])): ?><small class="form-hint">Current photo kept unless you upload a new one.</small><?php endif; ?>
                    </div>
                    <div class="form-group"><label class="form-label">Password <?= $action === 'edit' ? '' : '<span class="req">*</span>' ?></label>
                        <input class="form-control" type="text" name="password" placeholder="<?= $action === 'edit' ? 'Leave blank to keep current' : 'Min 8 characters' ?>">
                    </div>
                </div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Membership</h3>
                <div class="form-group"><label class="form-label">Plan / tier</label>
                    <select class="form-select" name="plan_id">
                        <option value="">— None —</option>
                        <?php foreach ($plans as $pl): ?>
                            <option value="<?= (int) $pl['id'] ?>" <?= (int) ($row['plan_id'] ?? 0) === (int) $pl['id'] ? 'selected' : '' ?>>
                                <?= e($pl['name']) ?> — <?= e(money($pl['price'], '₹', 0)) ?> / <?= (int) $pl['duration_days'] ?>d
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Tip: use the member view to auto-compute the expiry from the plan duration.</small>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Join date</label><input class="form-control" type="date" name="join_date" value="<?= $o('join_date') ?>"></div>
                    <div class="form-group"><label class="form-label">Expiry date</label><input class="form-control" type="date" name="expiry_date" value="<?= $o('expiry_date') ?>"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Membership status</label>
                        <select class="form-select" name="membership_status">
                            <?php foreach (['none', 'active', 'expired', 'cancelled'] as $ms): ?>
                                <option value="<?= e($ms) ?>" <?= ($row['membership_status'] ?? 'none') === $ms ? 'selected' : '' ?>><?= e(membership_status_label($ms)) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Account status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['status'] ?? 'pending') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <label class="checkbox mt-2"><input type="checkbox" name="auto_renew" value="1" <?= !empty($row['auto_renew']) ? 'checked' : '' ?>> Auto-renew enabled</label>
                <?php if ($action === 'create'): ?>
                <label class="checkbox mt-2"><input type="checkbox" name="mark_verified" value="1"> Mark email as already verified</label>
                <?php endif; ?>
                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> member</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('members')) ?>">Cancel</a>
                </div>
            </div></div>
        </div>
    </form>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* =============================================================================
 |  CSV IMPORT FORM
 |============================================================================*/
if ($action === 'import') {
    $page_title = 'Import Members (CSV)';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Import Members</h1><span class="muted">Members / CSV import</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('members')) ?>">← Back to list</a>
    </div>
    <div class="panel"><div class="panel-body">
        <p>Upload a <code>.csv</code> with a header row. Recognised columns (extra columns are ignored):</p>
        <p><code>name, email, phone, role, type, plan, join_date, expiry_date, status, membership_status, address, city, state, pincode, occupation, gender, dob</code></p>
        <ul class="text-muted" style="font-size:.9rem;">
            <li><strong>name</strong> and a valid <strong>email</strong> are required; rows missing them are skipped.</li>
            <li>Existing members (matched by email) are updated; others are created with a random password.</li>
            <li><strong>plan</strong> may be the plan name or its id. Dates accept <code>YYYY-MM-DD</code> or <code>DD/MM/YYYY</code>.</li>
            <li><strong>role</strong> decides which dashboard the person lands on:
                <code><?= e(implode(', ', array_keys($memberRoles))) ?></code>.
                Anything else is ignored — and on an update, a missing or unrecognised role leaves the
                existing one alone rather than resetting it.</li>
            <li><strong>status</strong> <code>pending</code> means the account is in the approval queue and
                cannot sign in; <code>active</code> lets it sign in once the email is verified.</li>
        </ul>
        <form method="post" action="<?= e(admin_url('members')) ?>" enctype="multipart/form-data" class="mt-3">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="import">
            <div class="form-group"><label class="form-label">CSV file</label>
                <input class="form-control" type="file" name="csv" accept=".csv,text/csv" required></div>
            <button class="btn btn-primary" type="submit"><?= lucide('upload') ?> Import</button>
            <a class="btn btn-outline" href="<?= e(admin_url('members?action=sample')) ?>"><?= lucide('file-down') ?> Download sample CSV</a>
        </form>
    </div></div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* Sample CSV download. */
if ($action === 'sample') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="members-sample.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['name', 'email', 'phone', 'role', 'type', 'plan', 'join_date', 'expiry_date', 'status', 'membership_status', 'address', 'city', 'state', 'pincode', 'occupation', 'gender', 'dob']);
    fputcsv($out, ['Asha Devi', 'asha@example.com', '7491932148', 'member', 'member', 'Gold', '2026-01-01', '2027-01-01', 'active', 'active', '12 MG Road', 'Patna', 'Bihar', '800001', 'Teacher', 'female', '1990-05-12']);
    fclose($out);
    exit;
}

/* =============================================================================
 |  LIST
 |============================================================================*/
$page_title   = 'Members';
$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');
$mStatusFilter = (string) get('mstatus', '');
$planFilter   = (int) get('plan', 0);
$expiringFilter = (int) get('expiring', 0) === 1;

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', m.name, m.email, m.phone, m.member_code) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
if (isset($statuses[$statusFilter])) {
    $where .= ' AND m.status = :st';
    $params[':st'] = $statusFilter;
}
if (in_array($mStatusFilter, ['none', 'active', 'expired', 'cancelled'], true)) {
    if ($mStatusFilter === 'expired') {
        // Include cron-flipped (stored 'expired') members, mirroring member_effective_status().
        $where .= " AND m.membership_status IN ('active','expired') AND m.expiry_date IS NOT NULL AND m.expiry_date < CURDATE()";
    } elseif ($mStatusFilter === 'active') {
        $where .= " AND m.membership_status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE())";
    } else {
        $where .= ' AND m.membership_status = :ms';
        $params[':ms'] = $mStatusFilter;
    }
}
if ($expiringFilter) {
    $where .= " AND m.membership_status = 'active' AND m.expiry_date IS NOT NULL AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}
if ($planFilter > 0) {
    $where .= ' AND m.plan_id = :pl';
    $params[':pl'] = $planFilter;
}

$p = paginate(
    "SELECT m.*, p.name AS plan_name FROM members m LEFT JOIN membership_plans p ON p.id = m.plan_id WHERE $where ORDER BY m.id DESC",
    $params, 15
);

$counts = [
    'all'       => db_count('members'),
    'active'    => db_count('members', "membership_status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE())"),
    'expiring'  => db_count('members', "membership_status = 'active' AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
    'expired'   => db_count('members', "membership_status IN ('active','expired') AND expiry_date IS NOT NULL AND expiry_date < CURDATE()"),
    // Account-level, not membership-level: these people cannot sign in at all.
    'pending'   => db_count('members', "status = 'pending'"),
];
$allPlans = membership_plans_all(false);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Members</h1><span class="muted"><?= (int) $counts['all'] ?> total</span></div>
    <div class="flex" style="gap:.5rem;">
        <a class="btn btn-outline" href="<?= e(admin_url('members?action=export')) ?>"><?= lucide('download') ?> Export</a>
        <a class="btn btn-outline" href="<?= e(admin_url('members?action=import')) ?>"><?= lucide('upload') ?> Import</a>
        <a class="btn btn-primary" href="<?= e(admin_url('members?action=create')) ?>">+ Add Member</a>
    </div>
</div>

<div class="stat-grid">
    <a class="stat-card" href="<?= e(admin_url('members')) ?>" style="<?= $mStatusFilter === '' && $statusFilter === '' ? 'outline:2px solid var(--brand,#0B4E3D);' : '' ?>">
        <div class="stat-icon bg-violet"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">All Members</div></div></a>
    <a class="stat-card" href="<?= e(admin_url('members?mstatus=active')) ?>" style="<?= $mStatusFilter === 'active' ? 'outline:2px solid var(--brand,#0B4E3D);' : '' ?>">
        <div class="stat-icon bg-green"><?= lucide('badge-check') ?></div><div><div class="stat-value"><?= (int) $counts['active'] ?></div><div class="stat-label">Active</div></div></a>
    <a class="stat-card" href="<?= e(admin_url('members?expiring=1')) ?>" style="<?= $expiringFilter ? 'outline:2px solid var(--brand,#0B4E3D);' : '' ?>">
        <div class="stat-icon bg-amber"><?= lucide('clock') ?></div><div><div class="stat-value"><?= (int) $counts['expiring'] ?></div><div class="stat-label">Expiring ≤30d</div></div></a>
    <a class="stat-card" href="<?= e(admin_url('members?mstatus=expired')) ?>" style="<?= $mStatusFilter === 'expired' ? 'outline:2px solid var(--brand,#0B4E3D);' : '' ?>">
        <div class="stat-icon bg-rose"><?= lucide('calendar-x') ?></div><div><div class="stat-value"><?= (int) $counts['expired'] ?></div><div class="stat-label">Expired</div></div></a>
    <?php /* The approval queue. These accounts cannot sign in until approved —
             see auth_status_gate(). Policy lives in Registration Policy. */ ?>
    <a class="stat-card" href="<?= e(admin_url('members?status=pending')) ?>" style="<?= $statusFilter === 'pending' ? 'outline:2px solid var(--brand,#0B4E3D);' : '' ?>">
        <div class="stat-icon bg-amber"><?= lucide('user-check') ?></div><div><div class="stat-value"><?= (int) $counts['pending'] ?></div><div class="stat-label">Awaiting Approval</div></div></a>
</div>

<?php if ($statusFilter === 'pending'): ?>
<div class="alert alert-info">
    <?= lucide('info') ?>
    <span>These accounts are waiting on a human. Whether new sign-ups land here at all is set in
    <a href="<?= e(admin_url('registration-settings')) ?>">Registration Policy</a>; School accounts
    always do, whatever that policy says.</span>
</div>
<?php endif; ?>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('members')) ?>">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone, ID…">
        </form>
        <form method="get" action="<?= e(admin_url('members')) ?>" class="flex" style="gap:.5rem;">
            <?php /* Account status was filterable only by hand-editing the URL, which is
                     why the approval queue was invisible from this screen. */ ?>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All accounts</option>
                <?php foreach ($statuses as $sk => $sl): ?>
                    <option value="<?= e($sk) ?>" <?= $statusFilter === $sk ? 'selected' : '' ?>><?= e($sl) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="mstatus" onchange="this.form.submit()">
                <option value="">All membership</option>
                <?php foreach (['active', 'expired', 'none', 'cancelled'] as $ms): ?>
                    <option value="<?= e($ms) ?>" <?= $mStatusFilter === $ms ? 'selected' : '' ?>><?= e(membership_status_label($ms)) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="plan" onchange="this.form.submit()">
                <option value="">All tiers</option>
                <?php foreach ($allPlans as $pl): ?><option value="<?= (int) $pl['id'] ?>" <?= $planFilter === (int) $pl['id'] ? 'selected' : '' ?>><?= e($pl['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($search !== '' || $statusFilter !== '' || $mStatusFilter !== '' || $planFilter || $expiringFilter): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('members')) ?>">Clear</a>
        <?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Member</th><th>Role</th><th>Account</th><th>Membership</th><th>Expires</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $viewUrl = admin_url('members?action=view&id=' . $r['id']);
            $ms = member_effective_status($r);
            $dl = member_days_left($r);
            $rr = strtolower((string) ($r['role'] ?? 'member'));
        ?>
            <tr>
                <td><div class="flex items-center" style="gap:.6rem;">
                    <img src="<?= e(image_url($r['avatar'] ?? null, 'avatar')) ?>" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex:none;">
                    <div><a href="<?= e($viewUrl) ?>"><strong><?= e($r['name']) ?></strong></a><br>
                        <small class="text-muted"><?= e($r['email']) ?></small><br>
                        <small class="text-muted"><?= e($r['member_code'] ?? '—') ?></small></div>
                </div></td>
                <td><span class="pill <?= e($rolePill($rr)) ?>"><?= e($memberRoles[$rr] ?? ucfirst($rr)) ?></span></td>
                <td><span class="pill <?= e($statusPill((string) $r['status'])) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span>
                    <?php if (empty($r['email_verified_at'])): ?><br><small class="pill pill-gray">Unverified</small><?php endif; ?></td>
                <td><span class="pill <?= e(membership_status_pill($ms)) ?>"><?= e(membership_status_label($ms)) ?></span>
                    <?php if ($r['plan_name']): ?><br><small class="text-muted"><?= e($r['plan_name']) ?></small><?php endif; ?></td>
                <td><?php if ($r['expiry_date']): ?>
                        <span title="<?= e(format_date($r['expiry_date'])) ?>"><?= e(format_date($r['expiry_date'], 'd M Y')) ?></span>
                        <?php if ($dl !== null && $dl >= 0 && $dl <= 30): ?><br><small class="pill pill-amber"><?= $dl ?>d left</small><?php endif; ?>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                <td><div class="actions">
                    <?php if ($r['status'] === 'pending'): ?>
                        <form method="post" action="<?= e(admin_url('members')) ?>" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_do" value="approve">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>">
                            <input type="hidden" name="f_q" value="<?= e($search) ?>">
                            <button class="icon-btn" type="submit" title="Approve account"><?= lucide('user-check') ?></button>
                        </form>
                    <?php endif; ?>
                    <a class="icon-btn" href="<?= e($viewUrl) ?>" title="View"><?= lucide('eye') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('member-card?id=' . $r['id'] . '&format=pdf')) ?>" title="Card PDF"><?= lucide('id-card') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('members?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('users') ?></div>
            <?= ($search !== '' || $statusFilter !== '' || $mStatusFilter !== '' || $planFilter || $expiringFilter) ? 'No members match your filters. ' : 'No members yet. ' ?>
            <a href="<?= e(admin_url('members?action=create')) ?>">Add one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
