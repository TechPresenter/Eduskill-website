<?php
/**
 * Admin — Coupons. Discount / waiver codes for courses, memberships, donations.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'coupons';
$action = get('action', 'list');
$id     = (int) get('id', 0);

if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) post('code', '')));
    $errors = validate($_POST, ['code' => 'required|max:48']);
    if ($errors || $code === '') { set_flash('error', $code === '' ? 'A valid code is required.' : reset($errors)); redirect('/admin/coupons?action=' . ($editId ? 'edit&id=' . $editId : 'create')); }

    $dupWhere = 'code = :c'; $dupParams = [':c' => $code];
    if ($editId) { $dupWhere .= ' AND id <> :id'; $dupParams[':id'] = $editId; }
    if (db_count($table, $dupWhere, $dupParams) > 0) { set_flash('error', 'That code already exists.'); redirect('/admin/coupons?action=' . ($editId ? 'edit&id=' . $editId : 'create')); }

    $data = [
        'code'           => $code,
        'description'    => clean(post('description', '')),
        'discount_type'  => in_array(post('discount_type'), ['percent', 'fixed', 'waiver'], true) ? post('discount_type') : 'percent',
        'value'          => (float) post('value', 0),
        'applies_to'     => in_array(post('applies_to'), ['all', 'courses', 'memberships', 'donations'], true) ? post('applies_to') : 'all',
        'min_amount'     => (float) post('min_amount', 0),
        'usage_limit'    => post('usage_limit', '') !== '' ? (int) post('usage_limit') : null,
        'per_user_limit' => (int) post('per_user_limit', 1),
        'starts_at'      => post('starts_at') ? date('Y-m-d H:i:s', strtotime((string) post('starts_at'))) : null,
        'expires_at'     => post('expires_at') ? date('Y-m-d H:i:s', strtotime((string) post('expires_at'))) : null,
        'status'         => post('status') ? 1 : 0,
    ];
    if ($editId) { db_update($table, $data, 'id = :id', [':id' => $editId]); }
    else { db_insert($table, $data); }
    log_activity('update', 'coupons', 'Saved coupon ' . $code);
    set_flash('success', 'Coupon saved.');
    redirect('/admin/coupons');
}
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    db_delete($table, 'id = :id', [':id' => (int) post('id', 0)]);
    set_flash('success', 'Coupon deleted.');
    redirect('/admin/coupons');
}

if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) { set_flash('error', 'Not found.'); redirect('/admin/coupons'); }
    $sv = static fn ($k, $d = '') => e($row[$k] ?? $d);
    $dtVal = static fn ($k) => ($row['starts_at'] ?? null) && $k === 'starts_at' ? date('Y-m-d\TH:i', strtotime($row['starts_at'])) : (($row['expires_at'] ?? null) && $k === 'expires_at' ? date('Y-m-d\TH:i', strtotime($row['expires_at'])) : '');
    $page_title = $action === 'edit' ? 'Edit Coupon' : 'Add Coupon';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head"><div><h1><?= e($page_title) ?></h1><span class="muted">Referral &amp; Coupons / Coupons</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('coupons')) ?>">← Back</a></div>
    <div class="panel"><form class="admin-form panel-body" method="post" action="<?= e(admin_url('coupons')) ?>">
        <?= csrf_field() ?><input type="hidden" name="_do" value="save"><input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Code <span class="req">*</span></label><input class="form-control" name="code" required value="<?= $sv('code') ?>" placeholder="WELCOME20" style="text-transform:uppercase;"></div>
            <div class="form-group"><label class="form-label">Applies to</label><select class="form-select" name="applies_to">
                <?php foreach (['all' => 'Everything', 'courses' => 'Courses', 'memberships' => 'Memberships', 'donations' => 'Donations'] as $k => $l): ?>
                    <option value="<?= $k ?>" <?= ($row['applies_to'] ?? 'all') === $k ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select></div>
        </div>
        <div class="form-group"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= $sv('description') ?>"></div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Discount type</label><select class="form-select" name="discount_type">
                <option value="percent" <?= ($row['discount_type'] ?? 'percent') === 'percent' ? 'selected' : '' ?>>Percent (%)</option>
                <option value="fixed" <?= ($row['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed amount</option>
                <option value="waiver" <?= ($row['discount_type'] ?? '') === 'waiver' ? 'selected' : '' ?>>Full waiver (100% free)</option>
            </select></div>
            <div class="form-group"><label class="form-label">Value</label><input class="form-control" type="number" step="0.01" name="value" value="<?= $sv('value', '0') ?>"><span class="form-hint">Percent (e.g. 20) or fixed amount. Ignored for full waiver.</span></div>
        </div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Minimum amount</label><input class="form-control" type="number" step="0.01" name="min_amount" value="<?= $sv('min_amount', '0') ?>"></div>
            <div class="form-group"><label class="form-label">Total usage limit</label><input class="form-control" type="number" name="usage_limit" value="<?= e($row['usage_limit'] ?? '') ?>" placeholder="unlimited"></div>
        </div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Per-user limit</label><input class="form-control" type="number" name="per_user_limit" value="<?= (int) ($row['per_user_limit'] ?? 1) ?>"></div>
            <div class="form-group"><label class="form-label">&nbsp;</label><label class="checkbox" style="padding-top:.5rem;"><input type="checkbox" name="status" value="1" <?= (!array_key_exists('status', (array) $row) || !empty($row['status'])) ? 'checked' : '' ?>> Active</label></div>
        </div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Starts at</label><input class="form-control" type="datetime-local" name="starts_at" value="<?= $dtVal('starts_at') ?>"></div>
            <div class="form-group"><label class="form-label">Expires at</label><input class="form-control" type="datetime-local" name="expires_at" value="<?= $dtVal('expires_at') ?>"></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Save Coupon</button><a class="btn btn-ghost" href="<?= e(admin_url('coupons')) ?>">Cancel</a></div>
    </form></div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

$page_title = 'Coupons';
$p = paginate("SELECT * FROM $table ORDER BY id DESC", [], 15);
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head"><div><h1>Coupons</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <div class="flex gap-2"><a class="btn btn-secondary" href="<?= e(admin_url('coupon-analytics')) ?>"><?= lucide('bar-chart-3') ?> Analytics</a>
        <a class="btn btn-primary" href="<?= e(admin_url('coupons?action=create')) ?>">+ Add Coupon</a></div></div>
<div class="panel"><div class="panel-body">
<?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Code</th><th>Discount</th><th>Applies to</th><th>Used</th><th>Expires</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead><tbody>
    <?php foreach ($p['items'] as $r):
        $disc = $r['discount_type'] === 'percent' ? ((float) $r['value']) . '%' : ($r['discount_type'] === 'waiver' ? 'Full waiver' : number_format((float) $r['value']));
        $expired = !empty($r['expires_at']) && strtotime($r['expires_at']) < time();
    ?>
        <tr>
            <td><strong><code><?= e($r['code']) ?></code></strong><?php if (!empty($r['description'])): ?><br><small class="text-muted"><?= e(excerpt($r['description'], 8)) ?></small><?php endif; ?></td>
            <td><?= e($disc) ?></td>
            <td><span class="pill pill-blue"><?= e(ucfirst($r['applies_to'])) ?></span></td>
            <td><?= (int) $r['used_count'] ?><?= $r['usage_limit'] !== null ? ' / ' . (int) $r['usage_limit'] : '' ?></td>
            <td><small class="text-muted"><?= $r['expires_at'] ? e(format_date($r['expires_at'], 'd M Y')) : 'Never' ?></small></td>
            <td><span class="pill <?= $expired ? 'pill-red' : (!empty($r['status']) ? 'pill-green' : 'pill-gray') ?>"><?= $expired ? 'Expired' : (!empty($r['status']) ? 'Active' : 'Off') ?></span></td>
            <td><div class="actions">
                <a class="icon-btn" href="<?= e(admin_url('coupons?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                <form method="post" action="<?= e(admin_url('coupons')) ?>" data-confirm="Delete this coupon?" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button></form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?= $p['links'] ?>
<?php else: ?><div class="empty-state"><div class="icon"><?= lucide('ticket') ?></div>No coupons yet. <a href="<?= e(admin_url('coupons?action=create')) ?>">Create your first coupon</a>.</div><?php endif; ?>
</div></div>
<?php include __DIR__ . '/partials/foot.php'; ?>
