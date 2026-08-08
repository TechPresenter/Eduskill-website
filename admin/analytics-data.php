<?php
/**
 * =============================================================================
 *  Admin — Visitor Analytics JSON endpoint (REST/AJAX).
 *  Admin-authenticated. Powers the live dashboard in admin/analytics.php.
 *    ?section=summary  (default) -> KPIs + all chart series + insights + realtime
 *    ?section=realtime           -> live snapshot only (polled every ~30s)
 *  All output is derived from real page_views data (read-only).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/analytics_report.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

try {
    $section = (string) get('section', 'summary');

    if ($section === 'realtime') {
        echo json_encode(['ok' => true, 'realtime' => anr_realtime()]);
        exit;
    }

    $f       = anr_filters();
    $summary = anr_summary($f);
    echo json_encode([
        'ok'       => true,
        'summary'  => $summary,
        'insights' => anr_insights($f, $summary),
        'realtime' => anr_realtime(),
        'facets'   => anr_facets($f),
        'filters'  => [
            'preset'  => $f['preset'], 'device' => $f['device'],
            'country' => $f['country'], 'state' => $f['state'], 'city' => $f['city'], 'source' => $f['source'],
            'start'   => substr($f['start'], 0, 10), 'end' => substr($f['end'], 0, 10),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Analytics query failed.']);
    error_log('[analytics-data] ' . $e->getMessage());
}
