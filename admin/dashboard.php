<?php
/**
 * =============================================================================
 *  Admin dashboard — real stat counts, Chart.js charts, recent activity,
 *  quick actions. Every count is a live query.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$page_title  = 'Dashboard';
$load_charts = true;

/* ---- Live counts (each in a try in case a table is briefly unavailable) --- */
$stat = static function (string $table, string $where = '', array $p = []): int {
    try { return db_count($table, $where, $p); } catch (Throwable $e) { return 0; }
};

$stats = [
    ['label' => 'Published Blogs', 'value' => $stat('blogs', "status='published'"),        'icon' => 'pen-line',       'sub' => 'Live on the site',  'grad' => 'dg-green',  'link' => 'blogs'],
    ['label' => 'Upcoming Events', 'value' => $stat('events', "start_datetime>=NOW() AND status='published'"), 'icon' => 'calendar-days', 'sub' => 'Scheduled ahead', 'grad' => 'dg-teal',   'link' => 'events'],
    ['label' => 'Total Donations','value' => $stat('donations'),                            'icon' => 'coins',          'sub' => 'All-time received', 'grad' => 'dg-gold',   'link' => 'donations'],
    ['label' => 'New Volunteers',  'value' => $stat('volunteers', "status='new'"),          'icon' => 'hand-heart',     'sub' => 'Awaiting review',   'grad' => 'dg-blue',   'link' => 'volunteers'],
    ['label' => 'Unread Messages', 'value' => $stat('contact_messages', "status='unread'"), 'icon' => 'message-square', 'sub' => 'In your inbox',     'grad' => 'dg-rose',   'link' => 'contact-messages'],
    ['label' => 'Subscribers',     'value' => $stat('newsletter_subscribers', "status='subscribed'"), 'icon' => 'mail', 'sub' => 'Newsletter list',   'grad' => 'dg-cyan',   'link' => 'newsletter'],
    ['label' => 'Programs',        'value' => $stat('programs', "status='active'"),         'icon' => 'layout-grid',    'sub' => 'Currently active',  'grad' => 'dg-violet', 'link' => 'programs'],
    ['label' => 'Team Members',    'value' => $stat('team_members', 'status=1'),            'icon' => 'users',          'sub' => 'Active profiles',   'grad' => 'dg-indigo', 'link' => 'team'],
];

/* ---- Donations over the last 6 months (for the line chart) ---- */
$months = [];
$donationSeries = [];
for ($i = 5; $i >= 0; $i--) {
    $label = date('M Y', strtotime("-$i months"));
    $ym    = date('Y-m', strtotime("-$i months"));
    $months[] = $label;
    try {
        $donationSeries[] = (float) db_value(
            "SELECT COALESCE(SUM(amount),0) FROM donations WHERE DATE_FORMAT(created_at,'%Y-%m') = :ym",
            [':ym' => $ym]
        );
    } catch (Throwable $e) {
        $donationSeries[] = 0;
    }
}

/* ---- Content overview (for the doughnut chart) ---- */
$overview = [
    'Blogs'    => $stat('blogs'),
    'Events'   => $stat('events'),
    'Programs' => $stat('programs'),
    'Projects' => $stat('projects'),
    'Gallery'  => $stat('gallery_media'),
    'Team'     => $stat('team_members'),
];

/* ---- Recent activity & messages (login/logout noise filtered out) ---- */
try {
    $activity = db_all("SELECT a.*, u.name AS user_name FROM activity_logs a
                        LEFT JOIN users u ON u.id = a.user_id
                        WHERE a.action NOT IN ('login','logout')
                        ORDER BY a.created_at DESC LIMIT 8");
    if (!$activity) { // fall back to everything if there's nothing else yet
        $activity = db_all("SELECT a.*, u.name AS user_name FROM activity_logs a
                            LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 8");
    }
} catch (Throwable $e) { $activity = []; }

/** Pick an icon for an activity row from its action verb. */
$actIcon = static function (string $action): string {
    return match (true) {
        str_contains($action, 'issue')  => 'file-check',
        str_contains($action, 'backup') => 'database-backup',
        str_contains($action, 'create') => 'plus',
        str_contains($action, 'update') => 'pencil',
        str_contains($action, 'delete') => 'trash-2',
        str_contains($action, 'export') => 'download',
        str_contains($action, '2fa')    => 'shield-check',
        default                         => 'circle-dot',
    };
};
try {
    $messages = db_all("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5");
} catch (Throwable $e) { $messages = []; }

/* ---- Campaign performance (goal vs raised, ranked) ---- */
try {
    $campaignPerf = db_all("SELECT title, slug, goal_amount, raised_amount FROM campaigns
                            WHERE deleted_at IS NULL AND status IN ('active','completed')
                            ORDER BY raised_amount DESC LIMIT 5");
} catch (Throwable $e) { $campaignPerf = []; }

/* ---- Pending approvals across every application type ---- */
$pendingItems = [
    ['label' => 'Volunteer Applications',   'icon' => 'hand-heart',     'link' => 'volunteers',                'count' => $stat('volunteers', "status='new'")],
    ['label' => 'Internship Applications',  'icon' => 'graduation-cap', 'link' => 'internships',               'count' => $stat('internships', "status='new'")],
    ['label' => 'Partner Requests',         'icon' => 'handshake',      'link' => 'partner-applications',      'count' => $stat('partner_applications', "status='new'")],
    ['label' => 'Scholarship Applications', 'icon' => 'clipboard-list', 'link' => 'scholarship-applications',  'count' => $stat('scholarship_applications', "status='new'")],
    ['label' => 'Membership Applications',  'icon' => 'id-card',        'link' => 'membership-applications',   'count' => $stat('membership_applications', "status='new'")],
    ['label' => 'Job Applications',         'icon' => 'briefcase',      'link' => 'job-applications',          'count' => $stat('job_applications', "status='new'")],
    ['label' => 'Comments to moderate',     'icon' => 'message-square', 'link' => 'comments',                  'count' => $stat('blog_comments', "status='pending'")],
];
$pendingTotal = array_sum(array_column($pendingItems, 'count'));

/* ---- Greeting (from the hour) + banner pending counts (all live, all safe) ---- */
$hour = (int) date('G');
if ($hour < 12)     { $greeting = 'Good morning';   $greetIcon = 'sunrise'; }
elseif ($hour < 17) { $greeting = 'Good afternoon'; $greetIcon = 'sun'; }
else                { $greeting = 'Good evening';   $greetIcon = 'moon'; }

$pendingApprovals = $stat('blog_comments', "status='pending'")
                  + $stat('event_registrations', "status='pending'");
$newMessages      = $stat('contact_messages', "status='unread'");
$todayLabel       = date('l, j M Y');

include __DIR__ . '/partials/head.php';
?>

<!-- Welcome banner -->
<div class="dash-welcome">
    <div class="dash-welcome__main">
        <img class="dash-welcome__avatar" src="<?= e(image_url($admin['avatar'] ?? null, 'avatar')) ?>" alt="">
        <div class="dash-welcome__intro">
            <span class="dash-welcome__eyebrow"><?= lucide($greetIcon) ?> <?= e($greeting) ?>,</span>
            <h1 class="dash-welcome__name">
                <?= e($admin['name'] ?? 'Admin') ?>
                <span class="dash-role-pill"><?= lucide('shield-check') ?> Super Admin</span>
            </h1>
            <div class="dash-welcome__meta">
                <span><?= lucide('calendar-days') ?> <?= e($todayLabel) ?></span>
                <span><?= lucide('clock') ?> <span id="dashClock">--:--:--</span></span>
                <?php $lastLogin = db_value("SELECT last_login_at FROM users WHERE id = :id", [':id' => current_user_id()]);
                if ($lastLogin): ?><span><?= lucide('log-in') ?> Last login <?= e(format_datetime($lastLogin)) ?></span><?php endif; ?>
            </div>
            <div class="dash-pending">
                <span class="dash-chip"><?= lucide('clipboard-check') ?> <strong><?= e(number_format($pendingApprovals)) ?></strong> pending approvals</span>
                <span class="dash-chip"><?= lucide('mail') ?> <strong><?= e(number_format($newMessages)) ?></strong> new messages</span>
            </div>
        </div>
    </div>
    <div class="dash-welcome__actions">
        <a class="dbtn dbtn-solid" href="<?= e(admin_url('blogs?action=create')) ?>"><?= lucide('plus') ?> New Blog Post</a>
        <a class="dbtn dbtn-ghost" href="<?= e(admin_url('events?action=create')) ?>"><?= lucide('calendar-plus') ?> Add Event</a>
        <a class="dbtn dbtn-ghost" href="<?= e(url('/')) ?>" target="_blank"><?= lucide('external-link') ?> View Site</a>
    </div>
</div>

<!-- Quick action strip -->
<div class="dash-quickbar">
    <a class="dash-qa" href="<?= e(admin_url('hero-slides?action=create')) ?>"><?= lucide('image') ?> Add Slide</a>
    <a class="dash-qa" href="<?= e(admin_url('programs?action=create')) ?>"><?= lucide('layout-grid') ?> Add Program</a>
    <a class="dash-qa" href="<?= e(admin_url('events?action=create')) ?>"><?= lucide('calendar-days') ?> Add Event</a>
    <a class="dash-qa" href="<?= e(admin_url('gallery-albums?action=create')) ?>"><?= lucide('camera') ?> New Album</a>
    <a class="dash-qa" href="<?= e(admin_url('team?action=create')) ?>"><?= lucide('users') ?> Add Member</a>
    <a class="dash-qa" href="<?= e(admin_url('document-hub')) ?>"><?= lucide('stamp') ?> Documents</a>
    <a class="dash-qa" href="<?= e(admin_url('settings')) ?>"><?= lucide('settings') ?> Settings</a>
</div>

<!-- Section: Overview -->
<div class="dash-section-head"><h2><?= lucide('layout-dashboard') ?> Overview</h2><span class="muted">Live figures across your platform</span></div>
<div class="dash-stats">
    <?php foreach ($stats as $s): ?>
        <a class="dash-stat" href="<?= e(admin_url($s['link'])) ?>">
            <div class="dash-stat__icon <?= e($s['grad']) ?>"><?= lucide($s['icon']) ?></div>
            <div class="dash-stat__body">
                <div class="dash-stat__value" data-count="<?= (int) $s['value'] ?>"><?= e(number_format($s['value'])) ?></div>
                <div class="dash-stat__label"><?= e($s['label']) ?></div>
                <div class="dash-stat__sub"><?= e($s['sub']) ?></div>
            </div>
            <span class="dash-stat__go"><?= lucide('arrow-up-right') ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Section: Analytics -->
<div class="dash-section-head"><h2><?= lucide('bar-chart-3') ?> Analytics &amp; Actions</h2><span class="muted">Trends and what needs your attention</span></div>
<div class="dash-grid-2-1">
    <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('trending-up') ?> Donations — Last 6 Months</h3></div>
        <div class="panel-body"><canvas id="donationChart" height="150"></canvas></div>
    </div>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title"><?= lucide('clipboard-check') ?> Pending Approvals</h3>
            <?php if ($pendingTotal > 0): ?><span class="pill pill-amber"><?= (int) $pendingTotal ?> total</span><?php endif; ?>
        </div>
        <div class="panel-body">
            <ul class="approval-list">
                <?php foreach ($pendingItems as $pi): ?>
                    <li>
                        <a href="<?= e(admin_url($pi['link'])) ?>">
                            <span class="ap-ico"><?= lucide($pi['icon']) ?></span>
                            <span class="ap-label"><?= e($pi['label']) ?></span>
                            <span class="ap-count <?= $pi['count'] > 0 ? 'has' : '' ?>"><?= (int) $pi['count'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="dash-grid-1-1">
    <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('pie-chart') ?> Content Overview</h3></div>
        <div class="panel-body" style="display:grid;place-items:center;"><canvas id="overviewChart" height="150"></canvas></div>
    </div>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title"><?= lucide('megaphone') ?> Campaign Performance</h3>
            <a class="btn btn-outline btn-sm" href="<?= e(admin_url('campaigns')) ?>">All campaigns</a>
        </div>
        <div class="panel-body">
            <?php if ($campaignPerf): ?>
                <?php foreach ($campaignPerf as $r => $cp):
                    $goal = (float) $cp['goal_amount']; $raised = (float) $cp['raised_amount'];
                    $pct = $goal > 0 ? min(100, (int) round($raised / $goal * 100)) : 0; ?>
                    <div class="camp-row">
                        <div class="camp-top">
                            <span class="camp-rank"><?= $r + 1 ?></span>
                            <span class="camp-name"><?= e($cp['title']) ?></span>
                            <strong><?= (int) $pct ?>%</strong>
                        </div>
                        <div class="progress"><div class="progress-bar" style="width:<?= (int) $pct ?>%;"></div></div>
                        <div class="camp-meta"><?= e(money($raised)) ?> raised of <?= e(money($goal)) ?> goal</div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><div class="icon"><?= lucide('megaphone') ?></div>No campaigns yet. <a href="<?= e(admin_url('campaigns?action=create')) ?>">Create one</a>.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Section: Recent -->
<div class="dash-section-head"><h2><?= lucide('history') ?> Recent</h2><span class="muted">Latest activity and messages</span></div>
<div class="dash-grid-1-1">
    <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('activity') ?> Recent Activity</h3><a class="btn btn-outline btn-sm" href="<?= e(admin_url('activity-logs')) ?>">View all</a></div>
        <div class="panel-body">
            <?php if ($activity): ?>
                <ul class="activity-list dash-feed">
                    <?php foreach ($activity as $a): ?>
                        <li class="activity-item">
                            <span class="dot"><?= lucide($actIcon((string) $a['action'])) ?></span>
                            <div>
                                <strong><?= e($a['user_name'] ?? 'System') ?></strong>
                                <?= e($a['description'] ?: $a['action']) ?>
                                <br><time><?= e(time_ago($a['created_at'])) ?></time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state"><div class="icon"><?= lucide('clipboard-list') ?></div>No activity yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('mail') ?> Recent Messages</h3><a class="btn btn-outline btn-sm" href="<?= e(admin_url('contact-messages')) ?>">Inbox</a></div>
        <div class="panel-body">
            <?php if ($messages): ?>
                <ul class="activity-list dash-feed">
                    <?php foreach ($messages as $m): ?>
                        <li class="activity-item msg">
                            <span class="dot"><?= lucide('mail') ?></span>
                            <div>
                                <strong><?= e($m['name']) ?></strong>
                                <?php if ($m['status'] === 'unread'): ?><span class="pill pill-blue">New</span><?php endif; ?>
                                <br><?= e(excerpt($m['message'], 12)) ?>
                                <br><time><?= e(time_ago($m['created_at'])) ?></time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state"><div class="icon"><?= lucide('inbox') ?></div>No messages yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Chart data injected from PHP. Charts are created after Chart.js loads
    // (foot.php loads it synchronously before DOMContentLoaded fires).
    window.__DASH__ = {
        months: <?= json_encode($months) ?>,
        donations: <?= json_encode($donationSeries) ?>,
        overviewLabels: <?= json_encode(array_keys($overview)) ?>,
        overviewData: <?= json_encode(array_values($overview)) ?>,
    };

    // Live ticking clock in the welcome banner.
    (function () {
        var el = document.getElementById('dashClock');
        if (!el) return;
        function tick() {
            el.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        tick(); setInterval(tick, 1000);
    })();

    // Animated KPI counters (respects reduced-motion).
    (function () {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var nodes = document.querySelectorAll('.dash-stat__value[data-count]');
        nodes.forEach(function (el) {
            var target = parseInt(el.getAttribute('data-count'), 10) || 0;
            if (reduce || target <= 0) { el.textContent = target.toLocaleString(); return; }
            var start = null, dur = 900;
            function step(ts) {
                if (start === null) start = ts;
                var p = Math.min((ts - start) / dur, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(target * eased).toLocaleString();
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });
    })();

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;
        const d = window.__DASH__;
        const brand = '#063566';
        new Chart(document.getElementById('donationChart'), {
            type: 'line',
            data: { labels: d.months, datasets: [{
                label: 'Donations (INR)', data: d.donations, borderColor: brand,
                backgroundColor: 'rgba(6,53,102,.14)', fill: true, tension: .35, pointRadius: 4,
                pointBackgroundColor: brand,
            }]},
            options: { responsive: true, plugins: { legend: { display: false } },
                       scales: { y: { beginAtZero: true } } },
        });
        new Chart(document.getElementById('overviewChart'), {
            type: 'doughnut',
            data: { labels: d.overviewLabels, datasets: [{
                data: d.overviewData,
                backgroundColor: ['#063566','#084881','#E67B1D','#063566','#084881','#e11d48'],
            }]},
            options: { responsive: true, plugins: { legend: { position: 'right' } }, cutout: '62%' },
        });
    });
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
