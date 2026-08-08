<?php
/**
 * =============================================================================
 *  Membership card renderer
 * =============================================================================
 *  Produces the digital / printable membership ID card in three forms:
 *    - card_html()  : crisp on-screen card (HTML/CSS + inline SVG QR)
 *    - card_png()   : GD-rendered raster (download / preview)
 *    - card_pdf()   : the raster wrapped in a minimal, dependency-free PDF
 *
 *  No Composer, no FPDF: the card is composited with GD (JPEG/PNG/FreeType,
 *  all confirmed available) and the QR is drawn straight from the PWF_QR module
 *  matrix, so nothing is fetched from the network.
 *
 *  Templates: 'classic' | 'modern' | 'dark'. The tier accent colour comes from
 *  the member's plan (membership_tier_color()).
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/qrcode.php';

/** Card pixel size — CR80 landscape (3.375in x 2.125in) at ~300 DPI. */
const CARD_W = 1012;
const CARD_H = 638;

/** Available template ids => human labels. */
function card_templates(): array
{
    return [
        'classic' => 'Classic (header band)',
        'modern'  => 'Modern (side accent)',
        'dark'    => 'Dark',
    ];
}

/* =============================================================================
 |  FONT RESOLUTION  (bundled → system → GD bitmap fallback)
 |============================================================================*/

/**
 * Resolve a usable TrueType font path for the given weight, or null when none
 * is found (callers then fall back to GD's built-in bitmap font).
 */
function card_font(string $weight = 'regular'): ?string
{
    static $cache = [];
    if (array_key_exists($weight, $cache)) {
        return $cache[$weight];
    }
    $bundled = BASE_PATH . '/assets/fonts';
    $candidates = [
        'bold' => [
            $bundled . '/Inter-Bold.ttf', $bundled . '/bold.ttf',
            'C:/Windows/Fonts/arialbd.ttf', 'C:/Windows/Fonts/segoeuib.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/Library/Fonts/Arial Bold.ttf',
        ],
        'semibold' => [
            $bundled . '/Inter-SemiBold.ttf', $bundled . '/semibold.ttf',
            'C:/Windows/Fonts/seguisb.ttf', 'C:/Windows/Fonts/arialbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ],
        'regular' => [
            $bundled . '/Inter-Regular.ttf', $bundled . '/regular.ttf',
            'C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/segoeui.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/Library/Fonts/Arial.ttf',
        ],
    ];
    foreach ($candidates[$weight] ?? $candidates['regular'] as $path) {
        if (is_file($path)) {
            return $cache[$weight] = $path;
        }
    }
    return $cache[$weight] = null;
}

/* =============================================================================
 |  LOW-LEVEL GD HELPERS
 |============================================================================*/

/** Allocate a colour from a #rrggbb hex string. */
function card_color($img, string $hex, int $alpha = 0)
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return $alpha > 0
        ? imagecolorallocatealpha($img, $r, $g, $b, $alpha)
        : imagecolorallocate($img, $r, $g, $b);
}

/** Draw text with a TTF when available, else GD's bitmap font (scaled). */
function card_text($img, int $size, int $x, int $y, $color, string $text, string $weight = 'regular'): void
{
    $font = card_font($weight);
    if ($font !== null) {
        // imagettftext y is the baseline; approximate with +size for top-left origin.
        imagettftext($img, $size, 0, $x, $y + $size, $color, $font, $text);
        return;
    }
    // Bitmap fallback: pick the largest built-in font and scale up if needed.
    $gdFont = 5;
    imagestring($img, $gdFont, $x, $y, $text, $color);
}

/** Measure TTF text width (0 when falling back to bitmap font). */
function card_text_width(int $size, string $text, string $weight = 'regular'): int
{
    $font = card_font($weight);
    if ($font === null) {
        return strlen($text) * 9;
    }
    $box = imagettfbbox($size, 0, $font, $text);
    return (int) abs($box[2] - $box[0]);
}

/** Filled rounded rectangle. */
function card_rounded_rect($img, int $x1, int $y1, int $x2, int $y2, int $r, $color): void
{
    $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
    imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
        imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
    }
}

/** Vertical gradient fill between two hex colours. */
function card_gradient($img, int $x1, int $y1, int $x2, int $y2, string $from, string $to): void
{
    $from = ltrim($from, '#');
    $to   = ltrim($to, '#');
    $fr = hexdec(substr($from, 0, 2)); $fg = hexdec(substr($from, 2, 2)); $fb = hexdec(substr($from, 4, 2));
    $tr = hexdec(substr($to, 0, 2));   $tg = hexdec(substr($to, 2, 2));   $tb = hexdec(substr($to, 4, 2));
    $h = max(1, $y2 - $y1);
    for ($i = 0; $i <= $h; $i++) {
        $t = $i / $h;
        $c = imagecolorallocate($img,
            (int) round($fr + ($tr - $fr) * $t),
            (int) round($fg + ($tg - $fg) * $t),
            (int) round($fb + ($tb - $fb) * $t));
        imagefilledrectangle($img, $x1, $y1 + $i, $x2, $y1 + $i, $c);
    }
}

/** Darken a hex colour by a factor (0..1). */
function card_darken(string $hex, float $factor = 0.75): string
{
    $hex = ltrim($hex, '#');
    $r = (int) (hexdec(substr($hex, 0, 2)) * $factor);
    $g = (int) (hexdec(substr($hex, 2, 2)) * $factor);
    $b = (int) (hexdec(substr($hex, 4, 2)) * $factor);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/* =============================================================================
 |  QR + PHOTO
 |============================================================================*/

/** Draw a QR (from its module matrix) onto $img inside a white panel. */
function card_draw_qr($img, string $payload, int $x, int $y, int $box): void
{
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 17, 24, 39);
    card_rounded_rect($img, $x, $y, $x + $box, $y + $box, 16, $white);

    $qr    = PWF_QR::encode($payload, 'M');
    $m     = $qr->matrix();
    $n     = $qr->size;
    $quiet = 3;
    $grid  = $n + 2 * $quiet;
    $inner = $box - 24;                    // padding inside the panel
    $mod   = (int) floor($inner / $grid);
    if ($mod < 1) { $mod = 1; }
    $span   = $mod * $grid;
    $ox     = $x + (int) (($box - $span) / 2);
    $oy     = $y + (int) (($box - $span) / 2);
    for ($row = 0; $row < $n; $row++) {
        for ($col = 0; $col < $n; $col++) {
            if ($m[$row][$col]) {
                $px = $ox + ($col + $quiet) * $mod;
                $py = $oy + ($row + $quiet) * $mod;
                imagefilledrectangle($img, $px, $py, $px + $mod - 1, $py + $mod - 1, $black);
            }
        }
    }
}

/** Load a member photo as a square GD image, or null. */
function card_load_photo(?string $avatar)
{
    if (empty($avatar)) {
        return null;
    }
    $path = UPLOAD_PATH . '/' . ltrim($avatar, '/');
    if (!is_file($path)) {
        return null;
    }
    $data = @file_get_contents($path);
    if ($data === false) {
        return null;
    }
    $src = @imagecreatefromstring($data); // auto-detects jpeg/png/gif/webp
    return $src ?: null;
}

/* =============================================================================
 |  CARD COMPOSITION (GD)
 |============================================================================*/

/**
 * Build the card as a GD image. Caller owns the returned resource.
 */
function card_build_gd(array $member, string $template = 'classic')
{
    $member = member_ensure_identity($member);
    $plan   = membership_plan((int) ($member['plan_id'] ?? 0));
    $accent = membership_tier_color($plan);
    $status = member_effective_status($member);
    $org    = (string) get_setting('site_name', SITE_NAME);
    $tier   = member_tier_label($member);
    $code   = (string) ($member['member_code'] ?? '');
    $name   = (string) ($member['name'] ?? 'Member');
    $joined = !empty($member['join_date']) ? format_date($member['join_date'], 'M Y') : '—';
    $valid  = !empty($member['expiry_date']) ? format_date($member['expiry_date'], 'd M Y') : 'Lifetime';

    $dark = $template === 'dark';
    $bg      = $dark ? '#0f172a' : '#ffffff';
    $ink     = $dark ? '#e5e7eb' : '#111827';
    $muted   = $dark ? '#94a3b8' : '#6b7280';
    $panelBg = $dark ? '#1e293b' : '#f8fafc';

    $img = imagecreatetruecolor(CARD_W, CARD_H);
    imagefilledrectangle($img, 0, 0, CARD_W, CARD_H, card_color($img, $bg));

    $accentDark = card_darken($accent, 0.72);

    // ---- Header / accent ----
    if ($template === 'modern') {
        // Left vertical accent bar.
        card_gradient($img, 0, 0, 150, CARD_H, $accent, $accentDark);
        $headBottom = 40;
        $contentX   = 190;
    } else {
        // classic / dark: top band.
        card_gradient($img, 0, 0, CARD_W, 140, $accent, $accentDark);
        $headBottom = 140;
        $contentX   = 40;
    }

    // Monogram badge.
    $mono = strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $org) ?: 'P', 0, 1));
    $badgeX = $template === 'modern' ? 30 : 40;
    $badgeY = 34;
    $bw = imagecolorallocate($img, 255, 255, 255);
    imagefilledellipse($img, $badgeX + 36, $badgeY + 36, 72, 72, $bw);
    card_text($img, 30, $badgeX + 24, $badgeY + 14, card_color($img, $accent), $mono, 'bold');

    // Org name + card label in the header.
    $white = imagecolorallocate($img, 255, 255, 255);
    if ($template === 'modern') {
        card_text($img, 26, 190, 34, card_color($img, $ink), $org, 'bold');
        card_text($img, 14, 190, 74, card_color($img, $muted), 'MEMBERSHIP CARD', 'semibold');
    } else {
        card_text($img, 26, $badgeX + 92, 34, $white, $org, 'bold');
        card_text($img, 14, $badgeX + 92, 74, $white, 'MEMBERSHIP CARD', 'semibold');
    }

    // ---- Photo ----
    $px = $contentX;
    $py = $headBottom + 30;
    $pw = 240;
    $ph = 300;
    card_rounded_rect($img, $px - 4, $py - 4, $px + $pw + 4, $py + $ph + 4, 18, card_color($img, $accent));
    $photo = card_load_photo($member['avatar'] ?? null);
    if ($photo) {
        $sw = imagesx($photo);
        $sh = imagesy($photo);
        $side = min($sw, $sh);
        // Center-crop to the card photo aspect.
        $targetRatio = $pw / $ph;
        $cropW = $sw; $cropH = (int) ($sw / $targetRatio);
        if ($cropH > $sh) { $cropH = $sh; $cropW = (int) ($sh * $targetRatio); }
        $sx = (int) (($sw - $cropW) / 2);
        $sy = (int) (($sh - $cropH) / 2);
        imagecopyresampled($img, $photo, $px, $py, $sx, $sy, $pw, $ph, $cropW, $cropH);
        imagedestroy($photo);
    } else {
        card_rounded_rect($img, $px, $py, $px + $pw, $py + $ph, 14, card_color($img, $panelBg));
        $initial = strtoupper(mb_substr($name, 0, 1));
        card_text($img, 120, $px + $pw / 2 - 40, $py + $ph / 2 - 90, card_color($img, $accent), $initial, 'bold');
    }

    // QR panel geometry (needed to bound the name column).
    $qrBox = 250;
    $qx = CARD_W - $qrBox - 40;

    // ---- Details column ----
    $tx = $px + $pw + 40;
    $ty = $headBottom + 34;
    $nameMaxW  = $qx - $tx - 20;
    $nameSize  = card_fit_size($name, 'bold', 40, 24, $nameMaxW);
    card_text($img, $nameSize, $tx, $ty, card_color($img, $ink), card_fit_text($name, 'bold', $nameSize, $nameMaxW), 'bold');
    card_text($img, 22, $tx, $ty + 66, card_color($img, $muted), $code, 'semibold');

    // Tier badge.
    $tierY = $ty + 108;
    $tierW = card_text_width(20, strtoupper($tier), 'bold') + 44;
    card_rounded_rect($img, $tx, $tierY, $tx + $tierW, $tierY + 44, 22, card_color($img, $accent));
    card_text($img, 20, $tx + 22, $tierY + 8, $white, strtoupper($tier), 'bold');

    // Meta rows.
    $metaY = $tierY + 78;
    card_text($img, 16, $tx, $metaY, card_color($img, $muted), 'MEMBER SINCE', 'semibold');
    card_text($img, 22, $tx, $metaY + 24, card_color($img, $ink), $joined, 'bold');
    card_text($img, 16, $tx + 220, $metaY, card_color($img, $muted), 'VALID THRU', 'semibold');
    card_text($img, 22, $tx + 220, $metaY + 24, card_color($img, $ink), $valid, 'bold');

    // Status chip.
    $statusColors = ['active' => '#58A42F', 'expired' => '#dc2626', 'cancelled' => '#E67B1D', 'none' => '#6b7280'];
    $sc = $statusColors[$status] ?? '#6b7280';
    $statusY = $metaY + 66;
    $label = strtoupper(membership_status_label($status));
    $sw2 = card_text_width(16, $label, 'bold') + 40;
    card_rounded_rect($img, $tx, $statusY, $tx + $sw2, $statusY + 38, 19, card_color($img, $sc, 0));
    card_text($img, 16, $tx + 20, $statusY + 8, $white, $label, 'bold');

    // ---- QR panel ----
    $qy = $headBottom + 28;
    card_draw_qr($img, member_verify_url($member), $qx, $qy, $qrBox);
    card_text($img, 15, $qx + 30, $qy + $qrBox + 6, card_color($img, $muted), 'Scan to verify', 'semibold');

    // ---- Footer ----
    $host = parse_url(APP_URL, PHP_URL_HOST) ?: 'eduskillindia.org';
    $footY = CARD_H - 46;
    imagefilledrectangle($img, 0, $footY, CARD_W, CARD_H, card_color($img, $accentDark));
    card_text($img, 15, 40, $footY + 12, $white, 'Authenticate this card at ' . $host . '/verify-member', 'semibold');

    return $img;
}

/** Largest font size (down to $min) whose rendered text fits within $maxW px. */
function card_fit_size(string $text, string $weight, int $max, int $min, int $maxW): int
{
    for ($s = $max; $s > $min; $s--) {
        if (card_text_width($s, $text, $weight) <= $maxW) {
            return $s;
        }
    }
    return $min;
}

/** Ellipsize text to fit $maxW px at the given size (no-op if it already fits). */
function card_fit_text(string $text, string $weight, int $size, int $maxW): string
{
    if (card_text_width($size, $text, $weight) <= $maxW) {
        return $text;
    }
    $t = $text;
    while (mb_strlen($t) > 1 && card_text_width($size, $t . '…', $weight) > $maxW) {
        $t = mb_substr($t, 0, -1);
    }
    return $t . '…';
}

/* =============================================================================
 |  OUTPUT: PNG / JPEG / PDF
 |============================================================================*/

/** Render the card as PNG binary. */
function card_png(array $member, string $template = 'classic'): string
{
    $img = card_build_gd($member, $template);
    ob_start();
    imagepng($img);
    $png = (string) ob_get_clean();
    imagedestroy($img);
    return $png;
}

/** Render the card as JPEG binary. */
function card_jpeg(array $member, string $template = 'classic', int $quality = 92): string
{
    $img = card_build_gd($member, $template);
    ob_start();
    imagejpeg($img, null, $quality);
    $jpeg = (string) ob_get_clean();
    imagedestroy($img);
    return $jpeg;
}

/**
 * Wrap the card JPEG in a minimal single-page PDF (card-sized page).
 * Hand-written PDF: catalog + pages + page + image XObject (DCTDecode) +
 * content stream. No external library.
 */
function card_pdf(array $member, string $template = 'classic'): string
{
    $jpeg = card_jpeg($member, $template, 92);
    $iw   = CARD_W;
    $ih   = CARD_H;
    // Page size in points (72 pt/in): CR80 landscape = 3.375in x 2.125in.
    $pw = 243.0;
    $ph = 153.0;

    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pw $ph] "
        . "/Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>";
    $objects[4] = "<< /Type /XObject /Subtype /Image /Width $iw /Height $ih "
        . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length "
        . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";
    $content = "q\n$pw 0 0 $ph 0 0 cm\n/Im0 Do\nQ";
    $objects[5] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    for ($i = 1; $i <= 5; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= "$i 0 obj\n" . $objects[$i] . "\nendobj\n";
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
    return $pdf;
}

/* =============================================================================
 |  ON-SCREEN HTML CARD (crisp; inline SVG QR)
 |============================================================================*/

/**
 * A self-contained HTML/CSS membership card for embedding in a page.
 * Uses inline styles so it drops into any of the existing layouts unchanged.
 */
function card_html(array $member, string $template = 'classic'): string
{
    $member = member_ensure_identity($member);
    $plan   = membership_plan((int) ($member['plan_id'] ?? 0));
    $accent = membership_tier_color($plan);
    $accentDark = card_darken($accent, 0.72);
    $status = member_effective_status($member);
    $org    = e(get_setting('site_name', SITE_NAME));
    $tier   = e(member_tier_label($member));
    $name   = e($member['name'] ?? 'Member');
    $code   = e($member['member_code'] ?? '');
    $joined = !empty($member['join_date']) ? e(format_date($member['join_date'], 'M Y')) : '—';
    $valid  = !empty($member['expiry_date']) ? e(format_date($member['expiry_date'], 'd M Y')) : 'Lifetime';
    $photo  = e(image_url($member['avatar'] ?? null, 'avatar'));

    $qrSvg = PWF_QR::encode(member_verify_url($member), 'M')->svg(2, 4, '#111827', '#ffffff');

    $statusColors = ['active' => '#58A42F', 'expired' => '#dc2626', 'cancelled' => '#E67B1D', 'none' => '#6b7280'];
    $sc = $statusColors[$status] ?? '#6b7280';
    $statusLabel = e(membership_status_label($status));

    $dark = $template === 'dark';
    $bg   = $dark ? '#0f172a' : '#ffffff';
    $ink  = $dark ? '#e5e7eb' : '#111827';
    $muted= $dark ? '#94a3b8' : '#6b7280';
    $mono = $org !== '' ? mb_substr($org, 0, 1) : 'P';

    return <<<HTML
<div class="pwf-card" style="max-width:520px;aspect-ratio:1012/638;background:{$bg};color:{$ink};
     border-radius:18px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.18);font-family:system-ui,Segoe UI,Roboto,sans-serif;position:relative;">
  <div style="background:linear-gradient(135deg,{$accent},{$accentDark});padding:14px 18px;display:flex;align-items:center;gap:12px;color:#fff;">
    <span style="width:40px;height:40px;border-radius:50%;background:#fff;color:{$accent};display:grid;place-items:center;font-weight:800;">{$mono}</span>
    <div style="line-height:1.15;"><strong style="font-size:1.05rem;">{$org}</strong><br><span style="font-size:.7rem;letter-spacing:.12em;opacity:.9;">MEMBERSHIP CARD</span></div>
  </div>
  <div style="display:flex;gap:16px;padding:18px;">
    <img src="{$photo}" alt="" style="width:34%;aspect-ratio:4/5;object-fit:cover;border-radius:12px;border:3px solid {$accent};flex:none;">
    <div style="flex:1;min-width:0;">
      <div style="font-size:1.5rem;font-weight:800;line-height:1.1;">{$name}</div>
      <div style="color:{$muted};font-weight:600;margin:2px 0 8px;">{$code}</div>
      <span style="display:inline-block;background:{$accent};color:#fff;font-size:.72rem;font-weight:800;letter-spacing:.06em;padding:4px 12px;border-radius:999px;">{$tier}</span>
      <span style="display:inline-block;background:{$sc};color:#fff;font-size:.72rem;font-weight:800;padding:4px 12px;border-radius:999px;margin-left:6px;">{$statusLabel}</span>
      <div style="display:flex;gap:18px;margin-top:12px;font-size:.8rem;">
        <div><div style="color:{$muted};font-size:.66rem;letter-spacing:.08em;">MEMBER SINCE</div><strong>{$joined}</strong></div>
        <div><div style="color:{$muted};font-size:.66rem;letter-spacing:.08em;">VALID THRU</div><strong>{$valid}</strong></div>
      </div>
    </div>
    <div style="flex:none;text-align:center;">
      <div style="width:96px;height:96px;">{$qrSvg}</div>
      <div style="font-size:.62rem;color:{$muted};margin-top:2px;">Scan to verify</div>
    </div>
  </div>
</div>
HTML;
}
