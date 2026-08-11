<?php
/**
 * =============================================================================
 *  Admin — Printable student certificate (standalone, no admin chrome)
 * =============================================================================
 *  ?id=<student_certificates id>. Renders a beautiful, print-ready landscape
 *  certificate with an inline QR verification code. Browser print-to-PDF — no
 *  PDF library required. Look varies by the certificate's stored template
 *  (classic | modern | elegant).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student.php';
require_once __DIR__ . '/../includes/lib/qrcode.php';

$id = (int) get('id', 0);

$cert = db_row(
    "SELECT c.*,
            s.name  AS student_name,
            co.title AS course_title
       FROM student_certificates c
       LEFT JOIN school_students s  ON s.id  = c.student_id
       LEFT JOIN courses         co ON co.id = c.course_id
      WHERE c.id = :id",
    [':id' => $id]
);
if (!$cert) {
    set_flash('error', 'Certificate not found.');
    redirect(admin_url('certificates'));
}
// Admins, or the student who owns this certificate, may view it.
student_portal_or_admin_guard((int) $cert['student_id']);

/* Ensure a verification serial exists (persist so verify-award can resolve it). */
if (empty($cert['serial'])) {
    $serial = certificate_serial($id);
    db_update('student_certificates', ['serial' => $serial], 'id = :id', [':id' => $id]);
    $cert['serial'] = $serial;
}

/* Presentation variables. */
$templates = ['classic', 'modern', 'elegant'];
$template  = in_array($cert['template'] ?? '', $templates, true) ? $cert['template'] : 'classic';

$org        = (string) get_setting('site_name', SITE_NAME);
$type       = ucfirst((string) $cert['type']);
$student    = (string) ($cert['student_name'] ?? '');
$title      = (string) $cert['title'];
$desc       = (string) ($cert['description'] ?? '');
$course     = (string) ($cert['course_title'] ?? '');
$issued     = format_date($cert['issue_date'] ?: $cert['created_at'], 'd F Y');
$signedBy   = trim((string) ($cert['signed_by'] ?? ''));
$signImg    = trim((string) ($cert['signature_image'] ?? ''));
$serial     = (string) $cert['serial'];
$verifyHost = rtrim(preg_replace('#^https?://#i', '', abs_url('verify-award')), '/');
$qrSvg      = PWF_QR::encode(certificate_verify_url($cert), 'M')->svg(2, 3);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Certificate · <?= e($student ?: $serial) ?></title>
    <style>
        /* Standalone by design — no app stylesheets, so print output carries no
           panel chrome. Colours below are the brand palette written out by hand;
           the geometry (sizes, flex, @page) is untouched. */
        *{box-sizing:border-box;}
        @page{size:A4 landscape;margin:0;}
        body{margin:0;min-height:100vh;background:#F8FCF8;color:#151818;
             font-family:system-ui,Segoe UI,Roboto,sans-serif;
             display:flex;flex-direction:column;align-items:center;gap:22px;padding:28px;}

        /* -------- Toolbar (screen only) -------- */
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
        .btn{border:0;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;text-decoration:none;
             font-size:.92rem;display:inline-flex;align-items:center;gap:6px;}
        .btn-p{background:#0B4E3D;color:#fff;}
        .btn-o{background:#fff;color:#151818;border:1px solid #C1CCB3;}

        /* -------- Certificate shell -------- */
        /* Elevation is screen-only (print clears it) and stays inside the subtle
           band the brand allows — the old 50px/.18 drop was a heavy shadow. */
        .cert{width:1040px;max-width:100%;min-height:735px;background:var(--paper);
              display:flex;box-shadow:0 4px 12px -2px rgba(21,24,24,.10);position:relative;}
        .frame{flex:1;display:flex;margin:22px;}
        .inner{flex:1;display:flex;flex-direction:column;align-items:center;text-align:center;
               margin:8px;padding:46px 62px 40px;}

        .org{font-size:1.55rem;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:var(--accent);}
        .rule{width:70px;height:3px;background:var(--accent);margin:14px 0 4px;border-radius:999px;opacity:.85;}
        .heading{margin-top:20px;font-size:1.02rem;font-weight:700;letter-spacing:5px;text-transform:uppercase;color:var(--ink);opacity:.85;}
        .present{margin-top:26px;font-size:.98rem;font-style:italic;color:var(--muted);letter-spacing:.4px;}
        .name{margin-top:12px;font-size:3.1rem;line-height:1.05;font-weight:800;color:var(--accent);
              padding-bottom:12px;border-bottom:2px solid var(--soft);max-width:80%;}
        .course{margin-top:24px;font-size:1.28rem;font-weight:700;color:var(--ink);}
        .desc{margin-top:14px;max-width:76%;font-size:1rem;line-height:1.7;color:var(--muted);}

        .foot{margin-top:auto;padding-top:30px;width:100%;display:flex;align-items:flex-end;justify-content:space-between;gap:24px;}
        .foot-col{display:flex;flex-direction:column;align-items:center;gap:4px;min-width:170px;}
        .foot-col.qr{gap:8px;}
        .qr svg{display:block;border:1px solid var(--soft);border-radius:6px;background:#fff;padding:5px;}
        .verify{font-size:.68rem;letter-spacing:.5px;color:var(--muted);text-transform:uppercase;}
        .lbl{font-size:.72rem;letter-spacing:1.4px;text-transform:uppercase;color:var(--muted);}
        .val{font-size:1.02rem;font-weight:700;color:var(--ink);}
        .sig-line{width:190px;border-top:1.5px solid var(--ink);margin-bottom:4px;opacity:.7;}
        .sig-img{max-height:52px;max-width:190px;object-fit:contain;margin-bottom:2px;}
        .serial{margin-top:22px;font-size:.74rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);}

        /* ===== Template: classic (double border + serif) =====================
           Was navy: #14224e ink/accent, #5b6a86 muted, #c7d0e3 rules — four
           colours from outside the palette. Re-pointed onto primary-dark ink so
           the three templates differ by typography and border treatment (serif +
           double rule here, left bar in `modern`, gold frame in `elegant`)
           instead of by importing extra hues. */
        .cert.classic{--paper:#ffffff;--accent:#0F4537;--ink:#151818;--muted:#4B6754;--soft:#C1CCB3;
                      font-family:Georgia,'Times New Roman','Palatino Linotype',serif;}
        .cert.classic .frame{border:3px solid var(--accent);}
        .cert.classic .inner{border:1px solid var(--accent);}

        /* ===== Template: modern (left accent bar, minimal, sans) ===== */
        .cert.modern{--paper:#ffffff;--accent:#0B4E3D;--ink:#151818;--muted:#4B6754;--soft:#C1CCB3;
                     font-family:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif;}
        .cert.modern .frame{margin:0;border:0;}
        .cert.modern .inner{margin:0;border:0;border-left:14px solid var(--accent);
                            padding:58px 70px 46px;align-items:flex-start;text-align:left;}
        .cert.modern .name{max-width:100%;}
        .cert.modern .rule{margin-left:0;}
        .cert.modern .desc{max-width:88%;}
        .cert.modern .foot{justify-content:space-between;}

        /* ===== Template: elegant (gold border, serif, ivory) =================
           Keeps its gold-on-cream character with palette colours: ivory paper
           (#FEFEF1) and brand gold (#E8C52E) on every rule and border. The gold
           moved to --soft, NOT --accent, because --accent also paints the org line
           and the recipient's 3.1rem name — gold text on ivory is barely legible,
           so the ink sits on the warm text colour (#372C22) instead and the gold
           stays decorative. Was #fffdf5 / #b7912f / #5b4a1f / #8a7846 / #e6d7a8,
           five colours from outside the palette. */
        .cert.elegant{--paper:#FEFEF1;--accent:#372C22;--ink:#372C22;--muted:#4B6754;--soft:#E8C52E;
                      font-family:Georgia,'Palatino Linotype','Book Antiqua',serif;}
        .cert.elegant .frame{border:6px double var(--soft);}
        .cert.elegant .inner{border:1px solid var(--soft);box-shadow:inset 0 0 0 5px rgba(232,197,46,.16);}
        .cert.elegant .org{letter-spacing:4px;}

        @media print{
            body{background:#fff;padding:0;gap:0;}
            .toolbar{display:none;}
            .cert{box-shadow:none;width:100%;min-height:100vh;}
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-p" onclick="window.print()">Print</button>
        <a class="btn btn-o" href="<?= e(admin_url('certificates')) ?>">&larr; Back to certificates</a>
    </div>

    <div class="cert <?= e($template) ?>">
        <div class="frame">
            <div class="inner">
                <div class="org"><?= e($org) ?></div>
                <div class="rule"></div>

                <div class="heading">Certificate of <?= e($type) ?></div>
                <div class="present">This is proudly presented to</div>
                <div class="name"><?= e($student) ?></div>

                <?php if ($course !== '' && $course !== $title): ?>
                    <div class="course"><?= e($course) ?></div>
                <?php endif; ?>
                <div class="course"><?= e($title) ?></div>

                <?php if ($desc !== ''): ?>
                    <div class="desc"><?= e($desc) ?></div>
                <?php endif; ?>

                <div class="foot">
                    <div class="foot-col issue">
                        <span class="val"><?= e($issued) ?></span>
                        <span class="lbl">Date of Issue</span>
                    </div>

                    <div class="foot-col qr">
                        <?= $qrSvg ?>
                        <span class="verify">Verify at <?= e($verifyHost) ?></span>
                    </div>

                    <div class="foot-col sign">
                        <?php if ($signImg !== ''): ?>
                            <img class="sig-img" src="<?= e(upload_url($signImg)) ?>" alt="Signature">
                        <?php endif; ?>
                        <div class="sig-line"></div>
                        <?php if ($signedBy !== ''): ?>
                            <span class="val"><?= e($signedBy) ?></span>
                        <?php endif; ?>
                        <span class="lbl">Authorised Signatory</span>
                    </div>
                </div>

                <div class="serial">Serial No. <?= e($serial) ?></div>
            </div>
        </div>
    </div>
</body>
</html>
