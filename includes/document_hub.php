<?php
/**
 * =============================================================================
 *  Document Hub — dynamic, template-driven documents & certificates.
 * =============================================================================
 *  Templates are HTML with {{placeholders}} (authored in CKEditor). Issuing a
 *  document resolves the placeholders with real data, generates a unique number
 *  + QR-verification token, and stores a record for re-download + verification.
 *  Rendering is print-to-PDF (browser print) — no external PDF binary needed.
 * =============================================================================
 */

declare(strict_types=1);
require_once __DIR__ . '/lib/qrcode.php';

/* -------------------------------------------------------------- CATALOGUES */

function dh_categories(): array
{
    return ['certificate' => 'Certificate', 'id_card' => 'ID Card', 'receipt' => 'Receipt', 'letter' => 'Letter', 'report' => 'Report', 'pass' => 'Pass'];
}
function dh_layouts(): array
{
    return ['landscape' => 'Landscape', 'portrait' => 'Portrait', 'id_horizontal' => 'ID — Horizontal', 'id_vertical' => 'ID — Vertical'];
}
function dh_themes(): array
{
    return [
        'classic'     => 'Classic',
        'premium'     => 'Premium',
        'luxury'      => 'Luxury',
        'gold'        => 'Gold',
        'corporate'   => 'Corporate',
        'minimal'     => 'Minimal',
        'modern'      => 'Modern',
        'blue'        => 'Blue',
        'green'       => 'Green',
        'purple'      => 'Purple',
        'ngo'         => 'NGO',
        'educational' => 'Educational',
        'government'  => 'Government',
    ];
}

/** Placeholder catalogue for the builder palette (group => [key => label]). */
function dh_placeholders(): array
{
    return [
        'Organisation' => ['organization_name' => 'Org name', 'logo' => 'Logo', 'seal' => 'Seal', 'signature' => 'Signature', 'signatory_name' => 'Signatory name', 'signatory_designation' => 'Signatory designation'],
        'Recipient'    => ['member_name' => 'Member name', 'student_name' => 'Student name', 'photo' => 'Photo', 'designation' => 'Designation', 'blood_group' => 'Blood group', 'emergency_contact' => 'Emergency contact'],
        'Numbers'      => ['certificate_no' => 'Certificate no', 'membership_no' => 'Membership no', 'id_no' => 'ID no', 'roll_no' => 'Roll no', 'registration_no' => 'Registration no'],
        'Programme'    => ['course_name' => 'Course', 'program_name' => 'Programme', 'event_name' => 'Event', 'volunteer_hours' => 'Volunteer hours'],
        'Academic'     => ['marks' => 'Marks', 'grade' => 'Grade', 'percentage' => 'Percentage'],
        'Dates'        => ['date' => 'Date', 'issue_date' => 'Issue date', 'expiry_date' => 'Expiry date'],
        'Finance'      => ['amount' => 'Amount', 'amount_words' => 'Amount in words', 'purpose' => 'Purpose', 'pan' => 'PAN', 'reference_no' => 'Reference no'],
        'Extra'        => ['father_name' => "Father's name", 'address' => 'Address', 'valid_from' => 'Valid from', 'rank' => 'Rank', 'remarks' => 'Remarks'],
        'Codes'        => ['qr_code' => 'QR code', 'barcode' => 'Barcode'],
    ];
}

/** Sample values for previewing a template before real data exists. */
function dh_sample_data(): array
{
    return [
        'member_name' => 'Priya Sharma', 'student_name' => 'Priya Sharma', 'designation' => 'Volunteer',
        'membership_no' => 'MEM-2026-0042', 'id_no' => 'ID-2026-0042', 'roll_no' => 'R-104', 'registration_no' => 'REG-77',
        'course_name' => 'Community Health Worker Training', 'program_name' => 'Skill Development', 'event_name' => 'Annual Charity Run',
        'marks' => '92', 'grade' => 'A+', 'percentage' => '92%', 'volunteer_hours' => '120',
        'expiry_date' => date('d M Y', strtotime('+1 year')), 'blood_group' => 'O+', 'emergency_contact' => '+91 99554 46477',
        'photo' => '',
    ];
}

/* -------------------------------------------------------------- ASSETS / QR */

/** Org logo/seal/signature image URLs from settings (may be empty). */
function dh_org_assets(): array
{
    $u = static fn ($k) => get_setting($k) ? upload_url(get_setting($k)) : '';
    return ['logo' => $u('site_logo'), 'seal' => $u('org_seal'), 'signature' => $u('org_signature')];
}

/** Inline SVG QR for a verification URL/text. */
function dh_qr_svg(string $text): string
{
    try { return PWF_QR::encode($text, 'M')->svg(2, 4); } catch (Throwable $e) { return ''; }
}

/** A lightweight visual barcode (not machine-scannable — decorative). */
function dh_barcode_svg(string $text): string
{
    $bars = '';
    $x = 0;
    foreach (str_split($text) as $ch) {
        $w = 1 + (ord($ch) % 4);
        $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="40" fill="#111"/>';
        $x += $w + 2;
    }
    return '<svg class="doc-barcode" viewBox="0 0 ' . max(1, $x) . ' 40" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">' . $bars . '</svg>';
}

function dh_fallback_seal(): string
{
    $org = e(get_setting('site_name', SITE_NAME));
    return '<span class="doc-seal-badge"><span class="dsb-ring">OFFICIAL &bull; SEAL &bull;</span><span class="dsb-core">' . mb_substr($org, 0, 2) . '</span></span>';
}
function dh_fallback_signature(): string
{
    return '<span class="doc-sign-cursive">' . e(get_setting('site_name', SITE_NAME)) . '</span>';
}

/* -------------------------------------------------------------- RENDER */

/** Resolve special (HTML) placeholders for a template/issued doc. */
function dh_special_html(array $tpl, ?array $issued): array
{
    $a = dh_org_assets();
    $token = $issued['qr_token'] ?? 'PREVIEW';
    $no    = $issued['doc_no'] ?? 'PREVIEW-0000';
    return [
        'logo'      => !empty($tpl['show_logo']) && $a['logo'] ? '<img class="doc-logo-img" src="' . e($a['logo']) . '" alt="">' : '',
        'qr_code'   => !empty($tpl['show_qr']) ? '<span class="doc-qr">' . dh_qr_svg(rtrim(APP_URL, '/') . '/verify-document?t=' . $token) . '</span>' : '',
        'seal'      => !empty($tpl['show_seal']) ? ($a['seal'] ? '<img class="doc-seal-img" src="' . e($a['seal']) . '" alt="">' : dh_fallback_seal()) : '',
        'signature' => !empty($tpl['show_signature']) ? ($a['signature'] ? '<img class="doc-sign-img" src="' . e($a['signature']) . '" alt="">' : dh_fallback_signature()) : '',
        'barcode'   => dh_barcode_svg($no),
    ];
}

/** Render a template's HTML with data. $issued = issued row (for real no/token). */
function dh_render(array $tpl, array $data = [], ?array $issued = null): string
{
    $data = array_merge([
        'organization_name'     => get_setting('site_name', SITE_NAME),
        'date'                  => date('d M Y'),
        'issue_date'            => date('d M Y'),
        'certificate_no'        => $issued['doc_no'] ?? 'PREVIEW-0000',
        'doc_no'                => $issued['doc_no'] ?? 'PREVIEW-0000',
        'signatory_name'        => get_setting('doc_signatory_name', 'Authorised Signatory'),
        'signatory_designation' => get_setting('doc_signatory_designation', get_setting('site_name', SITE_NAME)),
    ], $data);

    $body = (string) ($tpl['body'] ?? '');

    // Special HTML placeholders.
    foreach (dh_special_html($tpl, $issued) as $k => $html) {
        $body = str_replace('{{' . $k . '}}', $html, $body);
    }
    // Photo (data-driven).
    $photo = trim((string) ($data['photo'] ?? ''));
    $body = str_replace('{{photo}}', $photo !== '' ? '<img class="doc-photo-img" src="' . e($photo) . '" alt="">' : '<span class="doc-photo-ph">PHOTO</span>', $body);
    // Text placeholders (escaped).
    $body = preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', static function ($m) use ($data) {
        $k = strtolower($m[1]);
        return array_key_exists($k, $data) ? e((string) $data[$k]) : '';
    }, $body) ?? $body;

    $wm = !empty($tpl['show_watermark'])
        ? '<div class="doc-watermark">' . e($tpl['watermark_text'] ?: get_setting('site_name', SITE_NAME)) . '</div>' : '';
    /* Terms go through the same placeholder pass as the body. They previously
       did not, so a template whose terms mentioned {{organization_name}}
       printed the raw braces on the issued document. */
    $terms = '';
    if (!empty($tpl['terms_enabled']) && trim((string) ($tpl['terms'] ?? '')) !== '') {
        $termsHtml = preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', static function ($m) use ($data) {
            $k = strtolower($m[1]);
            return array_key_exists($k, $data) ? e((string) $data[$k]) : '';
        }, (string) $tpl['terms']) ?? (string) $tpl['terms'];
        $terms = '<div class="doc-terms">' . blog_sanitize_html($termsHtml) . '</div>';
    }

    $layout = (string) ($tpl['layout'] ?? 'landscape');
    $isCard = $layout === 'id_horizontal' || $layout === 'id_vertical';

    /* Decorative chrome. Added here rather than in each stored body so all
       templates — and every future one — inherit the same document furniture,
       and a theme change restyles the lot. Purely presentational and
       aria-hidden, so it never reaches assistive tech or the text layer of a
       generated PDF. */
    $chrome = '<div class="doc-guilloche" aria-hidden="true"></div>'
            . '<div class="doc-frame" aria-hidden="true"></div>'
            . '<div class="doc-band" aria-hidden="true"></div>';
    if (!$isCard) {
        foreach (['tl', 'tr', 'bl', 'br'] as $c) {
            $chrome .= '<span class="doc-corner ' . $c . '" aria-hidden="true"></span>';
        }
    }

    $head = $foot = '';
    if (!$isCard) {
        $head = dh_letterhead($tpl, $data);
        $foot = dh_verify_footer($data, $issued);
    }

    $cls = 'doc doc-' . e((string) $tpl['theme']) . ' doc-' . e($layout);
    return '<div class="' . $cls . '">'
        . $chrome . $wm
        . '<div class="doc-inner">' . $head . $body . '</div>'
        . $terms . $foot
        . '</div>';
}

/**
 * Official letterhead: logo, organisation identity, statutory registrations,
 * then the document title bar and the number / date / verification strip.
 *
 * Everything is drawn from settings (with SITE_* constants as fallback), so an
 * admin changes the branding once and all 103 document types follow.
 */
function dh_letterhead(array $tpl, array $data): string
{
    $a    = dh_org_assets();
    $org  = (string) ($data['organization_name'] ?? get_setting('site_name', SITE_NAME));
    $addr = (string) get_setting('contact_address', defined('SITE_ADDRESS') ? SITE_ADDRESS : '');
    $mail = (string) get_setting('contact_email',   defined('SITE_EMAIL')   ? SITE_EMAIL   : '');
    $tel  = (string) get_setting('contact_phone',   defined('SITE_PHONE')   ? SITE_PHONE   : '');
    $site = preg_replace('#^https?://#i', '', rtrim(APP_URL, '/'));

    // Statutory identifiers — only the ones actually configured are shown.
    $regs = array_filter([
        'CIN'    => (string) get_setting('cin',           defined('SITE_CIN') ? SITE_CIN : ''),
        'PAN'    => (string) get_setting('pan',           defined('SITE_PAN') ? SITE_PAN : ''),
        'Darpan' => (string) get_setting('ngo_darpan_id', defined('SITE_DARPAN_ID') ? SITE_DARPAN_ID : ''),
    ], static fn($v) => trim((string) $v) !== '');

    $meta = array_filter([$addr, $mail, $tel, $site], static fn($v) => trim((string) $v) !== '');

    $h = '<header class="doc-head">';
    if (!empty($tpl['show_logo']) && $a['logo']) {
        $h .= '<div class="doc-head-logo"><img src="' . e($a['logo']) . '" alt=""></div>';
    }
    $h .= '<div class="doc-head-org">'
        . '<p class="dh-name">' . e($org) . '</p>'
        . '<p class="dh-meta">' . implode('', array_map(static fn($m) => '<span>' . e($m) . '</span>', $meta)) . '</p>'
        . '</div>';
    if ($regs) {
        $h .= '<div class="doc-head-reg">';
        foreach ($regs as $label => $val) {
            $h .= '<div><b>' . e($label) . ':</b> ' . e($val) . '</div>';
        }
        $h .= '</div>';
    }
    $h .= '</header>';

    // Title bar — the document's own type, e.g. "Course Certificate".
    $title = trim((string) ($tpl['doc_type'] ?? $tpl['name'] ?? ''));
    if ($title !== '') {
        $h .= '<div class="doc-titlebar">'
            . '<div class="dt-title">' . e($title) . '</div>'
            . '<div class="dt-sub">' . e(dh_categories()[$tpl['category']] ?? 'Official Document') . '</div>'
            . '</div>';
    }

    // Number / issue date / verification id.
    $no  = (string) ($data['certificate_no'] ?? $data['doc_no'] ?? '');
    $dt  = (string) ($data['issue_date'] ?? date('d M Y'));
    $h .= '<div class="doc-meta">'
        . ($no !== '' ? '<span><b>Document No:</b> ' . e($no) . '</span>' : '')
        . '<span><b>Issue Date:</b> ' . e($dt) . '</span>'
        . '</div>';

    return $h;
}

/** Footer stating how the document can be independently verified. */
function dh_verify_footer(array $data, ?array $issued): string
{
    $token = (string) ($issued['qr_token'] ?? 'PREVIEW');
    $url   = rtrim(APP_URL, '/') . '/verify-document?t=' . $token;
    $no    = (string) ($data['certificate_no'] ?? $data['doc_no'] ?? '');

    return '<footer class="doc-foot">'
        . '<div>This is a digitally issued document. Verify its authenticity at <b>'
        . e(preg_replace('#^https?://#i', '', $url)) . '</b></div>'
        . ($no !== '' ? '<div><b>Ref:</b> ' . e($no) . '</div>' : '')
        . '</footer>';
}

/* -------------------------------------------------------------- NUMBERING / ISSUE / VERIFY */

function dh_gen_number(array $tpl): string
{
    $n = (int) $tpl['number_next'];
    db_update('document_templates', ['number_next' => $n + 1], 'id = :id', [':id' => (int) $tpl['id']]);
    return strtoupper($tpl['number_prefix'] ?: 'DOC') . '-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

/** Issue a document from a template + data. Returns the document_issued row. */
function dh_issue(array $tpl, array $data): array
{
    $no    = dh_gen_number($tpl);
    $token = bin2hex(random_bytes(16));
    $id = db_insert('document_issued', [
        'template_id'     => (int) $tpl['id'],
        'doc_no'          => $no,
        'category'        => $tpl['category'],
        'doc_type'        => $tpl['doc_type'],
        'recipient_name'  => $data['recipient_name'] ?? $data['member_name'] ?? $data['student_name'] ?? null,
        'recipient_email' => $data['recipient_email'] ?? $data['email'] ?? null,
        'data'            => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'qr_token'        => $token,
        'issued_by'       => function_exists('current_user_id') ? current_user_id() : null,
    ]);
    if (function_exists('log_activity')) log_activity('issue', 'document-hub', 'Issued ' . $no . ' (' . $tpl['doc_type'] . ')');
    return find('document_issued', $id);
}

function dh_verify_token(string $token): ?array
{
    return db_row("SELECT * FROM document_issued WHERE qr_token = :t LIMIT 1", [':t' => $token]) ?: null;
}
function dh_find_no(string $no): ?array
{
    return db_row("SELECT * FROM document_issued WHERE doc_no = :n LIMIT 1", [':n' => $no]) ?: null;
}

/* -------------------------------------------------------------- ACCESS CONTROL */

/** Signatory identity (name + designation) from settings, for the builder + render. */
function dh_signatory(): array
{
    return [
        'name'        => get_setting('doc_signatory_name', 'Authorised Signatory'),
        'designation' => get_setting('doc_signatory_designation', get_setting('site_name', SITE_NAME)),
    ];
}

/** Is this template usable in the admin panel? (admin_enabled column, default on.) */
function dh_admin_enabled(array $tpl): bool
{
    return !array_key_exists('admin_enabled', $tpl) || (int) $tpl['admin_enabled'] === 1;
}

/** May the public (recipient) view/download a document from this template? */
function dh_user_enabled(array $tpl): bool
{
    return !array_key_exists('user_enabled', $tpl) || (int) $tpl['user_enabled'] === 1;
}

/* -------------------------------------------------------------- PRINT STYLES */

/** The shared stylesheet for previews + the public print page (all themes/layouts). */
function dh_styles(): string
{
    return <<<CSS
/* =============================================================================
 *  DOCUMENT HUB — enterprise document stylesheet
 * -----------------------------------------------------------------------------
 *  Every template shares this chrome; a template's stored `body` supplies only
 *  its content and {{placeholders}}. A theme therefore restyles all 103
 *  templates at once, and any new template inherits the same quality.
 *
 *  Each theme sets six tokens, and every rule below derives from them:
 *    --acc    primary accent (headings, rules, seal)
 *    --acc2   secondary accent (gradients, ribbon)
 *    --ink    body text
 *    --paper  page background
 *    --orn    ornament / guilloche line colour
 *    --frameW outer frame weight
 *
 *  Print: sizes are in mm so A4 output is physically correct, and
 *  print-color-adjust keeps the security patterns and tints from being
 *  stripped by the browser's "economode" print path.
 * ========================================================================== */

/* ---------------------------------------------------------------- base page */
.doc {
    --acc:#1e3a8a; --acc2:#2563eb; --ink:#1f2937; --paper:#ffffff;
    --orn:rgba(30,58,138,.16); --frameW:2.5mm; --radius:0;
    position:relative; box-sizing:border-box; margin:0 auto; overflow:hidden;
    background:var(--paper); color:var(--ink);
    font-family:'Plus Jakarta Sans','Inter',Georgia,'Times New Roman',serif;
    box-shadow:0 24px 70px -34px rgba(15,23,42,.55);
    -webkit-print-color-adjust:exact; print-color-adjust:exact;
}
.doc *, .doc *::before, .doc *::after { box-sizing:border-box; }

/* -------------------------------------------------------------- page sizes */
/* Physical mm keeps A4 exact in print and PDF. */
.doc-landscape     { width:297mm; min-height:210mm; padding:16mm 18mm; }
.doc-portrait      { width:210mm; min-height:297mm; padding:18mm 16mm; }
.doc-id_horizontal { width:85.6mm; min-height:54mm;  padding:5mm 6mm;  --frameW:1mm; }
.doc-id_vertical   { width:54mm;  min-height:85.6mm; padding:5mm 4.5mm; --frameW:1mm; }

/* ------------------------------------------------- decorative frame layers */
/* Painted by pseudo-elements so no template body has to carry markup. */
.doc-frame { position:absolute; inset:0; pointer-events:none; z-index:1; }
.doc-frame::before {                       /* outer rule */
    content:''; position:absolute; inset:4mm;
    border:var(--frameW) solid var(--acc); border-radius:var(--radius);
}
.doc-frame::after {                        /* inner hairline */
    content:''; position:absolute; inset:6.2mm;
    border:0.35mm solid var(--orn); border-radius:var(--radius);
}
.doc-id_horizontal .doc-frame::before, .doc-id_vertical .doc-frame::before { inset:1.6mm; }
.doc-id_horizontal .doc-frame::after,  .doc-id_vertical .doc-frame::after  { inset:2.7mm; }

/* Guilloche — the fine interference pattern on banknotes and diplomas.
   Pure CSS gradients: no image assets, scales cleanly, prints crisply. */
.doc-guilloche {
    position:absolute; inset:0; z-index:0; pointer-events:none; opacity:.5;
    background-image:
        repeating-linear-gradient(45deg,  var(--orn) 0 .18mm, transparent .18mm 2.4mm),
        repeating-linear-gradient(-45deg, var(--orn) 0 .18mm, transparent .18mm 2.4mm);
    -webkit-mask-image:radial-gradient(ellipse at center, transparent 42%, #000 88%);
            mask-image:radial-gradient(ellipse at center, transparent 42%, #000 88%);
}

/* Corner flourishes */
.doc-corner { position:absolute; width:16mm; height:16mm; z-index:2; pointer-events:none; opacity:.9; }
.doc-corner::before, .doc-corner::after { content:''; position:absolute; background:var(--acc); }
.doc-corner::before { width:100%; height:.7mm; top:0; left:0; }
.doc-corner::after  { width:.7mm; height:100%; top:0; left:0; }
.doc-corner.tl { top:7.5mm; left:7.5mm; }
.doc-corner.tr { top:7.5mm; right:7.5mm; transform:scaleX(-1); }
.doc-corner.bl { bottom:7.5mm; left:7.5mm; transform:scaleY(-1); }
.doc-corner.br { bottom:7.5mm; right:7.5mm; transform:scale(-1); }
.doc-id_horizontal .doc-corner, .doc-id_vertical .doc-corner { display:none; }

/* Top ribbon band */
.doc-band {
    position:absolute; top:4mm; left:4mm; right:4mm; height:7mm; z-index:2; pointer-events:none;
    background:linear-gradient(90deg, var(--acc), var(--acc2) 55%, var(--acc));
}
.doc-band::after {
    content:''; position:absolute; inset:auto 0 -1.1mm 0; height:1.1mm;
    background:linear-gradient(90deg, transparent, var(--orn), transparent);
}
.doc-id_horizontal .doc-band, .doc-id_vertical .doc-band { top:1.6mm; left:1.6mm; right:1.6mm; height:4mm; }

/* --------------------------------------------------------------- content */
.doc-inner {
    position:relative; z-index:3; text-align:center;
    display:flex; flex-direction:column; justify-content:center;
    min-height:100%; padding-top:6mm;
}
.doc-id_horizontal .doc-inner, .doc-id_vertical .doc-inner { text-align:center; padding-top:3mm; }

.doc h1 {
    color:var(--acc); font-size:11mm; line-height:1.1; margin:1mm 0 2mm;
    letter-spacing:.04em; font-weight:800; text-transform:uppercase;
}
.doc h1::after {                            /* rule under the title */
    content:''; display:block; width:38mm; height:.9mm; margin:2.4mm auto 0;
    background:linear-gradient(90deg, transparent, var(--acc), transparent);
}
.doc h2, .doc .doc-name {
    color:var(--ink); font-size:9mm; margin:2mm 0; font-weight:800; letter-spacing:.01em;
    font-family:Georgia,'Times New Roman',serif;
}
/* The recipient's name is the focal point of a certificate. */
.doc-landscape .doc-name, .doc-portrait .doc-name {
    color:var(--acc); border-bottom:.4mm solid var(--orn);
    display:inline-block; padding:0 6mm 1.5mm; margin-bottom:3mm;
}
.doc p { font-size:4.1mm; line-height:1.75; margin:1.4mm 0; color:var(--ink); }
.doc strong { color:var(--acc); font-weight:700; }
.doc .doc-sub {
    color:color-mix(in srgb, var(--ink) 55%, transparent);
    letter-spacing:.24em; text-transform:uppercase; font-size:2.9mm; font-weight:600;
}

/* ID cards need tighter type than a certificate. */
.doc-id_horizontal h1, .doc-id_vertical h1 { font-size:3.4mm; letter-spacing:.08em; margin:.6mm 0 1mm; }
.doc-id_horizontal h1::after, .doc-id_vertical h1::after { width:14mm; height:.4mm; margin-top:1mm; }
.doc-id_horizontal h2, .doc-id_vertical h2,
.doc-id_horizontal .doc-name, .doc-id_vertical .doc-name { font-size:4.2mm; margin:1mm 0; }
.doc-id_horizontal p, .doc-id_vertical p { font-size:2.6mm; line-height:1.5; margin:.5mm 0; }
.doc-id_horizontal .doc-sub, .doc-id_vertical .doc-sub { font-size:2.1mm; letter-spacing:.14em; }

/* ------------------------------------------------------------- components */
.doc-logo-img { max-height:20mm; margin-bottom:2mm; }
.doc-id_horizontal .doc-logo-img, .doc-id_vertical .doc-logo-img { max-height:8mm; margin-bottom:1mm; }

.doc-qr svg { width:22mm; height:22mm; display:block; }
.doc-id_horizontal .doc-qr svg, .doc-id_vertical .doc-qr svg { width:13mm; height:13mm; }
.doc-qr { display:inline-block; padding:1.2mm; background:#fff; border:.3mm solid var(--orn); }

.doc-barcode { height:9mm; width:60%; }
.doc-id_horizontal .doc-barcode, .doc-id_vertical .doc-barcode { height:6mm; width:90%; }

.doc-photo-img {
    width:26mm; height:32mm; object-fit:cover; border-radius:1.5mm;
    border:.7mm solid var(--acc); box-shadow:0 2mm 5mm -2mm rgba(0,0,0,.35);
}
.doc-photo-ph {
    display:inline-grid; place-items:center; width:26mm; height:32mm;
    border:.5mm dashed var(--orn); border-radius:1.5mm;
    color:color-mix(in srgb, var(--ink) 45%, transparent); font-size:2.4mm; letter-spacing:.14em;
}

.doc-seal-img, .doc-sign-img { max-height:22mm; }
.doc-seal-badge {
    display:inline-grid; place-items:center; width:24mm; height:24mm; border-radius:50%;
    border:.8mm double var(--acc); color:var(--acc); position:relative; font-weight:800;
    background:radial-gradient(circle, color-mix(in srgb, var(--acc) 8%, transparent), transparent 70%);
}
.doc-seal-badge .dsb-ring { position:absolute; font-size:1.9mm; letter-spacing:.14em; top:2.4mm; }
.doc-seal-badge .dsb-core { font-size:6mm; }
.doc-sign-cursive {
    font-family:'Brush Script MT','Segoe Script',cursive; font-size:7mm; color:var(--ink);
    border-bottom:.4mm solid var(--ink); padding:0 4mm .5mm;
}

/* Footer: signature | QR | seal */
.doc-footer, .doc-row {
    display:flex; align-items:flex-end; justify-content:space-between; gap:6mm;
    margin-top:6mm; position:relative; z-index:3;
}
.doc-footer > div, .doc-row > div { flex:1 1 0; text-align:center; }
.doc-id_horizontal .doc-footer, .doc-id_vertical .doc-footer,
.doc-id_horizontal .doc-row,   .doc-id_vertical .doc-row { margin-top:2mm; gap:2mm; }

/* Terms / tabular data */
.doc-terms {
    position:relative; z-index:3; text-align:left; font-size:2.9mm;
    color:color-mix(in srgb, var(--ink) 70%, transparent);
    border-top:.3mm solid var(--orn); margin-top:5mm; padding-top:3mm;
}
.doc-terms table { border-collapse:collapse; width:100%; }
.doc-terms th {
    background:color-mix(in srgb, var(--acc) 10%, transparent);
    color:var(--acc); font-weight:700; text-align:left;
}
.doc-terms td, .doc-terms th { border:.25mm solid var(--orn); padding:1.4mm 2mm; }
.doc-terms tr:nth-child(even) td { background:color-mix(in srgb, var(--acc) 4%, transparent); }

/* Watermark */
.doc-watermark {
    position:absolute; inset:0; z-index:1; display:grid; place-items:center; pointer-events:none;
    font-size:26mm; font-weight:900; letter-spacing:.06em; text-transform:uppercase;
    color:color-mix(in srgb, var(--acc) 7%, transparent);
    transform:rotate(-26deg); white-space:nowrap;
}

/* Verification strip — every document states how to check it. */
.doc-verify {
    position:absolute; left:8mm; right:8mm; bottom:6mm; z-index:3;
    font-size:2.5mm; letter-spacing:.06em; text-align:center;
    color:color-mix(in srgb, var(--ink) 55%, transparent);
}
.doc-id_horizontal .doc-verify, .doc-id_vertical .doc-verify { display:none; }

/* ====================================================== LETTERHEAD HEADER ==
 * Injected by dh_render() on A4 layouts, so every document carries the same
 * official identity block without any template body being edited. */
.doc-head {
    position:relative; z-index:3; display:flex; align-items:flex-start; gap:6mm;
    text-align:left; padding-bottom:3mm; margin-bottom:4mm;
    border-bottom:.6mm solid var(--acc);
}
.doc-head::after {                       /* second, thinner rule — letterhead cue */
    content:''; position:absolute; left:0; right:0; bottom:-1.4mm; height:.25mm; background:var(--orn);
}
.doc-head-logo { flex:0 0 auto; }
.doc-head-logo img { max-height:18mm; max-width:34mm; object-fit:contain; }

/* min-width:0 lets this column actually shrink; without it a flex item refuses
   to go below its content width and pushes into the registration block. */
.doc-head-org { flex:1 1 auto; min-width:0; }
.doc-head-org .dh-name {
    font-size:6mm; font-weight:800; color:var(--acc); line-height:1.15;
    letter-spacing:.02em; text-transform:uppercase; margin:0 0 .8mm;
    overflow-wrap:anywhere;
}
/* The contact line is a wrapping flex row. It was a nowrap inline run, so a
   long address + email + phone + URL could not break and ran underneath the
   registration column on the right. Each item stays intact; the LINE wraps. */
.doc-head-org .dh-meta {
    display:flex; flex-wrap:wrap; align-items:baseline; gap:.4mm 2.2mm;
    font-size:2.7mm; line-height:1.5; margin:0;
    color:color-mix(in srgb, var(--ink) 72%, transparent);
}
.doc-head-org .dh-meta span { white-space:normal; overflow-wrap:anywhere; }
/* Separator as its own flex gap item, so it never lands at the start of a
   wrapped line the way an inline ::before bullet did. */
.doc-head-org .dh-meta span + span::before {
    content:'•'; color:var(--orn); margin-right:2.2mm;
}

.doc-head-reg {
    flex:0 0 auto; max-width:46mm; text-align:right; font-size:2.4mm; line-height:1.55;
    color:color-mix(in srgb, var(--ink) 65%, transparent);
    overflow-wrap:anywhere;
}
.doc-head-reg > div { white-space:nowrap; }
.doc-head-reg b { color:var(--acc); }

/* Portrait is ~87mm narrower than landscape — stack the letterhead there so the
   two columns never compete for the same horizontal space. */
.doc-portrait .doc-head { flex-wrap:wrap; }
.doc-portrait .doc-head-reg { max-width:none; width:100%; text-align:left; margin-top:1.2mm; }
.doc-portrait .doc-head-reg > div { display:inline-block; margin-right:4mm; }

/* Document title bar */
.doc-titlebar {
    position:relative; z-index:3; margin:0 0 4mm; padding:2.6mm 5mm; text-align:center;
    background:linear-gradient(90deg, var(--acc), var(--acc2) 60%, var(--acc)); color:#fff;
    border-radius:1mm;
}
.doc-titlebar .dt-title { font-size:5mm; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
.doc-titlebar .dt-sub   { font-size:2.6mm; letter-spacing:.18em; opacity:.85; text-transform:uppercase; }

/* Meta strip: document no | issue date | verification id */
.doc-meta {
    position:relative; z-index:3; display:flex; flex-wrap:wrap; gap:2mm 6mm;
    justify-content:center; margin:0 0 4mm; font-size:2.9mm;
    color:color-mix(in srgb, var(--ink) 75%, transparent);
}
.doc-meta b { color:var(--acc); font-weight:700; }

/* Colour-coded section headers */
.doc-section {
    position:relative; z-index:3; text-align:left; margin:4mm 0 2.4mm;
    font-size:3.4mm; font-weight:800; letter-spacing:.1em; text-transform:uppercase;
    color:var(--acc); padding-left:3mm; border-left:1.2mm solid var(--acc2);
}

/* Information cards — label/value pairs */
.doc-cards {
    position:relative; z-index:3; display:grid; gap:2mm; text-align:left;
    grid-template-columns:repeat(auto-fit, minmax(45mm, 1fr)); margin:0 0 3mm;
}
.doc-card {
    padding:2.4mm 3mm; border-radius:1.2mm;
    border:.25mm solid var(--orn);
    background:color-mix(in srgb, var(--acc) 4%, transparent);
}
.doc-card .dc-label {
    display:block; font-size:2.3mm; letter-spacing:.14em; text-transform:uppercase;
    color:color-mix(in srgb, var(--ink) 55%, transparent); margin-bottom:.6mm;
}
.doc-card .dc-value { font-size:3.4mm; font-weight:700; color:var(--ink); }

/* Data tables (marksheets, grade cards, reports) */
.doc-table { position:relative; z-index:3; width:100%; border-collapse:collapse; margin:0 0 3mm; font-size:3mm; }
.doc-table th {
    background:linear-gradient(90deg, var(--acc), var(--acc2)); color:#fff;
    font-weight:700; text-align:left; letter-spacing:.06em; text-transform:uppercase; font-size:2.6mm;
}
.doc-table th, .doc-table td { border:.25mm solid var(--orn); padding:1.8mm 2.4mm; }
.doc-table tbody tr:nth-child(even) td { background:color-mix(in srgb, var(--acc) 4%, transparent); }
.doc-table tfoot td { font-weight:800; background:color-mix(in srgb, var(--acc) 10%, transparent); color:var(--acc); }
.doc-table .num { text-align:right; font-variant-numeric:tabular-nums; }

/* Verification footer */
.doc-foot {
    position:relative; z-index:3; margin-top:auto; padding-top:2.6mm;
    border-top:.3mm solid var(--orn);
    display:flex; align-items:center; justify-content:space-between; gap:4mm;
    font-size:2.4mm; color:color-mix(in srgb, var(--ink) 62%, transparent); text-align:left;
}
.doc-foot b { color:var(--acc); }
.doc-id_horizontal .doc-head, .doc-id_vertical .doc-head,
.doc-id_horizontal .doc-titlebar, .doc-id_vertical .doc-titlebar,
.doc-id_horizontal .doc-foot, .doc-id_vertical .doc-foot,
.doc-id_horizontal .doc-meta, .doc-id_vertical .doc-meta { display:none; }

/* =============================================================== THEMES ===
 * Each theme is a coordinated FOUR-HUE palette, not one colour at two
 * lightnesses. --acc anchors the document (title, frame, seal); --c1..--c4 are
 * genuinely different hues that the section headers, info cards, table headers,
 * ribbon and badges cycle through. That is what makes the output read as a
 * designed colour system rather than a monochrome page, while staying
 * professional — the hues are harmonised per theme, not arbitrary.
 * ========================================================================== */
.doc-classic     { --acc:#1e3a8a; --acc2:#3b5bb5; --ink:#1f2937; --paper:#fffefb; --orn:rgba(30,58,138,.20); --frameW:1.6mm;
                   --c1:#1e3a8a; --c2:#0e7490; --c3:#b45309; --c4:#15803d; }
.doc-premium     { --acc:#084881; --acc2:#0f6fc4; --ink:#0f172a; --paper:#fbfcff; --orn:rgba(8,72,129,.18);  --frameW:2.2mm;
                   --c1:#084881; --c2:#7c3aed; --c3:#0891b2; --c4:#c2410c; }
.doc-luxury      { --acc:#6b4e00; --acc2:#c9a227; --ink:#2b2312; --paper:#fffdf4; --orn:rgba(184,145,46,.32); --frameW:3mm;
                   --c1:#8a6a00; --c2:#7f1d1d; --c3:#14532d; --c4:#1e3a8a; }
.doc-gold        { --acc:#8a6a00; --acc2:#d4af37; --ink:#2a2410; --paper:#fffdf5; --orn:rgba(212,175,55,.36); --frameW:3mm;
                   --c1:#a16207; --c2:#92400e; --c3:#3f6212; --c4:#155e75; }
.doc-corporate   { --acc:#0f172a; --acc2:#334155; --ink:#111827; --paper:#ffffff; --orn:rgba(15,23,42,.16);  --frameW:1.4mm; --radius:1.5mm;
                   --c1:#0f172a; --c2:#0e7490; --c3:#b45309; --c4:#4338ca; }
.doc-minimal     { --acc:#111827; --acc2:#4b5563; --ink:#111827; --paper:#ffffff; --orn:rgba(17,24,39,.10);  --frameW:.5mm;
                   --c1:#111827; --c2:#374151; --c3:#4b5563; --c4:#6b7280; }
.doc-modern      { --acc:#084881; --acc2:#22d3ee; --ink:#0f172a; --paper:#fbfeff; --orn:rgba(8,72,129,.16);  --frameW:1.6mm; --radius:3mm;
                   --c1:#0369a1; --c2:#0891b2; --c3:#7c3aed; --c4:#db2777; }
.doc-blue        { --acc:#063566; --acc2:#1d6fb8; --ink:#0f2338; --paper:#f9fcff; --orn:rgba(6,53,102,.20);  --frameW:2.2mm;
                   --c1:#063566; --c2:#0e7490; --c3:#4338ca; --c4:#b45309; }
.doc-ngo         { --acc:#2f6b1f; --acc2:#58A42F; --ink:#14260f; --paper:#f9fdf8; --orn:rgba(88,164,47,.26); --frameW:2.2mm;
                   --c1:#58A42F; --c2:#063566; --c3:#E67B1D; --c4:#0e7490; }
.doc-educational { --acc:#9a4a06; --acc2:#E67B1D; --ink:#2b1a0c; --paper:#fffdf7; --orn:rgba(230,123,29,.26); --frameW:2.2mm;
                   --c1:#E67B1D; --c2:#1d4ed8; --c3:#15803d; --c4:#7c3aed; }
.doc-government  { --acc:#7f1d1d; --acc2:#b45309; --ink:#1c1917; --paper:#fffdf8; --orn:rgba(127,29,29,.22); --frameW:2.6mm;
                   --c1:#7f1d1d; --c2:#a16207; --c3:#14532d; --c4:#1e3a8a; }
.doc-green       { --acc:#14532d; --acc2:#16a34a; --ink:#0f2417; --paper:#f8fdfa; --orn:rgba(22,163,74,.24); --frameW:2.2mm;
                   --c1:#15803d; --c2:#0e7490; --c3:#a16207; --c4:#1e3a8a; }
.doc-purple      { --acc:#4c1d95; --acc2:#8b5cf6; --ink:#1e1633; --paper:#fdfbff; --orn:rgba(139,92,246,.24); --frameW:2.2mm;
                   --c1:#6d28d9; --c2:#c026d3; --c3:#0891b2; --c4:#ea580c; }

/* ------------------------------------------------- multi-colour application
 * The palette is applied semantically: consecutive sections, cards and badges
 * step through c1..c4 so a document with several sections is visibly
 * colour-organised, which is also a legibility aid — the reader can tell
 * sections apart at a glance. */
.doc { --c1:var(--acc); --c2:var(--acc2); --c3:var(--acc); --c4:var(--acc2); }

/* Section headers cycle hue, each with its own accent bar. */
.doc-section:nth-of-type(4n+1) { color:var(--c1); border-left-color:var(--c1); }
.doc-section:nth-of-type(4n+2) { color:var(--c2); border-left-color:var(--c2); }
.doc-section:nth-of-type(4n+3) { color:var(--c3); border-left-color:var(--c3); }
.doc-section:nth-of-type(4n+4) { color:var(--c4); border-left-color:var(--c4); }

/* Info cards: coloured left edge + tinted ground, cycling in the same order. */
.doc-card { border-left-width:1.1mm; border-left-style:solid; }
.doc-cards .doc-card:nth-child(4n+1) { border-left-color:var(--c1); background:color-mix(in srgb, var(--c1) 6%, transparent); }
.doc-cards .doc-card:nth-child(4n+2) { border-left-color:var(--c2); background:color-mix(in srgb, var(--c2) 6%, transparent); }
.doc-cards .doc-card:nth-child(4n+3) { border-left-color:var(--c3); background:color-mix(in srgb, var(--c3) 6%, transparent); }
.doc-cards .doc-card:nth-child(4n+4) { border-left-color:var(--c4); background:color-mix(in srgb, var(--c4) 6%, transparent); }
.doc-cards .doc-card:nth-child(4n+1) .dc-value { color:var(--c1); }
.doc-cards .doc-card:nth-child(4n+2) .dc-value { color:var(--c2); }
.doc-cards .doc-card:nth-child(4n+3) .dc-value { color:var(--c3); }
.doc-cards .doc-card:nth-child(4n+4) .dc-value { color:var(--c4); }

/* Multi-hue ribbon and table headers. */
.doc-band { background:linear-gradient(90deg, var(--c1), var(--c2) 33%, var(--c3) 66%, var(--c4)); }
.doc-table th { background:linear-gradient(90deg, var(--c1), var(--c2) 55%, var(--c3)); }
.doc-titlebar { background:linear-gradient(90deg, var(--c1), var(--c2) 50%, var(--c3)); }

/* Corner flourishes alternate so the frame is not one flat colour. */
.doc-corner.tl::before, .doc-corner.tl::after { background:var(--c1); }
.doc-corner.tr::before, .doc-corner.tr::after { background:var(--c2); }
.doc-corner.br::before, .doc-corner.br::after { background:var(--c3); }
.doc-corner.bl::before, .doc-corner.bl::after { background:var(--c4); }

/* Status / achievement chips a template body can drop in. */
.doc-chip {
    display:inline-flex; align-items:center; gap:1.2mm; padding:1mm 2.6mm; border-radius:99mm;
    font-size:2.6mm; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#fff;
}
.doc-chip.c1 { background:var(--c1); } .doc-chip.c2 { background:var(--c2); }
.doc-chip.c3 { background:var(--c3); } .doc-chip.c4 { background:var(--c4); }

/* Corporate and Minimal stay restrained on purpose: they keep the multi-hue
   system for sections and data, but not on the large gradient surfaces. */
.doc-minimal .doc-titlebar, .doc-minimal .doc-table th { background:var(--acc); }
.doc-corporate .doc-titlebar { background:linear-gradient(90deg, var(--c1), var(--c2)); }

/* Minimal is deliberately unornamented — the point of the theme. */
.doc-minimal .doc-guilloche, .doc-minimal .doc-corner, .doc-minimal .doc-band { display:none; }
.doc-minimal .doc-frame::after { display:none; }
.doc-minimal h1 { text-transform:none; letter-spacing:.01em; }

/* Corporate: restrained — band only, no guilloche or corners. */
.doc-corporate .doc-guilloche, .doc-corporate .doc-corner { display:none; }

/* Luxury / Gold: metallic title. */
.doc-luxury h1, .doc-gold h1 {
    background:linear-gradient(92deg, var(--acc), var(--acc2) 45%, var(--acc));
    -webkit-background-clip:text; background-clip:text; color:transparent;
}

/* ---------------------------------------------------------------- SCREEN */
/* Documents are fixed mm widths; scale them down rather than overflow. */
@media screen and (max-width:1100px) {
    .doc-landscape { transform:scale(.72); transform-origin:top center; margin-bottom:-70mm; }
}
@media screen and (max-width:820px) {
    .doc-landscape { transform:scale(.5);  margin-bottom:-108mm; }
    .doc-portrait  { transform:scale(.66); transform-origin:top center; margin-bottom:-100mm; }
}
@media screen and (max-width:560px) {
    .doc-landscape { transform:scale(.32); margin-bottom:-145mm; }
    .doc-portrait  { transform:scale(.44); margin-bottom:-166mm; }
}

/* ----------------------------------------------------------------- PRINT */
@page { margin:0; }
@media print {
    html, body { margin:0; padding:0; background:#fff; }
    .no-print { display:none !important; }
    .doc {
        box-shadow:none; margin:0 auto; transform:none !important;
        page-break-after:always; break-after:page;
    }
    .doc:last-child { page-break-after:auto; break-after:auto; }
    /* Keep tints, guilloche and gradients in the printed/PDF output. */
    .doc, .doc * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
}
/* Sheet orientation follows the document, so A4 lands the right way up. */
@media print { .doc-landscape { page:dl; } .doc-portrait { page:dp; } }
@page dl { size:A4 landscape; margin:0; }
@page dp { size:A4 portrait;  margin:0; }
CSS;
}

/* -------------------------------------------------------------- SEED */

/** Insert a few professional starter templates the first time the hub is opened. */
function dh_seed_defaults(): void
{
    if (db_count('document_templates') > 0) return;
    $tpls = [
        [
            'name' => 'Membership Certificate', 'slug' => 'membership-certificate', 'category' => 'certificate', 'doc_type' => 'Membership Certificate',
            'layout' => 'landscape', 'theme' => 'classic', 'number_prefix' => 'MEMC',
            'body' => '<div class="doc-logo">{{logo}}</div><p class="doc-sub">Certificate of Membership</p><h1>Certificate of Membership</h1><p>This is proudly presented to</p><h2 class="doc-name">{{member_name}}</h2><p>in recognition of valued membership of <strong>{{organization_name}}</strong>.</p><p>Membership No: <strong>{{membership_no}}</strong> &nbsp;&bull;&nbsp; Issued: {{issue_date}}</p><div class="doc-footer"><div>{{signature}}<div class="doc-sub">Authorised Signatory</div></div><div>{{qr_code}}</div><div>{{seal}}</div></div>',
        ],
        [
            'name' => 'Volunteer ID Card', 'slug' => 'volunteer-id-card', 'category' => 'id_card', 'doc_type' => 'Volunteer ID Card',
            'layout' => 'id_vertical', 'theme' => 'ngo', 'number_prefix' => 'VOL', 'show_watermark' => 1,
            'body' => '<div style="text-align:center">{{logo}}<h1>{{organization_name}}</h1><div class="doc-sub">Volunteer</div>{{photo}}<h2 class="doc-name" style="font-size:1.2rem">{{member_name}}</h2><p style="margin:.2rem 0">ID: {{id_no}}<br>Blood Group: {{blood_group}}<br>Valid till: {{expiry_date}}</p><div class="doc-row">{{qr_code}}{{barcode}}</div></div>',
        ],
        [
            'name' => 'Donation Receipt', 'slug' => 'donation-receipt-doc', 'category' => 'receipt', 'doc_type' => 'Donation Receipt',
            'layout' => 'portrait', 'theme' => 'minimal', 'number_prefix' => 'RCPT', 'terms_enabled' => 1,
            'terms' => '<p>This receipt is computer generated. Donations to {{organization_name}} may be eligible for tax exemption under applicable law.</p>',
            'body' => '<div class="doc-logo">{{logo}}</div><h1>Donation Receipt</h1><p>Receipt No: <strong>{{certificate_no}}</strong> &bull; Date: {{issue_date}}</p><p>Received with gratitude from</p><h2 class="doc-name">{{member_name}}</h2><p>the sum towards the work of <strong>{{organization_name}}</strong>.</p><div class="doc-footer"><div>{{signature}}<div class="doc-sub">For {{organization_name}}</div></div><div>{{qr_code}}</div></div>',
        ],
    ];
    foreach ($tpls as $t) {
        db_insert('document_templates', array_merge([
            'terms_enabled' => 0, 'show_qr' => 1, 'show_seal' => 1, 'show_signature' => 1, 'show_logo' => 1,
            'show_watermark' => 0, 'number_next' => 1, 'status' => 'published',
        ], $t));
    }
}

/* =============================================================================
 | STANDARD DOCUMENT CATALOGUE — ~80 ready-made NGO / educational / exam / ID
 | templates. Bodies are produced by parameterised shape generators so every
 | document renders consistently through dh_render() with the right placeholders.
 | ========================================================================== */

/** Signatory + QR/seal footer used by most certificates & letters. */
function dh_foot(bool $seal = true): string
{
    return '<div class="doc-footer"><div>{{signature}}<div class="doc-sub">{{signatory_name}}<br>{{signatory_designation}}</div></div>'
        . '<div>{{qr_code}}</div>' . ($seal ? '<div>{{seal}}</div>' : '') . '</div>';
}

/** Award / certificate body. */
function dh_shape_certificate(array $c): string
{
    $recip  = $c['recipient'] ?? '{{member_name}}';
    $fields = $c['fields'] ?? 'Certificate No: <strong>{{certificate_no}}</strong> &nbsp;&bull;&nbsp; Date: {{issue_date}}';
    return '<div class="doc-logo">{{logo}}</div>'
        . '<p class="doc-sub">' . ($c['subtitle'] ?? 'Certificate') . '</p>'
        . '<h1>' . ($c['title'] ?? 'Certificate') . '</h1>'
        . '<p>' . ($c['lead'] ?? 'This is proudly presented to') . '</p>'
        . '<h2 class="doc-name">' . $recip . '</h2>'
        . '<p>' . ($c['body'] ?? 'in recognition of a valued contribution to the work of <strong>{{organization_name}}</strong>.') . '</p>'
        . '<p>' . $fields . '</p>'
        . dh_foot();
}

/** Vertical ID card / badge body. */
function dh_shape_id_vertical(array $c): string
{
    $name  = $c['recipient'] ?? '{{member_name}}';
    $extra = $c['idfields'] ?? 'Blood Group: {{blood_group}}<br>Emergency: {{emergency_contact}}';
    return '<div style="text-align:center">{{logo}}<h1 style="font-size:.95rem;margin:.25rem 0">{{organization_name}}</h1>'
        . '<div class="doc-sub">' . ($c['role'] ?? 'Member') . '</div>{{photo}}'
        . '<h2 class="doc-name" style="font-size:1.05rem;margin:.35rem 0">' . $name . '</h2>'
        . '<p style="margin:.2rem 0;font-size:.76rem;line-height:1.55">' . ($c['idlabel'] ?? 'ID No') . ': <strong>{{id_no}}</strong><br>' . $extra
        . '<br>Issued: {{issue_date}} &bull; Valid: {{expiry_date}}</p>'
        . '<div class="doc-row" style="justify-content:center;gap:.6rem;margin-top:.3rem">{{qr_code}}</div>'
        . '<div style="margin-top:.25rem">{{signature}}</div></div>';
}

/** Horizontal ID card body (photo + details columns). */
function dh_shape_id_horizontal(array $c): string
{
    $name  = $c['recipient'] ?? '{{member_name}}';
    $extra = $c['idfields'] ?? 'Blood Group: {{blood_group}}<br>Emergency: {{emergency_contact}}';
    return '<div style="display:flex;gap:14px;align-items:stretch;text-align:left">'
        . '<div style="text-align:center;flex:0 0 auto">{{photo}}<div style="margin-top:6px">{{qr_code}}</div></div>'
        . '<div style="flex:1">'
        . '<div style="display:flex;align-items:center;gap:8px">{{logo}}<strong style="font-size:.9rem">{{organization_name}}</strong></div>'
        . '<div class="doc-sub" style="margin:.2rem 0">' . ($c['role'] ?? 'Member') . '</div>'
        . '<h2 class="doc-name" style="font-size:1.05rem;margin:.15rem 0">' . $name . '</h2>'
        . '<p style="font-size:.76rem;line-height:1.55;margin:.2rem 0">' . ($c['idlabel'] ?? 'ID No') . ': <strong>{{id_no}}</strong><br>' . $extra
        . '<br>Issued: {{issue_date}} &bull; Valid: {{expiry_date}}</p>'
        . '<div>{{barcode}}</div></div></div>';
}

/** Receipt body (donation / fee / tax). */
function dh_shape_receipt(array $c): string
{
    return '<div class="doc-logo">{{logo}}</div><h1 style="font-size:1.8rem">' . ($c['title'] ?? 'Receipt') . '</h1>'
        . '<p>Receipt No: <strong>{{certificate_no}}</strong> &bull; Date: {{issue_date}}</p>'
        . '<p>' . ($c['lead'] ?? 'Received with gratitude from') . '</p>'
        . '<h2 class="doc-name">' . ($c['recipient'] ?? '{{member_name}}') . '</h2>'
        . '<p>' . ($c['body'] ?? 'the sum of <strong>{{amount}}</strong> ({{amount_words}}) towards <strong>{{purpose}}</strong>.') . '</p>'
        . '<p style="font-size:.85rem;color:#6b7280">PAN: {{pan}} &bull; Ref: {{reference_no}}</p>'
        . '<div class="doc-footer"><div>{{signature}}<div class="doc-sub">{{signatory_name}}</div></div><div>{{qr_code}}</div></div>';
}

/** Formal letter body. */
function dh_shape_letter(array $c): string
{
    $recip = $c['recipient'] ?? '{{member_name}}';
    return '<div style="text-align:left">'
        . '<div style="text-align:center">{{logo}}<h1 style="font-size:1.3rem;margin:.2rem 0">{{organization_name}}</h1></div>'
        . '<p style="text-align:right;margin:.6rem 0;font-size:.85rem">Ref: {{certificate_no}} &bull; Date: {{issue_date}}</p>'
        . '<h2 style="font-size:1.2rem;text-align:center;margin:.5rem 0">' . ($c['title'] ?? 'Letter') . '</h2>'
        . '<p>Dear ' . $recip . ',</p>'
        . '<p style="line-height:1.9">' . ($c['body'] ?? 'We are pleased to write to you on behalf of <strong>{{organization_name}}</strong>.') . '</p>'
        . '<p style="margin-top:1.4rem">With best regards,</p>'
        . '<div>{{signature}}<br><strong>{{signatory_name}}</strong><br><span class="doc-sub">{{signatory_designation}}</span></div>'
        . '<div class="doc-row" style="justify-content:space-between;align-items:flex-end;margin-top:1rem">{{seal}}{{qr_code}}</div></div>';
}

/** Marksheet / grade card body (subjects table). */
function dh_shape_marksheet(array $c): string
{
    $cell = 'style="border:1px solid #cbd5e1;padding:6px 10px"';
    return '<div class="doc-logo">{{logo}}</div><h1 style="font-size:1.5rem">' . ($c['title'] ?? 'Marksheet') . '</h1>'
        . '<p class="doc-sub">{{organization_name}}</p>'
        . '<div style="text-align:left;margin:1rem 0;font-size:.9rem;line-height:1.7">Name: <strong>{{student_name}}</strong> &nbsp; Roll No: <strong>{{roll_no}}</strong><br>'
        . 'Registration: {{registration_no}} &nbsp; Course: {{course_name}}</div>'
        . '<table style="width:100%;border-collapse:collapse;margin:.6rem 0;font-size:.85rem">'
        . '<thead><tr><th ' . $cell . '>Subject</th><th ' . $cell . '>Max</th><th ' . $cell . '>Obtained</th><th ' . $cell . '>Grade</th></tr></thead>'
        . '<tbody><tr><td ' . $cell . '>Subject 1</td><td ' . $cell . '>100</td><td ' . $cell . '>—</td><td ' . $cell . '>—</td></tr>'
        . '<tr><td ' . $cell . '>Subject 2</td><td ' . $cell . '>100</td><td ' . $cell . '>—</td><td ' . $cell . '>—</td></tr>'
        . '<tr><td ' . $cell . '>Subject 3</td><td ' . $cell . '>100</td><td ' . $cell . '>—</td><td ' . $cell . '>—</td></tr></tbody></table>'
        . '<p>Total: <strong>{{marks}}</strong> &bull; Percentage: <strong>{{percentage}}</strong> &bull; Grade: <strong>{{grade}}</strong></p>'
        . dh_foot();
}

/** Report / scorecard body (field lines). */
function dh_shape_report(array $c): string
{
    return '<div class="doc-logo">{{logo}}</div><h1 style="font-size:1.5rem">' . ($c['title'] ?? 'Report') . '</h1>'
        . '<h2 class="doc-name" style="font-size:1.3rem">' . ($c['recipient'] ?? '{{student_name}}') . '</h2>'
        . '<div style="margin:1rem auto;max-width:520px;text-align:left;font-size:.95rem;line-height:2">'
        . ($c['body'] ?? 'Score: <strong>{{marks}}</strong><br>Percentage: <strong>{{percentage}}</strong><br>Grade: <strong>{{grade}}</strong><br>Rank: <strong>{{rank}}</strong><br>Remarks: {{remarks}}')
        . '</div><p style="font-size:.85rem;color:#6b7280">Ref: {{certificate_no}} &bull; Date: {{issue_date}}</p>'
        . '<div class="doc-footer"><div>{{signature}}<div class="doc-sub">{{signatory_name}}</div></div><div>{{qr_code}}</div><div>{{seal}}</div></div>';
}

/** Small pass / entry card / admit card body. */
function dh_shape_pass(array $c): string
{
    return '<div style="display:flex;gap:12px;align-items:center;text-align:left">'
        . '<div style="text-align:center;flex:0 0 auto">{{photo}}<div style="margin-top:5px">{{qr_code}}</div></div>'
        . '<div style="flex:1"><div style="display:flex;align-items:center;gap:6px">{{logo}}<strong style="font-size:.85rem">{{organization_name}}</strong></div>'
        . '<h1 style="font-size:1rem;margin:.25rem 0">' . ($c['title'] ?? 'Pass') . '</h1>'
        . '<h2 class="doc-name" style="font-size:.95rem;margin:.15rem 0">' . ($c['recipient'] ?? '{{member_name}}') . '</h2>'
        . '<p style="font-size:.74rem;line-height:1.5;margin:.2rem 0">' . ($c['body'] ?? 'Pass No: {{id_no}}') . '<br>{{event_name}} &bull; Valid: {{expiry_date}}</p></div></div>';
}

/** Build a template body from a catalogue entry's shape. */
function dh_build_body(array $e): string
{
    return match ($e['shape']) {
        'id_vertical'   => dh_shape_id_vertical($e),
        'id_horizontal' => dh_shape_id_horizontal($e),
        'receipt'       => dh_shape_receipt($e),
        'letter'        => dh_shape_letter($e),
        'marksheet'     => dh_shape_marksheet($e),
        'report'        => dh_shape_report($e),
        'pass'          => dh_shape_pass($e),
        default         => dh_shape_certificate($e),
    };
}

/** Map a shape to a Document Hub category + layout. */
function dh_shape_meta(string $shape): array
{
    return match ($shape) {
        'id_vertical'   => ['id_card', 'id_vertical'],
        'id_horizontal' => ['id_card', 'id_horizontal'],
        'receipt'       => ['receipt', 'portrait'],
        'letter'        => ['letter', 'portrait'],
        'marksheet'     => ['report', 'portrait'],
        'report'        => ['report', 'portrait'],
        'pass'          => ['pass', 'id_horizontal'],
        default         => ['certificate', 'landscape'],
    };
}

/**
 * The full standard catalogue. Each row: [name, shape, theme, who, overrides].
 * who = 'member' | 'student' (chooses the recipient placeholder).
 */
function dh_catalogue(): array
{
    // [name, shape, theme, who, prefix, extra-overrides]
    $rows = [
        // ---- NGO ----
        ['Membership Certificate', 'certificate', 'ngo', 'member', 'MEMC', ['subtitle' => 'Certificate of Membership', 'body' => 'as a valued member of <strong>{{organization_name}}</strong>. Membership No: <strong>{{membership_no}}</strong>.']],
        ['Membership ID Card', 'id_vertical', 'ngo', 'member', 'MEMID', ['role' => 'Member', 'idlabel' => 'Membership No']],
        ['Volunteer Certificate', 'certificate', 'ngo', 'member', 'VOLC', ['subtitle' => 'Certificate of Appreciation', 'body' => 'for {{volunteer_hours}} hours of dedicated volunteer service to <strong>{{organization_name}}</strong>.']],
        ['Volunteer Badge', 'id_vertical', 'ngo', 'member', 'VBADGE', ['role' => 'Volunteer', 'idlabel' => 'Badge No']],
        ['Internship Certificate', 'certificate', 'educational', 'student', 'INTC', ['subtitle' => 'Internship Certificate', 'body' => 'for successfully completing an internship in <strong>{{program_name}}</strong> with <strong>{{organization_name}}</strong>.']],
        ['Internship Offer Letter', 'letter', 'corporate', 'member', 'INTOL', ['body' => 'We are pleased to offer you an internship in <strong>{{program_name}}</strong> at <strong>{{organization_name}}</strong>, commencing {{issue_date}}. We look forward to your contribution.']],
        ['Internship Completion Certificate', 'certificate', 'educational', 'student', 'INTCC', ['subtitle' => 'Completion Certificate', 'body' => 'for the successful completion of the internship programme <strong>{{program_name}}</strong>.']],
        ['Appreciation Certificate', 'certificate', 'gold', 'member', 'APPC', ['subtitle' => 'Certificate of Appreciation', 'body' => 'in sincere appreciation of outstanding support to <strong>{{organization_name}}</strong>.']],
        ['Participation Certificate', 'certificate', 'blue', 'member', 'PARTC', ['subtitle' => 'Certificate of Participation', 'body' => 'for active participation in <strong>{{event_name}}</strong>.']],
        ['Recognition Certificate', 'certificate', 'premium', 'member', 'RECC', ['subtitle' => 'Certificate of Recognition', 'body' => 'in recognition of exemplary contribution to <strong>{{organization_name}}</strong>.']],
        ['Award Certificate', 'certificate', 'gold', 'member', 'AWDC', ['subtitle' => 'Certificate of Award', 'body' => 'awarded for distinguished achievement and service.']],
        ['Excellence Certificate', 'certificate', 'luxury', 'member', 'EXCC', ['subtitle' => 'Certificate of Excellence', 'body' => 'in honour of excellence and dedication to the mission of <strong>{{organization_name}}</strong>.']],
        ['Donation Receipt', 'receipt', 'minimal', 'member', 'DRCPT', []],
        ['Donation Certificate', 'certificate', 'ngo', 'member', 'DONC', ['subtitle' => 'Certificate of Contribution', 'body' => 'with heartfelt gratitude for a generous donation of <strong>{{amount}}</strong> to <strong>{{organization_name}}</strong>.']],
        ['Donation Tax Receipt', 'receipt', 'corporate', 'member', 'TAXR', ['title' => '80G Tax Receipt', 'body' => 'the sum of <strong>{{amount}}</strong> ({{amount_words}}). Eligible for deduction under Section 80G. PAN: {{pan}}.']],
        ['Partnership Certificate', 'certificate', 'premium', 'member', 'PARC', ['subtitle' => 'Certificate of Partnership', 'body' => 'in recognition of a valued partnership with <strong>{{organization_name}}</strong>.']],
        ['Partnership Agreement', 'letter', 'corporate', 'member', 'PAGR', ['body' => 'This letter confirms the partnership agreement between <strong>{{member_name}}</strong> and <strong>{{organization_name}}</strong> for <strong>{{purpose}}</strong>, effective {{issue_date}}.']],
        ['Sponsor Certificate', 'certificate', 'gold', 'member', 'SPNC', ['subtitle' => 'Certificate of Sponsorship', 'body' => 'in grateful recognition of generous sponsorship of <strong>{{event_name}}</strong>.']],
        ['Sponsor Appreciation Letter', 'letter', 'corporate', 'member', 'SPNL', ['body' => 'On behalf of <strong>{{organization_name}}</strong>, we extend our deepest gratitude for your generous sponsorship, which made <strong>{{event_name}}</strong> possible.']],
        ['Sponsor Agreement', 'letter', 'corporate', 'member', 'SPNA', ['body' => 'This agreement records the sponsorship commitment of <strong>{{member_name}}</strong> towards <strong>{{purpose}}</strong> with <strong>{{organization_name}}</strong>.']],
        ['Event Participation Certificate', 'certificate', 'blue', 'member', 'EVPC', ['subtitle' => 'Certificate of Participation', 'body' => 'for participating in <strong>{{event_name}}</strong> organised by <strong>{{organization_name}}</strong>.']],
        ['Event Pass', 'pass', 'ngo', 'member', 'EVPASS', ['body' => 'Admits one to {{event_name}}']],
        ['Event Entry Card', 'pass', 'blue', 'member', 'EVENT', ['body' => 'Entry Card — {{event_name}}']],
        ['Workshop Certificate', 'certificate', 'educational', 'member', 'WKSC', ['subtitle' => 'Workshop Certificate', 'body' => 'for attending the workshop <strong>{{program_name}}</strong>.']],
        ['Training Certificate', 'certificate', 'educational', 'member', 'TRNC', ['subtitle' => 'Training Certificate', 'body' => 'for successfully completing the training programme <strong>{{course_name}}</strong>.']],
        ['Appointment Letter', 'letter', 'corporate', 'member', 'APPTL', ['body' => 'We are pleased to appoint you to the position of <strong>{{designation}}</strong> at <strong>{{organization_name}}</strong>, effective {{issue_date}}.']],
        ['Joining Letter', 'letter', 'corporate', 'member', 'JOINL', ['body' => 'This confirms your joining as <strong>{{designation}}</strong> with <strong>{{organization_name}}</strong> on {{issue_date}}. We warmly welcome you.']],
        ['Relieving Letter', 'letter', 'corporate', 'member', 'RELVL', ['body' => 'This is to certify that {{member_name}} has been relieved from the position of <strong>{{designation}}</strong> at <strong>{{organization_name}}</strong> as on {{issue_date}}.']],
        ['Experience Letter', 'letter', 'corporate', 'member', 'EXPL', ['body' => 'This is to certify that {{member_name}} served as <strong>{{designation}}</strong> at <strong>{{organization_name}}</strong> and their conduct was found to be satisfactory.']],
        ['Recommendation Letter', 'letter', 'premium', 'member', 'RECL', ['body' => 'It is my pleasure to recommend {{member_name}}, whose commitment and character reflect the highest standards of <strong>{{organization_name}}</strong>.']],
        ['Appreciation Letter', 'letter', 'premium', 'member', 'APPL', ['body' => 'We write to sincerely thank you for your invaluable contribution to <strong>{{organization_name}}</strong>.']],
        ['Appreciation Shield Certificate', 'certificate', 'luxury', 'member', 'SHLD', ['subtitle' => 'Shield of Appreciation', 'body' => 'presented as a token of profound appreciation for exceptional service.']],
        ['Staff ID Card', 'id_vertical', 'corporate', 'member', 'STAFF', ['role' => 'Staff', 'idlabel' => 'Staff ID', 'idfields' => 'Designation: {{designation}}<br>Blood Group: {{blood_group}}']],
        ['Employee ID Card', 'id_vertical', 'corporate', 'member', 'EMPID', ['role' => 'Employee', 'idlabel' => 'Employee ID', 'idfields' => 'Designation: {{designation}}<br>Blood Group: {{blood_group}}']],
        ['Visitor Pass', 'pass', 'minimal', 'member', 'VISIT', ['title' => 'Visitor Pass', 'body' => 'Visitor: {{member_name}}<br>Purpose: {{purpose}}']],
        ['Press Pass', 'pass', 'corporate', 'member', 'PRESS', ['title' => 'Press Pass', 'body' => 'Media Representative']],
        // ---- Educational ----
        ['Admission Letter', 'letter', 'educational', 'student', 'ADML', ['body' => 'Congratulations! You have been granted admission to <strong>{{course_name}}</strong> at <strong>{{organization_name}}</strong>. Registration No: {{registration_no}}.']],
        ['Admission Confirmation', 'certificate', 'educational', 'student', 'ADMCF', ['subtitle' => 'Admission Confirmation', 'body' => 'has been confirmed for admission to <strong>{{course_name}}</strong>. Registration No: {{registration_no}}.']],
        ['Student ID Card', 'id_vertical', 'educational', 'student', 'STUID', ['role' => 'Student', 'idlabel' => 'Roll No', 'idfields' => 'Course: {{course_name}}<br>Blood Group: {{blood_group}}']],
        ['Student Profile', 'report', 'educational', 'student', 'STUP', ['title' => 'Student Profile', 'body' => 'Roll No: <strong>{{roll_no}}</strong><br>Course: {{course_name}}<br>Registration: {{registration_no}}<br>Father: {{father_name}}<br>Address: {{address}}']],
        ['Enrollment Certificate', 'certificate', 'educational', 'student', 'ENRC', ['subtitle' => 'Enrollment Certificate', 'body' => 'is duly enrolled in <strong>{{course_name}}</strong> at <strong>{{organization_name}}</strong>.']],
        ['Bonafide Certificate', 'certificate', 'educational', 'student', 'BONA', ['subtitle' => 'Bonafide Certificate', 'body' => 'is a bonafide student of <strong>{{organization_name}}</strong>, enrolled in <strong>{{course_name}}</strong>, Roll No {{roll_no}}.']],
        ['Character Certificate', 'certificate', 'educational', 'student', 'CHARC', ['subtitle' => 'Character Certificate', 'body' => 'bore a good moral character throughout their association with <strong>{{organization_name}}</strong>.']],
        ['Transfer Certificate', 'certificate', 'educational', 'student', 'TC', ['subtitle' => 'Transfer Certificate', 'body' => 'is granted a transfer certificate from <strong>{{organization_name}}</strong>. Roll No {{roll_no}}.']],
        ['Leaving Certificate', 'certificate', 'educational', 'student', 'LC', ['subtitle' => 'Leaving Certificate', 'body' => 'has left <strong>{{organization_name}}</strong> after completing <strong>{{course_name}}</strong>.']],
        ['Marksheet', 'marksheet', 'educational', 'student', 'MRKS', ['title' => 'Statement of Marks']],
        ['Grade Card', 'marksheet', 'blue', 'student', 'GRDC', ['title' => 'Grade Card']],
        ['Result Card', 'marksheet', 'blue', 'student', 'RESC', ['title' => 'Result Card']],
        ['Progress Report', 'report', 'educational', 'student', 'PROG', ['title' => 'Progress Report', 'body' => 'Course: {{course_name}}<br>Marks: <strong>{{marks}}</strong><br>Percentage: <strong>{{percentage}}</strong><br>Grade: <strong>{{grade}}</strong><br>Remarks: {{remarks}}']],
        ['Completion Certificate', 'certificate', 'educational', 'student', 'COMP', ['subtitle' => 'Completion Certificate', 'body' => 'for successfully completing <strong>{{course_name}}</strong> at <strong>{{organization_name}}</strong>.']],
        ['Course Certificate', 'certificate', 'educational', 'student', 'CRSC', ['subtitle' => 'Course Certificate', 'body' => 'has successfully completed the course <strong>{{course_name}}</strong> with grade {{grade}}.']],
        ['Diploma Certificate', 'certificate', 'luxury', 'student', 'DIPL', ['subtitle' => 'Diploma', 'body' => 'is hereby awarded the Diploma in <strong>{{course_name}}</strong>.']],
        ['Assignment Submission Receipt', 'receipt', 'minimal', 'student', 'ASUB', ['title' => 'Assignment Receipt', 'lead' => 'Received an assignment submission from', 'body' => 'for <strong>{{course_name}}</strong>. Reference: {{reference_no}}.']],
        ['Assignment Evaluation Report', 'report', 'educational', 'student', 'AEVR', ['title' => 'Assignment Evaluation', 'body' => 'Course: {{course_name}}<br>Marks: <strong>{{marks}}</strong> / 100<br>Grade: <strong>{{grade}}</strong><br>Remarks: {{remarks}}']],
        ['Practical Certificate', 'certificate', 'educational', 'student', 'PRAC', ['subtitle' => 'Practical Certificate', 'body' => 'has satisfactorily completed the practical work for <strong>{{course_name}}</strong>.']],
        ['Hall Ticket', 'pass', 'blue', 'student', 'HALL', ['title' => 'Hall Ticket', 'body' => 'Roll No: {{roll_no}}<br>Course: {{course_name}}']],
        ['Admit Card', 'pass', 'blue', 'student', 'ADMIT', ['title' => 'Admit Card', 'body' => 'Roll No: {{roll_no}}<br>Course: {{course_name}}']],
        ['Exam Attendance Sheet', 'report', 'minimal', 'student', 'ATTN', ['title' => 'Attendance Record', 'body' => 'Roll No: {{roll_no}}<br>Course: {{course_name}}<br>Status: Present']],
        ['Fee Receipt', 'receipt', 'minimal', 'student', 'FEER', ['title' => 'Fee Receipt', 'body' => 'the sum of <strong>{{amount}}</strong> ({{amount_words}}) towards fees for <strong>{{course_name}}</strong>.']],
        ['Fee Invoice', 'receipt', 'corporate', 'student', 'FEEIN', ['title' => 'Fee Invoice', 'lead' => 'Invoice raised to', 'body' => 'Amount due: <strong>{{amount}}</strong> for <strong>{{course_name}}</strong>. Due date: {{expiry_date}}.']],
        ['Scholarship Certificate', 'certificate', 'gold', 'student', 'SCHL', ['subtitle' => 'Scholarship Award', 'body' => 'is awarded a scholarship of <strong>{{amount}}</strong> for excellence in <strong>{{course_name}}</strong>.']],
        ['Merit Certificate', 'certificate', 'gold', 'student', 'MERIT', ['subtitle' => 'Certificate of Merit', 'body' => 'is awarded this certificate of merit for securing {{percentage}} in <strong>{{course_name}}</strong>.']],
        // ---- Exams & Quizzes ----
        ['Quiz Certificate', 'certificate', 'blue', 'student', 'QZC', ['subtitle' => 'Quiz Certificate', 'body' => 'for successfully completing the quiz <strong>{{event_name}}</strong> with a score of {{marks}}.']],
        ['Quiz Result', 'report', 'blue', 'student', 'QZR', ['title' => 'Quiz Result', 'body' => 'Quiz: {{event_name}}<br>Score: <strong>{{marks}}</strong><br>Percentage: <strong>{{percentage}}</strong><br>Grade: {{grade}}']],
        ['Quiz Scorecard', 'report', 'blue', 'student', 'QZS', ['title' => 'Quiz Scorecard', 'body' => 'Score: <strong>{{marks}}</strong><br>Percentage: <strong>{{percentage}}</strong><br>Rank: <strong>{{rank}}</strong>']],
        ['Exam Result', 'report', 'blue', 'student', 'EXR', ['title' => 'Exam Result', 'body' => 'Course: {{course_name}}<br>Marks: <strong>{{marks}}</strong><br>Percentage: <strong>{{percentage}}</strong><br>Grade: <strong>{{grade}}</strong><br>Result: PASS']],
        ['Exam Certificate', 'certificate', 'blue', 'student', 'EXC', ['subtitle' => 'Examination Certificate', 'body' => 'has passed the examination in <strong>{{course_name}}</strong> with grade {{grade}}.']],
        ['Assessment Report', 'report', 'educational', 'student', 'ASMT', ['title' => 'Assessment Report', 'body' => 'Assessment: {{event_name}}<br>Score: <strong>{{marks}}</strong><br>Percentage: <strong>{{percentage}}</strong><br>Remarks: {{remarks}}']],
        ['Rank Certificate', 'certificate', 'gold', 'student', 'RANK', ['subtitle' => 'Rank Certificate', 'body' => 'secured <strong>Rank {{rank}}</strong> in <strong>{{event_name}}</strong>.']],
        ['Performance Report', 'report', 'educational', 'student', 'PERF', ['title' => 'Performance Report', 'body' => 'Score: <strong>{{marks}}</strong><br>Percentage: <strong>{{percentage}}</strong><br>Grade: <strong>{{grade}}</strong><br>Rank: {{rank}}<br>Remarks: {{remarks}}']],
        // ---- ID Cards (horizontal) ----
        ['NGO Member ID', 'id_horizontal', 'ngo', 'member', 'NGOID', ['role' => 'Member', 'idlabel' => 'Member ID']],
        ['Student ID', 'id_horizontal', 'educational', 'student', 'STIDH', ['role' => 'Student', 'idlabel' => 'Roll No', 'idfields' => 'Course: {{course_name}}<br>Blood Group: {{blood_group}}']],
        ['Volunteer ID', 'id_horizontal', 'ngo', 'member', 'VOLID', ['role' => 'Volunteer', 'idlabel' => 'Volunteer ID']],
        ['Employee ID', 'id_horizontal', 'corporate', 'member', 'EMPIDH', ['role' => 'Employee', 'idlabel' => 'Employee ID', 'idfields' => 'Designation: {{designation}}<br>Blood Group: {{blood_group}}']],
        ['Staff ID', 'id_horizontal', 'corporate', 'member', 'STFIDH', ['role' => 'Staff', 'idlabel' => 'Staff ID', 'idfields' => 'Designation: {{designation}}<br>Blood Group: {{blood_group}}']],
        ['Trainer ID', 'id_horizontal', 'premium', 'member', 'TRNID', ['role' => 'Trainer', 'idlabel' => 'Trainer ID']],
        ['Faculty ID', 'id_horizontal', 'educational', 'member', 'FACID', ['role' => 'Faculty', 'idlabel' => 'Faculty ID', 'idfields' => 'Department: {{designation}}<br>Blood Group: {{blood_group}}']],
        ['Internship ID', 'id_horizontal', 'blue', 'student', 'INTID', ['role' => 'Intern', 'idlabel' => 'Intern ID']],
        ['Visitor ID', 'id_horizontal', 'minimal', 'member', 'VISID', ['role' => 'Visitor', 'idlabel' => 'Visitor ID', 'idfields' => 'Purpose: {{purpose}}']],
        ['Partner ID', 'id_horizontal', 'premium', 'member', 'PARID', ['role' => 'Partner', 'idlabel' => 'Partner ID']],
        ['Sponsor ID', 'id_horizontal', 'gold', 'member', 'SPNID', ['role' => 'Sponsor', 'idlabel' => 'Sponsor ID']],
    ];

    $out = [];
    foreach ($rows as $r) {
        [$name, $shape, $theme, $who, $prefix, $ov] = $r;
        [$cat, $layout] = dh_shape_meta($shape);
        $recipient = ($who === 'student') ? '{{student_name}}' : '{{member_name}}';
        $out[] = array_merge([
            'name' => $name, 'shape' => $shape, 'category' => $cat, 'layout' => $layout, 'theme' => $theme,
            'prefix' => $prefix, 'doc_type' => $name, 'recipient' => $recipient, 'title' => $name,
        ], $ov);
    }
    return $out;
}

/**
 * Insert every catalogue template that isn't already present (idempotent by
 * slug + name). Returns the number of templates added. Safe to run repeatedly.
 */
function dh_seed_catalogue(): int
{
    $added = 0;
    foreach (dh_catalogue() as $e) {
        $slug = slugify($e['name']);
        $exists = db_value('SELECT id FROM document_templates WHERE slug = :s OR name = :n LIMIT 1', [':s' => $slug, ':n' => $e['name']]);
        if ($exists !== null) {
            continue;
        }
        $isId = in_array($e['shape'], ['id_vertical', 'id_horizontal', 'pass'], true);
        db_insert('document_templates', [
            'name' => $e['name'], 'slug' => unique_slug('document_templates', $slug),
            'category' => $e['category'], 'doc_type' => $e['doc_type'],
            'layout' => $e['layout'], 'theme' => $e['theme'], 'body' => dh_build_body($e),
            'terms_enabled' => 0, 'terms' => '',
            'show_qr' => 1, 'show_seal' => $isId ? 0 : 1, 'show_signature' => 1, 'show_logo' => 1,
            'show_watermark' => $isId ? 1 : 0, 'watermark_text' => $isId ? get_setting('site_name', SITE_NAME) : '',
            'number_prefix' => $e['prefix'], 'number_next' => 1,
            'status' => 'published', 'admin_enabled' => 1, 'user_enabled' => 1,
            'created_by' => function_exists('current_user_id') ? current_user_id() : null,
        ]);
        $added++;
    }
    return $added;
}
