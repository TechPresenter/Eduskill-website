<?php
/**
 * Donation handler — records a pending donation (no live payment gateway).
 * On real deployment, integrate a gateway and update status on callback.
 */
require_once __DIR__ . '/../includes/bootstrap.php';


if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

$errors = validate($_POST, [
    'donor_name' => 'required|max:128',
    'amount'     => 'required|numeric',
]);
$amount = (float) post('amount', 0);
if ($amount < 1) {
    $errors['amount'] = 'Please enter a valid donation amount.';
}
if (post('email') !== '' && !is_email(post('email'))) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($errors) {
    json_error('Please correct the highlighted fields.', ['errors' => $errors]);
}

// Validate the optional campaign.
$campaignId = null;
if ((int) post('campaign_id', 0) > 0) {
    $campaign = find('campaigns', (int) post('campaign_id'));
    if ($campaign) {
        $campaignId = (int) $campaign['id'];
    }
}

/* Country selector: validate the number against the chosen country and keep
   the country name / ISO / dial code with it. Placed after require_csrf()
   and the field validation above, so it is unreachable on a bare GET and
   its error surfaces the same way as every other field error. */
$pwfCountry = country_capture('phone', false);
if (!$pwfCountry['ok']) {
    json_error($pwfCountry['error'], ['errors' => ['phone' => $pwfCountry['error']]]);
}

$id = db_insert('donations', [
    'campaign_id'    => $campaignId,
    'donor_name'     => clean(post('donor_name')),
    'email'          => clean(post('email', '')),
    'phone'          => $pwfCountry['phone'],
    'country_name' => $pwfCountry['columns']['country_name'],
    'country_iso'  => $pwfCountry['columns']['country_iso'],
    'country_dial' => $pwfCountry['columns']['country_dial'],
    'pan'            => clean(post('pan', '')),
    'address'        => clean(post('address', '')),
    'amount'         => $amount,
    'currency'       => 'INR',
    'payment_method' => clean(post('payment_method', 'Bank Transfer')),
    'message'        => clean(post('message', '')),
    'is_anonymous'   => post('is_anonymous') ? 1 : 0,
    'status'         => 'pending',
]);

// Thank-you email (best effort).
if (post('email') !== '') {
    try {
        send_mail(
            clean(post('email')),
            'Thank you for your donation',
            '<p>Dear ' . e(clean(post('donor_name'))) . ',</p>'
            . '<p>Thank you for your generous pledge of <strong>' . e(money($amount)) . '</strong>. '
            . 'Our team will share the payment details and your 80G receipt shortly.</p>'
        );
    } catch (Throwable $e) { /* ignore */ }
}

json_success('Thank you for your generosity! Your donation of ' . money($amount) . ' has been recorded. We will email you the payment details and receipt.', ['id' => $id]);
