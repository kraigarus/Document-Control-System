import { initFilterPanel } from './report-filter';

const filters = initFilterPanel({
    onApply: () => loadReport(),
    onReset: () => loadReport(),
});

const $ = (id) => document.getElementById(id);

const subTabs   = $('opcrSubTabs');
const title     = $('opcrTitle');
const count     = $('opcrCount');
const head      = $('opcrHead');
const body      = $('opcrBody');
const exportDropdown = $('exportDropdown');
const exportBtn = $('exportBtn');
const exportMenu = $('exportMenu');

let currentSub = 'update_masterlist';
let lastGeneratedParams = null;
let isLoading = false;

// ═══════════════════════════════════════════
// SUB-TAB CLICK
// ═══════════════════════════════════════════
subTabs.addEventListener('click', (e) => {
    const btn = e.target.closest('.rpt-sub');
    if (!btn || isLoading) return;

    subTabs.querySelectorAll('.rpt-sub').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentSub = btn.dataset.sub;
    lastGeneratedParams = null;
    loadReport();
});

// ═══════════════════════════════════════════
// LOAD REPORT
// ═══════════════════════════════════════════
async function loadReport() {
    isLoading = true;
    title.textContent = 'Loading...';
    count.textContent = '';
    head.innerHTML = '';
    body.innerHTML =
        '<tr><td colspan="20"><div class="rpt-state">' +
        '<div class="rpt-state-spinner"></div>' +
        '<h4 style="margin-top:18px;">Loading report...</h4>' +
        '</div></td></tr>';

    const params = new URLSearchParams();
    params.set('category', 'opcr');
    params.set('sub', currentSub);
    filters.apply(params);

    try {
        const res = await fetch('/reports/data?' + params.toString());
        const json = await res.json();

        if (json.error) {
            body.innerHTML = '<tr><td colspan="20"><div class="rpt-state">' +
                '<div class="rpt-state-icon state-error"><i class="fa-solid fa-circle-exclamation"></i></div>' +
                '<h4>Error</h4><p>' + esc(json.error) + '</p></div></td></tr>';
            title.textContent = 'Error';
            return;
        }

        title.textContent = json.title || 'OPCR Targets';
        const totalRows = json.total_rows || 0;
        count.textContent = totalRows + ' record' + (totalRows !== 1 ? 's' : '');

        const cols = json.columns;
        const colKeys = Object.keys(cols);
        const ratingKeys = ['rating_q', 'rating_e', 'rating_t', 'rating_a'];

        // ── Build header ──
        const groupHeaders = json.group_headers || {};
        const hasGroups = Object.keys(groupHeaders).length > 0;

        let headerHtml = '';

        if (hasGroups) {
            // Row 1: group headers
            headerHtml += '<tr>';
            let i = 0;
            while (i < colKeys.length) {
                const key = colKeys[i];
                const group = groupHeaders[key];

                if (group === null || group === undefined) {
                    headerHtml += '<th rowspan="2">' + esc(cols[key]) + '</th>';
                    i++;
                } else {
                    let span = 0;
                    let j = i;
                    while (j < colKeys.length && groupHeaders[colKeys[j]] === group) {
                        span++;
                        j++;
                    }
                    headerHtml += '<th colspan="' + span + '" class="opcr-rating-th">' + esc(group) + '</th>';
                    i = j;
                }
            }
            headerHtml += '</tr>';

            // Row 2: sub-labels for grouped columns
            headerHtml += '<tr>';
            colKeys.forEach(key => {
                if (groupHeaders[key] !== null && groupHeaders[key] !== undefined) {
                    headerHtml += '<th class="opcr-rating-th">' + esc(cols[key]) + '</th>';
                }
            });
            headerHtml += '</tr>';
        } else {
            headerHtml += '<tr>';
            colKeys.forEach(key => {
                headerHtml += '<th>' + esc(cols[key]) + '</th>';
            });
            headerHtml += '</tr>';
        }

        head.innerHTML = headerHtml;

        // ── Build body ──
        if (!json.rows || json.rows.length === 0) {
            body.innerHTML = '<tr><td colspan="' + colKeys.length + '"><div class="rpt-state">' +
                '<div class="rpt-state-icon"><i class="fa-solid fa-inbox"></i></div>' +
                '<h4>No records found</h4>' +
                '<p>Try adjusting your filters or date range</p></div></td></tr>';
            lastGeneratedParams = params.toString();
            return;
        }

        body.innerHTML = json.rows.map((row, i) => {
            let cells = '';
            colKeys.forEach(key => {
                const val = row[key];

                // Rating columns — render as input
                if (ratingKeys.includes(key)) {
                    cells += '<td class="opcr-rating-td">' +
                        '<input type="number" class="opcr-rating-input" ' +
                        'data-request-id="' + row.request_id + '" ' +
                        'data-field="' + key + '" ' +
                        'data-sub="' + currentSub + '" ' +
                        'min="0" max="10" step="0.01" ' +
                        'value="' + (val !== null && val !== undefined ? esc(String(val)) : '') + '" ' +
                        'placeholder="0"></td>';
                    return;
                }

                // Days advanced/delayed — show with color
                if (key === 'days_diff') {
                    if (val !== null && val !== undefined) {
                        if (row.days_type === 'advanced') {
                            cells += '<td class="opcr-days-advanced">+' + esc(String(val)) + '</td>';
                        } else {
                            cells += '<td class="opcr-days-delayed">-' + esc(String(val)) + '</td>';
                        }
                    } else {
                        cells += '<td class="rpt-na">&mdash;</td>';
                    }
                    return;
                }

                // PDF link
                if (key === 'pdf_path' && val) {
                    cells += '<td><a href="' + esc(val) + '" target="_blank"><i class="fa-solid fa-file-pdf"></i> View</a></td>';
                    return;
                }

                // Normal cell
                if (val === null || val === undefined || val === '') {
                    cells += '<td class="rpt-na">&mdash;</td>';
                } else {
                    cells += '<td>' + esc(String(val)) + '</td>';
                }
            });

            return '<tr data-row-index="' + i + '">' + cells + '</tr>';
        }).join('');

        lastGeneratedParams = params.toString();

    } catch (e) {
        console.error(e);
        body.innerHTML = '<tr><td colspan="20"><div class="rpt-state">' +
            '<div class="rpt-state-icon state-error"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
            '<h4>Connection error</h4><p>Failed to load report data.</p></div></td></tr>';
        title.textContent = 'Error';
    } finally {
        isLoading = false;
    }
}

// ═══════════════════════════════════════════
// SAVE RATINGS — auto-save on blur
// ═══════════════════════════════════════════
body.addEventListener('focusout', async (e) => {
    const input = e.target.closest('.opcr-rating-input');
    if (!input) return;

    const requestId = input.dataset.requestId;
    const field     = input.dataset.field;
    const sub       = input.dataset.sub;
    const value     = input.value;

    // Find all rating inputs for this row
    const row = input.closest('tr');
    const allInputs = row.querySelectorAll('.opcr-rating-input');

    const data = {
        request_id: requestId,
        sub: sub,
    };
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

        if (res.ok) {
            input.style.borderColor = '#059669';
            setTimeout(() => { input.style.borderColor = ''; }, 1500);
        } else {
            input.style.borderColor = '#dc2626';
            setTimeout(() => { input.style.borderColor = ''; }, 1500);
        }
    } catch (err) {
        console.error('Save failed:', err);
    }
});

// Also save on Enter key
body.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.classList.contains('opcr-rating-input')) {
        e.target.blur(); // triggers focusout which saves
    }
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
    url += '&rows=none';

    if (format === 'print') {
        showToast('success', 'Opening print view...');
        window.open(url.replace('format=pdf', 'format=html') + '&autoPrint=1', '_blank');
        return;
    }
    if (format === 'pdf') {
        showToast('success', 'Opening PDF...');
        window.open(url, '_blank');
        return;
    }
    if (format === 'xlsx') {
        showToast('success', 'Downloading Excel file...');
        const a = document.createElement('a');
        a.href = url; a.download = ''; a.style.display = 'none';
        document.body.appendChild(a); a.click();
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
// AUTO-LOAD
// ═══════════════════════════════════════════
loadReport();