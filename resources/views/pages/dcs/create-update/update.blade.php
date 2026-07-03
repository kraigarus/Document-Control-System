<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    @vite(['resources/css/dcs/update.css', 'resources/js/dcs/update.js'])
    @include('partials.header')
    @include('partials.sidebar')
    @include('partials.inactivity-modal')

    <div class="upd-container">
        <div class="upd-header">
            <div>
                <div class="upd-breadcrumb">Document Control System / Update</div>
                <div class="upd-title">Update Documents</div>
            </div>
            <div class="upd-header-stats">
                <span id="docCount" class="upd-count">0 Documents</span>
            </div>
        </div>

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
        <div class="upd-search-bar">
            <div class="upd-search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" class="upd-search-input" id="updSearch"
                    placeholder="Search by title, document no, DRF no, DCN no..."
                    autocomplete="off">
            </div>
            <select class="upd-filter-select" id="updTypeFilter">
                <option value="all">All Document Types</option>
                @foreach($docTypes as $type)
                    <option value="{{ $type->doc_type_id }}">{{ $type->doc_type_name }}</option>
                @endforeach
            </select>
            <button type="button" class="upd-btn-search" id="resetSearchBtn" title="Reset filters">
                <i class="fa-solid fa-xmark"></i> Clear
            </button>
        </div>

        <!-- Table (JS-rendered) -->
        <div class="upd-table-card">
            <div class="upd-table-scroll">
                <table class="upd-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Doc Type</th>
                            <th>Title</th>
                            <th>Document No.</th>
                            <th>Rev</th>
                            <th>Checklists</th>
                            <th style="width:130px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>
            <div id="emptyState" class="upd-empty" style="display:none;">
                <i class="fa-solid fa-folder-open"></i>
                <p>No documents found</p>
            </div>
            <div class="upd-pagination">
                <div class="upd-pagination-info" id="pageInfo">Loading...</div>
                <div class="upd-pagination-links" id="pageBtns"></div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="upd-modal-overlay" style="display:none;">
        <div class="upd-modal">
            <div class="upd-modal-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3>Delete Document?</h3>
            <p>This will permanently remove "<strong id="deleteDocTitle"></strong>" <span id="deleteRevInfo"></span>. This action cannot be undone.</p>
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
</body>
</html>