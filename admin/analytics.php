<?php
/**
 * =============================================================================
 *  Admin — Visitor Analytics dashboard (premium SaaS UI).
 *  A static, skeleton-first shell. assets/js/analytics.js fetches real data
 *  from admin/analytics-data.php (REST/AJAX) and renders KPIs (animated),
 *  Chart.js charts, a live real-time panel, rule-based insights, geo + content
 *  bar-lists, and CSV / Excel / PDF / PNG exports. All data is real page_views.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$page_title         = 'Visitor Analytics';
$load_charts        = true;   // foot.php loads Chart.js
$load_analytics     = true;   // foot.php loads jsPDF + SheetJS + analytics.js
$load_analytics_css = true;   // head.php loads analytics.css

// KPI card definitions (rendered as skeleton shells; JS fills value + delta).
$kpiCards = [
    ['visitors',           'Unique Visitors',   'users',       'ic-green',  'number'],
    ['views',              'Page Views',        'eye',         'ic-teal',   'number'],
    ['active_now',         'Active Now',        'radio',       'ic-rose',   'number'],
    ['sessions',           'Total Sessions',    'activity',    'ic-blue',   'number'],
    ['new_visitors',       'New Visitors',      'user-plus',   'ic-violet', 'number'],
    ['returning_visitors', 'Returning',         'repeat',      'ic-gold',   'number'],
    ['avg_session',        'Avg. Session',      'timer',       'ic-slate',  'duration'],
    ['bounce_rate',        'Bounce Rate',       'log-out',     'ic-rose',   'percent'],
    ['engagement_rate',    'Engagement Rate',   'heart',       'ic-green',  'percent'],
    ['pages_per_session',  'Pages / Session',   'layers',      'ic-teal',   'decimal'],
    ['leads',              'Leads',             'inbox',       'ic-blue',   'number'],
    ['donations',          'Donations',         'hand-heart',  'ic-green',  'number'],
    ['revenue',            'Revenue',           'indian-rupee','ic-gold',   'currency'],
    ['conversion_rate',    'Conversion Rate',   'target',      'ic-violet', 'percent'],
];

// Chart panels for the draggable grid: [canvasId, title, lucide, colClass].
$chartPanels = [
    ['anTrend',    'Visitors & Page Views', 'trending-up', 'col-8'],
    ['anSources',  'Traffic Sources',       'route',       'col-4'],
    ['anDevices',  'Devices',               'monitor-smartphone', 'col-4'],
    ['anBrowsers', 'Browsers',              'globe',       'col-4'],
    ['anOS',       'Operating Systems',     'cpu',         'col-4'],
    ['anHours',    'Activity by Hour',      'clock',       'col-8'],
    ['anWeekdays', 'Activity by Weekday',   'calendar-days','col-4'],
];

// Bar-list panels: [listId, title, lucide, colClass].
$listPanels = [
    ['anCountries', 'Top Countries', 'map',           'col-4'],
    ['anStates',    'Top States',    'map-pin',        'col-4'],
    ['anCities',    'Top Cities',    'building-2',     'col-4'],
    ['anTopPages',  'Top Pages',     'file-text',     'col-6'],
    ['anReferrers', 'Referrers',     'external-link',  'col-6'],
    ['anLanding',   'Landing Pages', 'log-in',        'col-6'],
    ['anExit',      'Exit Pages',    'log-out',       'col-6'],
];

$presets = [
    'today' => 'Today', 'yesterday' => 'Yesterday', '7d' => '7 Days', '30d' => '30 Days',
    'this_month' => 'This Month', 'last_month' => 'Last Month', '3m' => '3 Months',
    '6m' => '6 Months', 'this_year' => 'This Year', 'custom' => 'Custom',
];

include __DIR__ . '/partials/head.php';
?>
<div class="an-dash" id="anDash">

    <!-- ================= toolbar ================= -->
    <div class="an-toolbar">
        <div>
            <h1><?= lucide('chart-line') ?> Visitor Analytics</h1>
            <span class="an-sub"><?= lucide('database') ?> Real visitor data · <span id="anRangeLabel">Last 30 days</span></span>
        </div>
        <div class="an-actions">
            <button class="an-btn" id="anLiveToggle" title="Auto-refresh every 30s"><?= lucide('radio') ?> <span>Live</span></button>
            <button class="an-btn" id="anRefresh" title="Refresh now"><?= lucide('refresh-cw') ?> Refresh</button>
            <button class="an-btn" id="anFullscreen" title="Fullscreen"><?= lucide('maximize') ?> Fullscreen</button>
            <div class="an-export" id="anExport">
                <button class="an-btn" id="anExportBtn"><?= lucide('download') ?> Export <?= lucide('chevron-down') ?></button>
                <div class="an-export-menu">
                    <button data-export="csv"><?= lucide('file-text') ?> CSV (.csv)</button>
                    <button data-export="excel"><?= lucide('sheet') ?> Excel (.xlsx)</button>
                    <button data-export="pdf"><?= lucide('file-down') ?> PDF report</button>
                    <button data-export="png"><?= lucide('image') ?> Trend PNG</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= filter bar ================= -->
    <div class="an-filterbar">
        <div class="an-presets" id="anPresets">
            <?php foreach ($presets as $key => $label): ?>
                <button class="an-preset<?= $key === '30d' ? ' is-active' : '' ?>" data-range="<?= e($key) ?>"><?= e($label) ?></button>
            <?php endforeach; ?>
        </div>
        <div class="an-daterange" id="anDateRange">
            <input type="date" class="an-date" id="anStart" aria-label="Start date">
            <span>to</span>
            <input type="date" class="an-date" id="anEnd" aria-label="End date">
            <button class="an-btn" id="anApplyRange"><?= lucide('check') ?></button>
        </div>
        <div class="an-filter-spacer"></div>
        <select class="an-select" id="anDevice" aria-label="Device filter">
            <option value="">All devices</option>
            <option value="desktop">Desktop</option>
            <option value="mobile">Mobile</option>
            <option value="tablet">Tablet</option>
        </select>
        <select class="an-select" id="anSource" aria-label="Traffic source filter">
            <option value="">All sources</option>
            <option value="direct">Direct</option>
            <option value="organic">Organic Search</option>
            <option value="social">Social</option>
            <option value="referral">Referral</option>
        </select>
        <select class="an-select" id="anCountry" aria-label="Country filter">
            <option value="">All countries</option>
        </select>
        <select class="an-select" id="anState" aria-label="State filter">
            <option value="">All states</option>
        </select>
        <select class="an-select" id="anCity" aria-label="City filter">
            <option value="">All cities</option>
        </select>
    </div>

    <!-- ================= real-time ================= -->
    <div class="an-realtime">
        <div class="an-rt-card">
            <div class="an-rt-top"><span class="an-live-dot"></span> Active right now</div>
            <div class="an-rt-num" id="anActiveNow">0</div>
            <div class="an-rt-label"><span id="anViews5m">0</span> page views in the last 5 minutes</div>
            <div class="an-rt-spark"><canvas id="anSpark"></canvas></div>
        </div>
        <div class="an-rt-card" style="padding:0;">
            <div class="an-rt-top" style="padding:1.1rem 1.2rem 0;"><?= lucide('activity') ?> Live visitor feed</div>
            <div class="an-feed" id="anFeed">
                <div class="an-feed-empty">Waiting for live activity…</div>
            </div>
        </div>
    </div>

    <!-- ================= KPI cards ================= -->
    <div class="an-kpis" id="anKpis">
        <?php foreach ($kpiCards as [$key, $label, $icon, $tint, $fmt]): ?>
            <div class="an-kpi" data-kpi="<?= e($key) ?>" data-format="<?= e($fmt) ?>">
                <div class="an-kpi-ico <?= e($tint) ?>"><?= lucide($icon) ?></div>
                <div class="an-kpi-val skeleton an-sk-line" style="width:64%;">0</div>
                <div class="an-kpi-label"><?= e($label) ?></div>
                <span class="an-kpi-delta" hidden></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ================= charts ================= -->
    <div class="an-grid" id="anChartGrid">
        <?php foreach ($chartPanels as [$id, $title, $icon, $col]): ?>
            <div class="an-panel <?= e($col) ?>" draggable="true" data-panel="<?= e($id) ?>">
                <div class="an-panel-head">
                    <h3 class="an-panel-title"><?= lucide($icon) ?> <?= e($title) ?></h3>
                    <div class="an-panel-tools">
                        <span class="an-panel-drag" title="Drag to reorder"><?= lucide('grip-vertical') ?></span>
                    </div>
                </div>
                <div class="an-panel-body<?= $id === 'anTrend' ? ' tall' : '' ?>"><canvas id="<?= e($id) ?>"></canvas></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ================= geo + content bar-lists ================= -->
    <h2 class="an-section-h"><?= lucide('list') ?> Geography &amp; Content</h2>
    <div class="an-grid">
        <?php foreach ($listPanels as [$id, $title, $icon, $col]): ?>
            <div class="an-panel <?= e($col) ?>">
                <div class="an-panel-head"><h3 class="an-panel-title"><?= lucide($icon) ?> <?= e($title) ?></h3></div>
                <div class="an-panel-body" style="min-height:auto;">
                    <div class="an-list" id="<?= e($id) ?>">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <div class="an-list-item"><div class="an-list-row"><span class="an-list-label skeleton an-sk-line" style="width:55%;">&nbsp;</span></div><div class="an-list-bar skeleton"></div></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ================= AI insights ================= -->
    <h2 class="an-section-h"><?= lucide('sparkles') ?> Smart Insights</h2>
    <div class="an-insights" id="anInsights">
        <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="an-insight info">
                <div class="an-insight-ico skeleton" style="border-radius:10px;"></div>
                <div style="flex:1;">
                    <div class="an-insight-title skeleton an-sk-line" style="width:50%;">&nbsp;</div>
                    <div class="an-insight-text skeleton an-sk-line" style="width:90%;margin-top:.5rem;">&nbsp;</div>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <p class="text-muted" style="font-size:.8rem;text-align:center;margin-top:1rem;">
        <?= lucide('shield-check') ?> Self-hosted, cookie-free page-view analytics. New / returning is estimated from IP history; geo is resolved per-IP via a free lookup and won't populate for local visits.
    </p>
</div>

<script>
    window.__AN_CFG__ = {
        endpoint: <?= json_encode(admin_url('analytics-data')) ?>,
        site: <?= json_encode(get_setting('site_name', SITE_NAME)) ?>,
        currency: '₹'
    };
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
