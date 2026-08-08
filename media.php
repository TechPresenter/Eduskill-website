<?php
/**
 * =============================================================================
 *  Media & Videos — DB-driven video library with a featured player.
 *
 *  - Lists published videos (ORDER BY sort_order). Each card shows a thumbnail
 *    (uploaded thumbnail, else the YouTube hq thumb), title and category badge.
 *  - The first embeddable video loads into a featured player; clicking any card
 *    swaps that player's embed in place (small progressive-enhancement JS — the
 *    cards remain plain YouTube/watch links for no-JS and keyboard users).
 *  - Optional category filter, a Press & Media desk section, and a graceful
 *    empty-state so the page never looks broken before content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data -------------------------------------------------------------- */
$videos = db_all("SELECT * FROM videos WHERE status = 1 ORDER BY sort_order ASC, id ASC");

/* Derive an embeddable id + a thumbnail for every video, collect categories. */
$items      = [];
$categories = [];
foreach ($videos as $v) {
    $ytid = youtube_id($v['youtube_id'] ?: ($v['video_url'] ?? null));

    if (!empty($v['thumbnail'])) {
        $thumb = upload_url($v['thumbnail']);
    } elseif ($ytid) {
        $thumb = 'https://img.youtube.com/vi/' . $ytid . '/hqdefault.jpg';
    } else {
        $thumb = image_url(null); // placeholder asset
    }

    $cat = trim((string) ($v['category'] ?? ''));
    if ($cat !== '') {
        $categories[$cat] = true;
    }

    $items[] = ['row' => $v, 'ytid' => $ytid, 'thumb' => $thumb, 'cat' => $cat];
}
$categories = array_keys($categories);
sort($categories);

/* First YouTube-embeddable video powers the featured player. */
$featured = null;
foreach ($items as $it) {
    if ($it['ytid']) {
        $featured = $it;
        break;
    }
}

/* Press desk contact details (DB-driven with config fallbacks). */
$pressEmail = get_setting('contact_email', SITE_EMAIL);
$pressPhone = get_setting('contact_phone', SITE_PHONE);
$pressAddr  = get_setting('contact_address', SITE_ADDRESS);
$pressCin   = get_setting('cin', SITE_CIN);

seo_set([
    'title'       => 'Media & Videos',
    'description' => 'Watch stories, event highlights and impact films from EDUSKILL INDIA FOUNDATION — and find press resources and media contacts for our work across Bihar.',
    'image'       => $featured ? 'https://img.youtube.com/vi/' . $featured['ytid'] . '/hqdefault.jpg' : null,
    'page_key'    => 'media',
]);

$page_hero = [
    'title'      => 'Media & Videos',
    'subtitle'   => 'Watch the stories behind our work — event highlights, field reports and the voices of the communities we serve.',
    'breadcrumb' => [['label' => 'Media & Videos']],
];

include __DIR__ . '/includes/header.php';
?>

<?php if ($featured): $fRow = $featured['row']; ?>
<!-- ============================== FEATURED PLAYER ============================== -->
<section class="section section-dark" style="background:linear-gradient(rgba(11,17,32,.86),rgba(11,17,32,.92)), var(--grad-brand);">
    <div class="container reveal">
        <div class="section-head">
            <span class="eyebrow" style="color:#7BB8EC;justify-content:center;">Now Playing</span>
            <h2 class="section-title text-white">Watch Our Impact</h2>
        </div>

        <div class="map-embed" id="mediaPlayerWrap" style="max-width:960px;margin-inline:auto;aspect-ratio:16/9;border-radius:var(--radius-lg,16px);overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.45);">
            <iframe id="mediaPlayer"
                    src="https://www.youtube.com/embed/<?= e($featured['ytid']) ?>?rel=0"
                    title="<?= e($fRow['title']) ?>"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen loading="lazy"></iframe>
        </div>

        <div class="text-center mt-4" style="max-width:760px;margin-inline:auto;">
            <?php if (!empty($fRow['category'])): ?>
                <span class="badge badge-accent mb-1" data-player-badge><?= e($fRow['category']) ?></span>
            <?php else: ?>
                <span class="badge badge-accent mb-1" data-player-badge style="display:none;"></span>
            <?php endif; ?>
            <h3 class="text-white" data-player-title><?= e($fRow['title']) ?></h3>
            <p style="color:rgba(255,255,255,.85);" data-player-desc><?= e(excerpt($fRow['description'] ?? '', 34)) ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== VIDEO LIBRARY ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Video Library</span>
            <h2 class="section-title">Stories in Motion</h2>
            <p class="section-subtitle">From medical camps and classrooms to relief drives and celebrations — press play on the moments that define our mission.</p>
        </div>

        <?php if ($items): ?>

            <?php if (count($categories) > 1): ?>
            <!-- Category filter (progressive enhancement — all cards shown without JS) -->
            <div class="flex flex-wrap justify-center gap-2 mb-4" role="group" aria-label="Filter videos by category">
                <button type="button" class="btn btn-sm btn-primary" data-filter="all">All</button>
                <?php foreach ($categories as $cat): ?>
                    <button type="button" class="btn btn-sm btn-outline" data-filter="<?= e($cat) ?>"><?= e($cat) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-3">
                <?php foreach ($items as $i => $it):
                    $v     = $it['row'];
                    $ytid  = $it['ytid'];
                    $desc  = excerpt($v['description'] ?? '', 20);
                    // Progressive-enhancement target: YouTube watch page, else the raw url.
                    $href  = $ytid
                        ? 'https://www.youtube.com/watch?v=' . $ytid
                        : ($v['video_url'] ?? '');
                    $tag   = $href !== '' ? 'a' : 'div';
                ?>
                    <<?= $tag ?> class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>"
                        data-video-card data-category="<?= e($it['cat']) ?>"
                        <?php if ($href !== ''): ?>href="<?= e($href) ?>"<?php endif; ?>
                        <?php if ($ytid): ?>
                            data-video-swap
                            data-yt="<?= e($ytid) ?>"
                            data-title="<?= e($v['title']) ?>"
                            data-desc="<?= e(excerpt($v['description'] ?? '', 34)) ?>"
                        <?php elseif ($href !== ''): ?>
                            target="_blank" rel="noopener"
                        <?php endif; ?>
                        style="color:inherit;<?= $href !== '' ? 'cursor:pointer;' : '' ?>">
                        <div class="card-media" style="aspect-ratio:16/9;position:relative;">
                            <img src="<?= e($it['thumb']) ?>" alt="<?= e($v['title']) ?>" loading="lazy" width="600" height="338">
                            <span aria-hidden="true" style="position:absolute;inset:0;display:grid;place-items:center;">
                                <span style="width:58px;height:58px;border-radius:50%;background:rgba(17,24,39,.55);backdrop-filter:blur(2px);color:#fff;display:grid;place-items:center;font-size:1.4rem;box-shadow:0 6px 18px rgba(0,0,0,.35);"><?= lucide('play') ?></span>
                            </span>
                            <?php if (!empty($v['category'])): ?>
                                <span class="badge badge-brand" style="position:absolute;top:12px;left:12px;background:#fff;"><?= e($v['category']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h3 class="card-title"><?= e($v['title']) ?></h3>
                            <?php if ($desc !== ''): ?>
                                <p class="card-text"><?= e($desc) ?></p>
                            <?php endif; ?>
                            <span style="color:var(--brand-600);font-weight:700;">
                                <?= $ytid ? 'Play video ' . lucide('arrow-right') : ($href !== '' ? 'Watch on source ' . lucide('arrow-right') : 'Coming soon') ?>
                            </span>
                        </div>
                    </<?= $tag ?>>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <div class="icon"><?= lucide('clapperboard') ?></div>
                <h3>Our video library is coming soon</h3>
                <p class="text-muted">We are capturing our work on the ground. Impact films, event highlights and field stories will be published here shortly.</p>
                <div class="flex justify-center gap-2 mt-3 flex-wrap">
                    <a class="btn btn-primary" href="<?= e(url('gallery')) ?>">Browse the Photo Gallery</a>
                    <a class="btn btn-outline" href="<?= e(url('events')) ?>">See Our Events</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== PRESS & MEDIA ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">For Journalists</span>
            <h2 class="section-title">Press &amp; Media</h2>
            <p class="section-subtitle">Working on a story about our work in Bihar? Our communications desk can help with interviews, quotes, photographs and filming access.</p>
        </div>

        <div class="grid grid-2 gap-4 items-stretch">
            <div class="card-3d reveal">
                <div class="icon-badge"><?= lucide('newspaper') ?></div>
                <h3 class="card-title">Media Enquiries</h3>
                <p class="card-text">For interviews with our directors, on-ground access or verified figures on our programs, reach out to the press desk — we typically respond within two working days.</p>
                <ul class="footer-contact" style="list-style:none;padding:0;margin:1rem 0 0;line-height:2;">
                    <li><?= lucide('mail') ?> <a href="mailto:<?= e($pressEmail) ?>?subject=<?= e(rawurlencode('Press enquiry — ' . SITE_NAME)) ?>"><?= e($pressEmail) ?></a></li>
                    <li><?= lucide('phone') ?> <a href="tel:<?= e(preg_replace('/\s+/', '', $pressPhone)) ?>"><?= e($pressPhone) ?></a></li>
                    <li><?= lucide('map-pin') ?> <span><?= e($pressAddr) ?></span></li>
                    <li><?= lucide('landmark') ?> <span>CIN: <?= e($pressCin) ?></span></li>
                </ul>
                <a class="btn btn-primary mt-4" href="mailto:<?= e($pressEmail) ?>?subject=<?= e(rawurlencode('Press enquiry — ' . SITE_NAME)) ?>">Email the Press Desk</a>
            </div>

            <div class="card-3d reveal delay-1">
                <div class="icon-badge accent"><?= lucide('folders') ?></div>
                <h3 class="card-title">About the Foundation</h3>
                <p class="card-text"><?= e(get_setting('footer_about', SITE_NAME . ' is a registered non-profit based in Patna, Bihar, working across education, healthcare, skill development and relief to empower underserved communities — spreading hope and creating lasting change.')) ?></p>
                <ul class="list-check mt-3">
                    <li>Registered non-profit — CIN <?= e($pressCin) ?></li>
                    <li>Based in Patna, Bihar 840007</li>
                </ul>
                <div class="flex gap-2 mt-4 flex-wrap">
                    <a class="btn btn-outline" href="<?= e(url('about')) ?>">More About Us</a>
                    <a class="btn btn-outline" href="<?= e(url('gallery')) ?>">Photo Gallery</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <h2 class="text-white">Help Us Tell More Stories</h2>
            <p style="color:rgba(255,255,255,.92);max-width:640px;margin-inline:auto;">
                Behind every film is a life changed. Support our work or join us as a volunteer — and help us bring more stories of hope to light.
            </p>
            <div class="flex justify-center gap-2 mt-3 flex-wrap">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer With Us</a>
            </div>
        </div>
    </div>
</section>

<?php if ($items): ?>
<script>
/* Featured-player swap + category filter — progressive enhancement only. */
(function () {
    var player  = document.getElementById('mediaPlayer');
    var titleEl = document.querySelector('[data-player-title]');
    var descEl  = document.querySelector('[data-player-desc]');
    var badgeEl = document.querySelector('[data-player-badge]');
    var wrap    = document.getElementById('mediaPlayerWrap');
    var cards   = Array.prototype.slice.call(document.querySelectorAll('[data-video-swap]'));

    function markActive(active) {
        cards.forEach(function (c) {
            c.style.outline = '';
            c.style.outlineOffset = '';
            c.removeAttribute('aria-current');
        });
        active.style.outline = '3px solid #063566';
        active.style.outlineOffset = '2px';
        active.setAttribute('aria-current', 'true');
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function (e) {
            var id = card.getAttribute('data-yt');
            if (!id || !player) { return; } // let the YouTube link open normally
            e.preventDefault();
            player.src = 'https://www.youtube.com/embed/' + id + '?rel=0&autoplay=1';
            if (titleEl) { titleEl.textContent = card.getAttribute('data-title') || ''; }
            if (descEl)  { descEl.textContent  = card.getAttribute('data-desc') || ''; }
            if (badgeEl) {
                var cat = card.getAttribute('data-category') || '';
                badgeEl.textContent = cat;
                badgeEl.style.display = cat ? '' : 'none';
            }
            markActive(card);
            if (wrap) { wrap.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        });
    });

    // Category filter buttons.
    var filters = Array.prototype.slice.call(document.querySelectorAll('[data-filter]'));
    var allCards = Array.prototype.slice.call(document.querySelectorAll('[data-video-card]'));
    filters.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var f = btn.getAttribute('data-filter');
            filters.forEach(function (b) {
                b.classList.toggle('btn-primary', b === btn);
                b.classList.toggle('btn-outline', b !== btn);
            });
            allCards.forEach(function (it) {
                var show = (f === 'all' || it.getAttribute('data-category') === f);
                it.style.display = show ? '' : 'none';
            });
        });
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
