<?php
/**
 * Newsletter subscription handler — idempotent on email.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

// Honeypot — hidden field humans never fill; pretend success, store nothing.
if (trim((string) post('website_hp')) !== '') {
    json_success('Thank you for subscribing to our newsletter!');
}
if (!pwf_throttle('newsletter-form', 5, 300)) {
    json_error('Too many submissions — please try again in a few minutes.', [], 429);
}

$errors = validate($_POST, ['email' => 'required|email']);
if ($errors) {
    json_error('Please enter a valid email address.', ['errors' => $errors]);
}

$email = strtolower(clean(post('email')));
$existing = find_by('newsletter_subscribers', 'email', $email);

if ($existing) {
    if ($existing['status'] === 'subscribed') {
        json_success('You are already subscribed. Thank you!');
    }
    db_update('newsletter_subscribers', ['status' => 'subscribed'], 'id = :id', [':id' => $existing['id']]);
    json_success('Welcome back! Your subscription has been reactivated.');
}

$subName = clean(post('name', ''));
db_insert('newsletter_subscribers', [
    'name'       => $subName,
    'email'      => $email,
    'token'      => str_random(40),
    'status'     => 'subscribed',
    'ip_address' => client_ip(),
]);

// Fire the Welcome automation (no-op unless enabled in admin/email-automations).
em_trigger('welcome', $email, ['name' => $subName ?: 'Friend']);

json_success('Thank you for subscribing to our newsletter!');
