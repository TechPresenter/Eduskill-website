/* CMS page editor — add / remove / reorder sections and repeatable rows. No framework, no build. */
(function () {
  'use strict';
  var form = document.getElementById('page-editor');
  if (!form) return;
  var sectionsEl = document.getElementById('sections');
  var emptyEl = document.getElementById('sections-empty');
  var seq = sectionsEl.querySelectorAll('.section-item').length + 1;
  var rowSeq = 1;

  function uid(p) { return p + Date.now().toString(36) + '-' + (seq++); }
  function tpl(cls, type) { return document.querySelector('template.' + cls + '[data-type="' + type.replace(/"/g, '') + '"]'); }
  function refreshEmpty() { if (emptyEl) emptyEl.classList.toggle('hidden', sectionsEl.querySelectorAll('.section-item').length > 0); }

  var addBtn = document.getElementById('add-section-btn');
  var addSel = document.getElementById('add-section-type');
  if (addBtn && addSel) addBtn.addEventListener('click', function () {
    var t = tpl('section-template', addSel.value);
    if (!t) return;
    sectionsEl.insertAdjacentHTML('beforeend', t.innerHTML.split('__KEY__').join(uid('s')));
    refreshEmpty();
    var el = sectionsEl.lastElementChild;
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  sectionsEl.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('button') : null;
    if (!btn) return;
    var card = btn.closest('.section-item');
    if (!card) return;
    if (btn.classList.contains('section-remove')) { card.remove(); refreshEmpty(); }
    else if (btn.classList.contains('section-up')) { var p = card.previousElementSibling; if (p) sectionsEl.insertBefore(card, p); }
    else if (btn.classList.contains('section-down')) { var n = card.nextElementSibling; if (n) sectionsEl.insertBefore(n, card); }
    else if (btn.classList.contains('repeat-add')) {
      var rt = tpl('row-template', card.getAttribute('data-type'));
      if (!rt) return;
      var rows = card.querySelector('.repeat-rows');
      rows.insertAdjacentHTML('beforeend', rt.innerHTML.split('__KEY__').join(card.getAttribute('data-key')).split('__ROW__').join('r' + (rowSeq++) + '-' + Date.now().toString(36)));
    }
    else if (btn.classList.contains('row-remove')) { var r = btn.closest('.repeat-row'); if (r) r.remove(); }
  });

  // Write DOM order into position inputs on submit so the server saves the visible order.
  form.addEventListener('submit', function () {
    sectionsEl.querySelectorAll('.section-item').forEach(function (card, i) {
      var pos = card.querySelector('.pos-input');
      if (pos) pos.value = String(i + 1);
    });
  });
})();
