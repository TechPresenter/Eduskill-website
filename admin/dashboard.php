<?php
/**
 * =============================================================================
 *  Admin dashboard — rebuilt on the admin design system (assets/css/admin-ds.css).
 * -----------------------------------------------------------------------------
 *  What this screen is:
 *
 *    · a greeting header carrying the REAL signed-in name and the REAL role
 *      (the previous version hard-coded the pill to "Super Admin" for everyone;
 *      when no role is resolvable the pill is now omitted rather than guessed),
 *    · a date-range filter (Today / 7 / 30 / 90 days / This year / Custom) that
 *      actually moves every figure on the page. The range arrives as ?range=…
 *      and is resolved against a whitelist; a custom window is parsed with
 *      DateTimeImmutable and clamped. No request value is ever concatenated
 *      into SQL — only the resolved window is bound as a parameter,
 *    · six KPI cards, each with a percentage change against the PREVIOUS window
 *      of the same length. When the prior window has nothing to compare against
 *      the card shows no delta at all instead of a flattering "+100%",
 *    · donation-trend and membership-growth charts (Chart.js, already shipped by
 *      admin/partials/foot.php behind $load_charts — no new dependency), plus a
 *      server-rendered campaign-performance panel,
 *    · a compact quick-action strip, the recent-activity timeline, and the
 *      pending-approval queue with Approve / Reject / View,
 *    · an empty state for every panel and a loading state that is real: moving
 *      the range swaps the panels for skeletons while the new window loads.
 *
 *  Every number on this page comes from a query in the block below. Nothing is
 *  estimated, extrapolated or seeded, and a fresh install renders empty states
 *  rather than zeros dressed up as achievements.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$page_title  = 'Dashboard';
$load_charts = true;   // Chart.js is loaded by admin/partials/foot.php for this flag.

/* The markup half of the design system: admin_kpi(), admin_sparkline(),
   admin_timeline(), admin_empty_state(), admin_error_state(),
   admin_skeleton_rows(). admin/partials/head.php also loads it, but this page
   builds card data before head.php runs, so it is required here explicitly
   rather than by luck of include order.

   UNCONDITIONALLY, and that is deliberate. This file has 14 unguarded call sites
   into that helper, so an is_file() guard here bought nothing: with the file
   absent it turned a clean include failure into an undefined-function fatal
   halfway through rendering, after the shell and half the page had already been
   sent. A hard dependency is declared as one — this screen cannot render without
   it, and it now says so on line one instead of dying mid-body.

   The guard in admin/partials/head.php is a DIFFERENT case and stays: every DS
   call there is function_exists()-wrapped and it has a hand-rolled breadcrumb
   fallback, so the other 136 screens genuinely do survive the helper's absence.
   Making that one unconditional is what would take down every admin page. */
require_once BASE_PATH . '/includes/admin_ui.php';

/* =============================================================================
 |  1. WHO IS SIGNED IN
 |  The role is read, not assumed. If the account carries no role, or the role
 |  row has gone, no pill is rendered — an empty space beats a wrong job title.
 |============================================================================*/
$me       = current_user() ?? [];
$meName   = trim((string) ($me['name'] ?? ''));
$meFirst  = $meName !== '' ? (explode(' ', $meName)[0]) : '';
$roleName = null;
if (!empty($me['role_id'])) {
    try {
        $roleRow = find('roles', (int) $me['role_id']);
        if ($roleRow && trim((string) $roleRow['name']) !== '') {
            $roleName = trim((string) $roleRow['name']);
        }
    } catch (Throwable $e) {
        $roleName = null;
    }
}

$hour = (int) date('G');
if ($hour < 12)      { $greeting = 'Good morning';   $greetIcon = 'sunrise'; }
elseif ($hour < 17)  { $greeting = 'Good afternoon'; $greetIcon = 'sun'; }
else                 { $greeting = 'Good evening';   $greetIcon = 'moon'; }

$signedInAt = null;
try {
    $signedInAt = db_value("SELECT last_login_at FROM users WHERE id = :id", [':id' => current_user_id()]);
} catch (Throwable $e) {
    $signedInAt = null;
}

/* =============================================================================
 |  2. DATE RANGE — resolved server-side against a whitelist
 |  ---------------------------------------------------------------------------
 |  ?range= must be one of $RANGES. A custom window additionally takes ?from=
 |  and ?to= as Y-m-d; both are round-tripped through DateTimeImmutable (which
 |  rejects 2026-02-31 and anything non-numeric), swapped if reversed, and
 |  clamped to [2000-01-01 … today] with a five-year ceiling on the span. Only
 |  the four resolved timestamps below ever reach the database, always bound.
 |============================================================================*/
$RANGES = [
    'today' => 'Today',
    '7d'    => '7 days',
    '30d'   => '30 days',
    '90d'   => '90 days',
    'ytd'   => 'This year',
    'custom'=> 'Custom',
];

// ?range[]=x would arrive as an array, so the type is checked before the cast.
$rangeRaw = get('range', '30d');
$range    = is_string($rangeRaw) ? $rangeRaw : '30d';
if (!isset($RANGES[$range])) {
    $range = '30d';
}

$today = new DateTimeImmutable('today');

/** Strict Y-m-d parse: the round-trip check rejects impossible dates. */
$parse_day = static function ($raw): ?DateTimeImmutable {
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    return ($d && $d->format('Y-m-d') === $raw) ? $d : null;
};

$customFrom = $parse_day(get('from'));
$customTo   = $parse_day(get('to'));

switch ($range) {
    case 'today':
        $start = $today;                          $end = $today; break;
    case '7d':
        $start = $today->modify('-6 days');       $end = $today; break;
    case '90d':
        $start = $today->modify('-89 days');      $end = $today; break;
    case 'ytd':
        $start = $today->setDate((int) $today->format('Y'), 1, 1); $end = $today; break;
    case 'custom':
        if ($customFrom === null || $customTo === null) {
            // An unusable custom window silently degrades to the default rather
            // than erroring — the filter is a view, not a form submission.
            $range = '30d';
            $start = $today->modify('-29 days'); $end = $today;
            break;
        }
        [$f, $t] = $customFrom <= $customTo ? [$customFrom, $customTo] : [$customTo, $customFrom];
        $floor = new DateTimeImmutable('2000-01-01');
        if ($f < $floor)  { $f = $floor; }
        if ($t > $today)  { $t = $today; }
        if ($f > $t)      { $f = $t; }
        $spanFloor = $t->modify('-1825 days');    // five years is a wide enough window
        if ($f < $spanFloor) { $f = $spanFloor; }
        $start = $f; $end = $t;
        break;
    case '30d':
    default:
        $range = '30d';
        $start = $today->modify('-29 days');      $end = $today; break;
}

$days      = (int) $start->diff($end)->days + 1;
$prevEnd   = $start->modify('-1 day');
$prevStart = $prevEnd->modify('-' . ($days - 1) . ' days');

$sqlStart     = $start->format('Y-m-d 00:00:00');
$sqlEnd       = $end->format('Y-m-d 23:59:59');
$sqlPrevStart = $prevStart->format('Y-m-d 00:00:00');
$sqlPrevEnd   = $prevEnd->format('Y-m-d 23:59:59');

$compareLabel = $days === 1 ? 'vs the day before' : 'vs previous ' . $days . ' days';
$periodPhrase = match ($range) {
    'today'  => 'today',
    '7d'     => 'the last 7 days',
    '30d'    => 'the last 30 days',
    '90d'    => 'the last 90 days',
    'ytd'    => 'this year so far',
    default  => 'the selected window',
};
$rangeLabel   = $days === 1
    ? format_date($start->format('Y-m-d'), 'j M Y')
    : format_date($start->format('Y-m-d'), 'j M Y') . ' – ' . format_date($end->format('Y-m-d'), 'j M Y');
$prevLabel    = $days === 1
    ? format_date($prevStart->format('Y-m-d'), 'j M Y')
    : format_date($prevStart->format('Y-m-d'), 'j M Y') . ' – ' . format_date($prevEnd->format('Y-m-d'), 'j M Y');

/* ---- Bucket skeleton: one slot per day, or per hour for a single day -------
   Gaps become zeroes rather than disappearing, so a quiet Tuesday reads as a
   quiet Tuesday. In the hourly view a date-only column (members.join_date,
   school_students.enrollment_date) carries no time and therefore lands in the
   00:00 bucket — that is where a midnight-stamped date belongs, and the same
   expression is used for the window filter so the buckets still sum to the
   figure on the card. */
$byHour  = ($days === 1);
$buckets = [];
if ($byHour) {
    $lastHour = $end->format('Y-m-d') === $today->format('Y-m-d') ? (int) date('G') : 23;
    for ($h = 0; $h <= $lastHour; $h++) {
        $buckets[$start->format('Y-m-d') . ' ' . str_pad((string) $h, 2, '0', STR_PAD_LEFT)] = 0.0;
    }
} else {
    for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
        $buckets[$d->format('Y-m-d')] = 0.0;
    }
}
/** Human labels for the chart axis, in bucket order. */
$bucketLabels = [];
foreach (array_keys($buckets) as $k) {
    $bucketLabels[] = $byHour ? substr($k, 11) . ':00' : format_date($k, 'j M');
}

/* =============================================================================
 |  3. QUERY HELPERS
 |  $table / $dateExpr / $where / $agg are compile-time literals taken from the
 |  $KPI_DEFS map below. Nothing from the request is interpolated — the window
 |  travels as :s / :e. A failed query returns null so the card can say so
 |  instead of printing a zero it did not measure.
 |============================================================================*/
$bucket_query = static function (
    string $table, string $dateExpr, string $where, string $agg, string $from, string $to
) use ($byHour, $buckets): ?array {
    $key = $byHour ? "DATE_FORMAT($dateExpr, '%Y-%m-%d %H')" : "DATE($dateExpr)";
    $sql = "SELECT $key AS k, $agg AS v FROM `$table` WHERE $dateExpr BETWEEN :s AND :e"
         . ($where !== '' ? " AND ($where)" : '')
         . " GROUP BY k ORDER BY k";
    try {
        $rows = db_all($sql, [':s' => $from, ':e' => $to]);
    } catch (Throwable $e) {
        return null;
    }
    $out = $buckets;
    foreach ($rows as $row) {
        $k = (string) $row['k'];
        if (array_key_exists($k, $out)) {
            $out[$k] += (float) $row['v'];
        }
    }
    return $out;
};

$scalar_query = static function (string $sql, array $params): ?float {
    try {
        return (float) db_value($sql, $params);
    } catch (Throwable $e) {
        return null;
    }
};

/* =============================================================================
 |  4. KPI CARDS
 |  ---------------------------------------------------------------------------
 |  Two shapes of metric, both honest about what they measure:
 |
 |    kind=sum    a flow. The value is the total banked inside the window; the
 |                comparison is the same total over the previous window.
 |    kind=stock  a population. The value is the size of that population as at
 |                the end of the window (everything that qualifies and had been
 |                created by then); the comparison is its size at the end of the
 |                previous window, which is exactly the running total the window
 |                started from. Growth therefore equals what the window added.
 |                Caveat worth knowing when reading history: the qualifying
 |                clause is evaluated against today's row state, so a record
 |                that has since changed status is counted by today's status.
 |============================================================================*/
$KPI_DEFS = [
    'donations' => [
        'label' => 'Total Donations', 'icon' => 'coins', 'kind' => 'sum',
        'table' => 'donations', 'date' => 'COALESCE(donated_at, created_at)',
        'where' => "status = 'completed'", 'agg' => 'COALESCE(SUM(amount), 0)', 'money' => true,
    ],
    'members' => [
        'label' => 'Active Members', 'icon' => 'id-card', 'kind' => 'stock',
        'table' => 'members', 'date' => 'COALESCE(join_date, created_at)',
        'where' => "membership_status = 'active'", 'agg' => 'COUNT(*)',
    ],
    'volunteers' => [
        'label' => 'Active Volunteers', 'icon' => 'hand-heart', 'kind' => 'stock',
        'table' => 'volunteers', 'date' => 'created_at',
        'where' => "status = 'approved'", 'agg' => 'COUNT(*)',
    ],
    'students' => [
        'label' => 'Students', 'icon' => 'graduation-cap', 'kind' => 'stock',
        'table' => 'school_students', 'date' => 'COALESCE(enrollment_date, created_at)',
        'where' => "status IN ('enrolled','active')", 'agg' => 'COUNT(*)',
    ],
    'schools' => [
        'label' => 'Schools', 'icon' => 'school', 'kind' => 'stock',
        'table' => 'schools', 'date' => 'created_at',
        'where' => "status = 'active'", 'agg' => 'COUNT(*)',
    ],
    'campaigns' => [
        'label' => 'Campaigns', 'icon' => 'megaphone', 'kind' => 'stock',
        'table' => 'campaigns', 'date' => 'created_at',
        'where' => "status = 'active' AND deleted_at IS NULL", 'agg' => 'COUNT(*)',
    ],
];

$kpis   = [];
$series = [];      // kept for the charts — no second trip to the database

foreach ($KPI_DEFS as $key => $d) {
    $card = ['label' => $d['label'], 'icon' => $d['icon']];

    $inRange = $bucket_query($d['table'], $d['date'], $d['where'], $d['agg'], $sqlStart, $sqlEnd);
    if ($inRange === null) {
        // The query failed. Say so on the card rather than printing a zero that
        // reads like a measurement. array_merge, not +, so these keys win.
        $kpis[$key] = array_merge($card, [
            'state' => 'error',
            'error' => 'Could not be read',
        ]);
        $series[$key] = null;
        continue;
    }
    $added = array_values($inRange);

    if ($d['kind'] === 'sum') {
        $value = array_sum($added);
        $prev  = $scalar_query(
            "SELECT {$d['agg']} FROM `{$d['table']}`"
            . " WHERE {$d['date']} BETWEEN :s AND :e AND ({$d['where']})",
            [':s' => $sqlPrevStart, ':e' => $sqlPrevEnd]
        );
        $spark = $added;
    } else {
        // Everything that already qualified before the window opened.
        $base = $scalar_query(
            "SELECT {$d['agg']} FROM `{$d['table']}`"
            . " WHERE {$d['date']} < :s AND ({$d['where']})",
            [':s' => $sqlStart]
        );
        $base  = $base ?? 0.0;
        $value = $base + array_sum($added);
        $prev  = $base;
        $spark = [];
        $run   = $base;
        foreach ($added as $a) { $run += $a; $spark[] = $run; }
    }

    // No prior figure, or a prior figure of zero, means there is no percentage to
    // state. Saying "+100%" because the previous window was empty would be a lie,
    // so the delta stays null and admin_kpi() renders the period label alone.
    // Rounding to one decimal first means a 0.02% drift reads as "0%", not "-0%".
    $delta = null;
    if ($prev !== null && $prev > 0) {
        $delta = round((($value - $prev) / $prev) * 100, 1);
    }

    $kpis[$key] = array_merge($card, [
        // money() output is a pre-formatted string; counts go through as ints so
        // admin_ui_num() groups them.
        'value' => !empty($d['money']) ? money($value) : (int) round($value),
        'delta' => $delta,
        'since' => $delta === null ? 'No comparable prior figure' : $compareLabel,
        'spark' => $spark,
    ]);
    $series[$key] = $added;
}

/* =============================================================================
 |  5. CHARTS
 |  Chart.js is already part of the admin bundle (partials/foot.php, $load_charts)
 |  so no library is added here. Both datasets are the KPI series computed above.
 |============================================================================*/
$donationTrend  = $series['donations'] ?? null;
$membershipAdds = $series['members'] ?? null;
$donationTotal  = $donationTrend ? array_sum($donationTrend) : 0.0;
$membershipNew  = $membershipAdds ? array_sum($membershipAdds) : 0.0;

/* ---- Campaign performance: money raised inside the window, against goal ---- */
$campaigns   = [];
$campaignErr = false;
try {
    $campaigns = db_all(
        "SELECT c.id, c.title, c.slug, c.goal_amount, c.raised_amount,
                COALESCE(SUM(CASE WHEN d.status = 'completed' THEN d.amount END), 0) AS period_amount,
                COUNT(CASE WHEN d.status = 'completed' THEN d.id END)                AS period_donations
           FROM campaigns c
           LEFT JOIN donations d
                  ON d.campaign_id = c.id
                 AND COALESCE(d.donated_at, d.created_at) BETWEEN :s AND :e
          WHERE c.deleted_at IS NULL AND c.status IN ('active','completed')
          GROUP BY c.id, c.title, c.slug, c.goal_amount, c.raised_amount
          ORDER BY period_amount DESC, c.raised_amount DESC
          LIMIT 6",
        [':s' => $sqlStart, ':e' => $sqlEnd]
    );
} catch (Throwable $e) {
    $campaignErr = true;
}

/* =============================================================================
 |  6. RECENT ACTIVITY — inside the selected window, sign-in noise removed
 |============================================================================*/
$activityErr  = false;
$activityRows = [];
try {
    $log = db_all(
        "SELECT a.id, a.action, a.module, a.description, a.severity, a.created_at,
                u.name AS user_name
           FROM activity_logs a
           LEFT JOIN users u ON u.id = a.user_id
          WHERE a.created_at BETWEEN :s AND :e
            AND a.action NOT IN ('login','logout')
          ORDER BY a.created_at DESC
          LIMIT 8",
        [':s' => $sqlStart, ':e' => $sqlEnd]
    );
    // severity → the timeline's tone vocabulary. admin_timeline() adds a glyph
    // and an .sr-only word for a toned row, so the rail colour is never the only
    // signal. 'info' is passed as no tone at all: an ordinary entry.
    $tones = ['info' => '', 'notice' => 'info', 'warning' => 'warn', 'critical' => 'error'];
    foreach ($log as $a) {
        $ts   = strtotime((string) $a['created_at']) ?: time();
        $what = (string) ($a['description'] ?: $a['action']);
        if (!empty($a['module'])) {
            $what .= ' (' . $a['module'] . ')';
        }
        $activityRows[] = [
            'who'      => $a['user_name'] ?: 'System',
            'what'     => $what,
            'when'     => time_ago($a['created_at']),
            'when_iso' => date(DATE_ATOM, $ts),
            'tone'     => $tones[(string) $a['severity']] ?? '',
        ];
    }
} catch (Throwable $e) {
    $activityErr = true;
}

/* =============================================================================
 |  7. PENDING APPROVALS
 |  ---------------------------------------------------------------------------
 |  Deliberately NOT filtered by the date range: a queue is everything still
 |  outstanding, and hiding a three-month-old application because the filter says
 |  "7 days" would be the opposite of useful.
 |
 |  Approve / Reject post to the handler that already owns each queue — every one
 |  of these is the module's existing `_do=status` endpoint, CSRF-guarded there.
 |  No new endpoint is invented, and the module redirects to its own list with a
 |  flash afterwards. `schools` has no approve action at all, so that row links
 |  to the screen that can change its status instead of pretending otherwise.
 |============================================================================*/
$QUEUES = [
    'volunteers' => [
        'type' => 'Volunteer', 'icon' => 'hand-heart', 'module' => 'volunteers',
        'label' => 'Volunteer applications', 'table' => 'volunteers', 'where' => "status = 'new'",
        'sql' => "SELECT id, name, email AS detail, created_at
                    FROM volunteers WHERE status = 'new'
                   ORDER BY created_at DESC, id DESC LIMIT 6",
        'approve' => 'approved', 'reject' => 'rejected',
        'reject_note' => 'Mark this volunteer application as rejected?',
        'pill' => 'pill-blue', 'status_label' => 'New',
    ],
    'membership-applications' => [
        'type' => 'Membership', 'icon' => 'id-card', 'module' => 'membership-applications',
        'label' => 'Membership applications', 'table' => 'membership_applications', 'where' => "status = 'new'",
        'sql' => "SELECT id, name, email AS detail, created_at
                    FROM membership_applications WHERE status = 'new'
                   ORDER BY created_at DESC, id DESC LIMIT 6",
        'approve' => 'approved', 'reject' => 'rejected',
        'reject_note' => 'Mark this membership application as rejected?',
        'pill' => 'pill-amber', 'status_label' => 'New',
    ],
    'schools' => [
        'type' => 'School', 'icon' => 'school', 'module' => 'schools',
        'label' => 'School requests', 'table' => 'schools', 'where' => "status = 'prospective'",
        'sql' => "SELECT id, name, city AS detail, created_at
                    FROM schools WHERE status = 'prospective'
                   ORDER BY created_at DESC, id DESC LIMIT 6",
        'approve' => null, 'reject' => null,        // no approve handler exists
        'review'  => 'schools?action=edit&id=',
        'pill' => 'pill-amber', 'status_label' => 'Prospective',
    ],
    'scholarship-applications' => [
        'type' => 'Scholarship', 'icon' => 'clipboard-list', 'module' => 'scholarship-applications',
        'label' => 'Scholarship applications', 'table' => 'scholarship_applications', 'where' => "status = 'new'",
        'sql' => "SELECT id, name, email AS detail, created_at
                    FROM scholarship_applications WHERE status = 'new'
                   ORDER BY created_at DESC, id DESC LIMIT 6",
        'approve' => 'approved', 'reject' => 'rejected',
        'reject_note' => 'Mark this scholarship application as rejected?',
        'pill' => 'pill-blue', 'status_label' => 'New',
    ],
    'comments' => [
        'type' => 'Comment', 'icon' => 'message-square', 'module' => 'comments',
        'label' => 'Content approvals', 'table' => 'blog_comments', 'where' => "status = 'pending'",
        'sql' => "SELECT c.id, c.name, b.title AS detail, c.created_at
                    FROM blog_comments c
                    LEFT JOIN blogs b ON b.id = c.blog_id
                   WHERE c.status = 'pending'
                   ORDER BY c.created_at DESC, c.id DESC LIMIT 6",
        'approve' => 'approved', 'reject' => 'spam',
        'reject_note' => 'Reject this comment? It will be filed as spam.',
        'pill' => 'pill-amber', 'status_label' => 'Pending',
    ],
];

$queueCounts  = [];
$approvalRows = [];
$queueErr     = false;
foreach ($QUEUES as $slug => $q) {
    try {
        $queueCounts[$slug] = db_count($q['table'], $q['where']);
        foreach (db_all($q['sql']) as $r) {
            // Each query filters to a single status, so the queue's own
            // status_label is the pill text — no need to carry the value twice.
            $approvalRows[] = [
                'slug'    => $slug,
                'id'      => (int) $r['id'],
                'name'    => (string) ($r['name'] ?? ''),
                'detail'  => (string) ($r['detail'] ?? ''),
                'created' => (string) $r['created_at'],
                'ts'      => strtotime((string) $r['created_at']) ?: 0,
            ];
        }
    } catch (Throwable $e) {
        $queueCounts[$slug] = 0;
        $queueErr = true;
    }
}
usort($approvalRows, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);
$approvalRows = array_slice($approvalRows, 0, 8);
$pendingTotal = array_sum($queueCounts);

/* =============================================================================
 |  8. QUICK ACTIONS
 |  Every entry points at a screen that exists. `volunteers` and `donations` are
 |  intake-only modules with no create form — those two are labelled for what
 |  they actually open rather than promising an "Add" screen that isn't there.
 |============================================================================*/
$quickActions = [
    ['Add Member',      'user-plus',       'members?action=create'],
    ['Add Student',     'graduation-cap',  'school-students?action=create'],
    ['Create Campaign', 'megaphone',       'campaigns?action=create'],
    ['Create Course',   'book-open',       'courses?action=create'],
    ['Create Event',    'calendar-plus',   'events?action=create'],
    ['Volunteers',      'hand-heart',      'volunteers'],
    ['Donations',       'coins',           'donations'],
];

include __DIR__ . '/partials/head.php';
?>
<style>
/* ---------------------------------------------------------------------------
   Dashboard-only layout. Tokens only — every length, radius, shadow and
   duration below resolves through assets/css/admin-ds.css, so this screen
   cannot drift from the scales. No gradients, no glass, no heavy shadows.
   --------------------------------------------------------------------------- */
body.admin .dash2-hello {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
    gap: var(--sp-5); margin-bottom: var(--sp-5); padding: var(--sp-6);
    border: 1px solid var(--border); border-radius: var(--r-lg);
    background: var(--surface); box-shadow: var(--elev-1);
}
body.admin .dash2-who { display: flex; align-items: center; gap: var(--sp-4); min-width: 0; }
body.admin .dash2-avatar {
    width: 56px; height: 56px; flex: none; border-radius: var(--r-pill);
    object-fit: cover; border: 1px solid var(--border);
}
body.admin .dash2-eyebrow {
    display: inline-flex; align-items: center; gap: var(--sp-2);
    margin: 0 0 var(--sp-1); font-size: .85rem; font-weight: 600; color: var(--muted);
}
body.admin .dash2-eyebrow svg.lucide { width: 1em; height: 1em; }
body.admin .dash2-title {
    display: flex; align-items: center; flex-wrap: wrap; gap: var(--sp-3);
    margin: 0 0 var(--sp-2); font-size: clamp(1.25rem, 2.4vw, 1.6rem);
    font-weight: 800; line-height: 1.2; letter-spacing: -.02em; color: var(--text);
}
body.admin .dash2-role {
    padding: var(--sp-1) var(--sp-3); font-size: .72rem; font-weight: 700;
    letter-spacing: .04em; text-transform: uppercase; gap: var(--sp-1);
    color: var(--brand-600, #0B4E3D); background: var(--surface-2);
    border: 1px solid var(--border);
}
body.admin .dash2-role svg.lucide { width: 1em; height: 1em; }
body.admin .dash2-sub { margin: 0; font-size: .92rem; color: var(--text-soft); line-height: 1.5; }
body.admin .dash2-meta {
    display: flex; flex-wrap: wrap; gap: var(--sp-1) var(--sp-4);
    margin: var(--sp-2) 0 0; font-size: .82rem; color: var(--muted);
}
body.admin .dash2-meta span { display: inline-flex; align-items: center; gap: var(--sp-1); }
body.admin .dash2-meta svg.lucide { width: 1em; height: 1em; }

body.admin .dash2-attn {
    display: flex; align-items: center; gap: var(--sp-3); padding: var(--sp-3) var(--sp-4);
    border: 1px solid var(--border); border-radius: var(--r-md); background: var(--surface-2);
    color: var(--text); text-decoration: none; min-height: 44px;
    transition: border-color var(--dur-1) var(--ease);
}
body.admin .dash2-attn:hover { border-color: var(--muted); }
body.admin .dash2-attn svg.lucide { width: 20px; height: 20px; color: var(--brand-600, #0B4E3D); }
body.admin .dash2-attn b { font-size: 1.15rem; font-weight: 800; font-variant-numeric: tabular-nums; }
body.admin .dash2-attn small { display: block; color: var(--muted); font-size: .78rem; }

/* Date range ------------------------------------------------------------- */
body.admin .dash2-filter {
    display: flex; flex-wrap: wrap; align-items: center; gap: var(--sp-3);
    margin-bottom: var(--sp-3);
}
body.admin .dash2-segs {
    display: flex; flex-wrap: wrap; gap: var(--sp-1); padding: var(--sp-1);
    border: 1px solid var(--border); border-radius: var(--r-md); background: var(--surface);
}
body.admin .dash2-seg {
    display: inline-flex; align-items: center; min-height: 36px; padding: 0 var(--sp-4);
    border-radius: var(--r-sm); font-size: .86rem; font-weight: 600;
    color: var(--text-soft); text-decoration: none; white-space: nowrap;
    transition: background-color var(--dur-1) var(--ease), color var(--dur-1) var(--ease);
}
body.admin .dash2-seg:hover { background: var(--surface-2); color: var(--text); }
body.admin .dash2-seg[aria-current="page"] {
    background: var(--brand-600, #0B4E3D); color: #fff;
}
body.admin .dash2-custom { border: 1px solid var(--border); border-radius: var(--r-md); background: var(--surface); }
body.admin .dash2-custom > summary {
    display: flex; align-items: center; gap: var(--sp-2); min-height: 44px;
    padding: 0 var(--sp-4); font-size: .86rem; font-weight: 600; cursor: pointer;
    color: var(--text-soft); list-style: none;
}
body.admin .dash2-custom > summary::-webkit-details-marker { display: none; }
body.admin .dash2-custom > summary svg.lucide { width: 1em; height: 1em; }
body.admin .dash2-custom[open] > summary { border-bottom: 1px solid var(--border); color: var(--text); }
body.admin .dash2-custom-body {
    display: flex; flex-wrap: wrap; align-items: flex-end; gap: var(--sp-3); padding: var(--sp-4);
}
body.admin .dash2-custom-body label { display: grid; gap: var(--sp-1); font-size: .78rem; color: var(--muted); }
body.admin .dash2-custom-body input { min-height: 40px; }
body.admin .dash2-caption {
    margin: 0 0 var(--sp-5); font-size: .84rem; color: var(--muted); line-height: 1.6;
}
body.admin .dash2-caption b { color: var(--text); font-weight: 600; }

/* Grids and panels -------------------------------------------------------- */
body.admin .kpi-grid { margin-bottom: var(--sp-5); }
body.admin .kpi-value { overflow-wrap: anywhere; }
body.admin .kpi-grid > a.kpi { text-decoration: none; transition: border-color var(--dur-1) var(--ease); }
body.admin .kpi-grid > a.kpi:hover { border-color: var(--muted); }
body.admin .dash2-quick { display: flex; flex-wrap: wrap; gap: var(--sp-2); margin-bottom: var(--sp-6); }
body.admin .dash2-quick .btn svg.lucide { width: 1em; height: 1em; }
body.admin .dash2-row { display: grid; gap: var(--sp-5); margin-bottom: var(--sp-5); }
body.admin .dash2-row.is-wide { grid-template-columns: 1.55fr 1fr; }
body.admin .dash2-row.is-even { grid-template-columns: 1fr 1fr; }
body.admin .dash2-row > .panel { margin: 0; display: flex; flex-direction: column; }
body.admin .dash2-row > .panel > .panel-body { flex: 1; }
body.admin .dash2-sechead {
    display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap;
    gap: var(--sp-3); margin: var(--sp-7) 0 var(--sp-4);
}
body.admin .dash2-sechead h2 {
    display: inline-flex; align-items: center; gap: var(--sp-2);
    margin: 0; font-size: 1.02rem; font-weight: 800; color: var(--text);
}
body.admin .dash2-sechead h2 svg.lucide { width: 1.05em; height: 1.05em; color: var(--brand-600, #0B4E3D); }
body.admin .dash2-dim { color: var(--muted); font-size: .82rem; }
body.admin .dash2-note { margin: var(--sp-4) 0 0; line-height: 1.6; }

/* Charts ------------------------------------------------------------------ */
body.admin .dash2-chart { position: relative; height: 260px; }
body.admin .dash2-chart > canvas { display: block; width: 100%; height: 100%; }
body.admin .dash2-chart > .dash2-sk { position: absolute; inset: 0; }
body.admin .dash2-chart > .dash2-sk > .skeleton { width: 100%; height: 100%; }

/* Campaign performance bars ---------------------------------------------- */
body.admin .dash2-bars { display: grid; gap: var(--sp-4); }
body.admin .dash2-bar-top {
    display: flex; align-items: baseline; gap: var(--sp-2); margin-bottom: var(--sp-2);
    font-size: .88rem;
}
body.admin .dash2-bar-name { font-weight: 600; color: var(--text); text-decoration: none; }
body.admin .dash2-bar-name:hover { text-decoration: underline; }
body.admin .dash2-bar-pct { margin-left: auto; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--text); }
body.admin .dash2-track {
    height: 8px; border-radius: var(--r-pill); background: var(--surface-2);
    border: 1px solid var(--border); overflow: hidden;
}
body.admin .dash2-fill { height: 100%; background: var(--brand-600, #0B4E3D); }
body.admin .dash2-bar-meta { margin-top: var(--sp-2); font-size: .8rem; color: var(--muted); }

/* Queue chips + approval row actions ------------------------------------- */
body.admin .dash2-chips { display: flex; flex-wrap: wrap; gap: var(--sp-2); margin-bottom: var(--sp-4); }
body.admin .dash2-chip {
    display: inline-flex; align-items: center; gap: var(--sp-2); min-height: 36px;
    padding: 0 var(--sp-3); border: 1px solid var(--border); border-radius: var(--r-pill);
    background: var(--surface); color: var(--text-soft); text-decoration: none; font-size: .82rem;
    transition: border-color var(--dur-1) var(--ease);
}
body.admin .dash2-chip:hover { border-color: var(--muted); color: var(--text); }
body.admin .dash2-chip svg.lucide { width: 15px; height: 15px; color: var(--brand-600, #0B4E3D); }
body.admin .dash2-chip .n {
    font-weight: 800; font-variant-numeric: tabular-nums; color: var(--text);
}
body.admin .dash2-chip.is-clear .n { color: var(--muted); }
body.admin .dash2-rowacts { display: flex; flex-wrap: wrap; gap: var(--sp-2); }
body.admin .dash2-rowacts form { display: inline; }
body.admin .dash2-rowacts .btn svg.lucide { width: 1em; height: 1em; }
/* Destructive intent stated three ways — the word "Reject", an X glyph and the
   status hue. The border is left to .btn-outline so this control never diverges
   from every other outline button in the panel. */
body.admin .btn.dash2-reject { color: var(--st-err); }
body.admin .btn.dash2-reject:hover {
    background: color-mix(in srgb, var(--st-err) 8%, transparent); color: var(--st-err);
}
body.admin .dash2-who { font-weight: 600; color: var(--text); }
body.admin .dash2-sub2 { display: block; color: var(--muted); font-size: .8rem; }

@media (max-width: 1080px) {
    body.admin .dash2-row.is-wide, body.admin .dash2-row.is-even { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    body.admin .dash2-hello { padding: var(--sp-5); }
    body.admin .dash2-attn { width: 100%; }
    body.admin .dash2-segs { width: 100%; }
    body.admin .dash2-seg { flex: 1 1 auto; justify-content: center; min-height: 44px; }
}
</style>

<!-- ===================================================================== -->
<!-- Greeting — real name, real role                                        -->
<!-- ===================================================================== -->
<section class="dash2-hello">
    <div class="dash2-who">
        <img class="dash2-avatar" src="<?= e(image_url($me['avatar'] ?? null, 'avatar')) ?>" alt="" width="56" height="56">
        <div style="min-width:0;">
            <p class="dash2-eyebrow"><?= lucide($greetIcon) ?> <?= e(format_date($today->format('Y-m-d'), 'l, j M Y')) ?></p>
            <h1 class="dash2-title">
                <?= e($meFirst !== '' ? $greeting . ', ' . $meFirst : $greeting) ?>
                <?php if ($roleName !== null): ?>
                    <span class="pill dash2-role"><?= lucide('shield-check') ?> <?= e($roleName) ?></span>
                <?php endif; ?>
            </h1>
            <p class="dash2-sub">
                Here is how <?= e(get_setting('site_name', SITE_NAME)) ?> is doing across
                <?= e($range === 'custom' ? $rangeLabel : $periodPhrase) ?>.
            </p>
            <p class="dash2-meta">
                <span><?= lucide('calendar-range') ?> <?= e($rangeLabel) ?></span>
                <?php if ($signedInAt): ?>
                    <span><?= lucide('log-in') ?> Signed in <?= e(time_ago($signedInAt)) ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <a class="dash2-attn" href="#pending-approvals">
        <?= lucide('clipboard-check') ?>
        <span>
            <b><?= e(number_format($pendingTotal)) ?></b>
            <small><?= $pendingTotal === 1 ? 'item awaiting approval' : 'items awaiting approval' ?></small>
        </span>
    </a>
</section>

<!-- ===================================================================== -->
<!-- Date range — validated server-side, applied to every figure below      -->
<!-- ===================================================================== -->
<form class="dash2-filter" method="get" action="<?= e(admin_url('dashboard')) ?>" data-dash-filter>
    <div class="dash2-segs" role="group" aria-label="Date range">
        <?php foreach ($RANGES as $key => $label): if ($key === 'custom') continue; ?>
            <a class="dash2-seg" data-range-link
               href="<?= e(admin_url('dashboard?range=' . $key)) ?>"
               <?= $range === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <details class="dash2-custom" <?= $range === 'custom' ? 'open' : '' ?>>
        <summary><?= lucide('calendar-search') ?> Custom<?= $range === 'custom' ? ': ' . e($rangeLabel) : '' ?></summary>
        <div class="dash2-custom-body">
            <input type="hidden" name="range" value="custom">
            <label>From
                <input class="form-control" type="date" name="from" required
                       max="<?= e($today->format('Y-m-d')) ?>"
                       value="<?= e($range === 'custom' ? $start->format('Y-m-d') : '') ?>">
            </label>
            <label>To
                <input class="form-control" type="date" name="to" required
                       max="<?= e($today->format('Y-m-d')) ?>"
                       value="<?= e($range === 'custom' ? $end->format('Y-m-d') : '') ?>">
            </label>
            <button class="btn btn-primary btn-sm" type="submit">Apply</button>
        </div>
    </details>
</form>

<p class="dash2-caption">
    Donations are totalled across <b><?= e($rangeLabel) ?></b>; the remaining counts are the
    figures as at <b><?= e(format_date($end->format('Y-m-d'), 'j M Y')) ?></b>. Percentages compare
    with <b><?= e($prevLabel) ?></b>. The approval queue is not date-filtered — it shows everything
    still outstanding.
</p>

<!-- ===================================================================== -->
<!-- KPI cards                                                             -->
<!-- ===================================================================== -->
<?= admin_kpi_grid(array_values($kpis)) ?>

<!-- ===================================================================== -->
<!-- Quick actions — compact controls, not oversized cards                 -->
<!-- ===================================================================== -->
<div class="dash2-quick">
    <?php foreach ($quickActions as [$label, $icon, $path]): ?>
        <a class="btn btn-secondary btn-sm" href="<?= e(admin_url($path)) ?>"><?= lucide($icon) ?> <?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<!-- ===================================================================== -->
<!-- Trends                                                                -->
<!-- ===================================================================== -->
<div class="dash2-sechead">
    <h2><?= lucide('trending-up') ?> Trends</h2>
    <span class="dash2-dim"><?= e($rangeLabel) ?></span>
</div>

<div class="dash2-row is-wide">
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title"><?= lucide('coins') ?> Donation trend</h3>
            <a class="btn btn-outline btn-sm" href="<?= e(admin_url('donation-reports')) ?>">Reports</a>
        </div>
        <div class="panel-body" data-panel-load>
            <?php if ($donationTrend === null): ?>
                <?= admin_error_state(
                        'Donation figures unavailable',
                        'The donations table could not be read for this window. Nothing has been estimated in its place.',
                        ['Reload' => admin_url('dashboard?range=' . $range)]
                    ) ?>
            <?php elseif ($donationTotal <= 0): ?>
                <?= admin_empty_state(
                        'No completed donations in this period',
                        'Nothing was received across ' . $rangeLabel . '. Widen the range, or open the donations list to check for pending payments.',
                        ['View donations' => admin_url('donations')],
                        'coins'
                    ) ?>
            <?php else: ?>
                <div class="dash2-chart" data-chart>
                    <canvas id="dashDonationChart" role="img"
                            aria-label="Donation trend, <?= (int) count($bucketLabels) ?> points, <?= e(money($donationTotal)) ?> received in total across <?= e($rangeLabel) ?>."></canvas>
                </div>
                <noscript><?= admin_error_state(
                    'Chart needs JavaScript',
                    money($donationTotal) . ' was received across ' . $rangeLabel . '. The trend line cannot be drawn without scripting.'
                ) ?></noscript>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title"><?= lucide('megaphone') ?> Campaign performance</h3>
            <a class="btn btn-outline btn-sm" href="<?= e(admin_url('campaigns')) ?>">All</a>
        </div>
        <div class="panel-body" data-panel-load>
            <?php if ($campaignErr): ?>
                <?= admin_error_state(
                        'Campaign figures unavailable',
                        'The campaigns query did not complete, so no goal progress can be shown.',
                        ['Open campaigns' => admin_url('campaigns')]
                    ) ?>
            <?php elseif (!$campaigns): ?>
                <?= admin_empty_state(
                        'No campaigns yet',
                        'Create a campaign and its progress towards goal will be tracked here.',
                        ['Create campaign' => admin_url('campaigns?action=create')],
                        'megaphone'
                    ) ?>
            <?php else: ?>
                <div class="dash2-bars">
                    <?php foreach ($campaigns as $c):
                        $goal   = (float) $c['goal_amount'];
                        $raised = (float) $c['raised_amount'];
                        $pct    = $goal > 0 ? min(100, (int) round($raised / $goal * 100)) : 0; ?>
                        <div>
                            <div class="dash2-bar-top">
                                <a class="dash2-bar-name" href="<?= e(admin_url('campaigns?action=edit&id=' . (int) $c['id'])) ?>"><?= e($c['title']) ?></a>
                                <?php if ($goal > 0): ?>
                                    <span class="dash2-bar-pct"><?= (int) $pct ?>%</span>
                                <?php else: ?>
                                    <span class="dash2-bar-pct dash2-dim">No goal set</span>
                                <?php endif; ?>
                            </div>
                            <div class="dash2-track" role="img"
                                 aria-label="<?= e($c['title']) ?>: <?= e(money($raised)) ?> raised<?= $goal > 0 ? ' of ' . e(money($goal)) . ' goal, ' . (int) $pct . ' per cent' : '' ?>">
                                <div class="dash2-fill" style="width:<?= (int) $pct ?>%;"></div>
                            </div>
                            <p class="dash2-bar-meta">
                                <?= e(money((float) $c['period_amount'])) ?> from
                                <?= (int) $c['period_donations'] ?> <?= (int) $c['period_donations'] === 1 ? 'donation' : 'donations' ?>
                                in this period &middot; <?= e(money($raised)) ?> all-time<?= $goal > 0 ? ' of ' . e(money($goal)) : '' ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="dash2-row is-even">
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title"><?= lucide('user-plus') ?> Membership growth</h3>
            <a class="btn btn-outline btn-sm" href="<?= e(admin_url('members')) ?>">Members</a>
        </div>
        <div class="panel-body" data-panel-load>
            <?php if ($membershipAdds === null): ?>
                <?= admin_error_state(
                        'Membership figures unavailable',
                        'The members table could not be read for this window.',
                        ['Open members' => admin_url('members')]
                    ) ?>
            <?php elseif ($membershipNew <= 0): ?>
                <?= admin_empty_state(
                        'No new active members in this period',
                        'The roll did not change across ' . $rangeLabel . '. Members appear here as soon as a membership goes active.',
                        ['Add member' => admin_url('members?action=create')],
                        'user-plus'
                    ) ?>
            <?php else: ?>
                <div class="dash2-chart" data-chart>
                    <canvas id="dashMemberChart" role="img"
                            aria-label="Membership growth, <?= e(number_format($membershipNew)) ?> new active members across <?= e($rangeLabel) ?>."></canvas>
                </div>
                <noscript><?= admin_error_state(
                    'Chart needs JavaScript',
                    number_format($membershipNew) . ' new active members across ' . $rangeLabel . '. The bars cannot be drawn without scripting.'
                ) ?></noscript>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title"><?= lucide('activity') ?> Recent activity</h3>
            <a class="btn btn-outline btn-sm" href="<?= e(admin_url('activity-logs')) ?>">Activity log</a>
        </div>
        <div class="panel-body" data-panel-load>
            <?php if ($activityErr): ?>
                <?= admin_error_state(
                        'Activity log unavailable',
                        'The activity log could not be read. No entries have been invented in its place.',
                        ['Open activity log' => admin_url('activity-logs')]
                    ) ?>
            <?php elseif (!$activityRows): ?>
                <?= admin_empty_state(
                        'Nothing logged in this period',
                        'Admin actions across ' . $rangeLabel . ' would appear here. Sign-ins are filtered out.',
                        ['Full activity log' => admin_url('activity-logs')],
                        'history'
                    ) ?>
            <?php else: ?>
                <?= admin_timeline($activityRows) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- Pending approvals                                                     -->
<!-- ===================================================================== -->
<div class="dash2-sechead" id="pending-approvals">
    <h2><?= lucide('clipboard-check') ?> Pending approvals</h2>
    <span class="dash2-dim">Everything outstanding, all dates</span>
</div>

<div class="panel">
    <div class="panel-head">
        <h3 class="panel-title"><?= lucide('inbox') ?> Approval queue</h3>
        <?php if ($pendingTotal > 0): ?>
            <span class="pill pill-amber"><?= e(number_format($pendingTotal)) ?> waiting</span>
        <?php else: ?>
            <span class="pill pill-green">All clear</span>
        <?php endif; ?>
    </div>
    <div class="panel-body" data-panel-load>
        <div class="dash2-chips">
            <?php foreach ($QUEUES as $slug => $q): $n = (int) ($queueCounts[$slug] ?? 0); ?>
                <a class="dash2-chip<?= $n === 0 ? ' is-clear' : '' ?>" href="<?= e(admin_url($q['module'])) ?>">
                    <?= lucide($q['icon']) ?> <?= e($q['label']) ?> <span class="n"><?= e(number_format($n)) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($queueErr && !$approvalRows): ?>
            <?= admin_error_state(
                    'Approval queue unavailable',
                    'One or more queues could not be read. Open each module directly to review its submissions.'
                ) ?>
        <?php elseif (!$approvalRows): ?>
            <?= admin_empty_state(
                    'Nothing is waiting on you',
                    'Volunteer, membership, school, scholarship and content submissions all land here for review.',
                    [],
                    'check-check'
                ) ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Type</th>
                            <th scope="col">Received</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($approvalRows as $r):
                        $q      = $QUEUES[$r['slug']];
                        $module = admin_url($q['module']); ?>
                        <tr>
                            <td>
                                <span class="dash2-who"><?= e($r['name'] !== '' ? $r['name'] : 'Unnamed') ?></span>
                                <?php if ($r['detail'] !== ''): ?>
                                    <span class="dash2-sub2"><?= e($r['detail']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($q['type']) ?></td>
                            <td>
                                <time datetime="<?= e(date('c', $r['ts'])) ?>" title="<?= e(format_datetime($r['created'])) ?>">
                                    <?= e(time_ago($r['created'])) ?>
                                </time>
                            </td>
                            <td><span class="pill <?= e($q['pill']) ?>"><?= e($q['status_label']) ?></span></td>
                            <td>
                                <div class="dash2-rowacts">
                                    <?php if (!empty($q['approve'])): ?>
                                        <form method="post" action="<?= e($module) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_do" value="status">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <input type="hidden" name="status" value="<?= e($q['approve']) ?>">
                                            <button class="btn btn-primary btn-sm" type="submit">
                                                <?= lucide('check') ?> Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!empty($q['reject'])): ?>
                                        <form method="post" action="<?= e($module) ?>"
                                              data-confirm="<?= e($q['reject_note'] ?? 'Reject this submission?') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_do" value="status">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <input type="hidden" name="status" value="<?= e($q['reject']) ?>">
                                            <button class="btn btn-outline btn-sm dash2-reject" type="submit">
                                                <?= lucide('x') ?> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!empty($q['review'])): ?>
                                        <a class="btn btn-secondary btn-sm" href="<?= e(admin_url($q['review'] . (int) $r['id'])) ?>">
                                            <?= lucide('pencil') ?> Review
                                        </a>
                                    <?php else: ?>
                                        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url($q['module'] . '?action=view&id=' . (int) $r['id'])) ?>">
                                            <?= lucide('eye') ?> View
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="dash2-dim dash2-note">
                Approve and Reject are handled by each module&rsquo;s own screen, so you land there with a
                confirmation. School requests have no one-click approval — <b>Review</b> opens the school
                record where its status is set.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Loading state, reused by every panel while a new range loads, and the
     failure state for a chart whose library never arrived. Both are rendered
     server-side through the shared components so the shapes stay identical. -->
<template id="dashSkeleton"><?= admin_skeleton_rows(4, 3) ?></template>
<template id="dashChartError"><?= admin_error_state(
    'Chart could not be drawn',
    'The charting library did not load. The figures above are unaffected.'
) ?></template>

<script>
window.__DASH__ = {
    labels:     <?= json_encode($bucketLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    donations:  <?= json_encode($donationTrend === null ? [] : array_map('floatval', array_values($donationTrend)), JSON_HEX_TAG) ?>,
    members:    <?= json_encode($membershipAdds === null ? [] : array_map('intval', array_values($membershipAdds)), JSON_HEX_TAG) ?>,
    bucket:     <?= json_encode($byHour ? 'hour' : 'day') ?>,
    currency:   <?= json_encode('₹', JSON_UNESCAPED_UNICODE) ?>
};

(function () {
    'use strict';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* --------------------------------------------------------------------
       Loading state. Changing the range is a real page load, so the panels
       are swapped for skeletons on the way out and the KPI cards drop into
       their .is-loading state — the DS hides the stale figure for us.
       -------------------------------------------------------------------- */
    function showLoading() {
        var tpl = document.getElementById('dashSkeleton');
        if (tpl) {
            document.querySelectorAll('[data-panel-load]').forEach(function (panel) {
                panel.replaceChildren(tpl.content.cloneNode(true));
            });
        }
        document.querySelectorAll('.kpi-grid .kpi').forEach(function (kpi) {
            kpi.classList.add('is-loading');
            kpi.setAttribute('aria-busy', 'true');
        });
        /* The skeleton carries role="status" aria-busy, but it is INSERTED — and a
           live region has to exist before its content changes, so on its own it
           announces nothing. Say it through the shell's persistent announcer. */
        if (window.AdminUI && window.AdminUI.announce) {
            window.AdminUI.announce('Loading the dashboard for the new date range…');
        }
    }
    document.querySelectorAll('[data-range-link]').forEach(function (a) {
        a.addEventListener('click', showLoading);
    });
    var filter = document.querySelector('[data-dash-filter]');
    if (filter) { filter.addEventListener('submit', showLoading); }

    /* --------------------------------------------------------------------
       Charts. Colours are read from the live CSS custom properties so the
       canvases follow the Theme Engine and dark mode instead of hard-coding
       a palette. Flat fills only — no gradients.
       -------------------------------------------------------------------- */
    function cssVar(name, fallback) {
        var v = getComputedStyle(document.body).getPropertyValue(name);
        v = (v || '').trim();
        return v !== '' ? v : fallback;
    }
    function alpha(color, a) {
        var m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(color);
        if (!m) { return 'rgba(11,78,61,' + a + ')'; }
        var h = m[1];
        if (h.length === 3) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
        var n = parseInt(h, 16);
        return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
    }

    function build() {
        var d = window.__DASH__;
        var brand  = cssVar('--brand-600', '#0B4E3D');
        var grid   = cssVar('--border', '#C1CCB3');
        var muted  = cssVar('--muted', '#4B6754');

        if (typeof Chart === 'undefined') {
            // The library did not arrive. Swap in the server-rendered error state
            // instead of leaving a shimmer running forever over an empty box.
            var errTpl = document.getElementById('dashChartError');
            var swapped = 0;
            document.querySelectorAll('[data-chart]').forEach(function (box) {
                if (errTpl) {
                    box.replaceWith(errTpl.content.cloneNode(true));
                } else {
                    box.remove();
                }
                swapped++;
            });
            /* Same reason as showLoading(): an .error-state that is inserted
               carries role="alert" but was never spoken. */
            if (swapped && window.AdminUI && window.AdminUI.announce) {
                window.AdminUI.announce('The charting library did not load, so the trend charts could not be drawn. The figures above are unaffected.', true);
            }
            return;
        }

        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.color = muted;

        var common = {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduce ? false : { duration: 240 },   // design system: 150–400ms
            plugins: { legend: { display: false }, tooltip: { displayColors: false } },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8, autoSkip: true } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: grid } }
            }
        };

        var donationCanvas = document.getElementById('dashDonationChart');
        if (donationCanvas && d.donations.length) {
            new Chart(donationCanvas, {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Donations',
                        data: d.donations,
                        borderColor: brand,
                        backgroundColor: alpha(brand, 0.10),
                        borderWidth: 2, fill: true, tension: 0.25,
                        pointRadius: d.donations.length > 31 ? 0 : 3,
                        pointBackgroundColor: brand, pointHoverRadius: 5
                    }]
                },
                options: Object.assign({}, common, {
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            displayColors: false,
                            callbacks: {
                                label: function (c) {
                                    return d.currency + Number(c.parsed.y).toLocaleString();
                                }
                            }
                        }
                    }
                })
            });
        }

        var memberCanvas = document.getElementById('dashMemberChart');
        if (memberCanvas && d.members.length) {
            new Chart(memberCanvas, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'New active members',
                        data: d.members,
                        backgroundColor: alpha(brand, 0.85),
                        hoverBackgroundColor: brand,
                        borderRadius: 4, maxBarThickness: 26
                    }]
                },
                options: Object.assign({}, common, {
                    scales: Object.assign({}, common.scales, {
                        y: { beginAtZero: true, border: { display: false },
                             grid: { color: grid }, ticks: { precision: 0 } }
                    })
                })
            });
        }

        // Chart drawn — retire the placeholders.
        document.querySelectorAll('[data-chart] > .dash2-sk').forEach(function (sk) { sk.remove(); });
    }

    // A real gap exists between this markup parsing and Chart.js executing at
    // the end of <body>, so the placeholder is genuine rather than theatre.
    document.querySelectorAll('[data-chart]').forEach(function (box) {
        var sk = document.createElement('div');
        sk.className = 'dash2-sk';
        sk.setAttribute('aria-hidden', 'true');
        sk.innerHTML = '<div class="skeleton sk-block"></div>';
        box.appendChild(sk);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', build);
    } else {
        build();
    }
}());
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
