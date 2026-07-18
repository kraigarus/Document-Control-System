const CATEGORIES = window.CATEGORIES;

let currentCategory = window.ACTIVE_CATEGORY || null;
let currentSub = null;
let lastGeneratedParams = null;
let isGenerating = false;
let selectedRows = new Set();
let lastRenderedRowCount = 0;

const $ = (id) => document.getElementById(id);
const categoryCards  = $('categoryCards');
const subTabs        = $('subTabs');
const filterBar      = $('filterBar');
const generateBtn    = $('generateBtn');
const resetBtn       = $('resetBtn');
const resultsPanel   = $('resultsPanel');
const resultsTitle   = $('resultsTitle');
const resultsCount   = $('resultsCount');
const reportHead     = $('reportHead');
const reportBody     = $('reportBody');
const reportTable    = $('reportTable');
const exportDropdown = $('exportDropdown');
const exportBtn      = $('exportBtn');
const exportMenu     = $('exportMenu');
const dateFromInput  = $('filterDateFrom');
const dateToInput    = $('filterDateTo');

// ═══════════════════════════════════════════
// DATE RANGE PRESETS
// ═══════════════════════════════════════════
const presetBtns = document.querySelectorAll('.rpt-preset');

function getISO(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function applyPreset(months) {
    const today = new Date();
    if (months === 0) {
        dateFromInput.value = '';
        dateToInput.value = '';
    } else {
        const from = new Date();
        from.setMonth(from.getMonth() - months);
        dateFromInput.value = getISO(from);
        dateToInput.value = getISO(today);
    }
}

applyPreset(6);

presetBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        presetBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        applyPreset(parseInt(btn.dataset.months, 10));
    });
});

dateFromInput.addEventListener('input', () => {
    presetBtns.forEach(b => b.classList.remove('active'));
});
dateToInput.addEventListener('input', () => {
    presetBtns.forEach(b => b.classList.remove('active'));
});

// ═══════════════════════════════════════════
// AUTO-SELECT CATEGORY (from window.ACTIVE_CATEGORY)
// ═══════════════════════════════════════════
if (currentCategory && CATEGORIES[currentCategory]) {
    renderSubs();
    filterBar.classList.add('visible');
}

// ═══════════════════════════════════════════
// CATEGORY SELECTION (kept for backward compat)
// ═══════════════════════════════════════════
categoryCards.addEventListener('click', (e) => {
    const card = e.target.closest('.rpt-cat');
    if (!card) return;

    document.querySelectorAll('.rpt-cat').forEach(c => c.classList.remove('active'));
    card.classList.add('active');

    currentCategory = card.dataset.category;
    currentSub = null;
    lastGeneratedParams = null;
    selectedRows.clear();

    renderSubs();
    filterBar.classList.add('visible');
    resultsPanel.classList.remove('visible');
});

// ═══════════════════════════════════════════
// SUBCATEGORY TABS
// ═══════════════════════════════════════════
function renderSubs() {
    const cat = CATEGORIES[currentCategory];
    subTabs.innerHTML = '';

    if (!cat || Object.keys(cat.subs).length <= 1) {
        subTabs.classList.remove('visible');
        currentSub = cat ? Object.keys(cat.subs)[0] : null;
        return;
    }

    subTabs.classList.add('visible');

    Object.entries(cat.subs).forEach(([key, label]) => {
        const tab = document.createElement('button');
        tab.className = 'rpt-sub';
        tab.type = 'button';
        tab.dataset.sub = key;
        tab.textContent = label;
        tab.addEventListener('click', () => {
            document.querySelectorAll('.rpt-sub').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            currentSub = key;
            lastGeneratedParams = null;
            selectedRows.clear();
            resultsPanel.classList.remove('visible');
        });
        subTabs.appendChild(tab);
    });

    const first = subTabs.querySelector('.rpt-sub');
    if (first) {
        first.classList.add('active');
        currentSub = first.dataset.sub;
    }
}

// ═══════════════════════════════════════════
// BUILD PARAMS
// ═══════════════════════════════════════════
function buildParams() {
    const params = new URLSearchParams();
    params.set('category', currentCategory);
    if (currentSub) params.set('sub', currentSub);
    const from = dateFromInput.value;
    const to   = dateToInput.value;
    if (from) params.set('date_from', from);
    if (to)   params.set('date_to', to);
    return params;
}

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
// GENERATE REPORT
// ═══════════════════════════════════════════
generateBtn.addEventListener('click', async () => {
    if (isGenerating) return;
    if (!currentCategory) {
        showToast('error', 'Please select a report category first.');
        return;
    }

    const params = buildParams();

    isGenerating = true;
    generateBtn.disabled = true;
    selectedRows.clear();

    resultsPanel.classList.add('visible');
    resultsTitle.textContent = 'Loading...';
    resultsCount.textContent = '';
    reportHead.innerHTML = '';
    reportBody.innerHTML =
        '<tr><td colspan="20"><div class="rpt-state">' +
        '<div class="rpt-state-spinner"></div>' +
        '<h4 style="margin-top:18px;">Generating report...</h4>' +
        '<p>Please wait while we compile the data</p>' +
        '</div></td></tr>';

    try {
        const res = await fetch('/reports/data?' + params.toString());
        const json = await res.json();

        if (json.error) {
            reportBody.innerHTML =
                '<tr><td colspan="20"><div class="rpt-state">' +
                '<div class="rpt-state-icon state-error"><i class="fa-solid fa-circle-exclamation"></i></div>' +
                '<h4>Error</h4>' +
                '<p>' + esc(json.error) + '</p>' +
                '</div></td></tr>';
            resultsTitle.textContent = 'Error';
            return;
        }

        resultsTitle.textContent = json.title || 'Report';
        lastRenderedRowCount = json.total_rows || 0;
        updateSelectionCount();

        const cols = json.columns;
        const colKeys = Object.keys(cols);

        // Build header — support two-row headers (sub_headers)
        const subHeaders = json.sub_headers || {};
        const hasSubHeaders = Object.keys(subHeaders).length > 0;

        let row1 = '<tr>';
        row1 += '<th class="rpt-th-check"' + (hasSubHeaders ? ' rowspan="2"' : '') + '>';
        row1 += '<input type="checkbox" id="selectAllRows" title="Select all"></th>';

        colKeys.forEach(key => {
            if (hasSubHeaders) {
                if (subHeaders[key]) {
                    // This column HAS a sub-header — single row, no rowspan
                    row1 += '<th>' + esc(cols[key]) + '</th>';
                } else {
                    // This column has NO sub-header — spans both rows
                    row1 += '<th rowspan="2">' + esc(cols[key]) + '</th>';
                }
            } else {
                row1 += '<th>' + esc(cols[key]) + '</th>';
            }
        });
        row1 += '</tr>';

        let row2 = '';
        if (hasSubHeaders) {
            row2 = '<tr>';
            colKeys.forEach(key => {
                if (subHeaders[key]) {
                    // Only output <th> for columns that have sub-headers
                    row2 += '<th>' + esc(subHeaders[key]) + '</th>';
                }
            });
            row2 += '</tr>';
        }

        reportHead.innerHTML = row1 + row2;

        if (!json.rows || json.rows.length === 0) {
            reportBody.innerHTML =
                '<tr><td colspan="' + (colKeys.length + 1) + '"><div class="rpt-state">' +
                '<div class="rpt-state-icon"><i class="fa-solid fa-inbox"></i></div>' +
                '<h4>No records found</h4>' +
                '<p>Try adjusting your filters or date range</p>' +
                '</div></td></tr>';
            lastGeneratedParams = params.toString();
            return;
        }

        reportBody.innerHTML = json.rows.map((row, i) => {
            const checkCell = '<td class="rpt-td-check">' +
                '<input type="checkbox" class="rpt-row-check" data-row-index="' + i + '"></td>';
            return '<tr data-row-index="' + i + '">' + checkCell + colKeys.map(key => {
                const val = row[key];
                if (key === 'pdf_path' && val) {
                    return '<td><a href="' + esc(val) + '" target="_blank"><i class="fa-solid fa-file-pdf"></i> View</a></td>';
                }
                if (val === null || val === undefined || val === '') {
                    return '<td class="rpt-na">&mdash;</td>';
                }
                return '<td>' + esc(val) + '</td>';
            }).join('') + '</tr>';
        }).join('');

        lastGeneratedParams = params.toString();

    } catch (e) {
        console.error(e);
        reportBody.innerHTML =
            '<tr><td colspan="20"><div class="rpt-state">' +
            '<div class="rpt-state-icon state-error"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
            '<h4>Connection error</h4>' +
            '<p>Failed to load report data.</p>' +
            '</div></td></tr>';
        resultsTitle.textContent = 'Error';
    } finally {
        isGenerating = false;
        generateBtn.disabled = false;
    }
});

// ═══════════════════════════════════════════
// ROW SELECTION
// ═══════════════════════════════════════════
function updateSelectionCount() {
    if (selectedRows.size > 0) {
        resultsCount.textContent = selectedRows.size + ' of ' + lastRenderedRowCount + ' selected';
    } else {
        resultsCount.textContent = lastRenderedRowCount + ' record' + (lastRenderedRowCount !== 1 ? 's' : '');
    }
}

function syncSelectAllState() {
    const selectAll = document.getElementById('selectAllRows');
    if (!selectAll) return;
    const total = reportBody.querySelectorAll('.rpt-row-check').length;
    selectAll.checked = total > 0 && selectedRows.size === total;
    selectAll.indeterminate = selectedRows.size > 0 && selectedRows.size < total;
}

reportHead.addEventListener('change', (e) => {
    if (e.target.id !== 'selectAllRows') return;
    const checked = e.target.checked;
    const rowChecks = reportBody.querySelectorAll('.rpt-row-check');
    rowChecks.forEach(cb => {
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

reportBody.addEventListener('change', (e) => {
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

reportBody.addEventListener('click', (e) => {
    if (e.target.closest('.rpt-row-check') || e.target.closest('a')) return;
    const tr = e.target.closest('tr[data-row-index]');
    if (!tr) return;
    const cb = tr.querySelector('.rpt-row-check');
    if (!cb) return;
    cb.checked = !cb.checked;
    cb.dispatchEvent(new Event('change', { bubbles: true }));
});

// ═══════════════════════════════════════════
// EXPORT DROPDOWN
// ═══════════════════════════════════════════
exportBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    if (exportMenu.classList.contains('open')) {
        exportMenu.classList.remove('open');
        exportDropdown.classList.remove('open');
    } else {
        exportMenu.classList.add('open');
        exportDropdown.classList.add('open');
    }
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
        showToast('error', 'Generate a report first before exporting.');
        return;
    }

    let url = '/reports/export?' + lastGeneratedParams + '&format=' + format;
    const hasSelection = selectedRows.size > 0;
    const selectionNote = hasSelection ? ' (' + selectedRows.size + ' selected)' : '';

    if (hasSelection) {
        const indices = Array.from(selectedRows).sort((a, b) => a - b).join(',');
        url += '&rows=' + indices;
    } else {
        url += '&rows=none';
    }

    if (format === 'print') {
        showToast('success', 'Opening print view...' + selectionNote);
        const printUrl = url.replace('format=pdf', 'format=html') + '&autoPrint=1';
        window.open(printUrl, '_blank');
        return;
    }

    if (format === 'pdf') {
        showToast('success', 'Opening print view...' + selectionNote);
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
// RESET
// ═══════════════════════════════════════════
resetBtn.addEventListener('click', () => {
    currentSub = null;
    lastGeneratedParams = null;
    selectedRows.clear();
    lastRenderedRowCount = 0;
    resultsPanel.classList.remove('visible');
    exportDropdown.classList.remove('open');
    exportMenu.classList.remove('open');

    // Re-render subs (resets to first tab)
    renderSubs();

    // Reset presets to 6-month default
    presetBtns.forEach(b => b.classList.remove('active'));
    const defaultPreset = document.querySelector('.rpt-preset[data-months="6"]');
    if (defaultPreset) defaultPreset.classList.add('active');
    applyPreset(6);
});

// ═══════════════════════════════════════════
// UTILITIES
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