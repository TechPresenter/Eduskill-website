<?php
/**
 * =============================================================================
 *  Admin — Printable 80G donation receipt (standalone, no admin chrome)
 * =============================================================================
 *  ?id=<donations id>. Renders the official 80G receipt for a single completed
 *  donation. Assigns a receipt number on first view if one is not set yet.
 *  Browser print-to-PDF — no PDF library required. No admin chrome.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/donation_documents.php';

$id = (int) get('id', 0);

$d = $id ? find('donations', $id) : null;
if (!$d || $d['status'] !== 'completed') {
    set_flash('error', 'A receipt is available only for completed donations.');
    redirect(admin_url('donations'));
}

// Assign a receipt number on first view (kept for future lookups + the email link).
if (empty($d['receipt_no'])) {
    $d['receipt_no'] = donation_receipt_no($id);
    db_update('donations', ['receipt_no' => $d['receipt_no']], 'id = :id', [':id' => $id]);
}

echo donation_receipt_html($d);
