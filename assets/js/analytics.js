/* =============================================================================
   Visitor Analytics dashboard app (vanilla ES6 + Chart.js).
   Fetches real data from admin/analytics-data.php and renders KPIs, charts,
   real-time feed, insights, bar-lists, and CSV/Excel/PDF/PNG exports.
   Runs only on admin/analytics.php (guarded by #anDash).
   ============================================================================= */
(function () {
    'use strict';
    var dash = document.getElementById('anDash');
    if (!dash || typeof window.__AN_CFG__ === 'undefined') return;

    var CFG = window.__AN_CFG__;
    var PALETTE = ['#063566', '#084881', '#E67B1D', '#063566', '#084881', '#e11d48', '#0891b2', '#f59e0b', '#15803d', '#64748b'];
    var CHARTS = {};
    var LAST = null;
    var STATE = { range: '30d', start: '', end: '', device: '', source: '', country: '', state: '', city: '' };
    var rtTimer = null, liveTimer = null, live = false;

    /* ------------------------------------------------------------ helpers */
    function $(id) { return document.getElementById(id); }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function ic(n) { return '<i data-lucide="' + n + '"></i>'; }
    function drawIcons() { if (window.PWFdrawIcons) window.PWFdrawIcons(); }
    function setText(id, v) { var e = $(id); if (e) e.textContent = v; }
    function nf(n) { return new Intl.NumberFormat().format(Math.round(n)); }
    function fmtDuration(s) { s = Math.round(s); var m = Math.floor(s / 60); return m + ':' + String(s % 60).padStart(2, '0'); }
    var FMT = {
        number: nf,
        decimal: function (n) { return (Math.round(n * 100) / 100).toString(); },
        percent: function (n) { return (Math.round(n * 10) / 10) + '%'; },
        duration: fmtDuration,
        currency: function (n) { return CFG.currency + nf(n); }
    };
    function themeColors() {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return { grid: dark ? 'rgba(148,163,184,.14)' : 'rgba(148,163,184,.2)', tick: dark ? '#94a3b8' : '#64748b', ink: dark ? '#e2e8f0' : '#0f172a' };
    }
    function toastErr() { if (window.Swal) Swal.fire({ icon: 'error', title: 'Could not load analytics', timer: 1800, showConfirmButton: false }); }

    /* ------------------------------------------------------------ counters */
    function animate(el, to, fmt) {
        var from = 0, dur = 700, t0 = performance.now();
        function step(now) {
            var p = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - p, 3);
            el.textContent = fmt(from + (to - from) * e);
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    /* ------------------------------------------------------------ chart utils */
    function destroy(id) { if (CHARTS[id]) { CHARTS[id].destroy(); delete CHARTS[id]; } }
    function cctx(id) { var el = $(id); return el ? el.getContext('2d') : null; }
    function bodyOf(id) { var cv = $(id); return cv ? cv.closest('.an-panel-body') : null; }
    function showEmpty(id, msg) {
        var b = bodyOf(id); if (!b) return; var cv = $(id); if (cv) cv.style.display = 'none';
        var e = b.querySelector('.an-panel-empty');
        if (!e) { e = document.createElement('div'); e.className = 'an-panel-empty'; b.appendChild(e); }
        e.innerHTML = ic('inbox') + '<span>' + esc(msg || 'No data for this range') + '</span>'; drawIcons();
    }
    function clearEmpty(id) { var b = bodyOf(id); if (!b) return; var cv = $(id); if (cv) cv.style.display = ''; var e = b.querySelector('.an-panel-empty'); if (e) e.remove(); }
    function hasData(arr) { return arr && arr.length && arr.some(function (v) { return v > 0; }); }

    function buildTrend(t) {
        destroy('anTrend'); clearEmpty('anTrend'); var c = cctx('anTrend'); if (!c) return;
        if (!hasData(t.views)) { showEmpty('anTrend'); return; }
        var tc = themeColors();
        var g = c.createLinearGradient(0, 0, 0, 280); g.addColorStop(0, 'rgba(6,53,102,.28)'); g.addColorStop(1, 'rgba(6,53,102,0)');
        var g2 = c.createLinearGradient(0, 0, 0, 280); g2.addColorStop(0, 'rgba(8,72,129,.16)'); g2.addColorStop(1, 'rgba(8,72,129,0)');
        CHARTS.anTrend = new Chart(c, {
            type: 'line',
            data: { labels: t.labels, datasets: [
                { label: 'Page Views', data: t.views, borderColor: '#063566', backgroundColor: g, fill: true, tension: .35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5 },
                { label: 'Visitors', data: t.visitors, borderColor: '#084881', backgroundColor: g2, fill: true, tension: .35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, borderDash: [5, 4] }
            ] },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: tc.ink } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0, color: tc.tick }, grid: { color: tc.grid } }, x: { ticks: { color: tc.tick, maxTicksLimit: 12, autoSkip: true }, grid: { display: false } } }
            }
        });
    }
    function buildDoughnut(id, labels, data) {
        destroy(id); clearEmpty(id); var c = cctx(id); if (!c) return;
        if (!hasData(data)) { showEmpty(id); return; }
        var tc = themeColors();
        CHARTS[id] = new Chart(c, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: data, backgroundColor: PALETTE, borderWidth: 0, hoverOffset: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: tc.ink, font: { size: 11 } } } } }
        });
    }
    function buildBar(id, labels, data, horizontal) {
        destroy(id); clearEmpty(id); var c = cctx(id); if (!c) return;
        if (!hasData(data)) { showEmpty(id); return; }
        var tc = themeColors();
        var colors = data.map(function (_, i) { return PALETTE[i % PALETTE.length]; });
        CHARTS[id] = new Chart(c, {
            type: 'bar',
            data: { labels: labels, datasets: [{ data: data, backgroundColor: horizontal ? colors : '#063566', borderRadius: 6, maxBarThickness: 30 }] },
            options: {
                indexAxis: horizontal ? 'y' : 'x', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { ticks: { color: tc.tick, precision: 0, autoSkip: true, maxTicksLimit: 24 }, grid: { display: !horizontal ? false : true, color: tc.grid } }, y: { beginAtZero: true, ticks: { color: tc.tick, precision: 0 }, grid: { color: tc.grid, display: horizontal ? false : true } } }
            }
        });
    }
    function buildSpark(data) {
        destroy('anSpark'); var c = cctx('anSpark'); if (!c) return;
        var g = c.createLinearGradient(0, 0, 0, 40); g.addColorStop(0, 'rgba(6,53,102,.4)'); g.addColorStop(1, 'rgba(6,53,102,0)');
        CHARTS.anSpark = new Chart(c, {
            type: 'line',
            data: { labels: data.map(function (_, i) { return i; }), datasets: [{ data: data, borderColor: '#063566', backgroundColor: g, fill: true, tension: .4, borderWidth: 1.5, pointRadius: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } } }
        });
    }

    /* ------------------------------------------------------------ renderers */
    function renderKpis(s) {
        var kpis = s.kpis, prev = s.prev || {};
        document.querySelectorAll('#anKpis .an-kpi').forEach(function (card) {
            var key = card.dataset.kpi, fmt = FMT[card.dataset.format] || nf, val = kpis[key] || 0;
            var el = card.querySelector('.an-kpi-val');
            el.classList.remove('skeleton', 'an-sk-line'); el.style.width = '';
            animate(el, val, fmt);
            var d = card.querySelector('.an-kpi-delta');
            if ((key === 'views' || key === 'visitors') && prev[key] !== undefined) {
                var p = prev[key];
                if (p > 0) { var pc = Math.round((val - p) / p * 100); d.hidden = false; d.className = 'an-kpi-delta ' + (pc > 0 ? 'up' : pc < 0 ? 'down' : 'flat'); d.innerHTML = ic(pc > 0 ? 'arrow-up' : pc < 0 ? 'arrow-down' : 'minus') + Math.abs(pc) + '%'; }
                else if (val > 0) { d.hidden = false; d.className = 'an-kpi-delta up'; d.innerHTML = ic('sparkles') + 'new'; }
                else { d.hidden = true; }
            } else { d.hidden = true; }
        });
    }
    function renderList(id, items) {
        var el = $(id); if (!el) return;
        if (!items || !items.length) { el.innerHTML = '<div class="an-feed-empty">No data for this range</div>'; return; }
        var max = Math.max.apply(null, items.map(function (i) { return i.val || 0; }).concat([1]));
        el.innerHTML = items.map(function (it) {
            var w = Math.max(3, Math.round((it.val || 0) / max * 100));
            return '<div class="an-list-item"><div class="an-list-row"><span class="an-list-label" title="' + esc(it.label) + '">' + esc(it.label) + '</span><span class="an-list-val">' + nf(it.val || 0) + '</span></div><div class="an-list-bar"><span style="width:' + w + '%"></span></div></div>';
        }).join('');
    }
    function deviceIcon(d) { return d === 'mobile' ? 'smartphone' : d === 'tablet' ? 'tablet' : d === 'desktop' ? 'monitor' : 'globe'; }
    function renderRealtime(rt) {
        setText('anActiveNow', rt.active_now); setText('anViews5m', rt.views_5m);
        buildSpark(rt.spark || []);
        var feed = $('anFeed'); if (!feed) return;
        if (!rt.recent || !rt.recent.length) { feed.innerHTML = '<div class="an-feed-empty">No recent activity</div>'; return; }
        feed.innerHTML = rt.recent.map(function (r) {
            var loc = [r.city, r.country].filter(Boolean).join(', ') || r.source || 'Direct';
            return '<div class="an-feed-item"><div class="an-feed-ico">' + ic(deviceIcon(r.device)) + '</div><div class="an-feed-main"><div class="an-feed-url" title="' + esc(r.url) + '">' + esc(r.url) + '</div><div class="an-feed-meta">' + esc(r.browser) + ' &middot; ' + esc(loc) + '</div></div><div class="an-feed-ago">' + esc(r.ago) + '</div></div>';
        }).join('');
        drawIcons();
    }
    function renderInsights(items) {
        var el = $('anInsights'); if (!el) return;
        if (!items || !items.length) { el.innerHTML = '<div class="an-feed-empty">Not enough data for insights yet.</div>'; return; }
        el.innerHTML = items.map(function (i) {
            return '<div class="an-insight ' + esc(i.tone) + '"><div class="an-insight-ico">' + ic(i.icon) + '</div><div><div class="an-insight-title">' + esc(i.title) + '</div><div class="an-insight-text">' + esc(i.text) + '</div></div></div>';
        }).join('');
        drawIcons();
    }
    function fillSelect(id, allLabel, items, cur) {
        var sel = $(id); if (!sel) return;
        var found = false, opts = '<option value="">' + allLabel + '</option>';
        items.forEach(function (it) { if (it.value) { opts += '<option value="' + esc(it.value) + '">' + esc(it.text) + '</option>'; if (it.value === cur) found = true; } });
        if (cur && !found) opts += '<option value="' + esc(cur) + '">' + esc(cur) + '</option>';
        sel.innerHTML = opts; sel.value = cur || '';
    }
    function populateFacets(facets) {
        if (!facets) return;
        fillSelect('anCountry', 'All countries', (facets.countries || []).map(function (c) { return { value: c.code, text: c.name }; }), STATE.country);
        fillSelect('anState', 'All states', (facets.states || []).map(function (s) { return { value: s, text: s }; }), STATE.state);
        fillSelect('anCity', 'All cities', (facets.cities || []).map(function (c) { return { value: c, text: c }; }), STATE.city);
    }

    function apply(s, insights, rt, facets) {
        LAST = { summary: s, insights: insights, realtime: rt, facets: facets };
        setText('anRangeLabel', s.range.label);
        renderKpis(s);
        buildTrend(s.trend);
        buildDoughnut('anSources', s.sources.labels, s.sources.data);
        buildDoughnut('anDevices', s.devices.labels, s.devices.data);
        buildBar('anBrowsers', s.browsers.labels, s.browsers.data, true);
        buildBar('anOS', s.os.labels, s.os.data, true);
        buildBar('anHours', Array.from({ length: 24 }, function (_, i) { return String(i); }), s.hours, false);
        buildBar('anWeekdays', ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], s.weekdays, false);
        renderList('anCountries', (s.countries || []).map(function (c) { return { label: c.name, val: c.views }; }));
        renderList('anStates', (s.states || []).map(function (c) { return { label: c.name, val: c.views }; }));
        renderList('anCities', (s.cities || []).map(function (c) { return { label: c.name, val: c.views }; }));
        renderList('anTopPages', (s.top_pages || []).map(function (p) { return { label: p.url, val: p.views }; }));
        renderList('anReferrers', (s.referrers || []).map(function (p) { return { label: p.url, val: p.views }; }));
        renderList('anLanding', (s.landing || []).map(function (p) { return { label: p.url, val: p.views }; }));
        renderList('anExit', (s.exit || []).map(function (p) { return { label: p.url, val: p.views }; }));
        renderInsights(insights);
        renderRealtime(rt);
        populateFacets(facets || { countries: s.countries || [], states: [], cities: [] });
        drawIcons();
    }

    /* ------------------------------------------------------------ fetch */
    function query() {
        var p = new URLSearchParams(); p.set('section', 'summary'); p.set('range', STATE.range);
        if (STATE.range === 'custom') { p.set('start', STATE.start); p.set('end', STATE.end); }
        if (STATE.device) p.set('device', STATE.device);
        if (STATE.source) p.set('source', STATE.source);
        if (STATE.country) p.set('country', STATE.country);
        if (STATE.state) p.set('state', STATE.state);
        if (STATE.city) p.set('city', STATE.city);
        return p.toString();
    }
    function fetchSummary() {
        fetch(CFG.endpoint + '?' + query(), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (!d.ok) { toastErr(); return; } apply(d.summary, d.insights, d.realtime, d.facets); syncUrl(); })
            .catch(toastErr);
    }
    function fetchRealtime() {
        fetch(CFG.endpoint + '?section=realtime', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.ok) renderRealtime(d.realtime); })
            .catch(function () {});
    }
    function syncUrl() {
        var p = new URLSearchParams(); p.set('range', STATE.range);
        if (STATE.range === 'custom') { p.set('start', STATE.start); p.set('end', STATE.end); }
        if (STATE.device) p.set('device', STATE.device);
        if (STATE.source) p.set('source', STATE.source);
        if (STATE.country) p.set('country', STATE.country);
        if (STATE.state) p.set('state', STATE.state);
        if (STATE.city) p.set('city', STATE.city);
        history.replaceState(null, '', location.pathname + '?' + p.toString());
    }

    /* ------------------------------------------------------------ filters */
    function setPresetActive() {
        document.querySelectorAll('#anPresets .an-preset').forEach(function (b) { b.classList.toggle('is-active', b.dataset.range === STATE.range); });
        $('anDateRange').classList.toggle('is-on', STATE.range === 'custom');
    }
    function initFilters() {
        document.querySelectorAll('#anPresets .an-preset').forEach(function (b) {
            b.addEventListener('click', function () {
                STATE.range = b.dataset.range; setPresetActive();
                if (STATE.range === 'custom') { if (STATE.start && STATE.end) fetchSummary(); } else { fetchSummary(); }
            });
        });
        $('anApplyRange').addEventListener('click', function () {
            STATE.start = $('anStart').value; STATE.end = $('anEnd').value;
            if (STATE.start && STATE.end) { STATE.range = 'custom'; setPresetActive(); fetchSummary(); }
        });
        $('anDevice').addEventListener('change', function () { STATE.device = this.value; fetchSummary(); });
        $('anSource').addEventListener('change', function () { STATE.source = this.value; fetchSummary(); });
        $('anCountry').addEventListener('change', function () { STATE.country = this.value; STATE.state = ''; STATE.city = ''; fetchSummary(); });
        $('anState').addEventListener('change', function () { STATE.state = this.value; STATE.city = ''; fetchSummary(); });
        $('anCity').addEventListener('change', function () { STATE.city = this.value; fetchSummary(); });
    }

    /* ------------------------------------------------------------ toolbar */
    function initToolbar() {
        $('anRefresh').addEventListener('click', function () { fetchSummary(); });
        $('anLiveToggle').addEventListener('click', function () {
            live = !live; this.classList.toggle('is-on', live);
            if (live) { liveTimer = setInterval(fetchSummary, 45000); } else { clearInterval(liveTimer); }
        });
        $('anFullscreen').addEventListener('click', function () {
            if (!document.fullscreenElement) { if (dash.requestFullscreen) dash.requestFullscreen(); }
            else if (document.exitFullscreen) document.exitFullscreen();
        });
        var exp = $('anExport');
        $('anExportBtn').addEventListener('click', function (e) { e.stopPropagation(); exp.classList.toggle('is-open'); });
        document.addEventListener('click', function (e) { if (!e.target.closest('#anExport')) exp.classList.remove('is-open'); });
        exp.querySelectorAll('[data-export]').forEach(function (b) {
            b.addEventListener('click', function () { exp.classList.remove('is-open'); doExport(b.dataset.export); });
        });
    }

    /* ------------------------------------------------------------ export */
    function download(name, blob) { var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000); }
    function stamp() { return LAST ? LAST.summary.range.start : 'export'; }
    function doExport(kind) {
        if (!LAST) return;
        if (kind === 'csv') exportCSV();
        else if (kind === 'excel') exportExcel();
        else if (kind === 'pdf') exportPDF();
        else if (kind === 'png') exportPNG();
    }
    function exportCSV() {
        var s = LAST.summary, rows = [['Metric', 'Value']];
        Object.keys(s.kpis).forEach(function (k) { rows.push([k, s.kpis[k]]); });
        rows.push([], ['Traffic Source', 'Views']); s.sources.labels.forEach(function (l, i) { rows.push([l, s.sources.data[i]]); });
        rows.push([], ['Top Page', 'Views']); s.top_pages.forEach(function (p) { rows.push([p.url, p.views]); });
        var csv = rows.map(function (r) { return r.map(function (v) { v = (v == null ? '' : String(v)); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }).join(','); }).join('\n');
        download('analytics-' + stamp() + '.csv', new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    }
    function exportExcel() {
        if (!window.XLSX) { toastErr(); return; }
        var s = LAST.summary, wb = XLSX.utils.book_new();
        var kp = [['Metric', 'Value']]; Object.keys(s.kpis).forEach(function (k) { kp.push([k, s.kpis[k]]); });
        XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(kp), 'KPIs');
        var tr = [['Period', 'Views', 'Visitors']]; s.trend.labels.forEach(function (l, i) { tr.push([l, s.trend.views[i], s.trend.visitors[i]]); });
        XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(tr), 'Trend');
        var sr = [['Source', 'Views']]; s.sources.labels.forEach(function (l, i) { sr.push([l, s.sources.data[i]]); });
        XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(sr), 'Sources');
        var tp = [['Page', 'Views']]; s.top_pages.forEach(function (p) { tp.push([p.url, p.views]); });
        XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(tp), 'Top Pages');
        var cn = [['Country', 'Views']]; (s.countries || []).forEach(function (c) { cn.push([c.name, c.views]); });
        XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(cn), 'Countries');
        XLSX.writeFile(wb, 'analytics-' + stamp() + '.xlsx');
    }
    function exportPNG() { if (!CHARTS.anTrend) { toastErr(); return; } var a = document.createElement('a'); a.href = CHARTS.anTrend.toBase64Image('image/png', 1); a.download = 'trend-' + stamp() + '.png'; a.click(); }
    function exportPDF() {
        if (!window.jspdf) { toastErr(); return; }
        var jsPDF = window.jspdf.jsPDF, doc = new jsPDF('p', 'pt', 'a4'), W = doc.internal.pageSize.getWidth(), y = 44, s = LAST.summary, k = s.kpis;
        doc.setFontSize(18); doc.setTextColor(20); doc.text(CFG.site + ' — Visitor Analytics', 40, y); y += 20;
        doc.setFontSize(10); doc.setTextColor(120); doc.text(s.range.label + '  (' + s.range.start + ' to ' + s.range.end + ')', 40, y); y += 24;
        var items = [['Unique Visitors', nf(k.visitors)], ['Page Views', nf(k.views)], ['Sessions', nf(k.sessions)], ['Bounce Rate', k.bounce_rate + '%'], ['Avg Session', fmtDuration(k.avg_session)], ['Pages / Session', k.pages_per_session], ['Leads', nf(k.leads)], ['Revenue', CFG.currency + nf(k.revenue)], ['Conversion', k.conversion_rate + '%']];
        items.forEach(function (it, i) { var col = i % 3, row = Math.floor(i / 3), x = 40 + col * ((W - 80) / 3); doc.setFontSize(13); doc.setTextColor(20); doc.text(String(it[1]), x, y + row * 42 + 14); doc.setFontSize(8); doc.setTextColor(120); doc.text(String(it[0]), x, y + row * 42 + 27); });
        y += Math.ceil(items.length / 3) * 42 + 14;
        ['anTrend', 'anSources', 'anDevices'].forEach(function (id) {
            if (!CHARTS[id]) return;
            try { var img = CHARTS[id].toBase64Image('image/png', 1); if (y > 660) { doc.addPage(); y = 44; } var h = id === 'anTrend' ? 150 : 150; doc.addImage(img, 'PNG', 40, y, W - 80, h); y += h + 16; } catch (e) {}
        });
        doc.save('analytics-' + stamp() + '.pdf');
    }

    /* ------------------------------------------------------------ drag reorder */
    function initDrag() {
        var grid = $('anChartGrid'); if (!grid) return; var dragEl = null; var KEY = 'pwf-an-panel-order';
        try { var ord = JSON.parse(localStorage.getItem(KEY) || 'null'); if (ord) ord.forEach(function (pid) { var el = grid.querySelector('[data-panel="' + pid + '"]'); if (el) grid.appendChild(el); }); } catch (e) {}
        function save() { localStorage.setItem(KEY, JSON.stringify([].slice.call(grid.querySelectorAll('.an-panel')).map(function (p) { return p.dataset.panel; }))); }
        grid.querySelectorAll('.an-panel').forEach(function (p) {
            p.addEventListener('dragstart', function () { dragEl = p; p.classList.add('dragging'); });
            p.addEventListener('dragend', function () { p.classList.remove('dragging'); grid.querySelectorAll('.drop-target').forEach(function (x) { x.classList.remove('drop-target'); }); save(); });
            p.addEventListener('dragover', function (e) { e.preventDefault(); if (p !== dragEl) p.classList.add('drop-target'); });
            p.addEventListener('dragleave', function () { p.classList.remove('drop-target'); });
            p.addEventListener('drop', function (e) { e.preventDefault(); p.classList.remove('drop-target'); if (dragEl && p !== dragEl) { var r = p.getBoundingClientRect(); grid.insertBefore(dragEl, (e.clientX - r.left) > r.width / 2 ? p.nextSibling : p); } });
        });
    }

    /* ------------------------------------------------------------ init */
    function initState() {
        var p = new URLSearchParams(location.search);
        ['range', 'start', 'end', 'device', 'source', 'country', 'state', 'city'].forEach(function (k) { if (p.get(k)) STATE[k] = p.get(k); });
        if (STATE.start) { var a = $('anStart'); if (a) a.value = STATE.start; }
        if (STATE.end) { var b = $('anEnd'); if (b) b.value = STATE.end; }
        if (STATE.device) $('anDevice').value = STATE.device;
        if (STATE.source) $('anSource').value = STATE.source;
    }

    if (typeof Chart !== 'undefined') { Chart.defaults.font.family = "'Roboto','Plus Jakarta Sans',sans-serif"; Chart.defaults.font.size = 11; }
    initState(); setPresetActive(); initFilters(); initToolbar(); initDrag();
    fetchSummary();
    rtTimer = setInterval(fetchRealtime, 25000);
    new MutationObserver(function () { if (LAST) apply(LAST.summary, LAST.insights, LAST.realtime, LAST.facets); }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
