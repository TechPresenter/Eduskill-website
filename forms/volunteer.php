<?php
/**
 * Volunteer application handler — optional résumé upload.
 */
require_once __DIR__ . '/../includes/bootstrap.php';


if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

/* Honeypot — a hidden field humans never fill. A filled one means a bot, so the
   submission is dropped while the sender is told it worked.

   It is LOGGED, because that trade is only safe while the field is genuinely
   untouchable by humans. This field used to be called 'website_hp', and
   Chrome and password managers autofill anything whose name starts with
   'website' — so real applications were being destroyed silently, and the
   only visible symptom was a success message with no reference number.
   If these lines ever start appearing for real people, the name is wrong
   again. */
if (trim((string) post('pwf_zq')) !== '') {
    error_log('[honeypot] ' . basename(__FILE__) . ' dropped a submission from ' . client_ip());
    json_success('Thank you for volunteering! Our team will contact you soon.');
}
if (!pwf_throttle('volunteer-form', 5, 300)) {
    json_error('Too many submissions — please try again in a few minutes.', [], 429);
}

$errors = validate($_POST, [
    'name'  => 'required|max:128',
    'email' => 'required|email',
    'phone' => 'required|max:32',
]);
if ($errors) {
    json_error('Please correct the highlighted fields.', ['errors' => $errors]);
}

// Optional résumé.
$resumePath = null;
if (!empty($_FILES['resume']['name'])) {
    $up = upload_document($_FILES['resume'], 'resumes');
    if (!$up['success']) {
        json_error($up['error'], ['errors' => ['resume' => $up['error']]]);
    }
    $resumePath = $up['path'];
}

/* Country selector: validate the number against the chosen country and keep
   the country name / ISO / dial code with it. Placed after require_csrf()
   and the field validation above, so it is unreachable on a bare GET and
   its error surfaces the same way as every other field error. */
$pwfCountry = country_capture('phone', true);
if (!$pwfCountry['ok']) {
    json_error($pwfCountry['error'], ['errors' => ['phone' => $pwfCountry['error']]]);
}

db_insert('volunteers', [
    'name'             => clean(post('name')),
    'email'            => clean(post('email')),
    'phone'            => $pwfCountry['phone'],
    'country_name' => $pwfCountry['columns']['country_name'],
    'country_iso'  => $pwfCountry['columns']['country_iso'],
    'country_dial' => $pwfCountry['columns']['country_dial'],
    'city'             => clean(post('city', '')),
    'address'          => clean(post('address', '')),
    'area_of_interest' => clean(post('area_of_interest', '')),
    'availability'     => clean(post('availability', '')),
    'message'          => clean(post('message', '')),
    'resume'           => $resumePath,
    'status'           => 'new',
]);

json_success('Thank you for volunteering! Our team will contact you soon.');
