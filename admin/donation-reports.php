<?php
/**
 * =============================================================================
 *  Admin — Donation Reports  (read-only analytics + CSV export).
 * =============================================================================
 *  A filterable financial report over settled donations. Filters (GET):
 *  from date, to date, campaign_id (campaign_options()) and status. The date
 *  range is applied to donated_at (the settlement date), so only donations
 *  with a settlement timestamp appear. Headline stat cards summarise the
 *  COMPLETED donations in range (total raised, count, average, unique donors).
 *  action=export streams the same filtered rows as a CSV, with spreadsheet
 *  formula-injection neutralised. No create/edit here — donations are created
 *  by the public donate flow and managed in the Donations inbox.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$action   = get('action', 'list');
$STATUSES = ['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed', 'refunded' => 'Refunded'];

/* -------------------------------------------------------------- FILTERS (GET) */
$campaigns = campaign_options();                       // [id => title]

$fromRaw = trim((string) get('from', ''));
$toRaw   = trim((string) get('to', ''));
$fromOk  = $fromRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw);
$toOk    = $toRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw);

$campaignId   = (int) get('campaign_id', 0);
if ($campaignId > 0 && !isset($campaigns[$campaignId])) {
    $campaignId = 0;
}
$statusFilter = (string) get('status', '');
if (!isset($STATUSES[$statusFilter])) {
    $statusFilter = '';
}

// Effective, inclusive datetime bounds. Blank inputs mean "all time".
$fromBound = ($fromOk ? $fromRaw : '2000-01-01') . ' 00:00:00';
$toBound   = ($toOk   ? $toRaw   : date('Y-m-d')) . ' 23:59:59';

// Shared WHERE for the report list + CSV export. A named param is never reused
// within one query (PDO emulation is OFF), so BETWEEN uses :from/:to once each.
$where  = 'd.donated_at BETWEEN :from AND :to';
$params = [':from' => $fromBound, ':to' => $toBound];
if ($campaignId > 0)      { $where .= ' AND d.campaign_id = :cid'; $params[':cid'] = $campaignId; }
if ($statusFilter !== '') { $where .= ' AND d.status = :st';       $params[':st']  = $statusFilter; }

/* -------------------------------------------------------------- CSV EXPORT */
if ($action === 'export') {
    $rows = db_all(
        "SELECT d.*, c.title AS campaign_title
           FROM donations d
           LEFT JOIN campaigns c ON c.id = d.campaign_id
          WHERE $where
          ORDER BY d.donated_at DESC",
        $params
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="donation-report-' . date('Y-m-d') . '.csv"');
    // Neutralise spreadsheet formula injection (=,+,-,@,tab,CR) on export.
    $safe = static function ($v): string {
        $v = (string) $v;
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };
    $out = fopen('php://output', 'w');
    fputcsv($out, ['date', 'receipt_no', 'donor', 'email', 'phone', 'campaign',
        'amount', 'currency', 'gateway', 'method', 'status', 'transaction_id']);
    foreach ($rows as $r) {
        $anon  = !empty($r['is_anonymous']);
        $donor = $anon ? 'Anonymous' : (string) $r['donor_name'];
        fputcsv($out, array_map($safe, [
            format_date($r['donated_at'], 'Y-m-d H:i:s'),
            $r['receipt_no'] ?? '',
            $donor,
            $anon ? '' : ($r['email'] ?? ''),
            $anon ? '' : ($r['phone'] ?? ''),
            $r['campaign_title'] ?? '',
            number_format((float) $r['amount'], 2, '.', ''),
            $r['currency'] ?? 'INR',
            $r['gateway'] ?? '',
            $r['payment_method'] ?? '',
            $r['status'],
            $r['transaction_id'] ?? '',
        ]));
    }
    fclose($out);
    log_activity('export', 'donations', 'Exported donation report CSV');
    exit;
}

/* -------------------------------------------------------------- STATS (completed in range) */
$statWhere  = "donated_at BETWEEN :from AND :to AND status = 'completed'";
$statParams = [':from' => $fromBound, ':to' => $toBound];
if ($campaignId > 0) { $statWhere .= ' AND campaign_id = :cid'; $statParams[':cid'] = $campaignId; }

$agg = db_row(
    "SELECT COALESCE(SUM(amount), 0) AS total,
            COUNT(*) AS cnt,
            COUNT(DISTINCT COALESCE(NULLIF(email, ''), donor_name)) AS donors
       FROM donations WHERE $statWhere",
    $statParams
);
$totalRaised = (float) ($agg['total'] ?? 0);
$countPaid   = (int) ($agg['cnt'] ?? 0);
$uniqDonors  = (int) ($agg['donors'] ?? 0);
$avgGift     = $countPaid > 0 ? $totalRaised / $countPaid : 0.0;

/* -------------------------------------------------------------- LIST */
$p = paginate(
    "SELECT d.*, c.title AS campaign_title
       FROM donations d
       LEFT JOIN campaigns c ON c.id = d.campaign_id
      WHERE $where
      ORDER BY d.donated_at DESC",
    $params,
    30
);

$hasFilters = $fromOk || $toOk || $campaignId > 0 || $statusFilter !== '';
$sym        = donation_symbol();

// Query string that carries the current filters (for the CSV export link).
$filterQs = array_filter([
    'from'        => $fromOk ? $fromRaw : '',
    'to'          => $toOk ? $toRaw : '',
    'campaign_id' => $campaignId > 0 ? (string) $campaignId : '',
    'status'      => $statusFilter,
], static fn($v) => $v !== '');
$exportQs = http_build_query(array_merge($filterQs, ['action' => 'export']));

$page_title = 'Donation Reports';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Donation Reports</h1><span class="muted"><?= (int) $p['total'] ?> donations in range · <?= e(money($totalRaised, $sym, 0)) ?> raised</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('donation-reports?' . $exportQs)) ?>"><?= lucide('download') ?> Export CSV</a>
</div>

<!-- Stat cards: completed donations within the selected range -->
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('coins') ?></div><div><div class="stat-value"><?= e(money($totalRaised, $sym, 0)) ?></div><div class="stat-label">Total raised</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('heart-handshake') ?></div><div><div class="stat-value"><?= number_format($countPaid) ?></div><div class="stat-label">Completed donations</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('calculator') ?></div><div><div class="stat-value"><?= e(money($avgGift, $sym, 0)) ?></div><div class="stat-label">Average gift</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('users') ?></div><div><div class="stat-value"><?= number_format($uniqDonors) ?></div><div class="stat-label">Unique donors</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <!-- Filters -->
    <form class="data-toolbar" method="get" action="<?= e(admin_url('donation-reports')) ?>" style="flex-wrap:wrap;gap:.75rem;align-items:end;">
        <div class="form-group" style="margin:0;"><label class="form-label">From</label>
            <input class="form-control" type="date" name="from" value="<?= e($fromOk ? $fromRaw : '') ?>"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">To</label>
            <input class="form-control" type="date" name="to" value="<?= e($toOk ? $toRaw : '') ?>"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Campaign</label>
            <select class="form-select" name="campaign_id">
                <option value="">All campaigns</option>
                <?php foreach ($campaigns as $cid => $ctitle): ?><option value="<?= (int) $cid ?>" <?= $campaignId === (int) $cid ? 'selected' : '' ?>><?= e($ctitle) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="">All statuses</option>
                <?php foreach ($STATUSES as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select></div>
        <button class="btn btn-primary" type="submit"><?= lucide('filter') ?> Apply</button>
        <?php if ($hasFilters): ?><a class="btn btn-ghost" href="<?= e(admin_url('donation-reports')) ?>">Clear</a><?php endif; ?>
    </form>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr>
            <th>Date</th><th>Donor</th><th>Campaign</th>
            <th style="text-align:right;">Amount</th><th>Gateway / method</th><th>Status</th>
            <th style="text-align:right;">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $anon    = !empty($r['is_anonymous']);
            $gateway = $r['gateway'] ? ucfirst((string) $r['gateway']) : '';
            $method  = (string) ($r['payment_method'] ?? '');
        ?>
            <tr>
                <td><?= e(format_date($r['donated_at'], 'd M Y')) ?><br><small class="text-muted"><?= e(format_date($r['donated_at'], 'h:i A')) ?></small></td>
                <td>
                    <?php if ($anon): ?><span class="pill pill-gray">Anonymous</span>
                    <?php else: ?><strong><?= e($r['donor_name']) ?></strong><?php if (!empty($r['email'])): ?><br><small class="text-muted"><?= e($r['email']) ?></small><?php endif; ?><?php endif; ?>
                </td>
                <td><?= !empty($r['campaign_title']) ? e($r['campaign_title']) : '<span class="text-muted">General</span>' ?></td>
                <td style="text-align:right;"><strong><?= e(money($r['amount'], donation_symbol((string) ($r['currency'] ?? 'INR')), 2)) ?></strong></td>
                <td><?= $gateway !== '' ? e($gateway) : ($method !== '' ? e($method) : '—') ?><?php if ($gateway !== '' && $method !== ''): ?><br><small class="text-muted"><?= e($method) ?></small><?php endif; ?></td>
                <td><span class="pill <?= e(donation_status_pill($r['status'])) ?>"><?= e($STATUSES[$r['status']] ?? ucfirst((string) $r['status'])) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('donations?action=view&id=' . (int) $r['id'])) ?>" title="View donation"><?= lucide('eye') ?></a>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('chart-column') ?></div>No donations<?= $hasFilters ? ' match these filters' : ' in this range' ?>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
