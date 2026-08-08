<?php
/**
 * Event registration handler — validates event, capacity, then registers.
 */
require_once __DIR__ . '/../includes/bootstrap.php';


if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

$errors = validate($_POST, [
    'name'  => 'required|max:128',
    'email' => 'required|email',
]);
$eventId = (int) post('event_id', 0);
$event   = $eventId ? find('events', $eventId) : null;
if (!$event || $event['status'] !== 'published') {
    json_error('Sorry, this event is not open for registration.', [], 404);
}
if ($errors) {
    json_error('Please correct the highlighted fields.', ['errors' => $errors]);
}

// Capacity check (if a capacity is set).
if (!empty($event['capacity'])) {
    $taken = db_count('event_registrations', 'event_id = :e AND status <> :c',
        [':e' => $eventId, ':c' => 'cancelled']);
    if ($taken >= (int) $event['capacity']) {
        json_error('Registrations for this event are now full.');
    }
}

$guests = max(1, min(10, (int) post('guests', 1)));

/* Country selector: validate the number against the chosen country and keep
   the country name / ISO / dial code with it. Placed after require_csrf()
   and the field validation above, so it is unreachable on a bare GET and
   its error surfaces the same way as every other field error. */
$pwfCountry = country_capture('phone', false);
if (!$pwfCountry['ok']) {
    json_error($pwfCountry['error'], ['errors' => ['phone' => $pwfCountry['error']]]);
}

db_insert('event_registrations', [
    'event_id' => $eventId,
    'name'     => clean(post('name')),
    'email'    => clean(post('email')),
    'phone'    => $pwfCountry['phone'],
    'country_name' => $pwfCountry['columns']['country_name'],
    'country_iso'  => $pwfCountry['columns']['country_iso'],
    'country_dial' => $pwfCountry['columns']['country_dial'],
    'guests'   => $guests,
    'message'  => clean(post('message', '')),
    'status'   => 'pending',
]);

// Confirmation email (best effort).
try {
    send_mail(
        clean(post('email')),
        'Registration confirmed: ' . $event['title'],
        '<p>Dear ' . e(clean(post('name'))) . ',</p>'
        . '<p>You are registered for <strong>' . e($event['title']) . '</strong> on '
        . e(format_datetime($event['start_datetime'])) . '.</p>'
    );
} catch (Throwable $e) { /* ignore */ }

json_success('You are registered for "' . $event['title'] . '"! A confirmation email is on its way.');
