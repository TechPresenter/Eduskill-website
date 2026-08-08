<?php
/**
 * POST /api/v1/contact — submit a contact message (JSON or form body).
 */
require_once __DIR__ . '/bootstrap.php';
api_require_method('POST');
api_require_auth(); // no-op unless JWT auth is enabled
api_throttle('contact-form');

$in = api_input();
$errors = validate($in, [
    'name'    => 'required|max:128',
    'email'   => 'required|email',
    'message' => 'required|min:5|max:5000',
]);
if ($errors) {
    api_fail('Validation failed: ' . implode(' ', $errors), 422);
}

$id = db_insert('contact_messages', [
    'name'       => clean($in['name'] ?? ''),
    'email'      => clean($in['email'] ?? ''),
    'phone'      => clean($in['phone'] ?? ''),
    'subject'    => clean($in['subject'] ?? 'API Enquiry'),
    'message'    => clean($in['message'] ?? ''),
    'status'     => 'unread',
    'ip_address' => client_ip(),
]);

api_ok(['id' => $id, 'message' => 'Message received.'], [], 201);
