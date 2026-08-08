<?php
/** Job / career application handler (résumé upload). */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

// Honeypot — hidden field humans never fill; pretend success, store nothing.
if (trim((string) post('website_hp')) !== '') {
    json_success('Your application has been received. Thank you for your interest in joining us!');
}
if (!pwf_throttle('career-form', 5, 300)) {
    json_error('Too many submissions — please try again in a few minutes.', [], 429);
}

$errors = validate($_POST, [
    'name'  => 'required|max:128',
    'email' => 'required|email',
    'phone' => 'required|max:32',
]);
if ($errors) json_error('Please correct the highlighted fields.', ['errors' => $errors]);

$careerId = (int) post('career_id', 0) ?: null;
if ($careerId && !find('careers', $careerId)) {
    $careerId = null;
}

// The apply forms mark the résumé as required — enforce it server-side too.
if (empty($_FILES['resume']['name'])) {
    json_error('Please attach your résumé (PDF, DOC or DOCX).', ['errors' => ['resume' => 'A résumé is required.']]);
}
$up = upload_document($_FILES['resume'], 'resumes');
if (!$up['success']) json_error($up['error'], ['errors' => ['resume' => $up['error']]]);
$resumePath = $up['path'];

/* Country selector: validate the number against the chosen country and keep
   the country name / ISO / dial code with it. Placed after require_csrf()
   and the field validation above, so it is unreachable on a bare GET and
   its error surfaces the same way as every other field error. */
$pwfCountry = country_capture('phone', true);
if (!$pwfCountry['ok']) {
    json_error($pwfCountry['error'], ['errors' => ['phone' => $pwfCountry['error']]]);
}

/** Optional URL field — store only when it is actually a URL. */
$url = static function (string $key): ?string {
    $v = trim((string) post($key, ''));
    if ($v === '') {
        return null;
    }
    // Be forgiving about a missing scheme rather than discarding the value.
    if (!preg_match('~^https?://~i', $v)) {
        $v = 'https://' . $v;
    }
    return filter_var($v, FILTER_VALIDATE_URL) ? mb_substr($v, 0, 255) : null;
};

// Every field now has a real column (schema_v27.sql). They used to be appended
// as text into cover_letter, which made them unsearchable and unreportable.
db_insert('job_applications', [
    'career_id'        => $careerId,
    'name'             => clean(post('name')),
    'email'            => clean(post('email')),
    'phone'            => $pwfCountry['phone'],
    'country_name'     => $pwfCountry['columns']['country_name'],
    'country_iso'      => $pwfCountry['columns']['country_iso'],
    'country_dial'     => $pwfCountry['columns']['country_dial'],
    'address'          => clean(post('address', '')) ?: null,
    'city'             => clean(post('city', '')) ?: null,
    'state'            => clean(post('state', '')) ?: null,
    'qualification'    => clean(post('qualification', '')) ?: null,
    'experience'       => clean(post('experience', '')) ?: null,
    'current_company'  => clean(post('current_company', '')) ?: null,
    'current_salary'   => clean(post('current_salary', '')) ?: null,
    'expected_salary'  => clean(post('expected_salary', '')) ?: null,
    'notice_period'    => clean(post('notice_period', '')) ?: null,
    'position_applied' => clean(post('position_applied', '')) ?: null,
    'linkedin'         => $url('linkedin'),
    'portfolio'        => $url('portfolio'),
    'resume'           => $resumePath,
    'cover_letter'     => clean(post('cover_letter', '')) ?: null,
    'message'          => clean(post('message', '')) ?: null,
    'status'           => 'new',
]);
json_success('Your application has been received. Thank you for your interest in joining us!');
