/* =====================================================================
 * staffPayments.js — staff-payments.php
 * Salary paysheets from finance-approved payroll runs, ordered by the
 * scheduled pay date (from each staff member's payroll profile).
 * Rows due today are highlighted; finance confirms each payment with a
 * receipt (uploaded through the system file chooser — fileChooser.js).
 * API: api/staffPayments.php, api/staffPaymentReceipt.php
 * ===================================================================== */

const SP_API = 'api/staffPayments.php';
const spEl = (id) => document.getElementById(id);
const spEsc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const spMoney = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const spToast = (t, m) => { if (typeof showToast === 'function') showToast(t, m); };
const spToday = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const spFmtDate = (ymd) => {
    if (!ymd) return '';
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const SP_STATUS = {
    DUE_TODAY: ['Due Today', 'table-btn-danger'],
    OVERDUE:   ['Overdue', 'table-btn-orange'],
    UPCOMING:  ['Upcoming', 'table-btn-yellow'],
    NOT_SET:   ['Pay Date Not Set', 'table-btn-purple'],
    PAID:      ['Paid', 'table-btn-success'],
};

const spState = { page: 1, pages: 1, rows: [], req: 0, current: null, saving: false, file: null };

/* ----------------------------------------------------------- filters */
function spFilters() {
    return {
        status: spEl('sp-status').value,
        run_id: parseInt(spEl('sp-run').value, 10) || 0,
        method: spEl('sp-method-filter').value,
        date_from: spEl('sp-date-from').value,
        date_to: spEl('sp-date-to').value,
        search: spEl('sp-search').value.trim(),
        per_page: parseInt(spEl('sp-per-page').value, 10) || 10,
    };
}



/* -------------------------------------------------------------- list */
function spLoad(page = 1) {
    const f = spFilters();
    if (f.date_from && f.date_to && f.date_from > f.date_to) {
        spToast('error', '"Pay Date From" must be on or before "Pay Date To".');
        return;
    }
    const req = ++spState.req;
    const body = spEl('sp-body');
    body.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-14 sub-text">
        <span class="spinner-border spinner-border-sm me-2"></span>Loading staff payments...</td></tr>`;

    fetch(SP_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ action: 'list', page }, f))
    }).then(r => r.json()).then(d => {
        if (req !== spState.req) return; // a newer filter request is in flight
        if (d.error) {
            body.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-14 text-dark-red">${spEsc(d.error)}</td></tr>`;
            return;
        }
        spState.rows = d.rows || [];
        spState.page = d.page;
        spState.pages = d.pages;
        spRenderTotals(d.totals);
        spRenderRows();
        spRenderPages();
    }).catch(() => {
        if (req !== spState.req) return;
        body.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-14 text-dark-red">Could not load staff payments. Please try again.</td></tr>`;
    });
}

function spRenderTotals(t) {
    // Count box follows the active filters
    spEl('sp-count').textContent = t.rows;

    spEl('sp-card-due_today').textContent = spMoney(t.due_today_amount);
    spEl('sp-card-due_today-meta').textContent = `${t.due_today_count} staff to pay today`;
    spEl('sp-card-overdue').textContent = spMoney(t.overdue_amount);
    spEl('sp-card-overdue-meta').textContent = `${t.overdue_count} past their pay date`;
    spEl('sp-card-pending').textContent = spMoney(t.pending_amount);
    spEl('sp-card-pending-meta').textContent = `${t.pending_count} awaiting receipt`;
    spEl('sp-card-paid').textContent = spMoney(t.paid);
    spEl('sp-card-paid-meta').textContent = `${t.paid_count} paid with receipt`;
}

function spMethodCell(r) {
    if (!r.profile_method) return '<span class="text-14 sub-text">Not set</span>';
    const bank = [r.bank_name, r.bank_account_no].filter(Boolean).join(' · ');
    return `<span class="text-14 main-text fw-medium">${spEsc(r.profile_method)}</span>
        ${bank ? `<div class="sp-bank-line sub-text">${spEsc(bank)}</div>` : ''}
        ${r.bank_branch ? `<div class="sp-bank-line sub-text">${spEsc(r.bank_branch)}</div>` : ''}`;
}

function spPayDateCell(r) {
    if (r.is_paid) {
        return `<span class="text-14 main-text">${r.due_date ? spEsc(spFmtDate(r.due_date)) : '—'}</span>`;
    }
    if (!r.due_date) {
        return `<span class="text-14 sub-text">Not set</span><div class="sp-bank-line sub-text">Set in payroll profile</div>`;
    }
    const cls = r.due_status === 'DUE_TODAY' ? 'text-dark-red fw-semibold' : (r.due_status === 'OVERDUE' ? 'text-dark-orange fw-semibold' : 'main-text');
    return `<span class="text-14 ${cls}">${spEsc(spFmtDate(r.due_date))}</span>
        <div class="sp-bank-line sub-text">${spEsc(r.pay_day_label)}</div>`;
}

function spRenderRows() {
    const body = spEl('sp-body');
    if (!spState.rows.length) {
        body.innerHTML = `<tr class="justify-content-center align-middle"><td colspan="9" class="align-middle">
            <div class="d-flex flex-grow-1 align-items-center justify-content-center"><div class="no-data-state">
            <div class="no-data-icon-wrap"><iconify-icon icon="ph:empty-bold"></iconify-icon></div>
            <p class="no-data-text">No staff payments match these filters</p></div></div></td></tr>`;
        return;
    }
    body.innerHTML = spState.rows.map(r => {
        const p = r.payment;
        const short = p && Math.abs(p.amount - r.net) > 0.009;
        const [label, cls] = SP_STATUS[r.due_status] || [r.due_status, 'table-btn-primary'];
        const ribbon = r.due_status === 'DUE_TODAY' ? '<span class="sp-ribbon due">DUE</span>'
            : (r.due_status === 'OVERDUE' ? '<span class="sp-ribbon late">LATE</span>' : '');
        return `<tr class="${r.due_status === 'DUE_TODAY' ? 'sp-row-due' : ''}">
            <td class="px-4 py-3 align-middle sp-name-cell">
                ${ribbon}
                <div class="d-flex align-items-center">
                    <div class="hwr-40 bg-light-theme dark-theme d-flex align-items-center justify-content-center fw-medium flex-shrink-0">${spEsc(r.initials)}</div>
                    <div class="ms-2"><div class="text-14 main-text">${spEsc(r.employee)}</div>
                </div>
            </td>
            <td class="py-3 align-middle text-14 main-text text-nowrap">${spEsc(r.month_label)}</td>
            <td class="py-3 align-middle text-nowrap">${spPayDateCell(r)}</td>
            <td class="py-3 align-middle">${spMethodCell(r)}</td>
            <td class="py-3 align-middle text-14 text-dark-green fw-semibold text-end">${spMoney(r.net)}</td>
            <td class="py-3 align-middle text-14 main-text text-end text-nowrap">${p ? spMoney(p.amount) + (short ? ' <iconify-icon icon="mdi:alert-circle-outline" class="text-dark-orange" title="Different from net salary"></iconify-icon>' : '') : '—'}</td>
            <td class="py-3 align-middle text-14 main-text text-nowrap">${p ? spEsc(spFmtDate(p.date)) : '—'}</td>
            <td class="py-3 align-middle"><button class="table-btn ${cls} w-100 text-nowrap">${label}</button></td>
            <td class="py-3 align-middle text-end pe-3">
                <div class="dropdown sp-dropdown">
                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="sub-text cursor-pointer"
                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false"></iconify-icon>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow br-5">
                        ${r.can_add_receipt ? `
                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="spOpenAdd(${r.id}); return false;">
                            <iconify-icon icon="mdi:receipt-text-plus-outline" width="20" class="sub-text me-3"></iconify-icon>Add Receipt</a></li>` : ''}
                        ${p ? `
                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="spOpenView(${r.id}); return false;">
                            <iconify-icon icon="mdi:receipt-text-outline" width="20" class="sub-text me-3"></iconify-icon>View Receipt</a></li>` : ''}
                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="payroll-employee-details?payroll-run-employee-id=${r.id}&department=finance">
                            <iconify-icon icon="solar:eye-broken" width="20" class="sub-text me-3"></iconify-icon>View Paysheet</a></li>
                    </ul>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function spRenderPages() {
    const ul = spEl('sp-pages');
    const { page, pages } = spState;
    let html = '';
    let dots1 = false, dots2 = false;
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || Math.abs(page - i) <= 2) {
            html += `<li class="page-item ${i === page ? 'active' : ''}" id="page-${i}"><button class="py-2 px-3" ${i === page ? '' : `onclick="spLoad(${i})"`}>${i}</button></li>`;
        } else if ((i < page && !dots1) || (i > page && !dots2)) {
            if (i < page) dots1 = true; else dots2 = true;
            html += `<li class="d-flex align-items-end h-100"><iconify-icon icon="bi:three-dots" width="20" height="20" class="sub-text m-0 p-0"></iconify-icon></li>`;
        }
    }
    ul.innerHTML = html;
    spEl('sp-prev').disabled = page <= 1;
    spEl('sp-next').disabled = page >= pages;
}

/* ------------------------------------------- receipt file (fileChooser) */
// fileChooser.js draws the drop zone + file list. We read the chosen file
// from the same input / drop event, so the upload works however it was picked.
function spBindFileChooser() {
    const input = spEl('file-selector-input');
    const drop = spEl('drop-section');
    const list = spEl('list-section');
    if (!input || !drop) return;

    input.addEventListener('change', () => { spState.file = input.files && input.files[0] ? input.files[0] : null; spClearErr('receipt'); }, true);
    drop.addEventListener('drop', (e) => {
        const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) { spState.file = f; spClearErr('receipt'); }
    }, true);
    // If fileChooser's list is emptied (user removed the file), forget it here too.
    if (list && 'MutationObserver' in window) {
        let hadItems = false;
        new MutationObserver(() => {
            if (list.children.length > 0) hadItems = true;
            else if (hadItems) { hadItems = false; spState.file = null; }
        }).observe(list, { childList: true });
    }
}

function spResetFileChooser() {
    spState.file = null;
    const input = spEl('file-selector-input');
    if (input) input.value = '';
    const list = spEl('list-section');
    if (list) list.innerHTML = '';
}

/* -------------------------------------------------------- add receipt */
function spOpenAdd(id) {
    const r = spState.rows.find(x => x.id === id);
    if (!r) return;
    spState.current = r;

    spEl('sp-add-id').value = r.id;
    spEl('sp-add-sub').textContent = `${r.employee} · ${r.month_label}`;
    spEl('sp-add-gross').textContent = spMoney(r.gross);
    spEl('sp-add-ded').textContent = spMoney(r.deductions);
    spEl('sp-add-net').textContent = spMoney(r.net);
    spEl('sp-add-due').textContent = r.due_date ? spFmtDate(r.due_date) : 'Not set';

    const bank = [['Payment Method', r.profile_method], ['Bank', r.bank_name], ['Account No', r.bank_account_no], ['Branch', r.bank_branch]]
        .filter(([, v]) => v)
        .map(([k, v]) => `<div class="d-flex justify-content-between gap-3 py-1"><span class="text-14 sub-text">${k}</span><span class="text-14 main-text fw-medium text-end">${spEsc(v)}</span></div>`)
        .join('');
    spEl('sp-add-bank').innerHTML = bank || '<span class="text-14 sub-text">No payroll profile payment details for this staff member.</span>';

    spEl('sp-amount').value = Number(r.net).toFixed(2);
    spEl('sp-amount').max = Number(r.net).toFixed(2);
    spEl('sp-date').value = spToday();
    spEl('sp-date').max = spToday();
    spEl('sp-date').min = r.period_start || '';
    spEl('sp-method').value = r.profile_method_key || 'BANK_TRANSFER';
    spEl('sp-method-hint').textContent = r.profile_method ? `From payroll profile: ${r.profile_method}` : '';
    spEl('sp-ref').value = '';
    spEl('sp-note').value = '';
    spEl('sp-confirm').checked = false;
    spResetFileChooser();
    spClearErrors();
    spUpdateConfirmText();
    bootstrap.Modal.getOrCreateInstance(spEl('spAddReceiptModal')).show();
}

function spClearErr(field) {
    const el = document.querySelector(`[data-sp-error="${field}"]`);
    if (el) el.textContent = '';
}

function spClearErrors() {
    document.querySelectorAll('[data-sp-error]').forEach(e => e.textContent = '');
    spEl('sp-add-error').textContent = '';
}

function spShowErrors(fields) {
    Object.entries(fields || {}).forEach(([k, v]) => {
        const el = document.querySelector(`[data-sp-error="${k}"]`);
        if (el) el.textContent = v;
    });
}

// Keeps the confirmation sentence + difference warning in sync with the inputs.
function spUpdateConfirmText() {
    const r = spState.current;
    if (!r) return;
    const amount = parseFloat(spEl('sp-amount').value);
    const date = spEl('sp-date').value;
    const diffEl = spEl('sp-amount-diff');
    const differs = !isNaN(amount) && Math.abs(amount - r.net) > 0.009;

    diffEl.classList.toggle('d-none', !differs);
    diffEl.textContent = differs
        ? (amount > r.net ? `More than the net salary (${spMoney(r.net)}) — not allowed.`
            : `${spMoney(r.net - amount)} less than the net salary. A note is required.`)
        : '';
    spEl('sp-note-label').classList.toggle('required', differs);

    spEl('sp-confirm-text').textContent =
        `I confirm that ${isNaN(amount) ? '—' : spMoney(amount)} was paid to ${r.employee} on ${date ? spFmtDate(date) : '—'} ` +
        `for the ${r.month_label} salary, and the attached receipt is correct.`;

    spEl('sp-add-save').disabled = spState.saving || !spEl('sp-confirm').checked;
}

function spValidateClient() {
    spClearErrors();
    const r = spState.current;
    const errs = {};
    const amountRaw = spEl('sp-amount').value.trim();
    const amount = parseFloat(amountRaw);
    if (!/^\d+(\.\d{1,2})?$/.test(amountRaw) || !(amount > 0)) errs.paid_amount = 'Enter the paid amount (a positive number, up to 2 decimals).';
    else if (amount > r.net + 0.001) errs.paid_amount = `Paid amount cannot be more than the net salary (${spMoney(r.net)}).`;
    else if (Math.abs(amount - r.net) > 0.009 && !spEl('sp-note').value.trim()) errs.note = 'The paid amount is different from the net salary. Please add a note explaining why.';

    const date = spEl('sp-date').value;
    if (!date) errs.paid_date = 'Select the date the salary was paid.';
    else if (date > spToday()) errs.paid_date = 'Paid date cannot be in the future.';
    else if (r.period_start && date < r.period_start) errs.paid_date = `Paid date cannot be before the payroll period start (${r.period_start}).`;

    const f = spState.file;
    if (!f) errs.receipt = 'Please upload the payment receipt.';
    else if (f.size > 5 * 1024 * 1024) errs.receipt = 'Receipt file is too large (max 5 MB).';
    else if (!/\.(pdf|jpe?g|png|webp)$/i.test(f.name)) errs.receipt = 'Receipt must be a PDF, JPG, PNG or WEBP file.';

    if (!spEl('sp-confirm').checked) errs.confirm = 'Please confirm the payment details.';
    spShowErrors(errs);
    return Object.keys(errs).length === 0;
}

function spSubmitReceipt() {
    if (spState.saving || !spValidateClient()) return;
    const fd = new FormData();
    fd.append('action', 'add_receipt');
    fd.append('payroll_run_employee_id', spEl('sp-add-id').value);
    fd.append('paid_amount', spEl('sp-amount').value.trim());
    fd.append('paid_date', spEl('sp-date').value);
    fd.append('payment_method', spEl('sp-method').value);
    fd.append('reference_no', spEl('sp-ref').value.trim());
    fd.append('note', spEl('sp-note').value.trim());
    fd.append('confirm', spEl('sp-confirm').checked ? '1' : '0');
    fd.append('receipt', spState.file, spState.file.name);

    const btn = spEl('sp-add-save');
    spState.saving = true;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

    fetch(SP_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.error) {
                spEl('sp-add-error').textContent = d.error;
                spShowErrors(d.fields);
                return;
            }
            spToast('success', d.done);
            bootstrap.Modal.getOrCreateInstance(spEl('spAddReceiptModal')).hide();
            spLoad(spState.page);
        })
        .catch(() => { spEl('sp-add-error').textContent = 'Something went wrong. Please try again.'; })
        .finally(() => {
            spState.saving = false;
            btn.innerHTML = 'Confirm &amp; Submit Receipt';
            spUpdateConfirmText();
        });
}

/* ------------------------------------------------------- view receipt */
function spOpenView(id) {
    const r = spState.rows.find(x => x.id === id);
    if (!r || !r.payment) return;
    const p = r.payment;
    const row = (k, v) => `<div class="d-flex justify-content-between py-2 border-bottom gap-3">
        <span class="text-14 sub-text">${k}</span><span class="text-14 main-text fw-medium text-end">${v}</span></div>`;

    spEl('sp-view-sub').textContent = `${r.employee} · ${r.month_label}`;
    spEl('sp-view-body').innerHTML =
        row('Net Salary', spMoney(r.net)) +
        row('Paid Amount', spMoney(p.amount) + (Math.abs(p.amount - r.net) > 0.009 ? ` <span class="text-12 text-dark-orange">(${spMoney(r.net - p.amount)} less)</span>` : '')) +
        row('Scheduled Pay Date', r.due_date ? spEsc(spFmtDate(r.due_date)) : 'Not set') +
        row('Paid Date', spEsc(spFmtDate(p.date))) +
        row('Payment Method', spEsc(p.method)) +
        row('Reference No', spEsc(p.reference || '—')) +
        row('Receipt File', spEsc(p.file_name)) +
        row('Confirmed By', spEsc(p.confirmed_by || '—')) +
        row('Confirmed At', spEsc(p.confirmed_at || '—')) +
        (p.note ? `<div class="pt-3"><span class="text-14 sub-text d-block mb-1">Note</span><span class="text-14 main-text">${spEsc(p.note)}</span></div>` : '');

    spEl('sp-view-open').href = `api/staffPaymentReceipt.php?id=${p.id}`;
    spEl('sp-view-download').href = `api/staffPaymentReceipt.php?id=${p.id}&download=1`;
    bootstrap.Modal.getOrCreateInstance(spEl('spViewReceiptModal')).show();
}

/* --------------------------------------------------------------- init */
function initializeStaffPayments() {
    spEl('skeleton-list')?.classList.add('d-none');
    spEl('real-list')?.classList.remove('d-none');
    if (typeof initializeLanguage === 'function') { try { initializeLanguage(); } catch (e) { } }

    let t = null;
    spEl('sp-search').addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => spLoad(1), 300); });
    ['sp-status', 'sp-run', 'sp-method-filter', 'sp-date-from', 'sp-date-to', 'sp-per-page']
        .forEach(id => spEl(id).addEventListener('change', () => spLoad(1)));
    spEl('sp-prev').addEventListener('click', () => { if (spState.page > 1) spLoad(spState.page - 1); });
    spEl('sp-next').addEventListener('click', () => { if (spState.page < spState.pages) spLoad(spState.page + 1); });

    if (spEl('spAddReceiptModal')) {
        ['sp-amount', 'sp-date', 'sp-note'].forEach(id => spEl(id).addEventListener('input', spUpdateConfirmText));
        spEl('sp-confirm').addEventListener('change', spUpdateConfirmText);
        spBindFileChooser();
    }
    spLoad(1);
}
