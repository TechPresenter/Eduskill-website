<?php
/**
 * =============================================================================
 *  Tracking & Integrations — GA4, GTM, Meta Pixel, Clarity, Hotjar.
 * =============================================================================
 *  IDs are stored in the `settings` table (group 'tracking') and edited from
 *  admin/tracking.php. The emitters are called from the PUBLIC layout only
 *  (includes/header.php + footer.php) so nothing loads inside the admin panel.
 *
 *    tracking_head()       -> just inside <head> (dataLayer + pwfTrack + all tags)
 *    tracking_body_open()  -> immediately after <body> (GTM noscript + body code)
 *
 *  A single JS helper, window.pwfTrack(name, params), fans a conversion event
 *  out to dataLayer (GTM), gtag (GA4) and fbq (Meta Pixel) at once.
 * =============================================================================
 */

declare(strict_types=1);

/** Keep only characters valid for a tracking id (prevents inline-script breakout). */
function tracking_clean_id(string $value, string $allowed = 'A-Za-z0-9\-_'): string
{
    return preg_replace('/[^' . $allowed . ']/', '', trim($value)) ?? '';
}

/** Resolved, sanitised tracking configuration (memoised). */
function tracking_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $cfg = [
        'ga4'     => tracking_clean_id((string) get_setting('ga4_id', '')),          // G-XXXXXXX
        'gtm'     => tracking_clean_id((string) get_setting('gtm_id', '')),          // GTM-XXXXXX
        'pixel'   => tracking_clean_id((string) get_setting('meta_pixel_id', ''), '0-9'),
        'clarity' => tracking_clean_id((string) get_setting('clarity_id', '')),
        'hotjar'  => tracking_clean_id((string) get_setting('hotjar_id', ''), '0-9'),
    ];
    return $cfg;
}

/**
 * Scripts for the <head>: the pwfTrack() helper first (so it always exists),
 * then GTM, GA4, Meta Pixel, Clarity and Hotjar for whichever ids are set.
 */
function tracking_head(): string
{
    $c = tracking_config();

    // dataLayer + universal event dispatcher (always emitted; harmless when idle).
    $out = "<script>window.dataLayer=window.dataLayer||[];window.pwfTrack=function(n,p){p=p||{};"
         . "try{window.dataLayer.push(Object.assign({event:n},p));}catch(e){}"
         . "try{if(window.gtag)gtag('event',n,p);}catch(e){}"
         . "try{if(window.fbq){var m={Lead:1,Donate:1,Purchase:1,CompleteRegistration:1,Contact:1,Subscribe:1};"
         . "if(m[n])fbq('track',n,p);else fbq('trackCustom',n,p);}}catch(e){}};</script>\n";

    if ($c['gtm'] !== '') {
        $id = e($c['gtm']);
        $out .= "<!-- Google Tag Manager --><script>(function(w,d,s,l,i){w[l]=w[l]||[];"
              . "w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],"
              . "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;"
              . "j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);"
              . "})(window,document,'script','dataLayer','$id');</script>\n";
    }

    if ($c['ga4'] !== '') {
        $id = e($c['ga4']);
        $out .= "<!-- Google Analytics 4 --><script async src=\"https://www.googletagmanager.com/gtag/js?id=$id\"></script>\n"
              . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
              . "gtag('js',new Date());gtag('config','$id');</script>\n";
    }

    if ($c['pixel'] !== '') {
        $id = e($c['pixel']);
        $out .= "<!-- Meta Pixel --><script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){"
              . "n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;"
              . "n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;"
              . "t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',"
              . "'https://connect.facebook.net/en_US/fbevents.js');fbq('init','$id');fbq('track','PageView');</script>\n";
    }

    if ($c['clarity'] !== '') {
        $id = e($c['clarity']);
        $out .= "<!-- Microsoft Clarity --><script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){"
              . "(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src=\"https://www.clarity.ms/tag/\"+i;"
              . "y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y)})(window,document,\"clarity\",\"script\",\"$id\");</script>\n";
    }

    if ($c['hotjar'] !== '') {
        $id = e($c['hotjar']);
        $out .= "<!-- Hotjar --><script>(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};"
              . "h._hjSettings={hjid:$id,hjsv:6};a=o.getElementsByTagName('head')[0];r=o.createElement('script');r.async=1;"
              . "r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;a.appendChild(r);})(window,document,"
              . "'https://static.hotjar.com/c/hotjar-','.js?sv=');</script>\n";
    }

    return $out;
}

/** Immediately-after-<body> markup: GTM <noscript> + any custom body-open code. */
function tracking_body_open(): string
{
    $c   = tracking_config();
    $out = '';
    if ($c['gtm'] !== '') {
        $id = e($c['gtm']);
        $out .= "<!-- Google Tag Manager (noscript) --><noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=$id\""
              . " height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
    }
    $body = (string) get_setting('custom_js_body', '');
    if (trim($body) !== '') {
        $out .= "<script>\n" . $body . "\n</script>\n";
    }
    return $out;
}
