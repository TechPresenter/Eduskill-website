<?php
/**
 * =============================================================================
 *  Membership — become a member of EDUSKILL INDIA FOUNDATION.
 *  Plans are pulled from `membership_plans` (premium pricing cards) and the
 *  application is a multi-step form posting to forms/membership. Every section
 *  is DB-driven with curated fallbacks so the page is complete before seeding.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */
$plans = db_all(
    "SELECT * FROM membership_plans WHERE status = 1 ORDER BY sort_order ASC, id ASC"
);

/* Curated fallback plans so the page reads as complete before seeding.
   These carry no `id`, so the application select submits an empty plan_id
   (nullable FK) until real plans exist in the database. */
if (!$plans) {
    $plans = [
        [
            'name'        => 'Supporter',
            'price'       => 500,
            'duration'    => 'Annual',
            'is_featured' => 0,
            'benefits'    => "Official digital membership certificate\nQuarterly impact newsletter\nInvitations to community events\nName listed on our supporters wall",
        ],
        [
            'name'        => 'Member',
            'price'       => 1000,
            'duration'    => 'Annual',
            'is_featured' => 1,
            'benefits'    => "Everything in Supporter\nVoting rights at the annual general meeting\nPriority access to volunteering drives\nExclusive field-visit opportunities\nAnnual impact report delivered to you",
        ],
        [
            'name'        => 'Patron',
            'price'       => 5000,
            'duration'    => 'Annual',
            'is_featured' => 0,
            'benefits'    => "Everything in Member\nRecognition as a Patron on our website\nPersonalised impact briefing each year\nDedicated relationship manager\nSpecial invitation to our annual gala",
        ],
    ];
}

/* Curated "why join" benefits — the value of membership at a glance. */
$whyJoin = [
    ['cls' => '',  'icon' => 'handshake',   'title' => 'Be Part of the Movement', 'text' => 'Join a community of changemakers driving education, healthcare and relief across Bihar.'],
    ['cls' => 'p', 'icon' => 'bar-chart-3', 'title' => 'See Your Impact',          'text' => 'Receive regular impact reports so you can see exactly how your support creates change.'],
    ['cls' => 'g', 'icon' => 'ticket',      'title' => 'Exclusive Access',         'text' => 'Get priority invitations to events, field visits and volunteering opportunities.'],
    ['cls' => 'o', 'icon' => 'vote',        'title' => 'Have a Voice',             'text' => 'Members help shape our direction with voting rights at the annual general meeting.'],
];

/* Curated trust/impact numbers for the members band. */
$memberStats = [
    ['icon' => 'users',         'value' => 1200, 'suffix' => '+', 'label' => 'Active Members'],
    ['icon' => 'home',          'value' => 60,   'suffix' => '+', 'label' => 'Villages Served'],
    ['icon' => 'badge-check',   'value' => 100,  'suffix' => '%', 'label' => 'Funds Tracked'],
    ['icon' => 'calendar-days', 'value' => 12,   'suffix' => '+', 'label' => 'Years of Service'],
];

/* How membership works — process steps. */
$steps = [
    ['title' => 'Choose Your Plan',     'text' => 'Pick the membership tier that matches how you would like to contribute.'],
    ['title' => 'Submit Your Details',  'text' => 'Complete the short application form — it takes less than two minutes.'],
    ['title' => 'We Review & Confirm',  'text' => 'Our team verifies your application and reaches out with your membership details.'],
    ['title' => 'Welcome Aboard',       'text' => 'Receive your certificate and start enjoying member benefits right away.'],
];

/* Curated FAQs. */
$faqs = [
    ['q' => 'Who can become a member?',            'a' => 'Anyone aged 18 or above who supports our mission can apply. Members can be individuals, families or organisations from anywhere in India and beyond.'],
    ['q' => 'How long does a membership last?',    'a' => 'Most memberships are annual and can be renewed each year. Your renewal date is confirmed in your welcome email once your application is approved.'],
    ['q' => 'Is my membership fee tax-deductible?','a' => 'Contributions to EDUSKILL INDIA FOUNDATION may qualify for tax benefits under applicable sections. We issue a receipt for every payment for your records.'],
    ['q' => 'Can I upgrade my plan later?',        'a' => 'Absolutely. You can move to a higher tier at any time — just reach out to our team and we will adjust your membership.'],
];

seo_set([
    'title'       => 'Membership',
    'description' => 'Become a member of EDUSKILL INDIA FOUNDATION. Choose a membership plan and join a community driving education, healthcare and relief across Bihar.',
    'page_key'    => 'membership',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Become a Member',
    'subtitle'   => 'Join a growing community of changemakers and help us build a brighter, more equitable Bihar.',
    'breadcrumb' => [['label' => 'Membership']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== WHY JOIN ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Membership</span>
            <h2 class="section-title">Why Become a Member?</h2>
            <p class="section-subtitle">Your membership is more than a contribution — it is a lasting partnership that turns compassion into measurable change on the ground.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($whyJoin as $i => $b): ?>
                <div class="icon-box glass-card reveal <?= 'delay-' . (($i % 3) + 1) ?> <?= e($b['cls']) ?>">
                    <div class="ib-icon"><?= lucide($b['icon']) ?></div>
                    <h3 class="card-title"><?= e($b['title']) ?></h3>
                    <p class="card-text"><?= e($b['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== MEMBERSHIP PLANS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Choose Your Level</span>
            <h2 class="section-title">Membership Plans</h2>
            <p class="section-subtitle">Every tier fuels our work. Pick the one that feels right for you — you can always upgrade later.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($plans as $i => $plan):
                $benefits = preg_split('/\r\n|\r|\n/', trim((string) ($plan['benefits'] ?? '')));
                $benefits = array_values(array_filter(array_map('trim', $benefits), 'strlen'));
                $featured = !empty($plan['is_featured']);
                $planId   = isset($plan['id']) ? (int) $plan['id'] : '';
            ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 3) + 1) ?>"
                     style="display:flex;flex-direction:column;position:relative;<?= $featured ? 'border:2px solid var(--brand-600);box-shadow:var(--shadow-brand);' : '' ?>">
                    <?php if ($featured): ?>
                        <span class="badge badge-accent" style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);"><?= lucide('star') ?> Most Popular</span>
                    <?php endif; ?>
                    <h3 class="card-title text-center mb-1"><?= e($plan['name']) ?></h3>
                    <div class="text-center">
                        <span class="text-grad-multi" style="font-size:2.6rem;font-weight:800;line-height:1;"><?= e(money((float) ($plan['price'] ?? 0))) ?></span>
                        <div class="text-muted mt-1"><?= e($plan['duration'] ?? 'Annual') ?></div>
                    </div>
                    <div class="divider"></div>
                    <?php if ($benefits): ?>
                        <ul class="list-check" style="text-align:left;flex:1;">
                            <?php foreach ($benefits as $benefit): ?>
                                <li><?= e($benefit) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="card-text text-center" style="flex:1;">Full member benefits included.</p>
                    <?php endif; ?>
                    <a class="btn <?= $featured ? 'btn-3d' : 'btn-outline' ?> btn-block mt-3 plan-pick"
                       href="#apply" data-plan-id="<?= e((string) $planId) ?>">
                        Choose <?= e($plan['name']) ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== MEMBERS IMPACT BAND ============================== -->
<section class="section section-brand">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="color:#bfdbfe;justify-content:center;">Stronger Together</span>
            <h2 class="section-title text-white">What Your Membership Powers</h2>
        </div>
        <div class="counter-grid">
            <?php foreach ($memberStats as $s): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($s['icon']) ?></div>
                    <div class="counter-value"><span data-counter="<?= (int) $s['value'] ?>">0</span><?= e($s['suffix']) ?></div>
                    <div class="counter-label"><?= e($s['label']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== HOW IT WORKS ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Simple &amp; Transparent</span>
            <h2 class="section-title">How Membership Works</h2>
            <p class="text-muted mb-3">Joining takes just a few minutes. Here is exactly what happens from the moment you apply to the day you become a proud member.</p>
            <div class="process-steps">
                <?php foreach ($steps as $st): ?>
                    <div class="process-step">
                        <h3 class="card-title mb-0"><?= e($st['title']) ?></h3>
                        <p class="card-text"><?= e($st['text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="glass-card reveal delay-1" style="position:relative;overflow:hidden;">
            <span class="blob b1" style="top:-40px;right:-40px;"></span>
            <span class="blob b2" style="bottom:-50px;left:-30px;"></span>
            <div style="position:relative;">
                <div class="icon-box o">
                    <div class="ib-icon"><?= lucide('heart') ?></div>
                    <h3 class="card-title">A Membership That Gives Back</h3>
                    <p class="card-text">From day one you become part of a transparent, community-led movement. Every rupee is tracked from your contribution to its impact on the ground.</p>
                </div>
                <ul class="list-check mt-2" style="text-align:left;">
                    <li>Registered non-profit governed by dedicated directors</li>
                    <li>Receipt issued for every contribution</li>
                    <li>Cancel or upgrade your plan any time</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ============================== APPLICATION FORM ============================== -->
<section class="section section-alt" id="apply">
    <div class="container container-narrow">
        <div class="section-head">
            <span class="eyebrow">Join Us</span>
            <h2 class="section-title">Membership Application</h2>
            <p class="section-subtitle">Complete the short form below in three quick steps. We will review your application and be in touch soon.</p>
        </div>

        <div class="glass-card reveal" style="padding:2.25rem;">
            <form data-ajax-form data-endpoint="<?= e(url('forms/membership')) ?>" data-stepper novalidate>
                <?= csrf_field() ?>

                <!-- Stepper indicator -->
                <div class="stepper">
                    <div class="step is-active">
                        <div class="dot">1</div>
                        <div class="label">Plan &amp; You</div>
                    </div>
                    <div class="step">
                        <div class="dot">2</div>
                        <div class="label">Contact</div>
                    </div>
                    <div class="step">
                        <div class="dot">3</div>
                        <div class="label">Confirm</div>
                    </div>
                </div>

                <!-- Step 1: plan + name + email -->
                <div class="form-step is-active">
                    <div class="field-float">
                        <select id="plan_id" name="plan_id" required>
                            <option value="" selected disabled></option>
                            <?php foreach ($plans as $plan):
                                $optId  = isset($plan['id']) ? (int) $plan['id'] : '';
                                $optTxt = $plan['name'] . ' — ' . money((float) ($plan['price'] ?? 0)) . ' / ' . ($plan['duration'] ?? 'Annual');
                            ?>
                                <option value="<?= e((string) $optId) ?>"><?= e($optTxt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="plan_id">Membership Plan</label>
                    </div>
                    <div class="field-float">
                        <input type="text" id="name" name="name" placeholder=" " required maxlength="128">
                        <label for="name">Full Name</label>
                    </div>
                    <div class="field-float">
                        <input type="email" id="email" name="email" placeholder=" " required maxlength="191">
                        <label for="email">Email Address</label>
                    </div>
                    <div class="flex justify-end mt-2">
                        <button type="button" class="btn btn-3d" data-next>Continue <?= lucide('arrow-right') ?></button>
                    </div>
                </div>

                <!-- Step 2: phone + address + occupation -->
                <div class="form-step">
                    <div class="field-float">
                        <?= country_field(['name' => 'phone', 'label' => 'Phone', 'required' => true]) ?>
                        <label for="phone">Phone Number</label>
                    </div>
                    <div class="field-float">
                        <input type="text" id="address" name="address" placeholder=" " maxlength="255">
                        <label for="address">Address</label>
                    </div>
                    <div class="field-float">
                        <input type="text" id="occupation" name="occupation" placeholder=" " maxlength="128">
                        <label for="occupation">Occupation</label>
                    </div>
                    <div class="flex justify-between mt-2">
                        <button type="button" class="btn btn-outline" data-prev><?= lucide('arrow-left') ?> Back</button>
                        <button type="button" class="btn btn-3d" data-next>Continue <?= lucide('arrow-right') ?></button>
                    </div>
                </div>

                <!-- Step 3: message + submit -->
                <div class="form-step">
                    <div class="field-float">
                        <textarea id="message" name="message" placeholder=" " maxlength="1000"></textarea>
                        <label for="message">Why do you want to join? (optional)</label>
                    </div>
                    <label class="checkbox mb-2">
                        <input type="checkbox" name="consent" value="1" required>
                        I agree to be contacted about my membership application.
                    </label>
                    <div class="flex justify-between items-center mt-2 flex-wrap gap-2">
                        <button type="button" class="btn btn-outline" data-prev><?= lucide('arrow-left') ?> Back</button>
                        <button type="submit" class="btn btn-3d btn-lg">Submit Application</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- ============================== FAQ + CTA ============================== -->
<section class="section section-soft">
    <div class="container grid grid-2 gap-4 items-start">
        <div class="reveal">
            <span class="eyebrow">Good to Know</span>
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="text-muted mb-3">Everything you need to know before becoming a member. Still have a question? We are happy to help.</p>
            <?php foreach ($faqs as $f): ?>
                <details class="accordion-item">
                    <summary><?= e($f['q']) ?></summary>
                    <div class="acc-body"><?= e($f['a']) ?></div>
                </details>
            <?php endforeach; ?>
        </div>
        <div class="card-3d reveal delay-1" style="background:var(--grad-brand);color:#fff;align-self:stretch;display:flex;flex-direction:column;justify-content:center;">
            <h2 class="text-white">Prefer to Give Directly?</h2>
            <p style="color:rgba(255,255,255,.9);">You do not have to become a member to make a difference. A one-time donation or a few hours of volunteering goes a long way.</p>
            <div class="flex flex-wrap gap-2 mt-3">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer</a>
            </div>
        </div>
    </div>
</section>

<script>
/* Pricing card "Choose" buttons pre-select the plan in the application form. */
(function () {
    var select = document.getElementById('plan_id');
    document.querySelectorAll('.plan-pick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!select) return;
            var id = btn.getAttribute('data-plan-id') || '';
            for (var i = 0; i < select.options.length; i++) {
                if (id !== '' && select.options[i].value === id) { select.selectedIndex = i; break; }
            }
        });
    });
})();
</script>

<?php echo country_field_assets();
include __DIR__ . '/includes/footer.php'; ?>
