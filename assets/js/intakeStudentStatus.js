/* =====================================================================
 * intakeStudentStatus.js — intake-details.php
 * One modal for Activate / Deactivate / Discontinue (single row or bulk).
 * Shows how many students will change, asks for a reason + note, and the
 * API saves a students_has_intakes_logs row per student.
 * API: api/updateIntakeStudentsStatus.php
 * Entry point: issOpenStatusModal(intakeId, update, target)
 *   target = { checked: [shiId...], unchecked: [shiId...] }
 * ===================================================================== */

const ISS_API = 'api/updateIntakeStudentsStatus.php';
const ISS_TEXT = {
    1: { verb: 'Activate', past: 'activated', title: 'Activate Students', icon: 'mdi:account-check-outline', iconCls: 'text-success', btn: 'btn-success' },
    2: { verb: 'Deactivate', past: 'deactivated', title: 'Deactivate Students', icon: 'mdi:account-cancel-outline', iconCls: 'text-warning', btn: 'btn-warning' },
    3: { verb: 'Discontinue', past: 'discontinued', title: 'Discontinue Students', icon: 'mdi:account-remove-outline', iconCls: 'text-danger', btn: 'btn-danger' },
};
const issEl = (id) => document.getElementById(id);
const issEsc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const issState = { intakeId: 0, update: 0, payload: null, reasons: null, saving: false };

function issPost(body) {
    return fetch(ISS_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).then(r => r.json());
}

/** Students on the current page (used when "select all on page" is ticked). */
function issPageStudentIds() {
    return [...document.querySelectorAll('input.row-check[id^="check-user"]')]
        .map(cb => parseInt(cb.id.replace('check-user', ''), 10))
        .filter(n => n > 0);
}

function issBuildPayload(intakeId, update, target) {
    return {
        intakeId,
        update,
        checkedStudents: target.checked || [],
        uncheckedStudents: target.unchecked || [],
        pageStudents: (target.checked || []).length || (target.unchecked || []).length ? [] : issPageStudentIds(),
        currentPage: typeof selectedPage !== 'undefined' ? selectedPage : 1,
        searchText: $("#search-text").val(),
        sortSelector: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
    };
}

function issLoadReasons() {
    if (issState.reasons) return Promise.resolve(issState.reasons);
    return issPost({ action: 'reasons' }).then(d => {
        issState.reasons = d.reasons || [];
        return issState.reasons;
    }).catch(() => (issState.reasons = []));
}

function issClearErrors() {
    document.querySelectorAll('[data-ss-error]').forEach(e => e.textContent = '');
    issEl('ss-error').textContent = '';
}

/**
 * Open the status modal. update: 1 Active, 2 Inactive, 3 Discontinued.
 * target: { checked:[...], unchecked:[...] } — both empty = students on this page.
 */
function issOpenStatusModal(intakeId, update, target = {}) {
    update = parseInt(update, 10) || 0;
    if (!ISS_TEXT[update]) {
        showToast("warning", "Please select the desired student status change option.");
        return;
    }
    const checked = target.checked || [];
    const unchecked = target.unchecked || [];
    // Nothing selected and "select all" not ticked → don't silently update the whole page.
    if (!checked.length && !unchecked.length && !(issEl('check-all') && issEl('check-all').checked)) {
        showToast("warning", "Please select at least one student.");
        return;
    }

    const t = ISS_TEXT[update];
    issState.intakeId = intakeId;
    issState.update = update;
    issState.payload = issBuildPayload(intakeId, update, target);

    issEl('studentStatusModalTitle').textContent = t.title;
    const icon = issEl('ss-icon');
    icon.setAttribute('icon', t.icon);
    icon.className = t.iconCls;
    issEl('ss-summary').textContent = 'Checking selected students...';
    issEl('ss-skipped').classList.add('d-none');
    issEl('ss-discontinue-warning').classList.toggle('d-none', update !== 3);
    issEl('ss-reason-wrap').classList.toggle('d-none', update === 1);
    // Activate = active now + pick the auto-deactivate date (only the next 3 days can be picked).
    const dateWrap = issEl('ss-deactivate-date-wrap');
    if (dateWrap) {
        dateWrap.classList.toggle('d-none', update !== 1);
        issEl('ss-deactivate-date').value = '';
        issEl('ss-deactivate-date').disabled = true;
    }
    issEl('ss-note').value = '';
    issEl('ss-note-label').classList.toggle('required', update === 1);
    issEl('ss-note').placeholder = update === 1 ? 'Why is this student being re-activated?' : 'Add any details about this status change';
    const btn = issEl('ss-confirm');
    btn.className = `btn ${t.btn} px-4 br-0`;
    btn.textContent = `${t.verb}`;
    btn.disabled = true;
    issClearErrors();

    bootstrap.Modal.getOrCreateInstance(issEl('studentStatusModal')).show();

    Promise.all([
        issPost(Object.assign({ action: 'preview' }, issState.payload)),
        update === 1 ? Promise.resolve([]) : issLoadReasons(),
    ]).then(([p, reasons]) => {
        if (p.error) {
            issEl('ss-summary').textContent = '';
            issEl('ss-error').textContent = p.error;
            return;
        }
        // reasons dropdown
        const sel = issEl('ss-reason');
        sel.innerHTML = '<option value="">- Select Reason -</option>' +
            reasons.map(r => `<option value="${r.id}">${issEsc(r.name)}</option>`).join('');
        if (update !== 1 && !reasons.length) {
            // no reasons configured → the note becomes the required reason
            issEl('ss-reason-wrap').classList.add('d-none');
            issEl('ss-note-label').classList.add('required');
            issEl('ss-note').placeholder = 'Reason for this status change';
        }

        if (update === 1 && dateWrap) {
            const d = issEl('ss-deactivate-date');
            d.min = p.min_date || '';
            d.max = p.max_date || '';
            d.value = p.max_date || '';   // preselect the last allowed day
            d.disabled = false;
            let help = `Students are activated now and deactivated automatically on this date (${p.min_date} to ${p.max_date} only).`;
            if (p.monthly_limit) help += ' Academic coordinators can activate each student once per month.';
            issEl('ss-deactivate-date-help').textContent = help;
        }

        if (!p.eligible) {
            issEl('ss-summary').textContent = `None of the selected students can be ${t.past}.`;
        } else {
            const names = p.sample.length ? ` (${p.sample.map(issEsc).join(', ')}${p.eligible > p.sample.length ? ', …' : ''})` : '';
            issEl('ss-summary').innerHTML = `<strong>${p.eligible}</strong> student${p.eligible === 1 ? '' : 's'} will be ${t.past}${names}.`;
            btn.textContent = `${t.verb} ${p.eligible} Student${p.eligible === 1 ? '' : 's'}`;
            btn.disabled = false;
        }
        const skipped = p.already + p.locked + (p.limited || 0);
        if (skipped) {
            const parts = [];
            if (p.already) parts.push(`${p.already} already ${update === 1 ? 'active' : update === 2 ? 'inactive' : 'discontinued'}`);
            if (p.locked) parts.push(`${p.locked} discontinued (cannot be changed)`);
            if (p.limited) parts.push(`${p.limited} already activated this month (once per month)`);
            issEl('ss-skipped').textContent = `Skipped: ${parts.join(', ')}.`;
            issEl('ss-skipped').classList.remove('d-none');
        }
    }).catch(() => {
        issEl('ss-summary').textContent = '';
        issEl('ss-error').textContent = 'Could not check the selected students. Please try again.';
    });
}

function issSubmit() {
    if (issState.saving) return;
    issClearErrors();
    const update = issState.update;
    const reasonId = issEl('ss-reason').value;
    const note = issEl('ss-note').value.trim();
    const reasonsShown = !issEl('ss-reason-wrap').classList.contains('d-none');

    if (reasonsShown && !reasonId) {
        document.querySelector('[data-ss-error="reason"]').textContent = 'Please select a reason.';
        return;
    }
    const deactivateDate = update === 1 && issEl('ss-deactivate-date') ? issEl('ss-deactivate-date').value : '';
    if (update === 1) {
        const d = issEl('ss-deactivate-date');
        let msg = '';
        if (!deactivateDate) msg = 'Please select the deactivate date.';
        else if ((d.min && deactivateDate < d.min) || (d.max && deactivateDate > d.max)) msg = `Deactivate date must be between ${d.min} and ${d.max}.`;
        if (msg) {
            document.querySelector('[data-ss-error="deactivateDate"]').textContent = msg;
            return;
        }
    }
    if ((update === 1 || !reasonsShown) && !note) {
        document.querySelector('[data-ss-error="note"]').textContent = update === 1 ? 'Please enter the reason for activating.' : 'Please enter the reason.';
        return;
    }

    const btn = issEl('ss-confirm');
    const label = btn.textContent;
    issState.saving = true;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    issPost(Object.assign({ action: 'update', reasonId: reasonId ? parseInt(reasonId, 10) : null, note, deactivateDate }, issState.payload))
        .then(d => {
            if (d.error) {
                issEl('ss-error').textContent = d.error;
                Object.entries(d.fields || {}).forEach(([k, v]) => {
                    const el = document.querySelector(`[data-ss-error="${k}"]`);
                    if (el) el.textContent = v;
                });
                return;
            }
            bootstrap.Modal.getOrCreateInstance(issEl('studentStatusModal')).hide();
            showToast("success", d.done);
            if (typeof filterIntakeHasStudents === 'function') filterIntakeHasStudents(issState.intakeId);
            if (typeof setDefault === 'function') setDefault();
            const upd = issEl('update-selector');
            if (upd) upd.value = '0';
        })
        .catch(() => { issEl('ss-error').textContent = 'Something went wrong. No student status was changed.'; })
        .finally(() => {
            issState.saving = false;
            btn.textContent = label;
            btn.disabled = false;
        });
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = issEl('ss-confirm');
    if (btn) btn.addEventListener('click', issSubmit);
});
