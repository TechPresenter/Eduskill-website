<?php
/**
 * =============================================================================
 *  Admin — authenticated file gate for sensitive uploads
 * =============================================================================
 *  Applicant résumés (and any other PII upload routed through here) used to be
 *  linked with upload_url(), i.e. a plain Apache-served static path under
 *  /uploads. Anyone holding or guessing the URL could fetch them with no session
 *  at all — the filenames are random, but "unguessable URL" is not access
 *  control: URLs leak through referrers, proxy logs, browser history and
 *  forwarded links.
 *
 *  This endpoint puts the same files behind require_admin() and streams them:
 *      admin/secure-file?f=resumes/20260727-abc123.pdf
 *
 *  Hardening:
 *   - require_admin() first, before anything touches the filesystem.
 *   - The requested path must resolve (realpath) INSIDE one of ALLOWED_DIRS,
 *     which defeats ../ traversal and symlink escapes.
 *   - The directory must be on the allowlist, so this can never be turned into
 *     a reader for config.php or an arbitrary upload.
 *   - Content-Disposition is always "attachment" and the type is taken from a
 *     fixed map (never from the request), so a stored .html/.svg can't execute
 *     in the admin's origin.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

/**
 * Upload sub-directories this endpoint is allowed to serve.
 *
 *   resumes           — applicant CVs (career, internship, volunteer)
 *   admissions        — admission applicants' ID proofs and certificates
 *   documents         — scholarship applicants' documents, employee records,
 *                       issued certificate files
 *   student-documents — students' personal documents
 *   coordinator-docs  — community coordinator applicants' checklist documents
 *                       (photograph, ID, address proof, bank details, PAN)
 *   kanyadaan-docs    — Kanya Daan applicant families' identity, age, income
 *                       and bank documents
 *
 * All six are denied to Apache by a .htaccess in each directory, so this is
 * the only route to them. Public artwork must NOT be stored in these folders —
 * a denied directory cannot be rendered by <img> on a public page. That is why
 * uploads/students (the student photograph shown by verify-student.php) and
 * the Document Hub's ID photo stay in normal, publicly-served folders.
 */
const ALLOWED_DIRS = ['resumes', 'admissions', 'documents', 'student-documents', 'coordinator-docs', 'kanyadaan-docs'];

$rel = (string) get('f', '');
if ($rel === '') {
    http_response_code(400);
    exit('No file requested.');
}

// Reject anything that isn't a simple "<dir>/<filename>" pair up front.
$rel = str_replace('\\', '/', $rel);
if (str_contains($rel, "\0") || !preg_match('#^([a-z0-9_-]+)/([A-Za-z0-9._-]+)$#', $rel, $m)) {
    http_response_code(400);
    exit('Invalid file reference.');
}
[$all, $dir, $name] = $m;

if (!in_array($dir, ALLOWED_DIRS, true)) {
    http_response_code(403);
    exit('That file is not available through this endpoint.');
}

// Resolve and prove containment: realpath() collapses any ../ and follows
// symlinks, so comparing the result to the resolved base is the real check.
$base = realpath(UPLOAD_PATH . '/' . $dir);
$file = realpath(UPLOAD_PATH . '/' . $rel);
if ($base === false || $file === false || !is_file($file) || !str_starts_with($file, $base . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('File not found.');
}

/* Fixed extension -> type map. Never echo a browser-executable type. */
$types = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt'  => 'text/plain',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    // NOTE: .svg is deliberately absent — it is scriptable, and these files are
    // attacker-supplied. An SVG here falls through to octet-stream.
];
$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = $types[$ext] ?? 'application/octet-stream';

log_activity('view', 'secure-file', 'Downloaded ' . $rel);

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
readfile($file);
exit;
