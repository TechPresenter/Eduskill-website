<?php
/**
 * =============================================================================
 *  My Student ID Card — member self-service (view / print / download)
 * =============================================================================
 *  Behind require_member(): resolves the student record linked to the signed-in
 *  member, so no id is accepted from the request (no IDOR). ?format=pdf|png|print
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/student_card.php';
require_member('/login');

$sess    = current_member();
$student = db_row('SELECT * FROM school_students WHERE member_id = :m LIMIT 1', [':m' => (int) ($sess['id'] ?? 0)]);
if (!$student) {
    set_flash('error', 'No student record is linked to your account.');
    redirect('/account');
}
$student  = student_ensure_full($student);
$format   = (string) get('format', 'print');
$fileBase = 'student-' . ($student['student_code'] ?: $student['id']);

if ($format === 'pdf') {
    $pdf = student_card_pdf($student);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileBase . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}
if ($format === 'png') {
    $png = student_card_png($student);
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="' . $fileBase . '.png"');
    echo $png;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>My Student ID · <?= e(get_setting('site_name', SITE_NAME)) ?></title>
    <style>
        body{margin:0;min-height:100vh;background:#eef2f7;font-family:system-ui,Segoe UI,Roboto,sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:22px;padding:30px;}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
        .btn{border:0;border-radius:9px;padding:10px 18px;font-weight:700;cursor:pointer;text-decoration:none;font-size:.92rem;display:inline-flex;align-items:center;gap:6px;}
        .btn-p{background:#063566;color:#fff;} .btn-o{background:#fff;color:#1f2937;border:1px solid #cbd5e1;}
        img.card{max-width:520px;width:100%;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.18);}
        @media print{.toolbar{display:none;}body{background:#fff;}}
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-p" onclick="window.print()">Print</button>
        <a class="btn btn-o" href="?format=pdf">Download PDF</a>
        <a class="btn btn-o" href="?format=png" download>Download PNG</a>
        <a class="btn btn-o" href="<?= e(url('account')) ?>">← My account</a>
    </div>
    <img class="card" src="?format=png" alt="Student ID card">
</body>
</html>
