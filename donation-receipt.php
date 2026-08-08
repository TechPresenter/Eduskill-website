<?php
/**
 * =============================================================================
 *  Donation receipt (public, token-gated) — 80G printable receipt
 * =============================================================================
 *  ?id=<donation>&t=<hmac token>. The token (from the receipt email) prevents
 *  enumeration of donor receipts by guessing sequential ids.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/donation_documents.php';

$id = (int) get('id', 0);
$t  = (string) get('t', '');
$d  = $id ? find('donations', $id) : null;

if (!$d || $t === '' || !hash_equals(donation_receipt_token($id), $t)) {
    http_response_code(404);
    exit('Receipt not found.');
}
if ($d['status'] !== 'completed') {
    http_response_code(403);
    exit('A receipt is available only for completed donations.');
}
echo donation_receipt_html($d);
