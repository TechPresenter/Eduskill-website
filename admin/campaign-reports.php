<?php
/**
 * =============================================================================
 *  Admin — Campaign Report (read-only fundraising analytics)
 * =============================================================================
 *  ?campaign=<id>. Progress stat cards (raised / goal % / donors / remaining),
 *  completed-donation totals (sum, count, average), a 30-day daily-collection
 *  trend line chart, top-10 contributors and the most recent donations. No
 *  writes — a pure analytics view scoped to a single campaign.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$campaignId = (int) get('campaign', 0);
$campaign   = find('campaigns', $campaignId);
if (!$campaign) {
    set_flash('error', 'Campaign not found.');
    redirect(admin_url('campaigns'));
}

$q    = 'campaign=' . $campaignId;
$prog = campaign_progress($campaign);

/* Currency symbol — derived from the campaign's completed donations. */
$currency = (string) (db_value(
    "SELECT currency FROM donations WHERE campaign_id = :c AND status = 'completed' ORDER BY id DESC LIMIT 1",
    [':c' => $campaignId]
) ?: 'INR');
$sym = donation_symbol($currency);

/* Completed-donation aggregates: total, count, average. */
$agg = db_row(
    "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS cnt, COALESCE(AVG(amount), 0) AS avg_amount
       FROM donations WHERE campaign_id = :c AND status = 'completed'",
    [':c' => $campaignId]
) ?: ['total' => 0, 'cnt' => 0, 'avg_amount' => 0];
$total = (float) $agg['total'];
$count = (int) $agg['cnt'];
$avg   = (float) $agg['avg_amount'];

/* Top 10 contributors (anonymous donations shown as "Anonymous"). */
$contributors = db_all(
    "SELECT donor_name, email, MAX(is_anonymous) AS is_anonymous, SUM(amount) AS total, COUNT(*) AS cnt
       FROM donations
      WHERE campaign_id = :c AND status = 'completed'
      GROUP BY donor_name, email
      ORDER BY total DESC
      LIMIT 10",
    [':c' => $campaignId]
);

/* Daily collection trend — last 30 days, gaps filled to 0 for a continuous line. */
$trendRows = db_all(
    "SELECT DATE(donated_at) d, SUM(amount) t
       FROM donations
      WHERE campaign_id = :c AND status = 'completed'
        AND donated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      GROUP BY d
      ORDER BY d",
    [':c' => $campaignId]
);
$trendMap = [];
foreach ($trendRows as $tr) {
    $trendMap[(string) $tr['d']] = (float) $tr['t'];
}
$trendLabels = [];
$trendValues = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('d M', strtotime($day));
    $trendValues[] = round($trendMap[$day] ?? 0, 2);
}

/* Most recent donations (any status) for this campaign. */
$recent = db_all(
    "SELECT id, donor_name, email, amount, currency, is_anonymous, status, donated_at, created_at
       FROM donations WHERE campaign_id = :c ORDER BY id DESC LIMIT 12",
    [':c' => $campaignId]
);

$page_title  = 'Report · ' . $campaign['title'];
$load_charts = true;
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1><?= e($campaign['title']) ?></h1>
        <span class="muted">Campaigns / Report
            <span class="pill <?= e(campaign_status_pill($campaign['status'])) ?>"><?= e(ucfirst($campaign['status'])) ?></span></span>
    </div>
    <div class="flex" style="gap:.5rem;">
        <a class="btn btn-outline" href="<?= e(admin_url('campaigns?action=edit&id=' . $campaignId)) ?>"><?= lucide('pencil') ?> Edit</a>
        <a class="btn btn-secondary" href="<?= e(admin_url('campaigns')) ?>">← All campaigns</a>
    </div>
</div>

<!-- Progress stat cards -->
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('indian-rupee') ?></div><div><div class="stat-value"><?= e(money($prog['raised'], $sym, 0)) ?></div><div class="stat-label">Raised</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('target') ?></div><div><div class="stat-value"><?= e($prog['percent']) ?>%</div><div class="stat-label">of <?= e(money($prog['goal'], $sym, 0)) ?> goal</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $prog['donors'] ?></div><div class="stat-label">Donors</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('wallet') ?></div><div><div class="stat-value"><?= e(money($prog['remaining'], $sym, 0)) ?></div><div class="stat-label">Remaining</div></div></div>
</div>

<div class="grid-2" style="align-items:start;gap:1.25rem;">
    <div>
        <!-- Progress + completed-donation summary -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-2">Progress</h3>
            <div class="progress" style="height:10px;background:var(--surface-2,#e5e7eb);border-radius:999px;overflow:hidden;">
                <div class="progress-bar" style="width:<?= (int) $prog['percent'] ?>%;height:100%;background:#58A42F;"></div>
            </div>
            <small class="text-muted"><?= e(money($prog['raised'], $sym, 0)) ?> of <?= e(money($prog['goal'], $sym, 0)) ?> (<?= e($prog['percent']) ?>%)</small>
            <div class="table-wrap" style="margin-top:1rem;"><table class="admin-table"><tbody>
                <tr><th style="width:180px;">Total collected</th><td><?= e(money($total, $sym, 2)) ?></td></tr>
                <tr><th>Completed donations</th><td><?= (int) $count ?></td></tr>
                <tr><th>Average donation</th><td><?= e(money($avg, $sym, 2)) ?></td></tr>
            </tbody></table></div>
        </div></div>

        <!-- Top contributors -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-2">Top contributors</h3>
            <?php if ($contributors): ?>
            <div class="table-wrap"><table class="admin-table">
                <thead><tr><th>Donor</th><th>Gifts</th><th style="text-align:right;">Total</th></tr></thead><tbody>
                <?php foreach ($contributors as $c): ?>
                    <tr>
                        <td><?php if ((int) $c['is_anonymous'] === 1): ?><span class="text-muted">Anonymous</span><?php else: ?>
                            <strong><?= e($c['donor_name']) ?></strong><?= $c['email'] ? '<br><small class="text-muted">' . e($c['email']) . '</small>' : '' ?>
                        <?php endif; ?></td>
                        <td><?= (int) $c['cnt'] ?></td>
                        <td style="text-align:right;"><?= e(money($c['total'], $sym, 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php else: ?><p class="text-muted">No completed donations yet.</p><?php endif; ?>
        </div></div>
    </div>

    <div>
        <!-- Daily collection trend -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-2">Daily collections (30 days)</h3>
            <?php if ($count > 0): ?>
                <canvas id="trendChart" height="200"></canvas>
            <?php else: ?><p class="text-muted">No completed donations in the last 30 days.</p><?php endif; ?>
        </div></div>

        <!-- Recent donations -->
        <div class="panel"><div class="panel-body">
            <div class="flex items-center justify-between mb-2"><h3 class="mb-0">Recent donations</h3><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('donations?' . $q)) ?>">All donations</a></div>
            <?php if ($recent): ?>
            <div class="table-wrap"><table class="admin-table">
                <thead><tr><th>Date</th><th>Donor</th><th class="num" style="text-align:right;">Amount</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?= e(format_date($r['donated_at'] ?: $r['created_at'], 'd M Y')) ?></td>
                        <td><?= (int) $r['is_anonymous'] === 1 ? '<span class="text-muted">Anonymous</span>' : e($r['donor_name']) ?></td>
                        <td style="text-align:right;"><?= e(money($r['amount'], donation_symbol($r['currency'] ?? 'INR'), 2)) ?></td>
                        <td><span class="pill <?= e(donation_status_pill($r['status'])) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php else: ?><p class="text-muted">No donations recorded for this campaign.</p><?php endif; ?>
        </div></div>
    </div>
</div>

<?php if ($count > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Chart) return;
    var el = document.getElementById('trendChart');
    if (!el) return;
    var sym = <?= json_encode($sym) ?>;
    new Chart(el, {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [{
                label: 'Collected',
                data: <?= json_encode($trendValues) ?>,
                borderColor: '#58A42F',
                backgroundColor: 'rgba(88,164,47,.12)',
                fill: true,
                tension: .35,
                pointRadius: 2,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: function (v) { return sym + v; } } }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/partials/foot.php'; ?>
