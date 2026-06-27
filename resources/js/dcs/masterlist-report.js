// ═══ MASTERLIST REPORT ═══

let allData = [];

document.addEventListener('DOMContentLoaded', function () {
    // Select all
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-cb').forEach(cb => {
                cb.checked = this.checked;
            });
            updateSelectedCount();
        });
    }

    // Auto-apply on Enter in filter inputs
    document.querySelectorAll('.rpt-filter-input').forEach(input => {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters();
            }
        });
    });

    // Mode change handler
    document.querySelectorAll('input[name="reportMode"]').forEach(radio => {
        radio.addEventListener('change', function () {
            const mode = this.value;
            const checkboxes = document.querySelectorAll('.row-cb, #selectAll');
            if (mode === 'selected') {
                checkboxes.forEach(cb => cb.style.display = '');
            } else {
                // Still show checkboxes but they're only used for 'selected' mode
            }
        });
    });
});

// ═══ FILTERS ═══
window.applyFilters = function () {
    const params = new URLSearchParams();

    const category = document.getElementById('filterCategory').value;
    const title = document.getElementById('filterTitle').value.trim();
    const docNo = document.getElementById('filterDocNo').value.trim();
    const revNo = document.getElementById('filterRevNo').value.trim();
    const originator = document.getElementById('filterOriginator').value.trim();
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    const itemFrom = document.getElementById('filterItemFrom').value;
    const itemTo = document.getElementById('filterItemTo').value;

    if (category) params.set('category', category);
    if (title) params.set('title', title);
    if (docNo) params.set('doc_no', docNo);
    if (revNo) params.set('rev_no', revNo);
    if (originator) params.set('originator', originator);
    if (dateFrom) params.set('effectivity_from', dateFrom);
    if (dateTo) params.set('effectivity_to', dateTo);
    if (itemFrom) params.set('item_from', itemFrom);
    if (itemTo) params.set('item_to', itemTo);

    const url = '/reports/masterlist/data?' + params.toString();

    fetch(url)
        .then(r => r.json())
        .then(data => {
            allData = data;
            renderTable(data);
            document.getElementById('resultsCount').textContent = data.length + ' document' + (data.length !== 1 ? 's' : '');
            updateSelectedCount();

            // Reset select all
            const sa = document.getElementById('selectAll');
            if (sa) sa.checked = false;
        })
        .catch(err => {
            console.error('Failed to load data:', err);
        });
};

window.clearFilters = function () {
    document.getElementById('filterCategory').value = '';
    document.getElementById('filterTitle').value = '';
    document.getElementById('filterDocNo').value = '';
    document.getElementById('filterRevNo').value = '';
    document.getElementById('filterOriginator').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    document.getElementById('filterItemFrom').value = '';
    document.getElementById('filterItemTo').value = '';

    allData = [];
    const tbody = document.getElementById('masterlistBody');
    tbody.innerHTML = `
        <tr class="rpt-empty-row">
            <td colspan="8">
                <div class="rpt-empty-state">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                    <p>Apply filters to load documents</p>
                    <span>Use the filters above, then click "Apply Filters"</span>
                </div>
            </td>
        </tr>
    `;
    document.getElementById('resultsCount').textContent = '0 documents';
    document.getElementById('selectedCount').style.display = 'none';
};

function renderTable(data) {
    const tbody = document.getElementById('masterlistBody');

    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="rpt-empty-state">
                        <i class="fa-solid fa-folder-open"></i>
                        <p>No documents found</p>
                        <span>Try adjusting your filters</span>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    data.forEach(row => {
        html += `
            <tr>
                <td><input type="checkbox" class="rpt-cb row-cb" value="${row.id}" onchange="updateSelectedCount()"></td>
                <td class="item-num">${row.item_no}</td>
                <td class="doc-no">${escapeHtml(row.doc_no)}</td>
                <td class="doc-title-cell" title="${escapeHtml(row.title)}">${escapeHtml(row.title)}</td>
                <td class="rev-cell">${escapeHtml(row.rev_no)}</td>
                <td>${escapeHtml(row.originator)}</td>
                <td class="effectivity-cell">${escapeHtml(row.effectivity)}</td>
                <td class="status-cell"><span class="status-active-badge">${escapeHtml(row.status)}</span></td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function updateSelectedCount() {
    const selected = document.querySelectorAll('.row-cb:checked');
    const countEl = document.getElementById('selectedCount');
    if (selected.length > 0) {
        countEl.style.display = 'flex';
        countEl.querySelector('span').textContent = selected.length;
    } else {
        countEl.style.display = 'none';
    }
}

function getSelectedIds() {
    const ids = [];
    document.querySelectorAll('.row-cb:checked').forEach(cb => ids.push(cb.value));
    return ids;
}

function getMode() {
    const radio = document.querySelector('input[name="reportMode"]:checked');
    return radio ? radio.value : 'complete';
}

// ═══ EXPORT ACTIONS ═══
window.generatePDF = function () {
    const mode = getMode();
    const ids = getSelectedIds();

    if (mode === 'selected' && ids.length === 0) {
        alert('Please select at least one document.');
        return;
    }

    const params = new URLSearchParams();
    params.set('mode', mode);
    if (ids.length > 0) params.set('ids', ids.join(','));

    // Carry over current filters for 'filtered' mode
    if (mode === 'filtered') {
        const category = document.getElementById('filterCategory').value;
        const title = document.getElementById('filterTitle').value.trim();
        if (category) params.set('category', category);
        if (title) params.set('title', title);
    }

    window.open('/reports/masterlist/print?' + params.toString(), '_blank');
};

window.printReport = function () {
    generatePDF(); // Same as PDF — opens print-friendly page
};

window.exportExcel = function () {
    const mode = getMode();
    const ids = getSelectedIds();

    if (mode === 'selected' && ids.length === 0) {
        alert('Please select at least one document.');
        return;
    }

    // Build CSV from current table data
    let rows = [];

    if (mode === 'selected') {
        rows = allData.filter(r => ids.includes(String(r.id)));
    } else if (mode === 'filtered') {
        rows = allData;
    } else {
        // complete — fetch all
        rows = allData;
    }

    if (rows.length === 0) {
        alert('No documents to export. Apply filters first.');
        return;
    }

    // CSV header
    let csv = 'Item #,Document No.,Document Title,Revision,Originator,Effectivity Date,Status\n';

    rows.forEach(r => {
        csv += `${r.item_no},"${r.doc_no}","${r.title.replace(/"/g, '""')}",${r.rev_no},"${r.originator}","${r.effectivity}","${r.status}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'masterlist_report_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
};

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}