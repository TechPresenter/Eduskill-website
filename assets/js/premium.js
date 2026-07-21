/* =============================================================================
   Eduskill — PREMIUM interaction layer (public site).
   Additive only: it never re-binds elements app.js already controls
   (#esk-burger / #esk-drawer / #esk-top / [data-count]). Pure vanilla JS.
   ============================================================================= */
(function () {
  'use strict';
  var root = document.documentElement;
  root.classList.add('pm-js');
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- Ripple on primary / brand CTA buttons -------------------------------- */
  function isCta(el) {
    return el.matches('.btn-primary, a[class*="bg-brand-600"], button[class*="bg-brand-600"], [data-ripple]') &&
      !el.classList.contains('pm-plain');
  }
  document.addEventListener('click', function (e) {
    var el = e.target.closest('.btn-primary, a[class*="bg-brand-600"], button[class*="bg-brand-600"], [data-ripple]');
    if (!el || el.classList.contains('pm-plain')) return;
    var r = el.getBoundingClientRect();
    var size = Math.max(r.width, r.height);
    var span = document.createElement('span');
    span.className = 'pm-ripple';
    span.style.width = span.style.height = size + 'px';
    span.style.left = (e.clientX - r.left - size / 2) + 'px';
    span.style.top = (e.clientY - r.top - size / 2) + 'px';
    if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
    el.appendChild(span);
    setTimeout(function () { span.remove(); }, 650);
  }, false);

  /* ---- Scroll reveal -------------------------------------------------------- */
  var reveals = document.querySelectorAll('[data-reveal]');
  if (reveals.length) {
    if (reduce || !('IntersectionObserver' in window)) {
      reveals.forEach(function (el) { el.classList.add('is-in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (!en.isIntersecting) return;
          var el = en.target;
          var d = parseInt(el.getAttribute('data-reveal-delay'), 10) || 0;
          setTimeout(function () { el.classList.add('is-in'); }, d);
          io.unobserve(el);
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
      reveals.forEach(function (el) { io.observe(el); });
    }
  }

  /* ---- Subtle hero parallax ------------------------------------------------- */
  var hero = document.querySelector('.hero');
  if (hero && !reduce) {
    window.addEventListener('scroll', function () {
      var y = window.scrollY;
      if (y < 700) hero.style.setProperty('--pm-parallax', (y * 0.15) + 'px');
    }, { passive: true });
  }

  /* ---- Premium navbar: mobile off-canvas + mega-menu toggles ---------------- */
  /* Only active if the premium navbar markup is present (data-pm-* hooks),
     so it never clashes with the default #esk-burger/#esk-drawer wiring. */
  var navToggle = document.querySelector('[data-pm-nav-toggle]');
  var nav = document.querySelector('[data-pm-nav]');
  var navClose = document.querySelector('[data-pm-nav-close]');
  function setNav(open) {
    if (!nav) return;
    nav.classList.toggle('is-open', open);
    document.body.classList.toggle('pm-noscroll', open);
    if (navToggle) navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (navToggle && nav) {
    navToggle.addEventListener('click', function () { setNav(!nav.classList.contains('is-open')); });
    if (navClose) navClose.addEventListener('click', function () { setNav(false); });
    nav.addEventListener('click', function (e) { if (e.target === nav) setNav(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });
    nav.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { setNav(false); }); });
  }

  /* Accordion sub-menus inside the mobile drawer */
  document.querySelectorAll('[data-pm-acc-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = btn.nextElementSibling;
      var open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', open ? 'false' : 'true');
      if (panel) panel.style.maxHeight = open ? '0px' : panel.scrollHeight + 'px';
    });
  });

  /* ---- Button loading-state helper (opt-in via data-loading forms) ---------- */
  document.querySelectorAll('form[data-loading]').forEach(function (f) {
    f.addEventListener('submit', function () {
      var b = f.querySelector('[type=submit]');
      if (b) b.classList.add('pm-loading');
    });
  });
})();
