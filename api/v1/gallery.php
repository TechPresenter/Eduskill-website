<?php
/**
 * GET /api/v1/gallery — list albums
 * GET /api/v1/gallery?album=slug — album with its media.
 */
require_once __DIR__ . '/bootstrap.php';
api_require_method('GET');

if (!empty($_GET['album'])) {
    $album = db_row(
        "SELECT * FROM gallery_albums WHERE slug = :slug AND status = 1 LIMIT 1",
        [':slug' => clean((string) $_GET['album'])]
    );
    if (!$album) {
        api_fail('Album not found.', 404);
    }
    $media = db_all(
        "SELECT id, title, file_path, type, caption FROM gallery_media
         WHERE album_id = :id ORDER BY sort_order ASC, id ASC",
        [':id' => $album['id']]
    );
    foreach ($media as &$m) {
        $m['url'] = upload_url($m['file_path']);
    }
    unset($m);
    $album['media'] = $media;
    api_ok($album);
}

$albums = db_all(
    "SELECT a.id, a.title, a.slug, a.description, a.cover_image, a.event_date,
            (SELECT COUNT(*) FROM gallery_media m WHERE m.album_id = a.id) AS media_count
     FROM gallery_albums a WHERE a.status = 1 ORDER BY a.sort_order ASC, a.id DESC"
);
foreach ($albums as &$a) {
    $a['cover_url'] = $a['cover_image'] ? upload_url($a['cover_image']) : null;
}
unset($a);
api_ok($albums);
