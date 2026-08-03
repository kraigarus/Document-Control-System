

<div class="rpt-filter-overlay" id="filterOverlay"></div>

<aside class="rpt-filter-panel" id="filterPanel" aria-hidden="true">
    <div class="rpt-filter-panel-head">
        <h3>Filter Report</h3>
        <button type="button" class="rpt-filter-close" id="closeFilterBtn" aria-label="Close filters">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <form id="filterForm" class="rpt-filter-form">

        <div class="rpt-filter-group">
            <label>Date Range</label>
            <div class="rpt-filter-row">
                <input type="date" name="date_from" id="filterDateFrom">
                <span class="rpt-filter-sep">to</span>
                <input type="date" name="date_to" id="filterDateTo">
            </div>
        </div>

        @if(isset($originators))
        <div class="rpt-filter-group">
            <label for="filterOriginator">Originator</label>
            <select name="originator" id="filterOriginator">
                <option value="">All Originators</option>
                @foreach($originators as $o)
                    <option value="{{ $o->originator_name }}">{{ $o->originator_name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        @if(isset($offices))
        <div class="rpt-filter-group">
            <label for="filterOffice">Source Office</label>
            <select name="source_unit" id="filterOffice">
                <option value="">All Offices</option>
                @foreach($offices as $o)
                    <option value="{{ $o->office_id }}">{{ $o->office_name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="rpt-filter-group">
            <label for="filterStatus">Status</label>
            <select name="status" id="filterStatus">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="released">Released</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <div class="rpt-filter-group">
            <label for="filterRevNo">Revision No.</label>
            <input type="number" name="rev_no" id="filterRevNo" min="0" placeholder="e.g. 0, 1, 2...">
        </div>

        {{-- ═══════════════════════════════════════
             DATABASE-ONLY optional fields.
             Only rendered when the controller
             passes the relevant data/flag.
             ═══════════════════════════════════════ --}}

        @if(isset($subTypes) && $subTypes->count() > 0)
        <div class="rpt-filter-group">
            <label for="filterSubType">Sub-type</label>
            <select name="sub_type_id" id="filterSubType">
                <option value="">All Sub-types</option>
                @foreach($subTypes as $sub)
                    <option value="{{ $sub->doc_type_id }}">{{ $sub->doc_type_name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        @if(isset($showRevisionScope) && $showRevisionScope)
        <div class="rpt-filter-group">
            <label for="filterRevisionScope">Revision Scope</label>
            <select name="revision_scope" id="filterRevisionScope">
                <option value="">All Documents</option>
                <option value="obsolete">Has Obsolete Revisions</option>
            </select>
        </div>
        @endif

    </form>

    <div class="rpt-filter-panel-foot">
        <button type="button" class="rpt-btn rpt-btn-ghost" id="clearFilterBtn">Clear All</button>
        <button type="button" class="rpt-btn rpt-btn-primary" id="applyFilterBtn">Apply Filters</button>
    </div>
</aside>