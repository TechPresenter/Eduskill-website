<?php
/** Scholarship application handler (optional document upload). */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

$errors = validate($_POST, [
    'name'  => 'required|max:128',
    'email' => 'required|email',
    'phone' => 'required|max:32',
]);
if ($errors) json_error('Please correct the highlighted fields.', ['errors' => $errors]);

$scholarshipId = (int) post('scholarship_id', 0) ?: null;
if ($scholarshipId && !find('scholarships', $scholarshipId)) {
    $scholarshipId = null;
}

$docPath = null;
if (!empty($_FILES['document']['name'])) {
    $up = upload_document($_FILES['document'], 'documents');
    if (!$up['success']) json_error($up['error'], ['errors' => ['document' => $up['error']]]);
    $docPath = $up['path'];
}

/* Country selector: validate the number against the chosen country and keep
   the country name / ISO / dial code with it. Placed after require_csrf()
   and the field validation above, so it is unreachable on a bare GET and
   its error surfaces the same way as every other field error. */
$pwfCountry = country_capture('phone', true);
if (!$pwfCountry['ok']) {
    json_error($pwfCountry['error'], ['errors' => ['phone' => $pwfCountry['error']]]);
}

db_insert('scholarship_applications', [
    'scholarship_id' => $scholarshipId,
    'name'           => clean(post('name')),
    'email'          => clean(post('email')),
    'phone'          => $pwfCountry['phone'],
    'country_name' => $pwfCountry['columns']['country_name'],
    'country_iso'  => $pwfCountry['columns']['country_iso'],
    'country_dial' => $pwfCountry['columns']['country_dial'],
    'institution'    => clean(post('institution', '')),
    'course'         => clean(post('course', '')),
    'guardian_name'  => clean(post('guardian_name', '')),
    'annual_income'  => clean(post('annual_income', '')),
    'document'       => $docPath,
    'message'        => clean(post('message', '')),
    'status'         => 'new',
]);
json_success('Your scholarship application has been submitted successfully!');
