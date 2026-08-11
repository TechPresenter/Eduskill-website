<?php
/**
 * =============================================================================
 *  Admin — Student ID card endpoint (PDF / PNG / printable page)
 * =============================================================================
 *  ?id=..&format=pdf|png|print
 *    pdf   → application/pdf attachment (student card as a single-page PDF)
 *    png   → image/png inline (raw card image)
 *    print → standalone print-ready page embedding the PNG (default)
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_card.php';
require_admin();

$id     = (int) get('id', 0);
$format = (string) get('format', 'print');

$student = find('school_students', $id);
if (!$student) {
    set_flash('error', 'Student not found.');
    redirect(admin_url('school-students'));
}
$student  = student_ensure_full($student);
$fileBase = 'student-' . ($student['student_code'] ?: $id);

if ($format === 'pdf') {
    $pdf = student_card_pdf($student);
    log_activity('view', 'students', 'ID card');
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

/* -------- Printable HTML page (default) -------- */
$backUrl = admin_url('student-profile?id=' . $id);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Student ID Card · <?= e($student['name']) ?></title>
    <style>
        /* Standalone by design — no app stylesheets, so print output carries no
           panel chrome. This page is only the toolbar and the field the card sits
           on; the card image itself is rendered by student_card_png() in
           includes/student_card.php. Palette written out by hand: the field was
           #eef2f7 and the outline buttons were #1f2937 on #cbd5e1, none of which
           is in the brand. Radii moved 9px -> 10px, the middle step. */
        body{margin:0;min-height:100vh;background:#F8FCF8;font-family:system-ui,Segoe UI,Roboto,sans-serif;
             display:flex;flex-direction:column;align-items:center;justify-content:center;gap:22px;padding:30px;}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
        .btn{border:0;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;text-decoration:none;
             font-size:.92rem;display:inline-flex;align-items:center;gap:6px;}
        .btn-p{background:#0B4E3D;color:#fff;} .btn-o{background:#fff;color:#151818;border:1px solid #C1CCB3;}
        /* 16px radius is already the design system's largest step; only the
           shadow changes — screen-only, and now in the subtle band. */
        .card-wrap img{max-width:100%;height:auto;border-radius:16px;box-shadow:0 4px 12px -2px rgba(21,24,24,.10);}
        @media print{ .toolbar{display:none;} body{background:#fff;} .card-wrap img{box-shadow:none;} }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-p" onclick="window.print()">Print</button>
        <a class="btn btn-o" href="?id=<?= $id ?>&format=pdf">Download PDF</a>
        <a class="btn btn-o" href="?id=<?= $id ?>&format=png" download>Download PNG</a>
        <a class="btn btn-o" href="<?= e($backUrl) ?>">← Back</a>
    </div>

    <div class="card-wrap">
        <img src="?id=<?= $id ?>&format=png" alt="Student ID card · <?= e($student['name']) ?>">
    </div>
</body>
</html>
