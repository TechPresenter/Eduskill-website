<?php
/**
 * =============================================================================
 *  Student ID card renderer
 * =============================================================================
 *  Reuses the low-level GD helpers + QR encoder from membership_card.php to
 *  compose a student ID card (photo, student ID, class, school, QR) and wrap it
 *  in a minimal PDF. No new dependencies.
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/membership_card.php'; // card_* helpers + CARD_W/CARD_H + PWF_QR
require_once __DIR__ . '/student.php';          // student identity + verify url

/** Build the student card as a GD image. Caller owns the resource. */
function student_card_build_gd(array $student): \GdImage|false
{
    $student = student_ensure_full($student);
    $school  = find('schools', (int) ($student['school_id'] ?? 0));
    $accent  = '#0B4E3D';
    $accentDark = card_darken($accent, 0.72);
    $org     = (string) get_setting('site_name', SITE_NAME);
    $name    = (string) ($student['name'] ?? 'Student');
    $code    = (string) ($student['student_code'] ?? '');
    $grade   = trim((string) ($student['grade'] ?? '') . (!empty($student['section']) ? ' - ' . $student['section'] : ''));
    $schoolName = $school['name'] ?? $org;

    $img = imagecreatetruecolor(CARD_W, CARD_H);
    imagefilledrectangle($img, 0, 0, CARD_W, CARD_H, card_color($img, '#ffffff'));
    $white = imagecolorallocate($img, 255, 255, 255);
    $ink   = card_color($img, '#111827');
    $muted = card_color($img, '#6b7280');

    // Header band.
    card_gradient($img, 0, 0, CARD_W, 140, $accent, $accentDark);
    $mono = strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $org) ?: 'P', 0, 1));
    imagefilledellipse($img, 76, 70, 72, 72, $white);
    card_text($img, 30, 60, 48, card_color($img, $accent), $mono, 'bold');
    card_text($img, 24, 128, 34, $white, card_fit_text($org, 'bold', 24, 760), 'bold');
    card_text($img, 14, 128, 74, $white, 'STUDENT IDENTITY CARD', 'semibold');

    // Photo.
    $px = 40; $py = 175; $pw = 240; $ph = 300;
    card_rounded_rect($img, $px - 4, $py - 4, $px + $pw + 4, $py + $ph + 4, 18, card_color($img, $accent));
    $photo = card_load_photo($student['photo'] ?? null);
    if ($photo) {
        $sw = imagesx($photo); $sh = imagesy($photo);
        $ratio = $pw / $ph;
        $cropW = $sw; $cropH = (int) ($sw / $ratio);
        if ($cropH > $sh) { $cropH = $sh; $cropW = (int) ($sh * $ratio); }
        imagecopyresampled($img, $photo, $px, $py, (int) (($sw - $cropW) / 2), (int) (($sh - $cropH) / 2), $pw, $ph, $cropW, $cropH);
        imagedestroy($photo);
    } else {
        card_rounded_rect($img, $px, $py, $px + $pw, $py + $ph, 14, card_color($img, '#f1f5f9'));
        card_text($img, 120, $px + 78, $py + 90, card_color($img, $accent), strtoupper(mb_substr($name, 0, 1)), 'bold');
    }

    // Details.
    $tx = $px + $pw + 40;
    $ty = 172;
    $qx = CARD_W - 250 - 40;
    $nameSize = card_fit_size($name, 'bold', 40, 24, $qx - $tx - 20);
    card_text($img, $nameSize, $tx, $ty, $ink, card_fit_text($name, 'bold', $nameSize, $qx - $tx - 20), 'bold');
    card_text($img, 22, $tx, $ty + 62, $muted, $code, 'semibold');

    $rowY = $ty + 108;
    foreach ([
        ['CLASS', $grade !== '' ? $grade : '—'],
        ['SCHOOL', card_fit_text($schoolName, 'bold', 20, 360)],
        ['ADMISSION NO', $student['admission_no'] ?: '—'],
        ['SESSION', $student['academic_year'] ?: date('Y')],
    ] as $i => [$label, $val]) {
        $yy = $rowY + $i * 62;
        card_text($img, 14, $tx, $yy, $muted, $label, 'semibold');
        card_text($img, 20, $tx, $yy + 22, $ink, (string) $val, 'bold');
    }

    // QR.
    card_draw_qr($img, student_verify_url($student), $qx, 168, 250);
    card_text($img, 15, $qx + 40, 168 + 256, $muted, 'Scan to verify', 'semibold');

    // Footer.
    $host = parse_url(APP_URL, PHP_URL_HOST) ?: 'eduskillindia.org';
    imagefilledrectangle($img, 0, CARD_H - 46, CARD_W, CARD_H, card_color($img, $accentDark));
    card_text($img, 15, 40, CARD_H - 34, $white, 'If found, please return to ' . $schoolName, 'semibold');

    return $img;
}

function student_card_png(array $student): string
{
    $img = student_card_build_gd($student);
    ob_start(); imagepng($img); $out = (string) ob_get_clean(); imagedestroy($img);
    return $out;
}

function student_card_jpeg(array $student, int $quality = 92): string
{
    $img = student_card_build_gd($student);
    ob_start(); imagejpeg($img, null, $quality); $out = (string) ob_get_clean(); imagedestroy($img);
    return $out;
}

/** Minimal single-page PDF wrapping the card JPEG (CR80 landscape). */
function student_card_pdf(array $student): string
{
    $jpeg = student_card_jpeg($student, 92);
    $iw = CARD_W; $ih = CARD_H; $pw = 243.0; $ph = 153.0;

    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pw $ph] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>";
    $objects[4] = "<< /Type /XObject /Subtype /Image /Width $iw /Height $ih /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";
    $content = "q\n$pw 0 0 $ph 0 0 cm\n/Im0 Do\nQ";
    $objects[5] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    for ($i = 1; $i <= 5; $i++) { $offsets[$i] = strlen($pdf); $pdf .= "$i 0 obj\n" . $objects[$i] . "\nendobj\n"; }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) { $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]); }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $pdf;
}
