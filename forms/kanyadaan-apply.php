<?php
/**
 * =============================================================================
 *  Kanya Daan Project — application handler.
 * -----------------------------------------------------------------------------
 *  Backs kanyadaan-apply.php. Multipart: up to ten checklist documents arrive
 *  with the text fields, so the order of work matters —
 *
 *    1. method + CSRF + honeypot + throttle
 *    2. every text/scalar rule, so a typo never costs an upload
 *    3. the files, tracking what landed on disk
 *    4. the insert; if it throws, the files already written are removed again
 *
 *  THE AGE CHECK IS NOT A VALIDATION NICETY. This project assists marriages, so
 *  a case below the statutory age (bride 18, groom 21 — Prohibition of Child
 *  Marriage Act, 2006) is refused outright rather than stored for someone to
 *  catch during verification. The foundation should never hold an application
 *  it could not lawfully act on.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

$DONE = 'Your application has been received. Our team will verify the information provided and contact you '
      . 'if additional documentation or a field visit is required.';

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
    json_success($DONE);
}
if (!pwf_throttle('kanyadaan-form', 4, 600)) {
    json_error('Too many submissions — please try again in a few minutes.', [], 429);
}

/* -------------------------------------------------------------- 1. scalars */
$errors = validate($_POST, [
    'applicant_name' => 'required|max:128',
    'phone'          => 'required|max:32',
    'bride_name'     => 'required|max:128',
    'email'          => 'max:191',
    'hardship_reason'      => 'max:2000',
    'support_justification'=> 'max:2000',
    'groom_address'        => 'max:500',
]);
if (trim((string) post('email', '')) !== '' && !is_email((string) post('email'))) {
    $errors['email'] = 'Please enter a valid email address.';
}

$relationships = kd_relationships();
$relationship  = (string) post('relationship', '');
if (!isset($relationships[$relationship])) {
    $errors['relationship'] = 'Please tell us your relationship with the bride.';
}

if ((int) post('consent', 0) !== 1) {
    $errors['consent'] = 'Please confirm the declaration to submit the application.';
}
if ((int) post('dowry_declaration', 0) !== 1) {
    $errors['dowry_declaration'] = 'Please confirm that this request is not for any dowry payment.';
}

/** Accept a date only in the form the date input posts it, else null. */
$date = static function (string $key): ?string {
    $v = trim((string) post($key, ''));
    if ($v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
};

/** Whole years between a date of birth and today. */
$ageFrom = static function (?string $dob): ?int {
    return $dob === null ? null : (new DateTime($dob))->diff(new DateTime('today'))->y;
};

$brideDob = $date('bride_dob');
$groomDob = $date('groom_dob');
if (trim((string) post('bride_dob', '')) !== '' && $brideDob === null) {
    $errors['bride_dob'] = 'Please enter a valid date of birth.';
}
if (trim((string) post('groom_dob', '')) !== '' && $groomDob === null) {
    $errors['groom_dob'] = 'Please enter a valid date of birth.';
}

/* Age: prefer the date of birth, fall back to the typed age. Both are stored,
   because the paper form asks for both and a verifier compares them. */
$brideAge = $ageFrom($brideDob) ?? (post('bride_age', '') !== '' ? (int) post('bride_age') : null);
$groomAge = $ageFrom($groomDob) ?? (post('groom_age', '') !== '' ? (int) post('groom_age') : null);

/* ---- The statutory-age gate --------------------------------------------- */
if ($brideAge === null) {
    $errors['bride_dob'] = "The bride's date of birth or age is required.";
} elseif ($brideAge < kd_min_age('bride')) {
    $errors['bride_dob'] = 'The bride must be at least ' . kd_min_age('bride')
        . ' years old. This project cannot assist a marriage below the legal age of marriage.';
} elseif ($brideAge > 75) {
    $errors['bride_dob'] = 'Please check the date of birth you entered.';
}
if ($groomAge !== null && $groomAge < kd_min_age('groom')) {
    $errors['groom_dob'] = 'The groom must be at least ' . kd_min_age('groom')
        . ' years old. This project cannot assist a marriage below the legal age of marriage.';
}
if ((int) post('legally_permissible', 0) !== 1) {
    $errors['legally_permissible'] = 'Assistance can only be considered where the marriage is legally permissible.';
}

$houseType = (string) post('house_type', '');
if (!array_key_exists($houseType, kd_house_types())) {
    $houseType = '';
}

$marriageDate = $date('marriage_date');
if (trim((string) post('marriage_date', '')) !== '' && $marriageDate === null) {
    $errors['marriage_date'] = 'Please enter a valid marriage date.';
}

if ($errors) json_error('Please correct the highlighted fields.', ['errors' => $errors]);

/* Country selector: validate the number against the chosen country and keep the
   country name / ISO / dial code with it. WhatsApp is optional. */
$pwfCountry = country_capture('phone', true);
if (!$pwfCountry['ok']) {
    json_error($pwfCountry['error'], ['errors' => ['phone' => $pwfCountry['error']]]);
}
$waCountry = country_capture('whatsapp', false);
if (!$waCountry['ok']) {
    json_error($waCountry['error'], ['errors' => ['whatsapp' => $waCountry['error']]]);
}

/** Read a checkbox group; only whitelisted values survive, plus one free-text "Other". */
$checkGroup = static function (string $key, array $allowed, string $otherKey = ''): string {
    $raw = $_POST[$key] ?? [];
    if (!is_array($raw)) {
        $raw = [];
    }
    $picked = [];
    foreach ($raw as $v) {
        if (is_string($v) && in_array($v, $allowed, true) && !in_array($v, $picked, true)) {
            $picked[] = $v;
        }
    }
    if ($otherKey !== '') {
        $other = clean(post($otherKey, ''));
        if ($other !== '') {
            $picked[] = mb_substr($other, 0, 96);
        }
    }
    return mb_substr(implode(', ', $picked), 0, 500);
};

$support = $checkGroup('support_items', kd_support_items(), 'support_other');

/**
 * Section 10 — the family table. Rows left entirely blank are dropped so the
 * admin view is not padded with empty lines.
 */
$family = [];
foreach (($_POST['fam_name'] ?? []) as $i => $unused) {
    $row = [];
    foreach (kd_family_fields() as $key => $meta) {
        $row[$key] = mb_substr(clean($_POST['fam_' . $key][$i] ?? ''), 0, (int) $meta['max']);
    }
    if (implode('', $row) !== '') {
        $family[] = $row;
    }
    if (count($family) >= 12) {
        break;
    }
}

/* -------------------------------------------------------------- 2. uploads */
$stored  = [];
$written = [];

$abort = static function (string $msg, array $errs) use (&$written): void {
    foreach ($written as $path) {
        delete_upload($path);
    }
    json_error($msg, ['errors' => $errs]);
};

foreach (kd_documents() as $slot => $doc) {
    $file = $_FILES[$slot] ?? null;
    $sent = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && (string) ($file['name'] ?? '') !== '';

    if (!$sent) {
        if (!empty($doc['required'])) {
            $abort('Please attach the required documents.', [$slot => $doc['label'] . ' is required.']);
        }
        continue;
    }
    $up = upload_file($file, KD_DOC_DIR, [
        'allowed'    => kd_doc_allowed((bool) $doc['image']),
        'image_only' => (bool) $doc['image'],
    ]);
    if (!$up['success']) {
        $abort($doc['label'] . ': ' . $up['error'], [$slot => $up['error']]);
    }
    $stored[$slot] = $up['path'];
    $written[]     = $up['path'];
}

/* -------------------------------------------------------------- 3. persist */

/** Encrypt an identifier at rest, keeping only the last four digits readable. */
$secret = static function (string $key): array {
    $raw = preg_replace('/[^A-Za-z0-9]/', '', (string) post($key, ''));
    if ($raw === '') {
        return [null, null];
    }
    $raw   = mb_substr($raw, 0, 32);
    $last4 = mb_substr($raw, -4);
    return [sec_has_encryption() ? (sec_encrypt($raw) ?? $raw) : $raw, $last4];
};

[$brideId, $brideIdLast4] = $secret('bride_id_no');
[$bankAcc,  $bankLast4]   = $secret('bank_account');

/** A rupee amount, clamped so a negative can never be stored. */
$money = static function (string $key): ?float {
    $v = trim((string) post($key, ''));
    return is_numeric($v) ? round(max(0.0, (float) $v), 2) : null;
};

/** A small non-negative count. */
$count = static function (string $key, int $max): ?int {
    $v = trim((string) post($key, ''));
    return $v === '' ? null : min($max, max(0, (int) $v));
};

$data = [
    'applicant_name'     => clean(post('applicant_name')),
    'relationship'       => $relationship,
    'relationship_other' => $relationship === 'other' ? (mb_substr(clean(post('relationship_other', '')), 0, 96) ?: null) : null,
    'phone'              => $pwfCountry['phone'],
    'country_name'       => $pwfCountry['columns']['country_name'],
    'country_iso'        => $pwfCountry['columns']['country_iso'],
    'country_dial'       => $pwfCountry['columns']['country_dial'],
    'whatsapp'           => $waCountry['phone'] ?: null,
    'email'              => clean(post('email', '')) ?: null,

    'state'     => mb_substr(clean(post('state', '')), 0, 96) ?: null,
    'district'  => mb_substr(clean(post('district', '')), 0, 96) ?: null,
    'block'     => mb_substr(clean(post('block', '')), 0, 96) ?: null,
    'panchayat' => mb_substr(clean(post('panchayat', '')), 0, 96) ?: null,
    'village'   => mb_substr(clean(post('village', '')), 0, 128) ?: null,

    'bride_name'       => clean(post('bride_name')),
    'bride_dob'        => $brideDob,
    'bride_age'        => $brideAge,
    'bride_education'  => mb_substr(clean(post('bride_education', '')), 0, 128) ?: null,
    'bride_occupation' => mb_substr(clean(post('bride_occupation', '')), 0, 128) ?: null,
    'bride_id_no'      => $brideId,
    'bride_id_last4'   => $brideIdLast4,
    'bank_account'     => $bankAcc,
    'bank_last4'       => $bankLast4,
    'bank_name'        => mb_substr(clean(post('bank_name', '')), 0, 128) ?: null,
    'bank_ifsc'        => mb_substr(strtoupper(clean(post('bank_ifsc', ''))), 0, 16) ?: null,
    'marital_status'   => mb_substr(clean(post('marital_status', '')), 0, 48) ?: null,

    'groom_name'       => mb_substr(clean(post('groom_name', '')), 0, 128) ?: null,
    'groom_dob'        => $groomDob,
    'groom_age'        => $groomAge,
    'groom_occupation' => mb_substr(clean(post('groom_occupation', '')), 0, 128) ?: null,
    'groom_address'    => clean(post('groom_address', '')) ?: null,

    'marriage_date'       => $marriageDate,
    'marriage_location'   => mb_substr(clean(post('marriage_location', '')), 0, 191) ?: null,
    'marriage_type'       => mb_substr(clean(post('marriage_type', '')), 0, 128) ?: null,
    'legally_permissible' => 1,

    'family_members'  => $family ? json_encode($family, JSON_UNESCAPED_UNICODE) : null,
    'monthly_income'  => $money('monthly_income'),
    'annual_income'   => $money('annual_income'),
    'house_type'      => $houseType,
    'family_size'     => $count('family_size', 60),
    'earning_members' => $count('earning_members', 60),

    'financial_hardship'      => (int) post('financial_hardship', 0) === 1 ? 1 : 0,
    'hardship_reason'         => clean(post('hardship_reason', '')) ?: null,
    'govt_assistance'         => (int) post('govt_assistance', 0) === 1 ? 1 : 0,
    'govt_assistance_details' => mb_substr(clean(post('govt_assistance_details', '')), 0, 500) ?: null,

    'support_items'         => $support ?: null,
    'support_justification' => clean(post('support_justification', '')) ?: null,

    'documents' => $stored ? json_encode($stored, JSON_UNESCAPED_UNICODE) : null,

    'declared_place'    => mb_substr(clean(post('declared_place', '')), 0, 128) ?: null,
    'declared_on'       => $date('declared_on') ?? date('Y-m-d'),
    'consent'           => 1,
    'dowry_declaration' => 1,

    'status'     => 'new',
    'ip_address' => client_ip(),
];

try {
    $id = db_insert('kanyadaan_applications', $data);
} catch (Throwable $e) {
    error_log('[kanyadaan-apply] ' . $e->getMessage());
    foreach ($written as $path) {
        delete_upload($path);
    }
    json_error('We could not save your application. Please try again in a moment.', [], 500);
}

/* KD-2026-00001 — derived from the row id, so it is unique without a second
   round trip to check for collisions. */
$applicationNo = 'KD-' . date('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
db_update('kanyadaan_applications', ['application_no' => $applicationNo], 'id = :id', [':id' => $id]);

/* -------------------------------------------------------------- 4. notify */
$who   = clean(post('applicant_name'));
$bride = clean(post('bride_name'));
$place = implode(', ', array_filter([
    clean(post('village', '')), clean(post('panchayat', '')),
    clean(post('block', '')), clean(post('district', '')), clean(post('state', '')),
]));

notify('New Kanya Daan application', [
    'body' => $bride . ' · applied by ' . $who . ' · ' . ($place ?: 'location not given') . ' · ' . $applicationNo,
    'url'  => admin_url('kanyadaan-applications?action=view&id=' . $id),
    'icon' => 'heart-handshake',
    'type' => 'info',
]);

try {
    send_mail(
        get_setting('contact_email', SITE_EMAIL),
        'New Kanya Daan application — ' . $applicationNo,
        '<p>A new Kanya Daan application has been received.</p>'
        . '<p>Reference: <strong>' . e($applicationNo) . '</strong><br>'
        . 'Bride: ' . e($bride) . ($brideAge !== null ? ' (' . (int) $brideAge . ')' : '') . '<br>'
        . 'Applicant: ' . e($who) . ' — ' . e($relationships[$relationship]) . '<br>'
        . 'Phone: ' . e($pwfCountry['phone']) . '<br>'
        . 'Area: ' . e($place ?: '—') . '<br>'
        . 'Marriage date: ' . e($marriageDate ?: 'not given') . '</p>'
        . '<p><a href="' . e(admin_url('kanyadaan-applications?action=view&id=' . $id)) . '">Open the application in the admin panel</a></p>',
        array_filter(['reply_to' => clean(post('email', ''))])
    );
} catch (Throwable $e) { /* ignore mail failures on local/dev */ }

// Applicant acknowledgement — only when an email address was given.
$applicantEmail = clean(post('email', ''));
if ($applicantEmail !== '' && is_email($applicantEmail)) {
    try {
        send_mail(
            $applicantEmail,
            'We received your Kanya Daan application — ' . $applicationNo,
            '<p>Dear ' . e($who) . ',</p>'
            . '<p>Thank you for applying to the <strong>Kanya Daan Project</strong>.</p>'
            . '<p>Your application reference is <strong>' . e($applicationNo) . '</strong>. Please quote it whenever '
            . 'you contact us about this application.</p>'
            . '<p>Our team will verify the information provided and contact you if additional documentation or a '
            . 'field visit is required. Submission of an application does not guarantee assistance.</p>'
            . '<p style="font-size:13px;color:#666;">' . e(kd_policy_statement()) . '</p>'
            . '<p>Warm regards,<br>' . e(SITE_NAME) . '</p>'
        );
    } catch (Throwable $e) { /* ignore mail failures on local/dev */ }
}

json_success($DONE, ['application_no' => $applicationNo]);
