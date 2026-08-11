<?php
/**
 * =============================================================================
 *  Awareness Calendar — a year-round, DB-driven list of the awareness days,
 *  weeks and months the foundation observes. Rows are grouped by calendar
 *  month and rendered as colourful, self-coloured chips/cards. Every section
 *  degrades gracefully so the page looks complete and polished before any
 *  content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */

/* All active observances, ordered chronologically through the year. */
$events = db_all(
    "SELECT * FROM awareness_calendar
     WHERE status = 1
     ORDER BY MONTH(event_date) ASC, DAY(event_date) ASC, title ASC"
);

/* Month lookup + grouping ------------------------------------------------ */
$monthNames = [
    1 => 'January',   2 => 'February', 3 => 'March',     4 => 'April',
    5 => 'May',       6 => 'June',     7 => 'July',      8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];

$byMonth    = [];   // month number => rows
$categories = [];   // distinct category => true
foreach ($events as $ev) {
    $m = (int) date('n', strtotime((string) $ev['event_date']));
    if ($m < 1 || $m > 12) {
        $m = 1;
    }
    $byMonth[$m][] = $ev;
    if (!empty($ev['category'])) {
        $categories[$ev['category']] = true;
    }
}
ksort($byMonth);

$total        = count($events);
$monthsActive = count($byMonth);
$catCount     = count($categories);

/* Impact / scope stats — animated where sensible, with pre-seed fallbacks
   so the band still reads well before any observances are added. */
$stats = [
    ['icon' => 'calendar-days', 'value' => $total > 0 ? $total : 40, 'suffix' => $total > 0 ? '' : '+', 'label' => 'Observances', 'animate' => true],
    ['icon' => 'calendar', 'value' => $monthsActive > 0 ? $monthsActive : 12, 'suffix' => '', 'label' => 'Months Covered', 'animate' => true],
    ['icon' => 'tag', 'value' => $catCount > 0 ? $catCount : 8, 'suffix' => '+', 'label' => 'Cause Themes', 'animate' => true],
    ['icon' => 'globe', 'value' => 365, 'suffix' => ' Days', 'label' => 'Of Awareness', 'animate' => true],
];

/* How we mark these days — static explainer cards for the intro band. */
$howWeObserve = [
    ['icon' => 'megaphone', 'title' => 'Community Drives', 'text' => 'Street rallies, wall paintings and door-to-door campaigns that carry each cause into the heart of Bihar’s villages and towns.'],
    ['icon' => 'graduation-cap', 'title' => 'Awareness Sessions', 'text' => 'School talks, workshops and health camps built around the theme of the day, turning information into everyday action.'],
    ['icon' => 'handshake', 'title' => 'Partnerships', 'text' => 'We join hands with schools, health workers and local bodies so every observance reaches further and lasts longer.'],
];

/* ---- SEO + hero ------------------------------------------------------- */
seo_set([
    'title'       => 'Awareness Calendar',
    'description' => 'Explore the year-round awareness calendar of EDUSKILL INDIA FOUNDATION — the national and international days, weeks and months we observe across Bihar to spread knowledge, spark action and create lasting change.',
    'page_key'    => 'calendar',
]);

$page_hero = [
    'title'      => 'Awareness Calendar',
    'subtitle'   => 'Every month carries a cause worth championing. Follow the days, weeks and months we observe through the year — and join us in turning awareness into action.',
    'breadcrumb' => [['label' => 'Awareness Calendar']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== SCOPE STATS ============================== -->
<section class="section section-brand section-sm">
    <div class="container">
        <div class="counter-grid">
            <?php foreach ($stats as $s): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($s['icon']) ?></div>
                    <div class="counter-value"><?php if ($s['animate']): ?><span data-counter="<?= (int) $s['value'] ?>">0</span><?php else: ?><?= e((string) $s['value']) ?><?php endif; ?><?= e($s['suffix']) ?></div>
                    <div class="counter-label"><?= e($s['label']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== INTRO / WHY IT MATTERS ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Why an Awareness Calendar</span>
            <h2 class="section-title"><?= e(get_setting('calendar_intro_title', 'A cause for every season, a reason for every day')) ?></h2>
            <p class="text-muted"><?= e(get_setting('calendar_intro_text', SITE_NAME . ' marks a curated calendar of awareness days, weeks and months — from health and education to environment, women’s rights and social justice. Each observance is an invitation to learn, to talk openly, and to act. By aligning our programs with these moments, we keep the most important conversations alive all year round across the communities we serve in Bihar.')) ?></p>
            <ul class="list-check mt-3">
                <li>National and international observances mapped month by month</li>
                <li>Every day tied to real drives, camps and awareness sessions on the ground</li>
                <li>A simple way for volunteers and partners to plan their support in advance</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Join a Campaign</a>
                <a class="btn btn-outline" href="<?= e(url('events')) ?>">See Upcoming Events</a>
            </div>
        </div>
        <div class="grid gap-3 reveal delay-1">
            <?php foreach ($howWeObserve as $i => $h): ?>
                <div class="card-3d">
                    <div class="icon-badge <?= $i === 1 ? 'accent' : '' ?>"><?= lucide($h['icon']) ?></div>
                    <h3 class="card-title"><?= e($h['title']) ?></h3>
                    <p class="card-text"><?= e($h['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== YEAR-ROUND CALENDAR ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Month by Month</span>
            <h2 class="section-title">The Year in Awareness</h2>
            <p class="section-subtitle">Browse every observance we champion through the calendar year. Jump straight to any active month below.</p>
        </div>

        <?php if ($events): ?>

            <!-- Month quick-navigation rail -->
            <nav class="flex flex-wrap justify-center gap-2 mb-4" aria-label="Jump to month">
                <?php foreach ($byMonth as $mNum => $rows): ?>
                    <a class="chip" href="#month-<?= (int) $mNum ?>"><?= e($monthNames[$mNum]) ?> <span class="text-muted">(<?= count($rows) ?>)</span></a>
                <?php endforeach; ?>
            </nav>

            <?php foreach ($byMonth as $mNum => $rows): ?>
                <div id="month-<?= (int) $mNum ?>" class="mb-4" style="scroll-margin-top:90px;">
                    <div class="section-head left flex items-center gap-2 mb-2">
                        <span class="badge badge-brand"><?= e($monthNames[$mNum]) ?></span>
                        <span class="text-muted"><?= count($rows) ?> observance<?= count($rows) === 1 ? '' : 's' ?></span>
                    </div>
                    <div class="grid grid-2">
                        <?php foreach ($rows as $i => $ev): ?>
                            <?php
                                $color = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) ($ev['color'] ?? '')) ? $ev['color'] : '#0B4E3D';
                                $day   = format_date($ev['event_date'], 'd');
                                $mon   = format_date($ev['event_date'], 'M');
                                $hasRange = !empty($ev['end_date'])
                                    && $ev['end_date'] !== $ev['event_date']
                                    && format_date($ev['end_date']) !== '';
                            ?>
                            <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>"
                                     style="border-left:5px solid <?= e($color) ?>;">
                                <div class="card-body flex items-start gap-3">
                                    <div aria-hidden="true"
                                         style="flex:0 0 auto;width:64px;min-height:64px;border-radius:14px;background:<?= e($color) ?>;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1;box-shadow:0 6px 16px -6px <?= e($color) ?>;">
                                        <span style="font-size:1.5rem;font-weight:800;"><?= e($day) ?></span>
                                        <span style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;opacity:.92;"><?= e($mon) ?></span>
                                    </div>
                                    <div style="min-width:0;">
                                        <div class="flex flex-wrap items-center gap-1 mb-1">
                                            <?php if (!empty($ev['category'])): ?>
                                                <span class="chip" style="border-color:<?= e($color) ?>;color:<?= e($color) ?>;font-weight:700;"><?= e($ev['category']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($ev['is_recurring'])): ?>
                                                <span class="badge badge-muted"><?= lucide('repeat') ?> Annual</span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="card-title" style="font-size:1.1rem;"><?= e($ev['title']) ?></h3>
                                        <p class="text-muted mb-1" style="font-weight:600;font-size:.9rem;">
                                            <?= lucide('calendar-days') ?> <?= e(format_date($ev['event_date'], 'd M')) ?><?php if ($hasRange): ?> &ndash; <?= e(format_date($ev['end_date'], 'd M')) ?><?php endif; ?>
                                        </p>
                                        <?php if (!empty($ev['description'])): ?>
                                            <p class="card-text mb-0"><?= e(excerpt($ev['description'], 26)) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('calendar-days') ?></div>
                <h3>The Calendar Is Being Prepared</h3>
                <p class="text-muted">
                    We are mapping out a full year of awareness days, weeks and months — from health and education
                    to environment and social justice. Check back soon, or subscribe below and we will let you know
                    the moment our awareness calendar goes live.
                </p>
                <div class="flex flex-wrap justify-center gap-2 mt-3">
                    <a class="btn btn-primary" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
                    <a class="btn btn-outline" href="<?= e(url('contact')) ?>">Suggest a Cause</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== NEWSLETTER / STAY INFORMED ============================== -->
<section class="section">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;">Never Miss a Cause</span>
            <h2 class="text-white">Get Awareness Reminders in Your Inbox</h2>
            <p style="color:rgba(255,255,255,.9);max-width:620px;margin-inline:auto;">Subscribe to receive timely reminders before each awareness day, along with simple ways you can take part and spread the word.</p>
            <form data-ajax-form data-endpoint="<?= e(url('forms/newsletter')) ?>" class="mt-4"
                  style="max-width:520px;margin-inline:auto;">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label text-white" for="cal-nl-email">Email Address <span class="req">*</span></label>
                        <input class="form-control" id="cal-nl-email" type="email" name="email" placeholder="you@example.com" required>
                    </div>
                </div>
                <button class="btn btn-white btn-block" type="submit"><?= lucide('send') ?> Subscribe for Reminders</button>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
