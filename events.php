<?php
/**
 * =============================================================================
 *  Events — listing (Upcoming + Past) and single event detail (?slug=…).
 *
 *  ?slug=<event-slug>  -> single published event: hero image, prose description,
 *                        date/time, location/venue, a live COUNTDOWN and (when
 *                        registration is required) a registration form posting
 *                        to forms/event-register.
 *  no slug             -> a featured "Next up" banner with countdown, then
 *                        Upcoming and Past events as cards.
 *
 *  Everything is DB-driven with graceful fallbacks so the page never looks
 *  broken before content has been seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$slug = clean(get('slug', ''));

/* =============================================================================
 |  SINGLE EVENT DETAIL
 |============================================================================*/
if ($slug !== '') {

    $ev = db_row(
        "SELECT * FROM events
         WHERE slug = :s
           AND status = 'published'
           AND deleted_at IS NULL
         LIMIT 1",
        [':s' => $slug]
    );

    /* ---- NOT FOUND — 404 inside the normal layout -------------------- */
    if (!$ev) {
        http_response_code(404);

        seo_set([
            'title'       => 'Event Not Found',
            'description' => 'The event you are looking for is no longer available. Explore upcoming and past events from EDUSKILL INDIA FOUNDATION.',
            'page_key'    => 'events',
            'robots'      => 'noindex,follow',
        ]);
        $page_hero = [
            'title'      => 'Event Not Found',
            'subtitle'   => 'This event may have been moved, unpublished or cancelled. Browse our upcoming events instead.',
            'breadcrumb' => [
                ['label' => 'Events', 'url' => url('events')],
                ['label' => 'Not Found'],
            ],
        ];

        include __DIR__ . '/includes/header.php';
        ?>
        <section class="section">
            <div class="container">
                <div class="empty-state">
                    <div class="icon-badge"><?= lucide('calendar-days') ?></div>
                    <h3>We couldn't find that event</h3>
                    <p class="text-muted">The link may be outdated, or the event has ended. Explore our calendar for upcoming programs, camps and community events.</p>
                    <div class="flex flex-wrap justify-center gap-2 mt-3">
                        <a class="btn btn-primary" href="<?= e(url('events')) ?>">View All Events</a>
                        <a class="btn btn-outline" href="<?= e(url('/')) ?>">Back to Home</a>
                    </div>
                </div>
            </div>
        </section>
        <?php
        echo country_field_assets();
include __DIR__ . '/includes/footer.php';
        return;
    }

    /* ---- Derived display values -------------------------------------- */
    $now     = time();
    $startTs = strtotime((string) $ev['start_datetime']);
    $endTs   = !empty($ev['end_datetime']) ? strtotime((string) $ev['end_datetime']) : null;

    $isUpcoming = $startTs !== false && $startTs >= $now;
    $isLive     = $startTs !== false && $startTs < $now && $endTs && $now <= $endTs;
    $isPast     = !$isUpcoming && !$isLive;

    // Human "when" label (collapses to a single day when start/end share a date).
    $whenLabel = format_datetime($ev['start_datetime']);
    if ($endTs) {
        $sameDay = date('Y-m-d', (int) $startTs) === date('Y-m-d', $endTs);
        $whenLabel .= ' – ' . ($sameDay
            ? format_date($ev['end_datetime'], 'h:i A')
            : format_datetime($ev['end_datetime']));
    }

    // Capacity / registration availability.
    $capacity   = (int) ($ev['capacity'] ?? 0);
    $registered = (int) db_count('event_registrations', 'event_id = :e AND status <> :c',
        [':e' => (int) $ev['id'], ':c' => 'cancelled']);
    $spotsLeft  = $capacity > 0 ? max(0, $capacity - $registered) : null;
    $isFull     = $capacity > 0 && $registered >= $capacity;

    $regRequired = (int) ($ev['registration_required'] ?? 0) === 1;
    $canRegister = $regRequired && $isUpcoming && !$isFull;

    $summary  = $ev['excerpt'] ?: excerpt($ev['description'] ?? '', 30);
    $mapQuery = trim((($ev['venue'] ?? '') !== '' ? $ev['venue'] . ', ' : '') . ($ev['location'] ?? ''));

    // Other events to keep the page rich (upcoming first, else recent past).
    $others = db_all(
        "SELECT * FROM events
         WHERE status = 'published' AND deleted_at IS NULL
           AND id <> :id AND start_datetime >= NOW()
         ORDER BY start_datetime ASC LIMIT 3",
        [':id' => (int) $ev['id']]
    );
    if (!$others) {
        $others = db_all(
            "SELECT * FROM events
             WHERE status = 'published' AND deleted_at IS NULL
               AND id <> :id AND start_datetime < NOW()
             ORDER BY start_datetime DESC LIMIT 3",
            [':id' => (int) $ev['id']]
        );
    }

    /* ---- SEO + hero -------------------------------------------------- */
    seo_set([
        'title'       => $ev['title'],
        'description' => $summary ?: ('Join ' . SITE_NAME . ' for ' . $ev['title'] . '.'),
        'image'       => !empty($ev['image']) ? upload_url($ev['image']) : null,
        'type'        => 'article',
        'page_key'    => 'event:' . $ev['slug'],
    ]);

    $page_hero = [
        'title'      => $ev['title'],
        'subtitle'   => $isPast
            ? 'A look back at this event · ' . format_date($ev['start_datetime'], 'd M Y')
            : ($ev['location'] ? 'Join us · ' . $ev['location'] : 'Join us · ' . format_date($ev['start_datetime'], 'd M Y')),
        'breadcrumb' => [
            ['label' => 'Events', 'url' => url('events')],
            ['label' => $ev['title']],
        ],
    ];

    include __DIR__ . '/includes/header.php';
    echo json_ld_breadcrumb($page_hero['breadcrumb']);
    echo json_ld_event($ev);
    ?>

    <!-- ============================== EVENT DETAIL ============================== -->
    <section class="section">
        <div class="container grid grid-sidebar gap-4 items-start">

            <!-- Main column -->
            <article class="reveal">

                <figure class="card-media" style="border-radius:var(--radius,16px);overflow:hidden;margin:0 0 1.5rem;">
                    <img src="<?= e(image_url($ev['image'])) ?>" alt="<?= e($ev['title']) ?>" width="900" height="520" style="width:100%;height:auto;display:block;object-fit:cover;">
                </figure>

                <div class="flex items-center gap-2 flex-wrap mb-3">
                    <?php if ($isLive): ?>
                        <span class="badge badge-danger"><?= lucide('circle-dot') ?> Happening Now</span>
                    <?php elseif ($isUpcoming): ?>
                        <span class="badge badge-success">Upcoming</span>
                    <?php else: ?>
                        <span class="badge badge-muted">Completed</span>
                    <?php endif; ?>
                    <?php if ((int) ($ev['is_featured'] ?? 0) === 1): ?>
                        <span class="badge badge-accent"><?= lucide('star') ?> Featured</span>
                    <?php endif; ?>
                    <?php if ($regRequired): ?>
                        <span class="badge badge-brand"><?= lucide('ticket') ?> Registration Required</span>
                    <?php endif; ?>
                </div>

                <?php if ($isUpcoming): ?>
                    <!-- Live countdown -->
                    <div class="card-3d mb-4" style="background:var(--grad-brand);color:#fff;text-align:center;">
                        <span class="eyebrow" style="color:#fff;justify-content:center;">Event starts in</span>
                        <div class="countdown" data-countdown="<?= e($ev['start_datetime']) ?>" style="justify-content:center;flex-wrap:wrap;margin-top:.5rem;">
                            <div><span data-cd-d>00</span><small>Days</small></div>
                            <div><span data-cd-h>00</span><small>Hours</small></div>
                            <div><span data-cd-m>00</span><small>Minutes</small></div>
                            <div><span data-cd-s>00</span><small>Seconds</small></div>
                        </div>
                        <?php if ($canRegister): ?>
                            <a class="btn btn-white mt-3" href="#register">Register Now</a>
                        <?php endif; ?>
                    </div>
                <?php elseif ($isLive): ?>
                    <div class="alert alert-info mb-4">This event is happening now. We hope to see you there!</div>
                <?php else: ?>
                    <div class="alert alert-info mb-4">This event has concluded. Thank you to everyone who took part — explore our upcoming events below.</div>
                <?php endif; ?>

                <h2 class="section-title" style="margin-top:.25rem;">About This Event</h2>
                <div class="prose">
                    <?php if (!empty(trim((string) $ev['description']))): ?>
                        <?php if (!empty($ev['excerpt'])): ?>
                            <p style="font-size:1.15rem;color:var(--text);"><strong><?= e($ev['excerpt']) ?></strong></p>
                        <?php endif; ?>
                        <?= rich_text($ev['description']) ?>
                    <?php else: ?>
                        <p><?= e($summary ?: 'Full details for this event are being finalised. Please check back soon, or contact us to learn more.') ?></p>
                    <?php endif; ?>
                </div>

                <!-- Registration -->
                <?php if ($regRequired): ?>
                    <div class="divider"></div>
                    <div id="register">
                        <div class="section-head left">
                            <span class="eyebrow">Reserve Your Spot</span>
                            <h2 class="section-title mb-0">Register for This Event</h2>
                        </div>

                        <?php if (!$isUpcoming): ?>
                            <div class="alert alert-warning mt-3">Registration has closed for this event.</div>
                        <?php elseif ($isFull): ?>
                            <div class="alert alert-warning mt-3">All spots for this event are now full. Please check back for future events or contact us to join the waitlist.</div>
                        <?php else: ?>
                            <?php if ($spotsLeft !== null): ?>
                                <p class="text-muted mt-2">Hurry — only <strong><?= (int) $spotsLeft ?></strong> spot<?= $spotsLeft === 1 ? '' : 's' ?> left out of <?= (int) $capacity ?>.</p>
                            <?php else: ?>
                                <p class="text-muted mt-2">Fill in the form below and we'll confirm your registration by email.</p>
                            <?php endif; ?>

                            <form data-ajax-form data-endpoint="<?= e(url('forms/event-register')) ?>" class="card mt-3">
                                <div class="card-body">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label" for="r-name">Full Name <span class="req">*</span></label>
                                            <input class="form-control" id="r-name" name="name" maxlength="128" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="r-email">Email <span class="req">*</span></label>
                                            <input class="form-control" id="r-email" type="email" name="email" maxlength="191" required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <?= country_field(['name' => 'phone', 'label' => 'Phone']) ?>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="r-guests">Number of Guests</label>
                                            <select class="form-select" id="r-guests" name="guests">
                                                <?php for ($g = 1; $g <= 10; $g++): ?>
                                                    <option value="<?= $g ?>"><?= $g ?><?= $g === 10 ? '+' : '' ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="r-message">Message <span class="form-hint">(optional)</span></label>
                                        <textarea class="form-textarea" id="r-message" name="message" rows="4" maxlength="1000" placeholder="Any questions or special requirements?"></textarea>
                                    </div>
                                    <button class="btn btn-primary btn-block" type="submit">Confirm Registration</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <a class="btn btn-outline" href="<?= e(url('events')) ?>"><?= lucide('arrow-left') ?> Back to All Events</a>
                </div>
            </article>

            <!-- Sidebar -->
            <aside class="reveal delay-1">
                <div class="card-3d sticky-aside" style="position:sticky;top:90px;">
                    <h3 class="mb-2">Event Details</h3>
                    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.85rem;">
                        <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('calendar-days') ?> <span><strong>When</strong><br><span class="text-muted"><?= e($whenLabel) ?></span></span></li>
                        <?php if (!empty($ev['venue'])): ?>
                            <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('landmark') ?> <span><strong>Venue</strong><br><span class="text-muted"><?= e($ev['venue']) ?></span></span></li>
                        <?php endif; ?>
                        <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('map-pin') ?> <span><strong>Location</strong><br><span class="text-muted"><?= e($ev['location'] ?: 'Patna, Bihar') ?></span></span></li>
                        <?php if ($capacity > 0): ?>
                            <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('users') ?> <span><strong>Capacity</strong><br><span class="text-muted"><?= (int) $capacity ?> attendees<?php if ($spotsLeft !== null && $isUpcoming): ?> · <?= (int) $spotsLeft ?> left<?php endif; ?></span></span></li>
                        <?php endif; ?>
                        <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('ticket') ?> <span><strong>Registration</strong><br><span class="text-muted"><?= $regRequired ? 'Required' : 'Not required — all are welcome' ?></span></span></li>
                    </ul>

                    <?php if ($canRegister): ?>
                        <a class="btn btn-primary btn-block mt-3" href="#register">Register Now</a>
                    <?php endif; ?>

                    <?php if ($mapQuery !== ''): ?>
                        <div class="divider"></div>
                        <h4 class="mb-2">Find Us</h4>
                        <div class="map-embed" style="aspect-ratio:4/3;">
                            <iframe title="Event location" loading="lazy" allowfullscreen
                                src="https://www.google.com/maps?q=<?= e(urlencode($mapQuery)) ?>&output=embed"></iframe>
                        </div>
                    <?php endif; ?>

                    <div class="divider"></div>
                    <h4 class="mb-2">Share this event</h4>
                    <?php
                        $shareUrl      = abs_url('events?slug=' . $ev['slug']);
                        $shareUrlEnc   = rawurlencode($shareUrl);
                        $shareTitleEnc = rawurlencode($ev['title']);
                    ?>
                    <div class="flex gap-2 flex-wrap">
                        <a class="btn btn-outline btn-sm" href="https://www.facebook.com/sharer/sharer.php?u=<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><?= social_svg('facebook') ?></a>
                        <a class="btn btn-outline btn-sm" href="https://twitter.com/intent/tweet?url=<?= e($shareUrlEnc) ?>&amp;text=<?= e($shareTitleEnc) ?>" target="_blank" rel="noopener" aria-label="Share on X"><?= social_svg('x') ?></a>
                        <a class="btn btn-outline btn-sm" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><?= social_svg('linkedin') ?></a>
                        <a class="btn btn-outline btn-sm" href="https://wa.me/?text=<?= e($shareTitleEnc) ?>%20<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><?= social_svg('whatsapp') ?></a>
                        <a class="btn btn-outline btn-sm" href="mailto:?subject=<?= e($shareTitleEnc) ?>&amp;body=<?= e($shareUrlEnc) ?>" aria-label="Share by email"><?= lucide('mail') ?></a>
                    </div>

                    <div class="divider"></div>
                    <a class="btn btn-accent btn-block" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Support Our Work</a>
                </div>
            </aside>
        </div>
    </section>

    <?php if ($others): ?>
    <!-- ============================== MORE EVENTS ============================== -->
    <section class="section section-soft">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Don't Miss Out</span>
                <h2 class="section-title">More Events</h2>
                <p class="section-subtitle">Other ways to get involved with <?= e(SITE_NAME) ?>.</p>
            </div>
            <div class="grid grid-3">
                <?php foreach ($others as $i => $o): ?>
                    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <a href="<?= e(url('events?slug=' . $o['slug'])) ?>" class="card-media">
                            <img src="<?= e(image_url($o['image'])) ?>" alt="<?= e($o['title']) ?>" loading="lazy" width="600" height="375">
                            <span class="badge badge-brand" style="position:absolute;top:12px;left:12px;background:#fff;"><?= e(format_date($o['start_datetime'], 'd M Y')) ?></span>
                        </a>
                        <div class="card-body">
                            <h3 class="card-title"><a href="<?= e(url('events?slug=' . $o['slug'])) ?>" style="color:inherit;"><?= e($o['title']) ?></a></h3>
                            <p class="card-text"><?= lucide('map-pin') ?> <?= e($o['location'] ?: 'Patna, Bihar') ?></p>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('events?slug=' . $o['slug'])) ?>">View Details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

/* =============================================================================
 |  LISTING — Upcoming + Past
 |============================================================================*/
$upcoming = db_all(
    "SELECT * FROM events
     WHERE status = 'published' AND deleted_at IS NULL
       AND start_datetime >= NOW()
     ORDER BY start_datetime ASC"
);
$past = db_all(
    "SELECT * FROM events
     WHERE status = 'published' AND deleted_at IS NULL
       AND start_datetime < NOW()
     ORDER BY start_datetime DESC LIMIT 12"
);

$featured = $upcoming[0] ?? null;                 // soonest upcoming = "Next up"
$rest     = $upcoming ? array_slice($upcoming, 1) : [];
$hasAny   = $upcoming || $past;

seo_set([
    'title'       => 'Events',
    'description' => 'Upcoming and past events from ' . SITE_NAME . ' — community programs, medical camps, awareness drives and celebrations across Patna and Bihar. Register to join us.',
    'page_key'    => 'events',
]);

$page_hero = [
    'title'      => 'Events',
    'subtitle'   => 'Camps, drives, workshops and celebrations — find an event near you and join the movement to spread hope and create change.',
    'breadcrumb' => [['label' => 'Events']],
];

include __DIR__ . '/includes/header.php';
?>

<?php if (!$hasAny): ?>
<!-- ============================== EMPTY STATE ============================== -->
<section class="section">
    <div class="container">
        <div class="empty-state">
            <div class="icon-badge"><?= lucide('calendar-days') ?></div>
            <h3>No events scheduled just yet</h3>
            <p class="text-muted">We're planning our next community events. Subscribe to our newsletter or follow us to be the first to know when registrations open.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-3">
                <a class="btn btn-primary" href="<?= e(url('contact')) ?>">Get in Touch</a>
                <a class="btn btn-outline" href="<?= e(url('volunteer')) ?>">Become a Volunteer</a>
            </div>
        </div>
    </div>
</section>
<?php else: ?>

<!-- ============================== QUICK NAV ============================== -->
<section class="section section-sm">
    <div class="container text-center">
        <div class="flex flex-wrap justify-center gap-2">
            <?php if ($upcoming): ?><a class="btn btn-outline btn-sm" href="#upcoming">Upcoming (<?= count($upcoming) ?>)</a><?php endif; ?>
            <?php if ($past): ?><a class="btn btn-outline btn-sm" href="#past">Past Events</a><?php endif; ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate</a>
        </div>
    </div>
</section>

<?php if ($featured): ?>
<!-- ============================== NEXT UP (FEATURED) ============================== -->
<section class="section" id="next-up">
    <div class="container">
        <div class="card-3d reveal" style="overflow:hidden;padding:0;">
            <div class="grid grid-2 items-stretch" style="gap:0;">
                <div class="card-media" style="min-height:280px;border-radius:0;">
                    <img src="<?= e(image_url($featured['image'])) ?>" alt="<?= e($featured['title']) ?>" width="700" height="500" style="width:100%;height:100%;object-fit:cover;display:block;">
                </div>
                <div style="padding:clamp(1.5rem,3vw,2.5rem);">
                    <span class="eyebrow">Next Up</span>
                    <h2 class="section-title mb-1"><?= e($featured['title']) ?></h2>
                    <p class="text-muted mb-2">
                        <?= lucide('calendar-days') ?> <?= e(format_datetime($featured['start_datetime'])) ?><br>
                        <?= lucide('map-pin') ?> <?= e($featured['location'] ?: 'Patna, Bihar') ?>
                    </p>
                    <p class="card-text"><?= e(excerpt($featured['excerpt'] ?: $featured['description'], 26)) ?></p>
                    <div class="countdown" data-countdown="<?= e($featured['start_datetime']) ?>" style="flex-wrap:wrap;margin:1rem 0;">
                        <div><span data-cd-d>00</span><small>Days</small></div>
                        <div><span data-cd-h>00</span><small>Hours</small></div>
                        <div><span data-cd-m>00</span><small>Minutes</small></div>
                        <div><span data-cd-s>00</span><small>Seconds</small></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a class="btn btn-primary" href="<?= e(url('events?slug=' . $featured['slug'])) ?>">
                            <?= (int) ($featured['registration_required'] ?? 0) === 1 ? 'Details & Register' : 'View Details' ?>
                        </a>
                        <?php if ((int) ($featured['is_featured'] ?? 0) === 1): ?>
                            <span class="badge badge-accent" style="align-self:center;"><?= lucide('star') ?> Featured</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== UPCOMING EVENTS ============================== -->
<section class="section section-soft" id="upcoming">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Mark Your Calendar</span>
            <h2 class="section-title">Upcoming Events</h2>
            <p class="section-subtitle">Join us at our next programs, camps and community gatherings across Bihar.</p>
        </div>

        <?php if ($rest): ?>
            <div class="grid grid-3">
                <?php foreach ($rest as $i => $ev): ?>
                    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <a href="<?= e(url('events?slug=' . $ev['slug'])) ?>" class="card-media">
                            <img src="<?= e(image_url($ev['image'])) ?>" alt="<?= e($ev['title']) ?>" loading="lazy" width="600" height="375">
                            <span class="badge badge-brand" style="position:absolute;top:12px;left:12px;background:#fff;"><?= e(format_date($ev['start_datetime'], 'd M Y')) ?></span>
                            <?php if ((int) ($ev['registration_required'] ?? 0) === 1): ?>
                                <span class="badge badge-accent" style="position:absolute;top:12px;right:12px;"><?= lucide('ticket') ?> Register</span>
                            <?php endif; ?>
                        </a>
                        <div class="card-body">
                            <h3 class="card-title"><a href="<?= e(url('events?slug=' . $ev['slug'])) ?>" style="color:inherit;"><?= e($ev['title']) ?></a></h3>
                            <p class="card-text"><?= lucide('clock') ?> <?= e(format_datetime($ev['start_datetime'], 'h:i A')) ?> · <?= lucide('map-pin') ?> <?= e($ev['location'] ?: 'Patna, Bihar') ?></p>
                            <?php $blurb = excerpt($ev['excerpt'] ?: $ev['description'], 18); if ($blurb !== ''): ?>
                                <p class="card-text"><?= e($blurb) ?></p>
                            <?php endif; ?>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('events?slug=' . $ev['slug'])) ?>">Details &amp; Register</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$featured): ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('calendar-days') ?></div>
                <h3>No upcoming events right now</h3>
                <p class="text-muted">New events are added regularly. Meanwhile, take a look at what we've done recently below.</p>
            </div>
        <?php else: ?>
            <p class="text-center text-muted">That's our only scheduled event for now — see the highlight above. More events are coming soon!</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($past): ?>
<!-- ============================== PAST EVENTS ============================== -->
<section class="section" id="past">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Looking Back</span>
            <h2 class="section-title">Past Events</h2>
            <p class="section-subtitle">Moments of impact from events we've hosted with our communities and partners.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($past as $i => $ev): ?>
                <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <a href="<?= e(url('events?slug=' . $ev['slug'])) ?>" class="card-media">
                        <img src="<?= e(image_url($ev['image'])) ?>" alt="<?= e($ev['title']) ?>" loading="lazy" width="600" height="375" style="filter:saturate(.9);">
                        <span class="badge badge-muted" style="position:absolute;top:12px;left:12px;background:#fff;"><?= e(format_date($ev['start_datetime'], 'd M Y')) ?></span>
                    </a>
                    <div class="card-body">
                        <h3 class="card-title"><a href="<?= e(url('events?slug=' . $ev['slug'])) ?>" style="color:inherit;"><?= e($ev['title']) ?></a></h3>
                        <p class="card-text"><?= lucide('map-pin') ?> <?= e($ev['location'] ?: 'Patna, Bihar') ?></p>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('events?slug=' . $ev['slug'])) ?>">View Recap</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== GET INVOLVED CTA ============================== -->
<section class="section section-brand">
    <div class="container text-center reveal">
        <span class="eyebrow" style="color:#fff;justify-content:center;">Be Part of the Change</span>
        <h2 class="text-white">Want to host or support an event?</h2>
        <p class="text-white" style="opacity:.9;max-width:640px;margin:0 auto 1.5rem;">Partner with us, volunteer your time, or sponsor a community program. Together we can reach more lives across Bihar.</p>
        <div class="flex flex-wrap justify-center gap-2">
            <a class="btn btn-white btn-lg" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('contact')) ?>">Contact Us</a>
        </div>
    </div>
</section>

<?php endif; /* $hasAny */ ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
