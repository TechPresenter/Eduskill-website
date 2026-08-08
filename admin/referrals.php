<?php
/**
 * Admin — Referral Tracking. Leaderboard of referrers (clicks → signups →
 * donations → rewards) plus a live feed of recent conversions.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$sum = [
    'codes'     => (int) db_value("SELECT COUNT(*) FROM referral_codes"),
    'clicks'    => (int) db_value("SELECT COALESCE(SUM(clicks),0) FROM referral_codes"),
    'signups'   => (int) db_value("SELECT COALESCE(SUM(signups),0) FROM referral_codes"),
    'donations' => (float) db_value("SELECT COALESCE(SUM(donations_amount),0) FROM referral_codes"),
    'rewards'   => (float) db_value("SELECT COALESCE(SUM(reward_total),0) FROM referral_codes"),
];

$board = db_all(
    "SELECT * FROM referral_codes ORDER BY (signups + donations_count) DESC, clicks DESC LIMIT 20"
);
$recent = db_all(
    "SELECT rc.*, c.code, c.owner_name FROM referral_conversions rc
     JOIN referral_codes c ON c.id = rc.code_id
     ORDER BY rc.id DESC LIMIT 20"
);

$page_title = 'Referral Tracking';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head"><div><h1>Referral Tracking</h1><span class="muted">Referral &amp; Coupons / Tracking</span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('referral-codes')) ?>"><?= lucide('link') ?> Codes</a></div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('link') ?></div><div><div class="stat-value"><?= number_format($sum['codes']) ?></div><div class="stat-label">Referral codes</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('mouse-pointer-click') ?></div><div><div class="stat-value"><?= number_format($sum['clicks']) ?></div><div class="stat-label">Clicks</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('user-plus') ?></div><div><div class="stat-value"><?= number_format($sum['signups']) ?></div><div class="stat-label">Signups</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('heart-handshake') ?></div><div><div class="stat-value"><?= e(money($sum['donations'], '₹', 0)) ?></div><div class="stat-label">Referred donations</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('gift') ?></div><div><div class="stat-value"><?= e(money($sum['rewards'], '₹', 0)) ?></div><div class="stat-label">Rewards owed</div></div></div>
</div>

<div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('trophy') ?> Top referrers</h3></div><div class="panel-body">
    <?php if ($board): ?>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>#</th><th>Referrer</th><th>Code</th><th>Clicks</th><th>Signups</th><th>Donations</th><th>Conv.%</th><th>Rewards</th></tr></thead><tbody>
    <?php foreach ($board as $i => $r):
        $conv = (int) $r['clicks'] > 0 ? round(((int) $r['signups'] + (int) $r['donations_count']) / (int) $r['clicks'] * 100) : 0; ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><strong><?= e($r['owner_name'] ?: '—') ?></strong> <small class="text-muted"><?= e($r['owner_type']) ?></small></td>
            <td><code><?= e($r['code']) ?></code></td>
            <td><?= number_format((int) $r['clicks']) ?></td>
            <td><?= number_format((int) $r['signups']) ?></td>
            <td><?= number_format((int) $r['donations_count']) ?> <small class="text-muted">(<?= e(money((float) $r['donations_amount'], '₹', 0)) ?>)</small></td>
            <td><span class="pill <?= $conv >= 10 ? 'pill-green' : 'pill-gray' ?>"><?= $conv ?>%</span></td>
            <td><?= e(money((float) $r['reward_total'], '₹', 0)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('link') ?></div>No referral activity yet. <a href="<?= e(admin_url('referral-codes')) ?>">Generate codes</a> and share the links.</div><?php endif; ?>
</div></div>

<div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('history') ?> Recent conversions</h3></div><div class="panel-body">
    <?php if ($recent): ?>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Referrer</th><th>Code</th><th>Type</th><th>Who</th><th>Amount</th><th>When</th></tr></thead><tbody>
    <?php foreach ($recent as $r): ?>
        <tr><td><?= e($r['owner_name'] ?: '—') ?></td><td><code><?= e($r['code']) ?></code></td>
            <td><span class="pill pill-blue"><?= e($r['type']) ?></span></td>
            <td><small><?= e($r['ref_name'] ?: ($r['ref_email'] ?: '—')) ?></small></td>
            <td><?= (float) $r['amount'] > 0 ? e(money((float) $r['amount'], '₹', 0)) : '—' ?></td>
            <td><small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('users') ?></div>No conversions recorded yet.</div><?php endif; ?>
</div></div>
<?php include __DIR__ . '/partials/foot.php'; ?>
