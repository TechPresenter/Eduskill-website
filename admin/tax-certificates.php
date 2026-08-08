<?php
/**
 * =============================================================================
 *  Admin — 80G Annual Tax Certificates (read-only picker / generator)
 * =============================================================================
 *  Pick a donor (by email) and a calendar year, preview every completed
 *  donation that will appear on their 80G certificate, then open the printable
 *  certificate (tax-certificate-view) in a new tab. Also surfaces this year's
 *  top donors as one-click certificate shortcuts. Purely read-only — no writes,
 *  no CSRF (GET form).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$sym = donation_symbol(); // ₹

/* -------------------------------------------------------------- INPUTS */
$currentYear = (int) date('Y');
$minYear     = (int) db_value("SELECT MIN(YEAR(COALESCE(donated_at, created_at))) FROM donations WHERE status = 'completed'");
if ($minYear < 2000 || $minYear > $currentYear) {
    $minYear = $currentYear;
}
$years = range($currentYear, $minYear); // newest first

$email = trim((string) get('email', ''));
$year  = (int) get('year', $currentYear);
if (!in_array($year, $years, true)) {
    $year = $currentYear;
}
$searched = $email !== '';

/** Printable-certificate URL for a donor + year. */
$certUrl = static fn (string $em, int $yr): string =>
    admin_url('tax-certificate-view?email=' . urlencode($em) . '&year=' . $yr);

/* -------------------------------------------------------------- LOOKUP */
$rows      = [];
$total     = 0.0;
$donorName = '';
if ($searched) {
    $rows = db_all(
        "SELECT id, donor_name, email, amount, currency, receipt_no, payment_method, donated_at, created_at
           FROM donations
          WHERE status = 'completed'
            AND LOWER(email) = LOWER(:e)
            AND YEAR(COALESCE(donated_at, created_at)) = :y
          ORDER BY COALESCE(donated_at, created_at) ASC",
        [':e' => $email, ':y' => $year]
    );
    foreach ($rows as $r) {
        $total += (float) $r['amount'];
    }
    $donorName = (string) ($rows[0]['donor_name'] ?? '');
}

/* -------------------------------------------------------------- YEAR SNAPSHOT + TOP DONORS */
$yearStats = db_row(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total, COUNT(DISTINCT LOWER(email)) AS donors
       FROM donations
      WHERE status = 'completed'
        AND YEAR(COALESCE(donated_at, created_at)) = :y",
    [':y' => $year]
) ?: ['cnt' => 0, 'total' => 0, 'donors' => 0];

$topDonors = db_all(
    "SELECT MAX(donor_name) AS donor_name, MAX(email) AS email, SUM(amount) AS total, COUNT(*) AS cnt
       FROM donations
      WHERE status = 'completed'
        AND email IS NOT NULL AND email <> ''
        AND YEAR(COALESCE(donated_at, created_at)) = :y
      GROUP BY LOWER(email)
      ORDER BY total DESC
      LIMIT 10",
    [':y' => $year]
);

$page_title = '80G Tax Certificates';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>80G Tax Certificates</h1><span class="muted">Generate a donor's annual 80G exemption certificate</span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('donations')) ?>">← Donations</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('indian-rupee') ?></div><div><div class="stat-value"><?= e(money($yearStats['total'], $sym, 0)) ?></div><div class="stat-label">Donated in <?= (int) $year ?></div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $yearStats['donors'] ?></div><div class="stat-label">Unique donors</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('receipt') ?></div><div><div class="stat-value"><?= (int) $yearStats['cnt'] ?></div><div class="stat-label">Completed donations</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <h3 class="mb-3">Look up a donor</h3>
    <form method="get" action="<?= e(admin_url('tax-certificates')) ?>" class="flex flex-wrap" style="gap:.75rem;align-items:end;">
        <div class="form-group" style="margin:0;flex:1 1 280px;">
            <label class="form-label">Donor email</label>
            <input class="form-control" type="email" name="email" value="<?= e($email) ?>" placeholder="donor@example.com" required autofocus>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Year</label>
            <select class="form-select" name="year">
                <?php foreach ($years as $y): ?>
                    <option value="<?= (int) $y ?>" <?= $year === (int) $y ? 'selected' : '' ?>><?= (int) $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><?= lucide('search') ?> Preview donations</button>
        <?php if ($searched): ?><a class="btn btn-ghost" href="<?= e(admin_url('tax-certificates')) ?>">Clear</a><?php endif; ?>
    </form>
</div></div>

<?php if ($searched): ?>
<div class="panel"><div class="panel-body">
    <div class="flex items-center justify-between mb-2" style="flex-wrap:wrap;gap:.5rem;">
        <h3 class="mb-0">Completed donations · <?= e($donorName ?: $email) ?> · <?= (int) $year ?></h3>
        <?php if ($rows): ?>
            <a class="btn btn-primary" href="<?= e($certUrl($email, $year)) ?>" target="_blank" rel="noopener"><?= lucide('file-badge') ?> Generate 80G certificate</a>
        <?php endif; ?>
    </div>

    <?php if ($rows): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Date</th><th>Receipt No.</th><th>Method</th><th style="text-align:right;">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e(format_date($r['donated_at'] ?: $r['created_at'], 'd M Y')) ?></td>
                <td><?= e($r['receipt_no'] ?: '—') ?></td>
                <td><?= e($r['payment_method'] ?: '—') ?></td>
                <td style="text-align:right;"><?= e(money($r['amount'], $sym, 2)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <th colspan="3" style="text-align:right;">Total donated in <?= (int) $year ?></th>
            <th style="text-align:right;"><?= e(money($total, $sym, 2)) ?></th>
        </tr></tfoot>
    </table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('file-x') ?></div>
            No completed donations for <strong><?= e($email) ?></strong> in <?= (int) $year ?>.
            Check the email spelling or try a different year.</div>
    <?php endif; ?>
</div></div>
<?php endif; ?>

<div class="panel"><div class="panel-body">
    <h3 class="mb-2">Top donors in <?= (int) $year ?></h3>
    <?php if ($topDonors): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Donor</th><th>Email</th><th>Donations</th><th style="text-align:right;">Total</th><th style="text-align:right;">Certificate</th></tr></thead>
        <tbody>
        <?php foreach ($topDonors as $d): $dEmail = (string) $d['email']; ?>
            <tr>
                <td><a href="<?= e(admin_url('tax-certificates?email=' . urlencode($dEmail) . '&year=' . $year)) ?>"><strong><?= e($d['donor_name'] ?: '—') ?></strong></a></td>
                <td><small class="text-muted"><?= e($dEmail) ?></small></td>
                <td><?= (int) $d['cnt'] ?></td>
                <td style="text-align:right;"><?= e(money($d['total'], $sym, 2)) ?></td>
                <td style="text-align:right;">
                    <a class="btn btn-outline btn-sm" href="<?= e($certUrl($dEmail, $year)) ?>" target="_blank" rel="noopener"><?= lucide('file-badge') ?> Generate</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('users') ?></div>No completed donations recorded in <?= (int) $year ?> yet.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
