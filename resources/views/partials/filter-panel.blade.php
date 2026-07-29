<div class="db-filter-overlay" id="filterOverlay"></div>
<aside class="db-filter-panel" id="filterPanel">
    <div class="db-filter-head">
        <h3>Advanced Filters</h3>
        <button class="db-close-btn" id="closeFilterBtn">&times;</button>
    </div>
    <div class="db-filter-body">
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
                @foreach($offices as $office)
                    <option value="{{ $office->office_id }}">{{ $office->office_name }}</option>
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
            <label>Revision No</label>
            <input type="text" id="filterRevNo" placeholder="e.g. 3">
        </div>
    </div>
    <div class="db-filter-foot">
        <button class="db-btn db-btn-ghost" id="resetFilterBtn">Reset</button>
        <button class="db-btn db-btn-primary" id="applyFilterBtn">Apply Filters</button>
    </div>
</aside>