<?php
/**
 * =============================================================================
 *  Admin — Global Theme Settings
 * =============================================================================
 *  One console that restyles the entire platform. Edits are saved as a DRAFT,
 *  previewed live in an embedded viewport, then PUBLISHED. Every publish
 *  auto-snapshots the previous state so it can be rolled back.
 *
 *  Backed by includes/theme.php (token catalogue + CSS-variable emitter), so
 *  changing a token here instantly restyles the public site, the admin panel,
 *  the auth pages and outgoing email — with no code changes.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

theme_seed_presets();

$groups = theme_tokens();
$map    = theme_token_map();

/* ------------------------------------------------------------------ Actions */
if (is_post()) {
    require_csrf();
    $do = post('_do');

    if ($do === 'save-draft' || $do === 'publish') {
        foreach ($map as $token => $meta) {
            $fieldName = 'tk_' . str_replace('.', '__', $token);
            if ($meta['type'] === 'bool') {
                if (array_key_exists('sec_' . $meta['group'], $_POST)) {   // only for the visible section
                    theme_set($token, post($fieldName) ? '1' : '0', true);
                }
                continue;
            }
            if ($meta['type'] === 'image' || !array_key_exists($fieldName, $_POST)) { continue; }
            $val = (string) post($fieldName, '');
            if ($meta['type'] === 'color'  && $val !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $val)) { continue; }
            if ($meta['type'] === 'number' && $val !== '' && !is_numeric($val)) { continue; }
            theme_set($token, $val, true);
        }
        // Image uploads (logo / favicon / auth background …)
        foreach ($map as $token => $meta) {
            if ($meta['type'] !== 'image') { continue; }
            $fieldName = 'tk_' . str_replace('.', '__', $token);
            if (!empty($_FILES[$fieldName]['name'])) {
                $up = upload_image($_FILES[$fieldName], 'branding');
                if ($up['success']) { theme_set($token, $up['path'], true); }
            }
        }
        if ($do === 'publish') {
            theme_publish();
            set_flash('success', 'Theme published — live across the site, admin panel and emails.');
        } else {
            log_activity('update', 'theme', 'Saved theme draft');
            set_flash('success', 'Draft saved. Preview it, then Publish when you are happy.');
        }
        redirect('/admin/theme?sec=' . urlencode((string) post('sec', 'colors')));
    }

    if ($do === 'discard')  { theme_discard_draft(); set_flash('success', 'Draft discarded.'); redirect('/admin/theme'); }
    if ($do === 'reset')    { theme_reset();         set_flash('success', 'Theme reset to defaults.'); redirect('/admin/theme'); }
    if ($do === 'snapshot') { theme_snapshot(clean(post('label', '')) ?: null); set_flash('success', 'Backup created.'); redirect('/admin/theme?sec=history'); }

    if ($do === 'rollback') {
        $ok = theme_rollback((int) post('id', 0));
        set_flash($ok ? 'success' : 'error', $ok ? 'Theme restored from that version.' : 'That version could not be restored.');
        redirect('/admin/theme?sec=history');
    }
    if ($do === 'apply-preset') {
        theme_apply_preset((int) post('id', 0), true);
        set_flash('success', 'Preset loaded as a draft — preview it, then Publish.');
        redirect('/admin/theme');
    }
    if ($do === 'save-preset') {
        theme_save_preset(clean(post('name', '')) ?: 'Custom theme');
        set_flash('success', 'Saved as a new theme preset.');
        redirect('/admin/theme?sec=presets');
    }
    if ($do === 'delete-preset') {
        $p = find('theme_presets', (int) post('id', 0));
        if ($p && empty($p['is_builtin'])) { db_delete('theme_presets', 'id = :id', [':id' => (int) $p['id']]); }
        set_flash('success', 'Preset deleted.');
        redirect('/admin/theme?sec=presets');
    }
    if ($do === 'import') {
        $json = (string) post('payload', '');
        if (!empty($_FILES['file']['tmp_name'])) { $json = (string) file_get_contents($_FILES['file']['tmp_name']); }
        $r = theme_import($json, true);
        set_flash($r['ok'] ? 'success' : 'error', $r['message']);
        redirect('/admin/theme');
    }
}

/* Export endpoint */
if (get('action') === 'export') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="theme-' . date('Ymd-His') . '.json"');
    echo theme_export();
    exit;
}

/* -------------------------------------------------------------- View data   */
$sec        = (string) get('sec', 'colors');
$vals       = theme_all(true);          // draft-over-live for the editor
$hasDraft   = theme_has_draft();
$presets    = theme_presets();
$versions   = theme_versions(20);
$page_title = 'Theme Settings';

$fontChoices = ['Plus Jakarta Sans','Inter','Outfit','Manrope','Poppins','Montserrat','Lato','Roboto','Open Sans','Nunito','Raleway','Work Sans','DM Sans','Sora'];

/** Render one editor field. */
$field = function (string $token, array $m) use ($vals, $fontChoices): void {
    $id  = 'tk_' . str_replace('.', '__', $token);
    $val = (string) ($vals[$token] ?? '');
    echo '<div class="tf" data-search="' . e(strtolower($m['label'] . ' ' . $token)) . '">';
    echo '<label class="form-label" for="' . e($id) . '">' . e($m['label']) . '</label>';
    switch ($m['type']) {
        case 'color':
            echo '<div class="tf-color"><input type="color" id="' . e($id) . '" name="' . e($id) . '" value="' . e($val ?: '#000000') . '">'
               . '<input class="form-control tf-hex" type="text" value="' . e(strtoupper($val)) . '" data-hex-for="' . e($id) . '" spellcheck="false"></div>';
            break;
        case 'font':
            echo '<select class="form-select" id="' . e($id) . '" name="' . e($id) . '">';
            foreach ($fontChoices as $f) {
                echo '<option value="' . e($f) . '"' . ($val === $f ? ' selected' : '') . ' style="font-family:\'' . e($f) . '\',sans-serif">' . e($f) . '</option>';
            }
            echo '</select>';
            break;
        case 'select':
            echo '<select class="form-select" id="' . e($id) . '" name="' . e($id) . '">';
            foreach (($m['options'] ?? []) as $o) {
                echo '<option value="' . e((string) $o) . '"' . ($val === (string) $o ? ' selected' : '') . '>' . e(ucfirst((string) $o)) . '</option>';
            }
            echo '</select>';
            break;
        case 'bool':
            echo '<label class="switch tf-switch"><input type="checkbox" id="' . e($id) . '" name="' . e($id) . '" value="1"' . ($val === '1' ? ' checked' : '') . '>'
               . '<span class="switch-track"><span class="switch-thumb"></span></span></label>';
            break;
        case 'number':
            echo '<div class="tf-range"><input type="range" min="' . e((string) ($m['min'] ?? 0)) . '" max="' . e((string) ($m['max'] ?? 100)) . '" step="' . e((string) ($m['step'] ?? 1)) . '" value="' . e($val) . '" data-range-for="' . e($id) . '">'
               . '<input class="form-control tf-num" type="number" id="' . e($id) . '" name="' . e($id) . '" value="' . e($val) . '" min="' . e((string) ($m['min'] ?? 0)) . '" max="' . e((string) ($m['max'] ?? 100)) . '" step="' . e((string) ($m['step'] ?? 1)) . '">'
               . '<span class="tf-unit">' . e((string) ($m['unit'] ?? '')) . '</span></div>';
            break;
        case 'image':
            echo '<div class="tf-img">';
            if ($val !== '') { echo '<img src="' . e(upload_url($val)) . '" alt=""> '; }
            echo '<input class="form-control" type="file" name="' . e($id) . '" accept="image/*"></div>';
            break;
        case 'code':
            echo '<textarea class="form-textarea tf-code" id="' . e($id) . '" name="' . e($id) . '" rows="8" spellcheck="false">' . e($val) . '</textarea>';
            break;
        case 'textarea':
            echo '<textarea class="form-textarea" id="' . e($id) . '" name="' . e($id) . '" rows="3">' . e($val) . '</textarea>';
            break;
        default:
            echo '<input class="form-control" type="text" id="' . e($id) . '" name="' . e($id) . '" value="' . e($val) . '">';
    }
    if (!empty($m['hint'])) { echo '<span class="form-hint">' . e($m['hint']) . '</span>'; }
    echo '</div>';
};

include __DIR__ . '/partials/head.php';
?>
<style>
/* Theme console layout. Panels, buttons, pills, form controls and tables come
   from the shared admin layers; what stays here is the three-column shell, the
   token-editor grid, and the swatch / contrast previews (which legitimately
   need inline colour, because they PREVIEW the value being edited). */
.th-wrap{max-width:1500px}
.th-bar{display:flex;align-items:center;gap:var(--sp-4);flex-wrap:wrap;padding:var(--sp-3) var(--sp-4);margin-bottom:var(--sp-5);
    border-radius:var(--r-lg);background:var(--surface);border:1px solid var(--border);box-shadow:var(--elev-1);position:sticky;top:74px;z-index:30}
.th-bar h1{margin:0;font-size:1.15rem;font-weight:800;display:flex;align-items:center;gap:var(--sp-2)}
.th-bar h1 svg{color:var(--brand-600,#0B4E3D)}
.th-actions{margin-left:auto;display:flex;gap:var(--sp-2);flex-wrap:wrap}
.th-grid{display:grid;grid-template-columns:212px 1fr 400px;gap:var(--sp-5);align-items:start}

/* Section rail */
.th-rail{position:sticky;top:150px;display:flex;flex-direction:column;gap:2px;padding:var(--sp-2);
    background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg)}
.th-rail a{display:flex;align-items:center;gap:var(--sp-2);padding:var(--sp-2) var(--sp-3);border-radius:var(--r-sm);
    color:var(--text-soft);font-size:.88rem;font-weight:650;text-decoration:none;
    transition:background-color var(--dur-1) var(--ease),color var(--dur-1) var(--ease)}
.th-rail a:hover{background:var(--surface-2);color:var(--text)}
.th-rail a.on{background:var(--brand-600,#0B4E3D);color:#fff;box-shadow:var(--elev-1)}
.th-rail a svg{width:1.05em;height:1.05em}
.th-search{margin-bottom:var(--sp-1)}
.th-search .form-control{font-size:.85rem;padding:var(--sp-2) var(--sp-3);background:var(--surface-2)}

/* Token editor */
.th-sub{color:var(--muted);font-size:.86rem;margin:var(--sp-1) 0 var(--sp-5)}
.th-stack{margin-top:var(--sp-5)}
.th-inline-input{max-width:260px}
.th-bare-actions{margin:0 0 var(--sp-4);padding:0;border:0}
.tf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:var(--sp-4) var(--sp-5)}
.tf{display:flex;flex-direction:column;gap:var(--sp-1)}
.tf > .form-label{margin-bottom:0;font-size:.82rem}
.tf > .form-hint{margin-top:0;font-size:.74rem}
.tf-color{display:flex;gap:var(--sp-2);align-items:center}
/* The colour input and its hex field ARE the preview of the token value. */
.tf-color input[type=color]{width:46px;height:38px;border:1px solid var(--border);border-radius:var(--r-sm);padding:2px;background:var(--surface);cursor:pointer}
.tf-color .tf-hex{flex:1;min-width:0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.82rem;text-transform:uppercase;padding:var(--sp-2) var(--sp-3)}
.tf-range{display:flex;align-items:center;gap:var(--sp-2)}
.tf-range input[type=range]{flex:1;accent-color:var(--brand-600,#0B4E3D)}
/* qualified so it beats the shared `.admin-content .form-control{width:100%}` */
.tf-range .tf-num{width:74px;flex:0 0 auto;font-size:.82rem;padding:var(--sp-1) var(--sp-2)}
.tf-unit{font-size:.75rem;color:var(--muted);min-width:24px}
.tf-img img{display:block;max-height:54px;padding:var(--sp-1);margin-bottom:var(--sp-1);
    border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface-2)}
.tf-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.82rem}
.tf-switch{padding:0;border:0;background:transparent;display:inline-flex;width:max-content}

/* Live preview */
.th-prev{position:sticky;top:150px}
.th-prev-head{display:flex;align-items:center;gap:var(--sp-1);margin-bottom:var(--sp-2)}
.th-prev-head strong{font-size:.85rem;margin-right:auto;display:inline-flex;align-items:center;gap:var(--sp-1)}
.th-dev{width:32px;height:32px}
.th-dev.on{background:var(--brand-600,#0B4E3D);color:#fff;border-color:transparent}
.th-dev svg{width:15px;height:15px}
.th-frame{border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;background:var(--surface);
    box-shadow:var(--elev-2);transition:max-width var(--dur-3) var(--ease);margin-inline:auto}
.th-frame iframe{width:100%;height:540px;border:0;display:block;background:#fff}
.th-frame.tablet{max-width:330px}.th-frame.mobile{max-width:235px}

/* Contrast report — the dot previews the background token being measured. */
.th-contrast{margin-top:var(--sp-4);border-top:1px solid var(--border);padding-top:var(--sp-3)}
.th-contrast-title{font-size:.82rem;display:block;margin-bottom:var(--sp-1)}
.th-cc{display:flex;align-items:center;gap:var(--sp-2);font-size:.8rem;padding:var(--sp-1) 0}
.th-cc .dot{width:26px;height:18px;border-radius:var(--r-sm);border:1px solid var(--border)}
.th-cc .grade{margin-left:auto;font-weight:800;padding:var(--sp-1) var(--sp-2);border-radius:var(--r-sm);font-size:.7rem}
.grade-AAA,.grade-AA{background:color-mix(in srgb,var(--st-ok) 16%,transparent);color:var(--st-ok)}
.grade-AALarge{background:color-mix(in srgb,var(--st-warn) 16%,transparent);color:var(--st-warn)}
.grade-Fail{background:color-mix(in srgb,var(--st-err) 14%,transparent);color:var(--st-err)}

/* Preset cards — the swatch strip previews the preset's colour tokens. */
.th-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(196px,1fr));gap:var(--sp-3)}
.th-card{border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;background:var(--surface);
    transition:border-color var(--dur-1) var(--ease)}
.th-card:hover{border-color:var(--muted)}
.th-swatches{height:60px;display:flex}
.th-swatches i{flex:1}
.th-card-foot{padding:var(--sp-2) var(--sp-3);display:flex;align-items:center;gap:var(--sp-1);flex-wrap:wrap}
.th-card-foot b{font-size:.84rem;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
/* Below 1400px the preview moves BELOW the editor instead of being hidden —
   hiding it also prevented the lazy iframe from ever loading. */
@media (max-width:1400px){
    .th-grid{grid-template-columns:212px 1fr}
    .th-prev{grid-column:1 / -1;position:static;margin-top:var(--sp-5)}
    .th-frame iframe{height:460px}
}
@media (max-width:900px){.th-grid{grid-template-columns:1fr}.th-rail{position:static;flex-direction:row;overflow-x:auto}}
</style>

<div class="admin-content th-wrap">
<form id="themeForm" method="post" action="<?= e(admin_url('theme')) ?>" enctype="multipart/form-data">
<?= csrf_field() ?>
<input type="hidden" name="sec" value="<?= e($sec) ?>">
<input type="hidden" name="_do" id="themeDo" value="save-draft">
<?php if (isset($groups[$sec])): ?><input type="hidden" name="sec_<?= e($sec) ?>" value="1"><?php endif; ?>
<input type="hidden" name="id" id="rowId" value="">

<div class="th-bar">
    <h1><?= lucide('palette') ?> Theme Settings</h1>
    <?php if ($hasDraft): ?><span class="pill pill-amber">Unpublished draft</span><?php endif; ?>
    <div class="th-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('theme?action=export')) ?>"><?= lucide('download') ?> Export</a>
        <a class="btn btn-secondary btn-sm" href="<?= e(url('/')) ?>" target="_blank"><?= lucide('external-link') ?> View site</a>
        <?php if ($hasDraft): ?>
            <button class="btn btn-ghost btn-sm" type="submit" formnovalidate data-do="discard"><?= lucide('undo-2') ?> Discard</button>
        <?php endif; ?>
        <button class="btn btn-secondary btn-sm" type="submit" data-do="save-draft"><?= lucide('save') ?> Save draft</button>
        <button class="btn btn-primary btn-sm" type="submit" data-do="publish"><?= lucide('rocket') ?> Publish</button>
    </div>
</div>

<div class="th-grid">
    <nav class="th-rail">
        <div class="th-search"><input class="form-control" type="search" id="thSearch" placeholder="Search settings…" aria-label="Search settings"></div>
        <?php foreach ($groups as $gk => $g): ?>
            <a class="<?= $sec === $gk ? 'on' : '' ?>" href="<?= e(admin_url('theme?sec=' . $gk)) ?>"><?= lucide($g['icon']) ?> <?= e($g['label']) ?></a>
        <?php endforeach; ?>
        <a class="<?= $sec === 'presets' ? 'on' : '' ?>" href="<?= e(admin_url('theme?sec=presets')) ?>"><?= lucide('layout-grid') ?> Themes</a>
        <a class="<?= $sec === 'history' ? 'on' : '' ?>" href="<?= e(admin_url('theme?sec=history')) ?>"><?= lucide('history') ?> History</a>
    </nav>

    <div>
        <?php if (isset($groups[$sec])): $g = $groups[$sec]; ?>
            <div class="panel"><div class="panel-body">
                <h2 class="panel-title"><?= lucide($g['icon']) ?> <?= e($g['label']) ?></h2>
                <p class="th-sub">Saved as a draft and shown in the live preview. Publish to push it across the whole platform.</p>
                <div class="tf-grid"><?php foreach ($g['tokens'] as $token => $m) { $field($token, $m); } ?></div>
            </div></div>

        <?php elseif ($sec === 'presets'): ?>
            <div class="panel"><div class="panel-body">
                <h2 class="panel-title"><?= lucide('layout-grid') ?> Themes</h2>
                <p class="th-sub">Apply a ready-made theme, or save your current design as a reusable preset.</p>
                <div class="th-cards">
                    <?php foreach ($presets as $p): $pv = json_decode((string) $p['payload'], true) ?: []; ?>
                        <div class="th-card">
                            <div class="th-swatches">
                                <?php foreach (['color.primary','color.secondary','color.accent','color.success'] as $sw): ?>
                                    <i style="background:<?= e($pv[$sw] ?? 'var(--border)') ?>"></i>
                                <?php endforeach; ?>
                            </div>
                            <div class="th-card-foot">
                                <b><?= e($p['name']) ?></b>
                                <?php if (!empty($p['is_active'])): ?><span class="pill pill-green">Active</span><?php endif; ?>
                                <button class="btn btn-primary btn-sm" type="submit" formnovalidate data-do="apply-preset" data-id="<?= (int) $p['id'] ?>">Apply</button>
                                <?php if (empty($p['is_builtin'])): ?>
                                    <button class="icon-btn danger" type="submit" formnovalidate title="Delete" data-do="delete-preset" data-id="<?= (int) $p['id'] ?>"><?= lucide('trash-2') ?></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions">
                    <input class="form-control th-inline-input" name="name" placeholder="Name this theme…">
                    <button class="btn btn-secondary" type="submit" formnovalidate data-do="save-preset"><?= lucide('plus') ?> Save current as preset</button>
                </div>
            </div></div>

            <div class="panel th-stack"><div class="panel-body">
                <h2 class="panel-title"><?= lucide('upload') ?> Import / Reset</h2>
                <p class="th-sub">Import a theme JSON (loaded as a draft so you can preview it), or reset everything to the shipped defaults.</p>
                <div class="tf-grid">
                    <div class="tf"><label class="form-label">Theme JSON file</label><input class="form-control" type="file" name="file" accept="application/json,.json"></div>
                    <div class="tf"><label class="form-label">…or paste JSON</label><textarea class="form-textarea" name="payload" rows="3" spellcheck="false"></textarea></div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-secondary" type="submit" formnovalidate data-do="import"><?= lucide('upload') ?> Import</button>
                    <button class="btn btn-danger" type="submit" formnovalidate data-do="reset" data-confirm-reset><?= lucide('rotate-ccw') ?> Reset to defaults</button>
                </div>
            </div></div>

        <?php elseif ($sec === 'history'): ?>
            <div class="panel"><div class="panel-body">
                <h2 class="panel-title"><?= lucide('history') ?> Version History</h2>
                <p class="th-sub">Every publish, rollback and reset auto-snapshots the previous theme so you can restore it.</p>
                <div class="form-actions th-bare-actions">
                    <input class="form-control th-inline-input" name="label" placeholder="Backup label…">
                    <button class="btn btn-secondary btn-sm" type="submit" formnovalidate data-do="snapshot"><?= lucide('save') ?> Create backup</button>
                </div>
                <?php if ($versions): ?>
                    <div class="table-wrap"><table class="admin-table">
                        <thead><tr><th>Version</th><th>Type</th><th>When</th><th class="num">Restore</th></tr></thead>
                        <tbody>
                        <?php foreach ($versions as $vv): ?>
                            <tr>
                                <td><strong><?= e((string) $vv['label']) ?></strong></td>
                                <td><span class="pill <?= 'pill-tag' ?>"><?= $vv['is_auto'] ? 'auto' : 'manual' ?></span></td>
                                <td><small class="text-muted"><?= e(format_datetime($vv['created_at'])) ?></small></td>
                                <td class="num">
                                    <button class="btn btn-outline btn-sm" type="submit" formnovalidate data-do="rollback" data-id="<?= (int) $vv['id'] ?>"><?= lucide('undo-2') ?> Restore</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                <?php else: ?>
                    <div class="empty-state"><div class="icon"><?= lucide('history') ?></div>No versions yet — publish a change to create the first snapshot.</div>
                <?php endif; ?>
            </div></div>
        <?php endif; ?>
    </div>

    <aside class="th-prev">
        <div class="th-prev-head">
            <strong><?= lucide('eye') ?> Live preview</strong>
            <button class="icon-btn th-dev on" type="button" data-dev="desktop" title="Desktop"><?= lucide('monitor') ?></button>
            <button class="icon-btn th-dev" type="button" data-dev="tablet" title="Tablet"><?= lucide('tablet') ?></button>
            <button class="icon-btn th-dev" type="button" data-dev="mobile" title="Mobile"><?= lucide('smartphone') ?></button>
            <button class="icon-btn th-dev" type="button" id="thReload" title="Reload preview"><?= lucide('refresh-cw') ?></button>
        </div>
        <div class="th-frame" id="thFrame">
            <iframe id="thPreview" src="<?= e(url('/')) ?>" title="Theme preview"></iframe>
        </div>
        <div class="th-contrast">
            <strong class="th-contrast-title"><?= lucide('contrast') ?> WCAG contrast</strong>
            <?php
            $checks = [
                ['White on Primary',   '#FFFFFF', $vals['color.primary']   ?? '#0B4E3D'],
                ['White on Accent',    '#FFFFFF', $vals['color.accent']    ?? '#F15A24'],
                ['Body on Background', $vals['color.text_soft'] ?? '#374151', $vals['color.bg']      ?? '#FFFFFF'],
                ['Muted on Surface',   $vals['color.muted']     ?? '#6B7280', $vals['color.surface'] ?? '#FFFFFF'],
            ];
            foreach ($checks as [$lbl, $fg, $bg]): $c = theme_contrast((string) $fg, (string) $bg); ?>
                <div class="th-cc">
                    <span class="dot" style="background:<?= e((string) $bg) ?>"></span>
                    <span><?= e($lbl) ?></span>
                    <span class="grade grade-<?= e(str_replace(' ', '', $c['grade'])) ?>"><?= e((string) $c['ratio']) ?> · <?= e($c['grade']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>
</div>
</form>
</div>

<script>
(function () {
    var form = document.getElementById('themeForm'),
        doField = document.getElementById('themeDo'),
        idField = document.getElementById('rowId'),
        dirty = false;

    /* every submit button declares the action (and optional row id) */
    form.addEventListener('click', function (e) {
        var b = e.target.closest('[data-do]');
        if (!b) return;
        if (b.hasAttribute('data-confirm-reset') &&
            !confirm('Reset every theme token back to the shipped defaults? A backup is taken first.')) {
            e.preventDefault(); return;
        }
        doField.value = b.getAttribute('data-do');
        idField.value = b.getAttribute('data-id') || '';
        dirty = false;
    });

    /* hex <-> colour picker */
    document.querySelectorAll('.tf-color input[type=color]').forEach(function (c) {
        var hex = document.querySelector('[data-hex-for="' + c.id + '"]');
        c.addEventListener('input', function () { if (hex) hex.value = c.value.toUpperCase(); });
        if (hex) hex.addEventListener('change', function () {
            var v = hex.value.trim(); if (v && v[0] !== '#') v = '#' + v;
            if (/^#[0-9a-fA-F]{6}$/.test(v)) { c.value = v; } else { hex.value = c.value.toUpperCase(); }
        });
    });
    /* range <-> number */
    document.querySelectorAll('[data-range-for]').forEach(function (r) {
        var n = document.getElementById(r.getAttribute('data-range-for'));
        if (!n) return;
        r.addEventListener('input', function () { n.value = r.value; });
        n.addEventListener('input', function () { r.value = n.value; });
    });

    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    /* settings search */
    var s = document.getElementById('thSearch');
    if (s) s.addEventListener('input', function () {
        var q = s.value.toLowerCase().trim();
        document.querySelectorAll('.tf[data-search]').forEach(function (f) {
            f.style.display = (!q || f.dataset.search.indexOf(q) > -1) ? '' : 'none';
        });
    });

    /* responsive preview */
    var frame = document.getElementById('thFrame');
    document.querySelectorAll('.th-dev[data-dev]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.th-dev[data-dev]').forEach(function (x) { x.classList.remove('on'); });
            b.classList.add('on');
            frame.className = 'th-frame ' + (b.dataset.dev === 'desktop' ? '' : b.dataset.dev);
        });
    });
    var rl = document.getElementById('thReload');
    if (rl) rl.addEventListener('click', function () {
        var f = document.getElementById('thPreview');
        f.contentWindow.location.reload();
    });

    /* shortcuts: Ctrl+S = save draft, Ctrl+Enter = publish */
    document.addEventListener('keydown', function (e) {
        if (!(e.ctrlKey || e.metaKey)) return;
        if (e.key === 's')     { e.preventDefault(); doField.value = 'save-draft'; dirty = false; form.submit(); }
        if (e.key === 'Enter') { e.preventDefault(); doField.value = 'publish';    dirty = false; form.submit(); }
    });
})();
</script>
<?php include __DIR__ . '/partials/foot.php'; ?>
