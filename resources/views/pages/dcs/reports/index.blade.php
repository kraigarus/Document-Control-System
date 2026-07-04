<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System - Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f4f5f7;
            --surface: #ffffff;
            --border: #dfe1e6;
            --border-light: #ebecf0;
            --accent: #0c4a2f;
            --accent-hover: #0a3d26;
            --accent-light: #e6f0eb;
            --accent-muted: #d4e6dc;
            --text: #172b4d;
            --text-secondary: #5e6c84;
            --text-muted: #97a0af;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px rgba(9,30,66,0.08);
            --shadow-md: 0 4px 12px rgba(9,30,66,0.1);
            --shadow-lg: 0 8px 24px rgba(9,30,66,0.12);
            --radius: 6px;
            --radius-lg: 8px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

.rpt-page {
    position: fixed;
    top: 70px;
    right: 0;
    bottom: 0;
    padding: 28px 32px;
    background-color: var(--bg);
    overflow-y: auto;
    overflow-x: hidden;
    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.rpt-page::-webkit-scrollbar {
    width: 6px;
}

.rpt-page::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

.rpt-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 24px;
}

.rpt-breadcrumb {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 400;
    margin-bottom: 4px;
}

.rpt-breadcrumb span {
    font-weight: 600;
    color: var(--text-secondary);
}

.rpt-header h1 {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -0.02em;
}

        /* ═══════════════════════════════════════════
           CATEGORY CARDS
           ═══════════════════════════════════════════ */
        .rpt-cats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .rpt-cat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            cursor: pointer;
            transition: all 0.15s ease;
            position: relative;
        }
        .rpt-cat-card:hover {
            border-color: #b3c0cf;
            box-shadow: var(--shadow-sm);
        }
        .rpt-cat-card.active {
            border-color: var(--accent);
            border-width: 2px;
            background: var(--accent-light);
            box-shadow: var(--shadow-sm);
        }
        .rpt-cat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .rpt-cat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius);
            background: #eef2f7;
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .rpt-cat-card.active .rpt-cat-icon {
            background: var(--accent);
            color: var(--white);
        }
        .rpt-cat-check {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: transparent;
            transition: all 0.15s ease;
        }
        .rpt-cat-card.active .rpt-cat-check {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--white);
        }
        .rpt-cat-card h3 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text);
            line-height: 1.3;
        }
        .rpt-cat-card p {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* ═══════════════════════════════════════════
           SUBCATEGORY TABS
           ═══════════════════════════════════════════ */
        .rpt-subs {
            display: flex;
            gap: 0;
            margin-bottom: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 4px;
            overflow-x: auto;
        }
        .rpt-subs:empty { display: none; }
        .rpt-sub-tab {
            padding: 8px 18px;
            border: none;
            border-radius: var(--radius);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            background: transparent;
            cursor: pointer;
            transition: all 0.12s ease;
            white-space: nowrap;
        }
        .rpt-sub-tab:hover {
            background: #f4f5f7;
            color: var(--text);
        }
        .rpt-sub-tab.active {
            background: var(--accent);
            color: var(--white);
        }

        /* ═══════════════════════════════════════════
           FILTER BAR
           ═══════════════════════════════════════════ */
        .rpt-filter-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 20px;
            display: none;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }
        .rpt-filter-bar.visible {
            display: flex;
        }
        .rpt-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .rpt-field label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }
        .rpt-field input[type="date"] {
            padding: 7px 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: var(--text);
            background: var(--white);
            height: 34px;
        }
        .rpt-field input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(12,74,47,0.12);
        }
        .rpt-spacer { flex: 1; }

        /* ═══════════════════════════════════════════
           BUTTONS
           ═══════════════════════════════════════════ */
        .rpt-btn {
            padding: 0 16px;
            height: 34px;
            border-radius: var(--radius);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid;
            transition: all 0.12s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .rpt-btn-primary {
            background: var(--accent);
            color: var(--white);
            border-color: var(--accent);
        }
        .rpt-btn-primary:hover {
            background: var(--accent-hover);
            border-color: var(--accent-hover);
        }
        .rpt-btn-secondary {
            background: var(--white);
            color: var(--text);
            border-color: var(--border);
        }
        .rpt-btn-secondary:hover {
            background: #f4f5f7;
            border-color: #b3c0cf;
        }
        .rpt-btn-ghost {
            background: transparent;
            color: var(--text-secondary);
            border-color: transparent;
            padding: 0 10px;
        }
        .rpt-btn-ghost:hover {
            color: var(--text);
            background: #f4f5f7;
        }
        .rpt-btn i { font-size: 12px; }

        /* ═══════════════════════════════════════════
           RESULTS PANEL
           ═══════════════════════════════════════════ */
        .rpt-results {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            display: none;
        }
        .rpt-results.visible { display: block; }

        .rpt-results-head {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafbfc;
        }
        .rpt-results-head h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }
        .rpt-results-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .rpt-results-count {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .rpt-results-divider {
            width: 1px;
            height: 16px;
            background: var(--border);
        }

        /* ═══════════════════════════════════════════
           TABLE
           ═══════════════════════════════════════════ */
        .rpt-table-scroll {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }
        .rpt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .rpt-table th {
            position: sticky;
            top: 0;
            background: #f4f5f7;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-secondary);
            padding: 10px 16px;
            text-align: left;
            white-space: nowrap;
            border-bottom: 2px solid var(--border);
            z-index: 1;
        }
        .rpt-table td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-light);
            white-space: normal;
            word-wrap: break-word;
            vertical-align: top;
            color: var(--text);
        }
        .rpt-table tbody tr:hover td {
            background: #f8f9fb;
        }
        .rpt-table .rpt-na {
            color: var(--text-muted);
        }
        .rpt-table a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .rpt-table a:hover {
            text-decoration: underline;
        }
        .rpt-table a i {
            margin-right: 4px;
            font-size: 11px;
        }

        /* ── Empty / Loading states ── */
        .rpt-state {
            text-align: center;
            padding: 60px 24px;
        }
        .rpt-state-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: #f4f5f7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--text-muted);
        }
        .rpt-state h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }
        .rpt-state p {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* ═══════════════════════════════════════════
           PRINT STYLES
           ═══════════════════════════════════════════ */
        @media print {
            .rpt-page { margin-left: 0; padding: 20px; }
            .rpt-cats, .rpt-filter-bar, .rpt-subs,
            .rpt-btn, header, nav, .sidebar,
            #sideNav, .rpt-breadcrumb { display: none !important; }
            .rpt-results { border: none; display: block !important; }
            .rpt-table th { background: #eee; position: static; }
            .rpt-results-head { background: #fff; }
        }

        /* ═══════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════ */
        @media (max-width: 1100px) {
            .rpt-cats { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .rpt-page { margin-left: 0; padding: 20px 16px; }
            .rpt-cats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

<main class="rpt-page">

    <header class="rpt-header">
        <div class="rpt-header-left">
            <div class="rpt-breadcrumb">Document Control System / <span>Reports</span></div>
            <h1>Generate Report</h1>
        </div>
    </header>

    {{-- CATEGORY CARDS --}}
    <div class="rpt-cats" id="categoryCards">
        @foreach($categories as $key => $cat)
        <div class="rpt-cat-card" data-category="{{ $key }}">
            <div class="rpt-cat-top">
                <div class="rpt-cat-icon"><i class="{{ $cat['icon'] }}"></i></div>
                <div class="rpt-cat-check"><i class="fa-solid fa-check"></i></div>
            </div>
            <h3>{{ $cat['label'] }}</h3>
            <p>{{ count($cat['subs']) }} report type{{ count($cat['subs']) > 1 ? 's' : '' }} available</p>
        </div>
        @endforeach
    </div>

    {{-- SUBCATEGORY TABS --}}
    <div class="rpt-subs" id="subTabs"></div>

    {{-- FILTER BAR --}}
    <div class="rpt-filter-bar" id="filterBar">
        <div class="rpt-field">
            <label>Date From</label>
            <input type="date" id="filterDateFrom">
        </div>
        <div class="rpt-field">
            <label>Date To</label>
            <input type="date" id="filterDateTo">
        </div>
        <div class="rpt-spacer"></div>
        <button class="rpt-btn rpt-btn-secondary" id="resetBtn">
            <i class="fa-solid fa-arrow-rotate-left"></i> Reset
        </button>
        <button class="rpt-btn rpt-btn-primary" id="generateBtn">
            <i class="fa-solid fa-magnifying-glass-chart"></i> Generate
        </button>
    </div>

    {{-- RESULTS --}}
    <div class="rpt-results" id="resultsPanel">
        <div class="rpt-results-head">
            <h3 id="resultsTitle">Report</h3>
            <div class="rpt-results-right">
                <span class="rpt-results-count" id="resultsCount">0 records</span>
                <div class="rpt-results-divider"></div>
                <button class="rpt-btn rpt-btn-secondary" id="exportBtn" style="display:none;">
                    <i class="fa-solid fa-print"></i> Print / Export
                </button>
            </div>
        </div>
        <div class="rpt-table-scroll">
            <table class="rpt-table" id="reportTable">
                <thead id="reportHead"></thead>
                <tbody id="reportBody"></tbody>
            </table>
        </div>
    </div>

</main>

<script>
    const CATEGORIES = @json($categories);

    let currentCategory = null;
    let currentSub = null;

    const categoryCards = document.getElementById('categoryCards');
    const subTabs       = document.getElementById('subTabs');
    const filterBar     = document.getElementById('filterBar');
    const generateBtn   = document.getElementById('generateBtn');
    const exportBtn     = document.getElementById('exportBtn');
    const resetBtn      = document.getElementById('resetBtn');
    const resultsPanel  = document.getElementById('resultsPanel');
    const resultsTitle  = document.getElementById('resultsTitle');
    const resultsCount  = document.getElementById('resultsCount');
    const reportHead    = document.getElementById('reportHead');
    const reportBody    = document.getElementById('reportBody');

    // ── Category click ──
    categoryCards.addEventListener('click', (e) => {
        const card = e.target.closest('.rpt-cat-card');
        if (!card) return;

        document.querySelectorAll('.rpt-cat-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');

        currentCategory = card.dataset.category;
        currentSub = null;

        renderSubs();
        filterBar.classList.add('visible');
        resultsPanel.classList.remove('visible');
        exportBtn.style.display = 'none';
    });

    // ── Render sub tabs ──
    function renderSubs() {
        const cat = CATEGORIES[currentCategory];
        subTabs.innerHTML = '';

        if (!cat || Object.keys(cat.subs).length <= 1) {
            subTabs.style.display = 'none';
            currentSub = cat ? Object.keys(cat.subs)[0] : null;
            return;
        }

        subTabs.style.display = 'flex';

        Object.entries(cat.subs).forEach(([key, label]) => {
            const tab = document.createElement('button');
            tab.className = 'rpt-sub-tab';
            tab.dataset.sub = key;
            tab.textContent = label;
            tab.addEventListener('click', () => {
                document.querySelectorAll('.rpt-sub-tab').forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                currentSub = key;
                resultsPanel.classList.remove('visible');
                exportBtn.style.display = 'none';
            });
            subTabs.appendChild(tab);
        });

        const first = subTabs.querySelector('.rpt-sub-tab');
        if (first) {
            first.classList.add('active');
            currentSub = first.dataset.sub;
        }
    }

    // ── Generate ──
    generateBtn.addEventListener('click', async () => {
        if (!currentCategory) return;

        const params = new URLSearchParams();
        params.set('category', currentCategory);
        if (currentSub) params.set('sub', currentSub);
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo   = document.getElementById('filterDateTo').value;
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo)   params.set('date_to', dateTo);

        resultsPanel.classList.add('visible');
        resultsTitle.textContent = 'Loading...';
        resultsCount.textContent = '';
        reportHead.innerHTML = '';
        reportBody.innerHTML =
            '<tr><td colspan="20"><div class="rpt-state">' +
            '<div class="rpt-state-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>' +
            '<h4>Generating report...</h4>' +
            '<p>Please wait while we compile the data</p>' +
            '</div></td></tr>';
        exportBtn.style.display = 'none';

        try {
            const res = await fetch('/reports/data?' + params.toString());
            const json = await res.json();

            if (json.error) {
                reportBody.innerHTML =
                    '<tr><td colspan="20"><div class="rpt-state">' +
                    '<div class="rpt-state-icon" style="background:#fef2f2;color:#b91c1c;"><i class="fa-solid fa-circle-exclamation"></i></div>' +
                    '<h4>Error</h4>' +
                    '<p>' + esc(json.error) + '</p>' +
                    '</div></td></tr>';
                return;
            }

            resultsTitle.textContent = json.title || 'Report';
            resultsCount.textContent = json.total_rows + ' record' + (json.total_rows !== 1 ? 's' : '');

            const cols = json.columns;

            // Header
            reportHead.innerHTML = '<tr>' +
                Object.values(cols).map(h => '<th>' + esc(h) + '</th>').join('') +
                '</tr>';

            // Body
            if (!json.rows || json.rows.length === 0) {
                reportBody.innerHTML =
                    '<tr><td colspan="' + Object.keys(cols).length + '"><div class="rpt-state">' +
                    '<div class="rpt-state-icon"><i class="fa-solid fa-inbox"></i></div>' +
                    '<h4>No records found</h4>' +
                    '<p>Try adjusting your filters or date range</p>' +
                    '</div></td></tr>';
                return;
            }

            const colKeys = Object.keys(cols);
            reportBody.innerHTML = json.rows.map(row => {
                return '<tr>' + colKeys.map(key => {
                    const val = row[key];
                    if (key === 'pdf_path' && val) {
                        return '<td><a href="' + esc(val) + '" target="_blank"><i class="fa-solid fa-file-pdf"></i>View</a></td>';
                    }
                    if (val === null || val === undefined || val === '') {
                        return '<td class="rpt-na">&mdash;</td>';
                    }
                    return '<td>' + esc(val) + '</td>';
                }).join('') + '</tr>';
            }).join('');

            exportBtn.style.display = '';

        } catch (e) {
            console.error(e);
            reportBody.innerHTML =
                '<tr><td colspan="20"><div class="rpt-state">' +
                '<div class="rpt-state-icon" style="background:#fef2f2;color:#b91c1c;"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
                '<h4>Connection Error</h4>' +
                '<p>Failed to load report data. Please check your connection.</p>' +
                '</div></td></tr>';
        }
    });

    // ── Export ──
    exportBtn.addEventListener('click', () => {
        const params = new URLSearchParams();
        params.set('category', currentCategory);
        if (currentSub) params.set('sub', currentSub);
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo   = document.getElementById('filterDateTo').value;
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo)   params.set('date_to', dateTo);
        window.open('/reports/export?' + params.toString(), '_blank');
    });

    // ── Reset ──
    resetBtn.addEventListener('click', () => {
        currentCategory = null;
        currentSub = null;
        document.querySelectorAll('.rpt-cat-card').forEach(c => c.classList.remove('active'));
        subTabs.innerHTML = '';
        subTabs.style.display = 'none';
        filterBar.classList.remove('visible');
        resultsPanel.classList.remove('visible');
        exportBtn.style.display = 'none';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';
    });

    function esc(str) {
        if (str === null || str === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    // ── Sidebar toggle ──
    const sideNav = document.getElementById('sideNav');
    const rptPage = document.querySelector('.rpt-page');
    function updateSidebar() {
        if (!sideNav || !rptPage) return;
        rptPage.style.marginLeft = sideNav.classList.contains('collapsed') ? '68px' : '280px';
    }
    if (sideNav) {
        new MutationObserver(updateSidebar).observe(sideNav, { attributes: true, attributeFilter: ['class'] });
        updateSidebar();
    }
</script>

</body>
</html>