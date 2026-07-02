<div class="upd-table-card">
    @if($documents->count() > 0)
        <table class="upd-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Doc Type</th>
                    <th>Title</th>
                    <th>Document No.</th>
                    <th>Rev</th>
                    <th>Checklists</th>
                    <th>Date Created</th>
                    <th style="width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($documents as $doc)
                    @php
                        $drf  = $doc->documentRequestForm;
                        $dcn  = $doc->documentChangeNotice;
                        $ml   = $doc->masterlistRegistration;
                        $ret  = $doc->documentRetrieval;
                        $dist = $doc->documentDistribution;
                        $title  = $drf->doc_title ?? $ml->doc_title ?? 'N/A';
                        $docNo  = $ml->doc_no ?? $drf->drf_no ?? 'N/A';
                        $revNo  = $ml->revise_no ?? 0;
                        $checklists = [];
                        if ($drf)  $checklists[] = 'DRF';
                        if ($dcn)  $checklists[] = 'DCN';
                        if ($ml)   $checklists[] = 'ML';
                        if ($ret)  $checklists[] = 'RET';
                        if ($dist) $checklists[] = 'DIST';
                    @endphp
                    <tr>
                        <td class="upd-id">#{{ $doc->request_id }}</td>
                        <td><span class="upd-type-badge">{{ $doc->docType->doc_type_name ?? 'N/A' }}</span></td>
                        <td class="upd-doc-title" title="{{ $title }}">{{ $title }}</td>
                        <td class="upd-doc-no">{{ $docNo }}</td>
                        <td>
                            <span class="upd-rev-badge">{{ $revNo }}</span>
                        </td>
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
                                @if($ml)
                                    <a href="{{ route('register.history', $ml->doc_no) }}" class="upd-btn-icon" title="View Revision History">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </a>
                                @endif
                                <a href="{{ route('register.edit', $doc->request_id) }}" class="upd-btn-icon" title="Edit Latest Revision">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button type="button" class="upd-btn-icon danger" title="Delete Latest Revision"
                                    onclick="confirmDelete({{ $doc->request_id }}, '{{ addslashes($title) }}', '{{ $revNo }}')">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="upd-pagination">
            <div class="upd-pagination-info">
                Showing {{ $documents->firstItem() }} to {{ $documents->lastItem() }} of {{ $documents->total() }} documents
            </div>
            <div class="upd-pagination-links">
                @if($documents->onFirstPage())
                    <span class="disabled"><i class="fa-solid fa-chevron-left"></i></span>
                @else
                    <a href="{{ $documents->previousPageUrl() }}" onclick="event.preventDefault(); goToPage('{{ $documents->previousPageUrl() }}')"><i class="fa-solid fa-chevron-left"></i></a>
                @endif
                @foreach($documents->getUrlRange(max(1, $documents->currentPage() - 2), min($documents->lastPage(), $documents->currentPage() + 2)) as $page => $url)
                    @if($page == $documents->currentPage())
                        <span class="active">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" onclick="event.preventDefault(); goToPage('{{ $url }}')">{{ $page }}</a>
                    @endif
                @endforeach
                @if($documents->hasMorePages())
                    <a href="{{ $documents->nextPageUrl() }}" onclick="event.preventDefault(); goToPage('{{ $documents->nextPageUrl() }}')"><i class="fa-solid fa-chevron-right"></i></a>
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