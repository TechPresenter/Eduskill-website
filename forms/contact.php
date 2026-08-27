<?php
/**
 * Contact form handler — inserts into contact_messages, notifies admin.
 */
require_once __DIR__ . '/../includes/bootstrap.php';


if (!is_post())      json_error('Method not allowed.', [], 405);
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
    json_success('Thank you for reaching out! We will get back to you shortly.');
}
if (!pwf_throttle('contact-form', 5, 300)) {
    json_error('Too many submissions — please try again in a few minutes.', [], 429);
}

$errors = validate($_POST, [
    'name'    => 'required|max:128',
    'email'   => 'required|email',
    'message' => 'required|min:5|max:5000',
]);
if ($errors) {
    json_error('Please correct the highlighted fields.', ['errors' => $errors]);
}

/* Country selector: validate the number against the chosen country and keep
   the country name / ISO / dial code with it. Placed after require_csrf()
   and the field validation above, so it is unreachable on a bare GET and
   its error surfaces the same way as every other field error. */
$pwfCountry = country_capture('phone', false);
if (!$pwfCountry['ok']) {
    json_error($pwfCountry['error'], ['errors' => ['phone' => $pwfCountry['error']]]);
}

$id = db_insert('contact_messages', [
    'name'       => clean(post('name')),
    'email'      => clean(post('email')),
    'phone'      => $pwfCountry['phone'],
    'country_name' => $pwfCountry['columns']['country_name'],
    'country_iso'  => $pwfCountry['columns']['country_iso'],
    'country_dial' => $pwfCountry['columns']['country_dial'],
    'subject'    => clean(post('subject', 'General Enquiry')),
    'message'    => clean(post('message')),
    'status'     => 'unread',
    'ip_address' => client_ip(),
]);

// Best-effort admin notification (never blocks the response).
try {
    send_mail(
        get_setting('contact_email', SITE_EMAIL),
        'New contact message: ' . clean(post('subject', 'General Enquiry')),
        '<p>You received a new message from <strong>' . e(clean(post('name'))) . '</strong> (' . e(clean(post('email'))) . ').</p>'
        . '<p>' . nl2br(e(clean(post('message')))) . '</p>',
        ['reply_to' => clean(post('email'))]
    );
} catch (Throwable $e) { /* ignore mail failures on local/dev */ }

json_success('Thank you for reaching out! We will get back to you shortly.', ['id' => $id]);
