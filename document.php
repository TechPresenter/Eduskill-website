<?php
/**
 * Public document view — renders an issued document for viewing / printing
 * (print-to-PDF). Accessed via ?t=<qr_token> (the QR links here).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/document_hub.php';

$token  = preg_replace('/[^a-f0-9]/', '', (string) get('t', ''));
$issued = $token !== '' ? dh_verify_token($token) : null;
$tpl    = $issued ? find('document_templates', (int) $issued['template_id']) : null;

if (!$issued || $issued['status'] !== 'issued' || !$tpl) {
    http_response_code(404);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Document not found</title><body style="font-family:sans-serif;text-align:center;padding:4rem;color:#334155"><h1>Document not available</h1><p>This document could not be found or has been revoked.</p></body>';
    exit;
}

// Public (recipient) access can be disabled per document type — then only a
// logged-in admin may open it.
if (!dh_user_enabled($tpl) && !(function_exists('is_logged_in') && is_logged_in())) {
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Document unavailable</title><body style="font-family:sans-serif;text-align:center;padding:4rem;color:#334155"><h1>Document unavailable</h1><p>This document is not available for public download.</p></body>';
    exit;
}

$data = json_decode((string) $issued['data'], true) ?: [];
$html = dh_render($tpl, $data, $issued);
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($tpl['doc_type'] ?: $tpl['name']) ?> · <?= e($issued['doc_no']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style><?= dh_styles() ?>
body{margin:0;padding:5rem 1.5rem 2rem;background:#e5e7eb;}
.pv-bar{position:fixed;top:0;left:0;right:0;background:#0f172a;color:#fff;padding:.7rem 1rem;display:flex;gap:.6rem;justify-content:center;align-items:center;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,.3)}
.pv-bar b{margin-right:auto;padding-left:.5rem;font-size:.9rem}
.pv-bar button,.pv-bar a{background:#063566;color:#fff;border:0;padding:.55rem 1rem;border-radius:9px;cursor:pointer;text-decoration:none;font:inherit;font-weight:600}
</style></head><body>
<div class="pv-bar no-print"><b><?= e($issued['doc_no']) ?></b><button onclick="window.print()">Print / Save as PDF</button><a href="<?= e(url('verify-document?t=' . $issued['qr_token'])) ?>">Verify</a></div>
<?= $html ?>
</body></html>
