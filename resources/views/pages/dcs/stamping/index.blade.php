<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DCS — Document Stamping</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/dcs/stamping.css',
'resources/js/dcs/stamping.js'])
</head>
<body>

    {{-- TOPBAR --}}
    <header class="stmp-topbar">
        <div class="stmp-topbar-left">
            <a href="{{ route('dashboard') }}" class="stmp-back-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Dashboard
            </a>
            <span class="stmp-separator">/</span>
            <h1 class="stmp-page-title">Document Stamping</h1>
        </div>
        <div class="stmp-topbar-right">
            <span class="stmp-user-info">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                {{ auth()->user()->name ?? 'User' }}
            </span>
        </div>
    </header>

    <div class="stmp-container">

        {{-- PAGE HEADER --}}
        <div class="stmp-page-header">
            <div>
                <h2>Document Stamping</h2>
                <p>Manage stamping for registered documents</p>
            </div>
            <div class="stmp-header-actions">
                <button class="stmp-btn stmp-btn-batch" id="btnBatchStamp" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                    Batch Stamp (<span id="batchCount">0</span>)
                </button>
            </div>
        </div>

        {{-- STATS CARDS --}}
        <div class="stmp-stats-row">
            <div class="stmp-stat-card">
                <div class="stmp-stat-icon stmp-icon-pending">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </div>
                <div>
                    <span class="stmp-stat-num">12</span>
                    <span class="stmp-stat-text">Pending Stamping</span>
                </div>
            </div>
            <div class="stmp-stat-card">
                <div class="stmp-stat-icon stmp-icon-stamped">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
                </div>
                <div>
                    <span class="stmp-stat-num">48</span>
                    <span class="stmp-stat-text">Stamped Today</span>
                </div>
            </div>
            <div class="stmp-stat-card">
                <div class="stmp-stat-icon stmp-icon-dispatched">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                </div>
                <div>
                    <span class="stmp-stat-num">136</span>
                    <span class="stmp-stat-text">Dispatched</span>
                </div>
            </div>
            <div class="stmp-stat-card">
                <div class="stmp-stat-icon stmp-icon-total">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
                </div>
                <div>
                    <span class="stmp-stat-num">196</span>
                    <span class="stmp-stat-text">Total This Month</span>
                </div>
            </div>
        </div>

        {{-- FILTERS --}}
        <div class="stmp-filter-bar">
            <div class="stmp-filter-left">
                <div class="stmp-search-wrap">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input type="text" id="searchInput" placeholder="Search document no, title, DRF..." autocomplete="off">
                </div>
                <select id="filterStatus" class="stmp-filter-select">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="stamped">Stamped</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select id="filterDocType" class="stmp-filter-select">
                    <option value="">All Types</option>
                    @foreach($docTypes ?? [] as $type)
                        <option value="{{ $type->doc_type_id }}">{{ $type->doc_type_name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="stmp-btn stmp-btn-clear" id="btnClearFilters">Clear Filters</button>
        </div>

        {{-- TABLE --}}
        <div class="stmp-table-card">
            <table class="stmp-table">
                <thead>
                    <tr>
                        <th class="col-check">
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th class="col-id">ID</th>
                        <th>Document No</th>
                        <th class="col-title">Title</th>
                        <th>DRF No</th>
                        <th>Type</th>
                        <th>Rev</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr data-id="1001" data-status="pending">
                        <td><input type="checkbox" class="row-check" value="1001"></td>
                        <td class="cell-id">1001</td>
                        <td class="cell-mono">QMS-SOP-001</td>
                        <td class="cell-title">Quality Management System Manual</td>
                        <td class="cell-mono">DRF-2026-0041</td>
                        <td><span class="stmp-chip stmp-chip-internal">Internal</span></td>
                        <td class="cell-center">3</td>
                        <td class="cell-date">Jun 25, 2026</td>
                        <td><span class="stmp-badge stmp-badge-pending">Pending</span></td>
                        <td class="cell-actions">
                            <button class="stmp-icon-btn stmp-icon-btn-stamp" title="Stamp" data-id="1001">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                            </button>
                            <button class="stmp-icon-btn stmp-icon-btn-view" title="View" data-id="1001">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr data-id="1002" data-status="pending">
                        <td><input type="checkbox" class="row-check" value="1002"></td>
                        <td class="cell-id">1002</td>
                        <td class="cell-mono">QMS-WI-014</td>
                        <td class="cell-title">Incoming Inspection Work Instruction</td>
                        <td class="cell-mono">DRF-2026-0039</td>
                        <td><span class="stmp-chip stmp-chip-internal">Internal</span></td>
                        <td class="cell-center">1</td>
                        <td class="cell-date">Jun 24, 2026</td>
                        <td><span class="stmp-badge stmp-badge-pending">Pending</span></td>
                        <td class="cell-actions">
                            <button class="stmp-icon-btn stmp-icon-btn-stamp" title="Stamp" data-id="1002">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                            </button>
                            <button class="stmp-icon-btn stmp-icon-btn-view" title="View" data-id="1002">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr data-id="1003" data-status="stamped">
                        <td><input type="checkbox" class="row-check" value="1003"></td>
                        <td class="cell-id">1003</td>
                        <td class="cell-mono">EXT-CTR-022</td>
                        <td class="cell-title">Supplier Evaluation Criteria</td>
                        <td class="cell-mono">DRF-2026-0035</td>
                        <td><span class="stmp-chip stmp-chip-external">External</span></td>
                        <td class="cell-center">5</td>
                        <td class="cell-date">Jun 22, 2026</td>
                        <td><span class="stmp-badge stmp-badge-stamped">Stamped</span></td>
                        <td class="cell-actions">
                            <button class="stmp-icon-btn stmp-icon-btn-stamp" title="Stamp" data-id="1003" disabled>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                            </button>
                            <button class="stmp-icon-btn stmp-icon-btn-view" title="View" data-id="1003">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr data-id="1004" data-status="pending">
                        <td><input type="checkbox" class="row-check" value="1004"></td>
                        <td class="cell-id">1004</td>
                        <td class="cell-mono">QMS-FM-008</td>
                        <td class="cell-title">Corrective Action Request Form</td>
                        <td class="cell-mono">DRF-2026-0044</td>
                        <td><span class="stmp-chip stmp-chip-form">Form</span></td>
                        <td class="cell-center">2</td>
                        <td class="cell-date">Jun 26, 2026</td>
                        <td><span class="stmp-badge stmp-badge-pending">Pending</span></td>
                        <td class="cell-actions">
                            <button class="stmp-icon-btn stmp-icon-btn-stamp" title="Stamp" data-id="1004">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                            </button>
                            <button class="stmp-icon-btn stmp-icon-btn-view" title="View" data-id="1004">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr data-id="1005" data-status="dispatched">
                        <td><input type="checkbox" class="row-check" value="1005"></td>
                        <td class="cell-id">1005</td>
                        <td class="cell-mono">QMS-SOP-003</td>
                        <td class="cell-title">Document Control Procedure</td>
                        <td class="cell-mono">DRF-2026-0028</td>
                        <td><span class="stmp-chip stmp-chip-internal">Internal</span></td>
                        <td class="cell-center">7</td>
                        <td class="cell-date">Jun 18, 2026</td>
                        <td><span class="stmp-badge stmp-badge-dispatched">Dispatched</span></td>
                        <td class="cell-actions">
                            <button class="stmp-icon-btn stmp-icon-btn-stamp" title="Stamp" data-id="1005" disabled>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                            </button>
                            <button class="stmp-icon-btn stmp-icon-btn-view" title="View" data-id="1005">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr data-id="1006" data-status="pending">
                        <td><input type="checkbox" class="row-check" value="1006"></td>
                        <td class="cell-id">1006</td>
                        <td class="cell-mono">EXT-STD-011</td>
                        <td class="cell-title">ISO 9001:2015 Requirements Guide</td>
                        <td class="cell-mono">DRF-2026-0046</td>
                        <td><span class="stmp-chip stmp-chip-external">External</span></td>
                        <td class="cell-center">1</td>
                        <td class="cell-date">Jun 27, 2026</td>
                        <td><span class="stmp-badge stmp-badge-pending">Pending</span></td>
                        <td class="cell-actions">
                            <button class="stmp-icon-btn stmp-icon-btn-stamp" title="Stamp" data-id="1006">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                            </button>
                            <button class="stmp-icon-btn stmp-icon-btn-view" title="View" data-id="1006">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        <div class="stmp-pagination">
            <span class="stmp-page-info">Showing <strong>1–6</strong> of <strong>12</strong> entries</span>
            <div class="stmp-page-btns">
                <button class="stmp-pg" disabled>&laquo;</button>
                <button class="stmp-pg stmp-pg-active">1</button>
                <button class="stmp-pg">2</button>
                <button class="stmp-pg">&raquo;</button>
            </div>
        </div>
    </div>

    {{-- STAMP MODAL --}}
    <div class="stmp-overlay" id="stampModal">
        <div class="stmp-modal">
            <div class="stmp-modal-head">
                <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                    Stamp Document
                </h3>
                <button class="stmp-close-btn" id="closeModal">&times;</button>
            </div>
            <div class="stmp-modal-body">
                {{-- Document Preview --}}
                <div class="stmp-doc-preview">
                    <div class="stmp-preview-item">
                        <span>Document</span>
                        <strong id="previewDocNo">QMS-SOP-001</strong>
                    </div>
                    <div class="stmp-preview-item">
                        <span>Title</span>
                        <strong id="previewTitle">Quality Management System Manual</strong>
                    </div>
                    <div class="stmp-preview-item">
                        <span>Revision</span>
                        <strong id="previewRev">Rev 3</strong>
                    </div>
                </div>

                <div class="stmp-form-row">
                    <div class="stmp-field">
                        <label>Stamp Type <b class="req">*</b></label>
                        <select id="stampType">
                            <option value="">-- Select --</option>
                            <option value="controlled">CONTROLLED</option>
                            <option value="uncontrolled">UNCONTROLLED</option>
                            <option value="obsolete">OBSOLETE</option>
                            <option value="original">ORIGINAL COPY</option>
                            <option value="master">MASTER COPY</option>
                        </select>
                    </div>
                    <div class="stmp-field">
                        <label>Stamp Date <b class="req">*</b></label>
                        <input type="date" id="stampDate" value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="stmp-form-row">
                    <div class="stmp-field">
                        <label>Stamp Color</label>
                        <div class="stmp-radio-group">
                            <label class="stmp-radio-item"><input type="radio" name="stampColor" value="red" checked><span class="stmp-radio-dot" style="background:#dc2626"></span> Red</label>
                            <label class="stmp-radio-item"><input type="radio" name="stampColor" value="blue"><span class="stmp-radio-dot" style="background:#2563eb"></span> Blue</label>
                            <label class="stmp-radio-item"><input type="radio" name="stampColor" value="green"><span class="stmp-radio-dot" style="background:#16a34a"></span> Green</label>
                        </div>
                    </div>
                    <div class="stmp-field">
                        <label>Copies</label>
                        <div class="stmp-number-wrap">
                            <button type="button" class="stmp-num-btn" id="copyMinus">−</button>
                            <input type="number" id="stampCopies" value="1" min="1" max="999">
                            <button type="button" class="stmp-num-btn" id="copyPlus">+</button>
                        </div>
                    </div>
                </div>
                <div class="stmp-form-row stmp-form-full">
                    <div class="stmp-field stmp-field-full">
                        <label>Remarks</label>
                        <textarea id="stampRemarks" rows="3" placeholder="Optional remarks..."></textarea>
                    </div>
                </div>
            </div>
            <div class="stmp-modal-foot">
                <button class="stmp-btn stmp-btn-cancel" id="cancelModal">Cancel</button>
                <button class="stmp-btn stmp-btn-confirm" id="confirmStamp">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
                    Confirm Stamp
                </button>
            </div>
        </div>
    </div>

    {{-- DETAIL DRAWER --}}
    <div class="stmp-overlay" id="detailDrawer">
        <div class="stmp-drawer">
            <div class="stmp-drawer-head">
                <h3>Document Details</h3>
                <button class="stmp-close-btn" id="closeDrawer">&times;</button>
            </div>
            <div class="stmp-drawer-body">
                <div class="stmp-detail-section">
                    <h4>Registration Info</h4>
                    <table class="stmp-detail-table">
                        <tr><td class="dt-label">Request ID</td><td class="dt-value" id="detailId">1001</td></tr>
                        <tr><td class="dt-label">Document No</td><td class="dt-value" id="detailDocNo">QMS-SOP-001</td></tr>
                        <tr><td class="dt-label">Title</td><td class="dt-value" id="detailTitle">Quality Management System Manual</td></tr>
                        <tr><td class="dt-label">DRF No</td><td class="dt-value" id="detailDrf">DRF-2026-0041</td></tr>
                        <tr><td class="dt-label">Document Type</td><td class="dt-value" id="detailType">Internal</td></tr>
                        <tr><td class="dt-label">Revision</td><td class="dt-value" id="detailRev">3</td></tr>
                        <tr><td class="dt-label">Originator</td><td class="dt-value" id="detailOrigin">QA Department</td></tr>
                        <tr><td class="dt-label">Effectivity</td><td class="dt-value" id="detailEffect">Jun 25, 2026</td></tr>
                    </table>
                </div>
                <div class="stmp-detail-section">
                    <h4>Stamping History</h4>
                    <div class="stmp-history-list">
                        <div class="stmp-history-item">
                            <div class="stmp-history-dot stmp-dot-green"></div>
                            <div>
                                <strong>Stamped — CONTROLLED</strong>
                                <p>Jun 20, 2026 at 09:15 AM by Juan Dela Cruz</p>
                                <p>Copies: 3 &middot; Color: Red</p>
                            </div>
                        </div>
                        <div class="stmp-history-item">
                            <div class="stmp-history-dot stmp-dot-yellow"></div>
                            <div>
                                <strong>Registered</strong>
                                <p>Jun 18, 2026</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TOAST --}}
    <div class="stmp-toast-wrap" id="toastContainer"></div>

</body>
</html>