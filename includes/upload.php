<?php
/**
 * =============================================================================
 *  Secure file uploads
 * =============================================================================
 *  - Whitelist by extension AND MIME type
 *  - Enforce a maximum size (UPLOAD_MAX_BYTES)
 *  - Random, unguessable filenames
 *  - Per-type target folder under /uploads
 *  - Real image validation via getimagesize()
 *
 *  Returns: ['success' => bool, 'path' => 'folder/filename.ext', 'error' => '']
 *  The stored 'path' is relative to /uploads and is what you save in the DB.
 * =============================================================================
 */

declare(strict_types=1);

/** Allowed MIME types keyed by extension. */
function upload_mime_map(): array
{
    return [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'svg'  => ['image/svg+xml', 'text/plain', 'text/xml', 'application/xml'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'txt'  => ['text/plain'],
    ];
}

/**
 * Handle one uploaded file.
 *
 * @param array  $file    A single entry from $_FILES.
 * @param string $folder  Target subfolder under /uploads (e.g. 'blogs').
 * @param array  $opts    ['allowed' => 'jpg,png', 'max' => bytes, 'image_only' => bool]
 */
function upload_file(array $file, string $folder = 'media', array $opts = []): array
{
    $fail = static fn(string $msg): array => ['success' => false, 'path' => null, 'error' => $msg];

    // No file / upload error.
    if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $fail('No file was uploaded.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return $fail(upload_error_message((int) $file['error']));
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('Invalid upload source.');
    }

    // Size. A Security Center setting (upload_max_mb) can raise/lower the global
    // cap; an explicit opts['max'] still wins for a specific caller.
    $settingMax = function_exists('get_setting') ? (int) get_setting('upload_max_mb', 0) : 0;
    $globalMax  = $settingMax > 0 ? $settingMax * 1024 * 1024 : UPLOAD_MAX_BYTES;
    $maxBytes   = (int) ($opts['max'] ?? $globalMax);
    if ($file['size'] > $maxBytes) {
        return $fail('File exceeds the maximum size of ' . human_filesize($maxBytes) . '.');
    }
    if ($file['size'] === 0) {
        return $fail('The uploaded file is empty.');
    }

    // Extension whitelist.
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = array_filter(array_map('trim',
        explode(',', $opts['allowed'] ?? (ALLOWED_IMAGE_EXT . ',' . ALLOWED_DOC_EXT))
    ));
    if (!in_array($ext, $allowed, true)) {
        return $fail('File type ".' . $ext . '" is not allowed.');
    }

    // SVG can be disabled globally (scriptable image format) from the Security Center.
    if ($ext === 'svg' && function_exists('get_setting') && (int) get_setting('upload_block_svg', 0) === 1) {
        return $fail('SVG uploads are disabled by the security policy.');
    }

    // MIME check against whitelist. For mapped extensions the real content type
    // MUST be detectable and match — no soft fallback to the client-supplied type
    // (which is spoofable). Unmapped extensions keep the lenient path.
    $mimeMap = upload_mime_map();
    if (isset($mimeMap[$ext])) {
        $detected = mime_content_type($file['tmp_name']);
        if ($detected === false) {
            return $fail('The file type could not be verified.');
        }
        if (!in_array($detected, $mimeMap[$ext], true)) {
            return $fail('The file content does not match its extension.');
        }
    } else {
        $detected = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
    }

    // Image-specific validation.
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $isImage = in_array($ext, $imageExts, true);
    if (!empty($opts['image_only']) && !$isImage && $ext !== 'svg') {
        return $fail('Only image files are allowed here.');
    }
    if ($isImage) {
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return $fail('The file is not a valid image.');
        }

        /* getimagesize() and mime_content_type() both read only the HEADER, so a
           polyglot passes both. A file whose entire contents were
               GIF89a<?php echo "x"; ?>
           was accepted as a genuine GIF: getimagesize() reported it as
           image/gif at 16188x26736 (it parsed the PHP text as dimension bytes)
           and mime_content_type() agreed, because both stop after the magic
           number. Nothing downstream looked at the rest of the file.

           uploads/.htaccess already stops such a file EXECUTING (engine off,
           handlers removed, script extensions denied), so this was not remote
           code execution. But "we store attacker-supplied PHP and rely on one
           .htaccess never being lost" is a thinner margin than it needs to be —
           a copy to another directory, a host that ignores .htaccess, or a
           future image pipeline would each remove it.

           So: scan the WHOLE file for server-side script markers.

           Every marker here must be long enough not to occur in compressed
           pixel data by chance. The first version of this check also looked for
           "<%" (ASP/JSP), and that ONE alternative rejected every genuine photo
           tested — 29 hits across three ordinary JPEG/PNG images, because two
           specific bytes recur roughly every 64 KB of entropy. A guard that
           blocks all real uploads is a worse bug than the polyglot it was
           added to stop, and ASP tags are meaningless on this stack anyway.
           Measured on real images: <?php, <?=, <script and #!/bin all score
           zero; only the two-byte pattern was unsafe. */
        $bytes = (string) @file_get_contents($file['tmp_name']);
        if ($bytes !== '' && preg_match('/<\?php|<\?=|<script\b|#!\s*\/(bin|usr)/i', $bytes)) {
            return $fail('That image contains embedded code and was rejected.');
        }
    }

    // SVG: positively require an <svg> root so a text/HTML payload renamed .svg
    // (which mime_content_type() reports as text/plain) is rejected.
    if ($ext === 'svg') {
        $svg = (string) @file_get_contents($file['tmp_name']);
        if (stripos($svg, '<svg') === false) {
            return $fail('The file is not a valid SVG image.');
        }

        /* SVG is a scriptable document format, so an <svg> root is necessary but
           nowhere near sufficient: <svg><script>alert(1)</script></svg> passed
           the old check untouched.

           There IS a kill switch for this — upload_block_svg — but it defaults
           to 0 and NOTHING anywhere sets it: no admin screen writes the key and
           no row exists in `settings`. A control nobody can reach is not a
           control, so blocking the dangerous constructs is what actually has to
           carry the weight. (uploads/.htaccess serves SVG as an attachment
           under a `sandbox` CSP, which is the second layer, not the first.)

           Reject rather than strip: silently rewriting someone's artwork and
           storing the result is worse than telling them the file is not
           acceptable. */
        if (preg_match('/<script\b|<foreignObject\b|javascript:|<!ENTITY|\bon[a-z]+\s*=/i', $svg)) {
            return $fail('That SVG contains scripting and was rejected. Please upload a plain image.');
        }
    }

    // Build destination.
    $folder   = preg_replace('/[^a-z0-9_\-]/i', '', $folder) ?: 'media';
    $destDir  = UPLOAD_PATH . '/' . $folder;
    if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        return $fail('Could not create the upload directory.');
    }

    $filename = date('Ymd') . '-' . str_random(16) . '.' . $ext;
    $destPath = $destDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return $fail('Failed to save the uploaded file.');
    }
    @chmod($destPath, 0644);

    // Strip active content from stored SVGs (scripts / event handlers / js: URIs)
    // so an uploaded SVG can never execute when opened directly from /uploads.
    if ($ext === 'svg') {
        upload_sanitize_svg($destPath);
    }

    return [
        'success' => true,
        'path'    => $folder . '/' . $filename, // relative to /uploads
        'error'   => '',
        'size'    => (int) $file['size'],
        'mime'    => $detected,
        'ext'     => $ext,
    ];
}

/** Convenience wrapper for image-only uploads. */
function upload_image(array $file, string $folder = 'images', array $opts = []): array
{
    return upload_file($file, $folder, array_merge(
        ['allowed' => ALLOWED_IMAGE_EXT, 'image_only' => true],
        $opts
    ));
}

/** Convenience wrapper for document uploads. */
function upload_document(array $file, string $folder = 'documents', array $opts = []): array
{
    return upload_file($file, $folder, array_merge(['allowed' => ALLOWED_DOC_EXT], $opts));
}

/**
 * Delete a previously-uploaded file given its stored relative path.
 */
function delete_upload(?string $relativePath): bool
{
    if (empty($relativePath)) {
        return false;
    }
    // Prevent path traversal.
    $relativePath = str_replace(['..', "\0"], '', $relativePath);
    $full = UPLOAD_PATH . '/' . ltrim($relativePath, '/');
    if (is_file($full)) {
        return @unlink($full);
    }
    return false;
}

/**
 * Neutralise active content in an SVG file in place. Removes <script> and
 * <foreignObject> in every form (paired, self-closing, and namespace-prefixed
 * like <s:script>), all on*= event-handler attributes (quoted and unquoted),
 * and href/xlink:href/src values that resolve to a script-capable URI scheme
 * (javascript:/vbscript:/data:non-image) AFTER decoding entities + stripping
 * obfuscating whitespace — so encoded payloads like `&#106;avascript:` cannot
 * slip through. Belt-and-suspenders on top of the uploads/.htaccess rule that
 * forces SVGs to download and forbids their scripts via CSP.
 */
function upload_sanitize_svg(string $path): void
{
    $svg = @file_get_contents($path);
    if ($svg === false || $svg === '') {
        return;
    }
    // <script>/<foreignObject> in any namespace, self-closing OR paired.
    foreach (['script', 'foreignObject'] as $tag) {
        $svg = preg_replace('#<\s*(?:[a-z0-9_-]+:)?' . $tag . '\b[^>]*/\s*>#is', '', (string) $svg);              // self-closing
        $svg = preg_replace('#<\s*(?:[a-z0-9_-]+:)?' . $tag . '\b[^>]*>.*?<\s*/\s*(?:[a-z0-9_-]+:)?' . $tag . '\s*>#is', '', (string) $svg); // paired
    }
    // Event-handler attributes: quoted and unquoted.
    $svg = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', (string) $svg);
    $svg = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', (string) $svg);
    $svg = preg_replace('#\son\w+\s*=\s*[^\s>]+#i', '', (string) $svg);
    // Dangerous URI schemes in link/image attributes — normalise before matching.
    $svg = preg_replace_callback(
        '#(href|xlink:href|src)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))#i',
        static function (array $m): string {
            $raw = $m[3] !== '' ? $m[3] : ($m[4] !== '' ? $m[4] : ($m[5] ?? ''));
            $val = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $val = preg_replace('/[\s\x00-\x20]+/', '', (string) $val);
            $bad = preg_match('#^(javascript|vbscript):#i', (string) $val)
                || (preg_match('#^data:#i', (string) $val) && !preg_match('#^data:image/(png|jpe?g|gif|webp);#i', (string) $val));
            return $bad ? $m[1] . '="#"' : $m[0];
        },
        (string) $svg
    ) ?? $svg;
    @file_put_contents($path, (string) $svg);
}

/** Human readable file size. */
function human_filesize(int $bytes, int $decimals = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
    $factor = min($factor, count($units) - 1);
    return round($bytes / (1024 ** $factor), $decimals) . ' ' . $units[$factor];
}

/** Translate a PHP upload error code into a message. */
function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write the file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
        default               => 'Unknown upload error.',
    };
}
