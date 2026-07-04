document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('tableBody');
    const emptyState = document.getElementById('emptyState');
    const docCount = document.getElementById('docCount');
    const searchInput = document.getElementById('dbSearch');
    const pageInfo = document.getElementById('pageInfo');
    const pageBtns = document.getElementById('pageBtns');

    let currentTypeId = 'all';
    let currentPage = 1;
    const perPage = 20;

    // ═══════════════════════════════════════════
    // Header row heights
    // ═══════════════════════════════════════════
    function updateHeaderHeights() {
        const row1 = document.querySelector('.db-head-primary');
        const row2 = document.querySelector('.db-head-secondary');
        if (row1) {
            const h1 = row1.offsetHeight;
            document.documentElement.style.setProperty('--header-row1-h', h1 + 'px');
            if (row2) {
                const h2 = row2.offsetHeight;
                document.documentElement.style.setProperty('--header-row2-top', (h1 + h2) + 'px');
            }
        }
    }

    // ═══════════════════════════════════════════
    // Column group toggle
    // ═══════════════════════════════════════════
    const groups = ['approval', 'deadline', 'masterlist', 'dcn', 'drf', 'distribution', 'retrieval'];
    const groupState = {};
    groups.forEach(g => groupState[g] = true);

    document.querySelectorAll('.col-group-summary').forEach(c => {
        c.style.display = 'none';
    });

    function toggleColumnGroup(group) {
        groupState[group] = !groupState[group];
        const expanded = groupState[group];

        // 1. Toggle header summary column (visible when collapsed)
        const summary = document.querySelector('.col-group-summary[data-group="' + group + '"]');
        if (summary) summary.style.display = expanded ? 'none' : '';

        // 2. Toggle all expanded columns (header th + body td)
        document.querySelectorAll('.col-group-' + group).forEach(cell => {
            cell.style.display = expanded ? '' : 'none';
        });

        // 3. Toggle body summary cells (visible when collapsed, hidden when expanded)
        document.querySelectorAll('.col-group-summary-' + group).forEach(cell => {
            cell.style.display = expanded ? 'none' : '';
        });

        requestAnimationFrame(updateHeaderHeights);
    }

    document.querySelectorAll('[data-toggle]').forEach(el => {
        el.style.cursor = 'pointer';
        el.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleColumnGroup(el.dataset.toggle);
        });
    });

    document.querySelectorAll('.col-group-summary').forEach(el => {
        el.style.cursor = 'pointer';
        el.addEventListener('click', () => {
            toggleColumnGroup(el.dataset.group);
        });
    });

    window.toggleColumnGroup = toggleColumnGroup;

    // ═══════════════════════════════════════════
    // Doc type tabs
    // ═══════════════════════════════════════════
    document.querySelectorAll('.db-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.db-type-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentTypeId = btn.dataset.typeId;
            currentPage = 1;
            loadData();
        });
    });

    // ═══════════════════════════════════════════
    // Search
    // ═══════════════════════════════════════════
    let searchTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentPage = 1; loadData(); }, 400);
    });

    // ═══════════════════════════════════════════
    // Filter panel
    // ═══════════════════════════════════════════
    const filterOverlay = document.getElementById('filterOverlay');
    const filterPanel = document.getElementById('filterPanel');

    document.getElementById('openFilterBtn').addEventListener('click', () => {
        filterOverlay.classList.add('db-open');
        filterPanel.classList.add('db-open');
    });

    function closeFilter() {
        filterOverlay.classList.remove('db-open');
        filterPanel.classList.remove('db-open');
    }

    document.getElementById('closeFilterBtn').addEventListener('click', closeFilter);
    filterOverlay.addEventListener('click', closeFilter);

    document.getElementById('resetFilterBtn').addEventListener('click', () => {
        document.getElementById('filterOriginator').value = '';
        document.getElementById('filterSourceUnit').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';
        document.getElementById('filterRevNo').value = '';
    });

    document.getElementById('applyFilterBtn').addEventListener('click', () => {
        closeFilter();
        currentPage = 1;
        loadData();
    });

    document.getElementById('exportBtn').addEventListener('click', () => {
        const params = buildParams();
        const qs = new URLSearchParams(params).toString();
        window.location.href = '/database/export?' + qs;
    });

    function buildParams() {
        const p = { page: currentPage, per_page: perPage };
        if (currentTypeId !== 'all') p.doc_type_id = currentTypeId;
        if (searchInput.value.trim()) p.search = searchInput.value.trim();
        const originator = document.getElementById('filterOriginator').value.trim();
        const sourceUnit = document.getElementById('filterSourceUnit').value;
        const status = document.getElementById('filterStatus').value;
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo = document.getElementById('filterDateTo').value;
        const revNo = document.getElementById('filterRevNo').value.trim();
        if (originator) p.originator = originator;
        if (sourceUnit) p.source_unit = sourceUnit;
        if (status) p.status = status;
        if (dateFrom) p.date_from = dateFrom;
        if (dateTo) p.date_to = dateTo;
        if (revNo) p.rev_no = revNo;
        return p;
    }

    // ═══════════════════════════════════════════
    // Load data
    // ═══════════════════════════════════════════
    async function loadData() {
        document.querySelector('.db-table-scroll').style.display = '';
        emptyState.style.display = 'none';
        tableBody.innerHTML = '<tr><td colspan="46" style="text-align:center;padding:40px;color:#94a3b8;">Loading documents...</td></tr>';

        try {
            const params = buildParams();
            const qs = new URLSearchParams(params).toString();
            const res = await fetch('/database/data?' + qs);

            if (!res.ok) {
                const errText = await res.text();
                console.error('Server error:', res.status, errText);
                tableBody.innerHTML = '';
                showEmpty('Server error (' + res.status + '). Check console for details.', true);
                return;
            }

            const json = await res.json();

            if (json.error) {
                console.error('Server error:', json.error);
                tableBody.innerHTML = '';
                showEmpty('Server error: ' + json.error, true);
                return;
            }

            docCount.textContent = json.total + ' Documents';

            if (!json.data || json.data.length === 0) {
                showEmpty('No documents found for the selected filters.', false);
                renderPagination(json);
                return;
            }

            document.querySelector('.db-table-scroll').style.display = '';
            emptyState.style.display = 'none';
            renderRows(json.data);
            renderPagination(json);

        } catch (e) {
            console.error('Database load error:', e);
            tableBody.innerHTML = '';
            showEmpty('Failed to load data. Please check your connection and try again.', true);
        }
    }

    function showEmpty(message, isError) {
        document.querySelector('.db-table-scroll').style.display = 'none';
        emptyState.style.display = '';

        emptyState.innerHTML =
            '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
            (isError
                ? '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>'
                : '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>'
            ) +
            '</svg>' +
            '<h3>' + (isError ? 'Error Loading Data' : 'No Documents Found') + '</h3>' +
            '<p>' + esc(message) + '</p>' +
            (isError ? '<button class="db-btn db-btn-primary" style="margin-top:12px" onclick="location.reload()">Retry</button>' : '');
    }

    // ═══════════════════════════════════════════
    // Check if a row has any data for a group
    // ═══════════════════════════════════════════
    function hasGroupData(r, group) {
        switch (group) {
            case 'approval':
                return !!(r.approval_no || r.approval_date);
            case 'deadline':
                return !!(r.deadline_date || r.deadline_diff);
            case 'masterlist':
                return !!(r.ml_receipt_date || r.ml_receipt_time || r.ml_register_date || r.ml_register_time);
            case 'dcn':
                return !!(r.dcn_no || r.dcn_date || r.dcn_receipt_date || r.dcn_receipt_time || r.dcn_purpose || r.dcn_scan);
            case 'drf':
                return !!(r.drf_no || r.drf_date || r.drf_receipt_date || r.drf_receipt_time || r.drf_scan);
            case 'distribution':
                return !!(r.dist_onfile_date || r.dist_onfile_time || r.dist_actual_date || r.dist_actual_time || r.dist_offices || r.dist_scan);
            case 'retrieval':
                return !!(r.ret_onfile || r.ret_actual || r.ret_offices || r.ret_scan);
            default:
                return false;
        }
    }

    // ═══════════════════════════════════════════
    // Build summary cell for body row
    // ═══════════════════════════════════════════
    function summaryCell(group, r) {
        const hidden = groupState[group] ? ' style="display:none"' : '';
        const hasData = hasGroupData(r, group);
        const icon = hasData
            ? '<span class="db-summary-check">\u2713</span>'
            : '<span class="db-summary-x">\u2717</span>';
        return '<td class="col-group-summary-body col-group-summary-' + group + ' col-bg-' + group + '"' + hidden + '>' + icon + '</td>';
    }

    // ═══════════════════════════════════════════
    // Render grouped rows
    // ═══════════════════════════════════════════
    function renderRows(groups) {
        const offset = (currentPage - 1) * perPage;

        const html = groups.map((group, i) => {
            const itemNo = offset + i + 1;
            const parent = group.parent;
            const children = group.children || [];
            const hasRevisions = group.has_revisions;
            const groupId = 'rev-' + itemNo;

            let firstCell = '';
            if (hasRevisions) {
                firstCell = '<td><span class="db-expand-btn" data-target="' + groupId + '" title="Show ' + children.length + ' older revision(s)">\u25B6</span>' + itemNo + '</td>';
            } else {
                firstCell = '<td>' + itemNo + '</td>';
            }

            let rows = '<tr class="db-parent-row">' + firstCell + rowCells(parent) + '</tr>';

            children.forEach((child, ci) => {
                rows += '<tr class="db-child-row" data-group="' + groupId + '" style="display:none">' +
                    '<td class="db-child-ind">' +
                        '<span class="db-child-dot"></span>' +
                        itemNo + '.' + (ci + 1) +
                    '</td>' +
                    rowCells(child) +
                '</tr>';
            });

            return rows;
        }).join('');

        tableBody.innerHTML = html;

        document.querySelectorAll('.db-expand-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const target = this.dataset.target;
                const childRows = document.querySelectorAll('tr[data-group="' + target + '"]');
                const expanded = this.classList.toggle('expanded');
                this.textContent = expanded ? '\u25BC' : '\u25B6';
                childRows.forEach(row => {
                    row.style.display = expanded ? '' : 'none';
                });
            });
        });

        requestAnimationFrame(updateHeaderHeights);
    }

    // Build cells for a single row (all columns except the first item-no cell)
    function rowCells(r) {
        return '<td>' + esc(r.doc_no) + '</td>' +
            '<td>' + esc(r.rev_no) + '</td>' +
            '<td title="' + esc(r.title) + '">' + esc(r.title) + '</td>' +
            '<td>' + esc(r.effectivity) + '</td>' +
            '<td>' + esc(r.originator) + '</td>' +
            '<td style="text-align:center">' + esc(r.pages) + '</td>' +
            '<td style="text-align:center">' + statusBadge(r.status) + '</td>' +
            '<td style="text-align:center">' + pdfLink(r.pdf_path) + '</td>' +
            '<td>' + esc(r.source_unit) + '</td>' +
            // ── Approval ──
            summaryCell('approval', r) +
            groupCell('approval', r.approval_no) +
            groupCell('approval', r.approval_date) +
            // ── Deadline ──
            summaryCell('deadline', r) +
            groupCell('deadline', r.deadline_date) +
            groupCell('deadline', r.deadline_diff) +
            // ── Masterlist ──
            summaryCell('masterlist', r) +
            groupCell('masterlist', r.ml_receipt_date) +
            groupCell('masterlist', r.ml_receipt_time) +
            groupCell('masterlist', r.ml_register_date) +
            groupCell('masterlist', r.ml_register_time) +
            // ── DCN ──
            summaryCell('dcn', r) +
            groupCell('dcn', r.dcn_no) +
            groupCell('dcn', r.dcn_date) +
            groupCell('dcn', r.dcn_receipt_date) +
            groupCell('dcn', r.dcn_receipt_time) +
            groupCell('dcn', r.dcn_purpose) +
            groupCell('dcn', r.dcn_scan, true) +
            // ── DRF ──
            summaryCell('drf', r) +
            groupCell('drf', r.drf_no) +
            groupCell('drf', r.drf_date) +
            groupCell('drf', r.drf_receipt_date) +
            groupCell('drf', r.drf_receipt_time) +
            groupCell('drf', r.drf_scan, true) +
            // ── Distribution ──
            summaryCell('distribution', r) +
            groupCell('distribution', r.dist_onfile_date) +
            groupCell('distribution', r.dist_onfile_time) +
            groupCell('distribution', r.dist_actual_date) +
            groupCell('distribution', r.dist_actual_time) +
            groupCell('distribution', r.dist_offices) +
            groupCell('distribution', r.dist_scan, true) +
            // ── Retrieval ──
            summaryCell('retrieval', r) +
            groupCell('retrieval', r.ret_onfile) +
            groupCell('retrieval', r.ret_actual) +
            groupCell('retrieval', r.ret_offices) +
            groupCell('retrieval', r.ret_scan, true);
    }

    function groupCell(group, value, isLink) {
        const hidden = groupState[group] ? '' : ' style="display:none"';
        if (!value || value === 'N/A') {
            return '<td class="col-group-' + group + ' col-bg-' + group + '"' + hidden + '><span class="db-na">\u2014</span></td>';
        }
        if (isLink) {
            return '<td class="col-group-' + group + ' col-bg-' + group + '"' + hidden + '>' +
                '<a href="' + value + '" class="db-scan-link" target="_blank" rel="noopener">View</a></td>';
        }
        return '<td class="col-group-' + group + ' col-bg-' + group + '"' + hidden + '>' + esc(value) + '</td>';
    }

    function statusBadge(status) {
        const s = (status || 'active').toLowerCase();
        let cls = 'db-status-active';
        if (s === 'latest') cls = 'db-status-latest';
        else if (s === 'obsolete') cls = 'db-status-obsolete';
        else if (s === 'pending') cls = 'db-status-pending';
        return '<span class="db-status ' + cls + '">' + esc(status || 'Active') + '</span>';
    }

    function pdfLink(path) {
        if (!path) return '<span class="db-na">\u2014</span>';
        return '<a href="' + esc(path) + '" class="db-pdf-link" target="_blank" rel="noopener" title="View PDF">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>' +
            '<path d="M14 2v6h6"/></svg></a>';
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    // ═══════════════════════════════════════════
    // Pagination
    // ═══════════════════════════════════════════
    function renderPagination(json) {
        const total = json.total || 0;
        const lastPage = json.last_page || 1;
        const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
        const to = Math.min(currentPage * perPage, total);

        pageInfo.innerHTML = 'Showing <strong>' + from + '\u2013' + to + '</strong> of <strong>' + total + '</strong> documents';

        let btns = '<button class="db-pg" ' + (currentPage <= 1 ? 'disabled' : '') + ' data-page="' + (currentPage - 1) + '">&laquo;</button>';

        const start = Math.max(1, currentPage - 2);
        const end = Math.min(lastPage, currentPage + 2);

        if (start > 1) {
            btns += '<button class="db-pg" data-page="1">1</button>';
            if (start > 2) btns += '<span style="padding:0 6px;color:#94a3b8">...</span>';
        }
        for (let p = start; p <= end; p++) {
            btns += '<button class="db-pg ' + (p === currentPage ? 'db-pg-active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        if (end < lastPage) {
            if (end < lastPage - 1) btns += '<span style="padding:0 6px;color:#94a3b8">...</span>';
            btns += '<button class="db-pg" data-page="' + lastPage + '">' + lastPage + '</button>';
        }
        btns += '<button class="db-pg" ' + (currentPage >= lastPage ? 'disabled' : '') + ' data-page="' + (currentPage + 1) + '">&raquo;</button>';

        pageBtns.innerHTML = btns;
        pageBtns.querySelectorAll('.db-pg[data-page]').forEach(b => {
            b.addEventListener('click', () => {
                const p = parseInt(b.dataset.page);
                if (p >= 1 && p <= lastPage) {
                    currentPage = p;
                    loadData();
                    document.querySelector('.db-table-scroll').scrollTop = 0;
                }
            });
        });
    }

    // ═══════════════════════════════════════════
    // Sidebar collapse
    // ═══════════════════════════════════════════
    const sideNav = document.getElementById('sideNav');
    const dbPage = document.querySelector('.db-page');

    function updateSidebarOffset() {
        if (!sideNav || !dbPage) return;
        const collapsed = sideNav.classList.contains('collapsed');
        dbPage.style.left = collapsed ? '68px' : '280px';
    }

    if (sideNav) {
        const obs = new MutationObserver(updateSidebarOffset);
        obs.observe(sideNav, { attributes: true, attributeFilter: ['class'] });
        updateSidebarOffset();
    }

    // ═══════════════════════════════════════════
    // Init
    // ═══════════════════════════════════════════
    updateHeaderHeights();
    loadData();
});