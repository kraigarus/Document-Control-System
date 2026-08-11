{{-- Report preview — export-style iframe for most reports; interactive table for OPCR --}}
<section class="rpt-results" id="resultsPanel" hidden>
    <div class="rpt-results-head">
        <div class="rpt-results-meta">
            <h3 id="resultsTitle">Report Preview</h3>
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

    <div class="rpt-preview-shell" id="previewShell">
        <iframe id="reportPreviewFrame" class="rpt-preview-frame" title="Report preview" hidden></iframe>
        <div class="rpt-table-scroll" id="opcrTableHost" hidden>
            <table class="rpt-table" id="reportTable">
                <thead id="reportHead"></thead>
                <tbody id="reportBody"></tbody>
            </table>
        </div>
        <div class="rpt-state rpt-preview-placeholder" id="previewPlaceholder">
            <div class="rpt-state-icon"><i class="fa-solid fa-file-lines"></i></div>
            <h4>Select a document type and filters</h4>
            <p>The report preview will appear here.</p>
        </div>
    </div>
</section>
