<?php
/**
 * GET /api/v1/programs — list active programs
 * GET /api/v1/programs?slug=... — single program with its projects.
 */
require_once __DIR__ . '/bootstrap.php';
api_require_method('GET');

if (!empty($_GET['slug'])) {
    $program = db_row(
        "SELECT * FROM programs WHERE slug = :slug AND status = 'active' LIMIT 1",
        [':slug' => clean((string) $_GET['slug'])]
    );
    if (!$program) {
        api_fail('Program not found.', 404);
    }
    $program['image_url'] = $program['image'] ? upload_url($program['image']) : null;
    $program['projects']  = db_all(
        "SELECT id, title, slug, summary, image, location, status, progress
         FROM projects WHERE program_id = :pid ORDER BY sort_order ASC",
        [':pid' => $program['id']]
    );
    api_ok($program);
}

$programs = db_all(
    "SELECT id, title, slug, short_description, icon, image, color, is_featured
     FROM programs WHERE status = 'active' ORDER BY is_featured DESC, sort_order ASC"
);
foreach ($programs as &$p) {
    $p['image_url'] = $p['image'] ? upload_url($p['image']) : null;
}
unset($p);
api_ok($programs);
