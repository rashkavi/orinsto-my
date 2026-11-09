/* =====================================================================
 * intakeGroups.js — intake-groups.php (opened from intake-details →
 * Students → Manage Groups). Group list is on the page; add/edit/members/
 * move/history are modals.
 * Markup: intake-groups.php + includes/components/intake-groups-modals.php
 * API:    api/intakeGroups.php (every rule is re-checked on the server)
 * ===================================================================== */

const IG_API = 'api/intakeGroups.php';

const igEl = (id) => document.getElementById(id);
const igEsc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const igToast = (type, msg) => { if (typeof showToast === 'function') showToast(type, msg); };

const igState = {
    intakeId: 0,
    canManage: false,
    groups: [],
    currentGroup: null,  // group object from the list
    eligible: [],
    selected: new Set(),
    members: [],
    moveShiId: 0,
};

function igPost(action, data = {}) {
    return fetch(IG_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ action, intake_id: igState.intakeId }, data))
    }).then(r => r.json());
}

const igSpinner = (cols, text = 'Loading...') =>
    `<tr><td colspan="${cols}" class="text-center py-4 text-14 sub-text">
        <span class="spinner-border spinner-border-sm me-2"></span>${text}</td></tr>`;

const igEmptyRow = (cols, text) =>
    `<tr><td colspan="${cols}" class="py-4"><div class="d-flex align-items-center justify-content-center">
        <div class="no-data-state"><div class="no-data-icon-wrap"><iconify-icon icon="ph:empty-bold"></iconify-icon></div>
        <p class="no-data-text">${igEsc(text)}</p></div></div></td></tr>`;

function igAvatar(s) {
    if (s.profile_img) {
        return `<img src="./assets/imgs/students/${igEsc(s.profile_img)}" class="hwr-40" alt=""
                 onerror="this.outerHTML='<div class=&quot;d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium&quot;>${igEsc(igInitials(s))}</div>'">`;
    }
    return `<div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">${igEsc(igInitials(s))}</div>`;
}
const igInitials = (s) => (((s.first_name || '')[0] || '') + ((s.last_name || '')[0] || '')).toUpperCase();

/* ---------------------------------------------------------------------
 * Modal navigation. Bootstrap 5 can't stack modals, so opening a child
 * hides the parent and closing the child re-opens the parent (and
 * refreshes it). igNav.stack holds the open chain, e.g.
 * [igMembersModal, igMoveModal]. When the last modal closes, the group
 * list on the page is refreshed.
 * ------------------------------------------------------------------- */
const igNav = { stack: [], forward: false, onReturn: {} };
const igModal = (id) => bootstrap.Modal.getOrCreateInstance(igEl(id));

function igOpen(id) {
    const top = igNav.stack[igNav.stack.length - 1];
    if (top === id) return;
    igNav.stack.push(id);
    if (top) {
        igNav.forward = true;
        igEl(top).addEventListener('hidden.bs.modal', () => {
            igNav.forward = false;
            igModal(id).show();
        }, { once: true });
        igModal(top).hide();
    } else {
        igModal(id).show();
    }
}

function igClose(id) { igModal(id).hide(); }

function igBindNav() {
    ['igGroupFormModal', 'igAddStudentsModal', 'igMembersModal', 'igMoveModal', 'igHistoryModal', 'igConfirmModal']
        .forEach(id => {
            const el = igEl(id);
            if (!el) return;
            el.addEventListener('hidden.bs.modal', () => {
                if (igNav.forward) return;                 // hidden because a child opened
                if (igNav.stack[igNav.stack.length - 1] !== id) return;
                igNav.stack.pop();
                const parent = igNav.stack[igNav.stack.length - 1];
                if (parent) {
                    igModal(parent).show();
                    if (igNav.onReturn[parent]) igNav.onReturn[parent]();
                } else {
                    igLoadGroups(); // back on the page: refresh counts
                }
            });
        });
    igNav.onReturn.igMembersModal = () => igLoadMembers(igState.currentGroup);
}

/* ---------------------------------------------------------------------
 * 1. Group list (on the page)
 * ------------------------------------------------------------------- */
function igLoadGroups() {
    const body = igEl('ig-groups-body');
    if (!igState.groups.length) {
        body.innerHTML = `<div class="text-center py-5 text-14 sub-text"><span class="spinner-border spinner-border-sm me-2"></span>Loading groups...</div>`;
    }
    igPost('list').then(d => {
        if (d.error) {
            body.innerHTML = `<div class="text-center py-4 text-14 text-dark-red">${igEsc(d.error)}</div>`;
            return;
        }
        igState.groups = d.groups || [];
        igState.canManage = !!d.can_manage;
        igEl('ig-unassigned-text').textContent = `${d.unassigned} student${d.unassigned === 1 ? '' : 's'} not in any group`;
        igRenderCards(d.unassigned);
        igRenderGroups();
    }).catch(() => {
        body.innerHTML = `<div class="text-center py-4 text-14 text-dark-red">Could not load groups. Please try again.</div>`;
    });
}

function igRenderCards(unassigned) {
    const set = (id, v) => { const el = igEl('ig-card-' + id); if (el) el.textContent = v; };
    set('groups', igState.groups.length);
    set('grouped', igState.groups.reduce((s, g) => s + g.student_count, 0));
    set('unassigned', unassigned);
    set('seats', igState.groups.reduce((s, g) => s + g.available, 0));
}

function igRenderGroups() {
    const body = igEl('ig-groups-body');
    const q = (igEl('ig-group-search')?.value || '').trim().toLowerCase();
    const shown = q ? igState.groups.filter(g => g.intake_group.toLowerCase().includes(q)) : igState.groups;

    if (igState.groups.length && !shown.length) {
        body.innerHTML = `<div class="d-flex justify-content-center py-4"><div class="no-data-state">
            <div class="no-data-icon-wrap"><iconify-icon icon="ph:empty-bold"></iconify-icon></div>
            <p class="no-data-text">No groups match your search</p></div></div>`;
        return;
    }
    if (!igState.groups.length) {
        body.innerHTML = `
        <div class="d-flex flex-column align-items-center justify-content-center py-5">
            <div class="no-data-state">
                <div class="no-data-icon-wrap"><iconify-icon icon="mdi:account-group-outline"></iconify-icon></div>
                <p class="no-data-text">No groups yet for this intake</p>
            </div>
            <p class="text-14 sub-text text-center mb-3">Create groups to split students for lectures. Lectures without a group stay open to everyone in the intake.</p>
            ${igState.canManage ? `<button type="button" class="custom-btn px-4" onclick="igOpenGroupForm()">
                <iconify-icon icon="mdi:plus" width="18" class="me-1"></iconify-icon>Add Group</button>` : ''}
        </div>`;
        return;
    }

    body.innerHTML = `
    <div class="">
        <table class="table bl-solid m-0">
            <thead><tr>
                <th class="px-3 py-3 bg-table-header text-14 main-text fw-medium">Group</th>
                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Students</th>
                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Capacity</th>
                <th class="py-3 bg-table-header text-14 main-text fw-medium" style="min-width:160px">Available Seats</th>
                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Status</th>
                <th class="py-3 bg-table-header"></th>
            </tr></thead>
            <tbody>
            ${shown.map(g => {
        const full = g.available <= 0;
        const pct = g.max_students ? Math.min(100, Math.round(g.student_count / g.max_students * 100)) : 0;
        return `<tr>
                    <td class="px-3 py-3 align-middle"><span class="text-14 main-text fw-medium">${igEsc(g.intake_group)}</span></td>
                    <td class="py-3 align-middle text-center text-14 main-text">${g.student_count}</td>
                    <td class="py-3 align-middle text-center text-14 main-text">${g.max_students}</td>
                    <td class="py-3 align-middle">
                        <div class="text-12 sub-text mb-1">${g.available} of ${g.max_students} free</div>
                        <div class="ig-capacity-bar ${full ? 'full' : ''}"><div style="width:${pct}%"></div></div>
                    </td>
                    <td class="py-3 align-middle text-center">
                        <span class="table-btn ${full ? 'table-btn-danger' : 'table-btn-success'} d-inline-flex py-1 px-2 text-12">${full ? 'Full' : 'Open'}</span>
                    </td>
                    <td class="py-3 align-middle text-end pe-3">
                        <div class="dropdown">
                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow br-5">
                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="igOpenMembers(${g.intake_group_id}); return false;">
                                    <iconify-icon icon="solar:eye-broken" width="20" class="sub-text me-3"></iconify-icon>View / Move Students</a></li>
                                ${igState.canManage ? `
                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center ${full ? 'disabled' : ''}" href="#" onclick="igOpenAddStudents(${g.intake_group_id}); return false;">
                                    <iconify-icon icon="mdi:account-plus-outline" width="20" class="sub-text me-3"></iconify-icon>Add Students${full ? ' (full)' : ''}</a></li>
                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="igOpenGroupForm(${g.intake_group_id}); return false;">
                                    <iconify-icon icon="ic:sharp-edit" width="20" class="sub-text me-3"></iconify-icon>Edit Group</a></li>` : ''}
                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="igOpenHistory(${g.intake_group_id}); return false;">
                                    <iconify-icon icon="mdi:history" width="20" class="sub-text me-3"></iconify-icon>History</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>`;
    }).join('')}
            </tbody>
        </table>
    </div>`;
}

const igFindGroup = (id) => igState.groups.find(g => g.intake_group_id === Number(id));

/* ---------------------------------------------------------------------
 * 2. Add / edit group
 * ------------------------------------------------------------------- */
function igOpenGroupForm(groupId = 0) {
    const g = groupId ? igFindGroup(groupId) : null;
    igEl('igGroupFormTitle').textContent = g ? 'Edit Group' : 'Add Group';
    igEl('ig-form-group-id').value = g ? g.intake_group_id : 0;
    igEl('ig-form-name').value = g ? g.intake_group : '';
    igEl('ig-form-max').value = g ? g.max_students : '';
    igEl('ig-form-max').min = g ? Math.max(1, g.student_count) : 1;
    const hint = igEl('ig-form-max-hint');
    hint.classList.toggle('d-none', !g);
    hint.textContent = g ? `Currently ${g.student_count} student(s) in this group — capacity can't go below that.` : '';
    ['ig-form-name-feedback', 'ig-form-max-feedback', 'ig-form-error'].forEach(id => igEl(id).textContent = '');
    igEl('ig-form-save').disabled = false;
    igOpen('igGroupFormModal');
    setTimeout(() => igEl('ig-form-name').focus(), 400);
}

function igSaveGroup() {
    const groupId = parseInt(igEl('ig-form-group-id').value, 10) || 0;
    const name = igEl('ig-form-name').value.trim();
    const maxRaw = igEl('ig-form-max').value.trim();
    const g = groupId ? igFindGroup(groupId) : null;
    let ok = true;

    ['ig-form-name-feedback', 'ig-form-max-feedback', 'ig-form-error'].forEach(id => igEl(id).textContent = '');
    if (!name) { igEl('ig-form-name-feedback').textContent = 'Please enter a group name.'; ok = false; }
    else if (name.length > 100) { igEl('ig-form-name-feedback').textContent = 'Group name must be 100 characters or fewer.'; ok = false; }
    if (!/^\d+$/.test(maxRaw) || parseInt(maxRaw, 10) < 1) {
        igEl('ig-form-max-feedback').textContent = 'Enter a whole number greater than 0.'; ok = false;
    } else if (g && parseInt(maxRaw, 10) < g.student_count) {
        igEl('ig-form-max-feedback').textContent = `Capacity can't be lower than the ${g.student_count} student(s) already in this group.`; ok = false;
    }
    if (!ok) return;

    const btn = igEl('ig-form-save');
    btn.disabled = true;
    igPost(groupId ? 'update' : 'create', { group_id: groupId, group_name: name, max_students: parseInt(maxRaw, 10) })
        .then(d => {
            if (d.error) {
                igEl('ig-form-error').textContent = d.error;
                btn.disabled = false;
                return;
            }
            igToast('success', d.done);
            igClose('igGroupFormModal'); // parent (group list) re-opens and refreshes
        })
        .catch(() => { igEl('ig-form-error').textContent = 'Something went wrong. Please try again.'; btn.disabled = false; });
}

/* ---------------------------------------------------------------------
 * 3. Add students
 * ------------------------------------------------------------------- */
function igOpenAddStudents(groupId) {
    const g = igFindGroup(groupId);
    if (!g) return;
    if (g.available <= 0) { igToast('error', `"${g.intake_group}" is full.`); return; }

    igState.currentGroup = g;
    igState.eligible = [];
    igState.selected = new Set();
    igEl('igAddTitle').textContent = `Add Students to ${g.intake_group}`;
    igEl('ig-add-sub').textContent = `${g.available} seat(s) available · ${g.student_count}/${g.max_students} assigned`;
    igEl('ig-add-search').value = '';
    igEl('ig-add-error').textContent = '';
    igEl('ig-add-check-all').checked = false;
    igEl('ig-add-body').innerHTML = igSpinner(4, 'Loading eligible students...');
    igUpdateAddCount();
    igOpen('igAddStudentsModal');

    igPost('eligible', { group_id: groupId }).then(d => {
        if (d.error) { igEl('ig-add-body').innerHTML = igEmptyRow(4, d.error); return; }
        igState.eligible = d.students || [];
        igRenderEligible();
    }).catch(() => { igEl('ig-add-body').innerHTML = igEmptyRow(4, 'Could not load students.'); });
}

function igFilteredEligible() {
    const q = igEl('ig-add-search').value.trim().toLowerCase();
    if (!q) return igState.eligible;
    return igState.eligible.filter(s => `${s.name} ${s.reg_no} ${s.email}`.toLowerCase().includes(q));
}

function igRenderEligible() {
    const list = igFilteredEligible();
    const body = igEl('ig-add-body');
    if (!igState.eligible.length) {
        body.innerHTML = igEmptyRow(4, 'All students in this intake are already in a group.');
    } else if (!list.length) {
        body.innerHTML = igEmptyRow(4, 'No students match your search.');
    } else {
        body.innerHTML = list.map(s => `
        <tr>
            <td class="px-3 py-2 align-middle">
                <input type="checkbox" class="form-check-input text-20 m-0" ${igState.selected.has(s.shi_id) ? 'checked' : ''}
                       onchange="igToggleEligible(${s.shi_id}, this.checked)">
            </td>
            <td class="py-2 align-middle"><div class="d-flex align-items-center">${igAvatar(s)}
                <span class="text-14 main-text mx-2">${igEsc(s.name)}</span>
                ${s.status && s.status !== 'Active' ? `<span class="table-btn table-btn-danger d-inline-flex py-0 px-2 text-12">${igEsc(s.status)}</span>` : ''}</div></td>
            <td class="py-2 align-middle text-14 main-text">${igEsc(s.reg_no)}</td>
            <td class="py-2 align-middle text-14 main-text">${igEsc(s.email)}</td>
        </tr>`).join('');
    }
    const allShownChecked = list.length > 0 && list.every(s => igState.selected.has(s.shi_id));
    igEl('ig-add-check-all').checked = allShownChecked;
    igUpdateAddCount();
}

function igToggleEligible(shiId, on) {
    on ? igState.selected.add(shiId) : igState.selected.delete(shiId);
    igRenderEligible();
}

function igToggleAllEligible(on) {
    igFilteredEligible().forEach(s => on ? igState.selected.add(s.shi_id) : igState.selected.delete(s.shi_id));
    igRenderEligible();
}

function igUpdateAddCount() {
    const g = igState.currentGroup;
    const n = igState.selected.size;
    const available = g ? g.available : 0;
    const over = n > available;
    const countEl = igEl('ig-add-count');
    countEl.textContent = `${n} selected · ${available} seat(s) available`;
    countEl.classList.toggle('text-dark-red', over);
    igEl('ig-add-save').disabled = n === 0 || over;
    igEl('ig-add-error').textContent = over ? `Only ${available} more student(s) fit in this group. Unselect ${n - available}.` : '';
}

function igAddSelected() {
    const ids = [...igState.selected];
    if (!ids.length) return;
    const btn = igEl('ig-add-save');
    btn.disabled = true;
    igPost('add_students', { group_id: igState.currentGroup.intake_group_id, shi_ids: ids }).then(d => {
        if (d.error) {
            igEl('ig-add-error').textContent = d.error;
            btn.disabled = false;
            return;
        }
        igToast('success', d.done);
        igClose('igAddStudentsModal');
    }).catch(() => { igEl('ig-add-error').textContent = 'Something went wrong. Please try again.'; btn.disabled = false; });
}

/* ---------------------------------------------------------------------
 * 4. Group students
 * ------------------------------------------------------------------- */
function igOpenMembers(groupId) {
    const g = igFindGroup(groupId);
    if (!g) return;
    igState.currentGroup = g;
    igEl('ig-members-search').value = '';
    igOpen('igMembersModal');
    igLoadMembers(g);
}

function igLoadMembers(g) {
    if (!g) return;
    igEl('igMembersTitle').textContent = g.intake_group;
    igEl('ig-members-body').innerHTML = igSpinner(4);
    igPost('members', { group_id: g.intake_group_id }).then(d => {
        if (d.error) { igEl('ig-members-body').innerHTML = igEmptyRow(4, d.error); return; }
        igState.members = d.students || [];
        // keep counts in sync with the server after a move/remove
        const fresh = igFindGroup(g.intake_group_id);
        if (fresh) {
            fresh.student_count = igState.members.length;
            fresh.max_students = d.group.max_students;
            fresh.available = Math.max(0, fresh.max_students - fresh.student_count);
        }
        igEl('ig-members-sub').textContent = `${igState.members.length} of ${d.group.max_students} students`;
        igRenderMembers();
    }).catch(() => { igEl('ig-members-body').innerHTML = igEmptyRow(4, 'Could not load students.'); });
}

function igRenderMembers() {
    const q = igEl('ig-members-search').value.trim().toLowerCase();
    const list = q ? igState.members.filter(s => `${s.name} ${s.reg_no} ${s.email}`.toLowerCase().includes(q)) : igState.members;
    const body = igEl('ig-members-body');
    if (!igState.members.length) { body.innerHTML = igEmptyRow(4, 'No students in this group yet.'); return; }
    if (!list.length) { body.innerHTML = igEmptyRow(4, 'No students match your search.'); return; }

    body.innerHTML = list.map(s => `
    <tr>
        <td class="px-3 py-2 align-middle"><div class="d-flex align-items-center">${igAvatar(s)}
            <div class="d-flex flex-column mx-2"><span class="text-14 main-text">${igEsc(s.name)}</span>
            <span class="text-12 sub-text">${igEsc(s.email)}</span></div></div></td>
        <td class="py-2 align-middle text-14 main-text">${igEsc(s.reg_no)}</td>
        <td class="py-2 align-middle"><span class="table-btn ${s.status_id === 1 ? 'table-btn-success' : 'table-btn-yellow'} d-inline-flex py-1 px-2 text-12">${igEsc(s.status)}</span></td>
        <td class="py-2 align-middle text-end pe-3">
            ${igState.canManage ? `
            <div class="dropdown">
                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow br-5">
                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="igOpenMove(${s.shi_id}); return false;">
                        <iconify-icon icon="mdi:swap-horizontal" width="20" class="sub-text me-3"></iconify-icon>Move to Another Group</a></li>
                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="igConfirmRemove(${s.shi_id}); return false;">
                        <iconify-icon icon="mdi:account-remove-outline" width="20" class="sub-text me-3"></iconify-icon>Remove from Group</a></li>
                </ul>
            </div>` : ''}
        </td>
    </tr>`).join('');
}

/* ---------------------------------------------------------------------
 * 5. Move student
 * ------------------------------------------------------------------- */
function igOpenMove(shiId) {
    const s = igState.members.find(m => m.shi_id === shiId);
    const src = igState.currentGroup;
    if (!s || !src) return;
    igState.moveShiId = shiId;
    igEl('ig-move-student').textContent = `${s.name} (${s.reg_no})`;
    igEl('ig-move-current').textContent = src.intake_group;
    igEl('ig-move-error').textContent = '';

    const targets = igState.groups.filter(g => g.intake_group_id !== src.intake_group_id);
    const sel = igEl('ig-move-target');
    const open = targets.filter(g => g.available > 0);
    if (!targets.length) {
        igEl('ig-move-target-wrap').classList.add('d-none');
        igEl('ig-move-error').textContent = 'There are no other groups in this intake. Create another group first.';
        igEl('ig-move-save').disabled = true;
    } else {
        igEl('ig-move-target-wrap').classList.remove('d-none');
        sel.innerHTML = `<option value="0">- Select group -</option>` + targets.map(g =>
            `<option value="${g.intake_group_id}" ${g.available > 0 ? '' : 'disabled'}>
                ${igEsc(g.intake_group)} — ${g.available > 0 ? `${g.available} seat(s) free` : 'full'} (${g.student_count}/${g.max_students})
            </option>`).join('');
        igEl('ig-move-save').disabled = !open.length;
        if (!open.length) igEl('ig-move-error').textContent = 'Every other group is full.';
    }
    igOpen('igMoveModal');
}

function igMoveStudent() {
    const to = parseInt(igEl('ig-move-target').value, 10) || 0;
    if (!to) { igEl('ig-move-error').textContent = 'Please select the destination group.'; return; }
    const btn = igEl('ig-move-save');
    btn.disabled = true;
    igPost('move', { shi_id: igState.moveShiId, from_group_id: igState.currentGroup.intake_group_id, to_group_id: to })
        .then(d => {
            if (d.error) { igEl('ig-move-error').textContent = d.error; btn.disabled = false; return; }
            igToast('success', d.done);
            // refresh capacities so the next move/add sees current numbers
            igPost('list').then(l => { if (l.groups) igState.groups = l.groups; });
            igClose('igMoveModal');
        })
        .catch(() => { igEl('ig-move-error').textContent = 'Something went wrong. Please try again.'; btn.disabled = false; });
}

/* ---------------------------------------------------------------------
 * Remove from group (confirmed)
 * ------------------------------------------------------------------- */
function igConfirmRemove(shiId) {
    const s = igState.members.find(m => m.shi_id === shiId);
    if (!s) return;
    igEl('ig-confirm-msg').textContent =
        `${s.name} (${s.reg_no}) will be removed from "${igState.currentGroup.intake_group}" and will only see lectures open to the whole intake until added to a group again. This is recorded in the group history.`;
    igEl('ig-confirm-error').textContent = '';
    const btn = igEl('ig-confirm-btn');
    btn.disabled = false;
    btn.onclick = () => {
        btn.disabled = true;
        igPost('remove', { shi_id: shiId, group_id: igState.currentGroup.intake_group_id }).then(d => {
            if (d.error) { igEl('ig-confirm-error').textContent = d.error; btn.disabled = false; return; }
            igToast('success', d.done);
            igClose('igConfirmModal');
        }).catch(() => { igEl('ig-confirm-error').textContent = 'Something went wrong. Please try again.'; btn.disabled = false; });
    };
    igOpen('igConfirmModal');
}

/* ---------------------------------------------------------------------
 * 6. History (read-only)
 * ------------------------------------------------------------------- */
const IG_ACTION_LABELS = {
    GROUP_CREATED: ['Group created', 'table-btn-success'],
    GROUP_UPDATED: ['Group updated', 'table-btn-purple'],
    STUDENT_ADDED: ['Student added', 'table-btn-success'],
    STUDENT_MOVED_IN: ['Moved in', 'table-btn-green-blue'],
    STUDENT_MOVED_OUT: ['Moved out', 'table-btn-yellow'],
    STUDENT_REMOVED: ['Removed', 'table-btn-danger'],
};

function igOpenHistory(groupId) {
    const g = igFindGroup(groupId);
    if (!g) return;
    igEl('igHistoryTitle').textContent = `History — ${g.intake_group}`;
    igEl('ig-history-sub').textContent = 'Read-only audit log of this group';
    igEl('ig-history-body').innerHTML = igSpinner(7);
    igOpen('igHistoryModal');

    igPost('history', { group_id: groupId }).then(d => {
        if (d.error) { igEl('ig-history-body').innerHTML = igEmptyRow(7, d.error); return; }
        const rows = d.history || [];
        if (!rows.length) { igEl('ig-history-body').innerHTML = igEmptyRow(7, 'No history yet.'); return; }
        igEl('ig-history-body').innerHTML = rows.map(h => {
            const [label, cls] = IG_ACTION_LABELS[h.action] || [h.action, ''];
            const dt = new Date(String(h.date_added).replace(' ', 'T'));
            const when = isNaN(dt) ? h.date_added : dt.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            return `<tr>
                <td class="px-3 py-2 align-middle text-14 main-text text-nowrap">${igEsc(when)}</td>
                <td class="py-2 align-middle"><span class="table-btn ${cls} d-inline-flex py-1 px-2 text-12 ig-action-badge">${igEsc(label)}</span></td>
                <td class="py-2 align-middle text-14 main-text">${igEsc(h.student_label || '—')}</td>
                <td class="py-2 align-middle text-14 main-text">${igEsc(h.from_group || '—')}</td>
                <td class="py-2 align-middle text-14 main-text">${igEsc(h.to_group || '—')}</td>
                <td class="py-2 align-middle text-14 main-text">${igEsc(h.performed_by)}</td>
                <td class="py-2 align-middle text-12 sub-text">${igEsc(h.details || '')}</td>
            </tr>`;
        }).join('');
    }).catch(() => { igEl('ig-history-body').innerHTML = igEmptyRow(7, 'Could not load history.'); });
}

/* ---------------------------------------------------------------------
 * Init
 * ------------------------------------------------------------------- */
// <body onload> on intake-groups.php: swap the skeleton for the page and load.
function initializeIntakeGroupsPage() {
    const root = igEl('igPage');
    if (!root) return;
    igState.intakeId = parseInt(root.dataset.intakeId, 10) || 0;
    igState.canManage = root.dataset.canManage === '1';
    igBindNav();

    igEl('skeleton-list')?.classList.add('d-none');
    igEl('real-list')?.classList.remove('d-none');
    if (typeof initializeLanguage === 'function') {
        try { initializeLanguage(); } catch (e) { /* optional */ }
    }
    igLoadGroups();
}
