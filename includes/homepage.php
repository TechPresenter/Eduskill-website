<?php
/**
 * =============================================================================
 *  Homepage content — the domain layer shared by index.php and the admin.
 * =============================================================================
 *  Three homepage blocks are content, not code, and until now had no admin
 *  screen at all: the mission "focus split" bars, the five focus-area cards and
 *  the donation tiers. They live in the `settings` table under the `homepage`
 *  group like every other site setting — no parallel store — in exactly the
 *  shape index.php already read:
 *
 *      home_focus_split     one "Label|percent" line per bar
 *      home_focus_areas     JSON array of {icon,label,title,text,image,url}
 *      home_donation_tiers  JSON array of {amount,icon,title,impact}
 *
 *  Both sides of the app now come through the accessors below, so the fallback
 *  copy is written once: index.php renders what home_focus_areas() returns and
 *  admin/settings.php seeds and edits the same array. Storage format unchanged.
 *
 *  Also here: home_sections(), the single list of the sections the homepage
 *  actually renders today. admin/sections.php builds its form from it and
 *  index.php asks it whether to render a section and what to title it, so the
 *  two can no longer drift apart (they had: the admin was still offering
 *  headings for seven sections nothing on the page ever read).
 *
 *  And home_empty(): the honest empty state for a section whose content has not
 *  been seeded yet, so an unseeded homepage explains itself instead of silently
 *  dropping a third of its length.
 * =============================================================================
 */

declare(strict_types=1);

/* =============================================================================
 |  0. FIELD NORMALISERS
 |-----------------------------------------------------------------------------
 |  Every row field below used to be read as `clean((string) $v)`, which had two
 |  faults, both reachable:
 |
 |  1. `(string) $v` on an array or object raises
 |     "Array to string conversion" and yields the literal "Array". Storage is
 |     free-form JSON, so a bad import or a hand-edited row reaches the readers
 |     that way — and a crafted POST (`home_focus_areas[0][title][]=x`) reaches
 |     the ENCODERS the same way, which then PERSISTED "Array" into the settings
 |     table and rendered a card titled Array. Five warnings on a plain render,
 |     ten on that POST.
 |
 |  2. clean() is trim(strip_tags(...)), and strip_tags discards everything from
 |     an unclosed `<` to the end of the string. "Under <18 outreach" stored as
 |     "Under"; "Support for kids <18" stored as "Support for kids". Silent tail
 |     loss on a plain-language field.
 |
 |  home_text() fixes both: non-scalars become '' instead of "Array", and only
 |  WELL-FORMED tags are removed, so a lone `<` is data and survives. This is not
 |  a weakening of the output boundary — every consumer escapes with e(), which
 |  is what actually makes the value safe; the tag strip here is normalisation.
 |
 |  home_int() exists for the same reason: `(int) []` is 1, not 0, so an array
 |  posted into `amount` became a ₹1 tier rather than being dropped.
 |===========================================================================*/

/**
 * A stored/posted field as trimmed plain text.
 * Non-scalar in, empty string out — never the word "Array".
 */
function home_text(mixed $value): string
{
    if (!is_scalar($value)) {
        return '';
    }
    // Well-formed tags, comments and processing instructions only. A bare `<`
    // that opens nothing (`<18`, `a < b`) is left alone, which is the whole
    // point — strip_tags() would have eaten the rest of the string.
    $s = preg_replace('#<[a-zA-Z!/?][^>]*>#', '', (string) $value);
    return trim($s ?? (string) $value);
}

/**
 * A stored/posted field as a trimmed string with NO tag stripping — for values
 * that are paths or route slugs, where `<` is not markup and stripping would be
 * meaningless. Still non-scalar-safe.
 */
function home_raw(mixed $value): string
{
    return is_scalar($value) ? trim((string) $value) : '';
}

/** A stored/posted field as an integer, clamped. Non-scalar in, $min out. */
function home_int(mixed $value, int $min = 0, int $max = PHP_INT_MAX): int
{
    $n = is_scalar($value) ? (int) $value : 0;
    return max($min, min($max, $n));
}

/**
 * How many rows any of the three repeaters may hold.
 * admin/settings.php declares this as each panel's `max` (and its JS disables
 * the Add button at it), but the JS was the ONLY enforcement — a crafted POST of
 * 40 focus-area cards was accepted and all 40 rendered. The encoders and the
 * readers both clamp to it now, so neither a crafted save nor already-corrupt
 * storage can put more than this on the page.
 */
const HOME_ROWS_MAX = 8;

/* =============================================================================
 |  1. MISSION FOCUS SPLIT  —  setting `home_focus_split`
 |  Stored as one "Label|percent" line per bar. Percent is how effort is
 |  distributed across the work, so it is the admin's claim to make, never ours.
 |===========================================================================*/

/** The bars shown until an admin saves their own. */
function home_focus_split_defaults(): array
{
    return [
        ['label' => 'Education',             'pct' => 85],
        ['label' => 'Community Development', 'pct' => 72],
        ['label' => 'Digital Learning',      'pct' => 64],
        ['label' => 'Women Empowerment',     'pct' => 58],
    ];
}

/**
 * The focus bars as [['label' => string, 'pct' => int 0-100], ...].
 * Falls back to home_focus_split_defaults() while the setting is empty.
 */
function home_focus_split(): array
{
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) get_setting('home_focus_split', '')) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line, 2));
        // The encoder drops a label-less row; the reader has to agree, or stored
        // data the editor never produced renders a filled bar with no label — a
        // value of "|90" gave a 90% track labelled nothing, and "Education\n
        // Health" (no pipes, reachable by leaving Percent blank) gave two bars
        // at 0%. A bar with no label makes no claim, so it is not a bar.
        if ($parts[0] === '') {
            continue;
        }
        $rows[] = ['label' => $parts[0], 'pct' => home_int($parts[1] ?? 0, 0, 100)];
        if (count($rows) >= HOME_ROWS_MAX) {
            break;
        }
    }
    return $rows ?: home_focus_split_defaults();
}

/**
 * Serialise editor rows back into the stored line format.
 * `|` is the field separator, so it is stripped from labels rather than
 * silently splitting a label into a bogus percentage.
 */
function home_focus_split_encode(array $rows): string
{
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = str_replace('|', ' ', home_text($row['label'] ?? ''));
        $label = trim(preg_replace('/\s+/', ' ', $label) ?? '');
        if ($label === '') {
            continue;
        }
        $out[] = $label . '|' . home_int($row['pct'] ?? 0, 0, 100);
        if (count($out) >= HOME_ROWS_MAX) {
            break;
        }
    }
    return implode("\n", $out);
}

/* =============================================================================
 |  2. FOCUS AREAS  —  setting `home_focus_areas` (JSON)
 |===========================================================================*/

/**
 * The five areas of work shown until an admin saves their own.
 * `image` is a path under uploads/ and may be blank: a blank image means the
 * icon tile carries the card on its own, which is why no placeholder
 * photograph is invented for the fifth card.
 */
function home_focus_areas_defaults(): array
{
    return [
        ['icon' => 'book-open',  'label' => 'Education',   'title' => 'Education Empowerment',
         'text'  => 'Schooling support, scholarships and learning materials that keep children in classrooms.',
         'image' => 'programs/students-classroom-learning.webp', 'url' => 'programs'],
        ['icon' => 'rocket',     'label' => 'Youth',       'title' => 'Youth Empowerment',
         'text'  => 'Skills, mentorship and opportunity so young people can build the future they choose.',
         'image' => 'programs/women-handloom-skills.webp', 'url' => 'skill-development'],
        ['icon' => 'user-round', 'label' => 'Women',       'title' => 'Women Empowerment',
         'text'  => 'Training, self-help groups and livelihood support that put income in women\'s hands.',
         'image' => 'programs/women-farmers-livelihood.webp', 'url' => 'programs'],
        ['icon' => 'home',       'label' => 'Rural',       'title' => 'Rural Development',
         'text'  => 'Clean water, sanitation and infrastructure built alongside the communities that use them.',
         'image' => 'programs/village-handpump-clean-water.webp', 'url' => 'programs'],
        ['icon' => 'sprout',     'label' => 'Environment', 'title' => 'Environment Protection',
         'text'  => 'Tree planting, awareness drives and climate-resilient practices rooted in local need.',
         'image' => '', 'url' => 'campaigns'],
    ];
}

/** One focus-area card with every key present, so the view never has to guess. */
function home_focus_area_row(array $row): array
{
    return [
        'icon'  => home_text($row['icon']  ?? '') ?: 'sparkles',
        'label' => home_text($row['label'] ?? ''),
        'title' => home_text($row['title'] ?? ''),
        'text'  => home_text($row['text']  ?? ''),
        // Paths keep their markup characters untouched (they have none to lose),
        // but still need the non-scalar guard: these two were bare
        // `trim((string) …)` casts, so an array here warned and stored "Array"
        // as an image path or a link target.
        'image' => home_raw($row['image'] ?? ''),
        'url'   => home_raw($row['url']   ?? ''),
    ];
}

/** The focus-area cards, falling back to home_focus_areas_defaults(). */
function home_focus_areas(): array
{
    $rows = [];
    foreach (json_column((string) get_setting('home_focus_areas', '')) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $row = home_focus_area_row($row);
        if ($row['title'] !== '') {           // a card with no title is not a card
            $rows[] = $row;
        }
        if (count($rows) >= HOME_ROWS_MAX) {
            break;
        }
    }
    return $rows ?: array_map('home_focus_area_row', home_focus_areas_defaults());
}

/** Serialise editor rows back to the stored JSON. */
function home_focus_areas_encode(array $rows): string
{
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $row = home_focus_area_row($row);
        if ($row['title'] === '') {
            continue;
        }
        $out[] = $row;
        if (count($out) >= HOME_ROWS_MAX) {
            break;
        }
    }
    return $out ? (string) json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
}

/* =============================================================================
 |  3. DONATION TIERS  —  setting `home_donation_tiers` (JSON)
 |===========================================================================*/

/** The amount tiers shown until an admin saves their own. */
function home_donation_tiers_defaults(): array
{
    return [
        ['amount' => 500,  'icon' => 'book-open',   'title' => 'School kit',  'impact' => 'Books, stationery and a learning kit for a child for a month.'],
        ['amount' => 1000, 'icon' => 'soup',        'title' => 'Nutrition',   'impact' => 'A week of nutritious meals for a family facing hardship.'],
        ['amount' => 2500, 'icon' => 'stethoscope', 'title' => 'Health camp', 'impact' => 'A check-up and essential medicines for ten patients.'],
        ['amount' => 5000, 'icon' => 'scissors',    'title' => 'Livelihood',  'impact' => 'A month of vocational skill training for a young woman.'],
    ];
}

/** One tier with every key present and the amount as a positive integer. */
function home_donation_tier_row(array $row): array
{
    return [
        'amount' => home_int($row['amount'] ?? 0),
        'icon'   => home_text($row['icon'] ?? '') ?: 'heart',
        'title'  => home_text($row['title'] ?? ''),
        'impact' => home_text($row['impact'] ?? ''),
    ];
}

/** The donation tiers, falling back to home_donation_tiers_defaults(). */
function home_donation_tiers(): array
{
    $rows = [];
    foreach (json_column((string) get_setting('home_donation_tiers', '')) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $row = home_donation_tier_row($row);
        if ($row['amount'] > 0) {             // a tier with no amount has nothing to ask for
            $rows[] = $row;
        }
        if (count($rows) >= HOME_ROWS_MAX) {
            break;
        }
    }
    return $rows ?: home_donation_tiers_defaults();
}

/** Serialise editor rows back to the stored JSON. */
function home_donation_tiers_encode(array $rows): string
{
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $row = home_donation_tier_row($row);
        if ($row['amount'] <= 0) {
            continue;
        }
        $out[] = $row;
        if (count($out) >= HOME_ROWS_MAX) {
            break;
        }
    }
    return $out ? (string) json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
}

/* =============================================================================
 |  4. SECTION REGISTRY
 |-----------------------------------------------------------------------------
 |  Every section the homepage renders today, in page order. Keys are the
 |  settings prefix:  show_<key>, <key>_heading, <key>_subtitle.
 |
 |  The seven legacy keys (counters, programs, events, blogs, testimonials,
 |  gallery, partners) are kept so values already saved against them still
 |  apply — but their default heading/subtitle now match the copy the page
 |  actually renders, and a section only declares a heading/subtitle field where
 |  the page has somewhere to put it. Sections whose words come from a record
 |  (the featured story, the video, the hero) declare neither.
 |===========================================================================*/
function home_sections(): array
{
    return [
        'hero' => [
            'label'  => 'Hero slider',
            'icon'   => 'images',
            'desc'   => 'The opening panel. Always shown — it is the page\'s first impression and its H1.',
            'toggle' => false,
            'links'  => ['hero-slides' => 'Edit slides'],
        ],
        'counters' => [
            'label'    => 'Impact counters',
            'icon'     => 'bar-chart-3',
            'desc'     => 'The four counting numbers and the trust strip under the hero.',
            'heading'  => 'Our impact in numbers',
            'hint'     => 'Read by screen readers; the visible words are the numbers themselves. The figures come from the achievements records — there is no screen for them yet, so the page shows its built-in set until they are seeded.',
        ],
        'mission' => [
            'label' => 'Mission & vision',
            'icon'  => 'target',
            'desc'  => 'The editorial split: statement, focus bars, and the mission / vision pair.',
            'links' => ['settings' => 'Edit the copy (Settings → Homepage)'],
        ],
        'focus' => [
            'label'    => 'Focus areas',
            'icon'     => 'layout-grid',
            'desc'     => 'The five "what we do" cards.',
            'heading'  => 'Five ways we create lasting change',
            'subtitle' => 'Every programme we run sits within one of these areas — designed with communities, measured openly, and built to outlast us.',
            'links'    => ['settings' => 'Edit the cards (Settings → Focus Areas)'],
        ],
        'featured_story' => [
            'label' => 'Featured story',
            'icon'  => 'book-open',
            'desc'  => 'The newest published post in a "Success Stories" or "Stories" category, with its own photograph. Hidden until one exists.',
            'links' => ['blogs' => 'Manage posts'],
        ],
        'video' => [
            'label' => 'Video banner',
            'icon'  => 'play-circle',
            'desc'  => 'The "watch our story" band. Its title comes from the video record.',
            'links' => ['videos' => 'Manage videos'],
        ],
        'programs' => [
            'label'    => 'Programs',
            'icon'     => 'sparkles',
            'desc'     => 'Up to six programme cards, featured first.',
            'heading'  => 'Our Programs',
            'subtitle' => 'Focused initiatives designed to create real, measurable change in the communities we serve.',
            'links'    => ['programs' => 'Manage programmes'],
        ],
        'events' => [
            'label'   => 'Upcoming events',
            'icon'    => 'calendar-days',
            'desc'    => 'The next three published events. Shows an invitation to watch this space when the calendar is empty.',
            'heading' => 'Upcoming Events',
            'links'   => ['events' => 'Manage events'],
        ],
        'donation_tiers' => [
            'label'    => 'Donation tiers',
            'icon'     => 'indian-rupee',
            'desc'     => 'The amount tiers and what each one funds.',
            'heading'  => 'Choose what your gift becomes',
            'subtitle' => 'Every amount is tied to something real, and every rupee is tracked from donation to delivery.',
            'links'    => ['settings' => 'Edit the tiers (Settings → Donation Tiers)'],
        ],
        'cta' => [
            'label' => 'Donate / volunteer bands',
            'icon'  => 'heart-handshake',
            'desc'  => 'The two closing invitations — give, and give your time.',
        ],
        'testimonials' => [
            'label'    => 'Testimonials',
            'icon'     => 'message-square',
            'desc'     => 'The quotes carousel.',
            'heading'  => 'What People Say',
            'subtitle' => '',
            'hint'     => 'The subtitle is optional here — leave it empty and none is shown.',
            'links'    => ['testimonials' => 'Manage testimonials'],
        ],
        'blogs' => [
            'label'   => 'Blog & stories',
            'icon'    => 'newspaper',
            'desc'    => 'The three most recent published posts.',
            'heading' => 'Latest Stories & News',
            'links'   => ['blogs' => 'Manage posts'],
        ],
        'gallery' => [
            'label'    => 'Field gallery',
            'icon'     => 'image',
            'desc'     => 'The masonry wall of album covers.',
            'heading'  => 'From the Field',
            'subtitle' => 'Classrooms, camps and community days — photographed where the work happens.',
            'links'    => ['gallery-albums' => 'Manage albums', 'gallery-media' => 'Manage photos'],
        ],
        'partners' => [
            'label'    => 'Partners & sponsors',
            'icon'     => 'handshake',
            'desc'     => 'The supporter wall. Becomes an invitation to partner while the list is empty.',
            'heading'  => 'Our Partners & Sponsors',
            'subtitle' => 'Organisations who stand with us — funding, mentoring and delivering change alongside our teams.',
            'links'    => ['partners' => 'Manage partners', 'sponsors' => 'Manage sponsors'],
        ],
    ];
}

/** One registry entry (empty array for an unknown key). */
function home_section(string $key): array
{
    return home_sections()[$key] ?? [];
}

/** Is a section switched on? Unset means visible, so a fresh install is whole. */
function home_section_visible(string $key): bool
{
    $meta = home_section($key);
    if (($meta['toggle'] ?? true) === false) {
        return true;
    }
    return (string) get_setting('show_' . $key, '1') !== '0';
}

/**
 * A section's heading / subtitle: the admin's words if they saved any, else the
 * registry default (which is the copy the page shipped with). get_setting()
 * treats '' as absent, so clearing the field restores the default.
 */
function home_heading(string $key): string
{
    return (string) get_setting($key . '_heading', home_section($key)['heading'] ?? '');
}

function home_subtitle(string $key): string
{
    return (string) get_setting($key . '_subtitle', home_section($key)['subtitle'] ?? '');
}

/* =============================================================================
 |  5. EMPTY STATES
 |-----------------------------------------------------------------------------
 |  Five sections used to vanish without a word on unseeded data. Each now gets
 |  an honest answer instead:
 |    - audience 'public' where a visitor gains something from being told (the
 |      events calendar is genuinely empty; new stories are genuinely coming),
 |    - audience 'editor' where they do not (a video band with no video, a photo
 |      wall with no photographs) — the panel then renders for signed-in staff
 |      only, so the person who can fix it is told and visitors see nothing.
 |  Nothing here invents a number, a quote or a supporter.
 |===========================================================================*/

/**
 * Styles for the empty-state panel, emitted once on first use.
 * Colour pairs, all against the ivory field (#FEFEF1):
 *   title   #151818 on #FEFEF1 → 17.6:1
 *   body    #4B6754 on #FEFEF1 →  6.1:1
 *   tag     #0B4E3D on #FFE987 →  7.95:1
 */
function home_empty_css(): string
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;

    return <<<'CSS'
<style>
.hp-empty {
    display: flex; align-items: flex-start; gap: clamp(.9rem, 2vw, 1.35rem);
    padding: clamp(1.25rem, 2.6vw, 1.9rem);
    background: var(--ivory, #FEFEF1);
    border: 1.5px dashed color-mix(in srgb, var(--primary, #0B4E3D) 38%, transparent);
    border-radius: var(--r-lg, 20px);
}
.hp-empty-ico {
    flex: none; display: grid; place-items: center; width: 46px; height: 46px;
    border-radius: var(--r-md, 14px);
    background: var(--yellow, #FFE987); color: var(--primary, #0B4E3D);
}
.hp-empty-ico svg { width: 22px; height: 22px; }
.hp-empty-body { min-width: 0; }
.hp-empty-title { margin: 0 0 .4rem; font-size: 1.1rem; color: var(--text, #151818); }
.hp-empty-text { margin: 0; color: var(--muted, #4B6754); line-height: 1.7; max-width: 58ch; }
.hp-empty-actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1.15rem; }
.hp-tag {
    display: inline-flex; align-items: center; gap: .4rem; margin-bottom: .6rem;
    padding: .2rem .65rem; border-radius: var(--r-pill, 999px);
    background: var(--yellow, #FFE987); color: var(--primary, #0B4E3D);
    font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
}
.hp-tag svg { width: 13px; height: 13px; }
@media (max-width: 520px) {
    .hp-empty { flex-direction: column; }
    .hp-empty-actions .btn { flex: 1 1 auto; justify-content: center; }
}
</style>
CSS;
}

/**
 * Render an empty-state panel.
 *   icon     lucide slug for the tile
 *   title    what is missing, in plain words
 *   text     what will appear here once it exists
 *   audience 'public' (default) | 'editor'  — see the note above
 *   actions  [label => url]  visitor-facing links
 *   manage   [label => admin route]  shown to signed-in staff only
 */
function home_empty(array $o): string
{
    $editorOnly = ($o['audience'] ?? 'public') === 'editor';
    $isStaff    = is_logged_in();
    if ($editorOnly && !$isStaff) {
        return '';
    }

    $html = home_empty_css() . '<div class="hp-empty reveal">'
        . '<span class="hp-empty-ico" aria-hidden="true">' . lucide((string) ($o['icon'] ?? 'info')) . '</span>'
        . '<div class="hp-empty-body">';

    if ($editorOnly) {
        // Says out loud that visitors are not seeing this panel.
        $html .= '<span class="hp-tag">' . lucide('eye-off') . 'Only you can see this</span>';
    }

    $html .= '<h3 class="hp-empty-title">' . e((string) ($o['title'] ?? '')) . '</h3>'
        . '<p class="hp-empty-text">' . e((string) ($o['text'] ?? '')) . '</p>';

    $actions = '';
    foreach ((array) ($o['actions'] ?? []) as $label => $href) {
        $actions .= '<a class="btn btn-outline btn-sm" href="' . e(url((string) $href)) . '">' . e((string) $label) . '</a>';
    }
    if ($isStaff) {
        foreach ((array) ($o['manage'] ?? []) as $label => $route) {
            $actions .= '<a class="btn btn-secondary btn-sm" href="' . e(admin_url((string) $route)) . '">'
                . lucide('settings') . e((string) $label) . '</a>';
        }
    }
    if ($actions !== '') {
        $html .= '<div class="hp-empty-actions">' . $actions . '</div>';
    }

    return $html . '</div></div>';
}
