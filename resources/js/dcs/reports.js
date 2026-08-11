// Shared report UI — inline filters + export-template preview.
import { initInlineReportFilters } from './report-filter';

const REPORT_CATEGORY = window.REPORT_CATEGORY;
const REPORT_DOC_TYPES = window.REPORT_DOC_TYPES || [];
const HAS_SUB_TABS = REPORT_CATEGORY !== 'others';

const isOpcr = REPORT_CATEGORY === 'opcr';
const isSelectable = !isOpcr;
const RATING_KEYS = ['rating_q', 'rating_e', 'rating_t', 'rating_a'];
const USE_PREVIEW_FRAME = !isOpcr;

const $ = (id) => document.getElementById(id);
const subTabs = $('subTabs');
const inlineFilters = $('inlineFilters');
const resultsPanel = $('resultsPanel');
const previewShell = $('previewShell');
const previewFrame = $('reportPreviewFrame');
const previewPlaceholder = $('previewPlaceholder');
const opcrTableHost = $('opcrTableHost');
const title = $('resultsTitle');
const count = $('resultsCount');
const head = $('reportHead');
const body = $('reportBody');
const exportDropdown = $('exportDropdown');
const exportBtn = $('exportBtn');
const exportMenu = $('exportMenu');

let currentSub = null;
let lastGeneratedParams = null;
let lastRenderedRowCount = 0;
let selectedRows = new Set();
let isLoading = false;
let filtersReady = false;

function showResultsPanel() {
    if (!resultsPanel) return;
    resultsPanel.hidden = false;
}

const filters = initInlineReportFilters({
    docTypes: REPORT_DOC_TYPES,
    hasSubTabs: HAS_SUB_TABS,
    onChange: () => {
        if (filtersReady) loadReport();
    },
});

// ═══════════════════════════════════════════
// DOC TYPE (SUB-TAB) SELECTION
// ═══════════════════════════════════════════
if (subTabs) {
    subTabs.querySelectorAll('.rpt-sub').forEach(btn => btn.classList.remove('active'));

    subTabs.addEventListener('click', (e) => {
        const btn = e.target.closest('.rpt-sub');
        if (!btn || isLoading) return;

        subTabs.querySelectorAll('.rpt-sub').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        currentSub = btn.dataset.sub;
        selectedRows.clear();
        lastGeneratedParams = null;
        filtersReady = false;

        filters.setSubTab(currentSub);
        filters.show();
        filtersReady = true;
        loadReport();
    });
} else if (REPORT_CATEGORY === 'others') {
    filters.show();
    filtersReady = true;
    loadReport();
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
    if (isLoading || !filtersReady) return;
    if (!REPORT_CATEGORY) {
        showToast('error', 'Report category is not configured.');
        return;
    }
    if (HAS_SUB_TABS && !currentSub) return;

    const filterState = filters.getState();
    const subtypeBlock = document.getElementById('subtypeBlock');
    if (subtypeBlock && !subtypeBlock.hidden && filterState.sub_type_ids.length === 0) {
        showResultsPanel();
        title.textContent = 'Report Preview';
        count.textContent = '';
        showPreviewError('Select at least one sub-type to generate the report.');
        lastGeneratedParams = null;
        return;
    }

    isLoading = true;
    selectedRows.clear();
    showResultsPanel();
    setPreviewLoading();

    const params = buildParams();

    try {
        const res = await fetch('/reports/data?' + params.toString());
        const json = await res.json();

        if (json.error) {
            showPreviewError(json.error);
            title.textContent = 'Error';
            return;
        }

        title.textContent = json.title || 'Report';
        lastRenderedRowCount = json.total_rows || 0;
        updateSelectionCount();
        lastGeneratedParams = params.toString();

        if (USE_PREVIEW_FRAME) {
            refreshPreviewFrame(params);
        } else {
            renderOpcrTable(json);
        }

    } catch (e) {
        console.error(e);
        showPreviewError('Failed to load report data.');
        title.textContent = 'Error';
    } finally {
        isLoading = false;
    }
}

function setPreviewLoading() {
    title.textContent = 'Loading preview...';
    count.textContent = '';
    if (previewPlaceholder) {
        previewPlaceholder.hidden = false;
        previewPlaceholder.innerHTML =
            '<div class="rpt-state-spinner"></div>' +
            '<h4 style="margin-top:18px;">Generating preview...</h4>';
    }
    if (previewFrame) previewFrame.hidden = true;
    if (opcrTableHost) opcrTableHost.hidden = true;
}

function showPreviewError(msg) {
    if (previewPlaceholder) {
        previewPlaceholder.hidden = false;
        previewPlaceholder.innerHTML =
            '<div class="rpt-state-icon state-error"><i class="fa-solid fa-circle-exclamation"></i></div>' +
            '<h4>Error</h4><p>' + esc(msg) + '</p>';
    }
    if (previewFrame) previewFrame.hidden = true;
    if (opcrTableHost) opcrTableHost.hidden = true;
}

function refreshPreviewFrame(params) {
    if (!previewFrame) return;
    const previewParams = new URLSearchParams(params.toString());
    previewParams.set('format', 'html');
    previewParams.set('embed', '1');
    previewParams.set('_', String(Date.now()));

    if (previewPlaceholder) {
        previewPlaceholder.hidden = false;
        previewPlaceholder.innerHTML =
            '<div class="rpt-state-spinner"></div>' +
            '<h4 style="margin-top:18px;">Loading preview...</h4>';
    }
    previewFrame.hidden = true;
    if (opcrTableHost) opcrTableHost.hidden = true;

    fetch('/reports/export?' + previewParams.toString(), { credentials: 'same-origin' })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.text();
        })
        .then(html => {
            if (html.includes('/login') && (html.includes('Redirecting') || html.includes('showLoginForm'))) {
                showPreviewError('Session expired. Please refresh the page and log in again.');
                return;
            }
            previewFrame.removeAttribute('src');
            previewFrame.srcdoc = html;
            previewFrame.hidden = false;
            if (previewPlaceholder) previewPlaceholder.hidden = true;
        })
        .catch(() => {
            showPreviewError('Could not load the report preview. Try refreshing the page.');
        });
}

// ═══════════════════════════════════════════
// OPCR TABLE (interactive — not iframe)
// ═══════════════════════════════════════════
function renderOpcrTable(json) {
    if (!head || !body || !opcrTableHost) return;

    previewFrame.hidden = true;
    if (previewPlaceholder) previewPlaceholder.hidden = true;
    opcrTableHost.hidden = false;

    const cols = json.columns;
    const colKeys = Object.keys(cols);
    const groupHeaders = json.group_headers || {};
    const hasGroups = Object.keys(groupHeaders).length > 0;

    if (hasGroups) {
        let row1 = '<tr>';
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
                while (j < colKeys.length && groupHeaders[colKeys[j]] === group) { span++; j++; }
                row1 += '<th colspan="' + span + '" class="opcr-rating-th">' + esc(group) + '</th>';
                i = j;
            }
        }
        row1 += '</tr>';
        let row2 = '<tr>';
        colKeys.forEach(key => {
            if (groupHeaders[key] !== null && groupHeaders[key] !== undefined) {
                row2 += '<th class="opcr-rating-th">' + esc(cols[key]) + '</th>';
            }
        });
        row2 += '</tr>';
        head.innerHTML = row1 + row2;
    } else {
        head.innerHTML = '<tr>' + colKeys.map(k => '<th>' + esc(cols[k]) + '</th>').join('') + '</tr>';
    }

    if (!json.rows || !json.rows.length) {
        body.innerHTML =
            '<tr><td colspan="' + colKeys.length + '"><div class="rpt-state">' +
            '<div class="rpt-state-icon"><i class="fa-solid fa-inbox"></i></div>' +
            '<h4>No records found</h4><p>Try adjusting your filters or date range</p></div></td></tr>';
        return;
    }

    body.innerHTML = json.rows.map((row, i) => {
        const cells = colKeys.map(key => renderCell(key, row[key], row)).join('');
        return '<tr data-row-index="' + i + '">' + cells + '</tr>';
    }).join('');
}

function renderCell(key, val, row) {
    if (RATING_KEYS.includes(key)) {
        return '<td class="opcr-rating-td">' +
            '<input type="number" class="opcr-rating-input" ' +
            'data-request-id="' + row.request_id + '" data-field="' + key + '" ' +
            'data-sub="' + currentSub + '" min="0" max="10" step="0.01" ' +
            'value="' + (val !== null && val !== undefined ? esc(String(val)) : '') + '" placeholder="0"></td>';
    }
    if (key === 'days_diff') {
        if (val === null || val === undefined) return '<td class="rpt-na">&mdash;</td>';
        return row.days_type === 'advanced'
            ? '<td class="opcr-days-advanced">+' + esc(String(val)) + '</td>'
            : '<td class="opcr-days-delayed">-' + esc(String(val)) + '</td>';
    }
    if (key === 'pdf_path' && val) {
        return '<td><a href="' + esc(val) + '" target="_blank"><i class="fa-solid fa-file-pdf"></i> View</a></td>';
    }
    if (val === null || val === undefined || val === '') return '<td class="rpt-na">&mdash;</td>';
    return '<td>' + esc(val) + '</td>';
}

function updateSelectionCount() {
    if (!count) return;
    if (!isSelectable) {
        count.textContent = lastRenderedRowCount + ' record' + (lastRenderedRowCount !== 1 ? 's' : '');
        return;
    }
    count.textContent = lastRenderedRowCount + ' record' + (lastRenderedRowCount !== 1 ? 's' : '');
}

// OPCR rating save
if (isOpcr && body) {
    body.addEventListener('focusout', async (e) => {
        const input = e.target.closest('.opcr-rating-input');
        if (!input) return;
        const row = input.closest('tr');
        const data = { request_id: input.dataset.requestId, sub: input.dataset.sub };
        row.querySelectorAll('.opcr-rating-input').forEach(inp => {
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
            console.error(err);
        }
    });
    body.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.classList.contains('opcr-rating-input')) e.target.blur();
    });
}

// ═══════════════════════════════════════════
// EXPORT
// ═══════════════════════════════════════════
if (exportBtn && exportMenu) {
    exportBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        exportMenu.classList.toggle('open');
        exportDropdown?.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (exportDropdown && !exportDropdown.contains(e.target)) {
            exportMenu.classList.remove('open');
            exportDropdown.classList.remove('open');
        }
    });

    exportMenu.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-format]');
        if (!btn) return;
        const format = btn.dataset.format;
        exportMenu.classList.remove('open');
        exportDropdown?.classList.remove('open');

        if (!lastGeneratedParams) {
            showToast('error', 'Generate a preview before exporting.');
            return;
        }

        let url = '/reports/export?' + lastGeneratedParams + '&format=' + format;

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
            showToast('success', 'Downloading CSV...');
            const a = document.createElement('a');
            a.href = url;
            a.download = '';
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            setTimeout(() => a.remove(), 1000);
        }
    });
}

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

function esc(str) {
    if (str === null || str === undefined) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

// Sidebar sync
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
