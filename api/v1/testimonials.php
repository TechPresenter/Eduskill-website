<?php
/**
 * GET /api/v1/testimonials — list active testimonials.
 */
require_once __DIR__ . '/bootstrap.php';
api_require_method('GET');

$rows = db_all(
    "SELECT id, name, designation, photo, message, rating
     FROM testimonials WHERE status = 1 ORDER BY sort_order ASC, id DESC"
);
foreach ($rows as &$r) {
    $r['photo_url'] = $r['photo'] ? upload_url($r['photo']) : null;
    $r['rating']    = (int) $r['rating'];
}
unset($r);
api_ok($rows);
