{{-- Inline report filters — shown after a document type is selected. No Apply button; changes auto-refresh preview. --}}
<section class="rpt-inline-filters" id="inlineFilters" hidden aria-label="Report filters">
    <form id="filterForm" class="rpt-inline-filters-form" autocomplete="off">

        <div class="rpt-period-row">
            <div class="rpt-filter-group">
                <label for="filterPeriod">Report period</label>
                <select name="period" id="filterPeriod">
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="annually" selected>Annually</option>
                    <option value="custom">Custom range</option>
                </select>
            </div>
            <div class="rpt-filter-group">
                <label for="filterAsOf">As of</label>
                <input type="date" name="as_of" id="filterAsOf">
                <small class="rpt-filter-hint">Reference date for monthly / quarterly / annual period</small>
            </div>
        </div>

        <div class="rpt-inline-filters-grid rpt-custom-dates" id="customDateRow" hidden>
            <div class="rpt-filter-group">
                <label for="filterDateFrom">Date from</label>
                <input type="date" name="date_from" id="filterDateFrom">
            </div>
            <div class="rpt-filter-group">
                <label for="filterDateTo">Date to</label>
                <input type="date" id="filterDateTo" name="date_to">
            </div>
        </div>

        <div class="rpt-inline-filters-grid">
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
                <label for="filterStatus">Revision</label>
                <select name="revision_status" id="filterStatus">
                    <option value="all">All revisions</option>
                    <option value="latest">Latest only</option>
                    <option value="obsolete">Obsolete only</option>
                </select>
            </div>

            <div class="rpt-filter-group">
                <label for="filterRevNo">Revision No.</label>
                <input type="number" name="rev_no" id="filterRevNo" min="0" placeholder="Any">
            </div>
        </div>

        <div class="rpt-period-summary" id="periodSummary" aria-live="polite"></div>

        <div class="rpt-subtype-block" id="subtypeBlock" hidden>
            <div class="rpt-subtype-head">
                <span class="rpt-subtype-title">Sub-types</span>
                <small class="rpt-filter-hint">Leave all checked to include every document under this type</small>
                <button type="button" class="rpt-link-btn" id="subtypeSelectAll" hidden>Select all</button>
                <button type="button" class="rpt-link-btn" id="subtypeClearAll" hidden>Clear</button>
            </div>
            <div class="rpt-subtype-grid" id="subtypeCheckboxes" role="group" aria-label="Document sub-types"></div>
        </div>
    </form>
</section>
