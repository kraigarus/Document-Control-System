const $ = (id) => document.getElementById(id);

const subTabs   = $('monSubTabs');
const title     = $('monTitle');
const count     = $('monCount');
const head      = $('monHead');
const body      = $('monBody');
const exportDropdown = $('exportDropdown');
const exportBtn = $('exportBtn');
const exportMenu = $('exportMenu');

let currentSub = 'internal_docs';
let lastGeneratedParams = null;
let selectedRows = new Set();
let lastRenderedRowCount = 0;
let isLoading = false;

// ═══════════════════════════════════════════
// SUB-TAB CLICK — auto-load
// ═══════════════════════════════════════════
subTabs.addEventListener('click', (e) => {
    const btn = e.target.closest('.rpt-sub');
    if (!btn || isLoading) return;

    subTabs.querySelectorAll('.rpt-sub').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    currentSub = btn.dataset.sub;
    selectedRows.clear();
    lastGeneratedParams = null;

    loadReport();
});

// ═══════════════════════════════════════════
// LOAD REPORT
// ═══════════════════════════════════════════
async function loadReport() {
    isLoading = true;
    selectedRows.clear();

    title.textContent = 'Loading...';
    count.textContent = '';
    head.innerHTML = '';
    body.innerHTML =
        '<tr><td colspan="20"><div class="rpt-state">' +
        '<div class="rpt-state-spinner"></div>' +
        '<h4 style="margin-top:18px;">Loading report...</h4>' +
        '</div></td></tr>';

    const params = new URLSearchParams();
    params.set('category', 'monitoring');
    params.set('sub', currentSub);

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

        title.textContent = json.title || 'Monitoring Report';
        lastRenderedRowCount = json.total_rows || 0;
        count.textContent = lastRenderedRowCount + ' record' + (lastRenderedRowCount !== 1 ? 's' : '');

        const cols = json.columns;
        const colKeys = Object.keys(cols);
        const subHeaders = json.sub_headers || {};
        const hasSubHeaders = Object.keys(subHeaders).length > 0;

                // ── Build header ──
        const groupHeaders = json.group_headers || {};
        const hasGroups = Object.keys(groupHeaders).length > 0;

        if (hasGroups) {
            // ── Two-row grouped header ──
            let row1 = '<tr>';
            row1 += '<th class="rpt-th-check" rowspan="2">';
            row1 += '<input type="checkbox" id="selectAllRows" title="Select all"></th>';

            let i = 0;
            while (i < colKeys.length) {
                const key = colKeys[i];
                const group = groupHeaders[key];

                if (group === null || group === undefined) {
                    // Standalone column — spans both rows
                    row1 += '<th rowspan="2">' + esc(cols[key]) + '</th>';
                    i++;
                } else {
                    // Grouped — count consecutive columns in same group
                    let span = 0;
                    let j = i;
                    while (j < colKeys.length && groupHeaders[colKeys[j]] === group) {
                        span++;
                        j++;
                    }
                    row1 += '<th colspan="' + span + '">' + esc(group) + '</th>';
                    i = j;
                }
            }
            row1 += '</tr>';

            // Row 2: sub-labels for grouped columns only
            let row2 = '<tr>';
            colKeys.forEach(key => {
                if (groupHeaders[key] !== null && groupHeaders[key] !== undefined) {
                    row2 += '<th>' + esc(cols[key]) + '</th>';
                }
            });
            row2 += '</tr>';

            head.innerHTML = row1 + row2;
        } else {
            // ── Simple single-row header ──
            let row1 = '<tr>';
            row1 += '<th class="rpt-th-check">';
            row1 += '<input type="checkbox" id="selectAllRows" title="Select all"></th>';
            colKeys.forEach(key => {
                row1 += '<th>' + esc(cols[key]) + '</th>';
            });
            row1 += '</tr>';

            head.innerHTML = row1;
        }

        // ── Build body ──
        if (!json.rows || json.rows.length === 0) {
            body.innerHTML =
                '<tr><td colspan="' + (colKeys.length + 1) + '"><div class="rpt-state">' +
                '<div class="rpt-state-icon"><i class="fa-solid fa-inbox"></i></div>' +
                '<h4>No records found</h4>' +
                '<p>No documents found for this category</p>' +
                '</div></td></tr>';
            lastGeneratedParams = params.toString();
            return;
        }

        body.innerHTML = json.rows.map((row, i) => {
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
// ROW SELECTION
// ═══════════════════════════════════════════
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
// AUTO-LOAD on page open
// ═══════════════════════════════════════════
loadReport();