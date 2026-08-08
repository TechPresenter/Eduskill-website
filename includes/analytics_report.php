<?php
/**
 * =============================================================================
 *  Visitor Analytics — report/query layer (admin only).
 * =============================================================================
 *  Loaded by admin/analytics.php and admin/analytics-data.php (NOT by bootstrap,
 *  so the public site never pays for it). Everything here is READ-ONLY and works
 *  purely from the real `page_views` data collected in includes/analytics.php.
 *
 *  Filters supported (all backed by real columns / derivable data):
 *    range preset | custom start/end | device | country | traffic source.
 * =============================================================================
 */

declare(strict_types=1);

/* -------------------------------------------------------------- UA PARSERS */

/** Coarse browser name from a User-Agent (order matters — Edge/Chrome/Safari overlap). */
function anr_browser(string $ua): string
{
    if ($ua === '') return 'Unknown';
    if (stripos($ua, 'Edg') !== false)                                   return 'Edge';
    if (stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false) return 'Opera';
    if (stripos($ua, 'SamsungBrowser') !== false)                        return 'Samsung Internet';
    if (stripos($ua, 'Firefox') !== false || stripos($ua, 'FxiOS') !== false) return 'Firefox';
    if (stripos($ua, 'CriOS') !== false || stripos($ua, 'Chrome') !== false)  return 'Chrome';
    if (stripos($ua, 'Safari') !== false)                                return 'Safari';
    if (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false)  return 'Internet Explorer';
    return 'Other';
}

/** Coarse OS name from a User-Agent (Android before Linux, iOS before macOS). */
function anr_os(string $ua): string
{
    if ($ua === '') return 'Unknown';
    if (stripos($ua, 'Windows') !== false)   return 'Windows';
    if (stripos($ua, 'Android') !== false)   return 'Android';
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iPod') !== false) return 'iOS';
    if (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) return 'macOS';
    if (stripos($ua, 'CrOS') !== false)      return 'Chrome OS';
    if (stripos($ua, 'Linux') !== false)     return 'Linux';
    return 'Other';
}

/** Classify a referrer URL into a traffic-source channel. */
function anr_source_of(?string $ref, string $selfHost): string
{
    if ($ref === null || $ref === '') return 'Direct';
    $host = strtolower((string) (parse_url($ref, PHP_URL_HOST) ?: $ref));
    if ($selfHost !== '' && strpos($host, $selfHost) !== false) return 'Direct';
    $eng = ['google', 'bing', 'yahoo', 'duckduckgo', 'yandex', 'baidu', 'ecosia', 'ask.', 'qwant'];
    $soc = ['facebook', 'fb.com', 'instagram', 'twitter', 'x.com', 't.co', 'linkedin', 'lnkd.in',
            'youtube', 'youtu.be', 'whatsapp', 'wa.me', 'telegram', 't.me', 'pinterest', 'reddit',
            'threads', 'tiktok', 'snapchat'];
    foreach ($eng as $e) if (strpos($host, $e) !== false) return 'Organic Search';
    foreach ($soc as $s) if (strpos($host, $s) !== false) return 'Social';
    return 'Referral';
}

/** SQL WHERE fragment for a source filter (patterns are constant — safe to inline). */
function anr_source_where(string $source, string $p = ''): string
{
    $eng = 'google|bing|yahoo|duckduckgo|yandex|baidu|ecosia|ask\\.com|qwant';
    $soc = 'facebook|fb\\.com|instagram|twitter|x\\.com|t\\.co|linkedin|lnkd\\.in|youtube|youtu\\.be|'
         . 'whatsapp|wa\\.me|telegram|t\\.me|pinterest|reddit|threads|tiktok|snapchat';
    switch ($source) {
        case 'direct':   return "({$p}referrer IS NULL OR {$p}referrer = '')";
        case 'organic':  return "{$p}referrer REGEXP '($eng)'";
        case 'social':   return "{$p}referrer REGEXP '($soc)'";
        case 'referral': return "({$p}referrer IS NOT NULL AND {$p}referrer <> '' AND {$p}referrer NOT REGEXP '($eng|$soc)')";
        default:         return '';
    }
}

/* -------------------------------------------------------------- FILTERS */

/** Resolve a preset (or custom range) into concrete start/end datetimes. */
function anr_range(string $preset, string $customStart = '', string $customEnd = ''): array
{
    $now = time();
    switch ($preset) {
        case 'today':      $s = strtotime('today');            $e = $now; break;
        case 'yesterday':  $s = strtotime('yesterday');        $e = strtotime('today') - 1; break;
        case '7d':         $s = strtotime('today -6 days');    $e = $now; break;
        case '30d':        $s = strtotime('today -29 days');   $e = $now; break;
        case 'this_month': $s = strtotime(date('Y-m-01'));     $e = $now; break;
        case 'last_month': $s = strtotime('first day of last month 00:00:00');
                           $e = strtotime(date('Y-m-01')) - 1; break;
        case '3m':         $s = strtotime('today -3 months');  $e = $now; break;
        case '6m':         $s = strtotime('today -6 months');  $e = $now; break;
        case 'this_year':  $s = strtotime(date('Y-01-01'));    $e = $now; break;
        case 'custom':
            $s = $customStart !== '' ? strtotime($customStart . ' 00:00:00') : strtotime('today -29 days');
            $e = $customEnd   !== '' ? strtotime($customEnd   . ' 23:59:59') : $now;
            if ($s === false) $s = strtotime('today -29 days');
            if ($e === false) $e = $now;
            if ($s > $e) { $t = $s; $s = $e; $e = $t; }
            break;
        default:           $s = strtotime('today -29 days');   $e = $now; break;
    }
    return ['start' => date('Y-m-d H:i:s', $s), 'end' => date('Y-m-d H:i:s', $e), 'start_ts' => $s, 'end_ts' => $e];
}

/** Build the active filter set from request params (all validated/whitelisted). */
function anr_filters(): array
{
    $allowed = ['today', 'yesterday', '7d', '30d', 'this_month', 'last_month', '3m', '6m', 'this_year', 'custom'];
    $preset  = (string) get('range', '30d');
    if (!in_array($preset, $allowed, true)) $preset = '30d';

    $r = anr_range($preset, (string) get('start', ''), (string) get('end', ''));

    $device = (string) get('device', '');
    if (!in_array($device, ['desktop', 'mobile', 'tablet'], true)) $device = '';

    $country = strtoupper(substr((string) get('country', ''), 0, 2));
    if ($country !== '' && !ctype_alpha($country)) $country = '';

    $source = (string) get('source', '');
    if (!in_array($source, ['direct', 'organic', 'social', 'referral'], true)) $source = '';

    $state = substr(trim((string) get('state', '')), 0, 96);
    $city  = substr(trim((string) get('city', '')), 0, 96);

    return [
        'preset'   => $preset,
        'start'    => $r['start'],   'end' => $r['end'],
        'start_ts' => $r['start_ts'], 'end_ts' => $r['end_ts'],
        'device'   => $device, 'country' => $country, 'state' => $state, 'city' => $city, 'source' => $source,
        'label'    => anr_range_label($preset, $r),
    ];
}

/** Human label for the active range. */
function anr_range_label(string $preset, array $r): string
{
    $map = [
        'today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days',
        '30d' => 'Last 30 days', 'this_month' => 'This month', 'last_month' => 'Last month',
        '3m' => 'Last 3 months', '6m' => 'Last 6 months', 'this_year' => 'This year',
    ];
    if (isset($map[$preset])) return $map[$preset];
    return date('d M Y', $r['start_ts']) . ' – ' . date('d M Y', $r['end_ts']);
}

/** Compose the WHERE clause + params for the current filters. */
function anr_where(array $f, string $alias = ''): array
{
    $p   = $alias !== '' ? $alias . '.' : '';
    $sql = "{$p}created_at >= :start AND {$p}created_at <= :end";
    $params = [':start' => $f['start'], ':end' => $f['end']];
    if (!empty($f['device']))  { $sql .= " AND {$p}device = :device"; $params[':device'] = $f['device']; }
    if (!empty($f['country'])) { $sql .= " AND {$p}country_code = :cc"; $params[':cc'] = $f['country']; }
    if (!empty($f['state']))   { $sql .= " AND {$p}region = :state"; $params[':state'] = $f['state']; }
    if (!empty($f['city']))    { $sql .= " AND {$p}city = :city"; $params[':city'] = $f['city']; }
    if (!empty($f['source']))  { $frag = anr_source_where($f['source'], $p); if ($frag !== '') $sql .= " AND $frag"; }
    return [$sql, $params];
}

/**
 * Distinct country / state / city options for the filter dropdowns.
 * Uses date + device + source only (NOT the geo filters), so choosing a state
 * never collapses the dropdowns to a single option.
 */
function anr_facets(array $f): array
{
    $base = ['start' => $f['start'], 'end' => $f['end'], 'device' => $f['device'], 'source' => $f['source'], 'country' => '', 'state' => '', 'city' => ''];
    [$w, $p] = anr_where($base);

    $countries = [];
    foreach (db_all("SELECT country_code cc, country FROM page_views WHERE $w AND country_code IS NOT NULL AND country_code <> '' GROUP BY country_code, country ORDER BY country", $p) as $r) {
        $countries[] = ['code' => $r['cc'], 'name' => $r['country'] ?: $r['cc']];
    }
    $states = [];
    foreach (db_all("SELECT DISTINCT region FROM page_views WHERE $w AND region IS NOT NULL AND region <> '' ORDER BY region", $p) as $r) { $states[] = $r['region']; }
    $cities = [];
    foreach (db_all("SELECT DISTINCT city FROM page_views WHERE $w AND city IS NOT NULL AND city <> '' ORDER BY city LIMIT 300", $p) as $r) { $cities[] = $r['city']; }

    return ['countries' => $countries, 'states' => $states, 'cities' => $cities];
}

/* -------------------------------------------------------------- SUMMARY */

/** Full dashboard payload for a filter set: KPIs + every chart series. */
function anr_summary(array $f): array
{
    [$w, $params] = anr_where($f);

    $views    = (int) db_value("SELECT COUNT(*) FROM page_views WHERE $w", $params);
    $sessions = (int) db_value("SELECT COUNT(DISTINCT session_id) FROM page_views WHERE $w", $params);

    $single = (int) db_value(
        "SELECT COUNT(*) FROM (SELECT session_id FROM page_views WHERE $w GROUP BY session_id HAVING COUNT(*) = 1) t",
        $params
    );
    $bounce     = $sessions ? round($single / $sessions * 100, 1) : 0.0;
    $engagement = $sessions ? round(100 - $bounce, 1) : 0.0;
    $pagesPer   = $sessions ? round($views / $sessions, 2) : 0.0;

    $avg = (float) db_value(
        "SELECT AVG(dur) FROM (SELECT TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) dur
         FROM page_views WHERE $w GROUP BY session_id) t",
        $params
    );
    $avgSec = (int) round($avg);

    $rp = $params;
    $rp[':bstart'] = $f['start'];   // distinct name — PDO emulation is off, so :start can't repeat
    $returning = (int) db_value(
        "SELECT COUNT(DISTINCT pv.session_id) FROM page_views pv
         WHERE $w AND EXISTS (SELECT 1 FROM page_views p2 WHERE p2.ip_address = pv.ip_address AND p2.created_at < :bstart)",
        $rp
    );
    $new       = max(0, $sessions - $returning);
    $activeNow = (int) db_value("SELECT COUNT(DISTINCT session_id) FROM page_views WHERE created_at >= (NOW() - INTERVAL 5 MINUTE)");

    // Real conversions from the site's own data.
    $donations = 0; $revenue = 0.0; $leads = 0;
    try {
        $donations = (int) db_value("SELECT COUNT(*) FROM donations WHERE status = 'completed' AND created_at >= :start AND created_at <= :end", [':start' => $f['start'], ':end' => $f['end']]);
        $revenue   = (float) db_value("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status = 'completed' AND created_at >= :start AND created_at <= :end", [':start' => $f['start'], ':end' => $f['end']]);
    } catch (Throwable $e) {}
    try {
        $leads = (int) db_value("SELECT COUNT(*) FROM contact_messages WHERE created_at >= :start AND created_at <= :end", [':start' => $f['start'], ':end' => $f['end']]);
    } catch (Throwable $e) {}
    $convRate = $sessions ? round(($donations + $leads) / $sessions * 100, 2) : 0.0;

    // Previous equal-length period (for KPI delta badges + growth insight).
    $len         = max(1, $f['end_ts'] - $f['start_ts']);
    $pf          = $f;
    $pf['end']   = date('Y-m-d H:i:s', $f['start_ts'] - 1);
    $pf['start'] = date('Y-m-d H:i:s', $f['start_ts'] - 1 - $len);
    [$pw, $pp]   = anr_where($pf);
    $prev = [
        'views'    => (int) db_value("SELECT COUNT(*) FROM page_views WHERE $pw", $pp),
        'visitors' => (int) db_value("SELECT COUNT(DISTINCT session_id) FROM page_views WHERE $pw", $pp),
    ];

    $kpis = [
        'views' => $views, 'visitors' => $sessions, 'sessions' => $sessions, 'active_now' => $activeNow,
        'new_visitors' => $new, 'returning_visitors' => $returning,
        'avg_session' => $avgSec, 'bounce_rate' => $bounce, 'engagement_rate' => $engagement,
        'pages_per_session' => $pagesPer,
        'donations' => $donations, 'revenue' => round($revenue, 2), 'leads' => $leads, 'conversion_rate' => $convRate,
    ];

    // Device split
    $devices = ['labels' => [], 'data' => []];
    foreach (db_all("SELECT device, COUNT(*) c FROM page_views WHERE $w GROUP BY device ORDER BY c DESC", $params) as $r) {
        $devices['labels'][] = ucfirst((string) $r['device']);
        $devices['data'][]   = (int) $r['c'];
    }

    // Browser + OS (parse distinct UAs in PHP)
    $bro = []; $oss = [];
    foreach (db_all("SELECT user_agent, COUNT(*) c FROM page_views WHERE $w GROUP BY user_agent", $params) as $r) {
        $c = (int) $r['c']; $ua = (string) $r['user_agent'];
        $b = anr_browser($ua); $o = anr_os($ua);
        $bro[$b] = ($bro[$b] ?? 0) + $c;
        $oss[$o] = ($oss[$o] ?? 0) + $c;
    }
    arsort($bro); arsort($oss);

    // Traffic sources (classify referrers in PHP)
    $selfHost = strtolower((string) (parse_url(APP_URL, PHP_URL_HOST) ?: ''));
    $src = ['Direct' => 0, 'Organic Search' => 0, 'Social' => 0, 'Referral' => 0];
    foreach (db_all("SELECT referrer, COUNT(*) c FROM page_views WHERE $w GROUP BY referrer", $params) as $r) {
        $src[anr_source_of($r['referrer'], $selfHost)] += (int) $r['c'];
    }
    $src = array_filter($src, static fn ($v) => $v > 0);

    // Hour-of-day + weekday
    $hours = array_fill(0, 24, 0);
    foreach (db_all("SELECT HOUR(created_at) h, COUNT(*) c FROM page_views WHERE $w GROUP BY HOUR(created_at)", $params) as $r) {
        $hours[(int) $r['h']] = (int) $r['c'];
    }
    $weekdays = array_fill(0, 7, 0); // 0=Sun .. 6=Sat
    foreach (db_all("SELECT DAYOFWEEK(created_at) d, COUNT(*) c FROM page_views WHERE $w GROUP BY DAYOFWEEK(created_at)", $params) as $r) {
        $weekdays[((int) $r['d']) - 1] = (int) $r['c'];
    }

    // Geo — top countries, states, cities
    $countries = [];
    foreach (db_all("SELECT country_code cc, country, COUNT(*) views FROM page_views WHERE $w AND country_code IS NOT NULL AND country_code <> '' GROUP BY country_code, country ORDER BY views DESC LIMIT 12", $params) as $r) {
        $countries[] = ['code' => $r['cc'], 'name' => $r['country'] ?: $r['cc'], 'views' => (int) $r['views']];
    }
    $states = [];
    foreach (db_all("SELECT region, COUNT(*) views FROM page_views WHERE $w AND region IS NOT NULL AND region <> '' GROUP BY region ORDER BY views DESC LIMIT 12", $params) as $r) {
        $states[] = ['name' => $r['region'], 'views' => (int) $r['views']];
    }
    $cities = [];
    foreach (db_all("SELECT city, region, COUNT(*) views FROM page_views WHERE $w AND city IS NOT NULL AND city <> '' GROUP BY city, region ORDER BY views DESC LIMIT 12", $params) as $r) {
        $cities[] = ['name' => $r['city'] . ($r['region'] ? ', ' . $r['region'] : ''), 'city' => $r['city'], 'views' => (int) $r['views']];
    }

    // Pages
    $topPages = array_map(static fn ($r) => ['url' => $r['url'], 'views' => (int) $r['views']],
        db_all("SELECT url, COUNT(*) views FROM page_views WHERE $w GROUP BY url ORDER BY views DESC LIMIT 10", $params));
    $landing  = array_map(static fn ($r) => ['url' => $r['url'], 'views' => (int) $r['c']],
        db_all("SELECT url, COUNT(*) c FROM (SELECT SUBSTRING_INDEX(GROUP_CONCAT(url ORDER BY created_at ASC SEPARATOR 0x0a), 0x0a, 1) url FROM page_views WHERE $w GROUP BY session_id) t GROUP BY url ORDER BY c DESC LIMIT 8", $params));
    $exit     = array_map(static fn ($r) => ['url' => $r['url'], 'views' => (int) $r['c']],
        db_all("SELECT url, COUNT(*) c FROM (SELECT SUBSTRING_INDEX(GROUP_CONCAT(url ORDER BY created_at DESC SEPARATOR 0x0a), 0x0a, 1) url FROM page_views WHERE $w GROUP BY session_id) t GROUP BY url ORDER BY c DESC LIMIT 8", $params));
    $referrers = array_map(static fn ($r) => ['url' => $r['referrer'], 'views' => (int) $r['views']],
        db_all("SELECT referrer, COUNT(*) views FROM page_views WHERE $w AND referrer IS NOT NULL AND referrer <> '' GROUP BY referrer ORDER BY views DESC LIMIT 8", $params));

    return [
        'kpis'      => $kpis,
        'prev'      => $prev,
        'trend'     => anr_trend($f, $w, $params),
        'devices'   => $devices,
        'browsers'  => ['labels' => array_keys($bro), 'data' => array_values($bro)],
        'os'        => ['labels' => array_keys($oss), 'data' => array_values($oss)],
        'sources'   => ['labels' => array_keys($src), 'data' => array_values($src)],
        'hours'     => $hours,
        'weekdays'  => $weekdays,
        'countries' => $countries,
        'states'    => $states,
        'cities'    => $cities,
        'top_pages' => $topPages,
        'landing'   => $landing,
        'exit'      => $exit,
        'referrers' => $referrers,
        'range'     => ['label' => $f['label'], 'start' => $f['start'], 'end' => $f['end']],
    ];
}

/** Trend series with granularity chosen from the range span. */
function anr_trend(array $f, string $w, array $params): array
{
    $span = $f['end_ts'] - $f['start_ts'];
    if ($span <= (2 * 86400 + 3600)) {
        $unit = 'hour';  $sqlFmt = '%Y-%m-%d %H:00:00'; $keyFmt = 'Y-m-d H:00:00'; $labelFmt = 'H:i'; $align = date('Y-m-d H:00:00', $f['start_ts']);
    } elseif ($span <= (92 * 86400)) {
        $unit = 'day';   $sqlFmt = '%Y-%m-%d';          $keyFmt = 'Y-m-d';          $labelFmt = 'd M'; $align = date('Y-m-d 00:00:00', $f['start_ts']);
    } else {
        $unit = 'month'; $sqlFmt = '%Y-%m-01';          $keyFmt = 'Y-m-01';         $labelFmt = 'M Y'; $align = date('Y-m-01 00:00:00', $f['start_ts']);
    }

    $map = [];
    foreach (db_all("SELECT DATE_FORMAT(created_at, '$sqlFmt') k, COUNT(*) views, COUNT(DISTINCT session_id) visitors FROM page_views WHERE $w GROUP BY k", $params) as $r) {
        $map[$r['k']] = $r;
    }

    $labels = []; $views = []; $visitors = [];
    $cur = strtotime($align); $end = $f['end_ts']; $guard = 0;
    while ($cur <= $end && $guard++ < 1500) {
        $key = date($keyFmt, $cur);
        $labels[]   = date($labelFmt, $cur);
        $views[]    = isset($map[$key]) ? (int) $map[$key]['views'] : 0;
        $visitors[] = isset($map[$key]) ? (int) $map[$key]['visitors'] : 0;
        $cur = strtotime('+1 ' . $unit, $cur);
    }
    return ['labels' => $labels, 'views' => $views, 'visitors' => $visitors, 'unit' => $unit];
}

/* -------------------------------------------------------------- REAL-TIME */

/** Live snapshot: active sessions, last-5-min views, recent visits, 30-min sparkline. */
function anr_realtime(): array
{
    $activeNow = (int) db_value("SELECT COUNT(DISTINCT session_id) FROM page_views WHERE created_at >= (NOW() - INTERVAL 5 MINUTE)");
    $views5m   = (int) db_value("SELECT COUNT(*) FROM page_views WHERE created_at >= (NOW() - INTERVAL 5 MINUTE)");
    $selfHost  = strtolower((string) (parse_url(APP_URL, PHP_URL_HOST) ?: ''));

    $recent = [];
    foreach (db_all("SELECT url, device, referrer, country, city, user_agent, created_at FROM page_views ORDER BY id DESC LIMIT 12") as $r) {
        $recent[] = [
            'url'     => $r['url'],
            'device'  => $r['device'],
            'browser' => anr_browser((string) $r['user_agent']),
            'source'  => anr_source_of($r['referrer'], $selfHost),
            'country' => $r['country'] ?: null,
            'city'    => $r['city'] ?: null,
            'ago'     => anr_time_ago((string) $r['created_at']),
        ];
    }

    $map = [];
    foreach (db_all("SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:00') k, COUNT(*) c FROM page_views WHERE created_at >= (NOW() - INTERVAL 30 MINUTE) GROUP BY k") as $m) {
        $map[$m['k']] = (int) $m['c'];
    }
    $spark = [];
    for ($i = 29; $i >= 0; $i--) { $spark[] = $map[date('Y-m-d H:i:00', time() - $i * 60)] ?? 0; }

    return ['active_now' => $activeNow, 'views_5m' => $views5m, 'recent' => $recent, 'spark' => $spark];
}

/** Compact "3s / 5m / 2h ago" formatter. */
function anr_time_ago(string $datetime): string
{
    $t = strtotime($datetime);
    if ($t === false) return '';
    $s = max(0, time() - $t);
    if ($s < 60)    return $s . 's ago';
    if ($s < 3600)  return intdiv($s, 60) . 'm ago';
    if ($s < 86400) return intdiv($s, 3600) . 'h ago';
    return intdiv($s, 86400) . 'd ago';
}

/* -------------------------------------------------------------- INSIGHTS */

/** Rule-based "smart insights" derived from the real numbers (no ML, no fake). */
function anr_insights(array $f, array $summary): array
{
    $k = $summary['kpis'];
    $out = [];

    // Growth vs the immediately-preceding equal-length period (computed in anr_summary).
    $prevViews = (int) ($summary['prev']['views'] ?? 0);

    if ($prevViews > 0) {
        $delta = (int) round(($k['views'] - $prevViews) / $prevViews * 100);
        $out[] = [
            'icon' => $delta >= 0 ? 'trending-up' : 'trending-down',
            'tone' => $delta >= 0 ? 'good' : 'warn',
            'title' => ($delta >= 0 ? 'Traffic up ' : 'Traffic down ') . abs($delta) . '%',
            'text'  => 'Page views went from ' . number_format($prevViews) . ' to ' . number_format($k['views']) . ' versus the previous period.',
        ];
    } elseif ($k['views'] > 0) {
        $out[] = ['icon' => 'sparkles', 'tone' => 'good', 'title' => number_format($k['views']) . ' views recorded', 'text' => 'Fresh traffic in this period — not enough history yet to compute a growth trend.'];
    }

    if ($k['sessions'] > 0) {
        if ($k['bounce_rate'] >= 70) {
            $out[] = ['icon' => 'log-out', 'tone' => 'warn', 'title' => 'High bounce rate (' . $k['bounce_rate'] . '%)', 'text' => 'Most visitors leave after a single page. Strengthen landing pages and add clear internal links / CTAs.'];
        } elseif ($k['bounce_rate'] <= 40) {
            $out[] = ['icon' => 'heart', 'tone' => 'good', 'title' => 'Strong engagement', 'text' => 'Only ' . $k['bounce_rate'] . '% bounce — visitors are exploring multiple pages per visit.'];
        }
    }

    if (!empty($summary['top_pages'][0])) {
        $tp = $summary['top_pages'][0];
        $out[] = ['icon' => 'file-text', 'tone' => 'info', 'title' => 'Top page', 'text' => $tp['url'] . ' drew ' . number_format($tp['views']) . ' views — a good place for your strongest calls-to-action.'];
    }
    if (!empty($summary['devices']['labels'][0])) {
        $dev = $summary['devices']['labels'][0];
        $out[] = ['icon' => 'smartphone', 'tone' => 'info', 'title' => 'Primary device: ' . $dev, 'text' => 'Prioritise the ' . strtolower($dev) . ' experience — it is where most of your audience is.'];
    }
    if (array_sum($summary['hours']) > 0) {
        $peak = (int) array_keys($summary['hours'], max($summary['hours']))[0];
        $out[] = ['icon' => 'clock', 'tone' => 'info', 'title' => 'Peak hour: ' . sprintf('%02d:00', $peak), 'text' => 'Publish posts and launch campaigns around this window for maximum reach.'];
    }
    if (!empty($summary['sources']['labels'][0])) {
        $out[] = ['icon' => 'route', 'tone' => 'info', 'title' => 'Top channel: ' . $summary['sources']['labels'][0], 'text' => 'Your biggest traffic source — keep investing here while diversifying the others.'];
    }

    return $out;
}
