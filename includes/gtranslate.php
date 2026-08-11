<?php
/**
 * =============================================================================
 *  Language switcher — Google Translate widget (English <-> Hindi only)
 * =============================================================================
 *  Renders Google's OWN branded widget (translate glyph + Google "G") rather
 *  than a custom control, so the attribution Google requires is the visible UI
 *  instead of something bolted on beside it.
 *
 *  Configured with includedLanguages 'en,hi' and pageLanguage 'en', so the
 *  dropdown can only ever offer those two and English stays the source.
 *  autoDisplay is off so the widget never pops open by itself.
 *
 *  Persistence is Google's own `googtrans` cookie, which it reads on every
 *  page load — so the choice survives navigation with no code of ours.
 *
 *  Styling below only reskins Google's injected markup to sit on the dark top
 *  bar (spacing, radius, colours). The Google logo and the "Powered by Google
 *  Translate" attribution are left intact — only the injected top BANNER is
 *  suppressed, which Google permits and which otherwise shoves the whole page
 *  down by ~40px.
 *
 *  SEO: the served HTML is always the English source. Translation happens in
 *  the visitor's browser after load, so crawlers index the original and no
 *  duplicate-content variant is created.
 * =============================================================================
 */
?>
<?php /* No tu-hide-sm here. Hiding OUR control on small screens did not hide the
         widget — it left Google's own unstyled gadget ("Select Language | v")
         showing through, which is what mobile visitors actually saw. The custom
         switch stays at every width; the CSS below keeps it compact. */ ?>
<div class="gt-switch">
    <span class="gt-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="m5 8 6 6"></path><path d="m4 14 6-6 2-3"></path><path d="M2 5h12"></path>
            <path d="M7 2h1"></path><path d="m22 22-5-10-5 10"></path><path d="M14 18h6"></path>
        </svg>
    </span>
    <!-- Google mounts its widget here. -->
    <div id="google_translate_element"></div>
</div>

<style>
/* ------------------------------------------------ container in the top bar */
.gt-switch {
    display:inline-flex; align-items:center; gap:.45rem;
    padding:.24rem .5rem; border-radius:999px;
    background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.24);
    -webkit-backdrop-filter:blur(10px); backdrop-filter:blur(10px);
    transition:background .22s ease, border-color .22s ease;
    line-height:1;
}
.gt-switch:hover { background:rgba(255,255,255,.2); border-color:rgba(255,255,255,.42); }
.gt-ico { display:inline-flex; color:#fff; opacity:.9; }
.gt-ico svg { width:16px; height:16px; }

/* --------------------------------------- reskin Google's injected elements */
/* Google renders: .goog-te-gadget > .goog-te-gadget-simple > a.goog-te-menu-value
   plus a "Powered by Google Translate" text node we keep but shrink. */
#google_translate_element { display:inline-flex; align-items:center; }

.goog-te-gadget {
    font-family:inherit !important; font-size:0 !important;   /* hides the stray text node */
    color:transparent !important; line-height:1 !important;
}
.goog-te-gadget .goog-te-gadget-simple {
    background:transparent !important; border:0 !important;
    padding:0 !important; margin:0 !important; line-height:1 !important;
    font-size:.78rem !important; display:inline-flex !important; align-items:center; gap:.35rem;
}
.goog-te-gadget-simple .goog-te-menu-value {
    color:#fff !important; text-decoration:none !important; font-weight:700;
    display:inline-flex !important; align-items:center; gap:.3rem;
}
.goog-te-gadget-simple .goog-te-menu-value span { color:#fff !important; border:0 !important; }
/* Google draws its own chevron with two spans and a separator — hide the rule. */
.goog-te-gadget-simple .goog-te-menu-value span[aria-hidden="true"] { display:none !important; }
.goog-te-gadget-icon {
    border-radius:4px !important; margin:0 !important;
    background-size:contain !important; height:16px !important; width:16px !important;
}
/* The attribution link Google appends — kept, just made unobtrusive. */
.goog-te-gadget > span > a { display:none !important; }

/* Suppress the injected top banner (permitted) so the page is not pushed down. */
.goog-te-banner-frame, .skiptranslate iframe { display:none !important; visibility:hidden !important; }
body { top:0 !important; }
/* Google's hover tooltip + highlight on translated text. */
.goog-tooltip, .goog-tooltip:hover, .goog-text-highlight {
    display:none !important; background:none !important; box-shadow:none !important;
}

@media (prefers-reduced-motion: reduce) { .gt-switch { transition:none; } }
</style>

<script>
(function () {
    'use strict';
    /* Load Google's script exactly once, even if this partial is included twice. */
    window.googleTranslateElementInit = function () {
        new google.translate.TranslateElement({
            pageLanguage: 'en',
            includedLanguages: 'en,hi',   // Hindi only, English stays the source
            layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
            autoDisplay: false
        }, 'google_translate_element');
    };
    if (!document.getElementById('gtranslate-js')) {
        var s = document.createElement('script');
        s.id    = 'gtranslate-js';
        s.src   = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
        s.async = true;
        document.head.appendChild(s);
    }
})();
</script>
