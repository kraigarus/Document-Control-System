<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Masterlist Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite([
        'resources/css/dcs/reports.css',
        'resources/js/dcs/masterlist-report.js'
    ])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

    <div class="rpt-container">
        <!-- Header -->
        <div class="rpt-header">
            <div>
                <div class="rpt-breadcrumb">
                    <a href="{{ route('generate-report.report') }}">Reports</a> / Masterlist
                </div>
                <div class="rpt-title">Masterlist Report</div>
            </div>
            <a href="{{ route('generate-report.report') }}" class="rpt-btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Reports
            </a>
        </div>

        <!-- ═══ FILTERS ═══ -->
        <div class="rpt-filter-card">
            <div class="rpt-filter-header">
                <div class="rpt-filter-title">
                    <i class="fa-solid fa-filter"></i>
                    <span>Filters</span>
                </div>
                <button type="button" class="rpt-btn-clear-filters" onclick="clearFilters()">
                    <i class="fa-solid fa-rotate-left"></i> Clear Filters
                </button>
            </div>

            <div class="rpt-filter-grid">
                <!-- Category -->
                <div class="rpt-filter-field">
                    <label>Category</label>
                    <select id="filterCategory" class="rpt-filter-input">
                        <option value="">All Categories</option>
                        <option value="internal">Internal</option>
                        <option value="internal_forms">Internal Forms</option>
                        <option value="external">External</option>
                        <option value="forms">Forms</option>
                        <option value="logbooks">Logbooks</option>
                    </select>
                </div>

                <!-- Document Title -->
                <div class="rpt-filter-field">
                    <label>Document Title</label>
                    <input type="text" id="filterTitle" class="rpt-filter-input" placeholder="Search title..." autocomplete="off">
                </div>

                <!-- Document Number -->
                <div class="rpt-filter-field">
                    <label>Document Number</label>
                    <input type="text" id="filterDocNo" class="rpt-filter-input" placeholder="e.g. DOC-001" autocomplete="off">
                </div>

                <!-- Revision Number -->
                <div class="rpt-filter-field">
                    <label>Revision No.</label>
                    <input type="text" id="filterRevNo" class="rpt-filter-input" placeholder="e.g. 3" autocomplete="off">
                </div>

                <!-- Originator -->
                <div class="rpt-filter-field">
                    <label>Originator</label>
                    <input type="text" id="filterOriginator" class="rpt-filter-input" placeholder="Office or unit..." autocomplete="off">
                </div>

                <!-- Effectivity Date From -->
                <div class="rpt-filter-field">
                    <label>Effectivity From</label>
                    <input type="date" id="filterDateFrom" class="rpt-filter-input">
                </div>

                <!-- Effectivity Date To -->
                <div class="rpt-filter-field">
                    <label>Effectivity To</label>
                    <input type="date" id="filterDateTo" class="rpt-filter-input">
                </div>

                <!-- Item Number Range -->
                <div class="rpt-filter-field rpt-filter-field-row">
                    <label>Item No. Range</label>
                    <div class="rpt-range-row">
                        <input type="number" id="filterItemFrom" class="rpt-filter-input" placeholder="From" min="1">
                        <span class="rpt-range-sep">—</span>
                        <input type="number" id="filterItemTo" class="rpt-filter-input" placeholder="To" min="1">
                    </div>
                </div>
            </div>

            <div class="rpt-filter-actions">
                <button type="button" class="rpt-btn-apply" onclick="applyFilters()">
                    <i class="fa-solid fa-magnifying-glass"></i> Apply Filters
                </button>
            </div>
        </div>

        <!-- ═══ RESULTS ═══ -->
        <div class="rpt-results-card">
            <!-- Toolbar -->
            <div class="rpt-results-toolbar">
                <div class="rpt-results-left">
                    <span class="rpt-results-count" id="resultsCount">0 documents</span>
                    <span class="rpt-selected-count" id="selectedCount" style="display:none;">
                        <i class="fa-solid fa-check-circle"></i> <span>0</span> selected
                    </span>
                </div>
                <div class="rpt-results-right">
                    <!-- Report Mode -->
                    <div class="rpt-mode-group">
                        <label class="rpt-mode-radio">
                            <input type="radio" name="reportMode" value="complete" checked>
                            <span class="rpt-mode-label">Complete</span>
                        </label>
                        <label class="rpt-mode-radio">
                            <input type="radio" name="reportMode" value="filtered">
                            <span class="rpt-mode-label">Filtered</span>
                        </label>
                        <label class="rpt-mode-radio">
                            <input type="radio" name="reportMode" value="selected">
                            <span class="rpt-mode-label">Selected</span>
                        </label>
                    </div>

                    <div class="rpt-action-divider"></div>

                    <!-- Actions -->
                    <button type="button" class="rpt-action-btn" onclick="generatePDF()" title="Generate PDF">
                        <i class="fa-solid fa-file-pdf"></i> PDF
                    </button>
                    <button type="button" class="rpt-action-btn" onclick="exportExcel()" title="Export Excel">
                        <i class="fa-solid fa-file-excel"></i> Excel
                    </button>
                    <button type="button" class="rpt-action-btn" onclick="printReport()" title="Print">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="rpt-table-wrap">
                <table class="rpt-data-table" id="masterlistTable">
                    <thead>
                        <tr>
                            <th style="width:40px;">
                                <input type="checkbox" id="selectAll" class="rpt-cb">
                            </th>
                            <th style="width:55px;">Item #</th>
                            <th>Document No.</th>
                            <th>Document Title</th>
                            <th style="width:65px;">Rev</th>
                            <th>Originator</th>
                            <th style="width:120px;">Effectivity</th>
                            <th style="width:80px;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="masterlistBody">
                        <tr class="rpt-empty-row">
                            <td colspan="8">
                                <div class="rpt-empty-state">
                                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                                    <p>Apply filters to load documents</p>
                                    <span>Use the filters above, then click "Apply Filters"</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>