<?php
/**
 * REST: /api/campaigns.php
 *   GET     ...              list published campaigns (public)
 *   GET     ...?id=1         one campaign (public)
 *   POST    ...              create        (auth: campaigns.manage)
 *   PUT     ...?id=1         update        (auth: campaigns.manage)
 *   DELETE  ...?id=1         soft-delete   (auth: campaigns.manage)
 * Returns JSON only.
 */
require __DIR__ . '/../includes/config.php';

$method = request_method();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/* ---- reads (public) ---- */
if ($method === 'GET') {
    if ($id > 0) {
        $c = db_one('SELECT * FROM campaigns WHERE id = ? AND deleted_at IS NULL', [$id]);
        $c === null ? json_error('Campaign not found.', 404) : json_ok(['data' => $c]);
    }
    $rows = db_all('SELECT * FROM campaigns WHERE deleted_at IS NULL ORDER BY is_featured DESC, id DESC');
    json_ok(['data' => $rows]);
}

/* ---- writes (auth + permission + CSRF) ---- */
api_require('campaigns.manage');
$in = api_body();
if (!verify_csrf($in['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    json_error('Invalid or expired security token.', 400);
}

/** Build a clean, typed column set from input (rupees -> paise for money). */
$build = function (array $in): array {
    return [
        'title' => trim((string) ($in['title'] ?? '')),
        'summary' => trim((string) ($in['summary'] ?? '')),
        'description' => clean_html((string) ($in['description'] ?? '')),
        'image' => trim((string) ($in['image'] ?? '')),
        'category' => trim((string) ($in['category'] ?? '')),
        'goal_amount' => (int) round((float) ($in['goal_amount'] ?? 0) * 100),
        'raised_amount' => (int) round((float) ($in['raised_amount'] ?? 0) * 100),
        'donor_count' => (int) ($in['donor_count'] ?? 0),
        'beneficiary' => trim((string) ($in['beneficiary'] ?? '')),
        'starts_at' => ($in['starts_at'] ?? '') !== '' ? (string) $in['starts_at'] : null,
        'ends_at' => ($in['ends_at'] ?? '') !== '' ? (string) $in['ends_at'] : null,
        'status' => in_array($in['status'] ?? '', ['draft', 'active', 'completed', 'closed'], true) ? (string) $in['status'] : 'draft',
        'is_featured' => !empty($in['is_featured']) ? 1 : 0,
    ];
};

if ($method === 'POST') {
    $data = $build($in);
    if ($data['title'] === '') {
        json_error('Title is required.', 422);
    }
    $data['slug'] = unique_slug('campaigns', str_slug($data['title']));
    $cols = array_keys($data);
    $bind = [];
    foreach ($data as $k => $v) {
        $bind[":{$k}"] = $v;
    }
    $newId = db_insert(
        'INSERT INTO campaigns (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')',
        $bind
    );
    activity_log('campaign.created', (int) ($_SESSION['uid'] ?? 0));
    json_ok(['id' => $newId], 'Campaign created.');
}

if ($method === 'PUT' || $method === 'PATCH') {
    if ($id <= 0) {
        json_error('Missing id.', 400);
    }
    $data = $build($in);
    if ($data['title'] === '') {
        json_error('Title is required.', 422);
    }
    $data['slug'] = unique_slug('campaigns', str_slug($data['title']), $id);
    $set = implode(', ', array_map(fn ($c) => "{$c} = :{$c}", array_keys($data)));
    $bind = [':id' => $id];
    foreach ($data as $k => $v) {
        $bind[":{$k}"] = $v;
    }
    db_exec("UPDATE campaigns SET {$set} WHERE id = :id AND deleted_at IS NULL", $bind);
    activity_log('campaign.updated', (int) ($_SESSION['uid'] ?? 0));
    json_ok([], 'Campaign updated.');
}

if ($method === 'DELETE') {
    if ($id <= 0) {
        json_error('Missing id.', 400);
    }
    db_exec('UPDATE campaigns SET deleted_at = NOW() WHERE id = ?', [$id]);
    activity_log('campaign.deleted', (int) ($_SESSION['uid'] ?? 0));
    json_ok([], 'Campaign deleted.');
}

json_error('Method not allowed.', 405);
