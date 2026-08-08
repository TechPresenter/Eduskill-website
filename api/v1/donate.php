<?php
/**
 * POST /api/v1/donate — record a pending donation (JSON or form body).
 * Integrate a real payment gateway on deployment and update status via callback.
 */
require_once __DIR__ . '/bootstrap.php';
api_require_method('POST');
api_require_auth();
api_throttle('donate-form');

$in = api_input();
$errors = validate($in, [
    'donor_name' => 'required|max:128',
    'amount'     => 'required|numeric',
]);
$amount = (float) ($in['amount'] ?? 0);
if ($amount < 1) {
    $errors['amount'] = 'Invalid amount.';
}
if ($errors) {
    api_fail('Validation failed: ' . implode(' ', $errors), 422);
}

$campaignId = null;
if ((int) ($in['campaign_id'] ?? 0) > 0 && find('campaigns', (int) $in['campaign_id'])) {
    $campaignId = (int) $in['campaign_id'];
}

$id = db_insert('donations', [
    'campaign_id'    => $campaignId,
    'donor_name'     => clean($in['donor_name'] ?? ''),
    'email'          => clean($in['email'] ?? ''),
    'phone'          => clean($in['phone'] ?? ''),
    'amount'         => $amount,
    'currency'       => 'INR',
    'payment_method' => clean($in['payment_method'] ?? 'API'),
    'message'        => clean($in['message'] ?? ''),
    'is_anonymous'   => !empty($in['is_anonymous']) ? 1 : 0,
    'status'         => 'pending',
]);

api_ok(['id' => $id, 'amount' => $amount, 'currency' => 'INR', 'status' => 'pending'], [], 201);
