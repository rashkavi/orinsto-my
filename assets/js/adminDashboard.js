(function () {
    'use strict';

    var root = document.getElementById('adx');
    if (!root) return;

    var API = root.dataset.api;
    var TODAY = root.dataset.today;
    var view = document.getElementById('adxView');

    /* ---------------------------------------------------------- helpers */
    var pad = function (n) { return String(n).padStart(2, '0'); };
    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var num = function (n) { return Number(n || 0).toLocaleString('en-US'); };
    var money = function (n) { return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var isoOf = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
    var parse = function (iso) { return new Date(iso + 'T12:00:00'); };
    var fmtDate = function (iso) { return parse(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); };
    var fmtShort = function (iso) { return parse(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }); };
    var fmtTime = function (t) {
        if (!t) return '';
        var p = t.split(':'), h = parseInt(p[0], 10);
        return (h % 12 || 12) + ':' + p[1] + ' ' + (h >= 12 ? 'PM' : 'AM');
    };
    var safeLink = function (u) { return /^https?:\/\//i.test(u || '') ? u : ''; };

    /* ---------------------------------------------------------- state */
    var qs = new URLSearchParams(window.location.search);
    var ui = {
        from: qs.get('start_date') || '',
        to: qs.get('end_date') || '',
        preset: '',
        programme: parseInt(qs.get('programme') || '0', 10) || 0,
        search: qs.get('search') || '',
        lmore: false,
        calTab: 'events'
    };

    function presetRange(key) {
        var t = parse(TODAY), y = t.getFullYear(), m = t.getMonth();
        switch (key) {
            case 'today': return [TODAY, TODAY];
            case 'week': return [isoOf(new Date(y, m, t.getDate() - 6)), TODAY];
            case 'month': return [isoOf(new Date(y, m, 1)), TODAY];
            case 'last': return [isoOf(new Date(y, m - 1, 1)), isoOf(new Date(y, m, 0))];
            case '3m': return [isoOf(new Date(y, m - 2, 1)), TODAY];
            case '6m': return [isoOf(new Date(y, m - 5, 1)), TODAY];
        }
        return null;
    }
    function detectPreset() {
        ui.preset = '';
        ['today', 'week', 'month', 'last', '3m', '6m'].forEach(function (k) {
            var r = presetRange(k);
            if (r && r[0] === ui.from && r[1] === ui.to) ui.preset = k;
        });
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(ui.from) || !/^\d{4}-\d{2}-\d{2}$/.test(ui.to)) {
        var d0 = presetRange('month');
        ui.from = d0[0]; ui.to = d0[1];
    }
    detectPreset();

    /* ---------------------------------------------------------- filter bar bindings */
    var el = {
        from: document.getElementById('adxFrom'),
        to: document.getElementById('adxTo'),
        dFrom: document.getElementById('adxDFrom'),
        dTo: document.getElementById('adxDTo'),
        box: document.getElementById('adxDateBox'),
        search: document.getElementById('adxSearch'),
        prog: document.getElementById('adxProg'),
        err: document.getElementById('adxRangeErr'),
        updated: document.getElementById('adxUpdated')
    };

    function syncFilterBar() {
        el.from.textContent = fmtDate(ui.from);
        el.to.textContent = fmtDate(ui.to);
        el.dFrom.value = ui.from;
        el.dTo.value = ui.to;
        el.search.value = ui.search;
        root.querySelectorAll('.presets button').forEach(function (b) {
            b.setAttribute('aria-pressed', String(b.dataset.v === ui.preset));
        });
    }

    function pushUrl() {
        var p = new URLSearchParams();
        p.set('start_date', ui.from);
        p.set('end_date', ui.to);
        if (ui.programme) p.set('programme', ui.programme);
        if (ui.search) p.set('search', ui.search);
        history.replaceState(null, '', window.location.pathname + '?' + p.toString());
    }

    /* ---------------------------------------------------------- shared markup pieces */
    var TONE = { g: 'positive', o: 'neutral', r: 'negative' };
    var ACTION_TONE = { warn: 'status-orange', info: 'status-blue', bad: 'status-red' };

   function emptyState(title, text) {
    return `
        <div class="col-12 px-4 py-3 component-theme">
            <div class="row align-items-center justify-content-center">
                <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                    <div class="no-data-state">
                        <div class="no-data-icon-wrap">
                            <iconify-icon icon="ph:empty-bold"></iconify-icon>
                        </div>
                        <p class="no-data-text">${esc(title)}</p>
                        <p class="no-data-description">${esc(text)}</p>
                    </div>
                </div>
            </div>
        </div>
    `;
}
    function head(title, href, label) {
        return '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><span class="sub-topic">' + esc(title) + '</span>' +
            (href ? '<a class="text-14 dark-theme text-decoration-none" href="' + esc(href) + '">' + esc(label || 'View all') + '</a>' : '') + '</div>';
    }

    function panel(colCls, inner) {
        return '<div class="' + colCls + '"><section class="component-theme p-4 h-100 d-flex flex-column gap-3">' + inner + '</section></div>';
    }

    function pill(cls, text) {
        return '<span class="adx-pill ' + cls + '">' + esc(text) + '</span>';
    }

    /* ---------------------------------------------------------- summary cards (stats-mini-card) */
    function bars(vals) {
        var max = 1;
        vals.forEach(function (v) { if (v > max) max = v; });
        return '<div class="stats-mini-chart">' + vals.map(function (v, i) {
            var h = Math.max(6, Math.round(v / max * 42));
            return '<span class="stats-mini-bar' + (i === vals.length - 1 ? ' active' : '') + '" style="height:' + h + 'px"></span>';
        }).join('') + '</div>';
    }

    function changeHtml(c) {
        if (c.change === null) return '<span class="stats-mini-change positive">New</span>';
        if (c.change === 0) return '<span class="stats-mini-change neutral">0%</span>';
        var good = c.good_up ? c.change > 0 : c.change < 0;
        return '<span class="stats-mini-change ' + (good ? 'positive' : 'negative') + '">' + (c.change > 0 ? '+' : '') + c.change + '%</span>';
    }

    function card(c) {
        var value = c.format === 'money' ? '<span class="text-12 sub-text fw-medium">LKR </span>' + money(c.value) : num(c.value);
        var meta;
        if (c.share !== undefined) {
            meta = '<span class="stats-mini-change ' + (TONE[c.share_tone] || 'neutral') + '">' + c.share + '%</span><span class="stats-mini-period">' + esc(c.note || '') + '</span>';
        } else if (c.change !== undefined) {
            meta = changeHtml(c) + '<span class="stats-mini-period">vs previous period</span>';
        } else {
            meta = '<span class="stats-mini-period">' + esc(c.note || '') + '</span>';
        }
        return '<div class="col-12 col-md-6 col-lg-4 col-xl-3"><div class="stats-mini-card"' + (c.href ? ' data-href="' + esc(c.href) + '"' : '') + '>' +
            '<div class="stats-mini-top"><div class="stats-mini-left">' +
            '<div class="stats-mini-icon"><iconify-icon icon="' + esc(c.icon) + '" width="16" height="16"></iconify-icon></div>' +
            '<span class="stats-mini-title">' + esc(c.title) + '</span></div>' +
            '<button type="button" class="stats-mini-menu" aria-label="More options for ' + esc(c.title) + '"><iconify-icon icon="mdi:dots-horizontal" width="16" height="16"></iconify-icon></button></div>' +
            '<div class="stats-mini-body"><div class="stats-mini-content"><div class="stats-mini-value">' + value + '</div>' +
            '<div class="stats-mini-meta">' + meta + '</div></div>' + (c.spark ? bars(c.spark) : '') + '</div></div></div>';
    }

    /* ---------------------------------------------------------- sections */
    function statusPill(st) {
        if (st === 'Ongoing') return 'status-green';
        if (st === 'Upcoming') return 'status-blue';
        if (st === 'Pending Confirmation') return 'status-orange';
        return 'status-gray';
    }

    function liveSection(list) {
        var h = head('Live Classes (Today)', 'lecture-schedule');
        if (!list.length) return panel('col-12 col-xl-6', h + emptyState('No online classes today', 'Online lectures scheduled for today appear here.'));

        var m = list[0], link = safeLink(m.link), ongoing = m.status === 'Ongoing';
        var badge = ongoing ? '● Live' : (m.status === 'Upcoming' ? 'Starts ' + fmtTime(m.start) : 'Ended');
        var joinBtn = link
            ? '<a class="' + (ongoing ? 'custom-btn' : 'custom-outline-btn component-theme') + ' d-inline-flex align-items-center px-4 text-14 text-decoration-none" style="height:38px" href="' + esc(link) + '" target="_blank" rel="noopener noreferrer">' + (ongoing ? 'Join Class' : 'Open link') + '</a>'
            : '<span class="text-12 sub-text">No join link</span>';

        h += '<div class="lv"><div class="lv-main"><div class="lv-prev"><span class="lv-badge' + (ongoing ? '' : ' off') + '">' + esc(badge) + '</span>' +
            '<span class="lv-slide"><b>' + esc(m.subject) + '</b><span>' + esc(m.intake) + '</span></span>' +
            '<span class="lv-side"><span></span><span></span><span></span></span><span class="lv-lect">' + esc(m.initials) + '</span></div>' +
            '<div class="lv-info"><b class="text-dark-color">' + esc(m.subject) + ' – ' + esc(m.intake) + '</b>' +
            '<span class="text-13 sub-text">' + esc(m.lecturer) + ' · ' + fmtTime(m.start) + ' – ' + fmtTime(m.end) + '</span>' +
            '<div class="d-flex justify-content-between align-items-center gap-2 mt-1">' +
            (ongoing ? '<span class="livetxt"><span class="ldot"></span>LIVE NOW</span>' : '<span class="text-13 sub-text">' + esc(m.status) + '</span>') +
            joinBtn + '</div></div></div><div class="lv-list">';

        var others = list.slice(1, 4);
        h += others.length ? others.map(function (l) {
            return '<div class="lv-item bl-solid component-theme"><span class="lv-vi d-flex align-items-center justify-content-center status-blue"><iconify-icon icon="mdi:video-outline" width="20" height="20"></iconify-icon></span>' +
                '<span class="min-w-0"><b class="d-block text-14 text-dark-color">' + esc(l.intake) + ' ' + esc(l.subject) + '</b><span class="text-12 sub-text">' + fmtTime(l.start) + ' – ' + fmtTime(l.end) +
                (l.status === 'Ongoing' ? ' · <b class="text-dark-green">Live</b>' : (l.status === 'Upcoming' ? '' : ' · Ended')) + '</span></span></div>';
        }).join('') : '<p class="text-13 sub-text m-0">No other online classes today.</p>';
        return panel('col-12 col-xl-6', h + '</div></div>');
    }

    function actionSection(items) {
        var h = head('Management Action Centre');
        h += '<div class="d-flex flex-column gap-2">' + items.map(function (a) {
            var tone = ACTION_TONE[a.tone] || '';
            return '<a class="mac bl-solid component-theme" href="' + esc(a.href) + '">' +
                '<span class="mac-ic d-flex align-items-center justify-content-center ' + tone + '"><iconify-icon icon="' + esc(a.icon) + '" width="18" height="18"></iconify-icon></span>' +
                '<span>' + esc(a.label) + '</span><b class="cnt ' + (a.count ? tone : 'status-green') + '">' + num(a.count) + '</b></a>';
        }).join('') + '</div>';
        return panel('col-12 col-xl-6', h);
    }

    function batchSection(list) {
        var h = head('Course Batches', 'batch-activity');
        if (!list.length) return panel('col-12 col-xl-6', h + emptyState('No running batches', 'Batches with lectures in the last 30 days appear here.'));
        h += '<div class="adx-scroll"><table class="table table-hover align-middle mb-0"><thead><tr class="bg-table-header"><th class="bg-table-header">Batch</th><th class="bg-table-header">Programme</th><th class="bg-table-header">Progress</th><th class="bg-table-header">Students</th><th class="bg-table-header">Status</th></tr></thead><tbody>';
        h += list.map(function (b) {
            var cls = b.state === 'Running' ? 'status-green' : (b.state === 'New intake' ? 'status-blue' : (b.state === 'Ending soon' ? 'status-orange' : 'status-gray'));
            var stu = b.students === null ? '<span class="sub-text">–</span>' : num(b.active) + ' <span class="text-12 sub-text">/ ' + num(b.students) + '</span>';
            return '<tr><td class="fw-medium text-dark-color">' + esc(b.intake) + '</td><td>' + esc(b.programme) + '</td>' +
                '<td><div class="d-flex align-items-center gap-2"><div class="progress rounded-0" style="height:8px;width:90px;background:var(--table-header-color)"><div class="progress-bar" style="width:' + b.progress + '%;background:var(--dark-theme)"></div></div><span class="text-12 sub-text">' + b.progress + '%</span></div></td>' +
                '<td>' + stu + '</td><td>' + pill(cls, b.state) + '</td></tr>';
        }).join('');
        return panel('col-12 col-xl-6', h + '</tbody></table></div>');
    }

    function datesSection(data) {
        var list = ui.calTab === 'events' ? data.events : data.deadlines;
        var h = '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><span class="sub-topic">Important Dates</span>' +
            '<div class="d-flex align-items-center flex-wrap gap-3"><div class="seg" role="group" aria-label="Date type">' +
            '<button type="button" data-action="caltab" data-v="events" aria-pressed="' + (ui.calTab === 'events') + '">Events (' + data.events.length + ')</button>' +
            '<button type="button" data-action="caltab" data-v="deadlines" aria-pressed="' + (ui.calTab === 'deadlines') + '">Deadlines (' + data.deadlines.length + ')</button></div>' +
            '<a class="text-14 dark-theme text-decoration-none" href="event-calendar">View calendar</a></div></div>';
        if (!list.length) return panel('col-12 col-xl-6', h + emptyState('Nothing coming up', 'Upcoming ' + ui.calTab + ' appear here.'));
        h += '<div class="d-flex flex-column gap-2">' + list.map(function (c) {
            var d = parse(c.date);
            return '<div class="cal-row' + (c.highlight ? ' hl' : '') + '"><span class="dt ' + (ui.calTab === 'deadlines' ? 'status-purple' : 'status-blue') + '"><small>' +
                d.toLocaleDateString('en-GB', { month: 'short' }).toUpperCase() + '</small>' + pad(d.getDate()) + '</span>' +
                '<span><b class="ct text-14 text-dark-color">' + esc(c.title) + '</b>' + (ui.calTab === 'events' ? '<br><span class="text-12 sub-text">' + esc(c.time) + '</span>' : '') + '</span></div>';
        }).join('') + '</div>';
        return panel('col-12 col-xl-6', h);
    }

    function avatar(l) {
        if (l.profile) return '<img class="hwr-40" style="object-fit:cover" src="' + esc(l.profile) + '" alt="">';
        return '<div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium text-14 flex-shrink-0">' + esc(l.initials) + '</div>';
    }

    function lecturesSection(all, range) {
        var isToday = range.from === TODAY && range.to === TODAY;
        var shown = ui.lmore ? all : all.slice(0, 10);
        var title = isToday ? 'Ongoing Lectures - Today’s Details' : 'Lectures – ' + fmtShort(range.from) + ' to ' + fmtDate(range.to) + ' (' + all.length + ')';
        var h = '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><span class="sub-topic d-flex align-items-center gap-2"><iconify-icon class="dark-theme" icon="mdi:calendar-month-outline" width="22" height="22"></iconify-icon>' + esc(title) + '</span>' +
            (all.length > 10
                ? '<button type="button" class="table-btn table-btn-primary" data-action="lmore">' + (ui.lmore ? 'Show less' : 'View All (' + all.length + ')') + '</button>'
                : '<a class="text-14 dark-theme text-decoration-none" href="lecture-schedule">View all</a>') + '</div>';

        var th = ['#', 'Lecturer’s Name', 'Lecture Type', 'Intake / Batch', 'Subject', 'Date &amp; Time', 'Status'].map(function (t) { return '<th class="bg-table-header">' + t + '</th>'; }).join('');
        h += '<div class="adx-scroll"><table class="table table-hover align-middle mb-0 lectures"><thead><tr>' + th + '</tr></thead><tbody>';
        if (!shown.length) h += '<tr><td colspan="7">' + emptyState('No lectures found', 'No lectures scheduled for this date range.') + '</td></tr>';
        h += shown.map(function (l, i) {
            return '<tr><td>' + (i + 1) + '</td><td><div class="d-flex align-items-center gap-2">' + avatar(l) + '<span class="text-dark-color">' + esc(l.lecturer) + '</span></div></td>' +
                '<td>' + pill(l.type === 'Online' ? 'status-green' : 'status-blue', l.type) + '</td>' +
                '<td>' + esc(l.intake) + '</td><td>' + esc(l.subject) + '</td>' +
                '<td>' + fmtDate(l.date) + '<br><span class="text-12 sub-text">' + fmtTime(l.start) + ' - ' + fmtTime(l.end) + '</span></td>' +
                '<td>' + pill(statusPill(l.status), l.status) + '</td></tr>';
        }).join('');
        return '<section class="component-theme p-4 d-flex flex-column gap-3">' + h + '</tbody></table></div></section>';
    }

    var DATA = null;

    function render() {
        if (!DATA) return;
        var cards = DATA.student_cards.concat(DATA.finance_cards, DATA.system_cards);
        view.innerHTML =
            '<div class="row g-3">' + cards.map(card).join('') + '</div>' +
            '<div class="row g-4">' + liveSection(DATA.live) + actionSection(DATA.actions) + '</div>' +
            '<div class="row g-4">' + batchSection(DATA.batches) + datesSection(DATA) + '</div>' +
            lecturesSection(DATA.lectures, DATA.range);
    }

    /* ---------------------------------------------------------- data loading */
    var token = 0;

    function skeleton() {
        var cols = '';
        for (var i = 0; i < 12; i++) cols += '<div class="col-12 col-md-6 col-lg-4 col-xl-3"><div class="skel"></div></div>';
        view.innerHTML = '<div class="row g-3">' + cols + '</div><div class="skel" style="height:260px"></div>';
    }

    function load(opts) {
        opts = opts || {};
        var my = ++token;
        pushUrl();
        syncFilterBar();
        if (!DATA) skeleton(); else if (!opts.silent) root.classList.add('loading');

        var p = new URLSearchParams({ start_date: ui.from, end_date: ui.to, programme: ui.programme, search: ui.search });
        return fetch(API + '?' + p.toString(), { credentials: 'same-origin' })
            .then(function (r) {
                return r.text().then(function (t) {
                    var j;
                    try { j = JSON.parse(t); } catch (e) { throw new Error('Server returned an invalid response.'); }
                    if (!r.ok || !j.done) throw new Error(j.error || 'Failed to load dashboard data.');
                    return j;
                });
            })
            .then(function (j) {
                if (my !== token) return;
                DATA = j;
                fillProgrammes(j.programmes);
                render();
                el.updated.textContent = 'Updated ' + j.generated_at;
            })
            .catch(function (e) {
                if (my !== token) return;
                if (opts.silent && DATA) return; // keep the old view on background refresh failures
                view.innerHTML = '<div class="err"><div><b class="text-dark-red">Could not load the dashboard.</b><div class="text-13 sub-text">' + esc(e.message) +
                    '</div></div><button type="button" class="custom-btn px-4" data-action="retry">Try again</button></div>';
            })
            .then(function () { if (my === token) root.classList.remove('loading'); });
    }

    function fillProgrammes(list) {
        if (el.prog.dataset.filled) return;
        el.prog.dataset.filled = '1';
        el.prog.innerHTML = '<option value="0">All Programmes</option>' + (list || []).map(function (p) {
            return '<option value="' + p.id + '"' + (p.id === ui.programme ? ' selected' : '') + '>' + esc(p.name) + '</option>';
        }).join('');
    }

    /* ---------------------------------------------------------- events */
    function applyRange() {
        var f = el.dFrom.value, t = el.dTo.value;
        if (!f || !t) { el.err.textContent = 'Please select both dates.'; return; }
        if (f > t) { el.err.textContent = 'From date must be before To date.'; return; }
        el.err.textContent = '';
        ui.from = f; ui.to = t; ui.lmore = false;
        detectPreset();
        el.box.removeAttribute('open');
        load();
    }

    root.addEventListener('click', function (e) {
        var a = e.target.closest('[data-action]');
        if (a) {
            var act = a.dataset.action;
            if (act === 'preset') {
                var r = presetRange(a.dataset.v);
                el.dFrom.value = r[0]; el.dTo.value = r[1];
                ui.preset = a.dataset.v;
                root.querySelectorAll('.presets button').forEach(function (b) { b.setAttribute('aria-pressed', String(b === a)); });
                el.err.textContent = '';
            } else if (act === 'apply') applyRange();
            else if (act === 'caltab') { ui.calTab = a.dataset.v; render(); }
            else if (act === 'lmore') { ui.lmore = !ui.lmore; render(); }
            else if (act === 'retry' || act === 'refresh') load();
            else if (act === 'reset') {
                var d = presetRange('month');
                ui.from = d[0]; ui.to = d[1]; ui.programme = 0; ui.search = ''; ui.lmore = false;
                el.prog.value = '0';
                detectPreset(); load();
            }
            return;
        }
        // clickable summary cards
        var c = e.target.closest('.stats-mini-card[data-href]');
        if (c) window.location.href = c.dataset.href;
    });

    ['adxDFrom', 'adxDTo'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', function () {
            var f = el.dFrom.value, t = el.dTo.value, hit = '';
            ['today', 'week', 'month', 'last', '3m', '6m'].forEach(function (k) {
                var r = presetRange(k);
                if (r[0] === f && r[1] === t) hit = k;
            });
            root.querySelectorAll('.presets button').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.v === hit)); });
        });
    });

    el.prog.addEventListener('change', function () { ui.programme = parseInt(el.prog.value, 10) || 0; ui.lmore = false; load(); });

    var st;
    el.search.addEventListener('input', function () {
        clearTimeout(st);
        st = setTimeout(function () { ui.search = el.search.value.trim(); ui.lmore = false; load(); }, 400);
    });

    // close the date popover when clicking elsewhere / pressing Escape
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#adxDateBox')) el.box.removeAttribute('open');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') el.box.removeAttribute('open');
    });

    // keep live classes fresh (only when the tab is visible)
    setInterval(function () { if (!document.hidden) load({ silent: true }); }, 60000);

    syncFilterBar();
    load();
})();