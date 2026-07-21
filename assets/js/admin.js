/* Eduskill admin — vanilla JS. Sidebar/theme toggles + a small REST helper for the API. */
(function () {
  'use strict';

  // Sidebar drawer (mobile) + theme toggle.
  var sb = document.getElementById('esk-sidebar');
  var bd = document.getElementById('esk-sidebar-backdrop');
  var toggle = document.getElementById('esk-sidebar-toggle');
  if (toggle) toggle.addEventListener('click', function () {
    if (!sb) return;
    var hidden = sb.classList.toggle('admin-sidebar-hidden');
    if (bd) bd.classList.toggle('hidden', hidden);
  });
  if (bd) bd.addEventListener('click', function () { sb && sb.classList.add('admin-sidebar-hidden'); bd.classList.add('hidden'); });

  var themeBtn = document.getElementById('esk-theme-toggle');
  if (themeBtn) themeBtn.addEventListener('click', function () {
    var dark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('esk-theme', dark ? 'dark' : 'light'); } catch (e) {}
  });
})();

/* REST helper. Sends the CSRF token as a header and parses JSON. */
window.eskApi = function (path, method, data) {
  var meta = document.querySelector('meta[name="csrf-token"]');
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  var opts = {
    method: method || 'GET',
    headers: { 'Accept': 'application/json', 'X-CSRF-Token': meta ? meta.content : '' }
  };
  if (data) {
    if (data instanceof FormData) {
      data.append('_csrf', meta ? meta.content : '');
      opts.body = data;
    } else {
      opts.headers['Content-Type'] = 'application/json';
      data._csrf = meta ? meta.content : '';
      opts.body = JSON.stringify(data);
    }
  }
  return fetch(base + '/api/' + path, opts).then(function (r) {
    return r.json().catch(function () { return { ok: false, message: 'Server error (' + r.status + ')' }; });
  });
};

/* Generic resource form submit → REST API (create/update) with SweetAlert2 feedback. */
window.eskResourceForm = function (f) {
  if (!f) return;
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var resource = f.getAttribute('data-resource');
    var fd = new FormData(f);
    var id = fd.get('id');
    var data = {};
    fd.forEach(function (v, k) { data[k] = v; });
    f.querySelectorAll('input[type=checkbox]').forEach(function (cb) { data[cb.name] = cb.checked ? 1 : 0; });
    var path = id && id !== '0' ? resource + '.php?id=' + id : resource + '.php';
    var method = id && id !== '0' ? 'PUT' : 'POST';
    var btn = f.querySelector('button[type=submit]'); btn.disabled = true;
    window.eskApi(path, method, data).then(function (d) {
      btn.disabled = false;
      if (d.ok) {
        Swal.fire({ icon: 'success', title: 'Saved', timer: 1000, showConfirmButton: false })
          .then(function () { location.href = document.querySelector('meta[name=base-url]').content + '/admin/' + resource + '.php'; });
      } else {
        Swal.fire({ icon: 'error', title: 'Could not save', text: d.message || 'Please check the form.' });
      }
    });
  });
};

/* Image upload widgets (.esk-upload). Upload on file-select → api/upload.php → set hidden path + preview. */
document.addEventListener('change', function (e) {
  var input = e.target;
  if (!input.classList || !input.classList.contains('esk-upload-input')) return;
  var wrap = input.closest('.esk-upload');
  if (!wrap || !input.files || !input.files[0]) return;
  var hidden = wrap.querySelector('input[type=hidden]');
  var preview = wrap.querySelector('.esk-upload-preview');
  var fd = new FormData();
  fd.append('file', input.files[0]);
  if (preview) preview.innerHTML = '<span class="text-2xs text-content-muted">Uploading…</span>';
  window.eskApi('upload.php', 'POST', fd).then(function (d) {
    input.value = '';
    if (d.ok) {
      if (hidden) hidden.value = d.path;
      if (preview) preview.innerHTML = '<img src="' + d.url + '" class="h-full w-full object-cover">';
    } else {
      if (preview) preview.innerHTML = '<i class="fa-regular fa-image text-content-subtle"></i>';
      Swal.fire({ icon: 'error', title: 'Upload failed', text: d.message || 'Please try another file.' });
    }
  });
});
document.addEventListener('click', function (e) {
  var btn = e.target.closest ? e.target.closest('.esk-upload-clear') : null;
  if (!btn) return;
  var wrap = btn.closest('.esk-upload');
  var hidden = wrap.querySelector('input[type=hidden]');
  var preview = wrap.querySelector('.esk-upload-preview');
  if (hidden) hidden.value = '';
  if (preview) preview.innerHTML = '<i class="fa-regular fa-image text-content-subtle"></i>';
});

/* Delete-with-confirm used by list pages. */
window.eskDelete = function (resource, id, label) {
  Swal.fire({
    title: 'Delete ' + (label || 'this item') + '?',
    text: 'This cannot be undone.',
    icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Delete'
  }).then(function (res) {
    if (!res.isConfirmed) return;
    window.eskApi(resource + '.php?id=' + id, 'DELETE').then(function (d) {
      if (d.ok) {
        Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false })
          .then(function () { location.reload(); });
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: d.message || 'Could not delete.' });
      }
    });
  });
};
