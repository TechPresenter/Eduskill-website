<?php
/**
 * =============================================================================
 *  Admin — Membership card endpoint (PDF / PNG / printable page)
 * =============================================================================
 *  ?id=..&format=pdf|png|print&template=classic|modern|dark
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/membership_card.php';
require_admin();

$id       = (int) get('id', 0);
$format   = (string) get('format', 'print');
/* card_template_id() validates against card_templates() and resolves the
   pre-4-template 'dark' id, so an existing setting keeps working. */
$template = card_template_id((string) get('template', card_default_template()));

$member = find('members', $id);
if (!$member) {
    set_flash('error', 'Member not found.');
    redirect('/admin/members');
}
$member = member_ensure_identity($member);
$fileBase = 'membership-' . ($member['member_code'] ?: $id);

if ($format === 'pdf') {
    $pdf = card_pdf($member, $template);
    member_audit_log($id, 'card_downloaded', [], 'ID card PDF downloaded');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileBase . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

if ($format === 'png') {
    $png = card_png($member, $template);
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="' . $fileBase . '.png"');
    echo $png;
    exit;
}

/* -------- Printable HTML page (default) -------- */
$templates = card_templates();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Membership Card · <?= e($member['name']) ?></title>
    <style>
        /* Standalone by design — no app stylesheets, so print output carries no
           panel chrome. This page is only the toolbar and the field the card sits
           on; the card itself is rendered by card_html() in
           includes/membership_card.php. Palette written out by hand: the field was
           #eef2f7 and the outline buttons / select were #1f2937 on #cbd5e1, none
           of which is in the brand. Radii moved 9px -> 10px, the middle step. */
        body{margin:0;min-height:100vh;background:#F8FCF8;font-family:system-ui,Segoe UI,Roboto,sans-serif;
             display:flex;flex-direction:column;align-items:center;justify-content:center;gap:22px;padding:30px;}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
        .btn{border:0;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;text-decoration:none;
             font-size:.92rem;display:inline-flex;align-items:center;gap:6px;}
        .btn-p{background:#0B4E3D;color:#fff;} .btn-o{background:#fff;color:#151818;border:1px solid #C1CCB3;}
        select{padding:9px 12px;border-radius:10px;border:1px solid #C1CCB3;font-weight:600;}
        /* PRINT. This is the page the admin's "Card" button opens, i.e. the primary
           print path for staff, and its entire print stylesheet used to be
           "hide the toolbar, white background". Everything that makes a card print
           correctly — ID-1 width, print-color-adjust so the header/plaques/footer
           do not drop to white paper with pale text on them, hiding the
           3D-transformed back face that print rasterises unreliably, and hiding
           the flip button — is in the shared shell emitted by card_html(), so this
           page now inherits all of it. Only the toolbar and the page field are
           this file's business. */
        @media print{
            .toolbar{display:none;}
            body{background:#fff;padding:0;display:block;}
            @page{margin:12mm;}
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <label>Template
            <select onchange="location.href='?id=<?= $id ?>&format=print&template='+this.value">
                <?php foreach ($templates as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $k === $template ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-p" onclick="window.print()">Print</button>
        <a class="btn btn-o" href="?id=<?= $id ?>&format=pdf&template=<?= e($template) ?>">Download PDF</a>
        <a class="btn btn-o" href="?id=<?= $id ?>&format=png&template=<?= e($template) ?>" download>Download PNG</a>
        <a class="btn btn-o" href="<?= e(admin_url('members?action=view&id=' . $id)) ?>">← Back</a>
    </div>

    <?= card_html($member, $template) ?>
    <?= card_html_assets() ?>
</body>
</html>
