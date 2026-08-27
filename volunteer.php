<?php
/**
 * =============================================================================
 *  Become a Volunteer — recruitment landing page + application form.
 *
 *  Left column sells the "why" (benefits, list-check); the right column carries
 *  the multipart application form that posts to forms/volunteer (résumé optional).
 *  An impact strip, volunteer roles, a simple "how it works" journey, real
 *  volunteer voices and an FAQ round out the page.
 *
 *  Every dynamic block is DB-driven (achievements, testimonials, faqs, live
 *  volunteer count) and degrades to strong NGO-appropriate defaults so the page
 *  looks complete before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Live figures ------------------------------------------------------ */
$volunteerCount = (int) db_count('volunteers');
$joinedDisplay  = $volunteerCount > 0 ? $volunteerCount : 800;

/* ---- Impact counters (shared achievements table) ----------------------- */
$counters = get_all('achievements', ['status' => 1], 'sort_order ASC', 4);
if (!$counters) {
    $counters = [
        ['icon' => 'handshake', 'value' => 800,   'suffix' => '+', 'title' => 'Active Volunteers'],
        ['icon' => 'clock',     'value' => 45000, 'suffix' => '+', 'title' => 'Hours Contributed'],
        ['icon' => 'home',      'value' => 60,    'suffix' => '+', 'title' => 'Villages Reached'],
        ['icon' => 'users',     'value' => 25000, 'suffix' => '+', 'title' => 'Lives Touched'],
    ];
}

/* ---- Volunteer voices (testimonials) ----------------------------------- */
$voices = get_all('testimonials', ['status' => 1], 'sort_order ASC', 3);
if (!$voices) {
    $voices = [
        ['name' => 'Rakesh Singh',  'designation' => 'Volunteer since 2024', 'message' => 'Volunteering with EduSkill India has been the most rewarding experience of my life. The team truly cares about impact, and every weekend on the field feels meaningful.', 'rating' => 5, 'photo' => null],
        ['name' => 'Priya Sharma',  'designation' => 'Education Volunteer',   'message' => 'Teaching children who had never held a book before, and watching them read their first sentence — there is no feeling like it. This is where I found my purpose.', 'rating' => 5, 'photo' => null],
        ['name' => 'Amit Verma',    'designation' => 'Health Camp Volunteer', 'message' => 'From organising medical camps to reaching remote villages, I have grown as a person and a leader. EduSkill India gives you the tools and the trust to create real change.', 'rating' => 5, 'photo' => null],
    ];
}

/* ---- Volunteer FAQs ---------------------------------------------------- */
$faqs = db_all(
    "SELECT * FROM faqs
     WHERE status = 1 AND category IN ('volunteer', 'volunteering', 'general')
     ORDER BY (category IN ('volunteer', 'volunteering')) DESC, sort_order ASC, id ASC
     LIMIT 6"
);
if (!$faqs) {
    $faqs = [
        ['question' => 'Do I need any prior experience to volunteer?', 'answer' => 'Not at all. We welcome volunteers from every background. What matters most is your willingness to help — we provide orientation and on-the-job guidance for every role.'],
        ['question' => 'How much time do I need to commit?',           'answer' => 'It is entirely flexible. You can choose weekdays, weekends, or an on-call basis. Even a few hours a month makes a real difference to the communities we serve.'],
        ['question' => 'Can students and working professionals apply?', 'answer' => 'Yes. Many of our volunteers are students, homemakers and working professionals who contribute in the evenings or on weekends. There is a role to fit every schedule.'],
        ['question' => 'Will I receive a certificate?',                'answer' => 'Volunteers who complete a minimum engagement receive a certificate of appreciation, along with letters of recommendation on request.'],
        ['question' => 'Is there any fee to become a volunteer?',       'answer' => 'No. Volunteering with EDUSKILL INDIA FOUNDATION is completely free. We only ask for your time, commitment and compassion.'],
        ['question' => 'What happens after I submit the form?',         'answer' => 'Our volunteer coordinator reviews every application and reaches out within a few working days to discuss suitable opportunities and next steps.'],
    ];
}

/* ---- Volunteer roles (mirrors the area-of-interest options) ------------ */
$roles = [
    ['icon' => 'book',         'title' => 'Education',         'text' => 'Teach, tutor and mentor underprivileged children — from basic literacy to exam preparation and life skills.'],
    ['icon' => 'stethoscope',  'title' => 'Healthcare',        'text' => 'Support free medical camps, health awareness drives and community wellness programmes across rural Bihar.'],
    ['icon' => 'wrench',       'title' => 'Skill Development',  'text' => 'Train youth and women in vocational trades, computer literacy and entrepreneurship for sustainable livelihoods.'],
    ['icon' => 'party-popper', 'title' => 'Events',            'text' => 'Help plan and run awareness events, drives and community gatherings that bring people together for a cause.'],
    ['icon' => 'hand-coins',   'title' => 'Fundraising',       'text' => 'Rally support, organise campaigns and connect donors so our programmes can reach even more families.'],
    ['icon' => 'sparkles',     'title' => 'Other',             'text' => 'Content, design, photography, administration and more — whatever your talent, there is a place for it here.'],
];

/* ---- Why-volunteer benefits (list-check) ------------------------------- */
$benefits = [
    'Create real, measurable impact in the communities that need it most',
    'Gain hands-on experience in social work, leadership and project delivery',
    'Build lifelong friendships with a passionate community of changemakers',
    'Flexible schedules — contribute on weekdays, weekends or on your own terms',
    'Certificate of appreciation and letters of recommendation on request',
    'Discover your purpose while growing personally and professionally',
];

/* ---- Simple "how it works" journey ------------------------------------- */
$steps = [
    ['icon' => 'clipboard-pen', 'title' => 'Apply Online',       'text' => 'Fill in the short application form and tell us where your interests and strengths lie.'],
    ['icon' => 'phone',         'title' => 'Get a Call',          'text' => 'Our volunteer coordinator reaches out within a few working days to understand your goals.'],
    ['icon' => 'compass',       'title' => 'Orientation',         'text' => 'Attend a friendly onboarding session and get matched to a role and team that fit you.'],
    ['icon' => 'sparkles',      'title' => 'Start Creating Change','text' => 'Step onto the field and begin transforming lives — one project, one smile at a time.'],
];

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'Become a Volunteer',
    'description' => 'Join ' . SITE_NAME . ' as a volunteer and help empower communities across Bihar through education, healthcare, skill development and relief. Flexible roles, real impact — apply today.',
    'page_key'    => 'volunteer',
    'type'        => 'website',
]);

/* ---- Hero banner ------------------------------------------------------- */
$page_hero = [
    'title'      => 'Become a Volunteer',
    'subtitle'   => 'Give your time, share your skills, and become part of a movement that turns compassion into lasting change across Bihar.',
    'breadcrumb' => [
        ['label' => 'Get Involved'],
        ['label' => 'Volunteer'],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== WHY + APPLICATION FORM ============================== -->
<section class="section">
    <div class="container grid grid-2 items-start gap-4">

        <!-- WHY VOLUNTEER -->
        <div class="reveal">
            <span class="eyebrow">Join the Movement</span>
            <h2 class="section-title">Why Volunteer With Us?</h2>
            <p class="lead">Every great change begins with people who care enough to act. As a volunteer with <?= e(SITE_NAME) ?>, you become the hands and heart behind our mission — reaching children, families and whole communities with hope and opportunity.</p>
            <p class="text-muted">You don't need special qualifications — only compassion and a few hours you're willing to give. We'll guide you the rest of the way. Join <strong><?= e(number_short($joinedDisplay)) ?>+</strong> changemakers who are already spreading hope across Bihar.</p>

            <ul class="list-check mt-3">
                <?php foreach ($benefits as $benefit): ?>
                    <li><?= e($benefit) ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="flex flex-wrap gap-2 mt-4">
                <span class="chip"><?= lucide('clock') ?> Flexible hours</span>
                <span class="chip"><?= lucide('graduation-cap') ?> No experience needed</span>
                <span class="chip"><?= lucide('gift') ?> Free to join</span>
                <span class="chip"><?= lucide('award') ?> Certificate on completion</span>
            </div>

            <div class="divider"></div>
            <p class="text-muted mb-0" style="font-size:.92rem;">
                Prefer to talk first? Call us at
                <a href="tel:<?= e(preg_replace('/[^+\d]/', '', SITE_PHONE)) ?>"><strong><?= e(SITE_PHONE) ?></strong></a>
                or email <a href="mailto:<?= e(SITE_EMAIL) ?>"><strong><?= e(SITE_EMAIL) ?></strong></a>.
            </p>
        </div>

        <!-- APPLICATION FORM -->
        <div class="reveal delay-1">
            <div class="card-3d" id="apply">
                <span class="eyebrow">Volunteer Application</span>
                <h3 class="card-title" style="font-size:1.5rem;">Tell Us About Yourself</h3>
                <p class="text-muted" style="margin-top:-.35rem;">Fill in the form below and our team will get in touch with the right opportunity for you.</p>

                <form data-ajax-form data-endpoint="<?= e(url('forms/volunteer')) ?>" enctype="multipart/form-data" class="mt-2">
                    <?= csrf_field() ?>
                    <input type="text" name="pwf_zq" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="v-name">Full Name <span class="req">*</span></label>
                            <input class="form-control" id="v-name" name="name" type="text" maxlength="128" placeholder="e.g. Anjali Kumari" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="v-email">Email <span class="req">*</span></label>
                            <input class="form-control" id="v-email" name="email" type="email" maxlength="191" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <?= country_field(['name' => 'phone', 'label' => 'Phone', 'required' => true]) ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="v-city">City / Town</label>
                            <input class="form-control" id="v-city" name="city" type="text" maxlength="96" placeholder="e.g. Patna">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="v-area">Area of Interest <span class="req">*</span></label>
                            <select class="form-select" id="v-area" name="area_of_interest" required>
                                <option value="" disabled selected>Choose a focus area…</option>
                                <option value="Education">Education</option>
                                <option value="Healthcare">Healthcare</option>
                                <option value="Skill Development">Skill Development</option>
                                <option value="Events">Events</option>
                                <option value="Fundraising">Fundraising</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="v-availability">Availability <span class="req">*</span></label>
                            <select class="form-select" id="v-availability" name="availability" required>
                                <option value="" disabled selected>When are you free?…</option>
                                <option value="Weekdays">Weekdays</option>
                                <option value="Weekends">Weekends</option>
                                <option value="Flexible">Flexible</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="v-message">Why do you want to volunteer?</label>
                        <textarea class="form-textarea" id="v-message" name="message" rows="4" placeholder="Tell us a little about yourself, your skills, and what motivates you to give back."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="v-resume">Résumé / CV <span class="text-muted">(optional)</span></label>
                        <input class="form-control" id="v-resume" name="resume" type="file" accept=".pdf,.doc,.docx">
                        <small class="form-hint">PDF, DOC or DOCX — up to 5&nbsp;MB. Helpful for skill-based roles, but not required.</small>
                    </div>

                    <button class="btn btn-primary btn-lg btn-block mt-2" type="submit"><?= lucide('handshake') ?> Submit Application</button>
                    <p class="text-center text-muted mt-2 mb-0" style="font-size:.85rem;">By applying you agree to be contacted by our volunteer team. We never share your details.</p>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ============================== IMPACT STRIP ============================== -->
<section class="section section-brand">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;">The Power of Showing Up</span>
            <h2 class="section-title text-white">What Our Volunteers Make Possible</h2>
            <p class="section-subtitle" style="color:rgba(255,255,255,.9);">Behind every number is a person who chose to give their time — and a life that changed because of it.</p>
        </div>
        <div class="counter-grid">
            <?php foreach ($counters as $c): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($c['icon'] ?? 'star') ?></div>
                    <div class="counter-value"><?= e($c['prefix'] ?? '') ?><span data-counter="<?= (int) ($c['value'] ?? 0) ?>">0</span><?= e($c['suffix'] ?? '') ?></div>
                    <div class="counter-label"><?= e($c['title'] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== VOLUNTEER ROLES ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Ways to Contribute</span>
            <h2 class="section-title">Find the Role That Fits You</h2>
            <p class="section-subtitle">Whatever your passion or profession, there's a meaningful way for you to make a difference with us.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($roles as $i => $role): ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="icon-badge"><?= lucide($role['icon']) ?></div>
                    <h3 class="card-title"><?= e($role['title']) ?></h3>
                    <p class="card-text"><?= e($role['text']) ?></p>
                    <a class="link-action" href="#apply" style="color:var(--brand-600);font-weight:700;">Apply for this <?= lucide('arrow-right') ?></a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== HOW IT WORKS ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Getting Started Is Easy</span>
            <h2 class="section-title">Your Volunteering Journey</h2>
            <p class="section-subtitle">Four simple steps from filling in the form to changing lives on the ground.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($steps as $i => $step): $num = $i + 1; ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 4) + 1) ?>">
                    <div class="icon-badge" style="font-weight:800;"><?= e(str_pad((string) $num, 2, '0', STR_PAD_LEFT)) ?></div>
                    <div style="font-size:1.6rem;line-height:1;margin-bottom:.35rem;"><?= lucide($step['icon']) ?></div>
                    <h3 class="card-title"><?= e($step['title']) ?></h3>
                    <p class="card-text"><?= e($step['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== VOLUNTEER VOICES ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Voices From the Field</span>
            <h2 class="section-title">Why Our Volunteers Stay</h2>
            <p class="section-subtitle">Real stories from the people who give their time — and gain so much in return.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($voices as $i => $t): ?>
                <div class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>" style="height:100%;">
                    <div class="card-body">
                        <?= star_rating((int) ($t['rating'] ?? 5)) ?>
                        <p class="mt-2" style="font-size:1.02rem;color:var(--text-soft);">“<?= e($t['message']) ?>”</p>
                        <div class="flex items-center gap-2 mt-3">
                            <img src="<?= e(image_url($t['photo'] ?? null, 'avatar')) ?>" alt="<?= e($t['name']) ?>" width="52" height="52" style="width:52px;height:52px;border-radius:50%;object-fit:cover;">
                            <div>
                                <strong><?= e($t['name']) ?></strong><br>
                                <small class="text-muted"><?= e($t['designation'] ?? 'Volunteer') ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== FAQ ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Good to Know</span>
            <h2 class="section-title">Volunteer FAQs</h2>
            <p class="section-subtitle">Everything you need to know before you begin. Still have a question? Just reach out to our team.</p>
        </div>
        <div class="grid grid-2 gap-3">
            <?php foreach ($faqs as $i => $faq): ?>
                <details class="card reveal <?= 'delay-' . (($i % 2) + 1) ?>" <?= $i === 0 ? 'open' : '' ?> style="padding:1.1rem 1.25rem;">
                    <summary style="font-weight:700;cursor:pointer;list-style:none;"><?= e($faq['question']) ?></summary>
                    <p class="text-muted mt-2 mb-0"><?= rich_text($faq['answer']) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;max-width:900px;margin-inline:auto;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Your Time Is the Greatest Gift You Can Give</h2>
            <p style="color:rgba(255,255,255,.92);max-width:640px;margin-inline:auto;">Thousands of lives are waiting for someone who cares. Take the first step today — the community you'll change might just change you too.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="#apply"><?= lucide('handshake') ?> Apply to Volunteer</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Instead</a>
            </div>
        </div>
    </div>
</section>

<?php echo country_field_assets();
include __DIR__ . '/includes/footer.php'; ?>
