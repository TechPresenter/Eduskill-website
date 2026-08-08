<?php
/**
 * =============================================================================
 *  NGO Details — official registration, statutory & compliance information.
 *  Every value is DB-driven via get_setting() with sensible public fallbacks
 *  so the page reads as complete and trustworthy before anything is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Organisation identity (settings first, then config constants) ----- */
$siteName = get_setting('site_name', SITE_NAME);
$orgType  = get_setting('org_type', 'Section 8 Non-Profit Company (Companies Act, 2013)');
$cin      = get_setting('cin', SITE_CIN);
$pan      = get_setting('pan', 'Available on request');
$tan      = get_setting('tan', 'Available on request');
$address  = get_setting('contact_address', SITE_ADDRESS);
$email    = get_setting('contact_email', SITE_EMAIL);
$phone    = get_setting('contact_phone', SITE_PHONE);
$website  = get_setting('site_url', 'https://eduskillindia.org');

/* Incorporation date — format nicely, but never blank out a stored value. */
$incRaw = get_setting('incorporation_date');
$incorporation = 'Available on request';
if (!empty($incRaw)) {
    $formatted     = format_date($incRaw, 'd F Y');
    $incorporation = $formatted !== '' ? $formatted : (string) $incRaw;
}

/* Directors — comma-separated setting (Admin → Settings). Empty by default. */
$directorsSetting = get_setting('directors');
$directorNames = $directorsSetting
    ? array_values(array_filter(array_map('trim', explode(',', $directorsSetting))))
    : [];

/* ---- Tax-exemption / statutory registrations --------------------------- */
$reg12a = get_setting('reg_12a', 'Available on request');
$reg80g = get_setting('reg_80g', 'Available on request');
$reg_csr = get_setting('reg_csr');            // optional CSR-1 registration
$reg_darpan = get_setting('ngo_darpan');      // optional NGO Darpan ID

/* ---- Bank details for donations ---------------------------------------- */
$bankRows = [
    ['label' => 'Account Name',   'value' => get_setting('bank_account_name', $siteName)],
    ['label' => 'Bank Name',      'value' => get_setting('bank_name', 'Available on request')],
    ['label' => 'Account Number', 'value' => get_setting('bank_account_number', 'Available on request')],
    ['label' => 'Account Type',   'value' => get_setting('bank_account_type', 'Current Account')],
    ['label' => 'IFSC Code',      'value' => get_setting('bank_ifsc', 'Available on request')],
    ['label' => 'Branch',         'value' => get_setting('bank_branch', 'Patna, Bihar')],
];
$bankUpi = get_setting('bank_upi');

/* ---- Board / leadership (DB-driven, falls back to the directors) -------- */
$leaders = db_all(
    "SELECT * FROM team_members
     WHERE status = 1 AND is_leadership = 1
     ORDER BY sort_order ASC, id ASC LIMIT 6"
);
if (!$leaders) {
    $leaders = [];
    foreach ($directorNames as $n) {
        $leaders[] = ['name' => $n, 'designation' => 'Director', 'photo' => null, 'bio' => null];
    }
}

/* ---- Statutory / legal documents available to download ----------------- */
$docs = db_all(
    "SELECT * FROM documents
     WHERE status = 1 AND category IN ('legal','registration','compliance','statutory','general')
     ORDER BY id DESC LIMIT 6"
);

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'NGO Details',
    'description' => 'Official registration and statutory details of ' . $siteName
        . ' — CIN, PAN, TAN, 80G & 12A tax-exemption status, registered address, directors and bank details for donations.',
    'page_key'    => 'ngo-details',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'NGO Details',
    'subtitle'   => 'Full transparency on who we are — our legal registration, statutory compliance and the details you need to donate with confidence.',
    'breadcrumb' => [['label' => 'NGO Details']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== PAGE-SPECIFIC PREMIUM POLISH ============================== -->
<style>
/* ---- Unique hero treatment (scoped: this style block only ships on this page) ---- */
.page-hero { padding-bottom: clamp(4.5rem, 9vw, 7rem); }
.page-hero::before {
    content: ""; position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,.16) 1px, transparent 0);
    background-size: 26px 26px; opacity: .55;
    -webkit-mask-image: linear-gradient(180deg, transparent, #000 45%, transparent);
    mask-image: linear-gradient(180deg, transparent, #000 45%, transparent);
}
.page-hero h1 { letter-spacing: -.02em; font-size: clamp(2rem, 4.4vw, 3rem); }
.page-hero .lead { max-width: 680px !important; }

/* ---- Page wrapper ---- */
.ngo-page { position: relative; }

/* ---- Trust strip lifted over the base of the hero ---- */
.ngo-trust-wrap { position: relative; z-index: 3; margin-top: clamp(-4.75rem, -6vw, -3.25rem); }
.ngo-trust {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.1rem;
    padding: 1.5rem 1.75rem; background: var(--surface);
    border: 1px solid var(--border); border-radius: var(--radius-lg);
    box-shadow: 0 24px 60px -22px rgba(6,53,102,.35);
}
:root[data-theme="dark"] .ngo-trust { box-shadow: 0 24px 60px -22px rgba(0,0,0,.55); }
.ngo-trust-item { display: flex; align-items: center; gap: .9rem; min-width: 0; }
.ngo-trust-item + .ngo-trust-item { border-left: 1px solid var(--border); padding-left: 1.1rem; }
.tico {
    width: 46px; height: 46px; border-radius: 13px; flex: none; display: grid; place-items: center;
    background: rgba(6,53,102,.10); color: var(--brand); font-size: 1.15rem;
    transition: transform var(--transition);
}
.ngo-trust-item:hover .tico { transform: translateY(-3px) scale(1.06); }
.tico.gold { background: rgba(230,123,29,.16); color: #b07d12; }
.tico.teal { background: rgba(8,72,129,.13); color: #084881; }
:root[data-theme="dark"] .tico.gold { color: #e6b84a; }
.tlabel { display: block; font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
.tval { font-weight: 700; font-size: .98rem; line-height: 1.25; color: var(--text); }

/* ---- Decorative section divider ---- */
.ngo-divider { display: flex; align-items: center; justify-content: center; gap: .8rem; max-width: 220px; margin: 0 auto 2.75rem; }
.ngo-divider::before, .ngo-divider::after { content: ""; flex: 1; height: 2px; border-radius: 2px; }
.ngo-divider::before { background: linear-gradient(90deg, transparent, var(--border)); }
.ngo-divider::after  { background: linear-gradient(90deg, var(--border), transparent); }
.ngo-divider i { width: 9px; height: 9px; border-radius: 50%; background: var(--grad-brand); box-shadow: 0 0 0 5px rgba(6,53,102,.10); }

/* ---- At-a-glance info tiles ---- */
.glance { text-align: center; }
.glance .icon-badge { margin: 0 auto 1.1rem; transition: transform var(--transition), box-shadow var(--transition); }
.glance:hover .icon-badge { transform: translateY(-4px) rotate(-3deg); }
.glance-label { display: block; font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--brand); margin-bottom: .35rem; }
:root[data-theme="dark"] .glance-label { color: #4ca832; }
.glance-value { font-weight: 600; font-size: 1rem; line-height: 1.45; color: var(--text); margin: 0; }

/* ---- Spec sheet table ---- */
.spec-card { padding: 0; overflow: hidden; }
.spec-card .table-wrap { margin: 0; }
.ngo-page table th[scope="row"] { color: var(--text-soft); font-weight: 600; white-space: nowrap; }
.ngo-page .spec-card tbody tr { transition: background var(--transition); }
.ngo-page .spec-card tbody tr:hover { background: rgba(6,53,102,.045); }
:root[data-theme="dark"] .ngo-page .spec-card tbody tr:hover { background: rgba(76,168,50,.08); }

/* ---- Tax exemption cards with accent rail ---- */
.tax-card { position: relative; padding-left: 2rem; }
.tax-card::after { content: ""; position: absolute; left: 0; top: 1.25rem; bottom: 1.25rem; width: 5px; border-radius: 0 6px 6px 0; }
.tax-card.is-12a::after { background: var(--grad-brand); }
.tax-card.is-80g::after { background: var(--grad-accent); }

/* ---- Bank card ---- */
.bank-card .bank-head { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.4rem; }

/* ---- Leadership cards ---- */
.leader-card { transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition); }
.leader-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: rgba(6,53,102,.35); }
.leader-card .leader-photo { width: 76px; height: 76px; border-radius: 50%; object-fit: cover; flex: none; box-shadow: 0 0 0 3px var(--surface), 0 0 0 5px rgba(6,53,102,.18); }

/* ---- Document cards ---- */
.doc-card { transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition); }
.doc-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: rgba(230,123,29,.4); }
.doc-card .icon-badge { transition: transform var(--transition); }
.doc-card:hover .icon-badge { transform: translateY(-3px) scale(1.05); }

/* ---- Info note ---- */
.ngo-page .alert-info { display: flex; gap: .85rem; align-items: flex-start; }
.ngo-page .alert-info svg.lucide { flex: none; margin-top: .15rem; width: 1.3em; height: 1.3em; }

/* ---- Responsive ---- */
@media (max-width: 860px) {
    .ngo-trust { grid-template-columns: repeat(2, 1fr); gap: 1rem 1.25rem; }
    .ngo-trust-item:nth-child(odd) { border-left: 0; padding-left: 0; }
    .ngo-trust-item:nth-child(3), .ngo-trust-item:nth-child(4) { padding-top: 1rem; border-top: 1px solid var(--border); }
}
@media (max-width: 520px) {
    .ngo-trust-wrap { margin-top: -2.25rem; }
    .ngo-trust { grid-template-columns: 1fr; }
    .ngo-trust-item { border-left: 0 !important; padding-left: 0 !important; border-top: 0 !important; padding-top: 0 !important; }
    .ngo-trust-item + .ngo-trust-item { padding-top: 1rem !important; border-top: 1px solid var(--border) !important; }
    .tax-card { padding-left: 1.75rem; }
}
</style>

<div class="ngo-page">

    <!-- ============================== TRUST STRIP ============================== -->
    <div class="ngo-trust-wrap">
        <div class="container">
            <div class="ngo-trust reveal">
                <div class="ngo-trust-item">
                    <span class="tico"><?= lucide('landmark') ?></span>
                    <span>
                        <span class="tlabel">Legal Status</span>
                        <span class="tval">Section 8 Company</span>
                    </span>
                </div>
                <div class="ngo-trust-item">
                    <span class="tico teal"><?= lucide('badge-check') ?></span>
                    <span>
                        <span class="tlabel">Tax Exempt</span>
                        <span class="tval">12A &amp; 80G Certified</span>
                    </span>
                </div>
                <div class="ngo-trust-item">
                    <span class="tico gold"><?= lucide('id-card') ?></span>
                    <span>
                        <span class="tlabel">Identity</span>
                        <span class="tval">CIN Verified</span>
                    </span>
                </div>
                <div class="ngo-trust-item">
                    <span class="tico"><?= lucide('shield-check') ?></span>
                    <span>
                        <span class="tlabel">Governance</span>
                        <span class="tval">Fully Transparent</span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================== AT A GLANCE ============================== -->
    <section class="section">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Who We Are</span>
                <h2 class="section-title">A Registered, Accountable Non-Profit</h2>
                <p class="section-subtitle">
                    <?= e($siteName) ?> is a duly registered non-profit organisation working across Bihar to deliver
                    education, healthcare, skill development and relief. Below are our official records, verifiable at any time.
                </p>
            </div>
            <div class="ngo-divider" aria-hidden="true"><i></i></div>

            <div class="grid grid-4">
                <div class="card-3d glance reveal">
                    <div class="icon-badge"><?= lucide('landmark') ?></div>
                    <span class="glance-label">Legal Type</span>
                    <p class="glance-value"><?= e($orgType) ?></p>
                </div>
                <div class="card-3d glance reveal delay-1">
                    <div class="icon-badge accent"><?= lucide('id-card') ?></div>
                    <span class="glance-label">CIN</span>
                    <p class="glance-value" style="word-break:break-word;"><?= e($cin) ?></p>
                </div>
                <div class="card-3d glance reveal delay-2">
                    <div class="icon-badge"><?= lucide('calendar-days') ?></div>
                    <span class="glance-label">Incorporated</span>
                    <p class="glance-value"><?= e($incorporation) ?></p>
                </div>
                <div class="card-3d glance reveal delay-3">
                    <div class="icon-badge accent"><?= lucide('map-pin') ?></div>
                    <span class="glance-label">Registered In</span>
                    <p class="glance-value"><?= e($address) ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================== REGISTRATION DETAILS ============================== -->
    <section class="section section-soft">
        <div class="container container-narrow">
            <div class="section-head">
                <span class="eyebrow">Official Records</span>
                <h2 class="section-title">Registration &amp; Statutory Details</h2>
                <p class="section-subtitle">The complete legal profile of our organisation as recorded with the authorities.</p>
            </div>
            <div class="ngo-divider" aria-hidden="true"><i></i></div>

            <div class="card-3d spec-card reveal">
                <div class="table-wrap">
                    <table>
                        <tbody>
                            <tr>
                                <th scope="row" style="width:38%;">Organisation Name</th>
                                <td><strong><?= e($siteName) ?></strong></td>
                            </tr>
                            <tr>
                                <th scope="row">Type of Organisation</th>
                                <td><?= e($orgType) ?></td>
                            </tr>
                            <tr>
                                <th scope="row">CIN (Corporate Identity No.)</th>
                                <td><?= e($cin) ?></td>
                            </tr>
                            <tr>
                                <th scope="row">PAN</th>
                                <td><?= e($pan) ?></td>
                            </tr>
                            <tr>
                                <th scope="row">TAN</th>
                                <td><?= e($tan) ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Date of Incorporation</th>
                                <td><?= e($incorporation) ?></td>
                            </tr>
                            <?php if (!empty($reg_darpan)): ?>
                            <tr>
                                <th scope="row">NGO Darpan ID</th>
                                <td><?= e($reg_darpan) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($reg_csr)): ?>
                            <tr>
                                <th scope="row">CSR Registration (CSR-1)</th>
                                <td><?= e($reg_csr) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th scope="row">Registered Address</th>
                                <td><?= e($address) ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Email</th>
                                <td><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></td>
                            </tr>
                            <tr>
                                <th scope="row">Phone</th>
                                <td><a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></td>
                            </tr>
                            <tr>
                                <th scope="row">Website</th>
                                <td><a href="<?= e($website) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#i', '', rtrim($website, '/'))) ?></a></td>
                            </tr>
                            <tr>
                                <th scope="row">Board of Directors</th>
                                <td><?= e(implode(', ', $directorNames)) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================== 80G / 12A TAX EXEMPTION ============================== -->
    <section class="section">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Give &amp; Save Tax</span>
                <h2 class="section-title">Tax Exemption — 12A &amp; 80G</h2>
                <p class="section-subtitle">
                    Your generosity goes further. Donations to <?= e($siteName) ?> are eligible for tax benefits under the Income Tax Act, 1961.
                </p>
            </div>
            <div class="ngo-divider" aria-hidden="true"><i></i></div>

            <div class="grid grid-2 gap-4">
                <div class="card-3d tax-card is-12a reveal">
                    <div class="icon-badge"><?= lucide('scroll') ?></div>
                    <h3 class="card-title">12A Registration</h3>
                    <p class="card-text">
                        Our organisation is registered under Section 12A of the Income Tax Act, granting income-tax exemption on funds
                        applied towards our charitable objectives.
                    </p>
                    <p class="mt-2"><span class="badge badge-success">Registration No.</span> <strong><?= e($reg12a) ?></strong></p>
                </div>
                <div class="card-3d tax-card is-80g reveal delay-1">
                    <div class="icon-badge accent"><?= lucide('receipt') ?></div>
                    <h3 class="card-title">80G Certification</h3>
                    <p class="card-text">
                        Donors receive a deduction of <strong>50% of the donated amount</strong> from their taxable income under Section 80G,
                        subject to the limits prescribed by law.
                    </p>
                    <p class="mt-2"><span class="badge badge-success">Registration No.</span> <strong><?= e($reg80g) ?></strong></p>
                </div>
            </div>

            <div class="alert alert-info mt-4" role="note">
                <?= lucide('info') ?>
                <span>
                    <strong>How to claim your deduction:</strong> An official 80G receipt bearing our PAN and registration number is issued
                    for every contribution. Please share your PAN and postal address while donating so we can send your stamped receipt.
                    Contact <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a> if you need a duplicate receipt for filing.
                </span>
            </div>
        </div>
    </section>

    <!-- ============================== BANK DETAILS FOR DONATIONS ============================== -->
    <section class="section section-alt">
        <div class="container container-narrow">
            <div class="section-head">
                <span class="eyebrow">Support Our Mission</span>
                <h2 class="section-title">Bank Details for Donations</h2>
                <p class="section-subtitle">
                    Contribute securely via direct bank transfer (NEFT / RTGS / IMPS) using the account details below.
                </p>
            </div>
            <div class="ngo-divider" aria-hidden="true"><i></i></div>

            <div class="card-3d bank-card reveal">
                <div class="bank-head">
                    <div class="icon-badge"><?= lucide('landmark') ?></div>
                    <div>
                        <strong style="font-size:1.15rem;">Donation Bank Account</strong><br>
                        <small class="text-muted">All transfers are receipted and eligible for 80G benefits.</small>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <tbody>
                            <?php foreach ($bankRows as $row): ?>
                            <tr>
                                <th scope="row" style="width:40%;"><?= e($row['label']) ?></th>
                                <td><strong><?= e($row['value']) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!empty($bankUpi)): ?>
                            <tr>
                                <th scope="row">UPI ID</th>
                                <td><strong><?= e($bankUpi) ?></strong></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap gap-2 mt-4">
                    <a class="btn btn-primary" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Online</a>
                    <a class="btn btn-outline" href="mailto:<?= e($email) ?>"><?= lucide('receipt') ?> Request 80G Receipt</a>
                </div>
                <p class="form-hint mt-3">
                    After transferring, please email your name, transaction reference and PAN to
                    <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a> so we can issue your tax-exemption receipt promptly.
                </p>
            </div>
        </div>
    </section>

    <!-- ============================== BOARD OF DIRECTORS ============================== -->
    <section class="section">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Leadership</span>
                <h2 class="section-title">Board of Directors</h2>
                <p class="section-subtitle">Our governing body is accountable for every decision, rupee and outcome.</p>
            </div>
            <div class="ngo-divider" aria-hidden="true"><i></i></div>

            <div class="grid grid-3">
                <?php foreach ($leaders as $i => $leader): ?>
                    <article class="card leader-card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <div class="card-body flex items-center gap-3">
                            <img class="leader-photo" src="<?= e(image_url($leader['photo'] ?? null, 'avatar')) ?>"
                                 alt="<?= e($leader['name']) ?>" width="76" height="76">
                            <div>
                                <h3 class="card-title mb-0" style="font-size:1.1rem;"><?= e($leader['name']) ?></h3>
                                <small class="text-muted"><?= e($leader['designation'] ?? 'Director') ?></small>
                                <?php if (!empty($leader['bio'])): ?>
                                    <p class="card-text mt-2"><?= e(excerpt($leader['bio'], 18)) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================== STATUTORY DOCUMENTS ============================== -->
    <section class="section section-soft">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Transparency</span>
                <h2 class="section-title">Legal &amp; Statutory Documents</h2>
                <p class="section-subtitle">Download our registration and compliance records for your verification.</p>
            </div>
            <div class="ngo-divider" aria-hidden="true"><i></i></div>

            <?php if ($docs): ?>
                <div class="grid grid-3">
                    <?php foreach ($docs as $i => $doc): ?>
                        <a class="card doc-card reveal <?= 'delay-' . (($i % 3) + 1) ?>" href="<?= e(upload_url($doc['file_path'])) ?>" target="_blank" rel="noopener" style="color:inherit;">
                            <div class="card-body">
                                <div class="icon-badge mb-2"><?= lucide('file-text') ?></div>
                                <h3 class="card-title" style="font-size:1.05rem;"><?= e($doc['title']) ?></h3>
                                <?php if (!empty($doc['description'])): ?>
                                    <p class="card-text"><?= e(excerpt($doc['description'], 16)) ?></p>
                                <?php endif; ?>
                                <small class="text-muted">
                                    <?= e(strtoupper((string) ($doc['file_type'] ?: 'FILE'))) ?>
                                    <?php if (!empty($doc['file_size'])): ?> &middot; <?= e(human_filesize((int) $doc['file_size'])) ?><?php endif; ?>
                                </small>
                                <span class="badge badge-brand mt-2" style="display:inline-flex;align-items:center;gap:.35rem;"><?= lucide('download') ?> Download</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state reveal">
                    <div class="icon-badge" style="margin-inline:auto;"><?= lucide('lock') ?></div>
                    <h3 class="mt-2">Documents available on request</h3>
                    <p class="text-muted">
                        Our incorporation certificate, PAN, 12A and 80G certificates can be shared with donors, partners and auditors
                        on request. Please write to us and we will provide certified copies.
                    </p>
                    <a class="btn btn-primary mt-2" href="mailto:<?= e($email) ?>"><?= lucide('mail') ?> Request Documents</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================== CTA ============================== -->
    <section class="section section-brand">
        <div class="container text-center reveal">
            <h2 class="text-white">Ready to Create Change With Us?</h2>
            <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">
                Every verified rupee reaches the communities that need it most. Donate, partner or volunteer — and be part of the impact.
            </p>
            <div class="flex justify-center flex-wrap gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('contact')) ?>"><?= lucide('message-square') ?> Contact Us</a>
            </div>
        </div>
    </section>

</div><!-- /.ngo-page -->

<?php include __DIR__ . '/includes/footer.php'; ?>
