<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - DCS - General Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/dcs/reports.css', 
    'resources/css/dcs/sidebar.css', 
    'resources/js/dcs/sidebar.js', 
    'resources/js/dcs/reports.js'])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

@include('partials.filter-panel')

<main class="rpt-page" id="rptPage">

    <header class="rpt-hdr">
        <div>
            <div class="rpt-crumb">Document Control System / Generate Report /<span> General Report</span></div>
            <h1>General Report</h1>
        </div>
    </header>

    {{-- Results --}}
    <section class="rpt-results visible" id="resultsPanel">
        <div class="rpt-results-head">
            <div class="rpt-results-meta">
                <h3 id="resultsTitle">Loading...</h3>
                <span class="rpt-results-count" id="resultsCount"></span>
            </div>
            <div class="rpt-results-actions">
                <button class="rpt-btn rpt-btn-outline" id="openFilterBtn" type="button">
                    <i class="fa-solid fa-filter"></i> Filters
                </button>
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
                <tbody id="reportBody">
                    <tr><td colspan="20"><div class="rpt-state">
                        <div class="rpt-state-spinner"></div>
                        <h4 style="margin-top:18px;">Loading report...</h4>
                    </div></td></tr>
                </tbody>
            </table>
        </div>
    </section>

</main>
<script>
    window.REPORT_CATEGORY = 'others';
</script>
</body>
</html>