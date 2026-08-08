<?php
/**
 * =============================================================================
 *  Live search (AJAX) — returns grouped JSON results for the header minibar.
 *  GET ?q=<term>. Read-only; searches published/active content across the site.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$q = trim((string) get('q', ''));
if (mb_strlen($q) < 2) {
    json_response(['success' => true, 'query' => $q, 'groups' => [], 'total' => 0]);
}
$like = '%' . $q . '%';
$groups = [];
$total  = 0;

/** Helper to append a group when it has rows. */
$add = static function (string $type, string $icon, array $items) use (&$groups, &$total): void {
    if ($items) { $groups[] = ['type' => $type, 'icon' => $icon, 'items' => $items]; $total += count($items); }
};

try {
    // Blogs
    $rows = db_all(
        "SELECT title, slug, excerpt FROM blogs
         WHERE status='published' AND deleted_at IS NULL AND CONCAT_WS(' ', title, excerpt) LIKE :q
         ORDER BY published_at DESC LIMIT 4", [':q' => $like]);
    $add('Blogs', 'file-text', array_map(fn($r) => [
        'title' => $r['title'], 'meta' => excerpt($r['excerpt'] ?? '', 8),
        'url' => url('blog-details?slug=' . $r['slug']),
    ], $rows));

    // Programs
    $rows = db_all(
        "SELECT title, slug, short_description FROM programs
         WHERE status='active' AND deleted_at IS NULL AND CONCAT_WS(' ', title, short_description) LIKE :q
         ORDER BY sort_order LIMIT 4", [':q' => $like]);
    $add('Programs', 'layers', array_map(fn($r) => [
        'title' => $r['title'], 'meta' => excerpt($r['short_description'] ?? '', 8),
        'url' => $r['slug'] ? url('programs?slug=' . $r['slug']) : url('programs'),
    ], $rows));

    // Events
    $rows = db_all(
        "SELECT title, slug, start_datetime FROM events
         WHERE status='published' AND deleted_at IS NULL AND CONCAT_WS(' ', title, description) LIKE :q
         ORDER BY start_datetime DESC LIMIT 4", [':q' => $like]);
    $add('Events', 'calendar', array_map(fn($r) => [
        'title' => $r['title'], 'meta' => format_date($r['start_datetime'], 'd M Y'),
        'url' => url('events?slug=' . $r['slug']),
    ], $rows));

    // Campaigns
    $rows = db_all(
        "SELECT title, slug FROM campaigns
         WHERE status IN ('active','completed') AND deleted_at IS NULL AND CONCAT_WS(' ', title, description) LIKE :q
         ORDER BY id DESC LIMIT 4", [':q' => $like]);
    $add('Campaigns', 'megaphone', array_map(fn($r) => [
        'title' => $r['title'], 'meta' => 'Fundraiser',
        'url' => url('campaigns?slug=' . $r['slug']),
    ], $rows));

    // Testimonials / reviews
    $rows = db_all(
        "SELECT name, designation FROM testimonials
         WHERE status=1 AND CONCAT_WS(' ', name, message) LIKE :q
         ORDER BY sort_order LIMIT 3", [':q' => $like]);
    $add('Testimonials', 'quote', array_map(fn($r) => [
        'title' => $r['name'], 'meta' => $r['designation'] ?? 'Review',
        'url' => url('success-stories'),
    ], $rows));
} catch (Throwable $e) {
    error_log('[search] ' . $e->getMessage());
}

json_response([
    'success' => true,
    'query'   => $q,
    'groups'  => $groups,
    'total'   => $total,
    'all_url' => url('search?q=' . rawurlencode($q)),
]);
