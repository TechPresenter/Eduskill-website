<?php
/**
 * POST /api/newsletter.php — newsletter subscription intake.
 * DB-first (single opt-in in Phase 1; double opt-in arrives with real email in Phase 2).
 */
require __DIR__ . '/../includes/config.php';

if (request_method() !== 'POST') {
    json_error('Method not allowed.', 405);
}

$in = api_body();

// Honeypot — bots fill the hidden 'website' field.
if (!empty($in['website'])) {
    json_ok([], 'Thank you for subscribing!');
}

if (!verify_csrf($in['_csrf'] ?? null)) {
    json_error('Your session expired. Please refresh the page and try again.', 400);
}

$email = trim((string) ($in['email'] ?? ''));
$name = trim((string) ($in['name'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Please enter a valid email address.', 422);
}

$existing = db_one('SELECT id, status FROM newsletter_subscribers WHERE email = ? LIMIT 1', [$email]);
if ($existing !== null) {
    if ($existing['status'] === 'unsubscribed') {
        db_exec(
            'UPDATE newsletter_subscribers SET status = ?, subscribed_at = NOW() WHERE id = ?',
            ['subscribed', (int) $existing['id']]
        );
        json_ok([], 'Welcome back! Your subscription has been reactivated.');
    }
    json_ok([], 'You are already on our list — thank you!');
}

db_insert(
    'INSERT INTO newsletter_subscribers (email, name, status, token, subscribed_at) VALUES (?, ?, ?, ?, NOW())',
    [$email, $name, 'subscribed', bin2hex(random_bytes(16))]
);

json_ok([], 'Thank you for subscribing to our newsletter!');
