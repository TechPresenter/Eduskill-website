<?php
/**
 * GET /api/v1/team?leadership=1 — list active team members.
 */
require_once __DIR__ . '/bootstrap.php';
api_require_method('GET');

$where  = 'status = 1';
$params = [];
if (isset($_GET['leadership']) && $_GET['leadership'] !== '0') {
    $where .= ' AND is_leadership = 1';
}

$members = db_all(
    "SELECT id, name, slug, designation, department, bio, photo, socials, is_leadership
     FROM team_members WHERE $where ORDER BY is_leadership DESC, sort_order ASC, id ASC",
    $params
);
foreach ($members as &$m) {
    $m['photo_url'] = $m['photo'] ? upload_url($m['photo']) : null;
    $m['socials']   = json_column($m['socials']);
}
unset($m);
api_ok($members);
