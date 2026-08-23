<?php
/**
 * =============================================================================
 *  Kanya Daan Project — application form.
 * -----------------------------------------------------------------------------
 *  The public "Apply for Kanya Daan Support" page. One long form, every field on
 *  screen at once, posting multipart to forms/kanyadaan-apply — the same shape
 *  as the coordinator application, because the same families fill both in on a
 *  phone and a step wizard hides progress from them.
 *
 *  Section letters and numbers follow the printed application form so a family
 *  holding the paper version can work down both together.
 *
 *  Presentation reuses the .cap-* kit (career-apply.css) and the widgets added
 *  for the coordinator form (coordinator-apply.css) — both are already loaded
 *  patterns on this site, and the behaviour script is shared too: the markup
 *  contract is identical, so assets/js/coordinator-apply.js drives this page.
 *
 *  The one thing this page must never soften is the eligibility statement. The
 *  policy text and the legal-age confirmation are not decoration; see
 *  kd_policy_statement() and the statutory-age gate in the handler.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$docs = kd_documents();

/* The scheme record supplies the headline copy, so editing the scheme in the
   admin panel keeps this page in step. Falls back to the brochure wording if
   the scheme has been renamed or removed. */
$scheme = db_row("SELECT * FROM schemes WHERE slug = 'kanya-daan-project' LIMIT 1");

$assurances = [
    ['icon' => 'shield-check',  'title' => 'Verified, not judged',  'text' => 'Every application is checked with care and discretion. Your documents are seen only by the review team.'],
    ['icon' => 'scale',         'title' => 'Lawful support only',   'text' => 'We assist only where the marriage is legally permissible. This is not a dowry scheme.'],
    ['icon' => 'hand-heart',    'title' => 'Dignity first',         'text' => 'Support is given as household goods and essentials — practical help for a stronger beginning.'],
    ['icon' => 'file-check',    'title' => 'A clear record',        'text' => 'You receive an application number, and every step from verification to distribution is recorded.'],
];

$journey = [
    ['icon' => 'inbox',        'title' => 'Application received',  'text' => 'You get an application number immediately.'],
    ['icon' => 'file-search',  'title' => 'Document check',        'text' => 'Our team reviews the documents you attached.'],
    ['icon' => 'map-pinned',   'title' => 'Field verification',    'text' => 'A coordinator may visit to confirm the family situation.'],
    ['icon' => 'users',        'title' => 'Committee approval',    'text' => 'The Project Committee approves eligible cases.'],
    ['icon' => 'package-check','title' => 'Support distributed',   'text' => 'Assistance is handed over and acknowledged.'],
];

/* ---- SEO ---------------------------------------------------------------- */
seo_set([
    'title'       => 'Apply for Kanya Daan Support',
    'description' => 'Apply for dignified marriage-related support for a daughter from an economically weaker family. '
                   . 'Kanya Daan Project by ' . SITE_NAME . ' — assistance subject to eligibility, verification and available resources.',
    'page_key'    => 'kanyadaan-apply',
    'type'        => 'website',
]);

/* ---- Hero banner -------------------------------------------------------- */
$page_hero = [
    'title'      => 'Apply for Kanya Daan Support',
    'subtitle'   => 'बेटी का सम्मान • परिवार का सहयोग • समाज की जिम्मेदारी',
    'breadcrumb' => [
        ['label' => 'Schemes', 'url' => url('schemes')],
        ['label' => 'Kanya Daan Project', 'url' => url('schemes?slug=kanya-daan-project')],
        ['label' => 'Apply'],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= e(asset('css/career-apply.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/coordinator-apply.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/kanyadaan-apply.css')) ?>">
<script src="<?= e(asset('js/coordinator-apply.js')) ?>" defer></script>

<section class="cap coa kd" id="apply" data-coa>
    <div class="cap-bg" aria-hidden="true">
        <span class="cap-blob b1"></span><span class="cap-blob b2"></span><span class="cap-blob b3"></span>
        <span class="cap-mesh"></span>
    </div>

    <div class="container">

        <!-- ===================================================== INTRO -->
        <div class="coa-intro">
            <span class="cap-badge cap-rv"><?= lucide('heart-handshake') ?> Kanya Daan Project</span>
            <h2 class="cap-h1 cap-rv">Support for <span class="hl">a daughter's marriage</span></h2>
            <p class="cap-lead cap-rv">
                <?= e($scheme['short_description'] ?? 'Dignified support for economically weaker and needy families during the marriage of their eligible daughters — household essentials, marriage-related assistance and livelihood support.') ?>
            </p>
        </div>

        <div class="cap-why coa-why-4">
            <?php foreach ($assurances as $a): ?>
                <article class="cap-why-card cap-rv">
                    <span class="cap-why-ico"><?= lucide($a['icon']) ?></span>
                    <div>
                        <h3><?= e($a['title']) ?></h3>
                        <p><?= e($a['text']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php /* The policy statement leads, before a single field. Anyone who
                 should not be applying deserves to know that here, not after
                 filling in forty fields. */ ?>
        <div class="kd-policy cap-rv">
            <?= lucide('shield-alert') ?>
            <p><?= e(kd_policy_statement()) ?></p>
        </div>

        <!-- ===================================================== THE FORM -->
        <div class="coa-formwrap" id="kd-form" data-coa-card>
            <div class="cap-card">

                <div class="cap-done" data-coa-done>
                    <div class="cap-done-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                             stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <h3>Application submitted successfully</h3>
                    <p class="text-muted">Your application has been received and assigned the reference below.</p>
                    <span class="coa-ref" data-coa-ref></span>
                    <p class="text-muted" style="margin-top:1rem;">
                        Our team will verify the information provided and contact you if additional documentation or
                        field verification is required. Please keep this number for any correspondence.
                    </p>
                </div>

                <div class="cap-card-head" data-coa-formhead>
                    <h2>Application Form</h2>
                    <p>
                        Fill in every section that applies and submit once at the bottom. Fields marked * are required.
                        Your answers are saved in this browser as you type, so you can come back to an unfinished form.
                    </p>
                </div>

                <form data-ajax-form data-coa-form data-endpoint="<?= e(url('forms/kanyadaan-apply')) ?>"
                      enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>
                    <input type="text" name="website_hp" value="" tabindex="-1" autocomplete="off"
                           aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden">

                    <!-- ============================== A. APPLICANT DETAILS -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">A</span>
                            <div>
                                <h3>Applicant details</h3>
                                <p>Who is making this application? The bride herself, or a parent or guardian on her behalf.</p>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-app-name" name="applicant_name" type="text" maxlength="128" placeholder=" " required autocomplete="name">
                                <label for="kd-app-name">Applicant Name <span class="lbl-hi">आवेदक का नाम</span> *</label>
                                <span class="cap-f-ico"><?= lucide('user') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <select id="kd-rel" name="relationship" required>
                                    <option value="">Select…</option>
                                    <?php foreach (kd_relationships() as $key => $label): ?>
                                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="kd-rel">Relationship with Bride <span class="lbl-hi">वधू से संबंध</span> *</label>
                                <span class="cap-f-ico"><?= lucide('users') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-rel-other" name="relationship_other" type="text" maxlength="96" placeholder=" ">
                                <label for="kd-rel-other">If other, please specify <span class="lbl-hi">अन्य होने पर स्पष्ट करें</span></label>
                                <span class="cap-f-ico"><?= lucide('pencil') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-email" name="email" type="email" maxlength="191" placeholder=" " autocomplete="email">
                                <label for="kd-email">Email (optional) <span class="lbl-hi">ईमेल (वैकल्पिक)</span></label>
                                <span class="cap-f-ico"><?= lucide('mail') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f-country">
                                <?= country_field(['name' => 'phone', 'label' => 'Mobile Number', 'required' => true]) ?>
                            </div>
                            <div class="cap-f-country">
                                <?= country_field(['name' => 'whatsapp', 'label' => 'WhatsApp Number', 'hint' => 'Leave blank if the same as above.']) ?>
                            </div>
                        </div>

                        <div class="coa-sub"><?= lucide('map-pinned') ?> Where the family lives</div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-state" name="state" type="text" maxlength="96" placeholder=" ">
                                <label for="kd-state">State <span class="lbl-hi">राज्य</span></label>
                                <span class="cap-f-ico"><?= lucide('flag') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-district" name="district" type="text" maxlength="96" placeholder=" ">
                                <label for="kd-district">District <span class="lbl-hi">जिला</span></label>
                                <span class="cap-f-ico"><?= lucide('map') ?></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-block" name="block" type="text" maxlength="96" placeholder=" ">
                                <label for="kd-block">Block <span class="lbl-hi">प्रखंड</span></label>
                                <span class="cap-f-ico"><?= lucide('layers') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-panchayat" name="panchayat" type="text" maxlength="96" placeholder=" ">
                                <label for="kd-panchayat">Panchayat <span class="lbl-hi">पंचायत</span></label>
                                <span class="cap-f-ico"><?= lucide('landmark') ?></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-village" name="village" type="text" maxlength="128" placeholder=" ">
                                <label for="kd-village">Village / Ward <span class="lbl-hi">गाँव / वार्ड</span></label>
                                <span class="cap-f-ico"><?= lucide('home') ?></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ================================== B. BRIDE DETAILS -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">B</span>
                            <div>
                                <h3>Bride's details</h3>
                                <p>Her age must be confirmed from a document — assistance can only be given where the marriage is lawful.</p>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-bride" name="bride_name" type="text" maxlength="128" placeholder=" " required>
                                <label for="kd-bride">Full Name <span class="lbl-hi">पूरा नाम</span> *</label>
                                <span class="cap-f-ico"><?= lucide('user') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f is-date">
                                <input id="kd-bride-dob" name="bride_dob" type="date" placeholder=" "
                                       max="<?= e(date('Y-m-d', strtotime('-' . kd_min_age('bride') . ' years'))) ?>" required>
                                <label for="kd-bride-dob">Date of Birth <span class="lbl-hi">जन्म तिथि</span> *</label>
                                <span class="cap-f-ico"><?= lucide('cake') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-bride-age" name="bride_age" type="number" min="<?= (int) kd_min_age('bride') ?>" max="75" placeholder=" ">
                                <label for="kd-bride-age">Age (years) <span class="lbl-hi">आयु (वर्ष)</span></label>
                                <span class="cap-f-ico"><?= lucide('hash') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-bride-edu" name="bride_education" type="text" maxlength="128" placeholder=" ">
                                <label for="kd-bride-edu">Education <span class="lbl-hi">शिक्षा</span></label>
                                <span class="cap-f-ico"><?= lucide('graduation-cap') ?></span>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-bride-occ" name="bride_occupation" type="text" maxlength="128" placeholder=" ">
                                <label for="kd-bride-occ">Occupation <span class="lbl-hi">व्यवसाय</span></label>
                                <span class="cap-f-ico"><?= lucide('briefcase') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-marital" name="marital_status" type="text" maxlength="48" placeholder=" ">
                                <label for="kd-marital">Marital Status <span class="lbl-hi">वैवाहिक स्थिति</span></label>
                                <span class="cap-f-ico"><?= lucide('heart') ?></span>
                            </div>
                        </div>

                        <div class="coa-sub"><?= lucide('lock') ?> Identity &amp; bank — stored encrypted</div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-bride-id" name="bride_id_no" type="text" maxlength="32" placeholder=" " inputmode="numeric">
                                <label for="kd-bride-id">Aadhaar / ID Number <span class="lbl-hi">आधार / पहचान संख्या</span></label>
                                <span class="cap-f-ico"><?= lucide('id-card') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-bank" name="bank_account" type="text" maxlength="32" placeholder=" " inputmode="numeric">
                                <label for="kd-bank">Bank Account Number <span class="lbl-hi">बैंक खाता संख्या</span></label>
                                <span class="cap-f-ico"><?= lucide('landmark') ?></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-bank-name" name="bank_name" type="text" maxlength="128" placeholder=" ">
                                <label for="kd-bank-name">Bank &amp; Branch <span class="lbl-hi">बैंक और शाखा</span></label>
                                <span class="cap-f-ico"><?= lucide('building-2') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-ifsc" name="bank_ifsc" type="text" maxlength="16" placeholder=" " style="text-transform:uppercase;">
                                <label for="kd-ifsc">IFSC Code <span class="lbl-hi">आईएफएससी कोड</span></label>
                                <span class="cap-f-ico"><?= lucide('hash') ?></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ================================== C. GROOM DETAILS -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">C</span>
                            <div>
                                <h3>Groom's details</h3>
                                <p>The groom must be at least <?= (int) kd_min_age('groom') ?> years old for the marriage to be lawful.</p>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-groom" name="groom_name" type="text" maxlength="128" placeholder=" ">
                                <label for="kd-groom">Name <span class="lbl-hi">नाम</span></label>
                                <span class="cap-f-ico"><?= lucide('user') ?></span>
                            </div>
                            <div class="cap-f is-date">
                                <input id="kd-groom-dob" name="groom_dob" type="date" placeholder=" "
                                       max="<?= e(date('Y-m-d', strtotime('-' . kd_min_age('groom') . ' years'))) ?>">
                                <label for="kd-groom-dob">Date of Birth <span class="lbl-hi">जन्म तिथि</span></label>
                                <span class="cap-f-ico"><?= lucide('cake') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-groom-age" name="groom_age" type="number" min="<?= (int) kd_min_age('groom') ?>" max="90" placeholder=" ">
                                <label for="kd-groom-age">Age (years) <span class="lbl-hi">आयु (वर्ष)</span></label>
                                <span class="cap-f-ico"><?= lucide('hash') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-groom-occ" name="groom_occupation" type="text" maxlength="128" placeholder=" ">
                                <label for="kd-groom-occ">Occupation <span class="lbl-hi">व्यवसाय</span></label>
                                <span class="cap-f-ico"><?= lucide('briefcase') ?></span>
                            </div>
                        </div>
                        <div class="cap-row one">
                            <div class="cap-f no-ico">
                                <textarea id="kd-groom-addr" name="groom_address" maxlength="500" placeholder=" " data-coa-count></textarea>
                                <label for="kd-groom-addr">Address <span class="lbl-hi">पता</span></label>
                                <span class="cap-count"></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- =============================== D. MARRIAGE DETAILS -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">D</span>
                            <div>
                                <h3>Marriage details</h3>
                                <p>Where and when the marriage is planned.</p>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f is-date">
                                <input id="kd-mdate" name="marriage_date" type="date" placeholder=" ">
                                <label for="kd-mdate">Proposed Marriage Date <span class="lbl-hi">प्रस्तावित विवाह तिथि</span></label>
                                <span class="cap-f-ico"><?= lucide('calendar-heart') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-mloc" name="marriage_location" type="text" maxlength="191" placeholder=" ">
                                <label for="kd-mloc">Marriage Location <span class="lbl-hi">विवाह स्थल</span></label>
                                <span class="cap-f-ico"><?= lucide('map-pin') ?></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-mtype" name="marriage_type" type="text" maxlength="128" placeholder=" ">
                                <label for="kd-mtype">Marriage Type / Arrangement <span class="lbl-hi">विवाह का प्रकार</span></label>
                                <span class="cap-f-ico"><?= lucide('users-round') ?></span>
                            </div>
                        </div>

                        <div class="kd-legal" data-coa-group="legally_permissible"
                             data-coa-group-msg="We can only consider applications where the marriage is legally permissible.">
                            <label class="coa-consent">
                                <input type="checkbox" name="legally_permissible" value="1" required>
                                <span>
                                    I confirm this marriage is <strong>legally permissible</strong> — the bride is at least
                                    <?= (int) kd_min_age('bride') ?> and the groom at least <?= (int) kd_min_age('groom') ?>
                                    years of age, as required by the Prohibition of Child Marriage Act, 2006.
                                </span>
                            </label>
                            <span class="cap-err" data-coa-group-err></span>
                        </div>
                    </fieldset>

                    <!-- ================================= 10. FAMILY DETAILS -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">10</span>
                            <div>
                                <h3>Family details</h3>
                                <p>List the members of the household. Leave rows blank if they do not apply.</p>
                            </div>
                        </div>

                        <div class="coa-grid kd-fam">
                            <div class="coa-grid-head">
                                <?php foreach (kd_family_fields() as $meta): ?>
                                    <span><?= e($meta['label']) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php for ($r = 0; $r < 4; $r++): ?>
                                <div class="coa-grid-row">
                                    <?php foreach (kd_family_fields() as $key => $meta): ?>
                                        <input type="text" name="fam_<?= e($key) ?>[]" maxlength="<?= (int) $meta['max'] ?>"
                                               placeholder="<?= e($meta['label']) ?>"
                                               aria-label="Row <?= $r + 1 ?> <?= e($meta['label']) ?>"
                                               <?= in_array($key, ['age', 'income'], true) ? 'inputmode="numeric"' : '' ?>>
                                    <?php endforeach; ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="cap-row" style="margin-top:1rem;">
                            <div class="cap-f">
                                <input id="kd-minc" name="monthly_income" type="number" min="0" step="100" placeholder=" ">
                                <label for="kd-minc">Total Monthly Family Income (₹) <span class="lbl-hi">कुल मासिक पारिवारिक आय (₹)</span></label>
                                <span class="cap-f-ico"><?= lucide('indian-rupee') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-ainc" name="annual_income" type="number" min="0" step="1000" placeholder=" ">
                                <label for="kd-ainc">Annual Family Income (₹) <span class="lbl-hi">वार्षिक पारिवारिक आय (₹)</span></label>
                                <span class="cap-f-ico"><?= lucide('indian-rupee') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="kd-fsize" name="family_size" type="number" min="1" max="60" placeholder=" ">
                                <label for="kd-fsize">Number of Family Members <span class="lbl-hi">परिवार के सदस्यों की संख्या</span></label>
                                <span class="cap-f-ico"><?= lucide('users') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <input id="kd-earners" name="earning_members" type="number" min="0" max="60" placeholder=" ">
                                <label for="kd-earners">Earning Members <span class="lbl-hi">कमाने वाले सदस्य</span></label>
                                <span class="cap-f-ico"><?= lucide('briefcase') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>

                        <p class="coa-note-line">House type:</p>
                        <div class="coa-chips">
                            <?php foreach (kd_house_types() as $key => $label): ?>
                                <?php $hid = 'kd-house-' . $key; ?>
                                <input type="radio" id="<?= e($hid) ?>" name="house_type" value="<?= e($key) ?>">
                                <label for="<?= e($hid) ?>">
                                    <span class="coa-chips-tick"><?= lucide('check') ?></span><?= e($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <!-- ============================= 11. ECONOMIC CONDITION -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">11</span>
                            <div>
                                <h3>Economic condition</h3>
                                <p>Tell us plainly why help is needed. This is what the committee reads most closely.</p>
                            </div>
                        </div>

                        <div class="coa-yn-stack">
                            <div class="coa-yn">
                                <span class="coa-yn-q"><?= lucide('trending-down') ?> Does the family currently face financial hardship?</span>
                                <span class="coa-yn-opts">
                                    <input type="radio" id="kd-hard-y" name="financial_hardship" value="1" checked>
                                    <label for="kd-hard-y">Yes <span class="lbl-hi">हाँ</span></label>
                                    <input type="radio" id="kd-hard-n" name="financial_hardship" value="0">
                                    <label for="kd-hard-n">No <span class="lbl-hi">नहीं</span></label>
                                </span>
                            </div>
                            <div class="coa-yn">
                                <span class="coa-yn-q"><?= lucide('landmark') ?> Receiving any government / social welfare assistance?</span>
                                <span class="coa-yn-opts">
                                    <input type="radio" id="kd-govt-y" name="govt_assistance" value="1" data-coa-toggle="govt">
                                    <label for="kd-govt-y">Yes <span class="lbl-hi">हाँ</span></label>
                                    <input type="radio" id="kd-govt-n" name="govt_assistance" value="0" checked>
                                    <label for="kd-govt-n">No <span class="lbl-hi">नहीं</span></label>
                                </span>
                            </div>
                        </div>

                        <div class="coa-if" data-coa-if="govt">
                            <div class="cap-f no-ico">
                                <input id="kd-govt-det" name="govt_assistance_details" type="text" maxlength="500" placeholder=" ">
                                <label for="kd-govt-det">Which schemes, and what is received? <span class="lbl-hi">कौन सी योजनाएँ, और क्या प्राप्त होता है?</span></label>
                            </div>
                        </div>

                        <div class="cap-row one" style="margin-top:.9rem;">
                            <div class="cap-f no-ico">
                                <textarea id="kd-reason" name="hardship_reason" maxlength="2000" placeholder=" " data-coa-count></textarea>
                                <label for="kd-reason">Reason for requesting assistance <span class="lbl-hi">सहायता माँगने का कारण</span></label>
                                <span class="cap-count"></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- =============================== 12. SUPPORT REQUESTED -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">12</span>
                            <div>
                                <h3>Support requested</h3>
                                <p>Tick everything the family needs. What is finally provided depends on verification and available funds.</p>
                            </div>
                        </div>

                        <div class="coa-chips">
                            <?php foreach (kd_support_items() as $i => $item): ?>
                                <?php $sid = 'kd-sup-' . $i; ?>
                                <input type="checkbox" id="<?= e($sid) ?>" name="support_items[]" value="<?= e($item) ?>">
                                <label for="<?= e($sid) ?>">
                                    <span class="coa-chips-tick"><?= lucide('check') ?></span><?= e($item) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="cap-row" style="margin-top:.9rem;">
                            <div class="cap-f no-ico">
                                <input id="kd-sup-other" name="support_other" type="text" maxlength="96" placeholder=" ">
                                <label for="kd-sup-other">Other support needed <span class="lbl-hi">अन्य आवश्यक सहायता</span></label>
                            </div>
                        </div>
                        <div class="cap-row one">
                            <div class="cap-f no-ico">
                                <textarea id="kd-just" name="support_justification" maxlength="2000" placeholder=" " data-coa-count></textarea>
                                <label for="kd-just">Brief justification <span class="lbl-hi">संक्षिप्त औचित्य</span></label>
                                <span class="cap-count"></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- =============================== 13. DOCUMENT CHECKLIST -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">13</span>
                            <div>
                                <h3>Document checklist</h3>
                                <p>
                                    Clear photos or scans, up to 5&nbsp;MB each. Only the bride's identity and age proof are
                                    required now — the rest can be brought at verification.
                                </p>
                            </div>
                        </div>

                        <div class="coa-docs">
                            <?php foreach ($docs as $slot => $doc): ?>
                                <?php
                                $accept = '.' . str_replace(',', ',.', kd_doc_allowed((bool) $doc['image']));
                                $hint   = $doc['image'] ? 'JPG, PNG or WEBP' : 'PDF, image or DOC';
                                if (!empty($doc['note'])) {
                                    $hint .= ' · ' . $doc['note'];
                                }
                                ?>
                                <div class="coa-doc" data-coa-doc data-coa-doc-slot="<?= e($slot) ?>"
                                     <?= !empty($doc['required']) ? 'data-coa-doc-required' : '' ?>>
                                    <span class="coa-doc-ico"><?= lucide($doc['icon']) ?></span>
                                    <span class="coa-doc-meta">
                                        <b><?= e($doc['label']) ?>
                                            <?php if (!empty($doc['required'])): ?><span class="coa-doc-req">Required</span><?php endif; ?>
                                        </b>
                                        <small data-coa-doc-name><?= e($hint) ?></small>
                                        <small class="coa-doc-err" data-coa-doc-err></small>
                                    </span>
                                    <label class="coa-doc-btn">
                                        <?= lucide('upload') ?> Attach
                                        <input type="file" name="<?= e($slot) ?>" accept="<?= e($accept) ?>"
                                               aria-label="Attach <?= e($doc['label']) ?>">
                                    </label>
                                    <button type="button" class="coa-doc-x" data-coa-doc-remove
                                            aria-label="Remove <?= e($doc['label']) ?>"><?= lucide('x') ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <!-- =================================== 14. DECLARATION -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">14</span>
                            <div>
                                <h3>Declaration by applicant</h3>
                                <p>Please read both statements before you submit.</p>
                            </div>
                        </div>

                        <div class="coa-declare">
                            I declare that the information provided by me is true and complete to the best of my knowledge.
                            I understand that submission of this application does not guarantee assistance. I agree to
                            verification by <?= e(SITE_NAME) ?> and understand that assistance will be provided only after
                            eligibility and need are assessed.
                        </div>

                        <div data-coa-group="consent" data-coa-group-msg="Please accept the declaration before submitting.">
                            <label class="coa-consent">
                                <input type="checkbox" name="consent" value="1" required>
                                <span>I accept the declaration above and confirm the details I have entered are correct.</span>
                            </label>
                            <span class="cap-err" data-coa-group-err></span>
                        </div>

                        <div data-coa-group="dowry_declaration" style="margin-top:.85rem;"
                             data-coa-group-msg="This confirmation is required — the project cannot support a dowry payment.">
                            <label class="coa-consent">
                                <input type="checkbox" name="dowry_declaration" value="1" required>
                                <span>
                                    I further declare that the assistance requested is for <strong>legitimate social-welfare
                                    purposes</strong> and is <strong>not</strong> being sought for the payment or fulfilment
                                    of any dowry demand.
                                </span>
                            </label>
                            <span class="cap-err" data-coa-group-err></span>
                        </div>

                        <div class="cap-row" style="margin-top:1.1rem;">
                            <div class="cap-f">
                                <input id="kd-place" name="declared_place" type="text" maxlength="128" placeholder=" ">
                                <label for="kd-place">Place <span class="lbl-hi">स्थान</span></label>
                                <span class="cap-f-ico"><?= lucide('map-pin') ?></span>
                            </div>
                            <div class="cap-f is-date">
                                <input id="kd-date" name="declared_on" type="date" placeholder=" " value="<?= e(date('Y-m-d')) ?>">
                                <label for="kd-date">Date <span class="lbl-hi">दिनांक</span></label>
                                <span class="cap-f-ico"><?= lucide('calendar') ?></span>
                            </div>
                        </div>
                        <p class="coa-note-line">
                            Submitting this form electronically stands in place of a signature or thumb impression.
                        </p>
                    </fieldset>

                    <div class="cap-actions">
                        <button type="submit" class="cap-btn grow" data-coa-submit><?= lucide('send') ?> Submit Application</button>
                    </div>

                    <p class="cap-note">
                        <?= lucide('shield-check') ?>
                        Identity and bank numbers are encrypted before they are stored, and your documents are visible only
                        to the review team — never published, never shared.
                    </p>
                </form>
            </div>
        </div>

        <!-- ============================================ WHAT HAPPENS NEXT -->
        <div class="coa-next">
            <h3 class="coa-next-title cap-rv">What happens after you apply</h3>
            <div class="coa-next-grid">
                <?php $ji = 0; foreach ($journey as $j): ?>
                    <div class="coa-next-step cap-rv" style="transition-delay:<?= (int) ($ji++ * 60) ?>ms">
                        <span class="coa-next-ico"><?= lucide($j['icon']) ?></span>
                        <strong><?= e($j['title']) ?></strong>
                        <span><?= e($j['text']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="coa-disclaimer">
                <?= lucide('info') ?>
                Assistance is subject to eligibility, verification, available resources and project approval.
                <a href="<?= e(url('schemes?slug=kanya-daan-project')) ?>">Read the full project details</a>.
            </p>
        </div>
    </div>

    <div class="cap-sticky" data-coa-sticky>
        <a class="cap-btn" href="#kd-form"><?= lucide('send') ?> Go to the form</a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
