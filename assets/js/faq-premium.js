/* =============================================================================
 *  Premium FAQ — behaviour
 * -----------------------------------------------------------------------------
 *  The accordion itself is native <details>/<summary>, so it already works
 *  without this file. This adds:
 *    - single-open mode (admin setting)
 *    - live search filtering
 *    - staggered scroll-reveal
 *    - ripple origin for the "ripple" animation
 *    - magnetic / tilt hover variants
 *
 *  Everything is opt-in from data attributes written by the PHP renderer.
 * ========================================================================== */
(function () {
    'use strict';

    var REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function init(root) {
        if (root.dataset.pfBound) { return; }
        root.dataset.pfBound = '1';

        var items  = Array.prototype.slice.call(root.querySelectorAll('[data-pfaq-item]'));
        var search = root.querySelector('[data-pfaq-search]');
        var empty  = root.querySelector('[data-pfaq-empty]');
        var single = root.dataset.single === '1';
        var hover  = root.dataset.hover || '';
        var anim   = root.dataset.anim || '';

        /* ------------------------------------------------------- reveal */
        if ('IntersectionObserver' in window && !REDUCED) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (!en.isIntersecting) { return; }
                    en.target.classList.add('is-in');
                    io.unobserve(en.target);
                });
            }, { rootMargin: '0px 0px -6% 0px', threshold: .1 });
            items.forEach(function (el) { io.observe(el); });
        } else {
            items.forEach(function (el) { el.classList.add('is-in'); });
        }

        /* -------------------------------------------------- single open */
        if (single) {
            items.forEach(function (d) {
                d.addEventListener('toggle', function () {
                    if (!d.open) { return; }
                    items.forEach(function (o) { if (o !== d && o.open) { o.open = false; } });
                });
            });
        }

        /* ------------------------------------- ripple origin (data-anim) */
        if (anim === 'ripple' && !REDUCED) {
            items.forEach(function (d) {
                var q = d.querySelector('.pfaq-q');
                if (!q) { return; }
                q.addEventListener('click', function (e) {
                    var r = q.getBoundingClientRect();
                    q.style.setProperty('--pf-x', ((e.clientX - r.left) / r.width * 100) + '%');
                    q.style.setProperty('--pf-y', ((e.clientY - r.top) / r.height * 100) + '%');
                });
            });
        }

        /* ------------------------------------------ magnetic / tilt hover */
        if ((hover === 'magnetic' || hover === 'tilt') && !REDUCED) {
            items.forEach(function (d) {
                d.addEventListener('mousemove', function (e) {
                    var r = d.getBoundingClientRect();
                    var dx = (e.clientX - r.left) / r.width - .5;
                    var dy = (e.clientY - r.top) / r.height - .5;
                    d.style.transform = hover === 'magnetic'
                        ? 'translate(' + (dx * 7).toFixed(2) + 'px,' + (dy * 5).toFixed(2) + 'px)'
                        : 'perspective(900px) rotateX(' + (-dy * 4).toFixed(2) + 'deg) rotateY(' + (dx * 5).toFixed(2) + 'deg)';
                });
                d.addEventListener('mouseleave', function () { d.style.transform = ''; });
            });
        }

        /* -------------------------------------------------------- search */
        if (search) {
            var t;
            search.addEventListener('input', function () {
                clearTimeout(t);
                t = setTimeout(function () {
                    var q = search.value.trim().toLowerCase();
                    var shown = 0;
                    items.forEach(function (d) {
                        var hit = !q || (d.dataset.search || '').indexOf(q) > -1;
                        d.style.display = hit ? '' : 'none';
                        if (hit) { shown++; } else { d.open = false; }
                    });
                    if (empty) { empty.classList.toggle('is-on', shown === 0); }
                }, 120);
            });
        }
    }

    function boot() { document.querySelectorAll('.pfaq').forEach(init); }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.pwfFaqRefresh = boot;
})();
