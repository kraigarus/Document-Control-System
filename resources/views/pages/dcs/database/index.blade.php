<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/dcs/database.css'])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

<div class="db-filter-overlay" id="filterOverlay"></div>
<aside class="db-filter-panel" id="filterPanel">
    <div class="db-filter-head">
        <h3>Filters</h3>
        <button class="db-close-btn" id="closeFilterBtn">&times;</button>
    </div>
    <div class="db-filter-body">
        <div class="db-filter-group">
        <label>Sub-type</label>
        <select id="filterSubType">
            <option value="all">All Sub-types</option>
            @foreach($subTypes ?? [] as $sub)
                <option value="{{ $sub->id }}">{{ $sub->doc_type_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="db-filter-group">
        <label>Originator</label>
        <select id="filterOriginator">
            <option value="">All Originators</option>
            @foreach($originators as $orig)
                <option value="{{ $orig->originator_name }}">{{ $orig->originator_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="db-filter-group">
        <label>Source Unit</label>
        <select id="filterSourceUnit">
            <option value="">All Units</option>
            @foreach($offices ?? [] as $office)
                <option value="{{ $office->id }}">{{ $office->office_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="db-filter-group">
        <label>Approval Status</label>
        <select id="filterStatus">
            <option value="">Any</option>
            <option value="applicable">Applicable</option>
            <option value="not_applicable">Not Applicable</option>
        </select>
    </div>
    <div class="db-filter-group">
        <label>Revision</label>
        <select id="filterRevisionScope">
            <option value="all">All Documents</option>
            <option value="obsolete">Has Obsolete Revisions</option>
        </select>
    </div>
    <div class="db-filter-group">
        <label>Revision Status</label>
        <select id="filterRevisionStatus">
            <option value="all">All</option>
            <option value="latest">Latest Only</option>
            <option value="obsolete">Obsolete Only</option>
        </select>
    </div>
    <div class="db-filter-group">
        <label>Effectivity Date From</label>
        <input type="date" id="filterDateFrom">
    </div>
    <div class="db-filter-group">
        <label>Effectivity Date To</label>
        <input type="date" id="filterDateTo">
    </div>
    <div class="db-filter-group">
        <label>Revision No</label>
        <input type="text" id="filterRevNo" placeholder="e.g. 3">
    </div>
    </div>
    <div class="db-filter-foot">
        <button class="db-btn db-btn-ghost" id="resetFilterBtn">Reset</button>
        <button class="db-btn db-btn-primary" id="applyFilterBtn">Apply Filters</button>
    </div>
</aside>
    
<main class="db-page">

    <header class="db-header">
        <div class="db-header-left">
            <div class="db-breadcrumb">Document Control System / <span>Inventory</span></div>
            <h1>Inventory</h1>
        </div>
        <div class="db-header-right">
            <span class="db-count-badge" id="docCount">Loading...</span>
        </div>
    </header>

    <section class="db-controls">
        <div class="db-type-grid" id="docTypeGrid">
            <button class="db-type-btn active" data-type-id="all">ALL</button>
            @foreach($docTypes ?? [] as $type)
                <button class="db-type-btn" data-type-id="{{ $type->id }}">{{ strtoupper($type->doc_type_name) }}</button>
            @endforeach
        </div>
        <div class="db-controls-right">
            <div class="db-search-wrap">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" id="dbSearch" placeholder="Search documents..." autocomplete="off">
            </div>
            <button class="db-collapse-btn" id="collapseAllBtn" title="Collapse all columns">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="4 14 10 14 10 20"/>
                    <polyline points="20 10 14 10 14 4"/>
                    <line x1="14" y1="10" x2="21" y2="3"/>
                    <line x1="3" y1="21" x2="10" y2="14"/>
                </svg>
                <span id="collapseBtnLabel" class="btn-label">Collapse</span>
            </button>
            <button class="db-filter-btn" id="openFilterBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                <span class="btn-label">Filter</span>
            </button>
            <button class="db-export-btn" id="exportBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span class="btn-label">Export</span>
            </button>
        </div>
    </section>
    
    {{-- TABLE --}}
    <section class="db-table-wrap">
        <div class="db-table-scroll">
            <table class="db-table" id="inventoryTable">
                <thead>
                    <tr class="db-head-primary">
                        <th rowspan="3" class="db-sticky-col db-sticky-1">ITEM NO.</th>
                        <th rowspan="3" class="db-sticky-col db-sticky-2">DOCUMENT NO.</th>
                        <th rowspan="3" class="db-sticky-col db-sticky-3">REV.</th>
                        <th rowspan="3" class="db-sticky-col db-sticky-4">DOCUMENT TITLE</th>
                        <th rowspan="3">EFFECTIVITY DATE</th>
                        <th rowspan="3">ORIGINATOR</th>
                        <th rowspan="3">PAGES</th>
                        <th rowspan="3">STATUS</th>
                        <th rowspan="3">PDF FILE</th>
                        <th rowspan="3">SOURCE UNIT</th>
                        <th rowspan="3">RELATED DOCS</th>

                        <th rowspan="3" class="col-group-summary collapsed" data-group="approval">APPROVAL <span class="collapse-arrow">&#9654;</span></th>
                        <th colspan="2" class="col-group-approval col-group-expanded">APPROVAL <span class="collapse-arrow" data-toggle="approval">&#9664;</span></th>

                        <th rowspan="3" class="col-group-summary collapsed" data-group="deadline">DEADLINE <span class="collapse-arrow">&#9654;</span></th>
                        <th colspan="2" class="col-group-deadline col-group-expanded">DEADLINE <span class="collapse-arrow" data-toggle="deadline">&#9664;</span></th>

                        <th rowspan="3" class="col-group-summary collapsed" data-group="masterlist">MASTERLIST <span class="collapse-arrow">&#9654;</span></th>
                        <th colspan="4" class="col-group-masterlist col-group-expanded">MASTERLIST REGISTRATION <span class="collapse-arrow" data-toggle="masterlist">&#9664;</span></th>

                        <th rowspan="3" class="col-group-summary collapsed" data-group="dcn">DCN <span class="collapse-arrow">&#9654;</span></th>
                        <th colspan="6" class="col-group-dcn col-group-expanded">DOCUMENT CHANGE NOTICE <span class="collapse-arrow" data-toggle="dcn">&#9664;</span></th>

                        <th rowspan="3" class="col-group-summary collapsed" data-group="drf">DRF <span class="collapse-arrow">&#9654;</span></th>
                        <th colspan="5" class="col-group-drf col-group-expanded">DOCUMENT REQUEST FORM <span class="collapse-arrow" data-toggle="drf">&#9664;</span></th>

                        <th rowspan="3" class="col-group-summary collapsed" data-group="distribution">DISTRIBUTION <span class="collapse-arrow">&#9654;</span></th>
                        <th colspan="6" class="col-group-distribution col-group-expanded">DISTRIBUTION <span class="collapse-arrow" data-toggle="distribution">&#9664;</span></th>

                        <th rowspan="3" class="col-group-summary collapsed" data-group="retrieval">RETRIEVAL <span class="collapse-arrow">&#9654;</span></th>
                        <th colspan="4" class="col-group-retrieval col-group-expanded">RETRIEVAL <span class="collapse-arrow" data-toggle="retrieval">&#9664;</span></th>
                    </tr>

                    <tr class="db-head-secondary">
                        <th colspan="2" class="col-group-approval col-group-expanded">BOT/ADCO APPROVAL</th>
                        <th rowspan="2" class="col-group-deadline col-group-expanded">DATE</th>
                        <th rowspan="2" class="col-group-deadline col-group-expanded">DAY DIFF</th>
                        <th colspan="2" class="col-group-masterlist col-group-expanded">DOCUMENT RECEIPT</th>
                        <th colspan="2" class="col-group-masterlist col-group-expanded">DOCUMENT REGISTERED</th>
                        <th rowspan="2" class="col-group-dcn col-group-expanded">DCN NO.</th>
                        <th rowspan="2" class="col-group-dcn col-group-expanded">DCN DATE</th>
                        <th colspan="2" class="col-group-dcn col-group-expanded">DCN RECEIPT (ACTUAL)</th>
                        <th rowspan="2" class="col-group-dcn col-group-expanded">PURPOSE OF REVISION</th>
                        <th rowspan="2" class="col-group-dcn col-group-expanded">SCANNED DCN</th>
                        <th rowspan="2" class="col-group-drf col-group-expanded">DRF NO.</th>
                        <th rowspan="2" class="col-group-drf col-group-expanded">DRF DATE</th>
                        <th colspan="2" class="col-group-drf col-group-expanded">DRF RECEIPT</th>
                        <th rowspan="2" class="col-group-drf col-group-expanded">SCANNED DRF</th>
                        <th colspan="2" class="col-group-distribution col-group-expanded">DISTRIBUTION (ON FILE)</th>
                        <th colspan="2" class="col-group-distribution col-group-expanded">DISTRIBUTION (ACTUAL)</th>
                        <th rowspan="2" class="col-group-distribution col-group-expanded">RECEIVING OFFICE(S)</th>
                        <th rowspan="2" class="col-group-distribution col-group-expanded">SCANNED DIST.</th>
                        <th colspan="2" class="col-group-retrieval col-group-expanded">DATE</th>
                        <th rowspan="2" class="col-group-retrieval col-group-expanded">RETRIEVED OFFICE(S)</th>
                        <th rowspan="2" class="col-group-retrieval col-group-expanded">SCANNED RET.</th>
                    </tr>

                    <tr class="db-head-tertiary">
                        <th class="col-group-approval col-group-expanded">NO.</th>
                        <th class="col-group-approval col-group-expanded">APPV. DATE</th>
                        <th class="col-group-masterlist col-group-expanded">DATE</th>
                        <th class="col-group-masterlist col-group-expanded">TIME</th>
                        <th class="col-group-masterlist col-group-expanded">DATE</th>
                        <th class="col-group-masterlist col-group-expanded">TIME</th>
                        <th class="col-group-dcn col-group-expanded">DATE</th>
                        <th class="col-group-dcn col-group-expanded">TIME</th>
                        <th class="col-group-drf col-group-expanded">DATE</th>
                        <th class="col-group-drf col-group-expanded">TIME</th>
                        <th class="col-group-distribution col-group-expanded">DATE</th>
                        <th class="col-group-distribution col-group-expanded">TIME</th>
                        <th class="col-group-distribution col-group-expanded">DATE</th>
                        <th class="col-group-distribution col-group-expanded">TIME</th>
                        <th class="col-group-retrieval col-group-expanded">ON FILE</th>
                        <th class="col-group-retrieval col-group-expanded">ACTUAL</th>
                    </tr>
                </thead>

                <tbody id="tableBody">
                </tbody>
            </table>
        </div>

        <div class="db-empty" id="emptyState" style="display:none">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
            <h3>No documents found</h3>
            <p>Try adjusting your search or filters</p>
        </div>

    </section>

</main>

@vite(['resources/js/dcs/database.js'])

</body>
</html>