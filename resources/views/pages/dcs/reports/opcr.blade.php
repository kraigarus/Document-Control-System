<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/dcs/reports.css',
    'resources/css/dcs/sidebar.css',
    'resources/js/dcs/sidebar.js',
    'resources/js/dcs/opcr.js'])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

<main class="rpt-page" id="rptPage">

    <header class="rpt-hdr">
        <div>
            <div class="rpt-crumb">Document Control System / Generate Report / <span>OPCR Targets</span></div>
            <h1>OPCR Targets</h1>
        </div>
    </header>

    <nav class="rpt-subs visible" id="opcrSubTabs">
        <button class="rpt-sub active" data-sub="update_masterlist" type="button">Updating of Masterlist</button>
        <button class="rpt-sub" data-sub="issuance_internal" type="button">Issuance of Internal</button>
        <button class="rpt-sub" data-sub="issuance_external" type="button">Issuance of External</button>
        <button class="rpt-sub" data-sub="control_forms" type="button">Controlling of Forms</button>
        <button class="rpt-sub" data-sub="control_logbooks" type="button">Controlling of Logbooks</button>
        <button class="rpt-sub" data-sub="control_internal_forms" type="button">Controlling of Internal Forms</button>
    </nav>

    <section class="rpt-results visible" id="opcrResults">
        <div class="rpt-results-head">
            <div class="rpt-results-meta">
                <h3 id="opcrTitle">Loading...</h3>
                <span class="rpt-results-count" id="opcrCount"></span>
            </div>
            <div class="rpt-results-actions">
                <div class="rpt-export-wrap" id="exportDropdown">
                    <button class="rpt-btn rpt-btn-outline" id="exportBtn" type="button">
                        <i class="fa-solid fa-download"></i> Export
                        <i class="fa-solid fa-chevron-down rpt-chevron"></i>
                    </button>
                    <div class="rpt-export-menu" id="exportMenu">
                        <button type="button" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> Download as PDF</button>
                        <button type="button" data-format="xlsx"><i class="fa-solid fa-file-excel"></i> Download as Excel (.csv)</button>
                        <div class="rpt-export-sep"></div>
                        <button type="button" data-format="print"><i class="fa-solid fa-print"></i> Print Report</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="rpt-table-scroll">
            <table class="rpt-table" id="opcrTable">
                <thead id="opcrHead"></thead>
                <tbody id="opcrBody">
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