/* =============================================================================
   EDUSKILL INDIA FOUNDATION — PREMIUM interactions (v2), vanilla ES6.
   Loaded after main.js. Adds: page loader, card tilt, button ripple, marquee
   auto-clone, mouse parallax, tabs, before/after slider, multi-step forms,
   password strength + show/hide, OTP inputs, toast helper.
   ============================================================================ */
(function () {
    'use strict';

    /* Card tilt (3D) for .tilt elements */
    function initTilt() {
        document.querySelectorAll('.tilt').forEach((el) => {
            const max = parseFloat(el.dataset.tilt) || 8;
            el.addEventListener('pointermove', (e) => {
                const r = el.getBoundingClientRect();
                const px = (e.clientX - r.left) / r.width - 0.5;
                const py = (e.clientY - r.top) / r.height - 0.5;
                el.style.transform = `perspective(800px) rotateY(${px * max}deg) rotateX(${-py * max}deg) translateY(-4px)`;
            });
            el.addEventListener('pointerleave', () => { el.style.transform = ''; });
        });
    }

    /* Button ripple */
    function initRipple() {
        document.querySelectorAll('.btn, .btn-3d, .btn-grad').forEach((btn) => {
            btn.addEventListener('click', function (e) {
                const r = this.getBoundingClientRect();
                const ink = document.createElement('span');
                ink.className = 'ripple-ink';
                const size = Math.max(r.width, r.height);
                ink.style.width = ink.style.height = size + 'px';
                ink.style.left = (e.clientX - r.left - size / 2) + 'px';
                ink.style.top = (e.clientY - r.top - size / 2) + 'px';
                this.appendChild(ink);
                setTimeout(() => ink.remove(), 600);
            });
        });
    }

    /* Marquee — duplicate track content for a seamless loop */
    function initMarquee() {
        document.querySelectorAll('.marquee-track').forEach((track) => {
            if (track.dataset.cloned) return;
            track.innerHTML += track.innerHTML;
            track.dataset.cloned = '1';
        });
    }

    /* Mouse parallax for [data-mouse-parallax] children */
    function initMouseParallax() {
        const zones = document.querySelectorAll('[data-mouse-parallax]');
        if (!zones.length) return;
        zones.forEach((zone) => {
            zone.addEventListener('pointermove', (e) => {
                const r = zone.getBoundingClientRect();
                const cx = (e.clientX - r.left) / r.width - 0.5;
                const cy = (e.clientY - r.top) / r.height - 0.5;
                zone.querySelectorAll('[data-depth]').forEach((layer) => {
                    const d = parseFloat(layer.dataset.depth) || 10;
                    layer.style.transform = `translate(${cx * d}px, ${cy * d}px)`;
                });
            });
        });
    }

    /* Tabs */
    function initTabs() {
        document.querySelectorAll('.tabs').forEach((tabs) => {
            const btns = tabs.querySelectorAll('.tab-btn');
            const panels = tabs.querySelectorAll('.tab-panel');
            btns.forEach((btn) => btn.addEventListener('click', () => {
                btns.forEach((b) => b.classList.remove('is-active'));
                panels.forEach((p) => p.classList.remove('is-active'));
                btn.classList.add('is-active');
                const panel = tabs.querySelector('#' + btn.dataset.tab);
                if (panel) panel.classList.add('is-active');
            }));
        });
    }

    /* Before/After slider */
    function initBeforeAfter() {
        document.querySelectorAll('.ba-slider').forEach((slider) => {
            const after = slider.querySelector('.ba-after');
            const handle = slider.querySelector('.ba-handle');
            if (!after || !handle) return;
            let dragging = false;
            const move = (clientX) => {
                const r = slider.getBoundingClientRect();
                let pct = ((clientX - r.left) / r.width) * 100;
                pct = Math.max(0, Math.min(100, pct));
                after.style.width = pct + '%';
                handle.style.left = pct + '%';
            };
            handle.addEventListener('pointerdown', () => dragging = true);
            window.addEventListener('pointerup', () => dragging = false);
            window.addEventListener('pointermove', (e) => { if (dragging) move(e.clientX); });
            slider.addEventListener('click', (e) => move(e.clientX));
        });
    }

    /* Multi-step forms: [data-stepper] with .form-step + [data-next]/[data-prev] */
    function initSteppers() {
        document.querySelectorAll('[data-stepper]').forEach((form) => {
            const steps = Array.from(form.querySelectorAll('.form-step'));
            const dots = Array.from(form.querySelectorAll('.stepper .step'));
            let idx = 0;
            const show = (i) => {
                steps.forEach((s, n) => s.classList.toggle('is-active', n === i));
                dots.forEach((d, n) => {
                    d.classList.toggle('is-active', n === i);
                    d.classList.toggle('is-done', n < i);
                });
                idx = i;
            };
            const validStep = () => {
                const inputs = steps[idx].querySelectorAll('input, select, textarea');
                for (const el of inputs) { if (!el.checkValidity()) { el.reportValidity(); return false; } }
                return true;
            };
            form.querySelectorAll('[data-next]').forEach((b) => b.addEventListener('click', () => { if (validStep() && idx < steps.length - 1) show(idx + 1); }));
            form.querySelectorAll('[data-prev]').forEach((b) => b.addEventListener('click', () => { if (idx > 0) show(idx - 1); }));
            if (steps.length) show(0);
        });
    }

    /* Password strength + show/hide */
    function initPassword() {
        document.querySelectorAll('[data-password-strength]').forEach((input) => {
            const meter = document.querySelector(input.dataset.passwordStrength);
            input.addEventListener('input', () => {
                const v = input.value;
                let score = 0;
                if (v.length >= 8) score++;
                if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
                if (/\d/.test(v)) score++;
                if (/[^A-Za-z0-9]/.test(v)) score++;
                if (meter) meter.className = 'pw-strength ' + (score <= 1 ? 'weak' : score <= 3 ? 'medium' : 'strong');
            });
        });
        document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.querySelector(btn.dataset.togglePassword);
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                // Swap the Lucide glyph (never textContent — that destroys the SVG).
                btn.innerHTML = show ? '<i data-lucide="eye-off"></i>' : '<i data-lucide="eye"></i>';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                if (window.PWFdrawIcons) { window.PWFdrawIcons(); }
                else if (window.lucide) { try { window.lucide.createIcons(); } catch (e) {} }
            });
        });
    }

    /* OTP inputs — auto-advance & paste */
    function initOtp() {
        document.querySelectorAll('.otp-inputs').forEach((wrap) => {
            const inputs = Array.from(wrap.querySelectorAll('input'));
            inputs.forEach((input, i) => {
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/\D/g, '').slice(0, 1);
                    if (input.value && inputs[i + 1]) inputs[i + 1].focus();
                    const hidden = wrap.parentElement.querySelector('[data-otp-value]');
                    if (hidden) hidden.value = inputs.map((x) => x.value).join('');
                });
                input.addEventListener('keydown', (e) => { if (e.key === 'Backspace' && !input.value && inputs[i - 1]) inputs[i - 1].focus(); });
            });
            wrap.addEventListener('paste', (e) => {
                const data = (e.clipboardData.getData('text') || '').replace(/\D/g, '');
                inputs.forEach((x, n) => x.value = data[n] || '');
                const hidden = wrap.parentElement.querySelector('[data-otp-value]');
                if (hidden) hidden.value = data.slice(0, inputs.length);
                e.preventDefault();
            });
        });
    }

    /* Toast helper (window.toast('success','Saved')) */
    window.toast = function (type, message) {
        let stack = document.querySelector('.toast-stack');
        if (!stack) { stack = document.createElement('div'); stack.className = 'toast-stack'; document.body.appendChild(stack); }
        const t = document.createElement('div');
        t.className = 'toast ' + type;
        t.innerHTML = '<span>' + (type === 'success' ? '✅' : type === 'error' ? '⚠️' : 'ℹ️') + '</span><span>' + message + '</span>';
        stack.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 4000);
    };

    document.addEventListener('DOMContentLoaded', () => {
        initTilt(); initRipple(); initMarquee(); initMouseParallax();
        initTabs(); initBeforeAfter(); initSteppers(); initPassword(); initOtp();
    });
})();
