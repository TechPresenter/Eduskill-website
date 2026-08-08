<?php
/**
 * =============================================================================
 *  Admin — Email Dashboard (KPIs, performance chart, activity, scheduled).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/email_center.php';

$stats  = ec_dashboard_stats();
$series = ec_dashboard_series(14);
$counts = ec_folder_counts();

$scheduled = db_all("SELECT id, subject, to_email, scheduled_at FROM email_messages
                     WHERE folder = 'scheduled' AND deleted_at IS NULL ORDER BY scheduled_at ASC LIMIT 6");
$recentCampaigns = db_all("SELECT id, name, subject, status, sent_count, open_count, click_count, total, created_at
                           FROM email_campaigns ORDER BY id DESC LIMIT 5");
$recentActivity = db_all("SELECT event_type, recipient, created_at FROM email_events ORDER BY id DESC LIMIT 10");
$subs = (int) db_value("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'subscribed'");
$contactsN = (int) db_value("SELECT COUNT(*) FROM contacts WHERE status = 'active'");

/* KPI tiles (the 8 the spec asks for). */
$kpis = [
    ['Sent',         $stats['sent'],       'send',          'g-blue'],
    ['Delivered',    $stats['delivered'],  'mail-check',    'g-green'],
    ['Opened',       $stats['opened'],     'mail-open',     'g-violet'],
    ['Clicked',      $stats['clicked'],    'mouse-pointer-click', 'g-cyan'],
    ['Bounced',      $stats['bounced'],    'undo-2',        'g-amber'],
    ['Failed',       $stats['failed'],     'x-circle',      'g-red'],
    ['Spam',         $stats['spam'],       'shield-alert',  'g-orange'],
    ['Unsubscribed', $stats['unsubscribed'],'user-minus',   'g-slate'],
];

$eventIcon = ['open' => 'mail-open', 'click' => 'mouse-pointer-click', 'sent' => 'send',
              'bounce' => 'undo-2', 'fail' => 'x-circle', 'spam' => 'shield-alert',
              'unsubscribe' => 'user-minus', 'delivered' => 'mail-check'];

$page_title  = 'Email Dashboard';
$load_charts = true;
include __DIR__ . '/partials/head.php';
?>
<div class="dash-quickbar adm-rise">
  <a class="dash-qa" href="<?= e(admin_url('email-compose')) ?>"><?= lucide('pencil') ?> Compose</a>
  <a class="dash-qa" href="<?= e(admin_url('email-mailbox')) ?>"><?= lucide('inbox') ?> Mailbox</a>
  <a class="dash-qa" href="<?= e(admin_url('email-campaigns?action=create')) ?>"><?= lucide('megaphone') ?> New Campaign</a>
  <a class="dash-qa" href="<?= e(admin_url('email-template-library')) ?>"><?= lucide('layout-template') ?> Templates</a>
  <a class="dash-qa" href="<?= e(admin_url('email-contacts')) ?>"><?= lucide('contact') ?> Contacts</a>
  <a class="dash-qa" href="<?= e(admin_url('email-analytics')) ?>"><?= lucide('bar-chart-3') ?> Analytics</a>
  <a class="dash-qa" href="<?= e(admin_url('email-settings')) ?>"><?= lucide('settings') ?> Settings</a>
</div>

<!-- KPIs -->
<div class="dash-section-head"><h2><?= lucide('mail') ?> Email Performance</h2><span class="muted">Across campaigns and your mailbox</span></div>
<div class="em-kpis">
  <?php foreach ($kpis as [$label, $val, $icon, $grad]): ?>
    <div class="em-kpi <?= e($grad) ?>">
      <div class="em-kpi-ic"><?= lucide($icon) ?></div>
      <div class="em-kpi-val" data-count="<?= (int) $val ?>"><?= e(number_format($val)) ?></div>
      <div class="em-kpi-label"><?= e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Rates strip -->
<div class="em-rates">
  <div class="em-rate"><span class="em-rate-num"><?= $stats['deliver_rate'] ?>%</span><span class="em-rate-lbl">Delivery rate</span><div class="progress"><div class="progress-bar" style="width:<?= min(100, $stats['deliver_rate']) ?>%;background:#58A42F;"></div></div></div>
  <div class="em-rate"><span class="em-rate-num"><?= $stats['open_rate'] ?>%</span><span class="em-rate-lbl">Open rate</span><div class="progress"><div class="progress-bar" style="width:<?= min(100, $stats['open_rate']) ?>%;background:#8b5cf6;"></div></div></div>
  <div class="em-rate"><span class="em-rate-num"><?= $stats['click_rate'] ?>%</span><span class="em-rate-lbl">Click rate</span><div class="progress"><div class="progress-bar" style="width:<?= min(100, $stats['click_rate']) ?>%;background:#084881;"></div></div></div>
  <div class="em-rate"><span class="em-rate-num"><?= $stats['bounce_rate'] ?>%</span><span class="em-rate-lbl">Bounce rate</span><div class="progress"><div class="progress-bar" style="width:<?= min(100, $stats['bounce_rate']) ?>%;background:#E67B1D;"></div></div></div>
</div>

<!-- Chart + Scheduled -->
<div class="dash-section-head"><h2><?= lucide('activity') ?> Engagement &amp; Schedule</h2><span class="muted">Last 14 days</span></div>
<div class="dash-grid-2-1">
  <div class="panel">
    <div class="panel-head"><h3 class="panel-title"><?= lucide('trending-up') ?> Sends, Opens &amp; Clicks</h3></div>
    <div class="panel-body"><canvas id="emChart" height="150"></canvas></div>
  </div>
  <div class="panel">
    <div class="panel-head"><h3 class="panel-title"><?= lucide('calendar-clock') ?> Scheduled</h3>
      <a class="btn btn-outline btn-sm" href="<?= e(admin_url('email-mailbox?folder=scheduled')) ?>">View all</a></div>
    <div class="panel-body">
      <?php if ($scheduled): ?>
        <ul class="em-sched-list">
          <?php foreach ($scheduled as $s): ?>
            <li>
              <span class="em-sched-ic"><?= lucide('clock') ?></span>
              <a href="<?= e(admin_url('email-compose?draft=' . (int) $s['id'])) ?>">
                <strong><?= e($s['subject'] ?: '(no subject)') ?></strong>
                <span class="muted"><?= e($s['to_email']) ?></span>
              </a>
              <time class="pill pill-amber"><?= e(date('d M, g:i A', strtotime($s['scheduled_at']))) ?></time>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('calendar-clock') ?></div>No scheduled emails. <a href="<?= e(admin_url('email-compose')) ?>">Schedule one</a>.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Campaigns + activity -->
<div class="dash-section-head"><h2><?= lucide('history') ?> Recent</h2><span class="muted">Campaigns &amp; live activity</span></div>
<div class="dash-grid-1-1">
  <div class="panel">
    <div class="panel-head"><h3 class="panel-title"><?= lucide('megaphone') ?> Recent Campaigns</h3>
      <a class="btn btn-outline btn-sm" href="<?= e(admin_url('email-campaigns')) ?>">All campaigns</a></div>
    <div class="panel-body">
      <?php if ($recentCampaigns): ?>
        <div class="table-wrap"><table class="admin-table">
          <thead><tr><th>Campaign</th><th>Status</th><th>Sent</th><th>Opens</th><th>Clicks</th></tr></thead>
          <tbody>
            <?php foreach ($recentCampaigns as $c): ?>
              <tr>
                <td><a href="<?= e(admin_url('email-campaigns?action=report&id=' . (int) $c['id'])) ?>"><strong><?= e($c['name']) ?></strong></a></td>
                <td><span class="pill pill-<?= $c['status'] === 'sent' ? 'green' : ($c['status'] === 'sending' ? 'blue' : 'slate') ?>"><?= e(ucfirst($c['status'])) ?></span></td>
                <td><?= (int) $c['sent_count'] ?></td>
                <td><?= (int) $c['open_count'] ?></td>
                <td><?= (int) $c['click_count'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('megaphone') ?></div>No campaigns yet. <a href="<?= e(admin_url('email-campaigns?action=create')) ?>">Create one</a>.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h3 class="panel-title"><?= lucide('radio') ?> Activity Timeline</h3></div>
    <div class="panel-body dash-feed">
      <?php if ($recentActivity): ?>
        <ul class="em-timeline">
          <?php foreach ($recentActivity as $a): ?>
            <li>
              <span class="em-tl-ic ev-<?= e($a['event_type']) ?>"><?= lucide($eventIcon[$a['event_type']] ?? 'circle') ?></span>
              <span class="em-tl-body"><strong><?= e(ucfirst($a['event_type'])) ?></strong> &middot; <?= e($a['recipient'] ?: 'recipient') ?></span>
              <time class="muted"><?= e(time_ago($a['created_at'])) ?></time>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('radio') ?></div>No activity yet — send your first email to see opens and clicks here.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Mailbox + audience snapshot -->
<div class="em-snapshot">
  <a class="em-snap" href="<?= e(admin_url('email-mailbox?folder=inbox')) ?>"><?= lucide('inbox') ?><span><strong><?= (int) ($counts['inbox']['unread'] ?? 0) ?></strong> unread in Inbox</span></a>
  <a class="em-snap" href="<?= e(admin_url('email-mailbox?folder=drafts')) ?>"><?= lucide('file-text') ?><span><strong><?= (int) ($counts['drafts']['total'] ?? 0) ?></strong> drafts</span></a>
  <a class="em-snap" href="<?= e(admin_url('email-mailbox?folder=outbox')) ?>"><?= lucide('upload') ?><span><strong><?= (int) ($counts['outbox']['total'] ?? 0) ?></strong> in Outbox</span></a>
  <a class="em-snap" href="<?= e(admin_url('subscribers')) ?>"><?= lucide('users') ?><span><strong><?= number_format($subs) ?></strong> subscribers</span></a>
  <a class="em-snap" href="<?= e(admin_url('email-contacts')) ?>"><?= lucide('contact') ?><span><strong><?= number_format($contactsN) ?></strong> contacts</span></a>
</div>

<style>
.em-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px;}
.em-kpi{border-radius:14px;padding:16px 18px;color:#fff;position:relative;overflow:hidden;box-shadow:var(--shadow-sm);}
.em-kpi-ic{position:absolute;right:12px;top:12px;opacity:.35;}.em-kpi-ic svg{width:30px;height:30px;}
.em-kpi-val{font-size:1.9rem;font-weight:800;line-height:1;}
.em-kpi-label{font-size:.82rem;opacity:.92;margin-top:4px;font-weight:600;}
.g-blue{background:linear-gradient(135deg,#063566,#084881);}.g-green{background:linear-gradient(135deg,#308629,#58A42F);}
.g-violet{background:linear-gradient(135deg,#084881,#a855f7);}.g-cyan{background:linear-gradient(135deg,#084881,#084881);}
.g-amber{background:linear-gradient(135deg,#b45309,#E67B1D);}.g-red{background:linear-gradient(135deg,#b91c1c,#ef4444);}
.g-orange{background:linear-gradient(135deg,#c2410c,#E67B1D);}.g-slate{background:linear-gradient(135deg,#475569,#64748b);}
.em-rates{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:6px;}
.em-rate{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 16px;}
.em-rate-num{font-size:1.4rem;font-weight:800;color:var(--text);}
.em-rate-lbl{display:block;font-size:.78rem;color:var(--muted);margin:2px 0 8px;}
.em-sched-list,.em-timeline{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:2px;}
.em-sched-list li{display:flex;align-items:center;gap:10px;padding:9px 4px;border-bottom:1px solid var(--border-soft);}
.em-sched-ic svg{width:16px;height:16px;color:#E67B1D;}
.em-sched-list a{flex:1;display:flex;flex-direction:column;min-width:0;}
.em-sched-list a strong{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.88rem;}
.em-sched-list a .muted{font-size:.76rem;}
.em-timeline li{display:flex;align-items:center;gap:10px;padding:8px 4px;border-bottom:1px solid var(--border-soft);font-size:.85rem;}
.em-tl-ic{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;background:var(--surface-2);}
.em-tl-ic svg{width:15px;height:15px;}
.em-tl-ic.ev-open{background:#f3e8ff;color:#084881;}.em-tl-ic.ev-click{background:#cffafe;color:#084881;}
.em-tl-ic.ev-sent{background:#dbeafe;color:#063566;}.em-tl-ic.ev-bounce,.em-tl-ic.ev-fail{background:#fee2e2;color:#dc2626;}
.em-tl-body{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.em-snapshot{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-top:16px;}
.em-snap{display:flex;align-items:center;gap:10px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 16px;color:var(--text);transition:.15s;}
.em-snap:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:var(--brand-600);}
.em-snap svg{width:22px;height:22px;color:var(--brand-600);}
.em-snap span{font-size:.82rem;color:var(--text-soft);}.em-snap strong{color:var(--text);}
@media(max-width:1000px){.em-kpis,.em-rates{grid-template-columns:repeat(2,1fr);}.em-snapshot{grid-template-columns:repeat(2,1fr);}}
</style>

<script>
(function () {
  var S = <?= json_encode($series) ?>;
  var labels = S.map(function (r) { return r.label; });
  if (window.Chart && document.getElementById('emChart')) {
    var mk = function (c1, c2) { var g = document.getElementById('emChart').getContext('2d').createLinearGradient(0,0,0,180); g.addColorStop(0,c2); g.addColorStop(1,'rgba(255,255,255,0)'); return g; };
    new Chart(document.getElementById('emChart'), {
      type: 'line',
      data: { labels: labels, datasets: [
        { label: 'Sent',    data: S.map(function(r){return r.sent;}),    borderColor: '#063566', backgroundColor: 'rgba(6,53,102,.08)', fill: true, tension: .4, borderWidth: 2, pointRadius: 0 },
        { label: 'Opened',  data: S.map(function(r){return r.opened;}),  borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,.06)', fill: true, tension: .4, borderWidth: 2, pointRadius: 0 },
        { label: 'Clicked', data: S.map(function(r){return r.clicked;}), borderColor: '#084881', backgroundColor: 'rgba(6,182,212,.06)', fill: true, tension: .4, borderWidth: 2, pointRadius: 0 }
      ]},
      options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(148,163,184,.12)' } }, x: { grid: { display: false } } } }
    });
  }
  // Animate KPI counters
  document.querySelectorAll('.em-kpi-val[data-count]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0, cur = 0, step = Math.max(1, Math.ceil(target / 40));
    if (target === 0) return;
    var t = setInterval(function () { cur += step; if (cur >= target) { cur = target; clearInterval(t); } el.textContent = cur.toLocaleString(); }, 22);
  });
})();
</script>
<?php include __DIR__ . '/partials/foot.php'; ?>
