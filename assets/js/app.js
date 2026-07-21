/* Eduskill public site — vanilla JS, no framework. */
(function () {
  'use strict';

  // Sticky header shadow + back-to-top visibility.
  var header = document.getElementById('esk-header');
  var top = document.getElementById('esk-top');
  function onScroll() {
    var y = window.scrollY;
    if (header) header.classList.toggle('site-header-scrolled', y > 8);
    if (top) top.style.display = y > 400 ? 'grid' : 'none';
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  if (top) top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });

  // Mobile menu drawer.
  var burger = document.getElementById('esk-burger');
  var drawer = document.getElementById('esk-drawer');
  if (burger && drawer) burger.addEventListener('click', function () { drawer.classList.toggle('hidden'); });

  // Animated counters (Indian grouping), respecting reduced-motion.
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var counters = document.querySelectorAll('[data-count]:not([data-done])');
  if (counters.length) {
    if (!('IntersectionObserver' in window) || reduce) {
      counters.forEach(function (el) { el.textContent = Number(el.getAttribute('data-count')).toLocaleString('en-IN'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (!en.isIntersecting) return;
          var el = en.target; io.unobserve(el); el.setAttribute('data-done', '1');
          var target = parseInt(el.getAttribute('data-count'), 10) || 0, start = null;
          (function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / 1400, 1);
            el.textContent = Math.floor(p * target).toLocaleString('en-IN');
            if (p < 1) requestAnimationFrame(step);
          })(performance.now());
          requestAnimationFrame(function (t) {});
        });
      }, { threshold: 0.4 });
      counters.forEach(function (el) { io.observe(el); });
    }
  }
})();
