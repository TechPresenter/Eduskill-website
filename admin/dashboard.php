<?php
require __DIR__ . '/includes/auth.php';
$admin_title = 'Dashboard';
$u = current_user();
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

/* ---- real KPIs from the database ---- */
$raised = (int) db_val("SELECT COALESCE(SUM(raised_amount),0) FROM campaigns WHERE deleted_at IS NULL");
$kpis = [
    ['label' => 'Total Raised', 'value' => '₹' . inr($raised), 'sub' => 'All campaigns', 'icon' => 'fa-hand-holding-dollar', 'grad' => 'from-emerald-500 to-emerald-600', 'flag' => false],
    ['label' => 'Active Campaigns', 'value' => (string) (int) db_val("SELECT COUNT(*) FROM campaigns WHERE deleted_at IS NULL AND status='active'"), 'sub' => 'Running now', 'icon' => 'fa-bullhorn', 'grad' => 'from-orange-500 to-orange-600', 'flag' => false],
    ['label' => 'Programmes', 'value' => (string) (int) db_val("SELECT COUNT(*) FROM programs WHERE deleted_at IS NULL"), 'sub' => 'Active programmes', 'icon' => 'fa-graduation-cap', 'grad' => 'from-purple-500 to-purple-600', 'flag' => false],
    ['label' => 'Donors', 'value' => (string) (int) db_val("SELECT COALESCE(SUM(donor_count),0) FROM campaigns WHERE deleted_at IS NULL"), 'sub' => 'Total supporters', 'icon' => 'fa-hand-holding-heart', 'grad' => 'from-rose-500 to-rose-600', 'flag' => false],
    ['label' => 'Unread Messages', 'value' => (string) (int) db_val("SELECT COUNT(*) FROM contacts WHERE is_read=0"), 'sub' => 'Contact inbox', 'icon' => 'fa-inbox', 'grad' => 'from-sky-500 to-sky-600', 'flag' => (int) db_val("SELECT COUNT(*) FROM contacts WHERE is_read=0") > 0],
    ['label' => 'Team Members', 'value' => (string) (int) db_val("SELECT COUNT(*) FROM team_members WHERE deleted_at IS NULL"), 'sub' => 'Staff & leadership', 'icon' => 'fa-users', 'grad' => 'from-pink-500 to-pink-600', 'flag' => false],
    ['label' => 'Testimonials', 'value' => (string) (int) db_val("SELECT COUNT(*) FROM testimonials WHERE deleted_at IS NULL"), 'sub' => 'Community voices', 'icon' => 'fa-quote-left', 'grad' => 'from-amber-500 to-amber-600', 'flag' => false],
    ['label' => 'Published Pages', 'value' => (string) (int) db_val("SELECT COUNT(*) FROM pages WHERE deleted_at IS NULL AND status='published'"), 'sub' => 'Live on site', 'icon' => 'fa-file-lines', 'grad' => 'from-cyan-500 to-cyan-600', 'flag' => false],
];

/* ---- chart data (real) ---- */
$campaignRows = db_all("SELECT title, raised_amount, goal_amount FROM campaigns WHERE deleted_at IS NULL ORDER BY raised_amount DESC LIMIT 6");
$chartLabels = array_map(fn ($r) => mb_strlen($r['title']) > 18 ? mb_substr($r['title'], 0, 18) . '…' : $r['title'], $campaignRows);
$chartRaised = array_map(fn ($r) => round((int) $r['raised_amount'] / 100), $campaignRows);
$chartGoal = array_map(fn ($r) => round((int) $r['goal_amount'] / 100), $campaignRows);

$content = [
    'Campaigns' => (int) db_val("SELECT COUNT(*) FROM campaigns WHERE deleted_at IS NULL"),
    'Programmes' => (int) db_val("SELECT COUNT(*) FROM programs WHERE deleted_at IS NULL"),
    'Events' => (int) db_val("SELECT COUNT(*) FROM events WHERE deleted_at IS NULL"),
    'Team' => (int) db_val("SELECT COUNT(*) FROM team_members WHERE deleted_at IS NULL"),
    'Testimonials' => (int) db_val("SELECT COUNT(*) FROM testimonials WHERE deleted_at IS NULL"),
    'FAQs' => (int) db_val("SELECT COUNT(*) FROM faqs WHERE deleted_at IS NULL"),
];

$activity = db_all("SELECT a.action, a.created_at, u.name FROM user_activity_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 6");
$messages = db_all("SELECT name, subject, is_read, created_at FROM contacts ORDER BY id DESC LIMIT 6");

require __DIR__ . '/includes/header.php';
?>

<!-- ===== Welcome banner ===== -->
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-brand-800 via-brand-700 to-brand-900 p-6 text-white shadow-xl sm:p-8">
  <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-accent-500/20 blur-2xl"></div>
  <div class="absolute -bottom-10 right-24 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
  <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <p class="text-sm text-white/70"><?= e($greeting) ?>,</p>
      <h2 class="mt-1 font-display text-2xl font-extrabold sm:text-3xl"><?= e($u['name'] ?? 'Admin') ?> 👋</h2>
      <p class="mt-2 text-sm text-white/70"><i class="fa-regular fa-calendar mr-1"></i><?= date('l, d M Y') ?></p>
      <?php $unread = (int) db_val("SELECT COUNT(*) FROM contacts WHERE is_read=0"); ?>
      <?php if ($unread > 0): ?><p class="mt-3 inline-flex items-center gap-2 text-sm text-amber-300"><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span> <?= $unread ?> new message<?= $unread === 1 ? '' : 's' ?> waiting</p><?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="<?= e(url('admin/campaign-form.php')) ?>" class="rounded-xl bg-accent-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg transition hover:bg-accent-600"><i class="fa-solid fa-plus mr-1.5"></i>New Campaign</a>
      <a href="<?= e(url('admin/programs-form.php')) ?>" class="rounded-xl bg-white/15 px-4 py-2.5 text-sm font-bold text-white backdrop-blur transition hover:bg-white/25"><i class="fa-solid fa-graduation-cap mr-1.5"></i>Add Programme</a>
      <a href="<?= e(url('admin/events-form.php')) ?>" class="rounded-xl bg-white/15 px-4 py-2.5 text-sm font-bold text-white backdrop-blur transition hover:bg-white/25"><i class="fa-solid fa-calendar-plus mr-1.5"></i>Add Event</a>
    </div>
  </div>
</div>

<!-- ===== KPI cards ===== -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?php foreach ($kpis as $k): ?>
    <div class="relative rounded-2xl border border-edge bg-surface p-5 shadow-card transition hover:shadow-raised">
      <?php if ($k['flag']): ?><span class="absolute right-4 top-4 h-2.5 w-2.5 rounded-full bg-rose-500"></span><?php endif; ?>
      <span class="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br <?= e($k['grad']) ?> text-white shadow-lg"><i class="fa-solid <?= e($k['icon']) ?> text-lg"></i></span>
      <div class="mt-4 font-display text-2xl font-extrabold tracking-tight text-content sm:text-3xl"><?= e($k['value']) ?></div>
      <div class="mt-1 text-sm font-semibold text-content"><?= e($k['label']) ?></div>
      <div class="text-xs text-content-muted"><?= e($k['sub']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Secondary quick stats ===== -->
<?php
$publishedContent = (int) db_val("SELECT COUNT(*) FROM campaigns WHERE deleted_at IS NULL")
    + (int) db_val("SELECT COUNT(*) FROM programs WHERE deleted_at IS NULL")
    + (int) db_val("SELECT COUNT(*) FROM events WHERE deleted_at IS NULL AND status='published'")
    + (int) db_val("SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL AND status='published'");
$subscribers = (int) db_val("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='subscribed'");
?>
<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
  <div class="flex items-center gap-4 rounded-2xl border border-edge bg-surface p-5 shadow-card">
    <span class="grid h-14 w-14 place-items-center rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 text-white shadow-lg"><i class="fa-solid fa-layer-group text-xl"></i></span>
    <div><div class="font-display text-2xl font-extrabold text-content"><?= $publishedContent ?></div><div class="text-sm font-medium text-content-muted">Published content items</div><div class="text-xs text-content-subtle">Campaigns · programmes · events · posts</div></div>
  </div>
  <div class="flex items-center gap-4 rounded-2xl border border-edge bg-surface p-5 shadow-card">
    <span class="grid h-14 w-14 place-items-center rounded-xl bg-gradient-to-br from-teal-500 to-teal-600 text-white shadow-lg"><i class="fa-solid fa-paper-plane text-xl"></i></span>
    <div><div class="font-display text-2xl font-extrabold text-content"><?= $subscribers ?></div><div class="text-sm font-medium text-content-muted">Newsletter subscribers</div><div class="text-xs text-content-subtle">Opted in for updates</div></div>
  </div>
</div>

<!-- ===== Charts row ===== -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
  <div class="rounded-2xl border border-edge bg-surface p-6 shadow-card lg:col-span-2">
    <div class="mb-4 flex items-center justify-between">
      <div><h3 class="font-display text-base font-bold text-content">Campaign funds</h3><p class="text-xs text-content-muted">Raised vs goal (₹)</p></div>
      <span class="rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700"><i class="fa-solid fa-indian-rupee-sign mr-1"></i>Fundraising</span>
    </div>
    <div class="h-72"><canvas id="chartCampaigns"></canvas></div>
  </div>
  <div class="rounded-2xl border border-edge bg-surface p-6 shadow-card">
    <div class="mb-4"><h3 class="font-display text-base font-bold text-content">Content overview</h3><p class="text-xs text-content-muted">Items by type</p></div>
    <div class="h-72"><canvas id="chartContent"></canvas></div>
  </div>
</div>

<!-- ===== Activity + messages ===== -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
  <div class="rounded-2xl border border-edge bg-surface p-6 shadow-card lg:col-span-2">
    <div class="mb-4 flex items-center justify-between"><h3 class="font-display text-base font-bold text-content">Recent messages</h3><a href="<?= e(url('admin/contacts.php')) ?>" class="text-sm font-semibold text-brand-700 hover:text-accent-600">View all →</a></div>
    <div class="scroll-x"><table class="w-full text-left text-sm">
      <thead class="border-b border-edge text-xs uppercase tracking-wide text-content-muted"><tr><th class="pb-2 font-semibold">From</th><th class="pb-2 font-semibold">Subject</th><th class="pb-2 font-semibold">When</th><th class="pb-2 font-semibold">Status</th></tr></thead>
      <tbody class="divide-y divide-edge">
        <?php if ($messages === []): ?><tr><td colspan="4" class="py-6 text-center text-content-muted">No messages yet.</td></tr>
        <?php else: foreach ($messages as $m): ?>
          <tr><td class="py-2.5 font-medium text-content"><?= e($m['name']) ?></td>
            <td class="py-2.5 text-content-muted"><?= e($m['subject'] ?: '(no subject)') ?></td>
            <td class="py-2.5 text-content-subtle"><?= e(date('d M', strtotime((string) $m['created_at']))) ?></td>
            <td class="py-2.5"><?= (int) $m['is_read'] === 0 ? '<span class="rounded-full bg-amber-50 px-2 py-0.5 text-2xs font-semibold text-amber-700">New</span>' : '<span class="rounded-full bg-surface-sunken px-2 py-0.5 text-2xs font-semibold text-content-muted">Read</span>' ?></td></tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
  <div class="rounded-2xl border border-edge bg-surface p-6 shadow-card">
    <h3 class="mb-4 font-display text-base font-bold text-content">Activity log</h3>
    <ol class="space-y-4">
      <?php if ($activity === []): ?><li class="text-sm text-content-muted">No activity yet.</li>
      <?php else: foreach ($activity as $a): ?>
        <li class="flex gap-3">
          <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600"><i class="fa-solid fa-wave-square text-xs"></i></span>
          <div><p class="text-sm text-content"><?= e(ucwords(str_replace(['.', '_'], ' ', (string) $a['action']))) ?></p>
            <p class="text-xs text-content-muted"><?= e($a['name'] ?? 'System') ?> · <?= e(date('d M, h:i A', strtotime((string) $a['created_at']))) ?></p></div>
        </li>
      <?php endforeach; endif; ?>
    </ol>
  </div>
</div>

<?php
$labels = json_encode($chartLabels);
$raisedJson = json_encode($chartRaised);
$goalJson = json_encode($chartGoal);
$contentLabels = json_encode(array_keys($content));
$contentData = json_encode(array_values($content));
$admin_scripts = <<<JS
<script>
(function () {
  if (!window.Chart) return;
  Chart.defaults.font.family = "Inter, system-ui, sans-serif";
  var c1 = document.getElementById('chartCampaigns');
  if (c1) new Chart(c1, {
    type: 'bar',
    data: { labels: {$labels}, datasets: [
      { label: 'Raised', data: {$raisedJson}, backgroundColor: '#f97316', borderRadius: 6, maxBarThickness: 28 },
      { label: 'Goal', data: {$goalJson}, backgroundColor: 'rgba(30,58,138,0.25)', borderRadius: 6, maxBarThickness: 28 }
    ]},
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return '₹' + v.toLocaleString('en-IN'); } } } } }
  });
  var c2 = document.getElementById('chartContent');
  if (c2) new Chart(c2, {
    type: 'doughnut',
    data: { labels: {$contentLabels}, datasets: [{ data: {$contentData},
      backgroundColor: ['#1e3a8a','#7c3aed','#0891b2','#db2777','#f59e0b','#059669'], borderWidth: 0 }]},
    options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' } } }
  });
})();
</script>
JS;
require __DIR__ . '/includes/footer.php';
