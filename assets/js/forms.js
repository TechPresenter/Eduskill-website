/* =============================================================================
   AJAX form handling — progressive enhancement for every [data-ajax-form].
   -----------------------------------------------------------------------------
   Markup contract:
     <form data-ajax-form data-endpoint="/pwf/forms/contact">
        ...fields (name="...")...  <?= csrf_field() ?>
        <button type="submit">Send</button>
     </form>
   Server returns JSON: { success:bool, message:string, errors?:{field:msg} }
   Uses SweetAlert2 (loaded via CDN) for dialogs; falls back to alert().
   ============================================================================ */
(function () {
    'use strict';

    const token = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function notify(type, message) {
        if (window.Swal) {
            Swal.fire({
                icon: type, title: type === 'success' ? 'Success' : (type === 'error' ? 'Oops…' : ''),
                text: message, confirmButtonColor: '#063566', timer: type === 'success' ? 3500 : undefined,
                timerProgressBar: type === 'success',
            });
        } else {
            alert(message);
        }
    }

    function clearErrors(form) {
        form.querySelectorAll('.form-error').forEach((el) => el.remove());
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    }

    function showErrors(form, errors) {
        Object.keys(errors).forEach((field) => {
            const input = form.querySelector('[name="' + field + '"]');
            if (!input) return;
            input.classList.add('is-invalid');
            const msg = document.createElement('span');
            msg.className = 'form-error';
            msg.textContent = errors[field];
            (input.closest('.form-group') || input.parentNode).appendChild(msg);
        });
        const first = form.querySelector('.is-invalid');
        if (first) first.focus();
    }

    async function submitForm(form) {
        const endpoint = form.dataset.endpoint || form.action;
        const btn = form.querySelector('[type="submit"]');
        const original = btn ? btn.innerHTML : '';
        clearErrors(form);

        if (btn) { btn.disabled = true; btn.classList.add('is-loading'); btn.innerHTML = 'Please wait…'; }

        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token() },
            });

            let data;
            const text = await res.text();
            try { data = JSON.parse(text); }
            catch (e) {
                /* A non-JSON body from an upload form is almost always a size
                   rejection: the web server (not PHP) refused the request before
                   it reached us, and answers with its own HTML error page.
                   "Unexpected server response" told the sender nothing and left
                   them retrying the same too-large files forever. */
                if (res.status === 413) {
                    throw new Error(
                        'Your attachments are too large for the server to accept. '
                        + 'Please attach smaller or fewer files and try again.'
                    );
                }
                throw new Error(
                    res.ok
                        ? 'Unexpected server response. Please try again.'
                        : 'The server refused this submission (error ' + res.status + '). '
                          + 'If you attached documents, try smaller files.'
                );
            }

            if (data.success) {
                notify('success', data.message || 'Thank you!');
                try {
                    if (window.pwfTrack) {
                        var ep = (endpoint || '').toLowerCase();
                        var ev = ep.indexOf('newsletter') > -1 ? 'Subscribe'
                               : ep.indexOf('donate') > -1 ? 'Donate'
                               : ep.indexOf('comment') > -1 ? 'Contact' : 'Lead';
                        window.pwfTrack(ev, { form: (ep.split('/forms/')[1] || ep) });
                    }
                } catch (e) {}
                /* Let a page-specific script take over the "thank you" moment —
                   swapping the form for a success panel, showing a reference
                   number, firing confetti. career-apply.js and
                   coordinator-apply.js both listen for this; until now nothing
                   dispatched it, so their success panels never appeared and the
                   only feedback was the toast above. Dispatched BEFORE reset()
                   so a listener can still read the submitted values. */
                form.dispatchEvent(new CustomEvent('pwf:submitted', { detail: data, bubbles: true }));
                form.reset();
                if (data.redirect) setTimeout(() => { window.location.href = data.redirect; }, 1200);
            } else {
                if (data.errors) showErrors(form, data.errors);
                notify('error', data.message || 'Please check the form and try again.');
                /* Counterpart to pwf:submitted. On a multi-step form the field
                   the server rejected can be on a panel that is not on screen,
                   so the toast above would be the only clue. The page script
                   uses this to bring the offending panel back into view. */
                form.dispatchEvent(new CustomEvent('pwf:failed', { detail: data, bubbles: true }));
            }
        } catch (err) {
            notify('error', err.message || 'Network error. Please try again.');
        } finally {
            if (btn) { btn.disabled = false; btn.classList.remove('is-loading'); btn.innerHTML = original; }
        }
    }

    // Lightweight inline validation on blur (HTML5 constraint API).
    function initInlineValidation(form) {
        form.querySelectorAll('input, textarea, select').forEach((field) => {
            field.addEventListener('blur', () => {
                if (field.value && !field.checkValidity()) field.classList.add('is-invalid');
                else field.classList.remove('is-invalid');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[data-ajax-form]').forEach((form) => {
            initInlineValidation(form);
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                submitForm(form);
            });
        });
    });
})();
