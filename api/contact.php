<?php
/**
 * POST /api/contact.php — public contact form intake. Stores DB-first (a lead is never lost to a
 * mail failure); email notification is best-effort on top (added with the mailer).
 */
require __DIR__ . '/../includes/config.php';

if (request_method() !== 'POST') {
    json_error('Method not allowed.', 405);
}

$in = api_body();

// Honeypot: bots fill the hidden 'website' field. Pretend success so they don't retry.
if (!empty($in['website'])) {
    json_ok([], 'Thank you! Your message has been received.');
}

if (!verify_csrf($in['_csrf'] ?? null)) {
    json_error('Your session expired. Please refresh the page and try again.', 400);
}

$name = trim((string) ($in['name'] ?? ''));
$email = trim((string) ($in['email'] ?? ''));
$message = trim((string) ($in['message'] ?? ''));

$errors = [];
if ($name === '') {
    $errors[] = 'Name is required.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email is required.';
}
if ($message === '') {
    $errors[] = 'Message is required.';
}
if ($errors !== []) {
    json_error(implode(' ', $errors), 422);
}

$phone = trim((string) ($in['phone'] ?? ''));
$subject = trim((string) ($in['subject'] ?? ''));
db_insert(
    'INSERT INTO contacts (name, email, phone, subject, message, ip) VALUES (?, ?, ?, ?, ?, ?)',
    [$name, $email, $phone, $subject, $message, client_ip()]
);

// Notify the NGO (best-effort — the message is already saved, so a mail failure never loses a lead).
$notify = (string) setting('mail_notify_email', (string) setting('contact_email', ''));
if ($notify !== '') {
    $body = '<h3>New contact message</h3>'
        . '<p><strong>Name:</strong> ' . e($name) . '<br>'
        . '<strong>Email:</strong> ' . e($email) . '<br>'
        . ($phone !== '' ? '<strong>Phone:</strong> ' . e($phone) . '<br>' : '')
        . ($subject !== '' ? '<strong>Subject:</strong> ' . e($subject) . '<br>' : '')
        . '</p><p>' . nl2br(e($message)) . '</p>';
    @send_mail($notify, 'New contact message from ' . $name, $body);
}

json_ok([], 'Thank you! Your message has been received. We will get back to you soon.');
