<?php
/**
 * POST /api/v1/volunteer — submit a volunteer application (JSON or form body).
 */
require_once __DIR__ . '/bootstrap.php';
api_require_method('POST');
api_require_auth();
api_throttle('volunteer-form');

$in = api_input();
$errors = validate($in, [
    'name'  => 'required|max:128',
    'email' => 'required|email',
    'phone' => 'required|max:32',
]);
if ($errors) {
    api_fail('Validation failed: ' . implode(' ', $errors), 422);
}

$id = db_insert('volunteers', [
    'name'             => clean($in['name'] ?? ''),
    'email'            => clean($in['email'] ?? ''),
    'phone'            => clean($in['phone'] ?? ''),
    'city'             => clean($in['city'] ?? ''),
    'address'          => clean($in['address'] ?? ''),
    'area_of_interest' => clean($in['area_of_interest'] ?? ''),
    'availability'     => clean($in['availability'] ?? ''),
    'message'          => clean($in['message'] ?? ''),
    'status'           => 'new',
]);

api_ok(['id' => $id, 'message' => 'Application received.'], [], 201);
