<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System - Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --rpt-bg: #f8fafc;
            --rpt-surface: #ffffff;
            --rpt-border: #e2e8f0;
            --rpt-border-light: #f1f5f9;
            --rpt-accent: #0d2a7a;
            --rpt-accent-hover: #0b2368;
            --rpt-accent-light: #1a3a9e;
            --rpt-accent-subtle: #eef2ff;
            --rpt-text: #1e293b;
            --rpt-text-secondary: #64748b;
            --rpt-text-subtle: #94a3b8;
            --rpt-green: #059669;
            --rpt-green-bg: #ecfdf5;
            --rpt-red: #dc2626;
            --rpt-red-bg: #fef2f2;
            --rpt-radius: 8px;
            --rpt-radius-lg: 12px;
            --rpt-shadow: 0 1px 3px rgba(15,23,42,0.04), 0 4px 12px rgba(15,23,42,0.03);
            --rpt-shadow-hover: 0 4px 16px rgba(15,23,42,0.08), 0 1px 4px rgba(15,23,42,0.04);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', Arial, Helvetica, sans-serif;
            background: var(--rpt-bg);
            color: var(--rpt-text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Page ── */
        .rpt-page {
            position: fixed;
            top: 70px;
            left: 280px;
            right: 0;
            bottom: 0;
            padding: 28px 32px;
            background: var(--rpt-bg);
            overflow-y: auto;
            overflow-x: hidden;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .rpt-page::-webkit-scrollbar { width: 6px; }
        .rpt-page::-webkit-scrollbar-thumb { background: var(--rpt-border); border-radius: 3px; }

        /* ── Header ── */
        .rpt-hdr { margin-bottom: 28px; }
        .rpt-crumb { font-size: 0.82rem; color: var(--rpt-text-subtle); margin-bottom: 4px; }
        .rpt-crumb span { font-weight: 600; color: var(--rpt-text-secondary); }
        .rpt-hdr h1 { font-size: 1.75rem; font-weight: 800; color: var(--rpt-text); letter-spacing: -0.02em; }

        /* ── Category Cards ── */
        .rpt-cats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
            animation: rptFadeInUp 0.4s ease forwards;
        }
        .rpt-cat {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 18px;
            background: var(--rpt-surface);
            border: 1px solid var(--rpt-border-light);
            border-radius: var(--rpt-radius-lg);
            box-shadow: var(--rpt-shadow);
            cursor: pointer;
            text-align: left;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
        }
        .rpt-cat:hover { border-color: var(--rpt-border); box-shadow: var(--rpt-shadow-hover); transform: translateY(-2px); }
        .rpt-cat.active { border-color: var(--rpt-accent); border-width: 2px; background: var(--rpt-accent-subtle); box-shadow: var(--rpt-shadow-hover); padding: 15px 17px; }
        .rpt-cat-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: var(--rpt-accent-subtle); color: var(--rpt-accent);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0; transition: background 0.2s, color 0.2s;
        }
        .rpt-cat.active .rpt-cat-icon { background: var(--rpt-accent); color: #fff; }
        .rpt-cat-body { flex: 1; min-width: 0; }
        .rpt-cat-title { display: block; font-size: 0.82rem; font-weight: 700; color: var(--rpt-text); line-height: 1.3; }
        .rpt-cat-meta { display: block; font-size: 0.72rem; color: var(--rpt-text-subtle); margin-top: 2px; }
        .rpt-cat-check {
            width: 20px; height: 20px; border-radius: 50%;
            border: 2px solid var(--rpt-border);
            display: flex; align-items: center; justify-content: center;
            font-size: 9px; color: transparent; flex-shrink: 0; transition: all 0.15s;
        }
        .rpt-cat.active .rpt-cat-check { background: var(--rpt-accent); border-color: var(--rpt-accent); color: #fff; }

        /* ── Sub Tabs ── */
        .rpt-subs {
            display: none; gap: 0; margin-bottom: 18px;
            background: var(--rpt-surface);
            border: 1px solid var(--rpt-border-light);
            border-radius: var(--rpt-radius-lg);
            box-shadow: var(--rpt-shadow);
            padding: 4px; overflow-x: auto;
        }
        .rpt-subs.visible { display: flex; animation: rptSlideDown 0.2s ease; }
        .rpt-sub {
            padding: 8px 18px; border: none; border-radius: var(--rpt-radius);
            font-family: inherit; font-size: 0.76rem; font-weight: 600;
            color: var(--rpt-text-secondary); background: transparent;
            cursor: pointer; transition: background 0.12s, color 0.12s; white-space: nowrap;
        }
        .rpt-sub:hover { background: var(--rpt-border-light); color: var(--rpt-text); }
        .rpt-sub.active { background: var(--rpt-accent); color: #fff; }

        /* ── Filter Bar ── */
        .rpt-filter {
            display: none; align-items: flex-end; gap: 16px; flex-wrap: wrap;
            padding: 16px 20px; background: var(--rpt-surface);
            border: 1px solid var(--rpt-border-light);
            border-radius: var(--rpt-radius-lg);
            box-shadow: var(--rpt-shadow); margin-bottom: 18px;
        }
        .rpt-filter.visible { display: flex; animation: rptSlideDown 0.2s ease; }
        .rpt-filter-top { width: 100%; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
        .rpt-filter-fields { display: flex; gap: 14px; flex-wrap: wrap; }
        .rpt-field { display: flex; flex-direction: column; gap: 5px; }
        .rpt-label { font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--rpt-text-subtle); }
        .rpt-field input[type="date"] {
            height: 36px; padding: 0 10px; border: 1.5px solid var(--rpt-border);
            border-radius: var(--rpt-radius); font-family: inherit; font-size: 0.82rem;
            color: var(--rpt-text); background: var(--rpt-surface); transition: border-color 0.15s, box-shadow 0.15s;
        }
        .rpt-field input[type="date"]:focus { outline: none; border-color: var(--rpt-accent); box-shadow: 0 0 0 3px rgba(13,42,122,0.07); }
        .rpt-spacer { flex: 1; }
        .rpt-filter-actions { display: flex; gap: 8px; }

        /* ── Date Presets ── */
        .rpt-presets {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .rpt-preset-label {
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--rpt-text-subtle);
            margin-right: 4px;
        }
        .rpt-preset {
            padding: 5px 12px;
            border: 1.5px solid var(--rpt-border);
            border-radius: 99px;
            font-family: inherit;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--rpt-text-secondary);
            background: var(--rpt-surface);
            cursor: pointer;
            transition: all 0.12s ease;
            white-space: nowrap;
        }
        .rpt-preset:hover {
            border-color: var(--rpt-accent);
            color: var(--rpt-accent);
            background: var(--rpt-accent-subtle);
        }
        .rpt-preset.active {
            border-color: var(--rpt-accent);
            background: var(--rpt-accent);
            color: #fff;
        }

        /* ── Buttons ── */
        .rpt-btn {
            height: 36px; padding: 0 16px; border-radius: var(--rpt-radius);
            font-family: inherit; font-size: 0.82rem; font-weight: 600;
            cursor: pointer; border: 1.5px solid;
            display: inline-flex; align-items: center; gap: 6px;
            white-space: nowrap; transition: all 0.15s;
        }
        .rpt-btn i { font-size: 12px; }
        .rpt-btn-primary { background: var(--rpt-accent); color: #fff; border-color: var(--rpt-accent); }
        .rpt-btn-primary:hover { background: var(--rpt-accent-hover); border-color: var(--rpt-accent-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(13,42,122,0.2); }
        .rpt-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .rpt-btn-outline { background: var(--rpt-surface); color: var(--rpt-text); border-color: var(--rpt-border); }
        .rpt-btn-outline:hover { background: var(--rpt-border-light); border-color: #cbd5e1; }
        .rpt-btn-ghost { background: transparent; color: var(--rpt-text-secondary); border-color: transparent; padding: 0 10px; }
        .rpt-btn-ghost:hover { color: var(--rpt-text); background: var(--rpt-border-light); }

        /* ── Export Dropdown ── */
                /* ── Export Dropdown — CRITICAL FIX ── */
        .rpt-export-wrap {
            position: relative;
            z-index: 50;
        }
        .rpt-chevron {
            font-size: 9px;
            margin-left: 2px;
            transition: transform 0.2s;
        }
        .rpt-export-wrap.open .rpt-chevron {
            transform: rotate(180deg);
        }
        .rpt-export-menu {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            min-width: 210px;
            background: var(--rpt-surface);
            border: 1px solid var(--rpt-border);
            border-radius: var(--rpt-radius-lg);
            padding: 4px;
            box-shadow: 0 12px 32px rgba(15,23,42,0.15), 0 2px 6px rgba(15,23,42,0.08);
            display: none;
            z-index: 9999;
        }
        .rpt-export-menu.open {
            display: block;
            animation: rptDdIn 0.15s ease;
        }
        .rpt-export-menu button {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 9px 12px;
            border: none;
            background: none;
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--rpt-text);
            cursor: pointer;
            border-radius: var(--rpt-radius);
            transition: background 0.1s;
            text-align: left;
        }
        .rpt-export-menu button:hover {
            background: var(--rpt-border-light);
        }
        .rpt-export-menu button i {
            width: 18px;
            text-align: center;
            font-size: 13px;
            color: var(--rpt-text-subtle);
        }
        .rpt-export-menu button[data-format="pdf"] i { color: var(--rpt-red); }
        .rpt-export-menu button[data-format="xlsx"] i { color: var(--rpt-green); }
        .rpt-export-menu button[data-format="print"] i { color: var(--rpt-accent); }
        .rpt-export-sep {
            height: 1px;
            background: var(--rpt-border-light);
            margin: 4px 8px;
        }

        /* ── Results panel — NO overflow:hidden ── */
        .rpt-results {
            display: none;
            background: var(--rpt-surface);
            border: 1px solid var(--rpt-border-light);
            border-radius: var(--rpt-radius-lg);
            box-shadow: var(--rpt-shadow);
        }
        .rpt-results.visible {
            display: block;
            animation: rptSlideDown 0.25s ease;
        }
        .rpt-results-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid var(--rpt-border);
            background: #fafbfc;
            border-radius: var(--rpt-radius-lg) var(--rpt-radius-lg) 0 0;
        }
        .rpt-results-meta { display: flex; align-items: baseline; gap: 10px; }
        .rpt-results-meta h3 { font-size: 0.95rem; font-weight: 700; color: var(--rpt-text); letter-spacing: -0.01em; }
        .rpt-results-count { font-size: 0.75rem; font-weight: 600; color: var(--rpt-text-subtle); }
        .rpt-results-actions { display: flex; align-items: center; gap: 8px; }

        /* ── Table ── */
        .rpt-table-scroll { overflow-x: auto; max-height: 600px; overflow-y: auto; }
        .rpt-table-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .rpt-table-scroll::-webkit-scrollbar-thumb { background: var(--rpt-border); border-radius: 3px; }
        .rpt-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .rpt-table th {
            position: sticky; top: 0; background: #f4f6fa;
            font-size: 0.68rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--rpt-text-secondary);
            padding: 11px 16px; text-align: left; white-space: nowrap;
            border-bottom: 2px solid var(--rpt-border); z-index: 2;
        }
        .rpt-table td {
            padding: 10px 16px; border-bottom: 1px solid var(--rpt-border-light);
            white-space: normal; word-wrap: break-word; vertical-align: top; color: var(--rpt-text);
        }
        .rpt-table tbody tr:hover td { background: #f8f9fc; }
        .rpt-table .rpt-na { color: var(--rpt-text-subtle); }
        .rpt-table a { color: var(--rpt-accent); text-decoration: none; font-weight: 600; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 4px; }
        .rpt-table a:hover { text-decoration: underline; }

        /* ── States ── */
        .rpt-state { text-align: center; padding: 56px 24px; }
        .rpt-state-icon {
            width: 52px; height: 52px; border-radius: 50%;
            background: var(--rpt-accent-subtle); display: flex;
            align-items: center; justify-content: center;
            margin: 0 auto 16px; font-size: 20px; color: var(--rpt-accent);
        }
        .rpt-state-icon.state-error { background: var(--rpt-red-bg); color: var(--rpt-red); }
        .rpt-state-spinner {
            width: 34px; height: 34px;
            border: 3px solid var(--rpt-border); border-top-color: var(--rpt-accent);
            border-radius: 50%; margin: 0 auto; animation: rptSpin 0.7s linear infinite;
        }
        .rpt-state h4 { font-size: 0.88rem; font-weight: 700; color: var(--rpt-text); margin-bottom: 4px; }
        .rpt-state p { font-size: 0.82rem; color: var(--rpt-text-subtle); max-width: 280px; margin: 0 auto; line-height: 1.5; }

        /* ── Toast ── */
        .rpt-toast {
            position: fixed; bottom: 24px; right: 24px;
            background: var(--rpt-text); color: #fff;
            padding: 12px 20px; border-radius: var(--rpt-radius);
            font-size: 0.82rem; font-weight: 600;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            display: flex; align-items: center; gap: 8px;
            z-index: 9999; animation: rptToastIn 0.3s ease;
        }
        .rpt-toast i { font-size: 14px; }
        .rpt-toast.toast-success i { color: #4ade80; }
        .rpt-toast.toast-error i { color: #f87171; }

        @keyframes rptToastIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes rptToastOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(12px); } }

        /* ── Print ── */
        @media print {
            body { background: #fff; }
            .rpt-page { position: static; padding: 20px; left: 0 !important; }
            .rpt-cats, .rpt-subs, .rpt-filter, .rpt-crumb, header, nav, .sidebar,
            #sideNav, .rpt-results-actions, .rpt-toast { display: none !important; }
            .rpt-results { border: none; box-shadow: none; display: block !important; }
            .rpt-results-head { background: #fff; padding: 8px 0; }
            .rpt-table-scroll { max-height: none; overflow: visible; }
            .rpt-table th { background: #eee; position: static; }
        }

        /* ── Animations ── */
        @keyframes rptFadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes rptSlideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes rptDdIn { from { opacity: 0; transform: translateY(-4px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes rptSpin { to { transform: rotate(360deg); } }

        /* ── Responsive ── */
        @media (max-width: 1200px) { .rpt-cats { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .rpt-page { left: 0 !important; padding: 20px 16px; }
            .rpt-cats { grid-template-columns: 1fr; }
            .rpt-filter { flex-direction: column; align-items: stretch; }
            .rpt-filter-fields { flex-direction: column; }
            .rpt-spacer { display: none; }
            .rpt-filter-actions { justify-content: flex-end; }
            .rpt-results-head { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

<main class="rpt-page" id="rptPage">

    <header class="rpt-hdr">
        <div>
            <div class="rpt-crumb">Document Control System / <span>Reports</span></div>
            <h1>Generate Report</h1>
        </div>
    </header>

    <section class="rpt-cats" id="categoryCards">
        @foreach($categories as $key => $cat)
        <button class="rpt-cat" data-category="{{ $key }}" type="button">
            <div class="rpt-cat-icon"><i class="{{ $cat['icon'] }}"></i></div>
            <div class="rpt-cat-body">
                <span class="rpt-cat-title">{{ $cat['label'] }}</span>
                <span class="rpt-cat-meta">{{ count($cat['subs']) }} report type{{ count($cat['subs']) > 1 ? 's' : '' }} available</span>
            </div>
            <div class="rpt-cat-check"><i class="fa-solid fa-check"></i></div>
        </button>
        @endforeach
    </section>

    <nav class="rpt-subs" id="subTabs"></nav>

    <div class="rpt-filter" id="filterBar">
        {{-- Date range presets --}}
        <div class="rpt-filter-top">
            <span class="rpt-preset-label">Quick Range:</span>
            <button class="rpt-preset" data-months="1" type="button">Last 30 Days</button>
            <button class="rpt-preset" data-months="3" type="button">Last 3 Months</button>
            <button class="rpt-preset active" data-months="6" type="button">Last 6 Months</button>
            <button class="rpt-preset" data-months="12" type="button">This Year</button>
            <button class="rpt-preset" data-months="0" type="button">All Time</button>
        </div>

        <div class="rpt-filter-fields">
            <label class="rpt-field">
                <span class="rpt-label">Date From</span>
                <input type="date" id="filterDateFrom">
            </label>
            <label class="rpt-field">
                <span class="rpt-label">Date To</span>
                <input type="date" id="filterDateTo">
            </label>
        </div>
        <div class="rpt-spacer"></div>
        <div class="rpt-filter-actions">
            <button class="rpt-btn rpt-btn-ghost" id="resetBtn" type="button">
                <i class="fa-solid fa-arrow-rotate-left"></i> Reset
            </button>
            <button class="rpt-btn rpt-btn-primary" id="generateBtn" type="button">
                <i class="fa-solid fa-magnifying-glass-chart"></i> Generate
            </button>
        </div>
    </div>

    <section class="rpt-results" id="resultsPanel">
        <div class="rpt-results-head">
            <div class="rpt-results-meta">
                <h3 id="resultsTitle">Report</h3>
                <span class="rpt-results-count" id="resultsCount"></span>
            </div>
            <div class="rpt-results-actions">
                <div class="rpt-export-wrap" id="exportDropdown">
                    <button class="rpt-btn rpt-btn-outline" id="exportBtn" type="button">
                        <i class="fa-solid fa-download"></i> Export
                        <i class="fa-solid fa-chevron-down rpt-chevron"></i>
                    </button>
                    <div class="rpt-export-menu" id="exportMenu">
                        <button type="button" data-format="pdf">
                            <i class="fa-solid fa-file-pdf"></i> Download as PDF
                        </button>
                        <button type="button" data-format="xlsx">
                            <i class="fa-solid fa-file-excel"></i> Download as Excel (.csv)
                        </button>
                        <div class="rpt-export-sep"></div>
                        <button type="button" data-format="print">
                            <i class="fa-solid fa-print"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="rpt-table-scroll">
            <table class="rpt-table" id="reportTable">
                <thead id="reportHead"></thead>
                <tbody id="reportBody"></tbody>
            </table>
        </div>
    </section>

</main>

<script>
    const CATEGORIES = @json($categories);

    let currentCategory = null;
    let currentSub = null;
    let lastGeneratedParams = null;

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
            // All Time — clear dates
            dateFromInput.value = '';
            dateToInput.value = '';
        } else {
            const from = new Date();
            from.setMonth(from.getMonth() - months);
            dateFromInput.value = getISO(from);
            dateToInput.value = getISO(today);
        }
    }

    // Initialize with 6-month default
    applyPreset(6);

    presetBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            presetBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            applyPreset(parseInt(btn.dataset.months));
        });
    });

    // Clear active preset when user manually edits dates
    dateFromInput.addEventListener('input', () => {
        presetBtns.forEach(b => b.classList.remove('active'));
    });
    dateToInput.addEventListener('input', () => {
        presetBtns.forEach(b => b.classList.remove('active'));
    });

    // ═══════════════════════════════════════════
    // CATEGORY SELECTION
    // ═══════════════════════════════════════════
    categoryCards.addEventListener('click', (e) => {
        const card = e.target.closest('.rpt-cat');
        if (!card) return;

        document.querySelectorAll('.rpt-cat').forEach(c => c.classList.remove('active'));
        card.classList.add('active');

        currentCategory = card.dataset.category;
        currentSub = null;

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
        // ═══════════════════════════════════════════
    // GENERATE
    // ═══════════════════════════════════════════
    generateBtn.addEventListener('click', async () => {
        if (!currentCategory) {
            showToast('error', 'Please select a report category first.');
            return;
        }

        const params = buildParams();

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
            resultsCount.textContent = json.total_rows + ' record' + (json.total_rows !== 1 ? 's' : '');

            const cols = json.columns;
            const colKeys = Object.keys(cols);

            reportHead.innerHTML = '<tr>' +
                Object.values(cols).map(h => '<th>' + esc(h) + '</th>').join('') +
                '</tr>';

            if (!json.rows || json.rows.length === 0) {
                reportBody.innerHTML =
                    '<tr><td colspan="' + colKeys.length + '"><div class="rpt-state">' +
                    '<div class="rpt-state-icon"><i class="fa-solid fa-inbox"></i></div>' +
                    '<h4>No records found</h4>' +
                    '<p>Try adjusting your filters or date range</p>' +
                    '</div></td></tr>';
                // Still set params so export works (will export empty)
                lastGeneratedParams = params.toString();
                return;
            }

            reportBody.innerHTML = json.rows.map(row => {
                return '<tr>' + colKeys.map(key => {
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

            // ── THIS IS THE KEY LINE — must run after data loads ──
            lastGeneratedParams = params.toString();

            console.log('Export ready. Params:', lastGeneratedParams);

        } catch (e) {
            console.error(e);
            reportBody.innerHTML =
                '<tr><td colspan="20"><div class="rpt-state">' +
                '<div class="rpt-state-icon state-error"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
                '<h4>Connection error</h4>' +
                '<p>Failed to load report data.</p>' +
                '</div></td></tr>';
            resultsTitle.textContent = 'Error';
        }
    });

    // ═══════════════════════════════════════════
    // EXPORT DROPDOWN
    // ═══════════════════════════════════════════
        // ═══════════════════════════════════════════
    // EXPORT — dropdown toggle
    // ═══════════════════════════════════════════
    exportBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        const menu = document.getElementById('exportMenu');
        const wrap = document.getElementById('exportDropdown');

        if (menu.classList.contains('open')) {
            menu.classList.remove('open');
            wrap.classList.remove('open');
        } else {
            menu.classList.add('open');
            wrap.classList.add('open');
        }

        console.log('Export menu toggled. Visible:', menu.classList.contains('open'));
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        const wrap = document.getElementById('exportDropdown');
        const menu = document.getElementById('exportMenu');
        if (!wrap.contains(e.target)) {
            menu.classList.remove('open');
            wrap.classList.remove('open');
        }
    });

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const menu = document.getElementById('exportMenu');
            const wrap = document.getElementById('exportDropdown');
            menu.classList.remove('open');
            wrap.classList.remove('open');
        }
    });

    // ═══════════════════════════════════════════
    // EXPORT — format actions
    // ═══════════════════════════════════════════
    document.querySelectorAll('#exportMenu button[data-format]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const format = this.dataset.format;

            // Close menu
            document.getElementById('exportMenu').classList.remove('open');
            document.getElementById('exportDropdown').classList.remove('open');

            if (!lastGeneratedParams) {
                showToast('error', 'Generate a report first before exporting.');
                return;
            }

            var url = '/reports/export?' + lastGeneratedParams + '&format=' + format;

            if (format === 'print') {
                window.print();
                return;
            }

            showToast('success', 'Downloading ' + (format === 'pdf' ? 'PDF' : 'Excel file') + '...');

            var a = document.createElement('a');
            a.href = url;
            a.download = '';
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            setTimeout(function () { a.remove(); }, 1000);
        });
    });

    // ═══════════════════════════════════════════
    // EXPORT ACTIONS — server-side generation
    // ═══════════════════════════════════════════
    exportMenu.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-format]');
        if (!btn) return;

        const format = btn.dataset.format;
        exportDropdown.classList.remove('open');

        if (!lastGeneratedParams) {
            showToast('error', 'Generate a report first before exporting.');
            return;
        }

        const url = '/reports/export?' + lastGeneratedParams + '&format=' + format;

        if (format === 'print') {
            // Print the current page
            window.print();
            return;
        }

        if (format === 'pdf') {
            // Open print-friendly page — browser's Save as PDF
            showToast('success', 'Opening print view...');
            window.open(url, '_blank');
            return;
        }

        if (format === 'xlsx') {
            // CSV download
            showToast('success', 'Downloading Excel file...');
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
        currentCategory = null;
        currentSub = null;
        lastGeneratedParams = null;
        document.querySelectorAll('.rpt-cat').forEach(c => c.classList.remove('active'));
        subTabs.innerHTML = '';
        subTabs.classList.remove('visible');
        filterBar.classList.remove('visible');
        resultsPanel.classList.remove('visible');
        exportDropdown.classList.remove('open');
        dateFromInput.value = '';
        dateToInput.value = '';

        // Reset presets to 6-month default
        presetBtns.forEach(b => b.classList.remove('active'));
        document.querySelector('.rpt-preset[data-months="6"]').classList.add('active');
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
</script>

</body>
</html>