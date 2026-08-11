<?php
/**
 * =============================================================================
 *  Admin — Membership Settings
 * =============================================================================
 *  Configure the membership module:
 *    - the ID card: template, branding, palette, layout, visible fields,
 *      benefits source, footer copy, signatory and social marks, with a live
 *      preview of a real member's card;
 *    - the ID prefix and expiry reminders;
 *    - the Cashfree payment gateway and SMS (MSG91 / Fast2SMS).
 *
 *  Everything is stored through set_setting() in the `membership` group — there
 *  is no second store. Every key has a default in card_setting_defaults(), so a
 *  fresh install with no rows at all renders the audited default card.
 *
 *  Colours are chosen from the ten brand values only, and only ever as
 *  BACKGROUNDS: card_theme() derives each ink from the background that was
 *  actually picked, so no combination reachable from this screen can drop a text
 *  pair below WCAG AA. Secret fields are preserved when left blank so they are
 *  never accidentally wiped.
 *
 *  Access: require_admin() -> sec_ip_allowed() -> is_logged_in() ->
 *  sec_enforce_admin() -> rbac_gate(), and the slug `membership-settings` sits
 *  in the "Programs & Applications" RBAC group (includes/rbac.php).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/membership_card.php';
require_admin();

/* One source of truth: this page used to hardcode its own template list, which
   drifted from card_templates() the moment a template was renamed. */
$cardTemplates = card_templates();
$cardFields    = card_field_keys();
$cardPlatforms = card_social_platforms();

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    $plain = [
        'membership_code_prefix'          => 'membership',
        'membership_reminder_days'        => 'membership',
        'membership_card_org_name'        => 'membership',
        'membership_card_tagline'         => 'membership',
        'membership_card_signatory'       => 'membership',
        'membership_card_signatory_role'  => 'membership',
        'membership_card_helpline'        => 'membership',
        'cashfree_env'             => 'payments',
        'cashfree_app_id'          => 'payments',
        'sms_provider'             => 'sms',
        'msg91_sender'             => 'sms',
        'msg91_route'              => 'sms',
        'msg91_dlt_template_id'    => 'sms',
        'fast2sms_sender'          => 'sms',
    ];
    foreach ($plain as $key => $group) {
        set_setting($key, clean(post($key)), $group, 'text');
    }

    foreach (['membership_card_terms', 'membership_card_footer_text', 'membership_card_benefits_custom'] as $key) {
        set_setting($key, clean(post($key)), 'membership', 'textarea');
    }

    /* Template — validated against card_templates(), with the pre-4-template
       'dark' id resolved rather than silently reset to classic. */
    set_setting('membership_card_template', card_template_id((string) post('membership_card_template')), 'membership', 'text');

    /* Palette. Snapped to the values allowed for each slot; anything else (an
       old value, a hand-edited form) becomes '' = "use the template default". */
    foreach (['color_primary' => 'base', 'color_secondary' => 'base', 'color_accent' => 'accent', 'bg' => 'bg'] as $key => $slot) {
        set_setting('membership_card_' . $key, card_color_for_slot($slot, (string) post('membership_card_' . $key)), 'membership', 'color');
    }

    /* Layout. The card cannot put the photo and the QR in the same column, so
       card_settings() moves the QR if they collide — validated here too. */
    foreach (['photo_pos' => 'left', 'qr_pos' => 'right'] as $key => $fallback) {
        $val = (string) post('membership_card_' . $key);
        set_setting('membership_card_' . $key, in_array($val, ['left', 'right'], true) ? $val : $fallback, 'membership', 'text');
    }

    /* Visible fields are stored as the HIDDEN set. get_setting() returns the
       default for an empty string, so a "visible" list emptied by unticking
       everything would spring straight back to the default; an empty hidden
       list means "show everything", which is also the right default for a field
       added in a later release. */
    $visible = array_map('strval', (array) post('card_fields', []));
    $hidden  = [];
    foreach (array_keys($cardFields) as $key) {
        if (!in_array($key, $visible, true)) {
            $hidden[] = $key;
        }
    }
    set_setting('membership_card_hidden_fields', implode(',', $hidden), 'membership', 'text');

    $source = (string) post('membership_card_benefits_source');
    set_setting(
        'membership_card_benefits_source',
        in_array($source, ['plan', 'custom', 'plan_or_custom', 'none'], true) ? $source : 'plan',
        'membership',
        'text'
    );

    /* Social marks: '' means "follow Social Links", so a platform added there
       later appears on the card without anyone revisiting this screen. Narrowing
       the set and then ticking nothing has to mean "print none", not "print
       everything", so it stores the explicit 'none' sentinel. */
    $socials = '';
    if (post('card_social_mode') === 'pick') {
        $picked = [];
        foreach ((array) post('card_socials', []) as $slug) {
            $slug = strtolower(clean((string) $slug));
            if ($slug !== '' && in_array($slug, $cardPlatforms, true)) {
                $picked[] = $slug;
            }
        }
        $socials = $picked ? implode(',', $picked) : 'none';
    }
    set_setting('membership_card_socials', $socials, 'membership', 'text');

    /* Images: replaced only when a new file is uploaded, and cleared only when
       explicitly asked — so saving the form never silently drops the signature
       or the logo that is already printed on every card. */
    $images = [
        'membership_card_signature' => ['remove_signature', 'Signature image'],
        'membership_card_logo'      => ['remove_card_logo', 'Card logo'],
    ];
    foreach ($images as $key => [$removeFlag, $label]) {
        if (!empty($_FILES[$key]['name'])) {
            $up = upload_image($_FILES[$key], 'images');
            if (!empty($up['success'])) {
                $old = (string) get_setting($key, '');
                set_setting($key, $up['path'], 'membership', 'image');
                if ($old !== '' && $old !== $up['path']) {
                    delete_upload($old);
                }
            } else {
                set_flash('error', $label . ': ' . ($up['error'] ?? 'upload failed.'));
            }
        } elseif (post($removeFlag)) {
            $old = (string) get_setting($key, '');
            if ($old !== '') {
                delete_upload($old);
            }
            set_setting($key, '', 'membership', 'image');
        }
    }

    $bools = [
        'membership_reminders_enabled' => 'membership',
        'cashfree_enabled'             => 'payments',
        'sms_enabled'                  => 'sms',
    ];
    foreach ($bools as $key => $group) {
        set_setting($key, post($key) ? '1' : '0', $group, 'boolean');
    }

    // Secrets: only overwrite when a new value is supplied.
    $secrets = [
        'cashfree_secret_key'     => 'payments',
        'cashfree_webhook_secret' => 'payments',
        'msg91_authkey'           => 'sms',
        'fast2sms_key'            => 'sms',
    ];
    foreach ($secrets as $key => $group) {
        $val = (string) post($key);
        if ($val !== '') {
            set_setting($key, trim($val), $group, 'text');
        }
    }

    if (post('regen_token') || (string) get_setting('membership_cron_token', '') === '') {
        set_setting('membership_cron_token', bin2hex(random_bytes(20)), 'membership', 'text');
    }

    log_activity('update', 'membership-settings', 'Updated membership settings');
    set_flash('success', 'Membership settings saved.');
    redirect('/admin/membership-settings?tpl=' . urlencode(card_default_template()));
}

/* -------------------------------------------------------------- TEST SMS */
if (is_post() && post('_do') === 'test_sms') {
    require_csrf();
    $to  = clean(post('test_number'));
    $res = send_sms($to, 'Test SMS from ' . get_setting('site_name', SITE_NAME) . '. Your setup works.');
    set_flash($res['success'] ? 'success' : 'error', 'SMS test: ' . $res['message']);
    redirect('/admin/membership-settings');
}

$mask = static fn (string $key): string => get_setting($key, '') !== '' ? '•••••••• (saved)' : '';
$cronToken = (string) get_setting('membership_cron_token', '');
$cronPath  = str_replace('/', DIRECTORY_SEPARATOR, BASE_PATH . '/cron/membership-reminders.php');
$cronUrl   = abs_url('cron/membership-reminders?token=' . $cronToken); // extensionless (clean URL)

/* -------------------------------------------------- current configuration */
$cfg     = card_settings(true);          // re-read: a save just happened
$default = card_default_template();

/* -------------------------------------------------------------- PREVIEW
   A real member, so the preview shows what the card actually does with real
   data — including the rows that are empty and therefore omitted. Prefer
   someone with a photo and a plan; fall back to the newest member. No id is
   taken from the request: this page is admin-only, but there is no reason for
   it to accept one. */
$previewTpl = card_template_id((string) get('tpl', $default));
$preview = db_row(
    "SELECT * FROM members
      ORDER BY (avatar IS NOT NULL AND avatar <> '') DESC, (plan_id IS NOT NULL) DESC, id DESC
      LIMIT 1"
);

$page_title = 'Membership Settings';
include __DIR__ . '/partials/head.php';
?>
<style>
/* Scoped to this screen. The card itself brings its own styles. */
.mcs-wrap{display:grid;grid-template-columns:minmax(0,1fr);gap:1.25rem;align-items:start}
@media (min-width:1180px){.mcs-wrap{grid-template-columns:minmax(0,1fr) 400px}}
.mcs-col{display:grid;gap:1.25rem;min-width:0}
.mcs-side{position:sticky;top:1rem;min-width:0}
.mcs-tpl{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.6rem}
.mcs-tpl label{position:relative;display:block;cursor:pointer;border:1.5px solid var(--border,#C1CCB3);
    border-radius:12px;padding:.55rem;background:var(--surface,#fff);transition:.15s}
.mcs-tpl label:hover{border-color:var(--brand-600,#0B4E3D)}
.mcs-tpl input{position:absolute;opacity:0;pointer-events:none}
.mcs-tpl input:checked+.mcs-card{outline:2px solid var(--brand-600,#0B4E3D);outline-offset:3px}
.mcs-tpl input:focus-visible+.mcs-card{outline:2px dashed var(--brand-600,#0B4E3D);outline-offset:3px}
.mcs-card{display:block;overflow:hidden;aspect-ratio:1012/638;box-shadow:0 6px 14px -10px rgba(4,26,18,.6)}
.mcs-card i{display:block}
.mcs-name{display:block;margin-top:.45rem;font-size:.8rem;font-weight:700;line-height:1.25}
.mcs-sub{display:block;font-size:.7rem;color:var(--muted,#6B7280);line-height:1.3}
.mcs-sw{display:flex;flex-wrap:wrap;gap:.4rem}
.mcs-sw label{position:relative;cursor:pointer}
.mcs-sw input{position:absolute;opacity:0;pointer-events:none}
.mcs-sw span{display:block;width:34px;height:34px;border-radius:9px;border:1.5px solid rgba(0,0,0,.18);
    box-shadow:inset 0 0 0 2px rgba(255,255,255,.55)}
.mcs-sw .mcs-auto{display:grid;place-items:center;width:auto;min-width:66px;padding:0 .5rem;font-size:.7rem;
    font-weight:700;color:var(--text-soft,#374151);background:var(--surface-2,#F4F7F2);box-shadow:none}
.mcs-sw input:checked+span{outline:2px solid var(--brand-600,#0B4E3D);outline-offset:2px}
.mcs-sw input:focus-visible+span{outline:2px dashed var(--brand-600,#0B4E3D);outline-offset:2px}
.mcs-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.15rem 1rem}
.mcs-fields .checkbox{align-items:flex-start;padding:.28rem 0}
.mcs-fields .mcs-sub{margin-left:1.7rem}
.mcs-legend{font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
    color:var(--muted,#6B7280);margin:.6rem 0 .2rem;grid-column:1/-1}
.mcs-seg{display:flex;gap:.4rem;flex-wrap:wrap}
.mcs-seg label{position:relative;cursor:pointer}
.mcs-seg input{position:absolute;opacity:0;pointer-events:none}
.mcs-seg span{display:block;border:1.5px solid var(--border,#C1CCB3);border-radius:999px;padding:.4rem .85rem;
    font-size:.82rem;font-weight:650;background:var(--surface,#fff)}
.mcs-seg input:checked+span{background:var(--brand-600,#0B4E3D);color:#fff;border-color:transparent}
.mcs-seg input:focus-visible+span{outline:2px dashed var(--brand-600,#0B4E3D);outline-offset:2px}
.mcs-thumb{background:#fff;border:1px solid var(--border,#C1CCB3);border-radius:10px;padding:.4rem;
    display:inline-block;margin-bottom:.4rem}
.mcs-ratios{width:100%;border-collapse:collapse;font-size:.76rem}
.mcs-ratios th,.mcs-ratios td{text-align:left;padding:.25rem .4rem;border-bottom:1px solid var(--border,#C1CCB3)}
.mcs-ratios td:last-child{text-align:right;font-variant-numeric:tabular-nums;font-weight:700}
/* #1F5C48 is not a brand colour; the primary green is, and it reads at 9.5:1 on
   the panel. A FAIL now has its own loud style — the table used to mark failure
   only by the absence of the pass class, so a failing row looked exactly like a
   passing one. #151818 on #F15A24 is 5.30:1. */
.mcs-pass{color:#0B4E3D}
.mcs-fail{color:#151818;background:#F15A24;border-radius:5px}
.mcs-fail strong{display:block;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em}
</style>

<div class="admin-page-head">
    <div><h1>Membership Settings</h1><span class="muted">ID card · reminders · payments · SMS</span></div>
    <div class="flex" style="gap:.5rem;">
        <a class="btn btn-outline" href="<?= e(admin_url('membership-plans')) ?>"><?= lucide('badge-dollar-sign') ?> Plans &amp; benefits</a>
        <a class="btn btn-outline" href="<?= e(admin_url('social-links')) ?>"><?= lucide('share-2') ?> Social links</a>
    </div>
</div>

<form method="post" action="<?= e(admin_url('membership-settings')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="_do" value="save">

    <div class="mcs-wrap">
        <!-- ==================================================== CARD DESIGN -->
        <div class="mcs-col">

            <!-- Template -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">ID card template</h3>
                <p class="text-muted mb-3" style="font-size:.9rem;">
                    The default for every member's card. Each template is a different
                    structure — header shape, photo shape, benefits treatment and corner
                    radius — not just a repaint. A member can still preview the others on
                    their own card page.
                </p>
                <div class="mcs-tpl">
                    <?php foreach ($cardTemplates as $key => $labelText):
                        $t = card_theme($key);
                        $l = card_layout($key);
                        [$tplName, $tplNote] = array_pad(preg_split('/\s+—\s+/u', $labelText, 2) ?: [$labelText], 2, '');
                        ?>
                        <label>
                            <input type="radio" name="membership_card_template" value="<?= e($key) ?>" <?= $cfg['template'] === $key ? 'checked' : '' ?>>
                            <span class="mcs-card" style="border-radius:<?= e($l['radius']) ?>;background:<?= e($t['body']) ?>;">
                                <i style="height:34%;background:linear-gradient(150deg,<?= e($t['head1']) ?>,<?= e($t['head2']) ?>);
                                        <?= $l['headStyle'] === 'wave' ? 'border-bottom-left-radius:60% 40%;border-bottom-right-radius:60% 40%;' : '' ?>
                                        <?= $l['headStyle'] === 'arch' ? 'border-bottom-left-radius:50% 70%;border-bottom-right-radius:50% 70%;' : '' ?>
                                        <?= $l['rail'] > 0 ? 'border-left:6px solid ' . e($t['chipBg']) . ';' : '' ?>"></i>
                                <i style="height:38%;display:flex;gap:5px;padding:6px;box-sizing:border-box;">
                                    <i style="width:26%;border-radius:<?= $l['photoShape'] === 'circle' ? '50%' : ($l['photoShape'] === 'arch' ? '40% 40% 6px 6px' : '4px') ?>;
                                            background:<?= e($t['panel']) ?>;border:1px solid <?= e($t['frame']) ?>;"></i>
                                    <i style="flex:1;display:grid;gap:4px;align-content:start;">
                                        <i style="height:7px;width:70%;border-radius:3px;background:<?= e($t['ink']) ?>;"></i>
                                        <i style="height:5px;width:40%;border-radius:3px;background:<?= e($t['chipBg']) ?>;"></i>
                                        <i style="height:4px;width:58%;border-radius:3px;background:<?= e($t['label']) ?>;opacity:.6;"></i>
                                    </i>
                                    <i style="width:22%;background:<?= e($t['qrPlate']) ?>;border:1px solid <?= e($t['frame']) ?>;border-radius:3px;"></i>
                                </i>
                                <i style="height:<?= $l['strip'] === 'none' ? '0' : '10' ?>%;background:<?= e($t['panel']) ?>;"></i>
                                <i style="height:<?= $l['strip'] === 'none' ? '28' : '18' ?>%;background:<?= e($t['foot']) ?>;"></i>
                            </span>
                            <span class="mcs-name"><?= e($tplName) ?><?= $key === $default ? ' <small class="muted">(current)</small>' : '' ?></span>
                            <span class="mcs-sub"><?= e($tplNote) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div></div>

            <!-- Branding -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Branding</h3>
                <p class="text-muted mb-3" style="font-size:.9rem;">
                    Leave any of these empty and the card uses the site's own name, tagline
                    and logo. Override them when the card needs the registered legal name or
                    a print-safe version of the mark.
                </p>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label" for="mcOrg">Organisation name on the card</label>
                        <input class="form-control" id="mcOrg" name="membership_card_org_name" value="<?= e($cfg['org_name']) ?>" placeholder="<?= e(get_setting('site_name', SITE_NAME)) ?>">
                        <small class="form-hint">Default: the site name.</small></div>
                    <div class="form-group"><label class="form-label" for="mcTag">Tagline</label>
                        <input class="form-control" id="mcTag" name="membership_card_tagline" value="<?= e($cfg['tagline']) ?>" placeholder="<?= e(get_setting('site_tagline', '')) ?>">
                        <small class="form-hint">Default: the site tagline. Printed in small caps.</small></div>
                </div>
                <div class="form-group"><label class="form-label" for="mcLogo">Card logo</label>
                    <?php if ($cfg['logo'] !== ''): ?>
                        <div class="mcs-thumb"><img src="<?= e(upload_url($cfg['logo'])) ?>" alt="Current card logo" style="max-height:52px;display:block;"></div>
                        <label class="checkbox"><input type="checkbox" name="remove_card_logo" value="1"> Remove it and use the site logo again</label>
                    <?php endif; ?>
                    <input class="form-control" type="file" id="mcLogo" name="membership_card_logo" accept="image/*">
                    <small class="form-hint">Square reads best — it is drawn in a circle. Leave empty to keep the site / theme logo.</small></div>
            </div></div>

            <!-- Palette -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Colours</h3>
                <p class="text-muted mb-3" style="font-size:.9rem;">
                    Only the ten brand colours, and only as backgrounds. Every piece of
                    type is then derived from the background you pick and checked against
                    WCAG AA, so nothing you choose here can make the card unreadable.
                    <em>Template default</em> keeps the value the template ships with.
                </p>
                <?php
                $swatches = static function (string $slot, string $key, string $current): void {
                    echo '<div class="mcs-sw">';
                    echo '<label title="Use the template default"><input type="radio" name="membership_card_' . e($key) . '" value="" '
                        . ($current === '' ? 'checked' : '') . '><span class="mcs-auto">Template</span></label>';
                    foreach (card_color_options($slot) as $hex => $name) {
                        echo '<label title="' . e($name . ' ' . strtoupper($hex)) . '">'
                            . '<input type="radio" name="membership_card_' . e($key) . '" value="' . e($hex) . '" '
                            . (strcasecmp($current, $hex) === 0 ? 'checked' : '') . '>'
                            . '<span style="background:' . e($hex) . '"></span></label>';
                    }
                    echo '</div>';
                };
                ?>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Primary — header, footer and plaque</label>
                        <?php $swatches('base', 'color_primary', $cfg['color_primary']); ?></div>
                    <div class="form-group"><label class="form-label">Secondary — gradient end and labels</label>
                        <?php $swatches('base', 'color_secondary', $cfg['color_secondary']); ?></div>
                    <div class="form-group"><label class="form-label">Accent — tier chip and eyebrow type</label>
                        <?php $swatches('accent', 'color_accent', $cfg['color_accent']); ?></div>
                    <div class="form-group"><label class="form-label">Card background</label>
                        <?php $swatches('bg', 'bg', $cfg['bg']); ?></div>
                </div>
            </div></div>

            <!-- Layout -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Layout</h3>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Photograph</label>
                        <div class="mcs-seg">
                            <?php foreach (['left' => 'Left', 'right' => 'Right'] as $val => $lbl): ?>
                                <label><input type="radio" name="membership_card_photo_pos" value="<?= e($val) ?>" <?= $cfg['photo_pos'] === $val ? 'checked' : '' ?>><span><?= e($lbl) ?></span></label>
                            <?php endforeach; ?>
                        </div></div>
                    <div class="form-group"><label class="form-label">QR code</label>
                        <div class="mcs-seg">
                            <?php foreach (['left' => 'Left', 'right' => 'Right'] as $val => $lbl): ?>
                                <label><input type="radio" name="membership_card_qr_pos" value="<?= e($val) ?>" <?= $cfg['qr_pos'] === $val ? 'checked' : '' ?>><span><?= e($lbl) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <small class="form-hint">They cannot share a column — pick the same side twice and the QR moves to the other one.</small></div>
                </div>
            </div></div>

            <!-- Fields -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Fields on the card</h3>
                <p class="text-muted mb-2" style="font-size:.9rem;">
                    Unticking a field removes it completely — the card never prints a
                    heading over blank space. A field the member has not filled in is
                    dropped the same way, whether it is ticked or not.
                </p>
                <div class="mcs-fields">
                    <?php foreach (['front' => 'Front', 'both' => 'Front &amp; back', 'back' => 'Back'] as $face => $faceLabel): ?>
                        <div class="mcs-legend"><?= $faceLabel ?></div>
                        <?php foreach ($cardFields as $key => [$fLabel, $fHint, $fFace]):
                            if ($fFace !== $face) { continue; } ?>
                            <div>
                                <label class="checkbox"><input type="checkbox" name="card_fields[]" value="<?= e($key) ?>" <?= card_field_on($key) ? 'checked' : '' ?>> <?= e($fLabel) ?></label>
                                <span class="mcs-sub"><?= $fHint ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted" style="font-size:.82rem;margin:.9rem 0 0;">
                    The tier chip, the status badge and the QR block are not listed: the
                    status must always be shown (with a glyph, not colour alone) and the QR
                    is the reason the card exists.
                </p>
            </div></div>

            <!-- Benefits -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Benefits</h3>
                <div class="form-group"><label class="form-label" for="mcBenSrc">Where the benefit lines come from</label>
                    <select class="form-select" id="mcBenSrc" name="membership_card_benefits_source">
                        <?php foreach ([
                            'plan'           => 'The member’s own membership plan (default)',
                            'plan_or_custom' => 'The plan, falling back to the list below',
                            'custom'         => 'The list below, for every member',
                            'none'           => 'Do not print benefits',
                        ] as $val => $lbl): ?>
                            <option value="<?= e($val) ?>" <?= $cfg['benefits_source'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Plan benefits are edited per plan under <a href="<?= e(admin_url('membership-plans')) ?>">Membership Plans</a>, one per line.</small></div>
                <div class="form-group"><label class="form-label" for="mcBen">Organisation-wide benefit list</label>
                    <textarea class="form-control" id="mcBen" name="membership_card_benefits_custom" rows="4" placeholder="One benefit per line. Used by the two options above that mention this list."><?= e($cfg['benefits_custom']) ?></textarea>
                    <small class="form-hint">The front strip shows the first five; the back lists up to eight.</small></div>
            </div></div>

            <!-- Copy on the card -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Card copy</h3>
                <div class="form-group"><label class="form-label" for="mcFoot">Footer notice</label>
                    <textarea class="form-control" id="mcFoot" name="membership_card_footer_text" rows="2" placeholder="This card is the property of {org}.&#10;If found, please return to the nearest office."><?= e($cfg['footer_text']) ?></textarea>
                    <small class="form-hint">Up to three lines. <code>{org}</code>, <code>{site}</code>, <code>{email}</code> and <code>{phone}</code> are replaced. Empty = the default property notice.</small></div>
                <div class="form-group"><label class="form-label" for="mcTerms">Terms of use (back of card)</label>
                    <textarea class="form-control" id="mcTerms" name="membership_card_terms" rows="3" placeholder="Leave empty to omit the terms block."><?= e($cfg['terms']) ?></textarea></div>
                <div class="form-group"><label class="form-label" for="mcHelp">Helpline / emergency contact</label>
                    <input class="form-control" id="mcHelp" name="membership_card_helpline" value="<?= e($cfg['helpline']) ?>" placeholder="e.g. 1800 000 000">
                    <small class="form-hint">Shown on the back. Organisation-wide — <code>members</code> has no per-member emergency contact.</small></div>
            </div></div>

            <!-- Signatory -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Authorised signatory</h3>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label" for="mcSig">Name</label>
                        <input class="form-control" id="mcSig" name="membership_card_signatory" value="<?= e($cfg['signatory']) ?>" placeholder="e.g. Dr. A. Kumar">
                        <small class="form-hint">Printed under the signature on both faces.</small></div>
                    <div class="form-group"><label class="form-label" for="mcSigRole">Designation</label>
                        <input class="form-control" id="mcSigRole" name="membership_card_signatory_role" value="<?= e($cfg['signatory_role']) ?>" placeholder="e.g. Secretary"></div>
                </div>
                <div class="form-group"><label class="form-label" for="mcSigImg">Signature image</label>
                    <?php if ($cfg['signature'] !== ''): ?>
                        <div class="mcs-thumb"><img src="<?= e(upload_url($cfg['signature'])) ?>" alt="Current signature" style="max-height:52px;display:block;"></div>
                        <label class="checkbox"><input type="checkbox" name="remove_signature" value="1"> Remove the current signature</label>
                    <?php endif; ?>
                    <input class="form-control" type="file" id="mcSigImg" name="membership_card_signature" accept="image/*">
                    <small class="form-hint">PNG with a transparent background reads best. Leave empty to keep the current one.</small></div>
            </div></div>

            <!-- Social marks -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Social marks</h3>
                <?php if (!$cardPlatforms): ?>
                    <p class="text-muted" style="font-size:.9rem;margin:0;">
                        No active social links yet, so the card omits the “Follow us” block.
                        Add them under <a href="<?= e(admin_url('social-links')) ?>">Social Links</a>.
                    </p>
                <?php else: ?>
                    <div class="mcs-seg mb-3">
                        <label><input type="radio" name="card_social_mode" value="all" <?= $cfg['socials'] === '' ? 'checked' : '' ?>><span>Every active link</span></label>
                        <label><input type="radio" name="card_social_mode" value="pick" <?= $cfg['socials'] !== '' ? 'checked' : '' ?>><span>Only the ones I pick</span></label>
                    </div>
                    <div class="mcs-fields">
                        <?php foreach ($cardPlatforms as $slug): ?>
                            <label class="checkbox"><input type="checkbox" name="card_socials[]" value="<?= e($slug) ?>"
                                <?= ((!$cfg['social_none'] && $cfg['social_list'] === []) || in_array($slug, $cfg['social_list'], true)) ? 'checked' : '' ?>>
                                <?= social_svg($slug, 'soc-ico') ?> <?= e(ucfirst($slug)) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <small class="form-hint">“Every active link” follows Social Links, so a platform added there later appears on the card on its own.</small>
                <?php endif; ?>
            </div></div>
        </div>

        <!-- ========================================================= PREVIEW -->
        <div class="mcs-side">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-1">Live preview</h3>
                <p class="text-muted mb-3" style="font-size:.82rem;">
                    A real member's card, rendered with the <strong>saved</strong> settings —
                    save to see edits. Flip it to check the back.
                </p>
                <div class="mcs-seg mb-3">
                    <?php foreach ($cardTemplates as $key => $labelText):
                        $short = preg_split('/\s+—\s+/u', $labelText, 2)[0] ?? $key; ?>
                        <a class="btn <?= $previewTpl === $key ? 'btn-primary' : 'btn-outline' ?> btn-sm"
                           href="<?= e(admin_url('membership-settings?tpl=' . urlencode($key))) ?>#preview"><?= e($short) ?></a>
                    <?php endforeach; ?>
                </div>
                <div id="preview">
                <?php if ($preview): ?>
                    <?= card_html($preview, $previewTpl) ?>
                    <p class="text-muted" style="font-size:.78rem;margin:.75rem 0 0;">
                        <?= e($preview['name'] ?? 'Member') ?> ·
                        <a href="<?= e(admin_url('member-card?id=' . (int) $preview['id'])) ?>">open the print page</a>
                    </p>
                <?php elseif (function_exists('admin_empty_state')): ?>
                    <?= admin_empty_state('No members yet', 'The preview needs one member row to render a real card.', [], 'users') ?>
                <?php else: ?>
                    <p class="text-muted" style="font-size:.88rem;margin:0;">The preview needs one member row to render a real card.</p>
                <?php endif; ?>
                </div>
            </div></div>

            <!-- Contrast readout for the current combination -->
            <div class="panel"><div class="panel-body">
                <h3 class="mb-2">Contrast check</h3>
                <?php
                /* Every pair the card actually renders, not the nine that were
                   easiest to list. What was missing and why it mattered:
                     - head2: the header is a GRADIENT, and only head1 was checked,
                       so the far end of it was never measured;
                     - the header's radial highlight, composited over head1/head2 —
                       the real worst case on the card (the eyebrow measures 5.66:1
                       there, not the 7.95:1 this table used to report);
                     - all six STATUS chips, which carry their own fixed fills and
                       are exactly the pairs that had been failing;
                     - the status fill against the CARD BODY, which is the check
                       nobody was doing: on the charcoal templates the pill was
                       measuring 1.00:1 against the card and disappearing. */
                $t = card_theme($previewTpl);
                $sheen = static function (string $bg): string {
                    // rgba(255,233,135,.18) over $bg, blended in gamma sRGB as CSS does.
                    $bg = ltrim($bg, '#');
                    $mix = static fn (int $o, int $u): int => (int) round(0.18 * $o + 0.82 * $u);
                    return sprintf('#%02X%02X%02X',
                        $mix(255, (int) hexdec(substr($bg, 0, 2))),
                        $mix(233, (int) hexdec(substr($bg, 2, 2))),
                        $mix(135, (int) hexdec(substr($bg, 4, 2))));
                };
                $checks = [
                    'Organisation name on header'      => [$t['head1'], $t['headInk']],
                    'Organisation name, gradient end'  => [$t['head2'], $t['headInk']],
                    'Organisation name over highlight' => [$sheen($t['head1']), $t['headInk']],
                    'Tagline / eyebrow type'           => [$t['head1'], $t['headSub']],
                    'Eyebrow, gradient end'            => [$t['head2'], $t['headSub']],
                    'Eyebrow over highlight'           => [$sheen($t['head1']), $t['headSub']],
                    'Member name on body'              => [$t['body'],  $t['ink']],
                    'Field labels'                     => [$t['body'],  $t['label']],
                    'Tier chip'                        => [$t['chipBg'], $t['chipInk']],
                    'Member ID plaque'                 => [$t['plaque'], $t['plaqueInk']],
                    'Member ID label'                  => [$t['plaque'], $t['plaqueLabel']],
                    'Benefits strip'                   => [$t['panel'], $t['panelInk']],
                    'Footer copy'                      => [$t['foot'],  $t['footInk']],
                    'Footer eyebrow / social marks'    => [$t['foot'],  $t['footSub']],
                    'QR modules on plate'              => [$t['qrPlate'], $t['qrDark']],
                ];
                foreach (['active', 'pending', 'expired', 'suspended', 'cancelled', 'none'] as $sKey) {
                    $sm = card_status_meta($sKey);
                    $checks['Status “' . $sm['label'] . '” — text on pill'] = [$sm['bg'], $sm['ink']];
                    /* Fill vs card. Below 3:1 the pill needs its edge, and
                       card_status_edge() draws one — so the column reports the
                       delineation that will actually be rendered. */
                    $edge = card_status_edge($sm, $t['body'], $t['chipBg']);
                    $checks['Status “' . $sm['label'] . '” — pill' . ($edge !== '' ? ' edge' : '') . ' vs card']
                        = $edge !== '' ? [$t['body'], $edge] : [$t['body'], $sm['bg']];
                }
                ?>
                <table class="mcs-ratios">
                    <thead><tr><th>Pair</th><th>Ratio</th></tr></thead>
                    <tbody>
                    <?php foreach ($checks as $whatLabel => [$bg, $fg]):
                        /* Non-text delineation (a pill edge against the card) is a
                           1.4.11 check at 3:1; text is 1.4.3 at 4.5:1. */
                        $isEdge = str_contains($whatLabel, 'vs card');
                        $min    = $isEdge ? 3.0 : 4.5;
                        $ratio  = card_contrast($bg, $fg);
                        $pass   = $ratio >= $min; ?>
                        <tr>
                            <td><?= $whatLabel /* labels are literals from this file, curly quotes included */ ?>
                                <span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:<?= e($bg) ?>;border:1px solid rgba(0,0,0,.2);"></span>
                                <span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:<?= e($fg) ?>;border:1px solid rgba(0,0,0,.2);"></span>
                            </td>
                            <?php /* A failing row used to be marked only by the ABSENCE of a class,
                                     so it looked identical to a passing one. It now says so. */ ?>
                            <td class="<?= $pass ? 'mcs-pass' : 'mcs-fail' ?>">
                                <?= number_format($ratio, 2) ?>:1
                                <?php if (!$pass): ?><strong>FAIL (needs <?= number_format($min, 1) ?>:1)</strong><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <small class="form-hint">
                    WCAG AA needs 4.5:1 for text and 3:1 for a boundary that identifies a component.
                    Text inks are derived from the background actually chosen, so no colour choice can
                    fail those rows; the status pills carry fixed fills and are measured here too.
                </small>
            </div></div>
        </div>
    </div>

    <!-- ============================================== MODULE (non-card) -->
    <div class="grid-2" style="align-items:start;gap:1.25rem;margin-top:1.25rem;">
        <!-- General + reminders -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">General &amp; reminders</h3>
            <div class="form-group"><label class="form-label" for="mcPrefix">Membership ID prefix</label>
                <input class="form-control" id="mcPrefix" name="membership_code_prefix" value="<?= e(get_setting('membership_code_prefix', 'EIF')) ?>">
                <small class="form-hint">e.g. EIF → EIF-2026-00042</small></div>
            <div class="form-group"><label class="form-label" for="mcDays">Reminder days before expiry</label>
                <input class="form-control" id="mcDays" name="membership_reminder_days" value="<?= e(get_setting('membership_reminder_days', '30,15,7')) ?>">
                <small class="form-hint">Comma-separated, e.g. 30,15,7</small></div>
            <label class="checkbox"><input type="checkbox" name="membership_reminders_enabled" value="1" <?= (int) get_setting('membership_reminders_enabled', '1') === 1 ? 'checked' : '' ?>> Send expiry reminders</label>
        </div></div>

        <!-- Cashfree -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Cashfree payments</h3>
            <label class="checkbox mb-2"><input type="checkbox" name="cashfree_enabled" value="1" <?= (int) get_setting('cashfree_enabled', '0') === 1 ? 'checked' : '' ?>> Enable online renewals via Cashfree</label>
            <div class="form-group"><label class="form-label">Environment</label>
                <select class="form-select" name="cashfree_env">
                    <option value="sandbox" <?= get_setting('cashfree_env', 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (test)</option>
                    <option value="production" <?= get_setting('cashfree_env', 'sandbox') === 'production' ? 'selected' : '' ?>>Production (live)</option>
                </select></div>
            <div class="form-group"><label class="form-label">App ID</label>
                <input class="form-control" name="cashfree_app_id" value="<?= e(get_setting('cashfree_app_id', '')) ?>"></div>
            <div class="form-group"><label class="form-label">Secret key</label>
                <input class="form-control" type="password" name="cashfree_secret_key" placeholder="<?= e($mask('cashfree_secret_key') ?: 'Enter secret key') ?>"></div>
            <div class="form-group"><label class="form-label">Webhook secret</label>
                <input class="form-control" type="password" name="cashfree_webhook_secret" placeholder="<?= e($mask('cashfree_webhook_secret') ?: 'Optional (defaults to secret key)') ?>"></div>
            <small class="form-hint">Webhook URL: <code><?= e(abs_url('api/v1/cashfree-webhook')) ?></code></small>
        </div></div>

        <!-- SMS -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">SMS notifications</h3>
            <label class="checkbox mb-2"><input type="checkbox" name="sms_enabled" value="1" <?= (int) get_setting('sms_enabled', '0') === 1 ? 'checked' : '' ?>> Enable SMS reminders</label>
            <div class="form-group"><label class="form-label">Provider</label>
                <select class="form-select" name="sms_provider">
                    <option value="msg91" <?= get_setting('sms_provider', 'msg91') === 'msg91' ? 'selected' : '' ?>>MSG91</option>
                    <option value="fast2sms" <?= get_setting('sms_provider', 'msg91') === 'fast2sms' ? 'selected' : '' ?>>Fast2SMS</option>
                </select></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">MSG91 Auth Key</label>
                    <input class="form-control" type="password" name="msg91_authkey" placeholder="<?= e($mask('msg91_authkey') ?: 'Auth key') ?>"></div>
                <div class="form-group"><label class="form-label">MSG91 Sender ID</label>
                    <input class="form-control" name="msg91_sender" value="<?= e(get_setting('msg91_sender', '')) ?>"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">MSG91 DLT template id</label>
                    <input class="form-control" name="msg91_dlt_template_id" value="<?= e(get_setting('msg91_dlt_template_id', '')) ?>"></div>
                <div class="form-group"><label class="form-label">MSG91 route</label>
                    <input class="form-control" name="msg91_route" value="<?= e(get_setting('msg91_route', '4')) ?>"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Fast2SMS API key</label>
                    <input class="form-control" type="password" name="fast2sms_key" placeholder="<?= e($mask('fast2sms_key') ?: 'API key') ?>"></div>
                <div class="form-group"><label class="form-label">Fast2SMS sender</label>
                    <input class="form-control" name="fast2sms_sender" value="<?= e(get_setting('fast2sms_sender', '')) ?>"></div>
            </div>
        </div></div>

        <!-- Cron -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Reminder scheduler (cron)</h3>
            <p class="text-muted" style="font-size:.9rem;">Run this daily to send expiry reminders. Use Windows Task Scheduler locally, or a cron job / cron-URL service on your host.</p>
            <div class="form-group"><label class="form-label">CLI command (recommended)</label>
                <?php
                /* Use the interpreter actually running this request rather than a
                   hardcoded C:/xampp path, so the copy-paste command is correct on
                   the server it is being read on (Linux hosting included). */
                $phpBin = PHP_BINARY !== '' ? PHP_BINARY : 'php';
                $cronCmd = '"' . str_replace('/', DIRECTORY_SEPARATOR, $phpBin) . '" "' . $cronPath . '"';
                ?>
                <input class="form-control" readonly value="<?= e($cronCmd) ?>" onclick="this.select()"></div>
            <div class="form-group"><label class="form-label">Or trigger by URL (token-protected)</label>
                <input class="form-control" readonly value="<?= e($cronUrl) ?>" onclick="this.select()"></div>
            <label class="checkbox"><input type="checkbox" name="regen_token" value="1"> Regenerate the cron token</label>
        </div></div>
    </div>

    <div class="form-actions" style="margin-top:1rem;">
        <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save settings</button>
    </div>
</form>

<!-- Test SMS -->
<div class="panel" style="margin-top:1rem;"><div class="panel-body">
    <h3 class="mb-2">Send a test SMS</h3>
    <form method="post" action="<?= e(admin_url('membership-settings')) ?>" class="flex" style="gap:.5rem;align-items:end;">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="test_sms">
        <div class="form-group" style="margin:0;"><label class="form-label">Mobile number</label>
            <input class="form-control" name="test_number" placeholder="10-digit mobile" required></div>
        <button class="btn btn-outline" type="submit" <?= sms_enabled() ? '' : 'disabled title="Enable SMS and save first"' ?>><?= lucide('send') ?> Send test</button>
    </form>
</div></div>

<?= card_html_assets() ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
