<?php
/**
 * =============================================================================
 *  Admin — CKEditor 5 image upload endpoint (SimpleUploadAdapter).
 *  Receives the "upload" file, stores it via the standard image pipeline and
 *  returns { "url": "<public url>" } (or { "error": { "message": ... } }).
 *  Admin-authenticated; CSRF verified from the X-CSRF-Token header.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals(csrf_token(), $token)) {
    http_response_code(403);
    echo json_encode(['error' => ['message' => 'Your session expired — please reload the page and try again.']]);
    exit;
}

if (empty($_FILES['upload']['name'])) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'No file was received.']]);
    exit;
}

$up = upload_image($_FILES['upload'], 'blogs');
if (!$up['success']) {
    echo json_encode(['error' => ['message' => $up['error'] ?: 'Upload failed.']]);
    exit;
}

log_activity('create', 'media', 'CKEditor uploaded image ' . $up['path']);
echo json_encode(['url' => upload_url($up['path'])]);
