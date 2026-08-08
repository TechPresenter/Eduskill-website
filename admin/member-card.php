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
$template = (string) get('template', (string) get_setting('membership_card_template', 'classic'));
if (!array_key_exists($template, card_templates())) {
    $template = 'classic';
}

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
        body{margin:0;min-height:100vh;background:#eef2f7;font-family:system-ui,Segoe UI,Roboto,sans-serif;
             display:flex;flex-direction:column;align-items:center;justify-content:center;gap:22px;padding:30px;}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
        .btn{border:0;border-radius:9px;padding:10px 18px;font-weight:700;cursor:pointer;text-decoration:none;
             font-size:.92rem;display:inline-flex;align-items:center;gap:6px;}
        .btn-p{background:#063566;color:#fff;} .btn-o{background:#fff;color:#1f2937;border:1px solid #cbd5e1;}
        select{padding:9px 12px;border-radius:9px;border:1px solid #cbd5e1;font-weight:600;}
        @media print{ .toolbar{display:none;} body{background:#fff;} }
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
</body>
</html>
