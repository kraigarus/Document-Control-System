<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - DCS - Monitoring Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/dcs/reports.css', 'resources/css/dcs/sidebar.css', 'resources/js/dcs/sidebar.js', 'resources/js/dcs/monitoring.js'])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

@include('partials.filter-panel')

<div class="rpt-filter visible" id="filterBar" style="justify-content:flex-end;">
    <div class="rpt-filter-actions">
        <button class="rpt-btn rpt-btn-ghost" id="openFilterBtn" type="button">
            <i class="fa-solid fa-filter"></i> Filters
        </button>
    </div>
</div>

<main class="rpt-page" id="rptPage">

    <header class="rpt-hdr">
        <div>
            <div class="rpt-crumb">Document Control System / Generate Report /<span> Monitoring Reports</span></div>
            <h1>Monitoring Reports</h1>
        </div>
    </header>

    {{-- Sub-tabs: click to auto-load --}}
    <nav class="rpt-subs visible" id="monSubTabs">
        <button class="rpt-sub active" data-sub="internal_docs" type="button">Internal</button>
        <button class="rpt-sub" data-sub="external_docs" type="button">External</button>
        <button class="rpt-sub" data-sub="internal_forms" type="button">Internal Forms</button>
        <button class="rpt-sub" data-sub="forms" type="button">Forms</button>
        <button class="rpt-sub" data-sub="logbooks" type="button">Logbooks</button>
        <button class="rpt-sub" data-sub="drf" type="button">DRF</button>
        <button class="rpt-sub" data-sub="dcn" type="button">DCN</button>
    </nav>

    {{-- Results --}}
    <section class="rpt-results visible" id="monResults">
        <div class="rpt-results-head">
            <div class="rpt-results-meta">
                <h3 id="monTitle">Loading...</h3>
                <span class="rpt-results-count" id="monCount"></span>
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
            <table class="rpt-table" id="monTable">
                <thead id="monHead"></thead>
                <tbody id="monBody">
                    <tr><td colspan="20"><div class="rpt-state">
                        <div class="rpt-state-spinner"></div>
                        <h4 style="margin-top:18px;">Loading report...</h4>
                    </div></td></tr>
                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>