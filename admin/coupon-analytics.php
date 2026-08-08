<?php
/**
 * Admin — Coupon Analytics: usage, savings given, popular codes, expiry.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$totRedemptions = (int) db_value("SELECT COUNT(*) FROM coupon_redemptions");
$totSavings     = (float) db_value("SELECT COALESCE(SUM(discount),0) FROM coupon_redemptions");
$activeCoupons  = (int) db_value("SELECT COUNT(*) FROM coupons WHERE status = 1 AND (expires_at IS NULL OR expires_at >= NOW())");
$monthSavings   = (float) db_value("SELECT COALESCE(SUM(discount),0) FROM coupon_redemptions WHERE created_at >= (NOW() - INTERVAL 30 DAY)");

$popular = db_all(
    "SELECT c.code, c.discount_type, c.value, c.used_count, COALESCE(SUM(r.discount),0) AS savings, COUNT(r.id) AS redemptions
     FROM coupons c LEFT JOIN coupon_redemptions r ON r.coupon_id = c.id
     GROUP BY c.id, c.code, c.discount_type, c.value, c.used_count
     ORDER BY redemptions DESC, c.used_count DESC LIMIT 10"
);
$expiring = db_all("SELECT * FROM coupons WHERE status = 1 AND expires_at IS NOT NULL AND expires_at BETWEEN NOW() AND (NOW() + INTERVAL 30 DAY) ORDER BY expires_at ASC");
$recent   = db_all("SELECT * FROM coupon_redemptions ORDER BY id DESC LIMIT 15");

$page_title = 'Coupon Analytics';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head"><div><h1>Coupon Analytics</h1><span class="muted">Referral &amp; Coupons / Analytics</span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('coupons')) ?>">← Coupons</a></div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('ticket-check') ?></div><div><div class="stat-value"><?= number_format($totRedemptions) ?></div><div class="stat-label">Redemptions</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('piggy-bank') ?></div><div><div class="stat-value"><?= e(money($totSavings, '₹', 0)) ?></div><div class="stat-label">Total Savings Given</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('calendar') ?></div><div><div class="stat-value"><?= e(money($monthSavings, '₹', 0)) ?></div><div class="stat-label">Savings (30 days)</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('ticket') ?></div><div><div class="stat-value"><?= number_format($activeCoupons) ?></div><div class="stat-label">Active Coupons</div></div></div>
</div>

<div class="grid grid-2" style="align-items:start;gap:1.5rem;">
    <div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('trophy') ?> Most popular codes</h3></div><div class="panel-body">
        <?php if ($popular && (int) $popular[0]['redemptions'] > 0): ?>
        <div class="table-wrap"><table class="admin-table"><thead><tr><th>Code</th><th>Used</th><th>Savings</th></tr></thead><tbody>
        <?php foreach ($popular as $r): if ((int) $r['redemptions'] === 0) continue; ?>
            <tr><td><code><?= e($r['code']) ?></code></td><td><?= (int) $r['redemptions'] ?></td><td><?= e(money((float) $r['savings'], '₹', 0)) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('ticket') ?></div>No redemptions yet.</div><?php endif; ?>
    </div></div>

    <div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('clock') ?> Expiring soon (30 days)</h3></div><div class="panel-body">
        <?php if ($expiring): ?>
        <div class="table-wrap"><table class="admin-table"><thead><tr><th>Code</th><th>Expires</th></tr></thead><tbody>
        <?php foreach ($expiring as $r): ?><tr><td><code><?= e($r['code']) ?></code></td><td><span class="pill pill-amber"><?= e(format_date($r['expires_at'], 'd M Y')) ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('calendar-check') ?></div>No coupons expiring soon.</div><?php endif; ?>
    </div></div>
</div>

<div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('history') ?> Recent redemptions</h3></div><div class="panel-body">
    <?php if ($recent): ?>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Code</th><th>Context</th><th>Email</th><th>Before</th><th>Discount</th><th>After</th><th>When</th></tr></thead><tbody>
    <?php foreach ($recent as $r): ?>
        <tr><td><code><?= e($r['code']) ?></code></td><td><span class="pill pill-blue"><?= e($r['context']) ?></span></td>
            <td><small><?= e($r['user_email'] ?: '—') ?></small></td><td><?= e(money((float) $r['amount_before'], '₹', 0)) ?></td>
            <td><strong>−<?= e(money((float) $r['discount'], '₹', 0)) ?></strong></td><td><?= e(money((float) $r['amount_after'], '₹', 0)) ?></td>
            <td><small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('ticket-check') ?></div>No coupon redemptions yet.</div><?php endif; ?>
</div></div>
<?php include __DIR__ . '/partials/foot.php'; ?>
