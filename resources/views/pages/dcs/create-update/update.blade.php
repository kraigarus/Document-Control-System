<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

@vite([
'resources/css/dcs/update.css',
'resources/js/dcs/update.js'
])

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

    <div class="upd-container">
        <!-- Header -->
        <div class="upd-header">
            <div>
                <div class="upd-breadcrumb">Document Control System / Update</div>
                <div class="upd-title">Update Documents</div>
            </div>
        </div>

        <!-- Toast Messages -->
        @if(session('success'))
            <div class="upd-toast upd-toast-success" id="successToast">
                <div class="upd-toast-icon"><i class="fa-solid fa-check"></i></div>
                <div class="upd-toast-content">
                    <div class="upd-toast-title">Success</div>
                    <div class="upd-toast-msg">{{ session('success') }}</div>
                </div>
                <button class="upd-toast-close" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
            </div>
        @endif

        @if(session('error'))
            <div class="upd-toast upd-toast-error" id="errorToast">
                <div class="upd-toast-icon"><i class="fa-solid fa-xmark"></i></div>
                <div class="upd-toast-content">
                    <div class="upd-toast-title">Error</div>
                    <div class="upd-toast-msg">{{ session('error') }}</div>
                </div>
                <button class="upd-toast-close" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
            </div>
        @endif

        <!-- Search & Filter -->
        <form method="GET" action="{{ route('register.update') }}" class="upd-search-bar">
            <input type="text" name="search" class="upd-search-input"
                placeholder="Search by title, document no, DRF no, DCN no..."
                value="{{ request('search') }}">
            <select name="doc_type_id" class="upd-filter-select">
                <option value="">All Document Types</option>
                @foreach($docTypes as $type)
                    <option value="{{ $type->doc_type_id }}" {{ request('doc_type_id') == $type->doc_type_id ? 'selected' : '' }}>
                        {{ $type->doc_type_name }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="upd-btn-search">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
            @if(request('search') || request('doc_type_id'))
                <a href="{{ route('register.update') }}" class="upd-btn-search" style="background: #f1f5f9; color: var(--upd-text-muted); border: 1px solid var(--upd-border);">
                    <i class="fa-solid fa-xmark"></i> Clear
                </a>
            @endif
        </form>

        <!-- Table -->
        <div class="upd-table-card">
            @if($documents->count() > 0)
                <table class="upd-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Doc Type</th>
                            <th>Title</th>
                            <th>Document No.</th>
                            <th>Checklists</th>
                            <th>Date Created</th>
                            <th style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $doc)
                            @php
                                $drf = $doc->documentRequestForm;
                                $dcn = $doc->documentChangeNotice;
                                $ml = $doc->masterlistRegistration;
                                $ret = $doc->documentRetrieval;
                                $dist = $doc->documentDistribution;
                                $title = $drf->doc_title ?? $ml->doc_title ?? 'N/A';
                                $docNo = $ml->doc_no ?? $drf->drf_no ?? 'N/A';
                                $checklists = [];
                                if ($drf) $checklists[] = 'DRF';
                                if ($dcn) $checklists[] = 'DCN';
                                if ($ml) $checklists[] = 'ML';
                                if ($ret) $checklists[] = 'RET';
                                if ($dist) $checklists[] = 'DIST';
                            @endphp
                            <tr>
                                <td class="upd-id">#{{ $doc->request_id }}</td>
                                <td>
                                    <span class="upd-type-badge">{{ $doc->docType->doc_type_name ?? 'N/A' }}</span>
                                </td>
                                <td class="upd-doc-title" title="{{ $title }}">{{ $title }}</td>
                                <td class="upd-doc-no">{{ $docNo }}</td>
                                <td>
                                    <div class="upd-status-checklists">
                                        @foreach($checklists as $cl)
                                            <span class="upd-checklist-tag">{{ $cl }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $doc->created_at ? $doc->created_at->format('M d, Y') : 'N/A' }}</td>
                                <td>
                                    <div class="upd-actions">
                                        <a href="{{ route('register.edit', $doc->request_id) }}" class="upd-btn-icon" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <button type="button" class="upd-btn-icon danger" title="Delete"
                                            onclick="confirmDelete({{ $doc->request_id }}, '{{ addslashes($title) }}')">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="upd-pagination">
                    <div class="upd-pagination-info">
                        Showing {{ $documents->firstItem() }} to {{ $documents->lastItem() }} of {{ $documents->total() }} documents
                    </div>
                    <div class="upd-pagination-links">
                        @if($documents->onFirstPage())
                            <span class="disabled"><i class="fa-solid fa-chevron-left"></i></span>
                        @else
                            <a href="{{ $documents->previousPageUrl() }}"><i class="fa-solid fa-chevron-left"></i></a>
                        @endif

                        @foreach($documents->getUrlRange(max(1, $documents->currentPage() - 2), min($documents->lastPage(), $documents->currentPage() + 2)) as $page => $url)
                            @if($page == $documents->currentPage())
                                <span class="active">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if($documents->hasMorePages())
                            <a href="{{ $documents->nextPageUrl() }}"><i class="fa-solid fa-chevron-right"></i></a>
                        @else
                            <span class="disabled"><i class="fa-solid fa-chevron-right"></i></span>
                        @endif
                    </div>
                </div>
            @else
                <div class="upd-empty">
                    <i class="fa-solid fa-folder-open"></i>
                    <p>No documents found</p>
                    <span>Try adjusting your search or filter</span>
                </div>
            @endif
        </div>
    </div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="upd-modal-overlay" style="display:none;">
    <div class="upd-modal">
        <div class="upd-modal-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3>Delete Document?</h3>
        <p>This will permanently remove "<strong id="deleteDocTitle"></strong>" and all related records. This action cannot be undone.</p>
        <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
        </form>
        <div class="upd-modal-actions">
            <button class="upd-modal-btn upd-modal-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="upd-modal-btn upd-modal-confirm" onclick="submitDelete()">
                <i class="fa-solid fa-trash-can"></i> Delete
            </button>
        </div>
    </div>
</div>