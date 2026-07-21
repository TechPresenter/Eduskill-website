<?php
/**
 * POST /api/upload.php — authenticated file upload. Returns { ok, path, url } (path is relative to
 * /assets, e.g. "uploads/2026/07/ab12.jpg"; url is the full asset URL).
 *
 * Security (defence in depth):
 *   1. auth + CSRF.
 *   2. size + MIME(finfo) + extension allowlist — the three must agree.
 *   3. a fresh, random filename is generated — the client's filename is never used, so ".php.jpg"
 *      tricks can't reach disk.
 *   4. IMAGES are RE-ENCODED through GD: the output is a brand-new image built from pixel data, so
 *      any PHP/script bytes hidden in the original are discarded. This is the real control.
 *   5. the uploads folder has its own .htaccess that refuses to execute anything.
 */
require __DIR__ . '/../includes/config.php';

if (request_method() !== 'POST') {
    json_error('Method not allowed.', 405);
}
api_require('media.upload');
if (!verify_csrf($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    json_error('Invalid or expired security token.', 400);
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_error('No file received or upload failed.', 422);
}
if (!is_uploaded_file($file['tmp_name'])) {
    json_error('Invalid upload.', 400);
}

$maxImage = 5 * 1024 * 1024;      // 5 MB
$maxDoc = 10 * 1024 * 1024;       // 10 MB

// image mime => [extension, GD loader, GD saver]
$images = [
    'image/jpeg' => ['jpg', 'imagecreatefromjpeg', 'imagejpeg'],
    'image/png' => ['png', 'imagecreatefrompng', 'imagepng'],
    'image/webp' => ['webp', 'imagecreatefromwebp', 'imagewebp'],
];
$docs = ['application/pdf' => 'pdf'];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($file['tmp_name']);
$size = (int) $file['size'];

// Build target folder assets/uploads/YYYY/MM
$rel = 'uploads/' . date('Y') . '/' . date('m');
$dir = BASE_PATH . '/assets/' . $rel;
if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
    json_error('Could not create the upload folder.', 500);
}
$name = bin2hex(random_bytes(8)) . '-' . substr((string) time(), -6);

try {
    if (isset($images[$mime])) {
        if ($size > $maxImage) {
            json_error('Image is too large (max 5 MB).', 422);
        }
        [$ext, $loader, $saver] = $images[$mime];
        $img = @$loader($file['tmp_name']);
        if ($img === false) {
            json_error('That image could not be read.', 422);
        }
        // Downscale very large images (longest side 2000px) — keeps files light.
        $w = imagesx($img);
        $h = imagesy($img);
        $maxDim = 2000;
        if ($w > $maxDim || $h > $maxDim) {
            $scale = $maxDim / max($w, $h);
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $resized = imagecreatetruecolor($nw, $nh);
            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
            $w = $nw;
            $h = $nh;
        }
        $path = $rel . '/' . $name . '.' . $ext;
        $full = BASE_PATH . '/assets/' . $path;
        $mime === 'image/jpeg' ? $saver($img, $full, 85) : $saver($img, $full);
        imagedestroy($img);
    } elseif (isset($docs[$mime])) {
        if ($size > $maxDoc) {
            json_error('File is too large (max 10 MB).', 422);
        }
        $ext = $docs[$mime];
        $path = $rel . '/' . $name . '.' . $ext;
        $full = BASE_PATH . '/assets/' . $path;
        if (!move_uploaded_file($file['tmp_name'], $full)) {
            json_error('Could not save the file.', 500);
        }
        $w = $h = null;
    } else {
        json_error('Unsupported file type. Allowed: JPG, PNG, WebP, PDF.', 422);
    }
} catch (Throwable $e) {
    error_log('[upload] ' . $e->getMessage());
    json_error('Upload failed.', 500);
}

// Record in the media library.
try {
    db_insert(
        'INSERT INTO media (disk, path, filename, original_name, mime, size_bytes, width, height, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        ['public', $path, basename($path), substr((string) ($file['name'] ?? ''), 0, 255), $mime, filesize($full) ?: $size, $w, $h, (int) ($_SESSION['uid'] ?? 0)]
    );
} catch (Throwable $e) {
    error_log('[upload media record] ' . $e->getMessage());
}

json_ok(['path' => $path, 'url' => asset($path)], 'Uploaded.');
