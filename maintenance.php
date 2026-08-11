<?php
/**
 * =============================================================================
 *  503 — Scheduled Maintenance
 * -----------------------------------------------------------------------------
 *  Served to visitors while `maintenance_mode` is enabled in Settings.
 *  Admins signed into the control panel bypass this entirely (see the guard in
 *  includes/bootstrap.php) so the site can be verified before going live again.
 *
 *  DESIGN NOTE — this page is deliberately self-contained.
 *  Maintenance windows are exactly when the database is most likely to be mid
 *  migration, so nothing here may depend on a working DB connection. Any value
 *  pulled from settings is wrapped and falls back to a sensible default.
 *
 *  It answers 503 + Retry-After, which is the correct signal for search
 *  engines: it tells them the outage is temporary and the page must NOT be
 *  indexed or allowed to displace the real one in the results.
 * =============================================================================
 */

$retryMinutes = 30;
$message      = '';
$eta          = '';

// Best-effort: pull the admin-authored notice + ETA. Never fatal if the DB is
// down — that is the whole point of this page.
try {
    if (!function_exists('get_setting')) {
        require_once __DIR__ . '/includes/bootstrap.php';
    }
    $message = (string) get_setting('maintenance_message', '');
    $eta     = (string) get_setting('maintenance_eta', '');
    $mins    = (int) get_setting('maintenance_retry_minutes', 30);
    if ($mins > 0) {
        $retryMinutes = $mins;
    }
} catch (Throwable $e) {
    // Fall through with defaults; the page still renders correctly.
    $message = '';
    $eta     = '';
}

if ($message === '') {
    $message = 'We\'re carrying out some scheduled maintenance to make things better. '
             . 'The site will be back shortly — thank you for your patience.';
}

http_response_code(503);
header('Retry-After: ' . ($retryMinutes * 60));
header('Cache-Control: no-store, no-cache, must-revalidate');

/** Local escape helper — e() may not exist if bootstrap failed. */
$esc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>We'll Be Right Back — EDUSKILL INDIA FOUNDATION</title>
<style>
    :root{
        --brand:#0B4E3D; --brand-2:#174D3D; --green:#2F8065; --orange:#F15A24;
        --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f8fafc; --card:#ffffff;
    }
    @media (prefers-color-scheme: dark){
        :root{ --ink:#e2e8f0; --muted:#94a3b8; --line:#1e293b; --bg:#0b1220; --card:#111a2e; }
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{
        font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
        background:var(--bg); color:var(--ink); line-height:1.6;
        min-height:100vh; display:grid; place-items:center; padding:1.5rem;
        -webkit-font-smoothing:antialiased;
    }
    .wrap{
        width:100%; max-width:580px; background:var(--card); border:1px solid var(--line);
        border-radius:18px; padding:clamp(1.75rem,5vw,3rem); text-align:center;
        box-shadow:0 18px 45px -20px rgba(11,78,61,.35);
    }
    .badge{
        width:70px; height:70px; margin:0 auto 1.2rem; border-radius:50%;
        display:grid; place-items:center;
        background:linear-gradient(135deg,var(--brand),var(--brand-2) 60%,var(--green));
        box-shadow:0 10px 26px -10px rgba(11,78,61,.6);
    }
    .badge svg{width:32px;height:32px;stroke:#fff;fill:none;stroke-width:2;
        stroke-linecap:round;stroke-linejoin:round;animation:spin 7s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}
    .eyebrow{
        display:inline-block; font-size:.7rem; font-weight:700; letter-spacing:.14em;
        text-transform:uppercase; color:var(--orange); margin-bottom:.5rem;
    }
    h1{font-size:clamp(1.35rem,4.5vw,1.9rem); line-height:1.25; margin-bottom:.7rem}
    p{color:var(--muted); margin-bottom:.8rem}
    .eta{
        display:inline-block; margin-top:.4rem; padding:.5rem 1rem; border-radius:9px;
        background:rgba(47,128,101,.12); color:var(--green); font-weight:600; font-size:.9rem;
    }
    .foot{margin-top:1.7rem; padding-top:1.15rem; border-top:1px solid var(--line); font-size:.85rem}
    .foot a{color:var(--brand); font-weight:600; text-decoration:none}
    .foot a:hover{text-decoration:underline}
    @media (prefers-color-scheme: dark){ .foot a{color:#7fb2ec} }
    @media (prefers-reduced-motion: reduce){ .badge svg{animation:none} }
</style>
</head>
<body>
    <main class="wrap">
        <div class="badge" aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </div>

        <span class="eyebrow">Scheduled Maintenance</span>
        <h1>We'll be right back.</h1>
        <p><?= $esc($message) ?></p>

        <?php if ($eta !== ''): ?>
            <div class="eta">Expected back: <?= $esc($eta) ?></div>
        <?php endif; ?>

        <div class="foot">
            Something urgent? Email
            <a href="mailto:info@eduskillindia.org">info@eduskillindia.org</a>
        </div>
    </main>
</body>
</html>
