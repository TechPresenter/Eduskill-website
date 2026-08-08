<?php
/**
 * =============================================================================
 *  My Membership Card — member self-service (view / print / download)
 * =============================================================================
 *  Behind require_member(): only ever renders the signed-in member's own card,
 *  so no id is accepted from the request. ?format=pdf|png|print.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/membership_card.php';
require_member('/login');

$sess   = current_member();
$member = find('members', (int) ($sess['id'] ?? 0));
if (!$member) {
    redirect('/account');
}
$member   = member_ensure_identity($member);
$format   = (string) get('format', 'print');
$template = (string) get('template', (string) get_setting('membership_card_template', 'classic'));
if (!array_key_exists($template, card_templates())) {
    $template = 'classic';
}
$fileBase = 'membership-' . ($member['member_code'] ?: $member['id']);

if ($format === 'pdf') {
    $pdf = card_pdf($member, $template);
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

$templates = card_templates();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>My Membership Card · <?= e(get_setting('site_name', SITE_NAME)) ?></title>
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
            <select onchange="location.href='?format=print&template='+this.value">
                <?php foreach ($templates as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $k === $template ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-p" onclick="window.print()">Print</button>
        <a class="btn btn-o" href="?format=pdf&template=<?= e($template) ?>">Download PDF</a>
        <a class="btn btn-o" href="?format=png&template=<?= e($template) ?>" download>Download PNG</a>
        <a class="btn btn-o" href="<?= e(url('account')) ?>">← My account</a>
    </div>
    <?= card_html($member, $template) ?>
</body>
</html>
