//reports.js
// Handles ALL report categories: masterlist (default), monitoring, opcr.
// Category-specific behavior is branched on REPORT_CATEGORY instead of
// living in separate monitoring.js / opcr.js files.
import { initFilterPanel } from './report-filter';

const REPORT_CATEGORY = window.REPORT_CATEGORY; // 'masterlist' | 'monitoring' | 'opcr' | ...

// ═══════════════════════════════════════════
// CATEGORY CONFIG
// ═══════════════════════════════════════════
const isOpcr = REPORT_CATEGORY === 'opcr';
// OPCR rows are inline-editable (rating inputs) and are not row-selectable/exportable-by-selection.
const isSelectable = !isOpcr;
const RATING_KEYS = ['rating_q', 'rating_e', 'rating_t', 'rating_a'];

const $ = (id) => document.getElementById(id);
// Falls back to the old category-prefixed IDs so this works even before
// the monitoring/opcr blade views are updated to the generic IDs.
const pick = (...ids) => ids.map($).find(Boolean) || null;

const subTabs        = pick('subTabs', 'monSubTabs', 'opcrSubTabs');
const resultsPanel   = pick('resultsPanel'); // masterlist only; monitoring/opcr tables are always visible
const title          = pick('resultsTitle', 'monTitle', 'opcrTitle');
const count          = pick('resultsCount', 'monCount', 'opcrCount');
const head           = pick('reportHead', 'monHead', 'opcrHead');
const body           = pick('reportBody', 'monBody', 'opcrBody');
const exportDropdown = $('exportDropdown');
const exportBtn      = $('exportBtn');
const exportMenu     = $('exportMenu');

const filters = initFilterPanel({
    onApply: () => loadReport(),
    onReset: () => loadReport(),
});

let currentSub = subTabs?.querySelector('.rpt-sub.active')?.dataset.sub
    || subTabs?.querySelector('.rpt-sub')?.dataset.sub
    || null;
let lastGeneratedParams = null;
let lastRenderedRowCount = 0;
let selectedRows = new Set();
let isLoading = false;

// ═══════════════════════════════════════════
// SUB-TAB CLICK — auto-load
// ═══════════════════════════════════════════
if (subTabs) {
    subTabs.addEventListener('click', (e) => {
        const btn = e.target.closest('.rpt-sub');
        if (!btn || isLoading || btn.classList.contains('active')) return;

        subTabs.querySelectorAll('.rpt-sub').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        currentSub = btn.dataset.sub;
        selectedRows.clear();
        lastGeneratedParams = null;

        loadReport();
    });
}

// ═══════════════════════════════════════════
// BUILD PARAMS
// ═══════════════════════════════════════════
function buildParams() {
    const params = new URLSearchParams();
    params.set('category', REPORT_CATEGORY);
    if (currentSub) params.set('sub', currentSub);
    filters.apply(params);
    return params;
}

// ═══════════════════════════════════════════
// LOAD REPORT
// ═══════════════════════════════════════════
async function loadReport() {
    if (isLoading) return;
    if (!REPORT_CATEGORY) {
        showToast('error', 'Report category is not configured for this page.');
        return;
    }

    isLoading = true;
    selectedRows.clear();
    resultsPanel?.classList.add('visible');

    title.textContent = 'Loading...';
    count.textContent = '';
    head.innerHTML = '';
    body.innerHTML =
        '<tr><td colspan="20"><div class="rpt-state">' +
        '<div class="rpt-state-spinner"></div>' +
        '<h4 style="margin-top:18px;">Loading report...</h4>' +
        '</div></td></tr>';

    const params = buildParams();

    try {
        const res = await fetch('/reports/data?' + params.toString());
        const json = await res.json();

        if (json.error) {
            body.innerHTML =
                '<tr><td colspan="20"><div class="rpt-state">' +
                '<div class="rpt-state-icon state-error"><i class="fa-solid fa-circle-exclamation"></i></div>' +
                '<h4>Error</h4>' +
                '<p>' + esc(json.error) + '</p>' +
                '</div></td></tr>';
            title.textContent = 'Error';
            return;
        }

        title.textContent = json.title || 'Report';
        lastRenderedRowCount = json.total_rows || 0;
        updateSelectionCount();

        const cols = json.columns;
        const colKeys = Object.keys(cols);
        const groupHeaders = json.group_headers || {};
        const hasGroups = Object.keys(groupHeaders).length > 0;

        // ── Build header ──
        if (hasGroups) {
            let row1 = '<tr>';
            if (isSelectable) {
                row1 += '<th class="rpt-th-check" rowspan="2">' +
                    '<input type="checkbox" id="selectAllRows" title="Select all"></th>';
            }

            let i = 0;
            while (i < colKeys.length) {
                const key = colKeys[i];
                const group = groupHeaders[key];

                if (group === null || group === undefined) {
                    row1 += '<th rowspan="2">' + esc(cols[key]) + '</th>';
                    i++;
                } else {
                    let span = 0;
                    let j = i;
                    while (j < colKeys.length && groupHeaders[colKeys[j]] === group) {
                        span++;
                        j++;
                    }
                    const groupClass = isOpcr ? ' class="opcr-rating-th"' : '';
                    row1 += '<th colspan="' + span + '"' + groupClass + '>' + esc(group) + '</th>';
                    i = j;
                }
            }
            row1 += '</tr>';

            let row2 = '<tr>';
            colKeys.forEach(key => {
                if (groupHeaders[key] !== null && groupHeaders[key] !== undefined) {
                    const subClass = isOpcr ? ' class="opcr-rating-th"' : '';
                    row2 += '<th' + subClass + '>' + esc(cols[key]) + '</th>';
                }
            });
            row2 += '</tr>';

            head.innerHTML = row1 + row2;
        } else {
            let row1 = '<tr>';
            if (isSelectable) {
                row1 += '<th class="rpt-th-check"><input type="checkbox" id="selectAllRows" title="Select all"></th>';
            }
            colKeys.forEach(key => {
                row1 += '<th>' + esc(cols[key]) + '</th>';
            });
            row1 += '</tr>';
            head.innerHTML = row1;
        }

        // ── Build body ──
        const checkColspan = isSelectable ? 1 : 0;
        if (!json.rows || json.rows.length === 0) {
            body.innerHTML =
                '<tr><td colspan="' + (colKeys.length + checkColspan) + '"><div class="rpt-state">' +
                '<div class="rpt-state-icon"><i class="fa-solid fa-inbox"></i></div>' +
                '<h4>No records found</h4>' +
                '<p>Try adjusting your filters or date range</p>' +
                '</div></td></tr>';
            lastGeneratedParams = params.toString();
            return;
        }

        body.innerHTML = json.rows.map((row, i) => {
            const checkCell = isSelectable
                ? '<td class="rpt-td-check"><input type="checkbox" class="rpt-row-check" data-row-index="' + i + '"></td>'
                : '';
            const cells = colKeys.map(key => renderCell(key, row[key], row)).join('');
            return '<tr data-row-index="' + i + '">' + checkCell + cells + '</tr>';
        }).join('');

        lastGeneratedParams = params.toString();

    } catch (e) {
        console.error(e);
        body.innerHTML =
            '<tr><td colspan="20"><div class="rpt-state">' +
            '<div class="rpt-state-icon state-error"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
            '<h4>Connection error</h4>' +
            '<p>Failed to load report data.</p>' +
            '</div></td></tr>';
        title.textContent = 'Error';
    } finally {
        isLoading = false;
    }
}

// ═══════════════════════════════════════════
// CELL RENDERING (per-category)
// ═══════════════════════════════════════════
function renderCell(key, val, row) {
    if (isOpcr) {
        if (RATING_KEYS.includes(key)) {
            return '<td class="opcr-rating-td">' +
                '<input type="number" class="opcr-rating-input" ' +
                'data-request-id="' + row.request_id + '" ' +
                'data-field="' + key + '" ' +
                'data-sub="' + currentSub + '" ' +
                'min="0" max="10" step="0.01" ' +
                'value="' + (val !== null && val !== undefined ? esc(String(val)) : '') + '" ' +
                'placeholder="0"></td>';
        }
        if (key === 'days_diff') {
            if (val === null || val === undefined) return '<td class="rpt-na">&mdash;</td>';
            return row.days_type === 'advanced'
                ? '<td class="opcr-days-advanced">+' + esc(String(val)) + '</td>'
                : '<td class="opcr-days-delayed">-' + esc(String(val)) + '</td>';
        }
    }

    if (key === 'pdf_path' && val) {
        return '<td><a href="' + esc(val) + '" target="_blank"><i class="fa-solid fa-file-pdf"></i> View</a></td>';
    }
    if (val === null || val === undefined || val === '') {
        return '<td class="rpt-na">&mdash;</td>';
    }
    return '<td>' + esc(val) + '</td>';
}

// ═══════════════════════════════════════════
// ROW SELECTION (masterlist / monitoring only)
// ═══════════════════════════════════════════
function updateSelectionCount() {
    if (!isSelectable) {
        count.textContent = lastRenderedRowCount + ' record' + (lastRenderedRowCount !== 1 ? 's' : '');
        return;
    }
    count.textContent = selectedRows.size > 0
        ? selectedRows.size + ' of ' + lastRenderedRowCount + ' selected'
        : lastRenderedRowCount + ' record' + (lastRenderedRowCount !== 1 ? 's' : '');
}

function syncSelectAllState() {
    const selectAll = document.getElementById('selectAllRows');
    if (!selectAll) return;
    const total = body.querySelectorAll('.rpt-row-check').length;
    selectAll.checked = total > 0 && selectedRows.size === total;
    selectAll.indeterminate = selectedRows.size > 0 && selectedRows.size < total;
}

if (isSelectable) {
    head.addEventListener('change', (e) => {
        if (e.target.id !== 'selectAllRows') return;
        const checked = e.target.checked;
        body.querySelectorAll('.rpt-row-check').forEach(cb => {
            cb.checked = checked;
            const idx = parseInt(cb.dataset.rowIndex, 10);
            const tr = cb.closest('tr');
            if (checked) {
                selectedRows.add(idx);
                if (tr) tr.classList.add('rpt-row-selected');
            } else {
                selectedRows.delete(idx);
                if (tr) tr.classList.remove('rpt-row-selected');
            }
        });
        updateSelectionCount();
    });

    body.addEventListener('change', (e) => {
        const cb = e.target.closest('.rpt-row-check');
        if (!cb) return;
        const idx = parseInt(cb.dataset.rowIndex, 10);
        const tr = cb.closest('tr');
        if (cb.checked) {
            selectedRows.add(idx);
            if (tr) tr.classList.add('rpt-row-selected');
        } else {
            selectedRows.delete(idx);
            if (tr) tr.classList.remove('rpt-row-selected');
        }
        syncSelectAllState();
        updateSelectionCount();
    });

    body.addEventListener('click', (e) => {
        if (e.target.closest('.rpt-row-check') || e.target.closest('a')) return;
        const tr = e.target.closest('tr[data-row-index]');
        if (!tr) return;
        const cb = tr.querySelector('.rpt-row-check');
        if (!cb) return;
        cb.checked = !cb.checked;
        cb.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

// ═══════════════════════════════════════════
// OPCR — INLINE RATING SAVE (opcr only)
// ═══════════════════════════════════════════
if (isOpcr) {
    body.addEventListener('focusout', async (e) => {
        const input = e.target.closest('.opcr-rating-input');
        if (!input) return;

        const requestId = input.dataset.requestId;
        const sub       = input.dataset.sub;
        const row       = input.closest('tr');
        const allInputs = row.querySelectorAll('.opcr-rating-input');

        const data = { request_id: requestId, sub: sub };
        allInputs.forEach(inp => {
            data[inp.dataset.field] = inp.value || null;
        });

        try {
            const res = await fetch('/reports/opcr/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            });

            input.style.borderColor = res.ok ? '#059669' : '#dc2626';
            setTimeout(() => { input.style.borderColor = ''; }, 1500);
        } catch (err) {
            console.error('Save failed:', err);
        }
    });

    body.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.classList.contains('opcr-rating-input')) {
            e.target.blur(); // triggers focusout which saves
        }
    });
}

// ═══════════════════════════════════════════
// EXPORT
// ═══════════════════════════════════════════
exportBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    exportMenu.classList.toggle('open');
    exportDropdown.classList.toggle('open');
});

document.addEventListener('click', function (e) {
    if (!exportDropdown.contains(e.target)) {
        exportMenu.classList.remove('open');
        exportDropdown.classList.remove('open');
    }
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        exportMenu.classList.remove('open');
        exportDropdown.classList.remove('open');
    }
});

exportMenu.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-format]');
    if (!btn) return;

    const format = btn.dataset.format;
    exportMenu.classList.remove('open');
    exportDropdown.classList.remove('open');

    if (!lastGeneratedParams) {
        showToast('error', 'Wait for the report to load before exporting.');
        return;
    }

    let url = '/reports/export?' + lastGeneratedParams + '&format=' + format;
    const hasSelection = isSelectable && selectedRows.size > 0;
    const selectionNote = hasSelection ? ' (' + selectedRows.size + ' selected)' : '';

    if (hasSelection) {
        const indices = Array.from(selectedRows).sort((a, b) => a - b).join(',');
        url += '&rows=' + indices;
    } else {
        url += '&rows=none';
    }

    if (format === 'print') {
        showToast('success', 'Opening print view...' + selectionNote);
        window.open(url.replace('format=pdf', 'format=html') + '&autoPrint=1', '_blank');
        return;
    }
    if (format === 'pdf') {
        showToast('success', 'Opening PDF...' + selectionNote);
        window.open(url, '_blank');
        return;
    }
    if (format === 'xlsx') {
        showToast('success', 'Downloading Excel file...' + selectionNote);
        const a = document.createElement('a');
        a.href = url;
        a.download = '';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => a.remove(), 1000);
    }
});

// ═══════════════════════════════════════════
// TOAST
// ═══════════════════════════════════════════
function showToast(type, message) {
    const existing = document.querySelector('.rpt-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'rpt-toast toast-' + type;
    const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
    toast.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'rptToastOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// ═══════════════════════════════════════════
// UTILS
// ═══════════════════════════════════════════
function esc(str) {
    if (str === null || str === undefined) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

// ═══════════════════════════════════════════
// SIDEBAR SYNC
// ═══════════════════════════════════════════
const sideNav = document.getElementById('sideNav');
const rptPage = document.getElementById('rptPage');

function syncSidebar() {
    if (!sideNav || !rptPage) return;
    rptPage.style.left = sideNav.classList.contains('collapsed') ? '68px' : '280px';
}

if (sideNav) {
    new MutationObserver(syncSidebar).observe(sideNav, { attributes: true, attributeFilter: ['class'] });
    syncSidebar();
}

// ═══════════════════════════════════════════
// INITIAL LOAD
// ═══════════════════════════════════════════
loadReport();