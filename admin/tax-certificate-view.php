<?php
/**
 * =============================================================================
 *  Admin — Printable annual 80G tax exemption certificate (standalone)
 * =============================================================================
 *  ?email=<donor email>&year=<YYYY>. Aggregates a donor's completed donations
 *  for the year into a single print-ready 80G certificate. Browser print-to-PDF
 *  — no PDF library required. No admin chrome.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/donation_documents.php';

$email = trim((string) get('email', ''));
$year  = (int) get('year', (int) date('Y'));

$donations = db_all(
    "SELECT *
       FROM donations
      WHERE status = 'completed'
        AND LOWER(email) = LOWER(:e)
        AND YEAR(COALESCE(donated_at, created_at)) = :y
      ORDER BY donated_at",
    [':e' => $email, ':y' => $year]
);

if (!$donations) {
    set_flash('error', 'No completed donations found for that donor and year.');
    redirect(admin_url('tax-certificates'));
}

$pan = '';
foreach ($donations as $d) {
    if (!empty($d['pan'])) { $pan = (string) $d['pan']; break; }
}
$address = '';
foreach ($donations as $d) {
    if (!empty($d['address'])) { $address = (string) $d['address']; break; }
}

$donor = [
    'name'    => (string) $donations[0]['donor_name'],
    'pan'     => $pan,
    'address' => $address,
    'email'   => $email,
];

echo donation_80g_certificate_html($donations, $donor, $year);
