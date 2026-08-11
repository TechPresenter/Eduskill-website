<?php /* Premium hero — scoped styles + behaviour. Included once by index.php. */ ?>
<style>
/* Font comes from the Theme Engine (font.heading) so the hero can never drift
   away from the rest of the site's typography. */
.hx{position:relative;isolation:isolate;overflow:hidden;color:#fff;display:flex;align-items:center;
    --acc:var(--accent,#F15A24);font-family:var(--font-head,system-ui,sans-serif)}
.hx-h-tall{min-height:clamp(560px,74vh,780px)}
.hx-h-full{min-height:100vh}
.hx-h-auto{min-height:auto;padding:clamp(3.5rem,8vw,6rem) 0}
.hx-h-tall,.hx-h-full{padding:clamp(4rem,7vw,6rem) 0}
/* backgrounds + slider layers */
.hx-bgs{position:absolute;inset:0;z-index:-2}
.hx-bg{position:absolute;inset:0;opacity:0;transition:opacity 1s ease;will-change:opacity,transform}
.hx-bg.on{opacity:1}
.hx-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.hx-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(8,11,26,.35),rgba(8,11,26,.72));display:block}
/* floating decorative blobs */
.hx-blob{position:absolute;border-radius:50%;filter:blur(60px);opacity:.5;z-index:-1;pointer-events:none;mix-blend-mode:screen;will-change:transform}
.hx-blob.b1{width:460px;height:460px;top:-120px;right:-60px;background:radial-gradient(circle,var(--acc),transparent 68%);animation:hxfloat 14s ease-in-out infinite}
.hx-blob.b2{width:380px;height:380px;bottom:-140px;left:-40px;background:radial-gradient(circle,#22d3ee,transparent 68%);animation:hxfloat 18s ease-in-out infinite reverse}
.hx-blob.b3{width:300px;height:300px;top:30%;left:44%;background:radial-gradient(circle,#f472b6,transparent 68%);animation:hxfloat 20s ease-in-out infinite}
@keyframes hxfloat{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(28px,-30px) scale(1.06)}66%{transform:translate(-24px,20px) scale(.96)}}
/* content */
.hx-container{position:relative;z-index:2;width:100%}
.hx-copy{display:none}
.hx-copy.on{display:block}
.hx-copy.is-split.on{display:grid;grid-template-columns:1.1fr .9fr;gap:clamp(1.5rem,4vw,3.5rem);align-items:center}
.hx-align-center .hx-text{max-width:860px;margin-inline:auto;text-align:center}
.hx-align-center .hx-actions,.hx-align-center .hx-trust{justify-content:center}
.hx-align-right .hx-text{margin-left:auto;text-align:right}
.hx-align-right .hx-actions,.hx-align-right .hx-trust{justify-content:flex-end}
.hx-text{max-width:640px}
.hx-badge{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1rem;border-radius:999px;
    background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);backdrop-filter:blur(10px);
    font-size:.8rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;margin-bottom:1.15rem}
.hx-badge svg{width:1em;height:1em}
.hx-title{font-size:clamp(2.3rem,5.4vw,4rem);line-height:1.04;font-weight:800;letter-spacing:-.022em;margin:0 0 1.1rem;text-wrap:balance}
/* The heading is an <h1> on the first slide and a <p> on the rest (index.php:100).
   tailwind.css targets those ELEMENTS directly — `h1..h6{color:var(--primary)}`
   (#0B4E3D) and `p{color:var(--text-soft)}` — and an element's own colour rule
   beats the white `color` inherited from `.hx`. Every slide therefore painted its
   plain text dark-on-dark, leaving only the gradient .hx-hl span visible, which
   read as "the first half of the heading is missing". Pin it white here, and
   repeat for the dark-theme selector because `:root[data-theme] h1` (0,2,1) is
   more specific than a bare `.hx .hx-title` (0,2,0). */
.hx .hx-title,
:root[data-theme="dark"] .hx .hx-title{color:#fff;text-shadow:0 2px 18px rgba(8,11,26,.45)}
.hx-hl{background:linear-gradient(120deg,var(--acc),#f9d3ff 60%,#fff);-webkit-background-clip:text;background-clip:text;color:transparent;
       -webkit-text-fill-color:transparent}
/* The gradient span is painted through transparent glyphs, so an inherited
   text-shadow would show through and muddy it. */
.hx .hx-hl{text-shadow:none}
.hx-type::after{content:'';display:inline-block;width:2px;height:1em;margin-left:2px;background:currentColor;vertical-align:-.12em;
    -webkit-text-fill-color:var(--acc);background:var(--acc);animation:hxcaret 1s steps(1) infinite}
@keyframes hxcaret{50%{opacity:0}}
.hx-sub{font-size:clamp(1rem,1.5vw,1.2rem);line-height:1.7;color:rgba(255,255,255,.9);max-width:56ch;margin:0 0 1.6rem}
.hx-align-center .hx-sub{margin-inline:auto}
.hx-align-right .hx-sub{margin-left:auto}
.hx-trust{display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap;margin-bottom:1.7rem;font-size:.9rem}
.hx-rating{display:inline-flex;align-items:center;gap:.45rem}
.hx-stars{display:inline-flex;gap:1px}
.hx-star{color:rgba(255,255,255,.35);display:inline-flex}.hx-star svg{width:1.05em;height:1.05em}
.hx-star.on{color:#fbbf24}
.hx-star.on svg{fill:#fbbf24}
.hx-rate-num{font-weight:800}
.hx-trust-txt{display:inline-flex;align-items:center;gap:.4rem;color:rgba(255,255,255,.86);font-weight:600}
.hx-trust-txt svg{width:1.05em;height:1.05em;color:#4CA383}
.hx-actions{display:flex;gap:.8rem;flex-wrap:wrap;align-items:center}
/* premium CTAs */
/* --- Premium 3D glowing gradient CTAs --- */
.hx-btn{position:relative;display:inline-flex;align-items:center;gap:.6rem;padding:1rem 1.9rem;border-radius:16px;
    font-weight:800;font-size:1.02rem;letter-spacing:.01em;text-decoration:none;cursor:pointer;isolation:isolate;
    transition:transform .22s cubic-bezier(.34,1.4,.64,1),box-shadow .28s ease,filter .28s ease;border:0;color:#fff;
    transform-style:preserve-3d}
.hx-btn svg{width:1.15em;height:1.15em;transition:transform .25s ease}
.hx-btn:hover svg{transform:translateX(2px)}
.hx-btn:active{transform:translateY(1px) scale(.985)}
/* animated sheen sweep */
.hx-btn::before{content:'';position:absolute;inset:0;border-radius:inherit;overflow:hidden;z-index:1;
    background:linear-gradient(105deg,transparent 35%,rgba(255,255,255,.42) 50%,transparent 65%);
    background-size:250% 100%;background-position:200% 0;transition:background-position .7s ease;pointer-events:none}
.hx-btn:hover::before{background-position:-60% 0}
/* soft outer glow halo */
.hx-btn::after{content:'';position:absolute;inset:-3px;z-index:-1;border-radius:inherit;opacity:0;filter:blur(14px);
    transition:opacity .3s ease}
.hx-btn:hover::after{opacity:.85}

/* PRIMARY — the brand CTA orange, solid.
   This was a three-stop orange→amber→#FBBF24 gradient sitting on a hard 6px
   "3D" base edge. Two problems: the pale amber end put white label text at
   roughly 1.9:1, and the whole treatment is exactly the cheap-gradient button
   the brand direction rules out. Solid --accent is 3.23:1 against the green
   field, and the 1px inner hairline gives the control its boundary. */
.hx-btn.is-primary,.hx-btn-gradient.is-primary{
    background:var(--accent-ink,#CA4C1E);
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.34), 0 12px 28px -14px rgba(0,0,0,.6)}
.hx-btn.is-primary:hover{transform:translateY(-3px);
    background:color-mix(in srgb,var(--accent,#F15A24) 74%,#000);
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.48), 0 18px 38px -14px rgba(0,0,0,.65)}
.hx-btn.is-primary:active{transform:translateY(0)}
.hx-btn.is-primary::after{background:var(--accent,#F15A24)}

/* GRADIENT (secondary style) — brand green, solid */
.hx-btn-gradient:not(.is-primary){
    background:var(--success,#2F8065);
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.26), 0 12px 26px -14px rgba(0,0,0,.55)}
.hx-btn-gradient:not(.is-primary):hover{transform:translateY(-3px);
    background:color-mix(in srgb,var(--success,#2F8065) 88%,#fff);
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.4), 0 18px 36px -14px rgba(0,0,0,.6)}
.hx-btn-gradient:not(.is-primary)::after{background:var(--success,#2F8065)}
.hx-btn-glass{background:rgba(255,255,255,.14);box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.4), 0 10px 26px -12px rgba(0,0,0,.5);
    backdrop-filter:blur(12px)}
.hx-btn-glass:hover{background:rgba(255,255,255,.24);transform:translateY(-4px);
    box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.6), 0 18px 38px -12px rgba(0,0,0,.6)}
.hx-btn-glass::after{background:rgba(255,255,255,.5)}
.hx-btn-glass:hover{background:rgba(255,255,255,.2)}
.hx-btn-outline{background:transparent;border-color:rgba(255,255,255,.55)}
.hx-btn-outline:hover{background:rgba(255,255,255,.14)}
.hx-btn-solid{background:#fff;color:#111827}
.hx-btn-solid:hover{box-shadow:0 16px 34px -12px rgba(0,0,0,.5)}
/* split media */
.hx-media{position:relative;justify-self:center}
.hx-img{width:100%;max-width:520px;border-radius:24px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.18);
    animation:hxbob 6s ease-in-out infinite}
.hx-media-glow{position:absolute;inset:-10% -6%;z-index:-1;border-radius:50%;filter:blur(70px);opacity:.55;
    background:radial-gradient(circle,var(--acc),transparent 70%)}
@keyframes hxbob{0%,100%{transform:translateY(0)}50%{transform:translateY(-16px)}}
/* shape divider */
.hx-divider{position:absolute;left:0;right:0;bottom:-1px;line-height:0;z-index:2}
.hx-divider svg{width:100%;height:clamp(48px,7vw,110px);display:block;fill:var(--divider-fill,#ffffff)}
html[data-theme="dark"] .hx-divider svg{fill:#0b1220}
/* dots */
.hx-dots{position:absolute;bottom:calc(clamp(48px,7vw,110px) + 18px);left:50%;transform:translateX(-50%);z-index:3;display:flex;gap:.5rem}
.hx-align-left .hx-dots{left:max(24px,calc((100vw - 1200px)/2 + 24px));transform:none}
.hx-dot{width:26px;height:6px;border-radius:999px;border:0;background:rgba(255,255,255,.4);cursor:pointer;transition:all .25s ease;padding:0}
.hx-dot.on{background:#fff;width:40px}
/* entrance animations */
.hx-animate .hx-copy.on .hx-badge{animation:hxrise .6s .05s both}
.hx-animate .hx-copy.on .hx-title{animation:hxrise .6s .14s both}
.hx-animate .hx-copy.on .hx-sub{animation:hxrise .6s .24s both}
.hx-animate .hx-copy.on .hx-trust{animation:hxrise .6s .32s both}
.hx-animate .hx-copy.on .hx-actions{animation:hxrise .6s .4s both}
.hx-animate .hx-copy.on .hx-media{animation:hxzoom .8s .2s both}
@keyframes hxrise{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}
@keyframes hxzoom{from{opacity:0;transform:scale(.94) translateY(14px)}to{opacity:1;transform:none}}
/* =============================================================================
   EDITORIAL HERO  (.hx-panel)
   Copy left, photograph right, on a solid forest-green field.

   Replaces the previous treatment, which laid white text directly over a
   full-bleed photograph at overlay:0 — the headline competed with faces and
   foliage and failed contrast at every breakpoint. Here the type sits on a
   flat brand field (white on #0B4E3D = 9.67:1) and the photograph is given
   its own space instead of doing two jobs at once.
   ========================================================================= */
.hx-panel{background:var(--primary,#0B4E3D);overflow:hidden}
.hx-panel .hx-bgs{display:none}                 /* no full-bleed photo behind type */
.hx-panel .hx-container{max-width:var(--container,1200px)}

/* Organic accents: one warm sunrise bloom, one thin arc. Both yellow, both
   far below the copy in contrast so they never compete with content. */
.hx-panel .hx-blob{mix-blend-mode:normal;filter:blur(90px)}
.hx-panel .hx-blob.b1{width:520px;height:520px;top:-180px;right:-120px;
    background:radial-gradient(circle,var(--yellow,#FFE987),transparent 70%);opacity:.16;animation-duration:22s}
.hx-panel .hx-blob.b2{width:420px;height:420px;bottom:-200px;left:-140px;
    background:radial-gradient(circle,var(--gold,#E8C52E),transparent 72%);opacity:.10;animation-duration:26s}
.hx-panel .hx-blob.b3{display:none}

.hx-panel .hx-copy.is-split.on{
    display:grid;grid-template-columns:1.02fr .98fr;
    gap:clamp(2rem,5vw,4.5rem);align-items:center}

.hx-panel .hx-text{max-width:36em}

/* Eyebrow: a rule + uppercase label instead of a glassy pill. */
.hx-panel .hx-badge{background:none;border:0;backdrop-filter:none;padding:0;border-radius:0;
    color:var(--yellow,#FFE987);font-size:.76rem;font-weight:700;letter-spacing:.16em;
    margin-bottom:1.1rem;gap:.65rem}
.hx-panel .hx-badge::before{content:"";width:28px;height:2px;flex:none;border-radius:2px;
    background:currentColor}

.hx-panel .hx-title{font-size:clamp(2.2rem,4.6vw,3.9rem);line-height:1.05;letter-spacing:-.025em;
    margin-bottom:1.15rem}
.hx .hx-panel .hx-title,.hx-panel .hx-title{text-shadow:none}

/* Highlighted words: solid yellow with a hand-drawn-weight underline, rather
   than a gradient clipped through glyphs (which greyed out on dark fields). */
.hx-panel .hx-hl{background:none;-webkit-background-clip:initial;background-clip:initial;
    color:var(--yellow,#FFE987);-webkit-text-fill-color:var(--yellow,#FFE987);
    text-decoration:underline;text-decoration-thickness:.055em;text-underline-offset:.16em;
    text-decoration-color:rgba(255,233,135,.42)}

.hx-panel .hx-sub{font-size:clamp(1rem,1.15vw,1.09rem);line-height:1.75;
    color:rgba(255,255,255,.82);max-width:48ch;margin-bottom:2rem}
.hx-panel .hx-trust{color:rgba(255,255,255,.85)}

/* Photograph: generous but not excessive rounding, subtle depth, no bobbing. */
.hx-panel .hx-media{justify-self:end;width:100%}
.hx-panel .hx-media-glow{display:none}
.hx-panel .hx-img{width:100%;max-width:none;aspect-ratio:4/3.4;object-fit:cover;
    border-radius:clamp(18px,2vw,26px);border:0;animation:none;
    box-shadow:0 40px 80px -34px rgba(4,26,18,.7)}

/* --- Mobile: badge → heading → subtitle → IMAGE → trust → buttons ---
   The collapse selector MUST carry .hx-panel. `.hx-copy.is-split.on` is (0,2,0)
   while the desktop panel rule `.hx-panel .hx-copy.is-split.on` is (0,3,0), so
   the two-column grid outranked the mobile flex column and never collapsed:
   in editorial panel mode the headline stayed in a ~50% track at 375px and was
   clipped, and because .hx-text is display:contents the title measured 0 wide.
   Both selectors are listed here so the collapse wins in either hero mode. */
@media (max-width:820px){
    .hx-panel .hx-copy.is-split.on,
    .hx-copy.is-split.on{
        display:flex;flex-direction:column;text-align:center;gap:0;
        grid-template-columns:none}
    .hx-panel .hx-text{max-width:none}
    .hx-panel .hx-badge{justify-content:center}
    .hx-panel .hx-img{aspect-ratio:16/11;border-radius:16px}
    .hx-panel .hx-sub{margin-inline:auto}
    .hx-panel .hx-media{justify-self:auto}
    /* let the text children join the flex flow so the image can sit between them */
    .hx-copy.is-split .hx-text{display:contents}
    .hx-copy.is-split .hx-badge{order:1;align-self:center}
    .hx-copy.is-split .hx-title{order:2}
    .hx-copy.is-split .hx-sub{order:3;margin-inline:auto}
    .hx-copy.is-split .hx-media{order:4;margin:.4rem auto 1.3rem}
    .hx-copy.is-split .hx-trust{order:5;justify-content:center}
    .hx-copy.is-split .hx-actions{order:6;justify-content:center}
    .hx-img{max-width:min(78vw,340px)}
    .hx-actions .hx-btn{flex:1 1 auto;justify-content:center;padding:.95rem 1.4rem;font-size:.98rem}
    .hx-title{font-size:clamp(2rem,8vw,2.6rem)}
    /* Mobile is not a stacked desktop: the hero gets a shorter field, the
       decorative blooms come off (they cost a full-viewport blur on a phone GPU
       and are invisible behind centred copy anyway), and the parallax has no
       room to travel. */
    .hx-h-tall,.hx-h-full{min-height:auto;padding:clamp(2.75rem,9vw,4rem) 0}
    .hx-panel .hx-blob{display:none}
    .hx-panel .hx-img{max-width:none;width:100%}
}
/* Slider dots: the visible bar stays 6px, but the control itself is a 40px
   touch target (it was 26x6 — a quarter of the 44px minimum in either axis). */
@media (max-width:820px){
    .hx-dots{bottom:14px;gap:.15rem}
    .hx-dot{width:40px;height:40px;background:none;display:grid;place-items:center}
    .hx-dot::before{content:'';display:block;width:22px;height:5px;border-radius:999px;
        background:rgba(255,255,255,.45);transition:width .25s ease,background .25s ease}
    .hx-dot.on{width:44px;background:none}
    .hx-dot.on::before{width:30px;background:#fff}
}
@media (prefers-reduced-motion:reduce){
    .hx-blob,.hx-img{animation:none}
    .hx-animate .hx-copy.on *{animation:none !important}
    .hx-bg{transition:none}
}
</style>
<script>
(function () {
    var hero = document.querySelector('[data-hx]');
    if (!hero) return;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---- Slider ---- */
    var bgs = hero.querySelectorAll('.hx-bg'), copies = hero.querySelectorAll('.hx-copy'),
        dots = hero.querySelectorAll('.hx-dot'), n = bgs.length, cur = 0, timer = null,
        interval = parseInt(hero.getAttribute('data-interval'), 10) || 6000;
    function go(k) {
        k = (k + n) % n; if (k === cur && bgs[k].classList.contains('on')) return;
        [bgs, copies, dots].forEach(function (set) {
            set.forEach(function (el, i) { el.classList.toggle('on', i === k); });
        });
        cur = k; retype(copies[k]);
    }
    function play() { if (n > 1 && !reduce) { clearInterval(timer); timer = setInterval(function () { go(cur + 1); }, interval); } }
    dots.forEach(function (d, i) { d.addEventListener('click', function () { go(i); play(); }); });
    hero.addEventListener('mouseenter', function () { clearInterval(timer); });
    hero.addEventListener('mouseleave', play);

    /* ---- Typing animation ---- */
    var typers = [];
    function initType(scope) {
        (scope || hero).querySelectorAll('.hx-type').forEach(function (el) {
            if (el.__typed) return; el.__typed = true;
            var words = (el.getAttribute('data-words') || '').split('|').filter(Boolean);
            if (words.length < 2 || reduce) return;
            el.__state = { words: words, w: 0, i: (words[0] || '').length, del: false, run: false };
        });
    }
    function retype(copy) {
        if (!copy) return;
        copy.querySelectorAll('.hx-type').forEach(function (el) {
            if (!el.__state) return; var st = el.__state;
            if (st.run) return; st.run = true;
            (function step() {
                if (!copy.classList.contains('on')) { st.run = false; return; }
                var word = st.words[st.w];
                if (!st.del) {
                    st.i++; el.textContent = word.slice(0, st.i);
                    if (st.i >= word.length) { st.del = true; setTimeout(step, 1400); return; }
                } else {
                    st.i--; el.textContent = word.slice(0, st.i);
                    if (st.i <= 0) { st.del = false; st.w = (st.w + 1) % st.words.length; }
                }
                setTimeout(step, st.del ? 45 : 90);
            })();
        });
    }
    initType(); retype(hero.querySelector('.hx-copy.on'));

    /* ---- Parallax (pointer + scroll) on blobs + background ---- */
    if (!reduce) {
        var blobs = hero.querySelectorAll('.hx-blob'), bgWrap = hero.querySelector('[data-hx-parallax]');
        hero.addEventListener('pointermove', function (e) {
            var r = hero.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
            blobs.forEach(function (b, i) { var d = (i + 1) * 14; b.style.transform = 'translate(' + (x * d) + 'px,' + (y * d) + 'px)'; });
        });
        var primary = hero.querySelector('.hx-btn.is-primary');
        if (primary) primary.addEventListener('pointermove', function (e) {
            var r = primary.getBoundingClientRect();
            primary.style.setProperty('--rx', ((e.clientX - r.left) / r.width * 100) + '%');
            primary.style.setProperty('--ry', ((e.clientY - r.top) / r.height * 100) + '%');
        });
        window.addEventListener('scroll', function () {
            if (!bgWrap) return; var y = Math.min(window.scrollY, 600);
            bgWrap.style.transform = 'translateY(' + (y * .18) + 'px) scale(1.05)';
        }, { passive: true });
    }
    play();
    if (window.lucide && window.lucide.createIcons) { try { window.lucide.createIcons(); } catch (e) {} }
})();
</script>