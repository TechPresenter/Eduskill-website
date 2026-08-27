<?php
/**
 * =============================================================================
 *  Community Coordinator — recruitment page + application form.
 * -----------------------------------------------------------------------------
 *  Applications for the three field roles the foundation recruits for:
 *  Panchayat, Block and District Coordinator.
 *
 *  The page opens with the roles and what each is responsible for (section 7 of
 *  the printed form, which is information rather than input), then carries the
 *  complete application as ONE form — every section on screen at once, ending
 *  in a single Submit. It posts multipart to forms/coordinator-apply.
 *
 *  Section numbers below follow the printed form exactly, so an applicant
 *  holding the paper version can work down both together. Section 7 is the
 *  role cards above the form; the application number in the printed header is
 *  issued by the handler on submit rather than asked for.
 *
 *  ?position=panchayat|block|district deep-links a pre-selected role, so the
 *  role cards (and any campaign link) can drop the applicant straight into the
 *  right variant of section 2.
 *
 *  Presentation reuses the premium .cap-* kit from assets/css/career-apply.css
 *  — same glass card, floating labels and buttons as the careers page — with
 *  assets/css/coordinator-apply.css repainting it in the brand palette and
 *  adding the widgets this longer form needs. Every option list comes from
 *  includes/coordinator.php, which the handler validates against.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$positions = coord_positions();   // the three roles, with duties
$eduLevels = coord_education_levels();
$docs      = coord_documents();

/* Deep link: /coordinator-apply?position=block preselects that role. */
$preset = clean(get('position', ''));
if (!isset($positions[$preset])) {
    $preset = '';
}

/* ---- Accent colour per role -------------------------------------------
   Presentation only, so it lives here rather than in includes/coordinator.php.
   [start, end] of the gradient used for the card's top bar, icon and link.
   All three sit in the page's tomato scale so the row reads as one family,
   but they step light -> mid -> deep with the seniority of the role, which is
   what keeps them distinguishable at a glance. */
$roleAccent = [
    'panchayat' => ['#FF8A5B', '#E8551F'],   // coral
    'block'     => ['#F0402A', '#B81E0D'],   // tomato
    'district'  => ['#C41E12', '#7E1207'],   // deep red
];

/* ---- What the foundation asks of every coordinator --------------------- */
$expectations = [
    ['icon' => 'map-pin',        'title' => 'Local roots',         'text' => 'You live in — or work closely with — the area you would coordinate, and people there know you.'],
    ['icon' => 'users',          'title' => 'Community trust',     'text' => 'Comfortable sitting with families, panchayat members, schools and self-help groups alike.'],
    ['icon' => 'clipboard-pen',  'title' => 'Honest records',      'text' => 'Beneficiary lists, attendance and activity reports kept accurately and submitted on time.'],
    ['icon' => 'smartphone',     'title' => 'Basic digital skill', 'text' => 'A smartphone, WhatsApp and simple data entry are enough to start; we train you on the rest.'],
];

/* ---- What happens after the form is submitted -------------------------- */
$journey = [
    ['icon' => 'inbox',           'title' => 'Application received',     'text' => 'You get a reference number by email the moment you submit.'],
    ['icon' => 'file-check',      'title' => 'Document verification',    'text' => 'Our team checks your identity, address and qualification documents.'],
    ['icon' => 'map-pinned',      'title' => 'Field / background check', 'text' => 'A short verification in the area you have applied to coordinate.'],
    ['icon' => 'messages-square', 'title' => 'Interview',                'text' => 'A conversation about your area, your availability and the role.'],
    ['icon' => 'badge-check',     'title' => 'Appointment',              'text' => 'Approved candidates receive an assigned area, joining date and honorarium.'],
];

/* ---- SEO ---------------------------------------------------------------- */
seo_set([
    'title'       => 'Community Coordinator Application',
    'description' => 'Apply to become a Panchayat, Block or District Coordinator with ' . SITE_NAME
                   . '. Lead education, skilling and welfare programmes in your own area — apply online with your documents.',
    'page_key'    => 'coordinator-apply',
    'type'        => 'website',
]);

/* ---- Hero banner -------------------------------------------------------- */
$page_hero = [
    'title'      => 'Community Coordinator Positions',
    'subtitle'   => 'Empowering communities • Spreading hope • Creating change. Applications are invited for Panchayat, Block and District Coordinators.',
    'breadcrumb' => [
        ['label' => 'Support Our Work'],
        ['label' => 'Coordinator Application'],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= e(asset('css/career-apply.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/coordinator-apply.css')) ?>">
<script src="<?= e(asset('js/coordinator-apply.js')) ?>" defer></script>

<section class="cap coa" id="apply" data-coa>
    <div class="cap-bg" aria-hidden="true">
        <span class="cap-blob b1"></span><span class="cap-blob b2"></span><span class="cap-blob b3"></span>
        <span class="cap-mesh"></span>
    </div>

    <div class="container">

        <!-- ================================================== INTRO + ROLES -->
        <div class="coa-intro">
            <span class="cap-badge cap-rv"><?= lucide('sparkles') ?> Now Recruiting</span>
            <h2 class="cap-h1 cap-rv">Lead the change <span class="hl">in your own village</span></h2>
            <p class="cap-lead cap-rv">
                Our programmes reach families through people who already belong to the community — coordinators who know
                the lanes, the schools and the households. Apply for the level you can serve, and we will train, support
                and equip you for the rest.
            </p>
        </div>

        <?php /* Section 7 of the printed form — role-specific responsibilities.
                 It is information, not input, so it sits above the form. */ ?>
        <div class="coa-roles coa-roles-3">
            <?php $ri = 0; foreach ($positions as $key => $pos): ?>
                <?php [$accentA, $accentB] = $roleAccent[$key] ?? ['#084881', '#063566']; ?>
                <article class="coa-role cap-rv"
                         style="--role-a:<?= e($accentA) ?>;--role-b:<?= e($accentB) ?>;transition-delay:<?= (int) ($ri++ * 70) ?>ms">
                    <div class="coa-role-head">
                        <span class="coa-role-ico"><?= lucide($pos['icon']) ?></span>
                        <div>
                            <h3><?= e($pos['label']) ?></h3>
                            <span class="coa-role-scope"><?= e($pos['scope']) ?></span>
                        </div>
                    </div>
                    <ul>
                        <?php foreach ($pos['duties'] as $duty): ?>
                            <li><?= e($duty) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="coa-role-link" href="#coa-form"
                       data-coa-pick-link="<?= e($key) ?>"><?= lucide('arrow-right') ?> Apply for this role</a>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="cap-why coa-why-4">
            <?php foreach ($expectations as $x): ?>
                <article class="cap-why-card cap-rv">
                    <span class="cap-why-ico"><?= lucide($x['icon']) ?></span>
                    <div>
                        <h3><?= e($x['title']) ?></h3>
                        <p><?= e($x['text']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- ==================================================== THE FORM -->
        <?php /* No cap-rv here, deliberately. The scroll-reveal starts an element
                 at opacity:0 and waits for an IntersectionObserver to add .is-in —
                 which never happened for this one, because the form is taller than
                 nine viewports and could not meet the observer's threshold. Landing
                 on /coordinator-apply#coa-form rendered a blank white page. The form
                 is the entire point of the page; it does not get to be conditional
                 on a script. Decorative blocks around it still reveal. */ ?>
        <div class="coa-formwrap" id="coa-form" data-coa-card>
            <div class="cap-card">

                <div class="cap-done" data-coa-done>
                    <div class="cap-done-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                             stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <h3>Application received</h3>
                    <p class="text-muted">
                        Thank you. We have emailed your reference number — quote it whenever you contact us about this application.
                    </p>
                    <span class="coa-ref" data-coa-ref></span>
                    <p class="text-muted" style="margin-top:1rem;">
                        Our team verifies documents and completes a short field check before shortlisting. You will hear from us
                        on the number and email you provided.
                    </p>
                </div>

                <div class="cap-card-head" data-coa-formhead>
                    <h2>Application Form</h2>
                    <p>
                        Fill in every section that applies to you and submit once at the bottom. Fields marked * are required;
                        keep your ID, address proof and certificates handy for section&nbsp;9.
                        Your answers are saved in this browser as you type, so you can come back to an unfinished form.
                    </p>
                </div>

                <form data-ajax-form data-coa-form data-endpoint="<?= e(url('forms/coordinator-apply')) ?>"
                      enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>
                    <input type="text" name="pwf_zq" value="" tabindex="-1" autocomplete="off"
                           aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden">

                    <!-- ==================================== 1. APPLICANT DETAILS -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">1</span>
                            <div>
                                <h3>Applicant details</h3>
                                <p>Enter your name exactly as it appears on your identity document.</p>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="coa-name" name="name" type="text" maxlength="128" placeholder=" " required autocomplete="name">
                                <label for="coa-name">Full Name *</label>
                                <span class="cap-f-ico"><?= lucide('user') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <input id="coa-guardian" name="guardian_name" type="text" maxlength="128" placeholder=" ">
                                <label for="coa-guardian">Father's / Mother's / Spouse's Name</label>
                                <span class="cap-f-ico"><?= lucide('users') ?></span>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f is-date">
                                <input id="coa-dob" name="dob" type="date" placeholder=" " max="<?= e(date('Y-m-d', strtotime('-18 years'))) ?>">
                                <label for="coa-dob">Date of Birth</label>
                                <span class="cap-f-ico"><?= lucide('cake') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <select id="coa-gender" name="gender">
                                    <option value="">Prefer not to say</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                                <label for="coa-gender">Gender</label>
                                <span class="cap-f-ico"><?= lucide('user-round') ?></span>
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

                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="coa-email" name="email" type="email" maxlength="191" placeholder=" " required autocomplete="email">
                                <label for="coa-email">Email ID *</label>
                                <span class="cap-f-ico"><?= lucide('mail') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <input id="coa-idno" name="id_proof_no" type="text" maxlength="32" placeholder=" " inputmode="numeric">
                                <label for="coa-idno">Aadhaar / Valid ID No.</label>
                                <span class="cap-f-ico"><?= lucide('id-card') ?></span>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f">
                                <textarea id="coa-addr-cur" name="current_address" maxlength="500" placeholder=" "></textarea>
                                <label for="coa-addr-cur">Current Address</label>
                            </div>
                            <div class="cap-f">
                                <textarea id="coa-addr-per" name="permanent_address" maxlength="500" placeholder=" "></textarea>
                                <label for="coa-addr-per">Permanent Address</label>
                            </div>
                        </div>

                        <div class="coa-sub"><?= lucide('map-pinned') ?> Where you are based</div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="coa-state" name="state" type="text" maxlength="96" placeholder=" ">
                                <label for="coa-state">State</label>
                                <span class="cap-f-ico"><?= lucide('flag') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="coa-district" name="district" type="text" maxlength="96" placeholder=" ">
                                <label for="coa-district">District</label>
                                <span class="cap-f-ico"><?= lucide('map') ?></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="coa-block" name="block" type="text" maxlength="96" placeholder=" ">
                                <label for="coa-block">Block</label>
                                <span class="cap-f-ico"><?= lucide('layers') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="coa-panchayat" name="panchayat" type="text" maxlength="96" placeholder=" ">
                                <label for="coa-panchayat">Panchayat</label>
                                <span class="cap-f-ico"><?= lucide('landmark') ?></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="coa-village" name="village" type="text" maxlength="128" placeholder=" ">
                                <label for="coa-village">Village / Ward</label>
                                <span class="cap-f-ico"><?= lucide('home') ?></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================================== 2. POSITION APPLIED FOR -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">2</span>
                            <div>
                                <h3>Position applied for</h3>
                                <p>Choose the level you want to serve, then tell us the area you would prefer.</p>
                            </div>
                        </div>

                        <div class="coa-pick" data-coa-group="position"
                             data-coa-group-msg="Please choose the position you are applying for.">
                            <?php foreach ($positions as $key => $pos): ?>
                                <?php $rid = 'coa-pos-' . $key; ?>
                                <input type="radio" id="<?= e($rid) ?>" name="position" value="<?= e($key) ?>"
                                       <?= $preset === $key ? 'checked' : '' ?>>
                                <label for="<?= e($rid) ?>">
                                    <span class="coa-pick-dot" aria-hidden="true"></span>
                                    <span>
                                        <b><?= e($pos['label']) ?></b>
                                        <small><?= e($pos['scope']) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            <span class="cap-err" data-coa-group-err></span>
                        </div>

                        <!-- The preferred-area questions differ per level (section 2 A/B/C). -->
                        <div class="coa-area" data-coa-area="panchayat">
                            <div class="coa-sub"><?= lucide('home') ?> A · Panchayat Coordinator</div>
                            <div class="cap-row">
                                <div class="cap-f">
                                    <input id="coa-pref-pan" name="preferred_panchayat" type="text" maxlength="128" placeholder=" ">
                                    <label for="coa-pref-pan">Preferred Panchayat</label>
                                    <span class="cap-f-ico"><?= lucide('map-pin') ?></span>
                                </div>
                                <div class="cap-f">
                                    <input id="coa-cover" name="village_coverage" type="text" maxlength="255" placeholder=" ">
                                    <label for="coa-cover">Village / Ward coverage</label>
                                    <span class="cap-f-ico"><?= lucide('map') ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="coa-area" data-coa-area="block">
                            <div class="coa-sub"><?= lucide('layers') ?> B · Block Coordinator</div>
                            <div class="cap-row">
                                <div class="cap-f">
                                    <input id="coa-pref-blk" name="preferred_block" type="text" maxlength="128" placeholder=" ">
                                    <label for="coa-pref-blk">Preferred Block</label>
                                    <span class="cap-f-ico"><?= lucide('map-pin') ?></span>
                                </div>
                                <div class="cap-f">
                                    <input id="coa-blk-dist" name="block_district" type="text" maxlength="128" placeholder=" ">
                                    <label for="coa-blk-dist">District</label>
                                    <span class="cap-f-ico"><?= lucide('map') ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="coa-area" data-coa-area="district">
                            <div class="coa-sub"><?= lucide('map') ?> C · District Coordinator</div>
                            <div class="cap-row">
                                <div class="cap-f">
                                    <input id="coa-pref-dist" name="preferred_district" type="text" maxlength="128" placeholder=" ">
                                    <label for="coa-pref-dist">Preferred District</label>
                                    <span class="cap-f-ico"><?= lucide('map-pin') ?></span>
                                </div>
                                <div class="cap-f">
                                    <input id="coa-dist-state" name="district_state" type="text" maxlength="128" placeholder=" ">
                                    <label for="coa-dist-state">State</label>
                                    <span class="cap-f-ico"><?= lucide('flag') ?></span>
                                </div>
                            </div>
                        </div>

                    </fieldset>

                    <!-- =============================== 3. EDUCATIONAL QUALIFICATION -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">3</span>
                            <div>
                                <h3>Educational qualification</h3>
                                <p>Fill in the rows that apply to you and leave the rest blank.</p>
                            </div>
                        </div>

                        <div class="coa-grid edu">
                            <div class="coa-grid-head">
                                <span>Qualification</span><span>Board / University</span><span>Year</span><span>Percentage / Grade</span>
                            </div>
                            <?php foreach ($eduLevels as $level): ?>
                                <div class="coa-grid-row">
                                    <span class="coa-grid-lbl"><?= e($level) ?></span>
                                    <input type="text" name="edu_board[]" maxlength="128" placeholder="Board / University"
                                           aria-label="<?= e($level) ?> board or university">
                                    <input type="text" name="edu_year[]" maxlength="16" placeholder="Year"
                                           inputmode="numeric" aria-label="<?= e($level) ?> year">
                                    <input type="text" name="edu_grade[]" maxlength="32" placeholder="% / Grade"
                                           aria-label="<?= e($level) ?> percentage or grade">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="coa-sub"><?= lucide('monitor') ?> Computer knowledge</div>
                        <div class="coa-chips">
                            <?php foreach (coord_computer_skills() as $i => $skill): ?>
                                <?php $cid = 'coa-skill-' . $i; ?>
                                <input type="checkbox" id="<?= e($cid) ?>" name="computer_skills[]" value="<?= e($skill) ?>">
                                <label for="<?= e($cid) ?>">
                                    <span class="coa-chips-tick"><?= lucide('check') ?></span><?= e($skill) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="cap-row" style="margin-top:.8rem;">
                            <div class="cap-f no-ico">
                                <input id="coa-skill-other" name="computer_other" type="text" maxlength="64" placeholder=" ">
                                <label for="coa-skill-other">Other computer skills</label>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ======================================== 4. WORK EXPERIENCE -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">4</span>
                            <div>
                                <h3>Work experience</h3>
                                <p>Field experience matters more to us than years on paper — tell us what you have actually done.</p>
                            </div>
                        </div>

                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="coa-exp-y" name="experience_years" type="number" min="0" max="60" step="1" placeholder=" ">
                                <label for="coa-exp-y">Total Experience — Years</label>
                                <span class="cap-f-ico"><?= lucide('calendar') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <input id="coa-exp-m" name="experience_months" type="number" min="0" max="11" step="1" placeholder=" ">
                                <label for="coa-exp-m">Months</label>
                                <span class="cap-f-ico"><?= lucide('calendar-days') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>

                        <div class="coa-sub"><?= lucide('heart-handshake') ?> NGO / social work</div>
                        <div class="coa-yn">
                            <span class="coa-yn-q">Do you have experience in NGO or social work?</span>
                            <span class="coa-yn-opts">
                                <input type="radio" id="coa-ngo-y" name="ngo_experience" value="1" data-coa-toggle="ngo">
                                <label for="coa-ngo-y">Yes</label>
                                <input type="radio" id="coa-ngo-n" name="ngo_experience" value="0" checked>
                                <label for="coa-ngo-n">No</label>
                            </span>
                        </div>
                        <div class="coa-if" data-coa-if="ngo">
                            <div class="cap-f no-ico">
                                <textarea id="coa-ngo-details" name="ngo_details" maxlength="2000" placeholder=" " data-coa-count></textarea>
                                <label for="coa-ngo-details">Where, and what did you do?</label>
                                <span class="cap-count"></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- =========================== 5. COMMUNITY & FIELD EXPERIENCE -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">5</span>
                            <div>
                                <h3>Community &amp; field experience</h3>
                                <p>Where you have worked on the ground, and with whom.</p>
                            </div>
                        </div>

                        <div class="coa-yn">
                            <span class="coa-yn-q">Have you worked with rural or community groups?</span>
                            <span class="coa-yn-opts">
                                <input type="radio" id="coa-com-y" name="community_experience" value="1">
                                <label for="coa-com-y">Yes</label>
                                <input type="radio" id="coa-com-n" name="community_experience" value="0" checked>
                                <label for="coa-com-n">No</label>
                            </span>
                        </div>

                        <p class="coa-note-line">Select every area you have experience in:</p>
                        <div class="coa-chips">
                            <?php foreach (coord_focus_areas() as $i => $area): ?>
                                <?php $aid = 'coa-area-' . $i; ?>
                                <input type="checkbox" id="<?= e($aid) ?>" name="focus_areas[]" value="<?= e($area) ?>">
                                <label for="<?= e($aid) ?>">
                                    <span class="coa-chips-tick"><?= lucide('check') ?></span><?= e($area) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="cap-row one" style="margin-top:.9rem;">
                            <div class="cap-f no-ico">
                                <textarea id="coa-com-note" name="community_note" maxlength="2000" placeholder=" " data-coa-count></textarea>
                                <label for="coa-com-note">Briefly describe your community work</label>
                                <span class="cap-count"></span>
                            </div>
                        </div>

                        <?php /* Languages lived in section 6 on the printed form. That
                                 section is gone, but the question is not — it belongs
                                 with community work, so it moves here rather than being
                                 dropped. */ ?>
                        <p class="coa-note-line">Languages known:</p>
                        <div class="coa-chips">
                            <?php foreach (coord_languages() as $i => $lang): ?>
                                <?php $lid = "coa-lang-" . $i; ?>
                                <input type="checkbox" id="<?= e($lid) ?>" name="languages[]" value="<?= e($lang) ?>">
                                <label for="<?= e($lid) ?>">
                                    <span class="coa-chips-tick"><?= lucide("check") ?></span><?= e($lang) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="cap-row" style="margin-top:.8rem;">
                            <div class="cap-f no-ico">
                                <input id="coa-lang-other" name="language_other" type="text" maxlength="64" placeholder=" ">
                                <label for="coa-lang-other">Other language</label>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ============================ 8. AVAILABILITY & FIELD MOBILITY -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">8</span>
                            <div>
                                <h3>Availability &amp; field mobility</h3>
                                <p>Coordinator work happens in the field, so how you move around matters.</p>
                            </div>
                        </div>

                        <div class="coa-yn-stack">
                            <?php foreach (coord_availability() as $key => $q): ?>
                                <div class="coa-yn">
                                    <span class="coa-yn-q"><?= lucide($q['icon']) ?> <?= e($q['label']) ?></span>
                                    <span class="coa-yn-opts">
                                        <input type="radio" id="coa-<?= e($key) ?>-y" name="<?= e($key) ?>" value="1">
                                        <label for="coa-<?= e($key) ?>-y">Yes</label>
                                        <input type="radio" id="coa-<?= e($key) ?>-n" name="<?= e($key) ?>" value="0" checked>
                                        <label for="coa-<?= e($key) ?>-n">No</label>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="coa-sub"><?= lucide('clock') ?> Terms you are looking for</div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <select id="coa-mode" name="work_mode">
                                    <option value="">Select a work mode</option>
                                    <?php foreach (coord_work_modes() as $mode): ?>
                                        <option value="<?= e($mode) ?>"><?= e($mode) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="coa-mode">Preferred Work Mode</label>
                                <span class="cap-f-ico"><?= lucide('briefcase') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="coa-expect" name="expected_honorarium" type="number" min="0" step="100" placeholder=" ">
                                <label for="coa-expect">Expected Monthly Honorarium (₹)</label>
                                <span class="cap-f-ico"><?= lucide('indian-rupee') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f is-date">
                                <input id="coa-avail" name="available_from" type="date" placeholder=" " min="<?= e(date('Y-m-d')) ?>">
                                <label for="coa-avail">Earliest Joining Date</label>
                                <span class="cap-f-ico"><?= lucide('calendar-check') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ===================================== 9. DOCUMENT CHECKLIST -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">9</span>
                            <div>
                                <h3>Document checklist</h3>
                                <p>
                                    Clear photos or scans, up to 5&nbsp;MB each. Only the photograph and ID proof are required now —
                                    the rest can follow at verification, but attaching them here speeds your application up.
                                </p>
                            </div>
                        </div>

                        <div class="coa-docs">
                            <?php foreach ($docs as $slot => $doc): ?>
                                <?php
                                $accept = '.' . str_replace(',', ',.', coord_doc_allowed((bool) $doc['image']));
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

                    <!-- ====================================== 10. REFERENCE DETAILS -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">10</span>
                            <div>
                                <h3>Reference details</h3>
                                <p>One person who knows your work — a panchayat member, employer, teacher or group leader.</p>
                            </div>
                        </div>

                        <div class="cap-row">
                            <?php foreach (coord_reference_fields() as $key => $meta): ?>
                                <div class="cap-f">
                                    <input id="coa-<?= e($key) ?>" name="<?= e($key) ?>" type="text"
                                           maxlength="<?= (int) $meta['max'] ?>" placeholder=" "
                                           <?= $key === 'ref_mobile' ? 'inputmode="tel"' : '' ?>>
                                    <label for="coa-<?= e($key) ?>"><?= e($meta['label']) ?></label>
                                    <span class="cap-f-ico"><?= lucide($meta['icon']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <!-- =================================== 11. APPLICANT DECLARATION -->
                    <fieldset class="coa-fs">
                        <div class="coa-fs-head">
                            <span class="coa-fs-no">11</span>
                            <div>
                                <h3>Applicant declaration</h3>
                                <p>Please read this before you submit.</p>
                            </div>
                        </div>

                        <div class="coa-declare">
                            I hereby declare that the information provided in this application is true and complete to the best of my
                            knowledge. I understand that submission of this application does not guarantee selection or appointment.
                            I agree to participate in the Foundation's verification and selection process and understand that any
                            false or misleading information may result in rejection or cancellation of my engagement.
                        </div>

                        <div data-coa-group="consent" data-coa-group-msg="Please accept the declaration before submitting.">
                            <label class="coa-consent">
                                <input type="checkbox" name="consent" value="1" required>
                                <span>I have read and accept the declaration above, and I confirm the details I have entered are correct.</span>
                            </label>
                            <span class="cap-err" data-coa-group-err></span>
                        </div>

                        <div class="cap-row" style="margin-top:1.1rem;">
                            <div class="cap-f">
                                <input id="coa-place" name="declared_place" type="text" maxlength="128" placeholder=" ">
                                <label for="coa-place">Place</label>
                                <span class="cap-f-ico"><?= lucide('map-pin') ?></span>
                            </div>
                            <div class="cap-f is-date">
                                <input id="coa-date" name="declared_on" type="date" placeholder=" " value="<?= e(date('Y-m-d')) ?>">
                                <label for="coa-date">Date</label>
                                <span class="cap-f-ico"><?= lucide('calendar') ?></span>
                            </div>
                        </div>
                        <p class="coa-note-line">
                            Submitting this form electronically stands in place of a signature — the name and details above are
                            your declaration.
                        </p>
                    </fieldset>

                    <div class="cap-actions">
                        <button type="submit" class="cap-btn grow" data-coa-submit><?= lucide('send') ?> Submit Application</button>
                    </div>

                    <p class="cap-note">
                        <?= lucide('shield-check') ?>
                        Your documents are stored privately and are visible only to the review team — never published, never shared.
                    </p>
                </form>
            </div>
        </div>

        <!-- ============================================ WHAT HAPPENS NEXT -->
        <div class="coa-next">
            <h3 class="coa-next-title cap-rv">What happens after you submit</h3>
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
                Selection, assigned area, responsibilities and engagement terms are subject to the Foundation's requirements,
                its verification process and the applicable project guidelines.
            </p>
        </div>
    </div>

    <div class="cap-sticky" data-coa-sticky>
        <a class="cap-btn" href="#coa-form"><?= lucide('send') ?> Go to the form</a>
    </div>
</section>

<script>
/* The three role cards link into the form AND tick the matching position, so
   "Apply for this role" lands the reader on a form already answering its
   section 2. Progressive: without JS the anchor still scrolls to the form. */
(function () {
    document.querySelectorAll('[data-coa-pick-link]').forEach(function (a) {
        a.addEventListener('click', function () {
            var radio = document.getElementById('coa-pos-' + a.getAttribute('data-coa-pick-link'));
            if (radio && !radio.checked) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
