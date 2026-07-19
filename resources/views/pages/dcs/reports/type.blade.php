<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/dcs/reports.css', 'resources/js/dcs/reports.js'])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

<main class="rpt-page" id="rptPage">

    <header class="rpt-hdr">
        <div>
            <div class="rpt-crumb">Document Control System / Generate Report / <span>{{ $categories[$activeCategory]['label'] }}</span></div>
            <h1>{{ $categories[$activeCategory]['label'] }}</h1>
        </div>
    </header>

    {{-- Hidden data for JS --}}
    <div id="categoryCards" style="display:none;"></div>

    <nav class="rpt-subs visible" id="subTabs"></nav>

    <div class="rpt-filter visible" id="filterBar">
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
    window.CATEGORIES = @json($categories);
    window.ACTIVE_CATEGORY = '{{ $activeCategory }}';
</script>

</body>
</html>