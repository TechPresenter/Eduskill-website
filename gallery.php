<?php
/**
 * =============================================================================
 *  Gallery — two modes, both fully DB-driven with graceful fallbacks:
 *    1. Album view   (?album=<slug>) : one album + its media as a lightbox grid.
 *    2. Album index  (no query)      : all published albums as linked cards.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Which mode? ------------------------------------------------------- */
$albumSlug = clean(get('album', ''));
$album     = null;

if ($albumSlug !== '') {
    $album = db_row(
        "SELECT * FROM gallery_albums WHERE slug = :s AND status = 1 LIMIT 1",
        [':s' => $albumSlug]
    );
}

$isAlbumRequest = ($albumSlug !== '');
$isAlbumView    = ($album !== null);

/* ---- Data per mode ----------------------------------------------------- */
if ($isAlbumView) {
    // Media inside this album (images + videos), ordered as curated.
    $media = db_all(
        "SELECT * FROM gallery_media WHERE album_id = :a ORDER BY sort_order ASC, id ASC",
        [':a' => (int) $album['id']]
    );

    // A few sibling albums for a "keep exploring" strip.
    $moreAlbums = db_all(
        "SELECT a.*,
                (SELECT m.file_path FROM gallery_media m WHERE m.album_id = a.id ORDER BY m.sort_order ASC, m.id ASC LIMIT 1) AS first_media,
                (SELECT COUNT(*)     FROM gallery_media m WHERE m.album_id = a.id) AS media_count
         FROM gallery_albums a
         WHERE a.status = 1 AND a.id <> :id
         ORDER BY a.sort_order ASC, COALESCE(a.event_date, a.created_at) DESC, a.id DESC
         LIMIT 3",
        [':id' => (int) $album['id']]
    );

    seo_set([
        'title'       => $album['title'] . ' — Gallery',
        'description' => $album['description']
            ? excerpt($album['description'], 30)
            : 'Photos and moments from ' . $album['title'] . ' by EDUSKILL INDIA FOUNDATION.',
        'image'       => $album['cover_image'] ? upload_url($album['cover_image']) : null,
        'page_key'    => 'gallery',
        'type'        => 'article',
    ]);

    $page_hero = [
        'title'      => $album['title'],
        'subtitle'   => $album['event_date']
            ? 'Captured on ' . format_date($album['event_date'], 'd F Y')
            : ($album['description'] ? excerpt($album['description'], 22) : 'A collection of moments from our work on the ground.'),
        'breadcrumb' => [
            ['label' => 'Gallery', 'url' => url('gallery')],
            ['label' => $album['title']],
        ],
    ];
} else {
    // Album index: cover (or first media) thumb + live media count per album.
    $albums = db_all(
        "SELECT a.*,
                (SELECT m.file_path FROM gallery_media m WHERE m.album_id = a.id ORDER BY m.sort_order ASC, m.id ASC LIMIT 1) AS first_media,
                (SELECT COUNT(*)     FROM gallery_media m WHERE m.album_id = a.id) AS media_count
         FROM gallery_albums a
         WHERE a.status = 1
         ORDER BY a.sort_order ASC, COALESCE(a.event_date, a.created_at) DESC, a.id DESC"
    );

    // Photo wall: every image across published albums, carrying album context.
    // (Read-only, presentation-only — powers the masonry grid + filter tabs.)
    $allMedia = db_all(
        "SELECT m.id, m.title, m.file_path, m.type, m.caption, m.sort_order,
                a.title AS album_title, a.slug AS album_slug, a.event_date AS album_date
         FROM gallery_media m
         JOIN gallery_albums a ON a.id = m.album_id
         WHERE a.status = 1
         ORDER BY a.sort_order ASC, COALESCE(a.event_date, a.created_at) DESC, m.sort_order ASC, m.id ASC"
    );

    // Curated highlights: the lead image of each album (up to 5).
    $featured = [];
    $seenAlbum = [];
    foreach ($allMedia as $fm) {
        if (($fm['type'] ?? 'image') !== 'image') { continue; }
        if (isset($seenAlbum[$fm['album_slug']])) { continue; }
        $seenAlbum[$fm['album_slug']] = true;
        $featured[] = $fm;
        if (count($featured) >= 5) { break; }
    }

    // Live stats with graceful fallbacks (counts are always-safe COUNT(*)).
    $statAlbums = db_count('gallery_albums', 'status = 1');
    $statPhotos = db_count('gallery_media');
    $statEvents = db_count('events');
    $statVols   = db_count('volunteers');
    if ($statAlbums < 1) { $statAlbums = count($albums); }
    if ($statPhotos < 1) {
        $statPhotos = 0;
        foreach ($albums as $ca) { $statPhotos += (int) ($ca['media_count'] ?? 0); }
    }
    // Real numbers only — zero-count stats are simply not rendered (no
    // invented fallback figures on a public trust page).

    if ($isAlbumRequest) {
        // Unknown album slug: correct status + noindex (the browsable index
        // below doubles as the "browse instead" affordance).
        http_response_code(404);
    }
    seo_set([
        'title'       => $isAlbumRequest ? 'Album Not Found' : 'Gallery',
        'description' => 'Browse photo albums from EDUSKILL INDIA FOUNDATION — our programs, events, medical camps and community moments across Bihar.',
        'page_key'    => 'gallery',
        'robots'      => $isAlbumRequest ? 'noindex,nofollow' : 'index,follow',
    ]);

    $page_hero = [
        'title'      => $isAlbumRequest ? 'Album Not Found' : 'Photo Gallery',
        'subtitle'   => $isAlbumRequest
            ? 'The album you are looking for may have been moved or is no longer available.'
            : 'Every photograph is a story of hope, dignity and change — explore the moments we have shared with the communities we serve.',
        'breadcrumb' => [['label' => 'Gallery']],
    ];
}

include __DIR__ . '/includes/header.php';
?>

<style>
/* ======================= SCOPED GALLERY STYLES (.gal-*) ======================= */
.gal-wrap { --gal-brand:#0B4E3D; --gal-teal:#174D3D; --gal-gold:#F15A24; }

/* ---- Ambient hero glow beneath the shared page-hero ---- */
.gal-intro { position:relative; }
.gal-intro::before,
.gal-intro::after { content:""; position:absolute; border-radius:50%; filter:blur(70px); opacity:.28; z-index:0; pointer-events:none; }
.gal-intro::before { width:340px; height:340px; top:-140px; left:-90px; background:radial-gradient(circle,var(--gal-teal),transparent 70%); }
.gal-intro::after  { width:300px; height:300px; top:-90px; right:-70px; background:radial-gradient(circle,var(--gal-gold),transparent 70%); }
.gal-intro > * { position:relative; z-index:1; }

/* ---- Stat row ---- */
.gal-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1.25rem; }
.gal-stat {
    position:relative; text-align:center; padding:1.9rem 1rem 1.6rem;
    border-radius:var(--radius-lg); overflow:hidden;
    background:rgba(255,255,255,.62); backdrop-filter:blur(14px) saturate(160%); -webkit-backdrop-filter:blur(14px) saturate(160%);
    border:1px solid rgba(255,255,255,.6); box-shadow:var(--shadow);
    transition:transform var(--transition), box-shadow var(--transition);
}
:root[data-theme="dark"] .gal-stat { background:rgba(20,30,54,.55); border-color:rgba(255,255,255,.1); }
.gal-stat::after { content:""; position:absolute; inset:0 0 auto 0; height:4px; background:linear-gradient(90deg,var(--gal-brand),var(--gal-teal),var(--gal-gold)); }
.gal-stat:hover { transform:translateY(-6px); box-shadow:var(--shadow-lg); }
.gal-stat .icon-badge { width:56px; height:56px; margin:0 auto .9rem; font-size:1.35rem; }
.gal-stat .counter-value { font-size:clamp(1.9rem,3.4vw,2.6rem); font-weight:800; line-height:1; background:linear-gradient(120deg,var(--gal-brand),var(--gal-teal)); -webkit-background-clip:text; background-clip:text; color:transparent; }
.gal-stat .gal-stat-label { display:block; margin-top:.5rem; font-weight:700; color:var(--text-soft); font-size:.9rem; letter-spacing:.01em; }

/* ---- Filter chips (reuse .filter-chips look) ---- */
.gal-filter { margin-bottom:2rem; }
.gal-filter .filter-chip svg { width:16px; height:16px; margin-right:.15rem; vertical-align:-3px; }

/* ---- Featured highlights ---- */
.gal-featured { display:grid; grid-template-columns:repeat(12,1fr); gap:1.25rem; }
.gal-feat { position:relative; grid-column:span 4; border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--shadow); min-height:220px; cursor:zoom-in; display:block; }
.gal-feat:first-child { grid-column:span 6; }
.gal-feat img { width:100%; height:100%; min-height:220px; object-fit:cover; display:block; transition:transform .7s cubic-bezier(.2,.7,.2,1); }
.gal-feat:hover img { transform:scale(1.08); }
.gal-feat-veil { position:absolute; inset:0; background:linear-gradient(180deg,rgba(6,32,20,0) 32%,rgba(6,32,20,.82) 100%); }
.gal-feat-body { position:absolute; left:0; right:0; bottom:0; padding:1.15rem 1.25rem; color:#fff; z-index:2; }
.gal-feat-body .gal-feat-tag { display:inline-flex; align-items:center; gap:.35rem; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--gal-gold); }
.gal-feat-body h3 { color:#fff; font-size:1.15rem; margin:.25rem 0 .1rem; line-height:1.2; }
.gal-feat-body .gal-feat-date { font-size:.82rem; opacity:.9; display:inline-flex; align-items:center; gap:.35rem; }
.gal-feat-body svg { width:15px; height:15px; }

/* ---- Masonry photo wall ---- */
.gal-wall { column-count:4; column-gap:1.1rem; }
.gal-photo {
    position:relative; display:block; break-inside:avoid; margin:0 0 1.1rem;
    border-radius:var(--radius); overflow:hidden; min-height:170px; cursor:zoom-in;
    background:var(--surface-2); box-shadow:var(--shadow-sm);
    transition:transform var(--transition), box-shadow var(--transition), opacity .4s ease;
}
.gal-photo:hover { transform:translateY(-4px); box-shadow:var(--shadow-lg); }
.gal-photo.is-hidden { display:none; }
/* skeleton shimmer until the image paints */
.gal-photo::before { content:""; position:absolute; inset:0; z-index:1; background:var(--surface-2); }
.gal-photo::after { content:""; position:absolute; inset:0; z-index:2; transform:translateX(-100%); background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent); animation:galShimmer 1.5s infinite; }
:root[data-theme="dark"] .gal-photo::after { background:linear-gradient(90deg,transparent,rgba(255,255,255,.09),transparent); }
.gal-photo.is-loaded::before, .gal-photo.is-loaded::after { opacity:0; animation:none; }
@keyframes galShimmer { to { transform:translateX(100%); } }
.gal-photo img { position:relative; z-index:3; width:100%; display:block; opacity:0; transition:opacity .55s ease, transform .7s cubic-bezier(.2,.7,.2,1); }
.gal-photo.is-loaded img { opacity:1; }
.gal-photo:hover img { transform:scale(1.06); }
.gal-photo-overlay {
    position:absolute; inset:0; z-index:4; display:flex; flex-direction:column; justify-content:flex-end;
    padding:1rem; color:#fff; opacity:0; transition:opacity .35s ease;
    background:linear-gradient(180deg,rgba(6,32,20,0) 40%,rgba(6,32,20,.88) 100%);
}
.gal-photo:hover .gal-photo-overlay, .gal-photo:focus-visible .gal-photo-overlay { opacity:1; }
.gal-photo-title { font-weight:700; font-size:.98rem; line-height:1.25; transform:translateY(8px); transition:transform .35s ease; }
.gal-photo-meta { display:flex; flex-wrap:wrap; gap:.15rem .7rem; margin-top:.35rem; font-size:.78rem; opacity:.92; transform:translateY(8px); transition:transform .35s ease .04s; }
.gal-photo-meta span { display:inline-flex; align-items:center; gap:.3rem; }
.gal-photo-meta svg { width:14px; height:14px; }
.gal-photo:hover .gal-photo-title, .gal-photo:hover .gal-photo-meta { transform:translateY(0); }
/* video tiles */
.gal-photo.gal-video { display:grid; place-items:center; min-height:200px; }
.gal-photo.gal-video .gal-play { position:absolute; z-index:5; inset:0; display:grid; place-items:center; }
.gal-photo.gal-video .gal-play span { width:60px; height:60px; border-radius:50%; display:grid; place-items:center; background:rgba(255,255,255,.92); color:var(--gal-brand); box-shadow:var(--shadow-lg); transition:transform var(--transition); }
.gal-photo.gal-video:hover .gal-play span { transform:scale(1.1); }
.gal-photo.gal-video .gal-play svg { width:26px; height:26px; }

/* ---- Album cards (explore route) ---- */
.gal-albums { display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem; }
.gal-albumcard { position:relative; display:block; color:inherit; border-radius:var(--radius-lg); overflow:hidden; background:var(--surface); border:1px solid var(--border); box-shadow:var(--shadow-sm); transition:transform var(--transition), box-shadow var(--transition); }
.gal-albumcard:hover { transform:translateY(-6px); box-shadow:var(--shadow-lg); }
.gal-albumcard-media { position:relative; aspect-ratio:16/10; overflow:hidden; background:var(--surface-2); }
.gal-albumcard-media img { width:100%; height:100%; object-fit:cover; transition:transform .7s cubic-bezier(.2,.7,.2,1); }
.gal-albumcard:hover .gal-albumcard-media img { transform:scale(1.07); }
.gal-albumcard-count { position:absolute; top:12px; left:12px; z-index:2; display:inline-flex; align-items:center; gap:.35rem; padding:.32rem .7rem; border-radius:var(--radius-full); font-size:.74rem; font-weight:800; background:rgba(255,255,255,.92); color:var(--gal-brand); box-shadow:var(--shadow-sm); }
.gal-albumcard-count svg { width:14px; height:14px; }
.gal-albumcard-body { padding:1.15rem 1.25rem 1.3rem; }
.gal-albumcard-body h3 { font-size:1.1rem; margin-bottom:.3rem; }
.gal-albumcard-foot { display:flex; align-items:center; justify-content:space-between; margin-top:.7rem; }
.gal-albumcard-foot small { color:var(--muted); display:inline-flex; align-items:center; gap:.3rem; }
.gal-albumcard-foot small svg { width:14px; height:14px; }
.gal-albumcard-view { color:var(--gal-teal); font-weight:800; font-size:.85rem; display:inline-flex; align-items:center; gap:.3rem; }
.gal-albumcard-view svg { width:16px; height:16px; transition:transform var(--transition); }
.gal-albumcard:hover .gal-albumcard-view svg { transform:translateX(4px); }

/* ---- Album view meta bar ---- */
.gal-metabar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.75rem; }
.gal-metabar .gal-chips { display:flex; flex-wrap:wrap; gap:.5rem; }

/* ---- CTA band ---- */
.gal-cta { position:relative; overflow:hidden; border-radius:var(--radius-lg); padding:clamp(2.25rem,5vw,3.5rem); text-align:center; color:#fff; background:linear-gradient(135deg,var(--gal-brand) 0%,var(--gal-teal) 55%,#0b7d73 100%); box-shadow:var(--shadow-lg); }
.gal-cta::before { content:""; position:absolute; width:420px; height:420px; border-radius:50%; top:-200px; right:-120px; background:radial-gradient(circle,rgba(241,90,36,.4),transparent 70%); }
.gal-cta::after  { content:""; position:absolute; width:360px; height:360px; border-radius:50%; bottom:-200px; left:-100px; background:radial-gradient(circle,rgba(255,255,255,.18),transparent 70%); }
.gal-cta > * { position:relative; z-index:1; }
.gal-cta h2 { color:#fff; font-size:clamp(1.5rem,3vw,2.1rem); }
.gal-cta p { color:rgba(255,255,255,.92); max-width:640px; margin-inline:auto; }
.gal-cta-actions { display:flex; flex-wrap:wrap; justify-content:center; gap:.75rem; margin-top:1.6rem; }
.gal-cta .btn-outline { border-color:rgba(255,255,255,.85); color:#fff; }
.gal-cta .btn-outline:hover { background:#fff; color:var(--gal-brand); }

/* ---- Lightbox caption enhancement (shared lightbox) ---- */
.gal-lb-caption {
    position:fixed; left:50%; bottom:max(20px,4vh); transform:translateX(-50%);
    max-width:min(90vw,720px); padding:.55rem 1.1rem; z-index:100000;
    background:rgba(6,32,20,.72); color:#fff; border-radius:var(--radius-full);
    font-size:.9rem; font-weight:600; text-align:center; backdrop-filter:blur(8px);
    box-shadow:var(--shadow-lg); pointer-events:none;
}

/* ---- Responsive ---- */
@media (max-width:1024px) {
    .gal-wall { column-count:3; }
    .gal-feat, .gal-feat:first-child { grid-column:span 6; }
    .gal-albums { grid-template-columns:repeat(2,1fr); }
}
@media (max-width:768px) {
    .gal-stats { grid-template-columns:repeat(2,1fr); }
    .gal-wall { column-count:2; }
}
@media (max-width:560px) {
    .gal-featured { grid-template-columns:1fr; }
    .gal-feat, .gal-feat:first-child { grid-column:span 1; }
    .gal-albums { grid-template-columns:1fr; }
}
@media (max-width:420px) {
    .gal-wall { column-count:1; }
}
@media (prefers-reduced-motion:reduce) {
    .gal-photo img, .gal-feat img, .gal-albumcard-media img, .gal-photo::after { transition:none; animation:none; }
}
</style>

<div class="gal-wrap">
<?php if ($isAlbumView): ?>
<!-- ============================== ALBUM VIEW ============================== -->
<section class="section">
    <div class="container">

        <div class="gal-metabar">
            <a class="btn btn-outline btn-sm" href="<?= e(url('gallery')) ?>"><?= lucide('arrow-left') ?> Back to Gallery</a>
            <div class="gal-chips">
                <?php if (!empty($album['event_date'])): ?>
                    <span class="chip"><?= lucide('calendar-days') ?> <?= e(format_date($album['event_date'], 'd M Y')) ?></span>
                <?php endif; ?>
                <span class="chip"><?= lucide('image') ?> <?= (int) count($media) ?> <?= count($media) === 1 ? 'item' : 'items' ?></span>
            </div>
        </div>

        <?php if (!empty($album['description'])): ?>
            <p class="text-muted mb-4" style="max-width:760px;"><?= e($album['description']) ?></p>
        <?php endif; ?>

        <?php if ($media): ?>
            <div class="gal-wall">
                <?php foreach ($media as $m):
                    $src     = upload_url($m['file_path']);
                    $caption = $m['caption'] ?: $m['title'] ?: $album['title'];
                    $isVideo = (($m['type'] ?? 'image') === 'video');
                ?>
                    <?php if ($isVideo): ?>
                        <a class="gal-photo gal-video is-loaded reveal" href="<?= e($src) ?>" target="_blank" rel="noopener"
                           title="<?= e($caption) ?>" aria-label="<?= e($caption) ?>">
                            <span class="gal-play" aria-hidden="true"><span><?= lucide('play') ?></span></span>
                            <span class="gal-photo-overlay">
                                <span class="gal-photo-title"><?= e($caption) ?></span>
                                <span class="gal-photo-meta"><span><?= lucide('video') ?> Video</span></span>
                            </span>
                        </a>
                    <?php else: ?>
                        <a class="gal-photo reveal" data-lightbox="<?= e($src) ?>" data-caption="<?= e($caption) ?>" title="<?= e($caption) ?>">
                            <img src="<?= e($src) ?>" alt="<?= e($caption) ?>" loading="lazy">
                            <span class="gal-photo-overlay">
                                <span class="gal-photo-title"><?= e($caption) ?></span>
                                <?php if (!empty($album['event_date'])): ?>
                                    <span class="gal-photo-meta"><span><?= lucide('calendar-days') ?> <?= e(format_date($album['event_date'], 'd M Y')) ?></span></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon"><?= lucide('image') ?></div>
                <h3>No photos in this album yet</h3>
                <p class="text-muted">We are busy on the ground — new photos from this album will be added soon.</p>
                <a class="btn btn-primary mt-3" href="<?= e(url('gallery')) ?>">Explore Other Albums</a>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php if ($moreAlbums): ?>
<!-- ============================== MORE ALBUMS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Keep Exploring</span>
            <h2 class="section-title">More Albums</h2>
        </div>
        <div class="gal-albums">
            <?php foreach ($moreAlbums as $i => $a):
                $thumb = image_url($a['cover_image'] ?: ($a['first_media'] ?? null));
                $count = (int) ($a['media_count'] ?? 0);
            ?>
                <a class="gal-albumcard reveal <?= 'delay-' . (($i % 3) + 1) ?>" href="<?= e(url('gallery?album=' . $a['slug'])) ?>">
                    <div class="gal-albumcard-media">
                        <img src="<?= e($thumb) ?>" alt="<?= e($a['title']) ?>" loading="lazy" width="600" height="375">
                        <span class="gal-albumcard-count"><?= lucide('image') ?> <?= $count ?></span>
                    </div>
                    <div class="gal-albumcard-body">
                        <h3 class="card-title"><?= e($a['title']) ?></h3>
                        <div class="gal-albumcard-foot">
                            <small><?php if (!empty($a['event_date'])): ?><?= lucide('calendar-days') ?> <?= e(format_date($a['event_date'], 'd M Y')) ?><?php else: ?>&nbsp;<?php endif; ?></small>
                            <span class="gal-albumcard-view">View <?= lucide('arrow-right') ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== CTA BAND (album view) ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="gal-cta reveal">
            <h2>Be Part of the Next Photo</h2>
            <p>Behind every image is a life changed. Join us as a volunteer or support our work — and help us create more moments of hope.</p>
            <div class="gal-cta-actions">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer</a>
                <a class="btn btn-outline btn-lg" href="<?= e(url('events')) ?>"><?= lucide('calendar-days') ?> View Events</a>
            </div>
        </div>
    </div>
</section>

<?php else: ?>
<!-- ============================== ALBUM INDEX ============================== -->

<!-- ---- Photo stat row ---- -->
<section class="section section-sm gal-intro">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">Our Story In Pictures</span>
            <h2 class="section-title">Moments Worth A Thousand Words</h2>
            <p class="section-subtitle">From classrooms and medical camps to relief drives and celebrations — every frame captures the change we build together.</p>
        </div>
        <div class="gal-stats">
            <?php
            $stats = array_values(array_filter([
                ['icon' => 'folders',        'value' => $statAlbums, 'label' => 'Photo Albums'],
                ['icon' => 'image',          'value' => $statPhotos, 'label' => 'Photos Captured'],
                ['icon' => 'calendar-days',  'value' => $statEvents, 'label' => 'Events Documented'],
                ['icon' => 'users',          'value' => $statVols,   'label' => 'Volunteers Involved'],
            ], static fn ($s) => (int) $s['value'] > 0));
            foreach ($stats as $i => $s): ?>
                <div class="gal-stat reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <span class="icon-badge"><?= lucide($s['icon']) ?></span>
                    <span class="counter-value" data-counter="<?= (int) $s['value'] ?>">0</span>
                    <span class="gal-stat-label"><?= e($s['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($featured): ?>
<!-- ---- Featured highlights ---- -->
<section class="section section-sm section-soft">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">Featured</span>
            <h2 class="section-title">Highlights From The Field</h2>
        </div>
        <div class="gal-featured">
            <?php foreach ($featured as $i => $fm):
                $fsrc = upload_url($fm['file_path']);
                $fcap = $fm['caption'] ?: $fm['title'] ?: $fm['album_title'];
            ?>
                <a class="gal-feat reveal <?= 'delay-' . (($i % 3) + 1) ?>" data-lightbox="<?= e($fsrc) ?>" data-caption="<?= e($fcap) ?>" title="<?= e($fcap) ?>">
                    <img src="<?= e($fsrc) ?>" alt="<?= e($fcap) ?>" loading="lazy">
                    <span class="gal-feat-veil"></span>
                    <span class="gal-feat-body">
                        <span class="gal-feat-tag"><?= lucide('folder') ?> <?= e($fm['album_title']) ?></span>
                        <h3><?= e($fcap) ?></h3>
                        <?php if (!empty($fm['album_date'])): ?>
                            <span class="gal-feat-date"><?= lucide('calendar-days') ?> <?= e(format_date($fm['album_date'], 'd M Y')) ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($allMedia): ?>
<!-- ---- Filter tabs + masonry photo wall ---- -->
<section class="section">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">Browse</span>
            <h2 class="section-title">The Photo Wall</h2>
            <p class="section-subtitle">Filter by album, tap any image to view it full-screen — swipe or use arrow keys to move between photos.</p>
        </div>

        <div class="gal-filter reveal">
            <div class="filter-chips" id="galFilters">
                <button type="button" class="filter-chip is-active" data-filter="all"><?= lucide('layout-grid') ?> All</button>
                <?php foreach ($albums as $a): if ((int) ($a['media_count'] ?? 0) < 1) { continue; } ?>
                    <button type="button" class="filter-chip" data-filter="<?= e($a['slug']) ?>"><?= lucide('folder') ?> <?= e($a['title']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="gal-wall" id="galWall">
            <?php foreach ($allMedia as $m):
                $src     = upload_url($m['file_path']);
                $caption = $m['caption'] ?: $m['title'] ?: $m['album_title'];
                $isVideo = (($m['type'] ?? 'image') === 'video');
            ?>
                <?php if ($isVideo): ?>
                    <a class="gal-photo gal-video is-loaded reveal" data-album="<?= e($m['album_slug']) ?>"
                       href="<?= e($src) ?>" target="_blank" rel="noopener" title="<?= e($caption) ?>" aria-label="<?= e($caption) ?>">
                        <span class="gal-play" aria-hidden="true"><span><?= lucide('play') ?></span></span>
                        <span class="gal-photo-overlay">
                            <span class="gal-photo-title"><?= e($caption) ?></span>
                            <span class="gal-photo-meta">
                                <span><?= lucide('folder') ?> <?= e($m['album_title']) ?></span>
                                <span><?= lucide('video') ?> Video</span>
                            </span>
                        </span>
                    </a>
                <?php else: ?>
                    <a class="gal-photo reveal" data-album="<?= e($m['album_slug']) ?>"
                       data-lightbox="<?= e($src) ?>" data-caption="<?= e($caption) ?>" title="<?= e($caption) ?>">
                        <img src="<?= e($src) ?>" alt="<?= e($caption) ?>" loading="lazy">
                        <span class="gal-photo-overlay">
                            <span class="gal-photo-title"><?= e($caption) ?></span>
                            <span class="gal-photo-meta">
                                <span><?= lucide('folder') ?> <?= e($m['album_title']) ?></span>
                                <?php if (!empty($m['album_date'])): ?>
                                    <span><?= lucide('calendar-days') ?> <?= e(format_date($m['album_date'], 'd M Y')) ?></span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <p class="text-center text-muted mt-3" id="galNoResults" style="display:none;">No photos in this album yet.</p>
    </div>
</section>
<?php endif; ?>

<!-- ---- Explore all albums (album-detail route) ---- -->
<section class="section <?= $allMedia ? 'section-soft' : '' ?>">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">Collections</span>
            <h2 class="section-title">Explore Our Albums</h2>
            <p class="section-subtitle">Open any album to relive the full story, moment by moment.</p>
        </div>

        <?php if ($albums): ?>
            <div class="gal-albums">
                <?php foreach ($albums as $i => $a):
                    $thumb = image_url($a['cover_image'] ?: ($a['first_media'] ?? null));
                    $count = (int) ($a['media_count'] ?? 0);
                ?>
                    <a class="gal-albumcard reveal <?= 'delay-' . (($i % 3) + 1) ?>" href="<?= e(url('gallery?album=' . $a['slug'])) ?>">
                        <div class="gal-albumcard-media">
                            <img src="<?= e($thumb) ?>" alt="<?= e($a['title']) ?>" loading="lazy" width="600" height="375">
                            <span class="gal-albumcard-count"><?= lucide('image') ?> <?= $count ?> <?= $count === 1 ? 'photo' : 'photos' ?></span>
                        </div>
                        <div class="gal-albumcard-body">
                            <h3 class="card-title"><?= e($a['title']) ?></h3>
                            <?php if (!empty($a['description'])): ?>
                                <p class="card-text text-muted"><?= e(excerpt($a['description'], 16)) ?></p>
                            <?php endif; ?>
                            <div class="gal-albumcard-foot">
                                <small><?php if (!empty($a['event_date'])): ?><?= lucide('calendar-days') ?> <?= e(format_date($a['event_date'], 'd M Y')) ?><?php else: ?>&nbsp;<?php endif; ?></small>
                                <span class="gal-albumcard-view">View Album <?= lucide('arrow-right') ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon"><?= lucide('camera') ?></div>
                <h3>Our gallery is coming to life</h3>
                <p class="text-muted">We are documenting our journey on the ground. Photo albums from our programs and events will appear here soon.</p>
                <div class="flex justify-center gap-2 mt-3 flex-wrap">
                    <a class="btn btn-primary" href="<?= e(url('events')) ?>">See Our Events</a>
                    <a class="btn btn-outline" href="<?= e(url('programs')) ?>">Explore Programs</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="gal-cta reveal">
            <h2>Be Part of the Next Photo</h2>
            <p>Behind every image is a life changed. Join us as a volunteer or support our work — and help us create more moments of hope.</p>
            <div class="gal-cta-actions">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer With Us</a>
                <a class="btn btn-outline btn-lg" href="<?= e(url('events')) ?>"><?= lucide('calendar-days') ?> View Events</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
</div><!-- /.gal-wrap -->

<script>
(function () {
    /* ---- Skeleton fade-in as each photo paints ---- */
    function markLoaded(img) {
        var card = img.closest('.gal-photo');
        if (card) { card.classList.add('is-loaded'); }
    }
    document.querySelectorAll('.gal-photo img').forEach(function (img) {
        if (img.complete && img.naturalWidth) { markLoaded(img); }
        else {
            img.addEventListener('load', function () { markLoaded(img); });
            img.addEventListener('error', function () { markLoaded(img); });
        }
    });

    /* ---- Client-side album filtering of the photo wall ---- */
    var filters = document.getElementById('galFilters');
    var wall    = document.getElementById('galWall');
    if (filters && wall) {
        var noRes = document.getElementById('galNoResults');
        var cards = Array.prototype.slice.call(wall.querySelectorAll('.gal-photo'));
        filters.addEventListener('click', function (e) {
            var btn = e.target.closest('.filter-chip');
            if (!btn) { return; }
            filters.querySelectorAll('.filter-chip').forEach(function (c) { c.classList.remove('is-active'); });
            btn.classList.add('is-active');
            var key = btn.getAttribute('data-filter');
            var shown = 0;
            cards.forEach(function (card) {
                var match = (key === 'all') || (card.getAttribute('data-album') === key);
                card.classList.toggle('is-hidden', !match);
                if (match) { shown++; }
            });
            if (noRes) { noRes.style.display = shown ? 'none' : 'block'; }
        });
    }

    /* ---- Enhance the shared lightbox: captions + touch swipe ---- */
    window.addEventListener('load', function () {
        var box = document.querySelector('.lightbox');
        if (!box) { return; }
        var img = box.querySelector('img');
        if (!img) { return; }

        // Caption map keyed by full image src.
        var map = {};
        document.querySelectorAll('[data-lightbox]').forEach(function (t) {
            map[t.getAttribute('data-lightbox')] = t.getAttribute('data-caption') || t.getAttribute('title') || '';
        });

        var cap = document.createElement('div');
        cap.className = 'gal-lb-caption';
        cap.style.display = 'none';
        box.appendChild(cap);

        function sync() {
            if (!box.classList.contains('is-open')) { cap.style.display = 'none'; return; }
            var text = map[img.getAttribute('src')] || '';
            cap.textContent = text;
            cap.style.display = text ? 'block' : 'none';
        }
        new MutationObserver(sync).observe(img, { attributes: true, attributeFilter: ['src'] });
        new MutationObserver(sync).observe(box, { attributes: true, attributeFilter: ['class'] });

        // Touch swipe -> reuse the shared prev/next buttons.
        var sx = 0, sy = 0;
        box.addEventListener('touchstart', function (e) {
            sx = e.changedTouches[0].clientX; sy = e.changedTouches[0].clientY;
        }, { passive: true });
        box.addEventListener('touchend', function (e) {
            var dx = e.changedTouches[0].clientX - sx;
            var dy = e.changedTouches[0].clientY - sy;
            if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
                var sel = dx < 0 ? '.lightbox-nav.next' : '.lightbox-nav.prev';
                var b = box.querySelector(sel);
                if (b) { b.click(); }
            }
        }, { passive: true });
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
