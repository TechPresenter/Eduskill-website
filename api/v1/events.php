<?php
/**
 * GET /api/v1/events?type=upcoming|past — list events
 * GET /api/v1/events?slug=... — single event with registration count.
 */
require_once __DIR__ . '/bootstrap.php';
api_require_method('GET');

if (!empty($_GET['slug'])) {
    $event = db_row(
        "SELECT * FROM events WHERE slug = :slug AND status = 'published' LIMIT 1",
        [':slug' => clean((string) $_GET['slug'])]
    );
    if (!$event) {
        api_fail('Event not found.', 404);
    }
    $event['registered'] = db_count('event_registrations', 'event_id = :e AND status <> :c',
        [':e' => $event['id'], ':c' => 'cancelled']);
    $event['image_url'] = $event['image'] ? upload_url($event['image']) : null;
    api_ok($event);
}

$type   = $_GET['type'] ?? 'upcoming';
$params = [];
$where  = "status = 'published'";
if ($type === 'past') {
    $where .= ' AND start_datetime < NOW()';
    $order  = 'start_datetime DESC';
} else {
    $where .= ' AND start_datetime >= NOW()';
    $order  = 'start_datetime ASC';
}

api_paginated(
    "SELECT id, title, slug, excerpt, image, location, venue, start_datetime, end_datetime,
            capacity, registration_required
     FROM events WHERE $where ORDER BY $order",
    $params, 12
);
