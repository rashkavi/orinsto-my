/* =====================================================================
 * lectureAudience.js
 * ---------------------------------------------------------------------
 * Lecture audience = which intake groups a lecture targets.
 * Nothing selected  → all students in the intake (no rows are saved).
 *
 * 1) Shared selector:   renderAudienceSelector(), getAudienceSelection()
 * 2) Schedule page:     reacts to #intake-selector, sends group_ids
 *                       with the existing scheduleLecture() request
 * 3) Calendar pages:    openLectureAudienceModal(lectureId) — load the
 *                       saved audience, change it, save it
 *
 * Backend: api/lectureGroups.php, api/scheduleLecture.php
 * ===================================================================== */

const LA_API = 'api/lectureGroups.php';

const laEsc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const laPost = (body) => fetch(LA_API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
}).then(r => r.json());

function laToast(type, msg) {
    if (typeof showToast === 'function') showToast(type, msg);
}

function laJoinNames(names) {
    if (names.length <= 1) return names.join('');
    return names.slice(0, -1).join(', ') + ' and ' + names[names.length - 1];
}

/* ---------------------------------------------------------------------
 * 1) Shared selector
 * ------------------------------------------------------------------- */

/**
 * Render group checkboxes + the live audience message into `container`.
 * groups:      [{id, name, max_students, student_count}]
 * selectedIds: ids to pre-tick (the SAVED selection when editing)
 */
function renderAudienceSelector(container, groups, selectedIds = []) {
    if (!container) return;
    const selected = new Set((selectedIds || []).map(Number));

    if (!groups || groups.length === 0) {
        container.innerHTML = `
            <div class="d-flex align-items-start gap-2 p-3 bg-light-theme">
                <iconify-icon icon="mdi:information-outline" width="20" class="dark-theme flex-shrink-0"></iconify-icon>
                <span class="text-14 main-text">This intake has no student groups. The lecture will be available to all students in this intake.</span>
            </div>`;
        container.dataset.hasGroups = '0';
        return;
    }

    container.dataset.hasGroups = '1';
    container.innerHTML = `
        <div class="d-flex flex-wrap gap-2 la-group-list">
            ${groups.map(g => `
                <label class="d-flex align-items-center gap-2 px-3 py-2 bl-solid cursor-pointer la-group-option">
                    <input type="checkbox" class="form-check-input text-20 m-0 la-group-check"
                           value="${Number(g.id)}" data-name="${laEsc(g.name)}" ${selected.has(Number(g.id)) ? 'checked' : ''}>
                    <span class="text-14 main-text">${laEsc(g.name)}</span>
                    <span class="text-12 sub-text">(${Number(g.student_count)} student${Number(g.student_count) === 1 ? '' : 's'})</span>
                </label>`).join('')}
        </div>
        <div class="d-flex align-items-start gap-2 p-3 mt-3 bg-light-theme la-audience-msg">
            <iconify-icon icon="mdi:information-outline" width="20" class="dark-theme flex-shrink-0"></iconify-icon>
            <span class="text-14 main-text la-audience-text"></span>
        </div>`;

    container.querySelectorAll('.la-group-check').forEach(cb => cb.addEventListener('change', () => laUpdateMessage(container)));
    laUpdateMessage(container);
}

function laUpdateMessage(container) {
    const txt = container.querySelector('.la-audience-text');
    if (!txt) return;
    const names = [...container.querySelectorAll('.la-group-check:checked')].map(cb => cb.dataset.name);
    txt.textContent = names.length
        ? `This lecture will be available only to students in ${laJoinNames(names)}.`
        : 'No groups selected. This lecture will be available to all students in this intake.';
}

function getAudienceSelection(container) {
    if (!container) return [];
    return [...container.querySelectorAll('.la-group-check:checked')].map(cb => Number(cb.value));
}

/* ---------------------------------------------------------------------
 * 2) Schedule Lecture page (schedule-lecture.php)
 * ------------------------------------------------------------------- */

let laScheduleIntakeId = 0; // intake whose groups are currently rendered
let laScheduleReq = 0;      // guards against out-of-order responses

function laScheduleSection() { return document.getElementById('lecture-audience-section'); }
function laScheduleBox()     { return document.getElementById('lecture-audience-groups'); }

function laResetScheduleAudience() {
    laScheduleIntakeId = 0;
    const section = laScheduleSection();
    if (section) section.classList.add('d-none');
    const box = laScheduleBox();
    if (box) box.innerHTML = '';
    const fb = document.getElementById('groups-feedback');
    if (fb) fb.textContent = '';
}

function loadScheduleLectureGroups() {
    const sel = document.getElementById('intake-selector');
    const intakeId = sel ? parseInt(sel.value, 10) || 0 : 0;
    laResetScheduleAudience();
    if (!intakeId) return;

    const box = laScheduleBox();
    const section = laScheduleSection();
    if (!box || !section) return;

    const req = ++laScheduleReq;
    section.classList.remove('d-none');
    box.innerHTML = `<div class="d-flex align-items-center gap-2 text-14 sub-text">
        <span class="spinner-border spinner-border-sm"></span> Loading groups...</div>`;

    laPost({ action: 'options', intake_id: intakeId }).then(d => {
        if (req !== laScheduleReq) return; // a newer intake was chosen meanwhile
        if (d.error) {
            box.innerHTML = `<span class="text-14 text-dark-red">${laEsc(d.error)}</span>`;
            return;
        }
        laScheduleIntakeId = intakeId;
        renderAudienceSelector(box, d.groups || [], []);
    }).catch(() => {
        if (req !== laScheduleReq) return;
        box.innerHTML = '<span class="text-14 text-dark-red">Could not load groups. The lecture will be scheduled for all students unless you retry.</span>';
    });
}

/**
 * Append the audience to the schedule FormData.
 * Only sends groups that were loaded for the intake that is selected NOW;
 * the server re-validates them against the subject's intake anyway.
 */
function appendLectureAudience(formData) {
    if (!(formData instanceof FormData) || formData.has('group_ids')) return formData;
    const sel = document.getElementById('intake-selector');
    const currentIntake = sel ? parseInt(sel.value, 10) || 0 : 0;
    const ids = currentIntake && currentIntake === laScheduleIntakeId ? getAudienceSelection(laScheduleBox()) : [];
    formData.append('group_ids', JSON.stringify(ids));
    return formData;
}

function initScheduleLectureAudience() {
    const intakeSel = document.getElementById('intake-selector');
    if (!intakeSel || !laScheduleSection()) return; // not the schedule page

    intakeSel.addEventListener('change', loadScheduleLectureGroups);
    // Changing faculty / programme resets the intake list in functions.js
    // without firing a change event on #intake-selector, so clear here too.
    ['faculty-selector', 'programme-selector'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', laResetScheduleAudience);
    });

    // scheduleLecture() lives in functions.js (not part of this change).
    // Attach group_ids to its request without editing it: only for the
    // schedule endpoint, only when the body is FormData without group_ids.
    if (!window.__laFetchWrapped) {
        window.__laFetchWrapped = true;
        const originalFetch = window.fetch.bind(window);
        window.fetch = function (url, opts) {
            const u = typeof url === 'string' ? url : (url && url.url) || '';
            const isSchedule = /api\/scheduleLecture(\.php)?(\?|$)/.test(u);
            if (isSchedule && opts && opts.body instanceof FormData) {
                appendLectureAudience(opts.body);
                return originalFetch(url, opts).then(res => {
                    res.clone().json().then(laShowScheduleGroupError).catch(() => { });
                    return res;
                });
            }
            return originalFetch(url, opts);
        };
    }
}

function laShowScheduleGroupError(d) {
    const fb = document.getElementById('groups-feedback');
    if (!fb) return;
    fb.textContent = d && d.groups ? d.groups : '';
    if (d && d.groups) laToast('error', d.groups);
    if (d && d.hasOwnProperty('done')) loadScheduleLectureGroups(); // reset selection after success
}

document.addEventListener('DOMContentLoaded', initScheduleLectureAudience);

/* ---------------------------------------------------------------------
 * 3) Calendar: change the audience of an existing lecture
 *    (lecture-schedule.php and any page using eventCalender.js
 *     that includes this file)
 * ------------------------------------------------------------------- */

function laEnsureAudienceModal() {
    if (document.getElementById('lectureAudienceModal')) return;
    document.body.insertAdjacentHTML('beforeend', `
    <div class="modal fade" id="lectureAudienceModal" tabindex="-1" aria-labelledby="lectureAudienceTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content p-2">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title main-text" id="lectureAudienceTitle">Lecture Audience</h5>
                        <span class="text-12 sub-text" id="lectureAudienceSub"></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="la-lecture-id">
                    <div id="la-modal-groups"></div>
                    <span class="text-dark-red text-12 d-block mt-2" id="la-modal-error"></span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="custom-outline-btn px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="custom-btn px-4" id="la-modal-save" onclick="saveLectureAudience()">Save Audience</button>
                </div>
            </div>
        </div>
    </div>`);
}

function openLectureAudienceModal(lectureId) {
    laEnsureAudienceModal();

    // Bootstrap 5 does not support stacked modals; close the details modal first.
    const details = document.getElementById('lectureDetailsModal');
    const detailsInst = details ? bootstrap.Modal.getInstance(details) : null;
    if (detailsInst) detailsInst.hide();

    const box = document.getElementById('la-modal-groups');
    const err = document.getElementById('la-modal-error');
    const saveBtn = document.getElementById('la-modal-save');
    document.getElementById('la-lecture-id').value = lectureId;
    document.getElementById('lectureAudienceSub').textContent = '';
    err.textContent = '';
    saveBtn.disabled = true;
    box.innerHTML = `<div class="d-flex align-items-center gap-2 text-14 sub-text">
        <span class="spinner-border spinner-border-sm"></span> Loading saved audience...</div>`;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('lectureAudienceModal')).show();

    laPost({ action: 'get', lecture_id: lectureId }).then(d => {
        if (d.error) {
            box.innerHTML = '';
            err.textContent = d.error;
            return;
        }
        document.getElementById('lectureAudienceSub').textContent =
            `${d.lecture.intake} · ${d.lecture.subject}`;
        // Pre-tick exactly what is saved; an all-intake lecture ticks nothing.
        renderAudienceSelector(box, d.groups || [], d.selected || []);
        saveBtn.disabled = !(d.groups && d.groups.length);
    }).catch(() => {
        box.innerHTML = '';
        err.textContent = 'Could not load the lecture audience. Please try again.';
    });
}

function saveLectureAudience() {
    const btn = document.getElementById('la-modal-save');
    const err = document.getElementById('la-modal-error');
    const lectureId = parseInt(document.getElementById('la-lecture-id').value, 10);
    const ids = getAudienceSelection(document.getElementById('la-modal-groups'));

    btn.disabled = true;
    err.textContent = '';
    laPost({ action: 'save', lecture_id: lectureId, group_ids: ids }).then(d => {
        if (d.error) {
            err.textContent = d.error;
            btn.disabled = false;
            return;
        }
        laToast('success', 'Lecture audience updated.');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('lectureAudienceModal')).hide();
        if (typeof refreshLectureCalendar === 'function') refreshLectureCalendar();
    }).catch(() => {
        err.textContent = 'Something went wrong. Please try again.';
        btn.disabled = false;
    });
}
