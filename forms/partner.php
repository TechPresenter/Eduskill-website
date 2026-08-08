<?php
/** Become-a-partner application handler. */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

// Honeypot — hidden field humans never fill; pretend success, store nothing.
if (trim((string) post('website_hp')) !== '') {
    json_success('Thank you for your interest in partnering with us! Our team will reach out soon.');
}
if (!pwf_throttle('partner-form', 5, 300)) {
    json_error('Too many submissions — please try again in a few minutes.', [], 429);
}

$errors = validate($_POST, [
    'organization' => 'required|max:191',
    'contact_name' => 'required|max:128',
    'email'        => 'required|email',
    'message'      => 'required|max:2000',
]);
if ($errors) json_error('Please correct the highlighted fields.', ['errors' => $errors]);

/* Country selector: validate the number against the chosen country and keep
   the country name / ISO / dial code with it. Placed after require_csrf()
   and the field validation above, so it is unreachable on a bare GET and
   its error surfaces the same way as every other field error. */
$pwfCountry = country_capture('phone', false);
if (!$pwfCountry['ok']) {
    json_error($pwfCountry['error'], ['errors' => ['phone' => $pwfCountry['error']]]);
}

db_insert('partner_applications', [
    'organization' => clean(post('organization')),
    'contact_name' => clean(post('contact_name')),
    'email'        => clean(post('email')),
    'phone'        => $pwfCountry['phone'],
    'country_name' => $pwfCountry['columns']['country_name'],
    'country_iso'  => $pwfCountry['columns']['country_iso'],
    'country_dial' => $pwfCountry['columns']['country_dial'],
    'website'      => clean(post('website', '')),
    'tier'         => clean(post('tier', '')),
    'message'      => clean(post('message', '')),
    'status'       => 'new',
]);
json_success('Thank you for your interest in partnering with us! Our team will reach out soon.');
