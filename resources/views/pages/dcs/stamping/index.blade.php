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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,500&display=swap" rel="stylesheet">
    @vite(['resources/css/dcs/stamping.css', 'resources/js/dcs/stamping.js'])
    @include('partials.header')
    @include('partials.sidebar')
    @include('partials.inactivity-modal')

    <div class="st-container main-content">

        {{-- ═══ Header ═══ --}}
        <header class="st-header">
            <div>
                <nav class="st-breadcrumb">Document Control System / <span>Stamp Document</span></nav>
                <h1 class="st-title">Stamp Document</h1>
                <p class="st-subtitle">Select a document to preview, configure, and apply stamps to PDF files.</p>
            </div>
            <div class="st-header-right">
                <div class="st-stat-pill">
                    <i class="fa-solid fa-file-lines"></i>
                    <span>{{ $documents->total() }} Document{{ $documents->total() !== 1 ? 's' : '' }}</span>
                </div>
            </div>
        </header>

        {{-- ═══ Toolbar ═══ --}}
        <div class="st-toolbar">
            <div class="st-search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="stSearch" class="st-search"
                       placeholder="Search by title, document number, or type..." autocomplete="off">
            </div>
            <select id="stTypeFilter" class="st-filter">
                <option value="all">All Document Types</option>
                @foreach($docTypes as $type)
                    <option value="{{ strtolower($type->doc_type_name) }}">{{ $type->doc_type_name }}</option>
                @endforeach
            </select>
            <button type="button" id="stClearBtn" class="st-btn-clear">
                <i class="fa-solid fa-xmark"></i> Clear
            </button>
        </div>

        {{-- ═══ Table ═══ --}}
        <div class="st-table-card">
            <div class="st-table-scroll">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th class="col-idx">#</th>
                            <th class="col-type">Type</th>
                            <th class="col-docno">Document No.</th>
                            <th class="col-title">Document Title</th>
                            <th class="col-rev">Rev</th>
                            <th class="col-files"><i class="fa-solid fa-paperclip"></i>&nbsp; Files</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody id="stTableBody">
                        @forelse($documents as $i => $doc)
                            @php
                                $ml   = $doc->masterlistRegistration;
                                $drf  = $doc->documentRequestForm;
                                $dcn  = $doc->documentChangeNotice;
                                $dist = $doc->distribution ?? null;
                                $retr = $doc->retrieval ?? null;

                                $docNo   = $ml->doc_no ?? 'N/A';
                                $title   = $ml->doc_title ?? $drf->doc_title ?? 'Untitled';
                                $revNo   = $ml->revise_no ?? 0;
                                $docType = $doc->docType->doc_type_name ?? 'N/A';

                                $files = [];
                                if ($ml && $ml->scanned_masterlist) {
                                    $files[] = [
                                        'key'   => 'masterlist',
                                        'label' => 'Masterlist',
                                        'abbr'  => 'ML',
                                        'cls'   => 'ml',
                                        'path'  => $ml->scanned_masterlist,
                                    ];
                                }
                                if ($drf && $drf->scanned_drf) {
                                    $files[] = [
                                        'key'   => 'drf',
                                        'label' => 'Document Request Form',
                                        'abbr'  => 'DRF',
                                        'cls'   => 'drf',
                                        'path'  => $drf->scanned_drf,
                                    ];
                                }
                                if ($dcn && $dcn->scanned_dcn) {
                                    $files[] = [
                                        'key'   => 'dcn',
                                        'label' => 'Document Change Notice',
                                        'abbr'  => 'DCN',
                                        'cls'   => 'dcn',
                                        'path'  => $dcn->scanned_dcn,
                                    ];
                                }
                                if ($dist && $dist->scanned_distribution) {
                                    $files[] = [
                                        'key'   => 'distribution',
                                        'label' => 'Distribution',
                                        'abbr'  => 'DIST',
                                        'cls'   => 'dist',
                                        'path'  => $dist->scanned_distribution,
                                    ];
                                }
                                if ($retr && $retr->scanned_retrieval) {
                                    $files[] = [
                                        'key'   => 'retrieval',
                                        'label' => 'Retrieval',
                                        'abbr'  => 'RETR',
                                        'cls'   => 'retr',
                                        'path'  => $retr->scanned_retrieval,
                                    ];
                                }
                            @endphp
                            <tr data-search="{{ strtolower($docNo . ' ' . $title . ' ' . $docType) }}">
                                <td class="col-idx">{{ $documents->firstItem() + $i }}</td>
                                <td class="col-type"><span class="st-badge-type">{{ $docType }}</span></td>
                                <td class="col-docno"><span class="st-doc-no">{{ $docNo }}</span></td>
                                <td class="col-title"><span class="st-doc-title" title="{{ $title }}">{{ $title }}</span></td>
                                <td class="col-rev"><span class="st-badge-rev">{{ $revNo }}</span></td>
                                <td class="col-files">
                                    @if(count($files) > 0)
                                        <div class="st-files-group">
                                            @foreach($files as $file)
                                                <a href="/storage/{{ $file['path'] }}"
                                                   target="_blank"
                                                   rel="noopener"
                                                   class="st-file-tag st-file-{{ $file['cls'] }}"
                                                   title="Preview {{ $file['label'] }}">
                                                    <i class="fa-solid fa-file-pdf"></i>
                                                    {{ $file['abbr'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="st-no-files">No files</span>
                                    @endif
                                </td>
                                <td class="col-action">
                                    <button type="button" class="st-btn-stamp"
                                        data-files='@json($files)'
                                        data-title="{{ $title }}"
                                        data-doc-no="{{ $docNo }}"
                                        data-rev="{{ $revNo }}">
                                        <i class="fa-solid fa-stamp"></i>
                                        <span>Stamp</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="st-empty">
                                        <div class="st-empty-icon">
                                            <i class="fa-solid fa-stamp"></i>
                                        </div>
                                        <h3>No documents found</h3>
                                        <p>There are no documents with stampable files matching your criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($documents->hasPages())
            <div class="st-pagination">
                <div class="st-page-info">
                    Showing <strong>{{ $documents->firstItem() }}&ndash;{{ $documents->lastItem() }}</strong> of <strong>{{ $documents->total() }}</strong>
                </div>
                <div class="st-page-links">
                    @if($documents->onFirstPage())
                        <span class="st-pg disabled"><i class="fa-solid fa-chevron-left"></i></span>
                    @else
                        <a href="{{ $documents->previousPageUrl() }}" class="st-pg"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach($documents->getUrlRange(1, $documents->lastPage()) as $page => $url)
                        @if($page === $documents->currentPage())
                            <span class="st-pg st-pg-active">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="st-pg">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if($documents->hasMorePages())
                        <a href="{{ $documents->nextPageUrl() }}" class="st-pg"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <span class="st-pg disabled"><i class="fa-solid fa-chevron-right"></i></span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         STAMP MODAL
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="st-modal-overlay" id="stampModal" style="display:none;">
        <div class="st-modal">
            <div class="st-modal-header">
                <div>
                    <h3>Apply Stamp</h3>
                    <p class="st-modal-doc" id="modalDocInfo"></p>
                </div>
                <button class="st-modal-close" id="closeModal" type="button">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="st-modal-body">
                {{-- LEFT: PDF Preview --}}
                <div class="st-preview-panel">
                    <div class="st-preview-toolbar">
                        <button class="st-preview-nav" id="prevPage" type="button" title="Previous page" disabled>
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <span class="st-preview-page" id="pageIndicator">Page 1 / 1</span>
                        <button class="st-preview-nav" id="nextPage" type="button" title="Next page" disabled>
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                        <div class="st-preview-spacer"></div>
                        <button class="st-preview-refresh" id="refreshPreview" type="button" title="Refresh preview">
                            <i class="fa-solid fa-rotate-right"></i> Refresh
                        </button>
                    </div>
                    <div class="st-preview-frame" id="previewFrame">
                        <iframe id="pdfPreview" src="" title="PDF Preview"></iframe>

                        <div class="st-stamp-overlay pos-bottom-right" id="stampOverlay" style="display:none;">
                            <div class="st-stamp-box" id="stampBox">
                                <div class="st-stamp-inner">
                                    <div class="st-stamp-title" id="overlayTitle"></div>
                                    <div class="st-stamp-divider" id="overlayDivider" style="display:none;"></div>
                                    <div class="st-stamp-fields" id="overlayFields" style="display:none;">
                                        <div class="st-stamp-field">
                                            <span class="st-sf-label">Certified by:</span>
                                            <span class="st-sf-value" id="overlayCertBy">...</span>
                                        </div>
                                        <div class="st-stamp-field">
                                            <span class="st-sf-label">Designation:</span>
                                            <span class="st-sf-value" id="overlayDesig">...</span>
                                        </div>
                                    </div>
                                    <div class="st-stamp-date" id="overlayDate"></div>
                                    <div class="st-stamp-sub" id="overlaySub"></div>
                                </div>
                            </div>
                            <span class="st-stamp-pages" id="overlayPages">All pages</span>
                        </div>

                        <div class="st-preview-fallback" id="previewFallback" style="display:none;">
                            <i class="fa-solid fa-file-pdf"></i>
                            <p>PDF preview not available</p>
                            <a id="openPdfLink" href="#" target="_blank">Open PDF in new tab</a>
                        </div>
                    </div>
                    <p class="st-preview-note">
                        <i class="fa-solid fa-eye"></i>
                        Preview shows stamp position on first page
                    </p>
                </div>

                {{-- RIGHT: Configuration --}}
                <div class="st-config-panel">
                    <div class="st-section" id="fileSection" style="display:none;">
                        <label class="st-label">File</label>
                        <div class="st-file-options" id="fileOptions"></div>
                    </div>

                    <div class="st-section">
                        <label class="st-label">Stamp Type</label>
                        <div class="st-type-pills" id="typePills">
                            <button class="st-type-pill" data-type="controlled" type="button">
                                <span class="st-pill-color" style="background:#003399"></span> Controlled
                            </button>
                            <button class="st-type-pill" data-type="obsolete" type="button">
                                <span class="st-pill-color" style="background:#b40000"></span> Obsolete
                            </button>
                            <button class="st-type-pill" data-type="master_copy" type="button">
                                <span class="st-pill-color" style="background:#006400"></span> Master Copy
                            </button>
                            <button class="st-type-pill" data-type="reference" type="button">
                                <span class="st-pill-color" style="background:#646464"></span> Reference
                            </button>
                            <button class="st-type-pill" data-type="certified_true_copy" type="button">
                                <span class="st-pill-color" style="background:#003399"></span> Certified True Copy
                            </button>
                        </div>
                    </div>

                    <div class="st-section" id="certifiedFields" style="display:none;">
                        <div class="st-form-group">
                            <label class="st-label-sm">Certified By</label>
                            <input type="text" id="certifiedBy" class="st-input" placeholder="Full name">
                        </div>
                        <div class="st-form-group">
                            <label class="st-label-sm">Designation</label>
                            <input type="text" id="designation" class="st-input" placeholder="Position / Title">
                        </div>
                    </div>

                    <div class="st-section">
                        <label class="st-label">Position</label>
                        <div class="st-pos-wrap">
                            <div class="st-pos-map" id="positionMap">
                                <div class="st-pos-dot" data-pos="top-left">TL</div>
                                <div class="st-pos-dot" data-pos="top-right">TR</div>
                                <div class="st-pos-dot" data-pos="center">C</div>
                                <div class="st-pos-dot" data-pos="bottom-left">BL</div>
                                <div class="st-pos-dot active" data-pos="bottom-right">BR</div>
                            </div>
                            <span class="st-pos-label" id="positionLabel">Bottom Right</span>
                        </div>
                    </div>

                    <div class="st-section">
                        <label class="st-checkbox-wrap">
                            <input type="checkbox" id="stampAllPages" checked>
                            <span class="st-checkbox-custom"></span>
                            <span>Stamp all pages</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="st-modal-footer">
                <button class="st-btn-cancel" id="cancelBtn" type="button">Cancel</button>
                <div class="st-footer-actions">
                    <button class="st-btn-download" id="downloadBtn" type="button" disabled>
                        <i class="fa-solid fa-download"></i>
                        <span>Download Copy</span>
                    </button>
                    <button class="st-btn-apply" id="applyBtn" type="button" disabled>
                        <i class="fa-solid fa-stamp"></i>
                        <span>Apply Stamp</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         CONFIRM MODAL
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="st-confirm-overlay" id="confirmModal" style="display:none;">
        <div class="st-confirm">
            <div class="st-confirm-icon">
                <i class="fa-solid fa-stamp"></i>
            </div>
            <h3>Apply Stamp Permanently?</h3>
            <p>This will overwrite the original file with the <strong id="confirmStampLabel"></strong> stamp. This action cannot be undone.</p>
            <div class="st-confirm-actions">
                <button class="st-btn-secondary" id="confirmCancel" type="button">Cancel</button>
                <button class="st-btn-primary" id="confirmApply" type="button">
                    <i class="fa-solid fa-check"></i> Confirm Apply
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         TOAST
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="st-toast" id="stToast" style="display:none;">
        <i class="st-toast-icon fa-solid fa-check-circle"></i>
        <span class="st-toast-msg" id="toastMsg"></span>
    </div>
</body>
</html>