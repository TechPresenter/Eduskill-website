<?php
/**
 * =============================================================================
 *  500 — Internal Server Error
 * -----------------------------------------------------------------------------
 *  Shown by ErrorDocument 500 when a request dies unexpectedly.
 *
 *  DESIGN NOTE — why this page is defensive:
 *  A 500 is very often caused by the very thing a normal page depends on (the
 *  database being unreachable, a helper throwing during boot). If this page
 *  simply required bootstrap.php and that failed too, Apache would serve the
 *  error document for the error document and the visitor would get a blank
 *  page or a redirect loop.
 *
 *  So it renders in two tiers:
 *    1. Best case  — bootstrap loads, and we render inside the normal site
 *                    chrome (header/footer, theme tokens, nav).
 *    2. Fallback   — anything at all throws, and we emit a completely
 *                    self-contained page with inlined brand styling that needs
 *                    no database, no session and no includes.
 * =============================================================================
 */

http_response_code(500);
header('Cache-Control: no-store, no-cache, must-revalidate');

// Never leak a stack trace to a visitor, regardless of APP_ENV.
@ini_set('display_errors', '0');

$booted = false;
ob_start();

try {
    require_once __DIR__ . '/includes/bootstrap.php';

    seo_set([
        'title'       => 'Something Went Wrong',
        'description' => 'We hit an unexpected error. Our team has been notified — please try again in a moment.',
        'page_key'    => '500',
        'robots'      => 'noindex,nofollow',
    ]);

    include __DIR__ . '/includes/header.php';
    $booted = true;
    ?>

    <section class="section text-center">
        <div class="container container-narrow">

            <span class="eyebrow" style="justify-content:center;">Error 500</span>

            <div class="text-gradient reveal"
                 style="font-size:clamp(6rem,24vw,13rem);font-weight:800;line-height:.95;letter-spacing:-.04em;margin:.25rem 0;">
                500
            </div>

            <h1 class="section-title reveal delay-1">Something went wrong on our end.</h1>
            <p class="section-subtitle reveal delay-1" style="margin-inline:auto;">
                This one is on us, not you. The page couldn't be loaded because of an
                unexpected error. It has been logged and our team will look into it.
                Please try again in a moment.
            </p>

            <div class="hero-actions reveal delay-2" style="justify-content:center;">
                <a class="btn btn-primary btn-lg" href="<?= e(url('')) ?>"><?= lucide('home') ?> Back to Home</a>
                <a class="btn btn-outline btn-lg" href="javascript:location.reload()"><?= lucide('refresh-cw') ?> Try Again</a>
                <a class="btn btn-secondary btn-lg" href="<?= e(url('contact')) ?>">Contact Us</a>
            </div>

            <div class="divider" style="max-width:520px;margin:2.5rem auto 1rem;"></div>

            <p class="text-muted">
                If this keeps happening, please email
                <a href="mailto:<?= e(get_setting('contact_email', SITE_EMAIL)) ?>"><?= e(get_setting('contact_email', SITE_EMAIL)) ?></a>
                and tell us what you were trying to do — it helps us fix it faster.
            </p>

        </div>
    </section>

    <?php
    include __DIR__ . '/includes/footer.php';
} catch (Throwable $e) {
    // Tier 2: the site itself is unhealthy. Throw away whatever partial markup
    // was buffered and emit a page that depends on nothing at all.
    $booted = false;
    ob_end_clean();
    @error_log('[500-page] fallback rendered: ' . $e->getMessage());
}

if ($booted) {
    ob_end_flush();
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Something Went Wrong — EDUSKILL INDIA FOUNDATION</title>
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
        width:100%; max-width:560px; background:var(--card); border:1px solid var(--line);
        border-radius:18px; padding:clamp(1.75rem,5vw,3rem); text-align:center;
        box-shadow:0 18px 45px -20px rgba(11,78,61,.35);
    }
    .eyebrow{
        display:inline-block; font-size:.7rem; font-weight:700; letter-spacing:.14em;
        text-transform:uppercase; color:var(--orange); margin-bottom:.4rem;
    }
    .code{
        font-size:clamp(4.5rem,18vw,8rem); font-weight:800; line-height:.95; letter-spacing:-.04em;
        background:linear-gradient(135deg,var(--brand),var(--brand-2) 55%,var(--green));
        -webkit-background-clip:text; background-clip:text; color:transparent; margin:.2rem 0 .6rem;
    }
    h1{font-size:clamp(1.25rem,4vw,1.6rem); line-height:1.3; margin-bottom:.6rem}
    p{color:var(--muted); margin-bottom:1rem}
    .actions{display:flex; flex-wrap:wrap; gap:.6rem; justify-content:center; margin-top:1.4rem}
    a.btn{
        display:inline-block; padding:.7rem 1.35rem; border-radius:10px; font-weight:600;
        text-decoration:none; font-size:.95rem; border:1px solid transparent; transition:.18s ease;
    }
    a.primary{background:linear-gradient(135deg,var(--brand),var(--brand-2)); color:#fff}
    a.primary:hover{filter:brightness(1.1); transform:translateY(-1px)}
    a.ghost{border-color:var(--line); color:var(--ink)}
    a.ghost:hover{border-color:var(--brand); color:var(--brand)}
    .foot{margin-top:1.6rem; padding-top:1.1rem; border-top:1px solid var(--line); font-size:.85rem}
    .foot a{color:var(--brand); font-weight:600}
    @media (prefers-color-scheme: dark){ .foot a{color:#7fb2ec} }
    @media (prefers-reduced-motion: reduce){ a.btn{transition:none} a.primary:hover{transform:none} }
</style>
</head>
<body>
    <main class="wrap">
        <span class="eyebrow">Error 500</span>
        <div class="code">500</div>
        <h1>Something went wrong on our end.</h1>
        <p>
            This one is on us, not you. We couldn't complete your request because of an
            unexpected error. It has been logged, and our team will look into it.
        </p>
        <div class="actions">
            <a class="btn primary" href="/">Back to Home</a>
            <a class="btn ghost" href="javascript:location.reload()">Try Again</a>
        </div>
        <div class="foot">
            Need help now? Email
            <a href="mailto:info@eduskillindia.org">info@eduskillindia.org</a>
        </div>
    </main>
</body>
</html>
