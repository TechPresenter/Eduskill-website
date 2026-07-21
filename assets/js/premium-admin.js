/* =============================================================================
   Eduskill Admin — PREMIUM interaction layer.
   Additive only: admin.js still owns the mobile sidebar toggle, theme switch,
   and SweetAlert2 CRUD helpers. This adds ripple + a desktop collapse rail.
   ============================================================================= */
(function () {
  'use strict';
  document.documentElement.classList.add('pm-js');
  var shell = document.querySelector('.admin-shell');

  /* ---- Ripple on brand buttons --------------------------------------------- */
  document.addEventListener('click', function (e) {
    var el = e.target.closest('.btn-primary, a[class*="bg-brand-600"], button[class*="bg-brand-600"], [data-ripple]');
    if (!el || el.classList.contains('pm-plain')) return;
    var r = el.getBoundingClientRect();
    var size = Math.max(r.width, r.height);
    var s = document.createElement('span');
    s.className = 'pm-ripple';
    s.style.width = s.style.height = size + 'px';
    s.style.left = (e.clientX - r.left - size / 2) + 'px';
    s.style.top = (e.clientY - r.top - size / 2) + 'px';
    if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
    el.appendChild(s);
    setTimeout(function () { s.remove(); }, 650);
  }, false);

  /* ---- Desktop sidebar collapse (persisted) -------------------------------- */
  if (shell) {
    try { if (localStorage.getItem('esk-admin-collapsed') === '1') shell.classList.add('pm-collapsed'); } catch (e) {}
    var cbtn = document.querySelector('[data-pm-collapse]');
    if (cbtn) cbtn.addEventListener('click', function () {
      var on = shell.classList.toggle('pm-collapsed');
      try { localStorage.setItem('esk-admin-collapsed', on ? '1' : '0'); } catch (e) {}
    });
  }

  /* ---- Loading state on any submitting form -------------------------------- */
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function () {
      var b = f.querySelector('button[type=submit]:not(.pm-plain)');
      if (b && !b.disabled) b.classList.add('pm-loading');
    });
  });

  /* ---- Bring the active sidebar link into view ----------------------------- */
  var active = document.querySelector('.sidebar-link-active');
  if (active && active.scrollIntoView) {
    try { active.scrollIntoView({ block: 'center' }); } catch (e) {}
  }
})();
