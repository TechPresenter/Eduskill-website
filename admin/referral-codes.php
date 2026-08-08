<?php
/**
 * Admin — Referral Codes. Generate a unique referral link per member/volunteer,
 * list all codes with their live stats.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table = 'referral_codes';

if (is_post() && post('_do') === 'generate') {
    require_csrf();
    $name  = clean(post('name', ''));
    $type  = in_array(post('owner_type'), ['member', 'volunteer', 'user', 'other'], true) ? post('owner_type') : 'other';
    $email = strtolower(clean(post('email', '')));
    $ownerId = null;
    if ($email !== '') {
        if ($type === 'member')    { $o = find_by('members', 'email', $email);    if ($o) { $ownerId = (int) $o['id']; $name = $name ?: $o['name']; } }
        if ($type === 'volunteer') { $o = find_by('volunteers', 'email', $email); if ($o) { $ownerId = (int) $o['id']; $name = $name ?: $o['name']; } }
    }
    if ($name === '') { set_flash('error', 'Enter a name for this referral code.'); redirect('/admin/referral-codes'); }

    $custom = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) post('custom_code', '')));
    if ($custom !== '' && !find_by($table, 'code', $custom)) {
        $code = $custom;
    } else {
        $baseSrc = preg_replace('/[^A-Za-z0-9]/', '', $name);
        $base = strtoupper(substr($baseSrc, 0, 6) ?: 'REF');
        do { $code = $base . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4)); } while (find_by($table, 'code', $code));
    }
    db_insert($table, ['owner_type' => $type, 'owner_id' => $ownerId, 'owner_name' => $name, 'code' => $code, 'status' => 1]);
    log_activity('create', 'referral-codes', 'Generated referral code ' . $code);
    set_flash('success', 'Referral code ' . $code . ' created.');
    redirect('/admin/referral-codes');
}
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    db_delete($table, 'id = :id', [':id' => (int) post('id', 0)]);
    set_flash('success', 'Referral code deleted.');
    redirect('/admin/referral-codes');
}

$page_title = 'Referral Codes';
$rows = db_all("SELECT * FROM $table ORDER BY id DESC");
$origin = rtrim(APP_URL, '/');
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head"><div><h1>Referral Codes</h1><span class="muted"><?= count($rows) ?> codes</span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('referrals')) ?>"><?= lucide('bar-chart-3') ?> Tracking</a></div>

<div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('plus') ?> Generate a code</h3></div>
    <form class="admin-form panel-body" method="post" action="<?= e(admin_url('referral-codes')) ?>">
        <?= csrf_field() ?><input type="hidden" name="_do" value="generate">
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Referrer name <span class="req">*</span></label><input class="form-control" name="name" required placeholder="e.g. Priya Sharma"></div>
            <div class="form-group"><label class="form-label">Type</label><select class="form-select" name="owner_type">
                <option value="member">Member</option><option value="volunteer">Volunteer</option><option value="user">Staff/User</option><option value="other">Other</option>
            </select></div>
        </div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Link to email (optional)</label><input class="form-control" type="email" name="email" placeholder="looks up a member/volunteer"></div>
            <div class="form-group"><label class="form-label">Custom code (optional)</label><input class="form-control" name="custom_code" placeholder="auto-generated" style="text-transform:uppercase;"></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('link') ?> Generate</button></div>
    </form>
</div>

<div class="panel"><div class="panel-body">
<?php if ($rows): ?>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Referrer</th><th>Code / Link</th><th>Clicks</th><th>Signups</th><th>Donations</th><th style="text-align:right;">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $link = $origin . '/?ref=' . rawurlencode($r['code']); ?>
        <tr>
            <td><strong><?= e($r['owner_name'] ?: '—') ?></strong><br><small class="text-muted"><?= e(ucfirst($r['owner_type'])) ?></small></td>
            <td><code><?= e($r['code']) ?></code><br>
                <small class="text-muted" style="word-break:break-all;"><?= e($link) ?></small>
                <button type="button" class="icon-btn" title="Copy link" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= e($link) ?>');this.innerHTML='✓';"><?= lucide('copy') ?></button>
            </td>
            <td><?= number_format((int) $r['clicks']) ?></td>
            <td><?= number_format((int) $r['signups']) ?></td>
            <td><?= number_format((int) $r['donations_count']) ?> <small class="text-muted">(<?= e(money((float) $r['donations_amount'], '₹', 0)) ?>)</small></td>
            <td><div class="actions">
                <form method="post" action="<?= e(admin_url('referral-codes')) ?>" data-confirm="Delete this referral code?" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button></form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php else: ?><div class="empty-state"><div class="icon"><?= lucide('link') ?></div>No referral codes yet. Generate one above.</div><?php endif; ?>
</div></div>
<?php include __DIR__ . '/partials/foot.php'; ?>
