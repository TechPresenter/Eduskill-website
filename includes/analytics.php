<?php
/**
 * =============================================================================
 *  Visitor analytics — lightweight, self-hosted page-view tracking.
 * =============================================================================
 *  track_pageview() is called from bootstrap for public GET page requests.
 *  The admin Analytics page reads the pv_* query helpers below.
 * =============================================================================
 */

declare(strict_types=1);

/** Detect a coarse device type from the User-Agent. */
function pv_device(string $ua): string
{
    $ua = strtolower($ua);
    if (preg_match('/bot|crawl|spider|slurp|bing|google|yandex|duckduck|facebookexternal|preview/i', $ua)) {
        return 'bot';
    }
    if (preg_match('/ipad|tablet|kindle|playbook|silk/i', $ua)) {
        return 'tablet';
    }
    if (preg_match('/mobi|android|iphone|ipod|phone|blackberry|opera mini/i', $ua)) {
        return 'mobile';
    }
    return 'desktop';
}

/**
 * Record a page view. Safe to call on every public GET request; it silently
 * skips admin/api/asset/AJAX requests and never throws.
 */
function track_pageview(): void
{
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }
        $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        // Skip non-content requests.
        if (preg_match('#/(admin|api|forms|assets|uploads)(/|$)#i', $path)) {
            return;
        }
        if (is_ajax() || is_logged_in()) {
            return; // don't count admins browsing the site
        }
        $ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $device = pv_device($ua);
        if ($device === 'bot') {
            return; // don't pollute stats with crawlers
        }

        // First tracked view of this PHP session == a unique visitor.
        $isUnique = empty($_SESSION['_pv_seen']) ? 1 : 0;
        $_SESSION['_pv_seen'] = true;

        $ip  = client_ip();
        $geo = pv_geo_cached($ip);                       // cache-only read (no network)
        $ok  = ($geo['status'] ?? '') === 'success';

        db_insert('page_views', [
            'url'          => substr($path, 0, 255),
            'referrer'     => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255) ?: null,
            'ip_address'   => $ip,
            'user_agent'   => $ua,
            'session_id'   => substr(session_id() ?: '', 0, 64),
            'device'       => $device,
            'country'      => $ok ? ($geo['country'] ?? null) : null,
            'country_code' => $ok ? ($geo['country_code'] ?? null) : null,
            'city'         => $ok ? ($geo['city'] ?? null) : null,
            'region'       => $ok ? ($geo['region'] ?? null) : null,
            'is_unique'    => $isUnique,
        ]);

        // Resolve geo for a never-seen IP AFTER the response is sent, so the
        // public page is never delayed by the external lookup.
        if (!$geo) {
            register_shutdown_function('pv_geo_resolve', $ip);
        }
    } catch (Throwable $e) {
        // Analytics must never break page rendering.
        error_log('[analytics] ' . $e->getMessage());
    }
}

/* =============================================================================
 |  GEO-IP RESOLUTION (free ip-api.com, cached once per IP in `ip_geo`)
 |============================================================================*/

/** Is this a routable public IP (not localhost / LAN / reserved)? */
function pv_ip_is_public(string $ip): bool
{
    return $ip !== '' && filter_var(
        $ip, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

/** Read the cached geo row for an IP (no network). Returns [] on miss. */
function pv_geo_cached(string $ip): array
{
    if ($ip === '') {
        return [];
    }
    try {
        return db_row("SELECT * FROM ip_geo WHERE ip = :ip", [':ip' => $ip]) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** Upsert a geo record into the cache. */
function pv_geo_store(string $ip, array $geo): void
{
    try {
        $g = array_merge([
            'status' => 'unknown', 'country' => null, 'country_code' => null,
            'region' => null, 'city' => null, 'lat' => null, 'lon' => null,
            'isp' => null, 'timezone' => null,
        ], $geo);
        db_query(
            "INSERT INTO ip_geo (ip,status,country,country_code,region,city,lat,lon,isp,timezone)
             VALUES (:ip,:status,:country,:cc,:region,:city,:lat,:lon,:isp,:tz)
             ON DUPLICATE KEY UPDATE status=VALUES(status), country=VALUES(country),
                country_code=VALUES(country_code), region=VALUES(region), city=VALUES(city),
                lat=VALUES(lat), lon=VALUES(lon), isp=VALUES(isp), timezone=VALUES(timezone)",
            [
                ':ip' => substr($ip, 0, 45), ':status' => $g['status'], ':country' => $g['country'],
                ':cc' => $g['country_code'], ':region' => $g['region'], ':city' => $g['city'],
                ':lat' => $g['lat'], ':lon' => $g['lon'], ':isp' => $g['isp'], ':tz' => $g['timezone'],
            ]
        );
    } catch (Throwable $e) {
        error_log('[analytics-geo store] ' . $e->getMessage());
    }
}

/**
 * Resolve an IP's geolocation via the free ip-api.com endpoint, cache it, and
 * backfill any recent page_views from the same IP. Runs from a shutdown hook.
 */
function pv_geo_resolve(string $ip): void
{
    try {
        if ($ip === '' || pv_geo_cached($ip)) {
            return; // already resolved by a concurrent request
        }
        if (!pv_ip_is_public($ip)) {
            pv_geo_store($ip, ['status' => 'private']); // localhost / LAN — never retry
            return;
        }
        $url  = 'http://ip-api.com/json/' . urlencode($ip)
              . '?fields=status,country,countryCode,regionName,city,lat,lon,isp,timezone';
        $body = pv_http_get($url, 1.5);
        $d    = $body ? json_decode($body, true) : null;

        if (is_array($d) && ($d['status'] ?? '') === 'success') {
            $geo = [
                'status'       => 'success',
                'country'      => $d['country'] ?? null,
                'country_code' => $d['countryCode'] ?? null,
                'region'       => $d['regionName'] ?? null,
                'city'         => $d['city'] ?? null,
                'lat'          => isset($d['lat']) ? (float) $d['lat'] : null,
                'lon'          => isset($d['lon']) ? (float) $d['lon'] : null,
                'isp'          => $d['isp'] ?? null,
                'timezone'     => $d['timezone'] ?? null,
            ];
            pv_geo_store($ip, $geo);
            db_update(
                'page_views',
                ['country' => $geo['country'], 'country_code' => $geo['country_code'], 'city' => $geo['city'], 'region' => $geo['region']],
                "ip_address = :ip AND country IS NULL AND created_at >= (NOW() - INTERVAL 2 DAY)",
                [':ip' => $ip]
            );
        } else {
            pv_geo_store($ip, ['status' => 'fail']);
        }
    } catch (Throwable $e) {
        error_log('[analytics-geo] ' . $e->getMessage());
    }
}

/** Minimal cURL GET with a hard timeout. Returns the body, or null on failure. */
function pv_http_get(string $url, float $timeout = 1.5): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_TIMEOUT_MS        => (int) round($timeout * 1000),
        CURLOPT_CONNECTTIMEOUT_MS => 900,
        CURLOPT_FOLLOWLOCATION    => false,
        CURLOPT_USERAGENT         => 'PWF-Analytics/1.0',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return is_string($body) ? $body : null;
}

/* =============================================================================
 |  QUERY HELPERS (used by admin/analytics.php)
 |============================================================================*/

/** Total page views within the last N days. */
function pv_total(int $days = 30): int
{
    return (int) db_value(
        "SELECT COUNT(*) FROM page_views WHERE created_at >= (NOW() - INTERVAL :d DAY)",
        [':d' => $days]
    );
}

/** Unique visitors (distinct sessions) within the last N days. */
function pv_unique_total(int $days = 30): int
{
    return (int) db_value(
        "SELECT COUNT(DISTINCT session_id) FROM page_views WHERE created_at >= (NOW() - INTERVAL :d DAY)",
        [':d' => $days]
    );
}

/** Views + unique visitors per day for the last N days (chart series). */
function pv_daily(int $days = 30): array
{
    $rows = db_all(
        "SELECT DATE(created_at) AS d, COUNT(*) AS views, COUNT(DISTINCT session_id) AS visitors
         FROM page_views WHERE created_at >= (NOW() - INTERVAL :d DAY)
         GROUP BY DATE(created_at) ORDER BY d ASC",
        [':d' => $days]
    );
    $map = [];
    foreach ($rows as $r) { $map[$r['d']] = $r; }
    $out = ['labels' => [], 'views' => [], 'visitors' => []];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $out['labels'][]   = date('d M', strtotime($day));
        $out['views'][]    = isset($map[$day]) ? (int) $map[$day]['views'] : 0;
        $out['visitors'][] = isset($map[$day]) ? (int) $map[$day]['visitors'] : 0;
    }
    return $out;
}

/** Top pages by views within the last N days. */
function pv_top_pages(int $days = 30, int $limit = 10): array
{
    return db_all(
        "SELECT url, COUNT(*) AS views FROM page_views
         WHERE created_at >= (NOW() - INTERVAL :d DAY)
         GROUP BY url ORDER BY views DESC LIMIT " . (int) $limit,
        [':d' => $days]
    );
}

/** Top referrers within the last N days. */
function pv_referrers(int $days = 30, int $limit = 8): array
{
    return db_all(
        "SELECT referrer, COUNT(*) AS views FROM page_views
         WHERE created_at >= (NOW() - INTERVAL :d DAY) AND referrer IS NOT NULL AND referrer <> ''
         GROUP BY referrer ORDER BY views DESC LIMIT " . (int) $limit,
        [':d' => $days]
    );
}

/** Device breakdown within the last N days. */
function pv_device_split(int $days = 30): array
{
    $rows = db_all(
        "SELECT device, COUNT(*) AS views FROM page_views
         WHERE created_at >= (NOW() - INTERVAL :d DAY) GROUP BY device",
        [':d' => $days]
    );
    $out = [];
    foreach ($rows as $r) { $out[$r['device']] = (int) $r['views']; }
    return $out;
}

/** Estimated bounce rate (% of sessions with exactly one page view). */
function pv_bounce_rate(int $days = 30): float
{
    $totalSessions = (int) db_value(
        "SELECT COUNT(*) FROM (SELECT session_id FROM page_views
         WHERE created_at >= (NOW() - INTERVAL :d DAY) GROUP BY session_id) t",
        [':d' => $days]
    );
    if ($totalSessions === 0) {
        return 0.0;
    }
    $single = (int) db_value(
        "SELECT COUNT(*) FROM (SELECT session_id FROM page_views
         WHERE created_at >= (NOW() - INTERVAL :d DAY) GROUP BY session_id HAVING COUNT(*) = 1) t",
        [':d' => $days]
    );
    return round($single / $totalSessions * 100, 1);
}

/** Average session duration in seconds (last..first view per session). */
function pv_avg_session_seconds(int $days = 30): int
{
    $avg = db_value(
        "SELECT AVG(dur) FROM (
            SELECT TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) AS dur
            FROM page_views WHERE created_at >= (NOW() - INTERVAL :d DAY)
            GROUP BY session_id
         ) t",
        [':d' => $days]
    );
    return (int) round((float) $avg);
}
