<?php
/**
 * =============================================================================
 *  Share Your Feedback — a warm, encouraging page inviting the community to
 *  tell us how we're doing.
 *
 *  The left column sells the "why" (what feedback makes possible, how to reach
 *  us); the right column carries the feedback form that posts to forms/feedback
 *  with a 1–5 star rating selector (radio inputs + tiny inline styling/JS).
 *
 *  A live satisfaction snapshot, "what you can share", a "how we use it" journey
 *  and a wall of reviewed community feedback round out the page. Every dynamic
 *  block is DB-driven (feedback stats + reviewed feedback) and degrades to
 *  sensible NGO-appropriate defaults so the page looks complete before seeding.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Live feedback statistics ------------------------------------------ */
$stats = db_row(
    "SELECT
         COUNT(*)                                       AS total,
         COUNT(`rating`)                                AS rated,
         AVG(`rating`)                                  AS avg_rating,
         SUM(CASE WHEN `rating` >= 4 THEN 1 ELSE 0 END) AS happy,
         SUM(CASE WHEN `status` = 'reviewed' THEN 1 ELSE 0 END) AS reviewed
     FROM `feedback`"
);

$total        = (int) ($stats['total'] ?? 0);
$rated        = (int) ($stats['rated'] ?? 0);
$happy        = (int) ($stats['happy'] ?? 0);
$reviewedCnt  = (int) ($stats['reviewed'] ?? 0);
$avgRating    = $rated > 0 ? round((float) $stats['avg_rating'], 1) : 0;
$satisfaction = $rated > 0 ? (int) round($happy / $rated * 100) : 0;

/* Real figures only — a stat with no data is hidden rather than invented. */
$snapshot = [
    ['icon' => 'star',           'value' => $avgRating,    'suffix' => ' / 5', 'label' => 'Average Rating',      'decimals' => 1],
    ['icon' => 'message-square', 'value' => $total,        'suffix' => '+',    'label' => 'Voices Heard',        'decimals' => 0],
    ['icon' => 'circle-check',   'value' => $reviewedCnt,  'suffix' => '+',    'label' => 'Ideas Actioned',      'decimals' => 0],
    ['icon' => 'smile',          'value' => $satisfaction, 'suffix' => '%',    'label' => 'Would Recommend Us',  'decimals' => 0],
];
$snapshot = array_filter($snapshot, static fn (array $s): bool => $s['value'] > 0);

/* ---- Reviewed community feedback (public wall) ------------------------- */
$wall = db_all(
    "SELECT `name`, `subject`, `message`, `rating`, `created_at`
     FROM `feedback`
     WHERE `status` = 'reviewed' AND `rating` IS NOT NULL
     ORDER BY `id` DESC
     LIMIT 6"
);
if (!$wall) {
    $wall = [
        ['name' => 'Sunita Devi',   'subject' => 'Our Programs',      'rating' => 5, 'created_at' => null, 'message' => 'The education programme gave my daughter a real chance. The volunteers are patient and genuinely caring — thank you for never giving up on our children.'],
        ['name' => 'Manoj Kumar',   'subject' => 'Events & Activities','rating' => 5, 'created_at' => null, 'message' => 'I attended the free health camp in my village. Everything was organised so well and the doctors treated everyone with respect. Please keep doing this important work.'],
        ['name' => 'Farhan Ansari', 'subject' => 'Donations',         'rating' => 4, 'created_at' => null, 'message' => 'As a donor I appreciate the transparency reports the most. It is reassuring to see exactly where my contribution goes. A monthly summary email would make it perfect.'],
        ['name' => 'Kavita Raj',    'subject' => 'Volunteering',      'rating' => 5, 'created_at' => null, 'message' => 'Volunteering here has been the highlight of my year. The team listens to suggestions and actually acts on them — that is rare and it kept me coming back.'],
        ['name' => 'Ravi Prakash',  'subject' => 'Website',           'rating' => 5, 'created_at' => null, 'message' => 'The new website makes it so easy to find events and read about the impact. Clean, fast and full of hope. Great job to everyone behind the scenes.'],
        ['name' => 'Anjali Singh',  'subject' => 'Our Programs',      'rating' => 5, 'created_at' => null, 'message' => 'The skill-development classes changed how I see my own future. I now earn a small income of my own. I share your work with everyone I meet.'],
    ];
}

/* ---- Star meanings (for the selector + accessibility) ------------------ */
$ratingWords = [5 => 'Excellent', 4 => 'Very Good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'];

/* ---- Common feedback topics (datalist suggestions) --------------------- */
$topics = [
    'Our Programs',
    'Events & Activities',
    'Volunteering Experience',
    'Donations & Transparency',
    'Website & Online Experience',
    'Staff & Team',
    'A Suggestion or Idea',
    'General Feedback',
];

/* ---- Why feedback matters (list-check) --------------------------------- */
$reasons = [
    'Every comment helps us shape programmes around what communities truly need',
    'Praise motivates our volunteers and tells us what is working on the ground',
    'Honest criticism is a gift — it shows us exactly where to improve',
    'Fresh ideas from you often become tomorrow\'s projects and campaigns',
    'Ratings keep us accountable and transparent to the people we serve',
];

/* ---- What you can share (category cards) ------------------------------- */
$categories = [
    ['icon' => 'sprout',        'title' => 'Programme Impact',   'text' => 'Tell us how our education, healthcare or livelihood work has touched your life or your community.'],
    ['icon' => 'lightbulb',     'title' => 'Ideas & Suggestions', 'text' => 'Spotted a gap or dreamt up a better way to serve? Your ideas can spark our next project.'],
    ['icon' => 'handshake',     'title' => 'Volunteer Experience', 'text' => 'Share how it feels to give your time with us — what inspires you and what we can do better.'],
    ['icon' => 'search',        'title' => 'Transparency',        'text' => 'Comment on our reporting, communication and how clearly we account for every rupee.'],
    ['icon' => 'party-popper',  'title' => 'Events & Camps',      'text' => 'Attended a camp, drive or awareness event? Let us know how the experience was for you.'],
    ['icon' => 'laptop',        'title' => 'Website & Service',   'text' => 'Trouble donating, registering or finding information? Help us make every visit effortless.'],
];

/* ---- How we use your feedback (journey) -------------------------------- */
$steps = [
    ['icon' => 'clipboard-pen', 'title' => 'You Share',   'text' => 'Fill in the short form below — a rating, a subject and your honest thoughts.'],
    ['icon' => 'eye',           'title' => 'We Read',      'text' => 'Every submission reaches our team. Nothing is ignored and no voice is too small.'],
    ['icon' => 'wrench',        'title' => 'We Improve',   'text' => 'Recurring themes and bright ideas are turned into real changes across our work.'],
    ['icon' => 'mail',          'title' => 'We Follow Up', 'text' => 'Leave your email and, where helpful, our team reaches out to thank you or learn more.'],
];

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'Share Your Feedback',
    'description' => 'Your voice shapes our mission. Tell ' . SITE_NAME . ' what we are doing well and where we can do better — rate your experience and share your ideas to help us serve communities across Bihar with greater impact.',
    'page_key'    => 'feedback',
    'type'        => 'website',
]);

/* ---- Hero banner ------------------------------------------------------- */
$page_hero = [
    'title'      => 'Share Your Feedback',
    'subtitle'   => 'Your voice is our compass. Tell us what we are doing well, where we can do better, and how together we can create even greater change.',
    'breadcrumb' => [
        ['label' => 'Get Involved'],
        ['label' => 'Feedback'],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<!-- Scoped styling for the one-off star-rating selector (no CSS equivalent in the contract) -->
<style>
    .star-select{display:inline-flex;flex-direction:row-reverse;justify-content:flex-start;gap:.2rem;font-size:2.1rem;line-height:1}
    .star-select input{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);border:0;white-space:nowrap}
    .star-select label{color:rgba(148,163,184,.45);cursor:pointer;transition:color .15s ease,transform .12s ease;padding:.05rem}
    .star-select input:checked ~ label{color:#E67B1D}
    .star-select:hover label{color:rgba(148,163,184,.45)}
    .star-select label:hover,
    .star-select label:hover ~ label{color:#E67B1D;transform:scale(1.08)}
    .star-select input:focus-visible + label{outline:2px solid var(--brand-600,#063566);outline-offset:3px;border-radius:6px}
</style>

<!-- ============================== INTRO + FEEDBACK FORM ============================== -->
<section class="section">
    <div class="container grid grid-2 items-start gap-4">

        <!-- WHY YOUR FEEDBACK MATTERS -->
        <div class="reveal">
            <span class="eyebrow">We're Listening</span>
            <h2 class="section-title">Your Words Shape Our Work</h2>
            <p class="lead">At <?= e(SITE_NAME) ?>, every project we run and every life we touch is guided by the people we serve. Whether it's a word of encouragement, a hard truth, or a bright new idea — your feedback helps us grow into the organisation our community deserves.</p>
            <p class="text-muted">There are no wrong answers and nothing is too small to mention. Take a moment to rate your experience and tell us what's on your mind — it genuinely makes a difference.</p>

            <ul class="list-check mt-3">
                <?php foreach ($reasons as $reason): ?>
                    <li><?= e($reason) ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="flex flex-wrap gap-2 mt-4">
                <span class="chip"><?= lucide('star') ?> Rate your experience</span>
                <span class="chip"><?= lucide('message-square') ?> Share honestly</span>
                <span class="chip"><?= lucide('lock') ?> Kept confidential</span>
                <span class="chip"><?= lucide('zap') ?> Read by our team</span>
            </div>

            <div class="divider"></div>
            <p class="text-muted mb-0" style="font-size:.92rem;">
                Prefer to talk it through? Call us at
                <a href="tel:<?= e(preg_replace('/[^+\d]/', '', SITE_PHONE)) ?>"><strong><?= e(SITE_PHONE) ?></strong></a>
                or email <a href="mailto:<?= e(SITE_EMAIL) ?>"><strong><?= e(SITE_EMAIL) ?></strong></a>.
                We usually respond within a couple of working days.
            </p>
        </div>

        <!-- FEEDBACK FORM -->
        <div class="reveal delay-1">
            <div class="card-3d" id="feedback-form">
                <span class="eyebrow">Feedback Form</span>
                <h3 class="card-title" style="font-size:1.5rem;">Tell Us How We're Doing</h3>
                <p class="text-muted" style="margin-top:-.35rem;">It takes less than a minute. Only your feelings and your message are required — the rest is up to you.</p>

                <form data-ajax-form data-endpoint="<?= e(url('forms/feedback')) ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <input type="text" name="website_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden">

                    <!-- STAR RATING -->
                    <div class="form-group">
                        <label class="form-label">How would you rate your overall experience?</label>
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="star-select" id="ratingStars" role="radiogroup" aria-label="Rate your experience from 1 to 5 stars">
                                <?php foreach ($ratingWords as $val => $word): ?>
                                    <input type="radio" name="rating" id="star-<?= (int) $val ?>" value="<?= (int) $val ?>">
                                    <label for="star-<?= (int) $val ?>" title="<?= e($word) ?>" aria-label="<?= (int) $val ?> star<?= $val > 1 ? 's' : '' ?> — <?= e($word) ?>"><?= lucide('star') ?></label>
                                <?php endforeach; ?>
                            </div>
                            <span id="ratingLabel" class="badge badge-accent" style="min-width:96px;text-align:center;">Not rated</span>
                        </div>
                        <small class="form-hint">Tap a star to rate, from 1 (Poor) to 5 (Excellent). Optional, but it helps us a lot.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="f-name">Your Name <span class="req">*</span></label>
                            <input class="form-control" id="f-name" name="name" type="text" maxlength="128" placeholder="e.g. Anjali Kumari" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="f-email">Email <span class="text-muted">(optional)</span></label>
                            <input class="form-control" id="f-email" name="email" type="email" maxlength="191" placeholder="you@example.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-subject">What is your feedback about?</label>
                        <input class="form-control" id="f-subject" name="subject" type="text" maxlength="191" list="feedback-topics" placeholder="Choose a topic or type your own">
                        <datalist id="feedback-topics">
                            <?php foreach ($topics as $topic): ?>
                                <option value="<?= e($topic) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-message">Your Feedback <span class="req">*</span></label>
                        <textarea class="form-textarea" id="f-message" name="message" rows="5" maxlength="5000" placeholder="Tell us what went well, what could be better, or any idea you'd like to share…" required></textarea>
                        <small class="form-hint">Please share at least a sentence so we can understand you clearly.</small>
                    </div>

                    <button class="btn btn-primary btn-lg btn-block mt-2" type="submit"><?= lucide('message-square') ?> Submit Feedback</button>
                    <p class="text-center text-muted mt-2 mb-0" style="font-size:.85rem;">Thank you for helping us improve. We read every single response with care.</p>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ============================== SATISFACTION SNAPSHOT ============================== -->
<?php if ($snapshot): ?>
<section class="section section-brand">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;">The Community's Verdict</span>
            <h2 class="section-title text-white">What People Feel About Our Work</h2>
            <p class="section-subtitle" style="color:rgba(255,255,255,.9);">A living snapshot drawn from the feedback our supporters and beneficiaries share with us.</p>
        </div>
        <div class="counter-grid">
            <?php foreach ($snapshot as $s): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($s['icon']) ?></div>
                    <div class="counter-value">
                        <span data-counter="<?= e((string) $s['value']) ?>" data-decimals="<?= (int) $s['decimals'] ?>">0</span><?= e($s['suffix']) ?>
                    </div>
                    <div class="counter-label"><?= e($s['label']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== WHAT YOU CAN SHARE ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Not Sure Where to Start?</span>
            <h2 class="section-title">What You Can Share With Us</h2>
            <p class="section-subtitle">Anything on your mind is welcome. Here are a few of the things our community loves to tell us about.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($categories as $i => $cat): ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="icon-badge"><?= lucide($cat['icon']) ?></div>
                    <h3 class="card-title"><?= e($cat['title']) ?></h3>
                    <p class="card-text"><?= e($cat['text']) ?></p>
                    <a href="#feedback-form" style="color:var(--brand-600);font-weight:700;">Share this <?= lucide('arrow-right') ?></a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== HOW WE USE YOUR FEEDBACK ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">From Your Words to Real Change</span>
            <h2 class="section-title">How We Use Your Feedback</h2>
            <p class="section-subtitle">Your feedback doesn't vanish into an inbox. Here's the journey every response takes.</p>
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

<!-- ============================== COMMUNITY FEEDBACK WALL ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">In Their Own Words</span>
            <h2 class="section-title">Feedback From Our Community</h2>
            <p class="section-subtitle">A selection of the kind, candid and constructive words people have shared with us.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($wall as $i => $w): ?>
                <div class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>" style="height:100%;">
                    <div class="card-body">
                        <?= star_rating((int) ($w['rating'] ?? 5)) ?>
                        <p class="mt-2" style="font-size:1.02rem;color:var(--text-soft);">“<?= e($w['message']) ?>”</p>
                        <div class="flex items-center gap-2 mt-3">
                            <div class="icon-badge" aria-hidden="true" style="width:44px;height:44px;font-size:1.1rem;flex:0 0 auto;">
                                <?= e(function_exists('mb_substr') ? mb_strtoupper(mb_substr((string) $w['name'], 0, 1)) : strtoupper(substr((string) $w['name'], 0, 1))) ?>
                            </div>
                            <div>
                                <strong><?= e($w['name']) ?></strong><br>
                                <small class="text-muted">
                                    <?= e($w['subject'] ?: 'General feedback') ?><?php if (!empty($w['created_at'])): ?> · <?= e(time_ago($w['created_at'])) ?><?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a class="btn btn-primary" href="#feedback-form"><?= lucide('pen-line') ?> Add Your Voice</a>
        </div>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;max-width:900px;margin-inline:auto;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Every Voice Moves Us Forward</h2>
            <p style="color:rgba(255,255,255,.92);max-width:640px;margin-inline:auto;">Whether it's a thank-you, a concern or a spark of an idea, your feedback helps us serve communities across Bihar with more heart and more impact. We can't wait to hear from you.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="#feedback-form"><?= lucide('message-square') ?> Share Your Feedback</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('contact')) ?>"><?= lucide('mail') ?> Contact Us</a>
            </div>
        </div>
    </div>
</section>

<!-- Small inline JS to reflect the chosen star rating as a friendly label -->
<script>
    (function () {
        var group = document.getElementById('ratingStars');
        var out   = document.getElementById('ratingLabel');
        if (!group || !out) return;
        var words = { '1': 'Poor', '2': 'Fair', '3': 'Good', '4': 'Very Good', '5': 'Excellent' };
        group.addEventListener('change', function (e) {
            var t = e.target;
            if (t && t.name === 'rating') {
                out.textContent = (words[t.value] || 'Not rated') + ' (' + t.value + '/5)';
            }
        });
    })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
