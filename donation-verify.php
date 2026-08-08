<?php
/**
 * =============================================================================
 *  Razorpay checkout callback — verify signature, then complete the donation
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('/donate-pay');
}
require_csrf();

$oid = (string) post('razorpay_order_id', '');
$pid = (string) post('razorpay_payment_id', '');
$sig = (string) post('razorpay_signature', '');

/*
 * Resolve the donation from the GATEWAY ORDER ID, never from a client-supplied
 * row id.
 *
 * This endpoint has no login (it is a checkout callback) and CSRF is no barrier
 * — the caller is the direct requester, and every public page hands an anonymous
 * visitor a session plus a csrf-token in its <meta> tag. When the row was chosen
 * by `post('donation_id')`, any visitor could POST a sequential id with a
 * deliberately wrong order id and the mismatch branch would write
 * status='failed' over ANY donation, including settled, receipted ones — walking
 * the whole table in a loop. The order id is a CSPRNG value that only the real
 * payer's checkout session holds, so looking the row up by it makes the request
 * self-authorising.
 */
$d = $oid !== '' ? db_row('SELECT * FROM donations WHERE gateway_order_id = :o LIMIT 1', [':o' => $oid]) : null;
if (!$d) {
    set_flash('error', 'Payment could not be verified.');
    redirect('/donate-pay');
}
$donationId = (int) $d['id'];

donation_load_gateway('razorpay');
if (razorpay_verify_payment_signature($oid, $pid, $sig)) {
    donation_mark_paid($donationId, ['gateway' => 'razorpay', 'order_id' => $oid, 'payment_id' => $pid, 'method' => 'Razorpay']);
    redirect('/donation-return?donation=' . $donationId . '&gateway=razorpay&t=' . donation_receipt_token($donationId));
}

/*
 * Signature check failed. Only a still-pending donation may be marked failed —
 * a replayed or malicious callback must never downgrade one that already
 * settled (mirrors the atomic state guard in pay_reconcile()). Recompute the
 * campaign totals afterwards, as admin/donations.php does, so raised_amount
 * cannot drift away from the donations it is derived from.
 */
$changed = db_update('donations', ['status' => 'failed'], "id = :id AND status = 'pending'", [':id' => $donationId]);
if ($changed && !empty($d['campaign_id'])) {
    campaign_recompute((int) $d['campaign_id']);
}
set_flash('error', 'Payment signature verification failed. If you were charged, please contact us.');
redirect('/donate-pay');
