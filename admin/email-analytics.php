<?php
/**
 * =============================================================================
 *  Admin — Email Reports & Analytics.
 * =============================================================================
 *  Read-only reporting surface for the Email Center. Pulls every figure from
 *  the DB via email_center helpers (no fabricated numbers):
 *    - KPI recap  ......... ec_dashboard_stats()
 *    - Engagement trend ... ec_dashboard_series(30)  (Chart.js line)
 *    - Breakdowns ......... ec_breakdown(device|browser|os|country) (doughnuts)
 *    - Campaign compare ... email_campaigns with computed open/click rates
 *    - CSV export ......... ?export=campaigns (streams the comparison table)
 *  Analytics fills in as real opens/clicks arrive; empty states everywhere else.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/email_center.php';

/* -----------------------------------------------------------------------------
 |  CSV EXPORT — streams the campaign comparison. Must run and exit BEFORE any
 |  layout output (head.php), so it stays a clean attachment download.
 * ---------------------------------------------------------------------------*/
if (get('export') === 'campaigns') {
    $rows = db_all("SELECT name, subject, status, total, sent_count, open_count,
                           click_count, fail_count, created_at
                    FROM email_campaigns ORDER BY id DESC");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="email-campaign-report-' . date('Ymd-His') . '.csv"');
    // Neutralize CSV/formula injection on admin-set campaign name/subject.
    $safe = static fn($v): string => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Campaign', 'Subject', 'Status', 'Recipients', 'Sent',
                   'Delivered', 'Opens', 'Open Rate %', 'Clicks', 'Click Rate %',
                   'Failed', 'Created']);
    foreach ($rows as $r) {
        $sent      = (int) $r['sent_count'];
        $failed    = (int) $r['fail_count'];
        $delivered = max(0, $sent - $failed);
        $openRate  = $sent > 0 ? round((int) $r['open_count']  / $sent * 100, 1) : 0.0;
        $clickRate = $sent > 0 ? round((int) $r['click_count'] / $sent * 100, 1) : 0.0;
        fputcsv($out, [
            $safe($r['name']),
            $safe($r['subject']),
            ucfirst((string) $r['status']),
            (int) $r['total'],
            $sent,
            $delivered,
            (int) $r['open_count'],
            $openRate,
            (int) $r['click_count'],
            $clickRate,
            $failed,
            $safe($r['created_at']),
        ]);
    }
    fclose($out);
    exit;
}

/* -----------------------------------------------------------------------------
 |  DATA
 * ---------------------------------------------------------------------------*/
$stats  = ec_dashboard_stats();
$series = ec_dashboard_series(30);

$breakdowns = [
    'device'  => ['Device',  'monitor-smartphone', ec_breakdown('device')],
    'browser' => ['Browser', 'globe',              ec_breakdown('browser')],
    'os'      => ['Operating System', 'cpu',        ec_breakdown('os')],
    'country' => ['Country', 'map-pin',            ec_breakdown('country')],
];

$campaigns = db_all("SELECT id, name, subject, status, total, sent_count,
                            open_count, click_count, fail_count, created_at
                     FROM email_campaigns ORDER BY id DESC");

/* Headline rate cards + raw-count tiles (all straight from ec_dashboard_stats). */
$rateCards = [
    ['Delivery rate', $stats['deliver_rate'], $stats['delivered'], 'delivered',    'mail-check',           '#58A42F'],
    ['Open rate',     $stats['open_rate'],    $stats['opened'],    'opened',       'mail-open',            '#8b5cf6'],
    ['Click rate',    $stats['click_rate'],   $stats['clicked'],   'clicked',      'mouse-pointer-click',  '#084881'],
    ['Bounce rate',   $stats['bounce_rate'],  $stats['bounced'],   'bounced',      'undo-2',               '#E67B1D'],
];
$rawTiles = [
    ['Sent',         $stats['sent'],         'send',         'g-blue'],
    ['Delivered',    $stats['delivered'],    'mail-check',   'g-green'],
    ['Opened',       $stats['opened'],       'mail-open',    'g-violet'],
    ['Clicked',      $stats['clicked'],      'mouse-pointer-click', 'g-cyan'],
    ['Bounced',      $stats['bounced'],      'undo-2',       'g-amber'],
    ['Failed',       $stats['failed'],       'x-circle',     'g-red'],
    ['Spam',         $stats['spam'],         'shield-alert', 'g-orange'],
    ['Unsubscribed', $stats['unsubscribed'], 'user-minus',   'g-slate'],
];

/* Doughnut palette (cycled per slice). */
$donutColors = ['#6366f1', '#084881', '#58A42F', '#E67B1D', '#ef4444', '#8b5cf6',
                '#ec4899', '#14b8a6', '#E67B1D', '#084881', '#84cc16', '#64748b'];

$hasSeriesData = false;
foreach ($series as $s) { if ($s['sent'] || $s['opened'] || $s['clicked']) { $hasSeriesData = true; break; } }

$page_title  = 'Email Analytics';
$load_charts = true;
include __DIR__ . '/partials/head.php';
?>
<div class="ea-wrap adm-rise">

  <!-- Toolbar -->
  <div class="ea-toolbar">
    <div class="ea-toolbar-nav">
      <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('email-dashboard')) ?>"><?= lucide('layout-dashboard') ?> Dashboard</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('email-campaigns')) ?>"><?= lucide('megaphone') ?> Campaigns</a>
    </div>
    <a class="btn btn-primary btn-sm" href="<?= e(admin_url('email-analytics?export=campaigns')) ?>"><?= lucide('download') ?> Export CSV</a>
  </div>

  <!-- KPI recap: headline rate cards -->
  <div class="dash-section-head"><h2><?= lucide('gauge') ?> Performance Recap</h2><span class="muted">Across campaigns &amp; your mailbox</span></div>
  <div class="ea-rates">
    <?php foreach ($rateCards as [$label, $rate, $count, $sub, $icon, $color]): ?>
      <div class="ea-rate-card">
        <div class="ea-rate-top">
          <span class="ea-rate-ic" style="color:<?= e($color) ?>;background:<?= e($color) ?>1a;"><?= lucide($icon) ?></span>
          <span class="ea-rate-num"><?= e((string) $rate) ?><small>%</small></span>
        </div>
        <div class="ea-rate-lbl"><?= e($label) ?></div>
        <div class="progress"><div class="progress-bar" style="width:<?= e((string) min(100, (float) $rate)) ?>%;background:<?= e($color) ?>;"></div></div>
        <div class="ea-rate-sub"><strong><?= e(number_format((float) $count)) ?></strong> <?= e($sub) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Raw count tiles -->
  <div class="ea-tiles">
    <?php foreach ($rawTiles as [$label, $val, $icon, $grad]): ?>
      <div class="ea-tile <?= e($grad) ?>">
        <div class="ea-tile-ic"><?= lucide($icon) ?></div>
        <div class="ea-tile-val" data-count="<?= (int) $val ?>"><?= e(number_format((float) $val)) ?></div>
        <div class="ea-tile-lbl"><?= e($label) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Engagement trend -->
  <div class="dash-section-head"><h2><?= lucide('activity') ?> Engagement Trend</h2><span class="muted">Sends, opens &amp; clicks · last 30 days</span></div>
  <div class="panel">
    <div class="panel-head">
      <h3 class="panel-title"><?= lucide('trending-up') ?> Daily Activity</h3>
      <div class="ea-legend-mini">
        <span><i style="background:#063566"></i>Sent</span>
        <span><i style="background:#8b5cf6"></i>Opened</span>
        <span><i style="background:#084881"></i>Clicked</span>
      </div>
    </div>
    <div class="panel-body">
      <?php if ($hasSeriesData): ?>
        <canvas id="eaTrend" height="110"></canvas>
      <?php else: ?>
        <div class="empty-state">
          <div class="icon"><?= lucide('line-chart') ?></div>
          No engagement recorded in the last 30 days yet. The trend line fills in automatically as sends, opens and clicks are tracked.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Breakdown doughnuts -->
  <div class="dash-section-head"><h2><?= lucide('pie-chart') ?> Audience Breakdown</h2><span class="muted">Who is opening &amp; clicking</span></div>
  <div class="ea-donut-grid">
    <?php foreach ($breakdowns as $dim => [$label, $icon, $rows]): ?>
      <?php $total = array_sum(array_map(static fn($r) => (int) $r['n'], $rows)); ?>
      <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide($icon) ?> <?= e($label) ?></h3></div>
        <div class="panel-body">
          <?php if ($rows): ?>
            <div class="ea-donut-body">
              <div class="ea-donut-canvas">
                <canvas id="eaDonut-<?= e($dim) ?>"
                        data-labels='<?= e(json_encode(array_map(static fn($r) => $r['k'], $rows))) ?>'
                        data-values='<?= e(json_encode(array_map(static fn($r) => (int) $r['n'], $rows))) ?>'></canvas>
              </div>
              <ul class="ea-pills">
                <?php foreach (array_slice($rows, 0, 6) as $i => $r): ?>
                  <?php $pct = $total > 0 ? round((int) $r['n'] / $total * 100) : 0; ?>
                  <li>
                    <span class="ea-pill-dot" style="background:<?= e($donutColors[$i % count($donutColors)]) ?>;"></span>
                    <span class="ea-pill-k"><?= e((string) $r['k']) ?></span>
                    <span class="ea-pill-n"><?= e(number_format((int) $r['n'])) ?> <small><?= e((string) $pct) ?>%</small></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <div class="icon"><?= lucide($icon) ?></div>
              No <?= e(strtolower($label)) ?> data yet — this chart populates as real opens &amp; clicks arrive.
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Campaign comparison -->
  <div class="dash-section-head"><h2><?= lucide('bar-chart-3') ?> Campaign Comparison</h2><span class="muted"><?= e(number_format(count($campaigns))) ?> campaign<?= count($campaigns) === 1 ? '' : 's' ?> · open &amp; click rates as % of sent</span></div>
  <div class="panel">
    <div class="panel-head">
      <h3 class="panel-title"><?= lucide('table') ?> All Campaigns</h3>
      <a class="btn btn-outline btn-sm" href="<?= e(admin_url('email-analytics?export=campaigns')) ?>"><?= lucide('download') ?> Export CSV</a>
    </div>
    <div class="panel-body">
      <?php if ($campaigns): ?>
        <div class="table-wrap">
          <table class="admin-table ea-compare" id="eaCompare">
            <thead>
              <tr>
                <th data-sort="text">Campaign <?= lucide('chevrons-up-down') ?></th>
                <th>Status</th>
                <th class="ea-num" data-sort="num">Sent <?= lucide('chevrons-up-down') ?></th>
                <th class="ea-num" data-sort="num">Delivered <?= lucide('chevrons-up-down') ?></th>
                <th class="ea-num" data-sort="num">Opens <?= lucide('chevrons-up-down') ?></th>
                <th class="ea-num" data-sort="num">Open Rate <?= lucide('chevrons-up-down') ?></th>
                <th class="ea-num" data-sort="num">Clicks <?= lucide('chevrons-up-down') ?></th>
                <th class="ea-num" data-sort="num">Click Rate <?= lucide('chevrons-up-down') ?></th>
                <th class="ea-num" data-sort="num">Failed <?= lucide('chevrons-up-down') ?></th>
                <th class="ea-num" data-sort="num">Created <?= lucide('chevrons-up-down') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($campaigns as $c): ?>
                <?php
                  $sent      = (int) $c['sent_count'];
                  $failed    = (int) $c['fail_count'];
                  $delivered = max(0, $sent - $failed);
                  $openRate  = $sent > 0 ? round((int) $c['open_count']  / $sent * 100, 1) : 0.0;
                  $clickRate = $sent > 0 ? round((int) $c['click_count'] / $sent * 100, 1) : 0.0;
                  $statusPill = match ($c['status']) {
                      'sent'    => 'green',
                      'sending' => 'blue',
                      'scheduled' => 'amber',
                      'draft'   => 'slate',
                      default   => 'slate',
                  };
                ?>
                <tr>
                  <td data-v="<?= e(strtolower($c['name'])) ?>">
                    <a href="<?= e(admin_url('email-campaigns?action=report&id=' . (int) $c['id'])) ?>">
                      <strong><?= e($c['name']) ?></strong>
                    </a>
                    <span class="ea-subj muted"><?= e($c['subject'] ?: '(no subject)') ?></span>
                  </td>
                  <td><span class="pill pill-<?= e($statusPill) ?>"><?= e(ucfirst((string) $c['status'])) ?></span></td>
                  <td class="ea-num" data-v="<?= $sent ?>"><?= e(number_format($sent)) ?></td>
                  <td class="ea-num" data-v="<?= $delivered ?>"><?= e(number_format($delivered)) ?></td>
                  <td class="ea-num" data-v="<?= (int) $c['open_count'] ?>"><?= e(number_format((int) $c['open_count'])) ?></td>
                  <td class="ea-num" data-v="<?= $openRate ?>">
                    <span class="ea-rate-chip <?= $openRate >= 20 ? 'is-good' : ($openRate > 0 ? 'is-mid' : 'is-zero') ?>"><?= e((string) $openRate) ?>%</span>
                  </td>
                  <td class="ea-num" data-v="<?= (int) $c['click_count'] ?>"><?= e(number_format((int) $c['click_count'])) ?></td>
                  <td class="ea-num" data-v="<?= $clickRate ?>">
                    <span class="ea-rate-chip <?= $clickRate >= 3 ? 'is-good' : ($clickRate > 0 ? 'is-mid' : 'is-zero') ?>"><?= e((string) $clickRate) ?>%</span>
                  </td>
                  <td class="ea-num" data-v="<?= $failed ?>"><?= $failed > 0 ? '<span class="pill pill-red">' . e(number_format($failed)) . '</span>' : '<span class="muted">0</span>' ?></td>
                  <td class="ea-num" data-v="<?= e((string) strtotime((string) $c['created_at'])) ?>"><span class="muted"><?= e(date('d M Y', strtotime((string) $c['created_at']))) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <div class="icon"><?= lucide('megaphone') ?></div>
          No campaigns to compare yet. <a href="<?= e(admin_url('email-campaigns?action=create')) ?>">Create your first campaign</a> to start tracking open and click rates here.
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<style>
.ea-wrap{--ea-gap:16px;}
.ea-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:8px;}
.ea-toolbar-nav{display:flex;gap:6px;flex-wrap:wrap;}
/* Rate cards */
.ea-rates{display:grid;grid-template-columns:repeat(4,1fr);gap:var(--ea-gap);margin-bottom:16px;}
.ea-rate-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;box-shadow:var(--shadow-sm);}
.ea-rate-top{display:flex;align-items:center;justify-content:space-between;gap:10px;}
.ea-rate-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;}
.ea-rate-ic svg{width:20px;height:20px;}
.ea-rate-num{font-size:2rem;font-weight:800;line-height:1;color:var(--text);}
.ea-rate-num small{font-size:1rem;font-weight:700;color:var(--muted);margin-left:1px;}
.ea-rate-lbl{font-size:.82rem;font-weight:600;color:var(--text-soft);margin:10px 0 8px;}
.ea-rate-card .progress{height:6px;border-radius:6px;background:var(--surface-2);overflow:hidden;}
.ea-rate-card .progress-bar{height:100%;border-radius:6px;transition:width .6s ease;}
.ea-rate-sub{font-size:.78rem;color:var(--muted);margin-top:8px;}
.ea-rate-sub strong{color:var(--text);}
/* Raw tiles */
.ea-tiles{display:grid;grid-template-columns:repeat(8,1fr);gap:12px;margin-bottom:8px;}
.ea-tile{border-radius:14px;padding:14px 14px;color:#fff;position:relative;overflow:hidden;box-shadow:var(--shadow-sm);}
.ea-tile-ic{position:absolute;right:10px;top:10px;opacity:.32;}.ea-tile-ic svg{width:24px;height:24px;}
.ea-tile-val{font-size:1.5rem;font-weight:800;line-height:1;}
.ea-tile-lbl{font-size:.74rem;opacity:.92;margin-top:4px;font-weight:600;}
.g-blue{background:linear-gradient(135deg,#063566,#084881);}.g-green{background:linear-gradient(135deg,#308629,#58A42F);}
.g-violet{background:linear-gradient(135deg,#084881,#a855f7);}.g-cyan{background:linear-gradient(135deg,#084881,#084881);}
.g-amber{background:linear-gradient(135deg,#b45309,#E67B1D);}.g-red{background:linear-gradient(135deg,#b91c1c,#ef4444);}
.g-orange{background:linear-gradient(135deg,#c2410c,#E67B1D);}.g-slate{background:linear-gradient(135deg,#475569,#64748b);}
/* Trend legend */
.ea-legend-mini{display:flex;gap:14px;font-size:.78rem;color:var(--text-soft);}
.ea-legend-mini span{display:inline-flex;align-items:center;gap:6px;}
.ea-legend-mini i{width:10px;height:10px;border-radius:3px;display:inline-block;}
/* Doughnuts */
.ea-donut-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--ea-gap);}
.ea-donut-body{display:flex;align-items:center;gap:18px;flex-wrap:wrap;}
.ea-donut-canvas{width:170px;height:170px;flex:0 0 170px;position:relative;}
.ea-pills{list-style:none;margin:0;padding:0;flex:1;min-width:180px;display:flex;flex-direction:column;gap:2px;}
.ea-pills li{display:flex;align-items:center;gap:9px;padding:6px 2px;border-bottom:1px solid var(--border-soft);font-size:.85rem;}
.ea-pills li:last-child{border-bottom:0;}
.ea-pill-dot{width:10px;height:10px;border-radius:3px;flex:0 0 10px;}
.ea-pill-k{flex:1;color:var(--text);text-transform:capitalize;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ea-pill-n{color:var(--text-soft);font-weight:600;white-space:nowrap;}
.ea-pill-n small{color:var(--muted);font-weight:500;margin-left:2px;}
/* Compare table */
.ea-compare th[data-sort]{cursor:pointer;user-select:none;white-space:nowrap;}
.ea-compare th[data-sort] svg{width:13px;height:13px;opacity:.4;vertical-align:-2px;margin-left:1px;}
.ea-compare th[data-sort].ea-sorted svg{opacity:.9;}
.ea-compare th.ea-num,.ea-compare td.ea-num{text-align:right;}
.ea-compare td strong{display:block;}
.ea-subj{display:block;font-size:.76rem;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ea-rate-chip{display:inline-block;padding:2px 8px;border-radius:20px;font-weight:700;font-size:.8rem;}
.ea-rate-chip.is-good{background:rgba(88,164,47,.14);color:#58A42F;}
.ea-rate-chip.is-mid{background:rgba(230,123,29,.16);color:#b45309;}
.ea-rate-chip.is-zero{background:var(--surface-2);color:var(--muted);}
:root[data-theme="dark"] .ea-rate-chip.is-mid{color:#fbbf24;}
@media(max-width:1100px){.ea-tiles{grid-template-columns:repeat(4,1fr);}.ea-donut-grid{grid-template-columns:1fr;}}
@media(max-width:820px){.ea-rates{grid-template-columns:repeat(2,1fr);}}
@media(max-width:560px){.ea-tiles{grid-template-columns:repeat(2,1fr);}.ea-rates{grid-template-columns:1fr;}}
</style>

<script>
(function () {
  var DONUT = <?= json_encode($donutColors) ?>;
  var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  var grid = isDark ? 'rgba(148,163,184,.16)' : 'rgba(148,163,184,.14)';
  var tickColor = isDark ? '#94a3b8' : '#64748b';

  if (!window.Chart) { return; }
  Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";

  /* Engagement trend line */
  var trendEl = document.getElementById('eaTrend');
  if (trendEl) {
    var S = <?= json_encode($series) ?>;
    new Chart(trendEl, {
      type: 'line',
      data: {
        labels: S.map(function (r) { return r.label; }),
        datasets: [
          { label: 'Sent',    data: S.map(function (r) { return r.sent; }),    borderColor: '#063566', backgroundColor: 'rgba(6,53,102,.08)', fill: true, tension: .4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
          { label: 'Opened',  data: S.map(function (r) { return r.opened; }),  borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,.06)', fill: true, tension: .4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
          { label: 'Clicked', data: S.map(function (r) { return r.clicked; }), borderColor: '#084881', backgroundColor: 'rgba(6,182,212,.06)', fill: true, tension: .4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, color: tickColor }, grid: { color: grid } },
          x: { ticks: { color: tickColor, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }, grid: { display: false } }
        }
      }
    });
  }

  /* Breakdown doughnuts */
  ['device', 'browser', 'os', 'country'].forEach(function (dim) {
    var el = document.getElementById('eaDonut-' + dim);
    if (!el) { return; }
    var labels = [], values = [];
    try { labels = JSON.parse(el.getAttribute('data-labels')) || []; } catch (e) {}
    try { values = JSON.parse(el.getAttribute('data-values')) || []; } catch (e) {}
    if (!values.length) { return; }
    new Chart(el, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: values,
          backgroundColor: values.map(function (_, i) { return DONUT[i % DONUT.length]; }),
          borderColor: isDark ? '#1e293b' : '#ffffff',
          borderWidth: 2, hoverOffset: 6
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '62%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var tot = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                var pct = tot > 0 ? Math.round(ctx.parsed / tot * 100) : 0;
                return ' ' + ctx.label + ': ' + ctx.parsed.toLocaleString() + ' (' + pct + '%)';
              }
            }
          }
        }
      }
    });
  });

  /* KPI count-up */
  document.querySelectorAll('.ea-tile-val[data-count]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0, cur = 0,
        step = Math.max(1, Math.ceil(target / 40));
    if (target === 0) { return; }
    var t = setInterval(function () {
      cur += step; if (cur >= target) { cur = target; clearInterval(t); }
      el.textContent = cur.toLocaleString();
    }, 22);
  });

  /* Client-side sort for the campaign comparison table */
  var table = document.getElementById('eaCompare');
  if (table) {
    var ths = table.querySelectorAll('th[data-sort]');
    ths.forEach(function (th, idx) {
      th.addEventListener('click', function () {
        var type = th.getAttribute('data-sort');
        var asc = !(th.dataset.asc === 'true');
        ths.forEach(function (o) { o.classList.remove('ea-sorted'); o.dataset.asc = ''; });
        th.classList.add('ea-sorted'); th.dataset.asc = asc ? 'true' : 'false';
        var tbody = table.querySelector('tbody');
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.sort(function (a, b) {
          var av = a.children[idx].getAttribute('data-v') || a.children[idx].textContent.trim();
          var bv = b.children[idx].getAttribute('data-v') || b.children[idx].textContent.trim();
          if (type === 'num') { av = parseFloat(av) || 0; bv = parseFloat(bv) || 0; return asc ? av - bv : bv - av; }
          return asc ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av));
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
      });
    });
  }
})();
</script>
<?php include __DIR__ . '/partials/foot.php'; ?>
