<?php
/**
 * Download counter — increments a document's download count then serves the
 * file. Usage:  <a href="<?= url('forms/download?id=' . $doc['id']) ?>">
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$id  = (int) get('id', 0);
$doc = $id ? find('documents', $id) : null;

if (!$doc || (int) $doc['status'] !== 1 || empty($doc['file_path'])) {
    http_response_code(404);
    exit('File not found.');
}

// Resolve the real file on disk (path stored relative to /uploads).
$safe = str_replace(['..', "\0"], '', $doc['file_path']);
$full = UPLOAD_PATH . '/' . ltrim($safe, '/');

if (!is_file($full)) {
    http_response_code(404);
    exit('File not found.');
}

// Count the download (best effort).
try {
    db_update('documents', ['downloads' => (int) $doc['downloads'] + 1], 'id = :id', [':id' => $id]);
} catch (Throwable $e) { /* ignore */ }

// Stream the file as an attachment.
$filename = $doc['title'] !== ''
    ? preg_replace('/[^\w\-. ]/', '', $doc['title']) . '.' . pathinfo($full, PATHINFO_EXTENSION)
    : basename($full);

header('Content-Type: ' . (mime_content_type($full) ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
readfile($full);
exit;
