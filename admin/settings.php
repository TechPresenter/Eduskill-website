<?php
/**
 * =============================================================================
 *  Admin — Website Settings (key/value editor).  SPECIAL module.
 *  The master site-config page. Edits the `settings` table via
 *  get_setting()/set_setting(), grouped into panels (General, Contact,
 *  Homepage, Legal/Org, Social & Footer). Handles image uploads for the
 *  site logo + favicon (replacing and deleting the previous file).
 *  On first load any missing core keys are seeded with sensible defaults so
 *  the form is never empty.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/homepage.php';   // homepage content accessors + encoders
require_admin();

/* -----------------------------------------------------------------------------
 |  Field map — the single source of truth for this page.
 |  Each panel: label => [group, fields].  Each field:
 |    type  = storage type stored in settings.type (text|textarea|email|url|image|color)
 |    input = HTML input type for scalar fields (text|email|url|date|color)
 |    raw   = true to store the value un-sanitised (needed for HTML embeds)
 |    hint  = optional helper text under the field
 |    default = value seeded on first load when the key is missing
 |
 |  type 'rows' is the repeatable-item editor (add / remove / reorder) added for
 |  the homepage focus bars, focus-area cards and donation tiers — the three
 |  pieces of homepage content that index.php read but no admin screen offered.
 |  It takes four extra keys and changes nothing else on the page:
 |    source = callable returning the CURRENT rows (the saved ones, or the
 |             built-in set while the setting is empty)
 |    encode = callable turning posted rows back into the stored string. The
 |             storage format lives in includes/homepage.php, beside the code
 |             that reads it, so both sides can never disagree.
 |    sub    = the fields of one row
 |    store  = value written to settings.type ('json' / 'textarea')
 |--------------------------------------------------------------------------- */
$panels = [
    'General' => [
        'group'  => 'general',
        'fields' => [
            'site_name'        => ['type' => 'text',     'input' => 'text',  'default' => 'EDUSKILL INDIA FOUNDATION', 'label' => 'Site Name'],
            'site_tagline'     => ['type' => 'text',     'input' => 'text',  'default' => 'Empowering Communities • Spreading Hope • Creating Change', 'label' => 'Site Tagline'],
            'site_description'  => ['type' => 'textarea', 'default' => 'EDUSKILL INDIA FOUNDATION is a registered non-profit in Patna, Bihar working to empower communities through education, healthcare, skill development and relief.', 'label' => 'Site Description', 'hint' => 'Used as the default meta description.'],
            'site_keywords'    => ['type' => 'text',     'input' => 'text',  'default' => 'NGO Patna, Bihar NGO, charity India, donation, volunteer', 'label' => 'Site Keywords', 'hint' => 'Comma-separated keywords.'],
            'site_logo'        => ['type' => 'image',    'default' => '',    'label' => 'Site Logo', 'hint' => 'PNG/SVG recommended. Leave empty to keep the current logo.'],
            'favicon'          => ['type' => 'image',    'default' => '',    'label' => 'Favicon', 'hint' => 'Square icon (e.g. 64×64). Leave empty to keep the current one.'],
        ],
    ],
    'Contact' => [
        'group'  => 'contact',
        'fields' => [
            'contact_email'   => ['type' => 'email',    'input' => 'email', 'default' => 'info@eduskillindia.org', 'label' => 'Contact Email'],
            'contact_phone'   => ['type' => 'text',     'input' => 'text',  'default' => '+91 74919 32148', 'label' => 'Contact Phone'],
            'contact_address' => ['type' => 'textarea', 'default' => 'Patna, Bihar, India 840007', 'label' => 'Contact Address'],
            'whatsapp_number' => ['type' => 'text',     'input' => 'text',  'default' => '917491932148', 'label' => 'WhatsApp Number', 'hint' => 'Digits only, with country code (no + or spaces).'],
            'google_map'      => ['type' => 'textarea', 'default' => '', 'raw' => true, 'label' => 'Google Map Embed', 'hint' => 'Paste the full Google Maps <iframe> embed code or a map URL.'],
        ],
    ],
    /* Mail delivery had no screen at all: send_mail() reads these keys through
       get_setting(), but nothing wrote them, so the only way to point the site
       at a mailbox was to edit config.php on the server. Everything the sender
       needs now lives here. */
    'Mail & Delivery' => [
        'group'  => 'mail',
        'fields' => [
            'mail_from_name'  => ['type' => 'text',  'input' => 'text',  'default' => 'EDUSKILL INDIA FOUNDATION', 'label' => 'From Name',
                                  'hint' => 'The name recipients see in their inbox.'],
            'mail_from_email' => ['type' => 'email', 'input' => 'email', 'default' => 'info@eduskillindia.org', 'label' => 'From Address',
                                  'hint' => 'Every outgoing email is sent from this address.'],
            'mail_reply_to'   => ['type' => 'email', 'input' => 'email', 'default' => 'info@eduskillindia.org', 'label' => 'Reply-To Address',
                                  'hint' => 'Used unless a message sets its own — enquiry and application alerts still reply to the person who wrote in.'],
            'use_smtp'        => ['type' => 'text',  'input' => 'text',  'default' => '0', 'label' => 'Use SMTP',
                                  'hint' => '1 to send through the mailbox below, 0 to use the server\'s own mail command. On a plain VPS with no mail server installed, 0 means nothing is delivered.'],
            'smtp_host'       => ['type' => 'text',  'input' => 'text',  'default' => '', 'label' => 'SMTP Host',
                                  'hint' => 'e.g. smtp.hostinger.com, smtp.gmail.com, smtp.zoho.in'],
            'smtp_port'       => ['type' => 'text',  'input' => 'number','default' => '587', 'label' => 'SMTP Port',
                                  'hint' => '587 for TLS, 465 for SSL.'],
            'smtp_secure'     => ['type' => 'text',  'input' => 'text',  'default' => 'tls', 'label' => 'Encryption',
                                  'hint' => 'tls or ssl.'],
            'smtp_user'       => ['type' => 'text',  'input' => 'text',  'default' => '', 'label' => 'SMTP Username',
                                  'hint' => 'Usually the full mailbox address.'],
            'smtp_pass'       => ['type' => 'text',  'input' => 'text',  'default' => '', 'label' => 'SMTP Password',
                                  'hint' => 'Stored in the database. Use a mailbox-specific app password where the provider offers one.'],
        ],
    ],
    'Homepage' => [
        'group'  => 'homepage',
        'fields' => [
            'home_about_title' => ['type' => 'text',     'input' => 'text', 'default' => 'A movement for dignity, hope and opportunity', 'label' => 'Home About Title'],
            'home_about_text'  => ['type' => 'textarea', 'default' => 'EDUSKILL INDIA FOUNDATION is a registered non-profit based in Patna, Bihar. We work hand-in-hand with communities to deliver education, healthcare, skill development, and emergency relief.', 'label' => 'Home About Text'],
            'mission_short'    => ['type' => 'textarea', 'default' => 'To empower underserved communities by providing access to quality education, healthcare, and sustainable livelihoods.', 'label' => 'Mission (short)'],
            'vision_short'     => ['type' => 'textarea', 'default' => 'An equitable society where every individual has the opportunity to live with dignity and reach their full potential.', 'label' => 'Vision (short)'],
            'home_focus_split' => [
                'type'    => 'rows',
                'store'   => 'textarea',
                'label'   => 'Focus split bars',
                'hint'    => 'The progress bars beside the mission statement. The percentages are a claim about where your effort goes, so they are yours to set — nothing is calculated. Remove every bar to restore the built-in set.',
                'item'    => 'bar',
                // One source of truth with the server-side clamp in the
                // encoders — the JS that disables the Add button reads this via
                // data-max, and includes/homepage.php enforces the same number
                // on save and on render.
                'max'     => HOME_ROWS_MAX,
                'source'  => 'home_focus_split',
                'encode'  => 'home_focus_split_encode',
                'default' => home_focus_split_encode(home_focus_split_defaults()),
                'sub'     => [
                    'label' => ['label' => 'Label', 'input' => 'text', 'placeholder' => 'Education', 'maxlength' => 80, 'grow' => 2],
                    'pct'   => ['label' => 'Percent', 'input' => 'number', 'min' => 0, 'max' => 100, 'placeholder' => '0'],
                ],
            ],
        ],
    ],
    'Focus Areas' => [
        'group'  => 'homepage',
        'fields' => [
            'home_focus_areas' => [
                'type'    => 'rows',
                'store'   => 'json',
                'label'   => 'Focus area cards',
                'hint'    => 'The “what we do” cards. The first two cards are the wide ones on the homepage, so put your strongest two first. Remove every card to restore the built-in set.',
                'item'    => 'card',
                'max'     => HOME_ROWS_MAX,
                'source'  => 'home_focus_areas',
                'encode'  => 'home_focus_areas_encode',
                'default' => home_focus_areas_encode(home_focus_areas_defaults()),
                'title'   => 'title',
                'sub'     => [
                    'title' => ['label' => 'Card title', 'input' => 'text', 'placeholder' => 'Education Empowerment', 'maxlength' => 120, 'grow' => 2,
                                'hint' => 'Required — a card with no title is dropped.'],
                    'label' => ['label' => 'Small label above it', 'input' => 'text', 'placeholder' => 'Education', 'maxlength' => 40],
                    'icon'  => ['label' => 'Icon', 'input' => 'text', 'placeholder' => 'book-open', 'maxlength' => 40,
                                'hint' => 'A lucide.dev icon name, e.g. book-open, sprout, rocket.'],
                    'url'   => ['label' => 'Links to', 'input' => 'text', 'placeholder' => 'programs', 'maxlength' => 190,
                                'hint' => 'A page on this site, e.g. programs or skill-development.'],
                    'image' => ['label' => 'Photograph', 'input' => 'text', 'placeholder' => 'programs/students-classroom-learning.webp',
                                'maxlength' => 255, 'preview' => true, 'grow' => 2,
                                'hint' => 'Path under uploads/, or leave empty — a card with no photograph is carried by its icon tile.'],
                    'text'  => ['label' => 'Description', 'input' => 'textarea', 'placeholder' => 'One or two sentences on what this area of work does.', 'maxlength' => 320, 'wide' => true],
                ],
            ],
        ],
    ],
    'Donation Tiers' => [
        'group'  => 'homepage',
        'fields' => [
            'home_donation_tiers' => [
                'type'    => 'rows',
                'store'   => 'json',
                'label'   => 'Donation tiers',
                'hint'    => 'Each amount on the homepage and the tangible thing it funds. Only promise what a gift really buys — this is the page a donor decides on. Remove every tier to restore the built-in set.',
                'item'    => 'tier',
                'max'     => HOME_ROWS_MAX,
                'source'  => 'home_donation_tiers',
                'encode'  => 'home_donation_tiers_encode',
                'default' => home_donation_tiers_encode(home_donation_tiers_defaults()),
                'title'   => 'title',
                'sub'     => [
                    'amount' => ['label' => 'Amount (₹)', 'input' => 'number', 'min' => 1, 'max' => 10000000, 'placeholder' => '500',
                                 'hint' => 'Required — a tier with no amount is dropped.'],
                    'title'  => ['label' => 'What it is called', 'input' => 'text', 'placeholder' => 'School kit', 'maxlength' => 80, 'grow' => 2],
                    'icon'   => ['label' => 'Icon', 'input' => 'text', 'placeholder' => 'book-open', 'maxlength' => 40,
                                 'hint' => 'A lucide.dev icon name.'],
                    'impact' => ['label' => 'What it funds', 'input' => 'textarea', 'placeholder' => 'Books, stationery and a learning kit for a child for a month.', 'maxlength' => 320, 'wide' => true],
                ],
            ],
        ],
    ],
    'Legal / Organisation' => [
        'group'  => 'org',
        'fields' => [
            'cin'                => ['type' => 'text', 'input' => 'text', 'default' => 'U88900BR2026NPL081597', 'label' => 'CIN'],
            'pan'                => ['type' => 'text', 'input' => 'text', 'default' => '', 'label' => 'PAN'],
            'tan'                => ['type' => 'text', 'input' => 'text', 'default' => '', 'label' => 'TAN'],
            'incorporation_date' => ['type' => 'text', 'input' => 'date', 'default' => '2025-01-15', 'label' => 'Incorporation Date'],
        ],
    ],
    'Social & Footer' => [
        'group'  => 'social',
        'fields' => [
            'social_facebook'  => ['type' => 'url', 'input' => 'url', 'default' => '', 'label' => 'Facebook URL'],
            'social_twitter'   => ['type' => 'url', 'input' => 'url', 'default' => '', 'label' => 'Twitter / X URL'],
            'social_instagram' => ['type' => 'url', 'input' => 'url', 'default' => '', 'label' => 'Instagram URL'],
            'social_youtube'   => ['type' => 'url', 'input' => 'url', 'default' => '', 'label' => 'YouTube URL'],
            'social_linkedin'  => ['type' => 'url', 'input' => 'url', 'default' => '', 'label' => 'LinkedIn URL'],
            'footer_about'     => ['type' => 'textarea', 'default' => 'EDUSKILL INDIA FOUNDATION works across Bihar to empower communities through education, healthcare, skill development and relief.', 'label' => 'Footer About Text'],
        ],
    ],

    'FAQ Section' => [
        'group'  => 'faq',
        'fields' => [
            'faq_theme'       => ['type' => 'select', 'default' => 'ngo', 'label' => 'Colour theme',
                'options' => ['ngo' => 'NGO (green / blue / orange)', 'blue-purple' => 'Blue → Purple', 'orange-pink' => 'Orange → Pink',
                              'green-cyan' => 'Green → Cyan', 'indigo-violet' => 'Indigo → Violet', 'red-orange' => 'Red → Orange',
                              'teal-emerald' => 'Teal → Emerald', 'rainbow' => 'Rainbow', 'corporate' => 'Corporate', 'minimal' => 'Minimal White']],
            'faq_animation'   => ['type' => 'select', 'default' => 'slide', 'label' => 'Accordion animation',
                'options' => ['slide' => 'Smooth Slide Down', 'fade-slide' => 'Fade In + Slide', 'scale' => 'Scale Expand',
                              'bounce' => 'Bounce Open', 'elastic' => 'Elastic Expand', 'flip' => 'Flip Down', 'rotate' => 'Rotate Reveal',
                              'zoom' => 'Zoom In', 'fold' => '3D Fold', 'curtain' => 'Curtain Reveal', 'morph' => 'Morph Expand',
                              'liquid' => 'Liquid', 'spring' => 'Spring Motion', 'ripple' => 'Ripple Expansion']],
            'faq_hover'       => ['type' => 'select', 'default' => 'lift', 'label' => 'Hover effect',
                'options' => ['lift' => 'Lift on Hover', 'gradient-border' => 'Gradient Border Glow', 'neon' => 'Neon Glow',
                              'gradient-bg' => 'Animated Gradient Background', 'float' => 'Floating Card', 'glass' => 'Glass Reflection',
                              'shine' => 'Shine Sweep', 'magnetic' => 'Magnetic Hover', 'border-draw' => 'Border Animation',
                              'pulse' => 'Soft Pulse', 'shadow' => 'Shadow Expansion', 'color' => 'Colour Transition',
                              'tilt' => 'Tilt Effect', 'ring' => 'Glow Ring']],
            'faq_icon'        => ['type' => 'select', 'default' => 'plus-minus', 'label' => 'Icon animation',
                'options' => ['plus-minus' => 'Plus → Minus Rotation', 'arrow' => 'Arrow Rotation', 'chevron' => 'Chevron Flip',
                              'pulse' => 'Pulse Icon', 'bounce' => 'Bounce Icon', 'wiggle' => 'Wiggle', 'morph' => 'Morphing',
                              'circular' => 'Circular Rotation', 'glow' => 'Glow Animation', 'gradient' => 'Gradient Fill']],
            'faq_border'      => ['type' => 'select', 'default' => 'gradient', 'label' => 'Border effect',
                'options' => ['gradient' => 'Gradient Border', 'rainbow' => 'Rainbow Border', 'neon' => 'Neon Border',
                              'dashed' => 'Animated Dashed', 'pulse' => 'Pulse Border', 'shimmer' => 'Shimmer Border',
                              'glow' => 'Glow Border', 'liquid' => 'Liquid Border']],
            'faq_background'  => ['type' => 'select', 'default' => 'mesh', 'label' => 'Animated background',
                'options' => ['mesh' => 'Mesh Gradient', 'none' => 'None (flat)', 'aurora' => 'Aurora Effect', 'blobs' => 'Floating Blurred Circles',
                              'particles' => 'Floating Particles', 'waves' => 'Waves', 'rays' => 'Soft Light Rays', 'sparkles' => 'Sparkles']],
            'faq_duration'    => ['type' => 'number', 'input' => 'number', 'default' => '380', 'label' => 'Animation duration (ms)', 'hint' => '80–1200. Lower is snappier.'],
            'faq_radius'      => ['type' => 'number', 'input' => 'number', 'default' => '20',  'label' => 'Corner radius (px)', 'hint' => '0–40.'],
            'faq_shadow'      => ['type' => 'number', 'input' => 'number', 'default' => '3',   'label' => 'Shadow intensity', 'hint' => '0 (flat) – 5 (deep).'],
            'faq_glow'        => ['type' => 'number', 'input' => 'number', 'default' => '2',   'label' => 'Glow intensity', 'hint' => '0 (none) – 5 (strong), applied to the open item.'],
            'faq_spacing'     => ['type' => 'number', 'input' => 'number', 'default' => '12',  'label' => 'Gap between items (px)', 'hint' => '0–40.'],
            'faq_font_size'   => ['type' => 'number', 'input' => 'number', 'default' => '16',  'label' => 'Question font size (px)', 'hint' => '12–24.'],
            'faq_single_open' => ['type' => 'boolean', 'default' => '1', 'label' => 'Only one answer open at a time'],
            'faq_show_search' => ['type' => 'boolean', 'default' => '1', 'label' => 'Show the search box', 'hint' => 'Appears automatically once there are more than three questions.'],
            'faq_custom_css'  => ['type' => 'textarea', 'default' => '', 'raw' => true, 'label' => 'Custom CSS', 'hint' => 'Scope rules to .pfaq so they cannot leak into the rest of the site.'],
            'faq_custom_js'   => ['type' => 'textarea', 'default' => '', 'raw' => true, 'label' => 'Custom JavaScript', 'hint' => 'Injected on any page showing the FAQ section. Leave empty unless you need it.'],
        ],
    ],

    'Social Login' => [
        'group'  => 'oauth',
        'fields' => [
            'google_login_enabled'   => ['type' => 'boolean',  'default' => '0', 'label' => 'Enable Google sign-in', 'hint' => 'The Google button only appears on the login page once this is on AND both credentials below are filled in.'],
            'google_client_id'       => ['type' => 'text',     'input' => 'text', 'default' => '', 'label' => 'Google Client ID'],
            'google_client_secret'   => ['type' => 'text',     'input' => 'text', 'default' => '', 'label' => 'Google Client Secret'],
            'google_redirect_uri'    => ['type' => 'url',      'input' => 'url',  'default' => '', 'label' => 'Google Redirect URI', 'hint' => 'Leave blank to use the automatic URL shown below — only set this if the site sits behind a proxy on a different hostname.'],
            'facebook_login_enabled' => ['type' => 'boolean',  'default' => '0', 'label' => 'Enable Facebook sign-in', 'hint' => 'Requires the App ID and App Secret below.'],
            'facebook_app_id'        => ['type' => 'text',     'input' => 'text', 'default' => '', 'label' => 'Facebook App ID'],
            'facebook_app_secret'    => ['type' => 'text',     'input' => 'text', 'default' => '', 'label' => 'Facebook App Secret'],
            'facebook_redirect_uri'  => ['type' => 'url',      'input' => 'url',  'default' => '', 'label' => 'Facebook Redirect URI', 'hint' => 'Leave blank to use the automatic URL.'],
        ],
    ],

    'Maintenance' => [
        'group'  => 'maintenance',
        'fields' => [
            'maintenance_mode'            => ['type' => 'boolean',  'default' => '0', 'label' => 'Enable maintenance mode', 'hint' => 'Public pages answer 503 with the maintenance notice. The admin panel stays reachable, signed-in admins browse the site normally, and payment return URLs keep working so in-flight donations still settle.'],
            'maintenance_message'         => ['type' => 'textarea', 'default' => 'We\'re carrying out some scheduled maintenance to make things better. The site will be back shortly — thank you for your patience.', 'label' => 'Notice shown to visitors'],
            'maintenance_eta'             => ['type' => 'text',     'input' => 'text',   'default' => '', 'label' => 'Expected back (optional)', 'hint' => 'Free text, e.g. "today at 6:00 PM IST". Left empty, no ETA is shown.'],
            'maintenance_retry_minutes'   => ['type' => 'number',   'input' => 'number', 'default' => '30', 'label' => 'Retry-After (minutes)', 'hint' => 'Tells search engines how long the outage lasts so they do not de-index the site.'],
        ],
    ],
];

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    foreach ($panels as $panel) {
        foreach ($panel['fields'] as $key => $meta) {
            if ($meta['type'] === 'image') {
                continue; // images handled separately below
            }
            if ($meta['type'] === 'boolean') {
                // An unticked checkbox posts nothing at all, so it must be
                // normalised to an explicit '0' rather than left as ''.
                set_setting($key, post($key) === '1' ? '1' : '0', $panel['group'], 'boolean');
                continue;
            }
            if ($meta['type'] === 'rows') {
                // Repeatable items. Row ORDER is the order the browser posted
                // them, which is DOM order, so reordering in the editor needs no
                // index bookkeeping. encode() sanitises, clamps and drops empty
                // rows; an empty result stores '' and the page falls back to its
                // built-in set (see includes/homepage.php).
                $rows = post($key);
                $rows = is_array($rows) ? array_values($rows) : [];
                set_setting($key, ($meta['encode'])($rows), $panel['group'], $meta['store'] ?? 'textarea');
                continue;
            }
            if ($meta['type'] === 'select') {
                // Clamp to the declared options so a hand-crafted POST cannot
                // store a value the renderer has no styling for.
                $allowed = array_keys($meta['options'] ?? []);
                $picked  = (string) post($key, '');
                set_setting($key, in_array($picked, $allowed, true) ? $picked : (string) ($meta['default'] ?? ''), $panel['group'], 'text');
                continue;
            }
            $value = (string) post($key, '');
            $value = !empty($meta['raw']) ? $value : clean($value);
            set_setting($key, $value, $panel['group'], $meta['type']);
        }
    }

    // Image settings (logo + favicon): replace + delete old file on new upload.
    foreach ($panels as $panel) {
        foreach ($panel['fields'] as $key => $meta) {
            if ($meta['type'] !== 'image' || empty($_FILES[$key]['name'])) {
                continue;
            }
            $up = upload_image($_FILES[$key], 'images');
            if (!$up['success']) {
                set_flash('error', $up['error']);
                redirect('/admin/settings');
            }
            $old = get_setting($key);
            set_setting($key, $up['path'], $panel['group'], 'image');
            if ($old) {
                delete_upload($old);
            }
        }
    }

    log_activity('update', 'settings', 'Updated website settings');
    set_flash('success', 'Settings saved successfully.');
    redirect('/admin/settings');
}

/* -------------------------------------------------------------- SEED missing keys */
$existing = all_settings();
foreach ($panels as $panel) {
    foreach ($panel['fields'] as $key => $meta) {
        if (!array_key_exists($key, $existing)) {
            // 'rows' fields seed the built-in set, so the editor opens on the
            // content the homepage is actually showing rather than an empty list.
            set_setting($key, (string) ($meta['default'] ?? ''), $panel['group'], $meta['store'] ?? $meta['type']);
        }
    }
}

/* -------------------------------------------------------------- VIEW */
$page_title = 'Website Settings';

/* Presentational metadata per panel — purely cosmetic, does not touch the
   field map, the save handler or any stored value. `hue` drives the tab's
   colour coding: one accent per category, applied via a CSS custom property so
   the same rules serve every tab. */
$panelMeta = [
    'General'              => ['icon' => 'sliders-horizontal',     'desc' => 'Site identity, branding and SEO basics',   'hue' => '#2563eb'], // blue
    'Contact'              => ['icon' => 'phone',                  'desc' => 'How supporters reach you',                 'hue' => '#16a34a'], // green
    'Homepage'             => ['icon' => 'layout-dashboard',       'desc' => 'Headline content on the front page',       'hue' => '#7c3aed'], // purple
    'Focus Areas'          => ['icon' => 'layout-grid',            'desc' => 'The “what we do” cards on the homepage',   'hue' => '#0f766e'], // teal
    'Donation Tiers'       => ['icon' => 'indian-rupee',           'desc' => 'Amounts and what each gift becomes',       'hue' => '#b45309'], // amber
    'Legal / Organisation' => ['icon' => 'landmark',               'desc' => 'Registration and compliance details',      'hue' => '#ea580c'], // orange
    'Social & Footer'      => ['icon' => 'share-2',                'desc' => 'Social profiles and footer copy',          'hue' => '#db2777'], // pink
    'FAQ Section'          => ['icon' => 'message-circle-question','desc' => 'Theme, animation and hover styling',       'hue' => '#0891b2'], // cyan
    'Social Login'         => ['icon' => 'key-round',              'desc' => 'Google / Facebook OAuth credentials',      'hue' => '#8b5cf6'], // violet
    'Maintenance'          => ['icon' => 'construction',           'desc' => 'Temporarily take the public site offline', 'hue' => '#475569'], // slate
];
$panelKeys = array_keys($panels);

/* -----------------------------------------------------------------------------
 |  One row of a 'rows' field.
 |  $index is the row's integer position, or the literal '__IDX__' placeholder
 |  when the row is being rendered into the <template> the Add button clones.
 |  Inputs are wrapped in their <label> rather than paired by id, so reordering
 |  and inserting rows never has to rewrite an id/for pair — only the [n] in the
 |  field name, which is what carries the order back to PHP.
 |--------------------------------------------------------------------------- */
$repRow = static function (string $key, array $meta, array $row, $index): string {
    $item  = (string) ($meta['item'] ?? 'item');
    $shown = is_int($index) ? (string) ($index + 1) : '1';

    $html = '<div class="rep-row" data-rep-row>'
        . '<div class="rep-row-head">'
        . '<span class="rep-badge">' . e(ucfirst($item)) . ' <span data-rep-idx>' . $shown . '</span></span>'
        . '<div class="rep-tools">'
        . '<button type="button" class="rep-btn" data-rep-up aria-label="Move this ' . e($item) . ' up" title="Move up">' . lucide('chevron-up') . '</button>'
        . '<button type="button" class="rep-btn" data-rep-down aria-label="Move this ' . e($item) . ' down" title="Move down">' . lucide('chevron-down') . '</button>'
        . '<button type="button" class="rep-btn is-danger" data-rep-del aria-label="Remove this ' . e($item) . '" title="Remove">' . lucide('trash-2') . '</button>'
        . '</div></div><div class="rep-grid">';

    foreach (($meta['sub'] ?? []) as $sk => $spec) {
        $name  = $key . '[' . (is_int($index) ? (string) $index : '__IDX__') . '][' . $sk . ']';
        $value = (string) ($row[$sk] ?? '');
        $cls   = 'rep-f'
            . (!empty($spec['wide']) ? ' is-wide' : '')
            . ((int) ($spec['grow'] ?? 1) > 1 ? ' is-grow' : '');
        $ph    = isset($spec['placeholder']) ? ' placeholder="' . e((string) $spec['placeholder']) . '"' : '';
        $ml    = isset($spec['maxlength']) ? ' maxlength="' . (int) $spec['maxlength'] . '"' : '';

        $html .= '<div class="' . $cls . '"><label class="rep-lbl">' . e((string) ($spec['label'] ?? $sk));

        if (($spec['input'] ?? 'text') === 'textarea') {
            $html .= '<textarea class="form-textarea rep-input" name="' . e($name) . '" rows="3"' . $ml . $ph . '>' . e($value) . '</textarea>';
        } elseif (($spec['input'] ?? '') === 'number') {
            $html .= '<input class="form-control rep-input" type="number" name="' . e($name) . '" value="' . e($value) . '"'
                . ' min="' . (int) ($spec['min'] ?? 0) . '" max="' . (int) ($spec['max'] ?? 999999999) . '" step="1"' . $ph . '>';
        } else {
            $html .= '<input class="form-control rep-input" type="text" name="' . e($name) . '" value="' . e($value) . '"' . $ml . $ph
                . (!empty($spec['preview']) ? ' data-rep-src' : '') . '>';
        }
        $html .= '</label>';

        if (!empty($spec['preview'])) {
            // Live thumbnail so a mistyped path is obvious before saving.
            // The src is OMITTED, not emitted empty, when there is no path yet:
            // `<img src="">` resolves against the document URL, so browsers
            // re-request admin/settings.php as an image on every blank row. The
            // JS that fills this in on input sets the attribute itself.
            $html .= '<span class="rep-thumb"' . ($value === '' ? ' hidden' : '') . ' data-rep-thumb>'
                . '<img' . ($value !== '' ? ' src="' . e(upload_url($value)) . '"' : '')
                . ' alt="" loading="lazy"></span>';
        }
        if (!empty($spec['hint'])) {
            $html .= '<small class="rep-hint">' . e((string) $spec['hint']) . '</small>';
        }
        $html .= '</div>';
    }

    return $html . '</div></div>';
};

include __DIR__ . '/partials/head.php';
?>
<form id="settingsForm" class="settings-page admin-form" method="post" enctype="multipart/form-data" action="<?= e(admin_url('settings')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_do" value="save">

    <!-- Sticky page header -->
    <header class="settings-topbar">
        <div class="settings-head-txt">
            <nav class="settings-crumb" aria-label="Breadcrumb">
                <a href="<?= e(admin_url('dashboard')) ?>"><?= lucide('home') ?> Admin</a>
                <?= lucide('chevron-right') ?><span>Website Settings</span>
            </nav>
            <h1>Website Settings</h1>
            <p>Master configuration for your public site — identity, contact, content, legal and social.</p>
        </div>
        <div class="settings-actions">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= lucide('external-link') ?> View Site</a>
            <button class="btn btn-primary btn-sm" type="submit" data-save><?= lucide('save') ?> Save Changes</button>
        </div>
    </header>

    <!-- Horizontal, scrollable, colour-coded category tabs -->
    <div class="settings-tabbar">
        <button type="button" class="stab-arrow prev" data-stab-prev aria-label="Scroll categories left"><?= lucide('chevron-left') ?></button>
        <div class="settings-tabs" role="tablist" aria-label="Settings categories" data-stab-track>
            <?php foreach ($panelKeys as $i => $label): $m = $panelMeta[$label] ?? ['icon' => 'settings', 'desc' => '', 'hue' => '#2563eb']; ?>
                <button type="button" class="stab<?= $i === 0 ? ' is-active' : '' ?>" role="tab"
                        style="--stab-hue:<?= e($m['hue']) ?>"
                        data-tab="stab<?= $i ?>" aria-controls="stab<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
                    <span class="stab-ico"><?= lucide($m['icon']) ?></span>
                    <span class="stab-lbl"><?= e($label) ?></span>
                    <span class="stab-count"><?= count($panels[$label]['fields']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="stab-arrow next" data-stab-next aria-label="Scroll categories right"><?= lucide('chevron-right') ?></button>
    </div>

    <!-- Global search across every setting on the page -->
    <div class="settings-search">
        <?= lucide('search') ?>
        <input type="search" placeholder="Search all settings — try &quot;logo&quot;, &quot;email&quot;, &quot;maintenance&quot;…"
               aria-label="Search settings" data-settings-search autocomplete="off">
        <button type="button" class="ss-clear" data-settings-clear aria-label="Clear search" hidden><?= lucide('x') ?></button>
    </div>
    <p class="settings-search-note" data-settings-note hidden></p>

    <div class="settings-layout">
        <!-- Content: one card per panel -->
        <div class="settings-content">
            <?php foreach ($panelKeys as $i => $label): $panel = $panels[$label]; $m = $panelMeta[$label] ?? ['icon' => 'settings', 'desc' => '']; ?>
            <section class="settings-card<?= $i === 0 ? ' is-active' : '' ?>" id="stab<?= $i ?>" role="tabpanel"
                     style="--stab-hue:<?= e($m['hue'] ?? '#2563eb') ?>"
                     data-panel-label="<?= e($label) ?>" aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
                <header class="settings-card-head">
                    <span class="sch-ico"><?= lucide($m['icon']) ?></span>
                    <div><h2><?= e($label) ?></h2><p><?= e($m['desc']) ?></p></div>
                </header>
                <div class="settings-card-body">
                    <div class="settings-grid">
                        <?php foreach ($panel['fields'] as $key => $meta):
                            $value  = (string) get_setting($key, $meta['default'] ?? '');
                            $flabel = $meta['label'] ?? ucwords(str_replace('_', ' ', $key));
                            // 'rows' repeaters carry a whole grid of their own and
                            // must never be squeezed into one half-width column.
                            $wide   = in_array($meta['type'], ['textarea', 'image', 'boolean', 'rows'], true);
                        ?>
                        <div class="settings-field<?= $wide ? ' span-2' : '' ?>"
                             data-field-search="<?= e(mb_strtolower($flabel . ' ' . $key . ' ' . ($meta['hint'] ?? ''))) ?>">
                            <?php if ($meta['type'] === 'boolean'): ?>
                                <label class="settings-toggle" for="f_<?= e($key) ?>">
                                    <input type="hidden" name="<?= e($key) ?>" value="0">
                                    <input class="st-input" type="checkbox" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>>
                                    <span class="st-track" aria-hidden="true"><span class="st-thumb"></span></span>
                                    <span class="st-text"><?= e($flabel) ?></span>
                                </label>
                            <?php elseif ($meta['type'] === 'select'): ?>
                                <label class="settings-label" for="f_<?= e($key) ?>"><?= e($flabel) ?></label>
                                <select class="form-select" id="f_<?= e($key) ?>" name="<?= e($key) ?>">
                                    <?php foreach (($meta['options'] ?? []) as $ov => $ol): ?>
                                        <option value="<?= e($ov) ?>"<?= (string) $ov === $value ? ' selected' : '' ?>><?= e($ol) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($meta['type'] === 'rows'):
                                $repRows = array_values(($meta['source'])());
                                $repMax  = (int) ($meta['max'] ?? 12);
                                $repItem = (string) ($meta['item'] ?? 'item');
                            ?>
                                <label class="settings-label"><?= e($flabel) ?></label>
                                <div class="rep" data-rep data-max="<?= $repMax ?>" data-item="<?= e($repItem) ?>"
                                     data-upload="<?= e(UPLOAD_URI) ?>">
                                    <div class="rep-rows" data-rep-rows>
                                        <?php foreach ($repRows as $ri => $repItemRow): ?>
                                            <?= $repRow($key, $meta, (array) $repItemRow, $ri) ?>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="rep-none" data-rep-none<?= $repRows ? ' hidden' : '' ?>>
                                        <?= lucide('info') ?>
                                        No <?= e($repItem) ?>s left — saving now restores the built-in set.
                                    </p>
                                    <div class="rep-foot">
                                        <button type="button" class="btn btn-outline btn-sm" data-rep-add>
                                            <?= lucide('plus') ?> Add <?= e($repItem) ?>
                                        </button>
                                        <span class="rep-count" data-rep-count aria-live="polite"></span>
                                    </div>
                                    <?php /* Cloned by the Add button. Inputs inside a <template> are
                                             inert and are never submitted, so the placeholder index
                                             cannot reach PHP. */ ?>
                                    <template data-rep-tpl><?= $repRow($key, $meta, [], '__IDX__') ?></template>
                                </div>
                            <?php elseif ($meta['type'] === 'textarea'): ?>
                                <div class="ff">
                                    <textarea class="ff-input" id="f_<?= e($key) ?>" name="<?= e($key) ?>" placeholder=" " rows="4"><?= e($value) ?></textarea>
                                    <label class="ff-label" for="f_<?= e($key) ?>"><?= e($flabel) ?></label>
                                </div>
                            <?php elseif ($meta['type'] === 'image'): ?>
                                <label class="settings-label" for="f_<?= e($key) ?>"><?= e($flabel) ?></label>
                                <div class="settings-upload">
                                    <img id="preview_<?= e($key) ?>" class="su-preview" src="<?= e($value !== '' ? upload_url($value) : asset('images/placeholder.svg')) ?>" alt="<?= e($flabel) ?> preview">
                                    <div class="su-body">
                                        <input class="su-file" type="file" id="f_<?= e($key) ?>" name="<?= e($key) ?>" accept="image/*" data-preview="#preview_<?= e($key) ?>">
                                        <label class="btn btn-outline btn-sm" for="f_<?= e($key) ?>"><?= lucide('upload') ?> Choose image</label>
                                        <small class="su-note">Leave empty to keep the current file.</small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="ff<?= in_array($meta['input'] ?? '', ['date', 'color'], true) ? ' ff-static' : '' ?>">
                                    <input class="ff-input" type="<?= e($meta['input'] ?? 'text') ?>" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>" placeholder=" ">
                                    <label class="ff-label" for="f_<?= e($key) ?>"><?= e($flabel) ?></label>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($meta['hint'])): ?>
                                <small class="settings-hint"><?= lucide('info') ?> <?= e($meta['hint']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Sticky footer action bar -->
    <div class="settings-footbar">
        <span class="sf-note"><?= lucide('shield-check') ?> Changes apply site-wide immediately after saving.</span>
        <div class="sf-actions">
            <a class="btn btn-ghost" href="<?= e(admin_url('settings')) ?>"><?= lucide('rotate-ccw') ?> Reset</a>
            <a class="btn btn-secondary" href="<?= e(admin_url('dashboard')) ?>">Cancel</a>
            <button class="btn btn-primary" type="submit" data-save><?= lucide('save') ?> Save Changes</button>
        </div>
    </div>
</form>

<style>
/* ===================== PREMIUM WEBSITE SETTINGS (scoped) ===================== */
.settings-page { --sp-radius: 16px; margin: -0.25rem 0 0; }

/* Sticky header */
.settings-topbar {
    position: sticky; top: 60px; z-index: 30;
    display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
    margin: 0 0 1.4rem; padding: 1.1rem 1.3rem;
    background: rgba(255,255,255,.72); -webkit-backdrop-filter: blur(16px) saturate(180%); backdrop-filter: blur(16px) saturate(180%);
    border: 1px solid var(--border, #e2e8f0); border-radius: var(--sp-radius);
    box-shadow: 0 10px 30px -18px rgba(11,78,61,.4);
}
html[data-theme="dark"] .settings-topbar { background: rgba(17,24,39,.72); }
.settings-crumb { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--muted, #64748b); margin-bottom: .35rem; }
.settings-crumb a { color: var(--muted, #64748b); display: inline-flex; align-items: center; gap: .25rem; }
.settings-crumb a:hover { color: #0B4E3D; }
.settings-crumb svg { width: 13px; height: 13px; }
.settings-topbar h1 { font-size: 1.5rem; margin: 0; letter-spacing: -.02em; }
.settings-topbar p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .9rem; max-width: 60ch; }
.settings-actions { display: flex; gap: .5rem; flex-wrap: wrap; }

/* Single-column layout — the category list is the horizontal bar above. */
.settings-layout { display: block; }

/* ---------------------------------------------- horizontal category tabs */
.settings-tabbar {
    position: sticky; top: 122px; z-index: 29; display: flex; align-items: center; gap: .3rem;
    margin: 0 0 .9rem; padding: .4rem;
    background: rgba(255,255,255,.76); -webkit-backdrop-filter: blur(16px) saturate(180%); backdrop-filter: blur(16px) saturate(180%);
    border: 1px solid var(--border, #e2e8f0); border-radius: 18px;
    box-shadow: 0 10px 30px -22px rgba(11,78,61,.5);
}
html[data-theme="dark"] .settings-tabbar { background: rgba(17,24,39,.78); }

.settings-tabs {
    display: flex; gap: .3rem; overflow-x: auto; scroll-behavior: smooth; flex: 1 1 auto;
    scrollbar-width: none; -ms-overflow-style: none;
}
.settings-tabs::-webkit-scrollbar { display: none; }

.stab {
    --stab-hue: #2563eb;
    position: relative; display: inline-flex; align-items: center; gap: .5rem; flex: 0 0 auto;
    padding: .6rem .9rem; border-radius: 13px; cursor: pointer; white-space: nowrap;
    border: 1px solid transparent; background: transparent; color: var(--muted, #64748b);
    font: inherit; font-size: .86rem; font-weight: 650;
    transition: background .2s ease, color .2s ease, transform .2s ease;
}
.stab:hover { background: color-mix(in srgb, var(--stab-hue) 10%, transparent); color: var(--stab-hue); transform: translateY(-1px); }
.stab:focus-visible { outline: 2px solid var(--stab-hue); outline-offset: 2px; }
.stab.is-active {
    color: #fff;
    background: linear-gradient(135deg, var(--stab-hue), color-mix(in srgb, var(--stab-hue) 62%, #000));
    box-shadow: 0 10px 22px -12px color-mix(in srgb, var(--stab-hue) 85%, transparent);
}
/* Animated underline that slides with the active tab. */
.stab.is-active::after {
    content: ''; position: absolute; left: 18%; right: 18%; bottom: -.42rem; height: 3px; border-radius: 3px;
    background: var(--stab-hue); animation: stabUnderline .3s ease both;
}
@keyframes stabUnderline { from { transform: scaleX(.2); opacity: 0; } to { transform: none; opacity: 1; } }

.stab-ico { display: grid; place-items: center; flex: 0 0 auto; }
.stab-ico svg { width: 16px; height: 16px; }
.stab-count {
    font-size: .68rem; font-weight: 800; padding: .08rem .38rem; border-radius: 999px;
    background: color-mix(in srgb, var(--stab-hue) 14%, transparent); color: var(--stab-hue);
}
.stab.is-active .stab-count { background: rgba(255,255,255,.24); color: #fff; }

.stab-arrow {
    flex: 0 0 auto; display: none; place-items: center; width: 30px; height: 30px; cursor: pointer;
    border: 1px solid var(--border, #e2e8f0); border-radius: 9px; background: var(--surface, #fff); color: var(--muted, #64748b);
}
.stab-arrow:hover { color: var(--primary, #0B4E3D); border-color: var(--primary, #0B4E3D); }
.stab-arrow svg { width: 15px; height: 15px; }
.settings-tabbar.is-scrollable .stab-arrow { display: grid; }

/* ------------------------------------------------------- global search */
.settings-search { position: relative; margin: 0 0 1.1rem; }
.settings-search > svg {
    position: absolute; left: .95rem; top: 50%; transform: translateY(-50%);
    width: 17px; height: 17px; color: var(--muted, #64748b); pointer-events: none;
}
.settings-search input {
    width: 100%; padding: .8rem 2.6rem .8rem 2.6rem; font: inherit; font-size: .92rem;
    border: 1.5px solid var(--border, #e2e8f0); border-radius: 13px;
    background: var(--surface, #fff); color: var(--text, #0f172a);
    transition: border-color .2s ease, box-shadow .2s ease;
}
.settings-search input:focus {
    outline: none; border-color: var(--primary, #0B4E3D);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary, #0B4E3D) 13%, transparent);
}
.ss-clear {
    position: absolute; right: .7rem; top: 50%; transform: translateY(-50%);
    display: grid; place-items: center; width: 26px; height: 26px; cursor: pointer;
    border: 0; border-radius: 7px; background: var(--surface-2, #f1f5f9); color: var(--muted, #64748b);
}
.ss-clear:hover { color: #dc2626; }
.ss-clear svg { width: 14px; height: 14px; }
.settings-search-note { margin: -.5rem 0 1rem; font-size: .84rem; color: var(--muted, #64748b); }

/* While searching, show every matching field across all categories at once. */
.settings-page.is-searching .settings-card { display: block !important; margin-bottom: 1rem; }
.settings-page.is-searching .settings-card.no-match { display: none !important; }
.settings-page.is-searching .settings-field.no-match { display: none; }
.settings-page.is-searching .settings-tabbar { opacity: .45; pointer-events: none; }

/* Colour-code each card's header to match its tab. */
.settings-card .sch-ico { background: color-mix(in srgb, var(--stab-hue, #2563eb) 13%, transparent); color: var(--stab-hue, #2563eb); }
.settings-card { border-top: 3px solid var(--stab-hue, #2563eb); }

@media (max-width: 720px) {
    .settings-tabbar { top: 96px; }
    .stab-lbl { display: none; }
    .stab { padding: .6rem .7rem; }
}
@media (prefers-reduced-motion: reduce) {
    .stab, .settings-tabs { transition: none; scroll-behavior: auto; }
    .stab:hover { transform: none; }
    .stab.is-active::after { animation: none; }
}

/* Cards */
.settings-card { display: none; background: var(--surface, #fff); border: 1px solid var(--border, #e2e8f0); border-radius: var(--sp-radius); box-shadow: 0 1px 2px rgba(11,78,61,.04), 0 18px 40px -30px rgba(11,78,61,.35); overflow: hidden; }
.settings-card.is-active { display: block; animation: sp-fade .35s cubic-bezier(.16,1,.3,1); }
@keyframes sp-fade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.settings-card-head { display: flex; align-items: center; gap: .85rem; padding: 1.15rem 1.4rem; border-bottom: 1px solid var(--border, #e2e8f0); background: linear-gradient(180deg, var(--surface-2, #f8fafc), transparent); }
.sch-ico { width: 42px; height: 42px; flex: 0 0 auto; display: grid; place-items: center; border-radius: 12px; background: linear-gradient(135deg, rgba(11,78,61,.14), rgba(23,77,61,.14)); color: #0B4E3D; }
.sch-ico svg { width: 21px; height: 21px; }
.settings-card-head h2 { font-size: 1.12rem; margin: 0; }
.settings-card-head p { margin: .1rem 0 0; font-size: .82rem; color: var(--muted, #64748b); }
.settings-card-body { padding: 1.4rem; }
.settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.3rem 1.2rem; }
.settings-field { min-width: 0; }
.settings-field.span-2 { grid-column: 1 / -1; }

/* Floating-label fields */
.ff { position: relative; }
.ff-input {
    width: 100%; padding: 1.15rem .9rem .5rem; border: 1.5px solid var(--border, #e2e8f0); border-radius: 12px;
    background: var(--surface, #fff); color: var(--text, #0f172a); font: inherit; font-size: .92rem; outline: none;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
}
textarea.ff-input { padding-top: 1.4rem; resize: vertical; line-height: 1.6; }
.ff-input:hover { border-color: #BBD3EA; }
.ff-input:focus { border-color: #0B4E3D; box-shadow: 0 0 0 4px rgba(11,78,61,.14); background: var(--surface, #fff); }
.ff-label {
    position: absolute; left: .95rem; top: .82rem; font-size: .92rem; color: var(--muted, #64748b); pointer-events: none;
    transform-origin: left top; transition: transform .16s ease, color .16s ease;
    max-width: calc(100% - 1.8rem); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ff-input:focus + .ff-label,
.ff-input:not(:placeholder-shown) + .ff-label,
.ff-static .ff-label { transform: translateY(-.55rem) scale(.78); color: #0B4E3D; font-weight: 600; }
.ff-static .ff-input { padding-top: 1.15rem; }

/* Image upload */
.settings-label { display: block; font-size: .82rem; font-weight: 600; color: var(--text, #0f172a); margin-bottom: .5rem; }
.settings-upload { display: flex; align-items: center; gap: 1rem; padding: .9rem; border: 1.5px dashed var(--border, #cbd5e1); border-radius: 12px; background: var(--surface-2, #f8fafc); }
.su-preview { width: 64px; height: 64px; object-fit: contain; border-radius: 10px; background: #fff; border: 1px solid var(--border, #e2e8f0); flex: 0 0 auto; }
.su-body { display: flex; flex-direction: column; gap: .35rem; align-items: flex-start; }
.su-file { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
.su-note { font-size: .74rem; color: var(--muted, #64748b); }

.settings-hint { display: inline-flex; align-items: center; gap: .35rem; margin-top: .45rem; font-size: .77rem; color: var(--muted, #64748b); }
.settings-hint svg { width: 13px; height: 13px; flex: 0 0 auto; }

/* Boolean toggle switch */
.settings-toggle { display: inline-flex; align-items: center; gap: .75rem; cursor: pointer; user-select: none; }
.settings-toggle .st-input { position: absolute; width: 1px; height: 1px; opacity: 0; }
.st-track {
    position: relative; flex: 0 0 auto; width: 46px; height: 26px; border-radius: 999px;
    background: var(--border, #cbd5e1); transition: background .2s ease;
}
.st-thumb {
    position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%;
    background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.28); transition: transform .2s ease;
}
.st-input:checked + .st-track { background: var(--primary, #0B4E3D); }
.st-input:checked + .st-track .st-thumb { transform: translateX(20px); }
.st-input:focus-visible + .st-track { outline: 2px solid var(--primary, #0B4E3D); outline-offset: 3px; }
.st-text { font-size: .86rem; font-weight: 600; color: var(--text, #0f172a); }
@media (prefers-reduced-motion: reduce) { .st-track, .st-thumb { transition: none; } }

/* ---------------------------------------------- repeatable rows (.rep-*)
   Colour pairs introduced, as measured in the browser:
     .rep-badge  #0B4E3D on a 10% primary tint over white (≈#E9EDEC) → 8.2:1
     .rep-lbl / .rep-hint  var(--muted) #6B7280 on the row's #F4F8FC → 4.53:1
     .rep-count / .rep-none  #6B7280 on the card's #FDFEFE → 4.78:1
     .rep-input  #0B4E3D on #FDFEFE → 9.57:1
   The 11.5px hint is the smallest type here and still clears 4.5:1. These use
   var(--muted) rather than a deeper hardcoded slate so they follow the admin's
   dark theme like every other hint on the page.
   ---------------------------------------------------------------------- */
.rep { display: grid; gap: .75rem; }
.rep-rows { display: grid; gap: .75rem; }
.rep-row {
    border: 1px solid var(--border, #e2e8f0); border-radius: 13px;
    background: var(--surface-2, #f8fafc); overflow: hidden;
}
.rep-row-head {
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    padding: .5rem .7rem; background: var(--surface, #fff); border-bottom: 1px solid var(--border, #e2e8f0);
}
.rep-badge {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .18rem .6rem; border-radius: 999px;
    font-size: .72rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase;
    background: color-mix(in srgb, var(--primary, #0B4E3D) 10%, transparent);
    color: var(--primary, #0B4E3D);
}
.rep-tools { display: flex; gap: .3rem; }
.rep-btn {
    display: grid; place-items: center; width: 30px; height: 30px; cursor: pointer;
    border: 1px solid var(--border, #e2e8f0); border-radius: 9px;
    background: var(--surface, #fff); color: var(--muted, #64748b);
    transition: color var(--dur-fast, .18s) var(--ease-out, cubic-bezier(.22,1,.36,1)),
                border-color var(--dur-fast, .18s) var(--ease-out, cubic-bezier(.22,1,.36,1)),
                background-color var(--dur-fast, .18s) var(--ease-out, cubic-bezier(.22,1,.36,1));
}
.rep-btn svg { width: 15px; height: 15px; }
.rep-btn:hover { color: var(--primary, #0B4E3D); border-color: var(--primary, #0B4E3D); }
.rep-btn.is-danger:hover {
    color: #dc2626; border-color: #dc2626;
    background: color-mix(in srgb, #dc2626 10%, transparent);
}
.rep-btn:focus-visible { outline: 2px solid var(--primary, #0B4E3D); outline-offset: 2px; }
.rep-btn[disabled] { opacity: .4; cursor: not-allowed; }
.rep-btn[disabled]:hover { color: var(--muted, #64748b); border-color: var(--border, #e2e8f0); background: var(--surface, #fff); }

.rep-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: .75rem; padding: .85rem;
}
.rep-f { min-width: 0; }
.rep-f.is-grow { grid-column: span 2; }
.rep-f.is-wide { grid-column: 1 / -1; }
.rep-lbl {
    display: grid; gap: .3rem;
    font-size: .78rem; font-weight: 650; color: var(--muted, #64748b);
}
.rep-input { width: 100%; font-size: .9rem; }
textarea.rep-input { resize: vertical; line-height: 1.6; min-height: 76px; }
.rep-hint { display: block; margin-top: .3rem; font-size: .72rem; line-height: 1.5; color: var(--muted, #64748b); }
.rep-thumb {
    display: block; margin-top: .45rem; width: 96px; height: 62px;
    border: 1px solid var(--border, #e2e8f0); border-radius: 8px; overflow: hidden; background: var(--surface, #fff);
}
.rep-thumb[hidden] { display: none; }
.rep-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

.rep-foot { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
.rep-count { font-size: .78rem; color: var(--muted, #64748b); }
.rep-none {
    display: flex; align-items: center; gap: .4rem; margin: 0;
    font-size: .82rem; color: var(--muted, #64748b);
}
.rep-none svg { width: 14px; height: 14px; }
.rep-none[hidden] { display: none; }

/* On touch the 30px row controls become 44px targets — three of them still fit
   the row head on a 320px screen. */
@media (max-width: 820px) {
    .rep-btn { width: 44px; height: 44px; }
}
@media (max-width: 620px) {
    .rep-grid { grid-template-columns: 1fr; }
    .rep-f.is-grow { grid-column: span 1; }
}
@media (prefers-reduced-motion: reduce) { .rep-btn { transition: none; } }

/* Sticky footer bar */
.settings-footbar {
    position: sticky; bottom: 0; z-index: 30; margin-top: 1.4rem;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
    padding: .85rem 1.2rem; border: 1px solid var(--border, #e2e8f0); border-radius: 14px;
    background: rgba(255,255,255,.82); -webkit-backdrop-filter: blur(16px) saturate(180%); backdrop-filter: blur(16px) saturate(180%);
    box-shadow: 0 -10px 30px -18px rgba(11,78,61,.4);
}
html[data-theme="dark"] .settings-footbar { background: rgba(17,24,39,.82); }
.sf-note { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; color: var(--muted, #64748b); }
.sf-note svg { width: 15px; height: 15px; color: #2F8065; }
.sf-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.btn[data-save].is-saving { opacity: .75; pointer-events: none; }

/* Responsive */
@media (max-width: 940px) {
    .settings-topbar { top: 60px; }
    .settings-tabbar { top: 108px; }
}
@media (max-width: 620px) {
    .settings-grid { grid-template-columns: 1fr; }
    .settings-topbar { position: static; }
    .settings-footbar { flex-direction: column; align-items: stretch; }
    .sf-actions { justify-content: stretch; }
    .sf-actions .btn { flex: 1; justify-content: center; }
}
@media (prefers-reduced-motion: reduce) {
    .settings-card.is-active { animation: none; }
    .stab, .ff-input, .ff-label { transition: none; }
}
</style>

<script>
(function () {
    var form = document.getElementById('settingsForm');
    if (!form) return;
    var tabs  = Array.prototype.slice.call(form.querySelectorAll('.stab'));
    var panes = Array.prototype.slice.call(form.querySelectorAll('.settings-card'));
    var track = form.querySelector('[data-stab-track]');
    var bar   = form.querySelector('.settings-tabbar');

    function activate(id, store) {
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-tab') === id;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panes.forEach(function (p) {
            var on = p.id === id;
            p.classList.toggle('is-active', on);
            p.setAttribute('aria-hidden', on ? 'false' : 'true');
        });
        if (store) { try { localStorage.setItem('pwf-settings-tab', id); } catch (e) {} if (history.replaceState) history.replaceState(null, '', '#' + id); }
        // Keep the active tab visible in the scroller.
        var on = tabs.filter(function (t) { return t.getAttribute('data-tab') === id; })[0];
        if (on && on.scrollIntoView) { on.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { activate(t.getAttribute('data-tab'), true); }); });

    /* ---- arrow keys move between tabs (WAI-ARIA tablist behaviour) ------- */
    tabs.forEach(function (t, i) {
        t.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') { return; }
            e.preventDefault();
            var n = e.key === 'ArrowRight' ? (i + 1) % tabs.length : (i <= 0 ? tabs.length - 1 : i - 1);
            tabs[n].focus();
            activate(tabs[n].getAttribute('data-tab'), true);
        });
    });

    /* ---- horizontal scroll arrows, shown only when the bar overflows ----- */
    if (track && bar) {
        var syncArrows = function () { bar.classList.toggle('is-scrollable', track.scrollWidth > track.clientWidth + 4); };
        syncArrows();
        window.addEventListener('resize', syncArrows);
        var prev = form.querySelector('[data-stab-prev]');
        var next = form.querySelector('[data-stab-next]');
        if (prev) { prev.addEventListener('click', function () { track.scrollBy({ left: -220, behavior: 'smooth' }); }); }
        if (next) { next.addEventListener('click', function () { track.scrollBy({ left:  220, behavior: 'smooth' }); }); }
    }

    /* ---- global settings search ----------------------------------------- */
    var search = form.querySelector('[data-settings-search]');
    var clear  = form.querySelector('[data-settings-clear]');
    var note   = form.querySelector('[data-settings-note]');
    var page   = form;

    function runSearch(q) {
        q = (q || '').trim().toLowerCase();
        if (clear) { clear.hidden = q === ''; }

        if (q === '') {
            page.classList.remove('is-searching');
            panes.forEach(function (p) { p.classList.remove('no-match'); });
            form.querySelectorAll('.settings-field').forEach(function (f) { f.classList.remove('no-match'); });
            if (note) { note.hidden = true; }
            return;
        }

        // Show every category at once and hide the fields that do not match, so
        // a search spans the whole page rather than just the open tab.
        page.classList.add('is-searching');
        var hits = 0;
        panes.forEach(function (p) {
            var local = 0;
            p.querySelectorAll('[data-field-search]').forEach(function (f) {
                var hit = (f.getAttribute('data-field-search') || '').indexOf(q) > -1;
                f.classList.toggle('no-match', !hit);
                if (hit) { local++; hits++; }
            });
            p.classList.toggle('no-match', local === 0);
        });
        if (note) {
            note.hidden = false;
            note.textContent = hits === 0
                ? 'No setting matches “' + q + '”.'
                : hits + ' setting' + (hits === 1 ? '' : 's') + ' match “' + q + '”.';
        }
    }

    if (search) {
        var st;
        search.addEventListener('input', function () {
            clearTimeout(st);
            st = setTimeout(function () { runSearch(search.value); }, 120);
        });
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { search.value = ''; runSearch(''); }
        });
    }
    if (clear) {
        clear.addEventListener('click', function () { search.value = ''; runSearch(''); search.focus(); });
    }

    // Restore last tab (hash wins, then localStorage).
    var initial = (location.hash || '').replace('#', '');
    if (!panes.some(function (p) { return p.id === initial; })) {
        try { initial = localStorage.getItem('pwf-settings-tab') || ''; } catch (e) { initial = ''; }
    }
    if (panes.some(function (p) { return p.id === initial; })) { activate(initial, false); }

    // Saving state on submit (submission proceeds normally).
    form.addEventListener('submit', function () {
        form.querySelectorAll('[data-save]').forEach(function (b) {
            b.classList.add('is-saving'); b.setAttribute('aria-busy', 'true');
        });
    });

    // Turn server flash alerts into modern toasts.
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.Swal) return;
        document.querySelectorAll('.admin-content > .alert').forEach(function (al) {
            var icon = al.classList.contains('alert-success') ? 'success' : (al.classList.contains('alert-error') || al.classList.contains('alert-danger') ? 'error' : 'info');
            Swal.fire({ toast: true, position: 'top-end', icon: icon, title: al.textContent.trim(), showConfirmButton: false, timer: 3200, timerProgressBar: true });
            al.remove();
        });
    });
})();
</script>

<script>
/* =============================================================================
   Repeatable rows — add / remove / reorder for the 'rows' settings fields.
   Deliberately a separate IIFE from the tab/search script above so a change here
   can never take the rest of the settings page down with it.

   Order is carried by the [n] index in each field name, and PHP reads the rows
   in the order the browser posted them, so reordering is: move the node, then
   renumber. No hidden sort field, no JSON blob in a textarea.
   ========================================================================== */
(function () {
    var form = document.getElementById('settingsForm');
    if (!form) { return; }
    var reps = Array.prototype.slice.call(form.querySelectorAll('[data-rep]'));
    if (!reps.length) { return; }

    function drawIcons() {
        if (window.PWFdrawIcons) { window.PWFdrawIcons(); }
        else if (window.lucide) { try { window.lucide.createIcons(); } catch (e) {} }
    }

    function rowsOf(rep) {
        return Array.prototype.slice.call(rep.querySelectorAll('[data-rep-row]'));
    }

    /* Renumber names + labels, refresh the counter, and disable the controls
       that would do nothing (up on the first row, down on the last, add at max). */
    function sync(rep) {
        var rows = rowsOf(rep);
        var max  = parseInt(rep.getAttribute('data-max') || '12', 10);
        var item = rep.getAttribute('data-item') || 'item';

        rows.forEach(function (row, i) {
            row.querySelectorAll('[name]').forEach(function (f) {
                f.name = f.name.replace(/\[[^\]]*\]/, '[' + i + ']');
            });
            var n = row.querySelector('[data-rep-idx]');
            if (n) { n.textContent = String(i + 1); }
            var up = row.querySelector('[data-rep-up]');
            var dn = row.querySelector('[data-rep-down]');
            if (up) { up.disabled = i === 0; }
            if (dn) { dn.disabled = i === rows.length - 1; }
        });

        var add = rep.querySelector('[data-rep-add]');
        if (add) {
            add.disabled = rows.length >= max;
            add.classList.toggle('is-disabled', rows.length >= max);
        }
        var count = rep.querySelector('[data-rep-count]');
        if (count) {
            count.textContent = rows.length + ' of ' + max + ' ' + item + (rows.length === 1 ? '' : 's')
                + (rows.length >= max ? ' — that is the maximum' : '');
        }
        var none = rep.querySelector('[data-rep-none]');
        if (none) { none.hidden = rows.length !== 0; }
    }

    function hasContent(row) {
        return Array.prototype.slice.call(row.querySelectorAll('[name]')).some(function (f) {
            return String(f.value || '').trim() !== '';
        });
    }

    reps.forEach(function (rep) {
        var list = rep.querySelector('[data-rep-rows]');
        var tpl  = rep.querySelector('[data-rep-tpl]');

        rep.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-rep-add], [data-rep-up], [data-rep-down], [data-rep-del]');
            if (!btn || btn.disabled) { return; }
            e.preventDefault();

            if (btn.hasAttribute('data-rep-add')) {
                if (!tpl || !list) { return; }
                var max = parseInt(rep.getAttribute('data-max') || '12', 10);
                if (rowsOf(rep).length >= max) { return; }
                // The template carries __IDX__ placeholders; sync() would fix the
                // numbering anyway, but a real index keeps the markup valid at
                // every step.
                var html = tpl.innerHTML.replace(/__IDX__/g, String(rowsOf(rep).length));
                var frag = document.createElement('div');
                frag.innerHTML = html;
                var row = frag.firstElementChild;
                if (!row) { return; }
                list.appendChild(row);
                sync(rep);
                drawIcons();
                var first = row.querySelector('input, textarea');
                if (first) { first.focus(); }
                return;
            }

            var row = btn.closest('[data-rep-row]');
            if (!row) { return; }

            if (btn.hasAttribute('data-rep-del')) {
                // Nothing is saved until Save Changes, but a filled-in row is
                // real work — confirm before throwing it away.
                if (hasContent(row) && !window.confirm('Remove this ' + (rep.getAttribute('data-item') || 'item') + '?')) { return; }
                row.remove();
                sync(rep);
                return;
            }
            if (btn.hasAttribute('data-rep-up') && row.previousElementSibling) {
                row.parentNode.insertBefore(row, row.previousElementSibling);
            } else if (btn.hasAttribute('data-rep-down') && row.nextElementSibling) {
                row.parentNode.insertBefore(row.nextElementSibling, row);
            }
            sync(rep);
            // Keep focus on the button that moved, so a keyboard user can move
            // the same row again without hunting for it.
            var again = row.querySelector(btn.hasAttribute('data-rep-up') ? '[data-rep-up]' : '[data-rep-down]');
            if (again && !again.disabled) { again.focus(); }
        });

        /* Live thumbnail for image-path fields. */
        rep.addEventListener('input', function (e) {
            var input = e.target.closest('[data-rep-src]');
            if (!input) { return; }
            var wrap = input.closest('.rep-f');
            var box  = wrap && wrap.querySelector('[data-rep-thumb]');
            if (!box) { return; }
            var img  = box.querySelector('img');
            var val  = String(input.value || '').trim();
            if (val === '') { box.hidden = true; return; }
            if (img) {
                img.src = /^https?:\/\//i.test(val)
                    ? val
                    : (rep.getAttribute('data-upload') || '') + '/' + val.replace(/^\/+/, '');
            }
            box.hidden = false;
        });

        sync(rep);
    });
})();
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
