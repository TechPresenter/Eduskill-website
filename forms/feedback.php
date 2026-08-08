<?php
/**
 * Feedback form handler.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

// Honeypot — hidden field humans never fill; pretend success, store nothing.
if (trim((string) post('website_hp')) !== '') {
    json_success('Thank you for your valuable feedback!');
}
if (!pwf_throttle('feedback-form', 5, 300)) {
    json_error('Too many submissions — please try again in a few minutes.', [], 429);
}

$errors = validate($_POST, [
    'name'    => 'required|max:128',
    'message' => 'required|min:5|max:5000',
]);
if (post('email') !== '' && !is_email(post('email'))) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($errors) {
    json_error('Please correct the highlighted fields.', ['errors' => $errors]);
}

$rating = (int) post('rating', 0);
$rating = ($rating >= 1 && $rating <= 5) ? $rating : null;

db_insert('feedback', [
    'name'       => clean(post('name')),
    'email'      => clean(post('email', '')),
    'subject'    => clean(post('subject', '')),
    'message'    => clean(post('message')),
    'rating'     => $rating,
    'status'     => 'new',
    'ip_address' => client_ip(),
]);

json_success('Thank you for your valuable feedback!');
