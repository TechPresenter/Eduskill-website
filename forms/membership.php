<?php
/** Membership application handler. */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

$errors = validate($_POST, [
    'name'  => 'required|max:128',
    'email' => 'required|email',
    'phone' => 'required|max:32',
]);
if ($errors) json_error('Please correct the highlighted fields.', ['errors' => $errors]);

$planId = (int) post('plan_id', 0) ?: null;
if ($planId && !find('membership_plans', $planId)) {
    $planId = null;
}

/* Country selector: validate the number against the chosen country and keep
   the country name / ISO / dial code with it. Placed after require_csrf()
   and the field validation above, so it is unreachable on a bare GET and
   its error surfaces the same way as every other field error. */
$pwfCountry = country_capture('phone', true);
if (!$pwfCountry['ok']) {
    json_error($pwfCountry['error'], ['errors' => ['phone' => $pwfCountry['error']]]);
}

db_insert('membership_applications', [
    'plan_id'    => $planId,
    'name'       => clean(post('name')),
    'email'      => clean(post('email')),
    'phone'      => $pwfCountry['phone'],
    'country_name' => $pwfCountry['columns']['country_name'],
    'country_iso'  => $pwfCountry['columns']['country_iso'],
    'country_dial' => $pwfCountry['columns']['country_dial'],
    'address'    => clean(post('address', '')),
    'occupation' => clean(post('occupation', '')),
    'message'    => clean(post('message', '')),
    'status'     => 'new',
]);
json_success('Thank you for applying for membership! We will review your application and contact you.');
