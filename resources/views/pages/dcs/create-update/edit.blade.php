<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    @vite([
        'resources/css/dcs/edit.css',
        'resources/js/dcs/edit.js',

        'resources/css/dcs/register.css',
    ])
    
    <script>
        window.APP_CONFIG = {
            CURRENT_VERSION_ID: {{ isset($docRequest) ? $docRequest->version_id : 'null' }},
            CURRENT_DOC_TYPE_ID: {{ isset($docRequest) ? $docRequest->doc_type_id : 'null' }},
            CURRENT_SUB_TYPE_ID: {{ isset($docRequest) && $docRequest->sub_type_id ? $docRequest->sub_type_id : 'null' }},
            CURRENT_DRF_SOURCE: '{{ isset($drf) && $drf ? $drf->office_id : "" }}',
            CURRENT_DCN_SOURCE: '{{ isset($dcn) && $dcn ? $dcn->office_id : "" }}',
            CURRENT_APPROVAL_BODY: '{{ isset($approval) && $approval ? $approval->approval_body_id : "" }}',
        };
    </script>

</head>

<body>

@php
    function fmtDate($val) {
        if (!$val) return '';
        return \Carbon\Carbon::parse($val)->format('Y-m-d');
    }
    function fmtTime($val) {
        if (!$val) return '';
        return \Carbon\Carbon::parse($val)->format('H:i');
    }
@endphp

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

    <div class="reg-container">
        <!-- Header -->
        <div class="reg-header">
            <div>
                <div class="reg-breadcrumb">Document Control System / Update / Edit</div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="reg-title">Edit Document #{{ $docRequest->request_id }}</div>
                    <span class="edit-badge"><i class="fa-solid fa-pen"></i> Editing</span>
                </div>
            </div>
            <a href="{{ route('register.update') }}" class="reg-btn reg-btn-cancel">
                <i class="fa-solid fa-arrow-left"></i> Back to List
            </a>
        </div>

        <!-- Toast -->
        @if(session('success'))
            <div class="reg-toast reg-toast-success" id="successToast">
                <div class="reg-toast-icon"><i class="fa-solid fa-check"></i></div>
                <div class="reg-toast-content">
                    <div class="reg-toast-title">Success</div>
                    <div class="reg-toast-message">{{ session('success') }}</div>
                </div>
                <button class="reg-toast-close" onclick="closeToast()"><i class="fa-solid fa-xmark"></i></button>
                <div class="reg-toast-progress"></div>
            </div>
        @endif
        @if(session('error'))
            <div class="reg-toast reg-toast-error" id="errorToast">
                <div class="reg-toast-icon"><i class="fa-solid fa-xmark"></i></div>
                <div class="reg-toast-content">
                    <div class="reg-toast-title">Error</div>
                    <div class="reg-toast-message">{{ session('error') }}</div>
                </div>
                <button class="reg-toast-close" onclick="closeToast()"><i class="fa-solid fa-xmark"></i></button>
                <div class="reg-toast-progress"></div>
            </div>
        @endif

        <!-- Form -->
        <form id="masterForm" method="POST" action="{{ route('register.updateDocument', $docRequest->request_id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <!-- ═══ TOP PANEL ═══ -->
            <div class="reg-panel">
                <div class="reg-panel-grid">
                    <div class="reg-field">
                        <label>Version Type</label>
                        <select id="versionType" name="version_id" autocomplete="off" data-last-valid="{{ $docRequest->version_id }}">
                            <option value="" disabled>Select version</option>
                        </select>
                    </div>
                    <div class="reg-field">
                        <label>Document Type</label>
                        <select id="docType" name="doc_type_id" autocomplete="off" data-last-valid="{{ $docRequest->doc_type_id }}">
                            <option value="" disabled>Select document type</option>
                        </select>
                    </div>
                    <div class="reg-field">
                        <label>Sub-Type</label>
                        <select id="subType" name="sub_type_id" autocomplete="off">
                            <option value="" selected disabled>Select sub-type</option>
                        </select>
                    </div>
                </div>

                <div class="reg-panel-bottom">
                    <div id="dynamicCheckboxes" class="reg-checklist"></div>
                    <div class="reg-approval-toggle">
                        <span class="reg-toggle-label">Approval</span>
                        <div class="reg-toggle-options">
                            <label class="reg-radio">
                                <input type="radio" name="approval_status" value="applicable"
                                    {{ $docRequest->approval_status === 'applicable' ? 'checked' : '' }}
                                    onchange="handleApprovalToggle(true)">
                                Applicable
                            </label>
                            <label class="reg-radio">
                                <input type="radio" name="approval_status" value="not_applicable"
                                    {{ $docRequest->approval_status !== 'applicable' ? 'checked' : '' }}
                                    onchange="handleApprovalToggle(false)">
                                Not Applicable
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ SYLLABI ═══ -->
            <section class="reg-card" id="section-syllabi" style="display: {{ $syllabi->count() > 0 ? 'block' : 'none' }};">
                <div class="reg-card-header">
                    <i class="fa-solid fa-book"></i>
                    <span>Syllabi</span>
                </div>
                <div class="reg-card-body">
                    <div class="reg-table-wrap">
                        <table class="reg-table">
                            <thead>
                                <tr>
                                    <th>Course Name</th>
                                    <th>Availability</th>
                                    <th>No. Pages</th>
                                    <th>DRF Avail.</th>
                                    <th>DRF No.</th>
                                    <th>DRF Date</th>
                                    <th>DRF Received</th>
                                    <th>Scanned DRF</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="syllabiTableBody">
                                @forelse($syllabi as $syl)
                                <tr style="animation: fadeSlideUp 0.25s ease;">
                                    <td><input type="text" name="syllabiCourseName[]" placeholder="Enter course name" value="{{ $syl->course_name }}"></td>
                                    <td>
                                        <select name="syllabiAvailability[]">
                                            <option value="" disabled>Select</option>
                                            <option value="available" {{ $syl->syllabi_availability === 'available' ? 'selected' : '' }}>Available</option>
                                            <option value="not_available" {{ $syl->syllabi_availability === 'not_available' ? 'selected' : '' }}>Not Available</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="syllabiNoPages[]" min="0" placeholder="0" value="{{ $syl->no_pages }}"></td>
                                    <td>
                                        <select name="syllabiDrfAvailability[]">
                                            <option value="" disabled>Select</option>
                                            <option value="available" {{ $syl->drf_availability === 'available' ? 'selected' : '' }}>Available</option>
                                            <option value="not_available" {{ $syl->drf_availability === 'not_available' ? 'selected' : '' }}>Not Available</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="syllabiDrfNo[]" placeholder="DRF-001" value="{{ $syl->drf_no }}"></td>
                                    <td><input type="date" name="syllabiDrfDate[]" value="{{ fmtDate($syl->drf_date) }}"></td>
                                    <td><input type="date" name="syllabiDrfReceived[]" value="{{ fmtDate($syl->drf_received_date) }}"></td>
                                    <td>
                                        <label class="reg-upload-cell">
                                            <input type="file" name="syllabiScannedDrf[]" accept=".pdf,.docx">
                                            <i class="fa-solid fa-cloud-arrow-up"></i>
                                            <span>{{ $syl->scanned_drf ? basename($syl->scanned_drf) : 'No file chosen' }}</span>
                                        </label>
                                    </td>
                                    <td><button type="button" class="reg-row-del" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash-can"></i></button></td>
                                </tr>
                                @empty
                                <tr style="animation: fadeSlideUp 0.25s ease;">
                                    <td><input type="text" name="syllabiCourseName[]" placeholder="Enter course name"></td>
                                    <td>
                                        <select name="syllabiAvailability[]">
                                            <option value="" disabled selected>Select</option>
                                            <option value="available">Available</option>
                                            <option value="not_available">Not Available</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="syllabiNoPages[]" min="0" placeholder="0"></td>
                                    <td>
                                        <select name="syllabiDrfAvailability[]">
                                            <option value="" disabled selected>Select</option>
                                            <option value="available">Available</option>
                                            <option value="not_available">Not Available</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="syllabiDrfNo[]" placeholder="DRF-001"></td>
                                    <td><input type="date" name="syllabiDrfDate[]"></td>
                                    <td><input type="date" name="syllabiDrfReceived[]"></td>
                                    <td>
                                        <label class="reg-upload-cell">
                                            <input type="file" name="syllabiScannedDrf[]" accept=".pdf,.docx">
                                            <i class="fa-solid fa-cloud-arrow-up"></i>
                                            <span>No file chosen</span>
                                        </label>
                                    </td>
                                    <td><button type="button" class="reg-row-del" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash-can"></i></button></td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="reg-add-row" onclick="addSyllabiRow()">
                        <i class="fa-solid fa-plus"></i> Add Course Row
                    </button>
                </div>
            </section>

            <!-- ═══ SECTION 1 — DRF ═══ -->
            <section class="reg-card" id="section-1" style="display: {{ $drf ? 'block' : 'none' }};">
                <div class="reg-card-header">
                    <i class="fa-solid fa-file-lines"></i>
                    <span>Document Request Form</span>
                </div>
                <div class="reg-card-body">
                    <div class="reg-grid-3">
                        <div class="reg-field">
                            <label>DRF No.</label>
                            <input type="text" id="drfNo" name="drfNo" placeholder="DRF-001" value="{{ $drf->drf_no ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>DRF Date</label>
                            <input type="date" id="drfDate" name="drfDate" value="{{ fmtDate($drf->drf_date ?? '') }}">
                        </div>
                        <div class="reg-field">
                            <label>Source Unit</label>
                            <select id="drfSourceUnit" name="drfSourceUnit" autocomplete="off">
                                <option value="" selected disabled>Select office</option>
                            </select>
                        </div>
                    </div>
                    <div class="reg-grid-3">
                        <div class="reg-field">
                            <label>Date Receipt</label>
                            <input type="date" id="drfReceiptDate" name="drfReceiptDate" value="{{ fmtDate($drf->drf_receipt_date ?? '') }}">
                        </div>
                        <div class="reg-field">
                            <label>Time Receipt</label>
                            <input type="time" id="drfTime" name="drfTime" value="{{ fmtTime($drf->drf_receipt_time ?? '') }}">
                        </div>
                        <div class="reg-field">
                            <label>Document Title</label>
                            <input type="text" id="drfTitle" name="drfTitle" placeholder="Title" value="{{ $drf->doc_title ?? '' }}">
                        </div>
                    </div>
                    <div class="reg-field">
                        <label>Upload Scanned DRF</label>
                        @if($drf && $drf->scanned_drf)
                            <div class="reg-current-file">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span>{{ basename($drf->scanned_drf) }}</span>
                                <a href="{{ asset('storage/' . $drf->scanned_drf) }}" target="_blank">View</a>
                            </div>
                        @endif
                        <label class="reg-upload">
                            <input type="file" id="drfFile" name="drfFile" accept=".pdf,.docx">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>{{ $drf && $drf->scanned_drf ? 'Replace file' : 'Choose .pdf or .docx file' }}</span>
                        </label>
                    </div>
                </div>
            </section>

            <!-- ═══ SECTION 2 — DCN ═══ -->
            <section class="reg-card" id="section-2" style="display: {{ $dcn ? 'block' : 'none' }};">
                <div class="reg-card-header">
                    <i class="fa-solid fa-file-pen"></i>
                    <span>Document Change Notice</span>
                </div>
                <div class="reg-card-body">
                    <div class="reg-grid-3">
                        <div class="reg-field">
                            <label>DCN No.</label>
                            <input type="text" id="dcnNumber" name="dcnNumber" placeholder="DCN-001" value="{{ $dcn->dcn_no ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>DCN Date</label>
                            <input type="date" id="noticeDate" name="noticeDate" value="{{ fmtDate($dcn->dcn_date ?? '') }}">
                        </div>
                        <div class="reg-field">
                            <label>Source Unit</label>
                            <select id="dcnSourceUnit" name="dcnSourceUnit" autocomplete="off">
                                <option value="" selected disabled>Select office</option>
                            </select>
                        </div>
                    </div>
                    <div class="reg-grid-2">
                        <div class="reg-field">
                            <label>Receipt Date</label>
                            <input type="date" id="receiptDate" name="receiptDate" value="{{ fmtDate($dcn->dcn_receipt_date ?? '') }}">
                        </div>
                        <div class="reg-field">
                            <label>Receipt Time</label>
                            <input type="time" id="receiptTime" name="receiptTime" value="{{ fmtTime($dcn->dcn_receipt_time ?? '') }}">
                        </div>
                    </div>
                    <div class="reg-field">
                        <label>Upload Scanned DCN</label>
                        @if($dcn && $dcn->scanned_dcn)
                            <div class="reg-current-file">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span>{{ basename($dcn->scanned_dcn) }}</span>
                                <a href="{{ asset('storage/' . $dcn->scanned_dcn) }}" target="_blank">View</a>
                            </div>
                        @endif
                        <label class="reg-upload">
                            <input type="file" id="dcnFile" name="dcnFile" accept=".pdf,.docx">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>{{ $dcn && $dcn->scanned_dcn ? 'Replace file' : 'Choose .pdf or .docx file' }}</span>
                        </label>
                    </div>

                    <!-- Revision Table -->
                    <div class="reg-field">
                        <label>Document Revisions</label>
                        <div class="reg-table-wrap">
                            <table class="reg-table">
                                <thead>
                                    <tr>
                                        <th>Document Title</th>
                                        <th>Document No.</th>
                                        <th>Effectivity Date</th>
                                        <th>Revision No.</th>
                                        <th>Scanned Copy</th>
                                        <th>Purpose</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="revisionTableBody">
                                    @forelse($revisions as $rev)
                                    <tr>
                                        <td><input type="text" name="documentTitle[]" placeholder="Title" value="{{ $rev->title }}"></td>
                                        <td><input type="text" name="documentNo[]" placeholder="Doc No." value="{{ $rev->document_no }}"></td>
                                        <td><input type="date" name="effectiveDate[]" value="{{ fmtDate($rev->effectivity_date) }}"></td>
                                        <td><input type="number" name="revisionNo[]" placeholder="0" value="{{ $rev->revision_no }}"></td>
                                        <td>
                                            @if($rev->scanned_copy)
                                                <div class="reg-current-file" style="margin:0;">
                                                    <i class="fa-solid fa-file-pdf"></i>
                                                    <span>{{ basename($rev->scanned_copy) }}</span>
                                                    <a href="{{ asset('storage/' . $rev->scanned_copy) }}" target="_blank">View</a>
                                                </div>
                                            @endif
                                            <input type="file" name="scannedCopy[]" accept=".pdf,.docx">
                                        </td>
                                        <td><input type="text" name="revisionPurpose[]" placeholder="Purpose" value="{{ $rev->brief_purpose }}"></td>
                                        <td><button type="button" class="reg-row-del" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash-can"></i></button></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td><input type="text" name="documentTitle[]" placeholder="Title"></td>
                                        <td><input type="text" name="documentNo[]" placeholder="Doc No."></td>
                                        <td><input type="date" name="effectiveDate[]"></td>
                                        <td><input type="number" name="revisionNo[]" placeholder="0"></td>
                                        <td><input type="file" name="scannedCopy[]" accept=".pdf,.docx"></td>
                                        <td><input type="text" name="revisionPurpose[]" placeholder="Purpose"></td>
                                        <td><button type="button" class="reg-row-del" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash-can"></i></button></td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="reg-add-row" onclick="addRevisionRow()">
                            <i class="fa-solid fa-plus"></i> Add Revision Row
                        </button>
                    </div>
                </div>
            </section>

            <!-- ═══ SECTION 3 — MASTERLIST ═══ -->
            <section class="reg-card" id="section-3" style="display: {{ $masterlist ? 'block' : 'none' }};">
                <div class="reg-card-header">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>Masterlist Registration</span>
                </div>
                <div class="reg-card-body">
                    <div class="reg-ml-grid">
                        <div class="reg-field">
                            <label>Document No.</label>
                            <input type="text" id="masterlistDocNo" name="masterlistDocNo" placeholder="CSPC-INT.DOC-137" value="{{ $masterlist->doc_no ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Deadline of Submission</label>
                            <input type="date" id="deadlineOfSubmission" name="deadlineOfSubmission" value="{{ fmtDate($masterlist->deadline ?? '') }}">
                        </div>
                        <div class="reg-field">
                            <label>Document Receipt</label>
                            <div class="reg-dual">
                                <input type="date" id="masterlistReceiptDate" name="masterlistReceiptDate" value="{{ fmtDate($masterlist->doc_receipt_date ?? '') }}" oninput="calcMasterlistTimeSpent()">
                                <input type="time" id="masterlistReceiptTime" name="masterlistReceiptTime" value="{{ fmtTime($masterlist->doc_receipt_time ?? '') }}" oninput="calcMasterlistTimeSpent()">
                            </div>
                        </div>
                        <div class="reg-field reg-ml-timespent">
                            <label>Time Spent/Minute(s)</label>
                            <input type="text" id="masterlistTimeSpentDisplay" readonly placeholder="--"
                                style="background: #f8fafc; cursor: default; font-weight: 700; text-align: center; font-size: 18px; height: 100%; min-height: 80px;">
                            <input type="hidden" id="masterlistTimeSpent" name="masterlistTimeSpent">
                        </div>
                        <div class="reg-field reg-ml-title-span">
                            <label>Document Title</label>
                            <input type="text" id="masterlistDocTitle" name="masterlistDocTitle" placeholder="Document title" value="{{ $masterlist->doc_title ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Document Registered</label>
                            <div class="reg-dual">
                                <input type="date" id="masterlistRegisteredDate" name="masterlistRegisteredDate" value="{{ fmtDate($masterlist->doc_registered_date ?? '') }}" oninput="calcMasterlistTimeSpent()">
                                <input type="time" id="masterlistRegisteredTime" name="masterlistRegisteredTime" value="{{ fmtTime($masterlist->doc_registered_time ?? '') }}" oninput="calcMasterlistTimeSpent()">
                            </div>
                        </div>
                    </div>
                    <div class="reg-ml-mid">
                        <div class="reg-field">
                            <label>Effectivity Date</label>
                            <input type="date" id="masterlistEffectivityDate" name="masterlistEffectivityDate" value="{{ fmtDate($masterlist->effectivity_date ?? '') }}">
                        </div>
                        <div class="reg-field">
                            <label>Revision No.</label>
                            <input type="number" id="masterlistRevisionNo" name="masterlistRevisionNo" placeholder="0" value="{{ $masterlist->revise_no ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>No. of Pages</label>
                            <input type="number" id="masterlistNoOfPages" name="masterlistNoOfPages" min="0" placeholder="0" value="{{ $masterlist->no_pages ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>In-charge</label>
                            <input type="text" id="masterlistInCharge" name="masterlistInCharge" placeholder="Name" value="{{ $masterlist->in_charge ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Source Unit / Originator</label>
                            <input type="text" id="masterlistSourceUnit" name="masterlistSourceUnit"
                                placeholder="Type office name... (e.g. CAS, President)"
                                autocomplete="off"
                                value="{{ $masterlist->originator_name ?? '' }}"
                                oninput="handleSourceSearch(this, 'masterlistSourceResults')"
                                onfocus="handleSourceSearch(this, 'masterlistSourceResults')">
                            <div id="masterlistSourceResults" class="reg-source-suggestions" style="display:none;"></div>
                            <span class="reg-hint"><i class="fa-solid fa-circle-info"></i> Separate multiple with commas</span>
                        </div>
                    </div>
                    <div class="reg-grid-2">
                        <div class="reg-field">
                            <label>Brief Purpose</label>
                            <input type="text" id="briefPurpose" name="briefPurpose" placeholder="Type here..." value="{{ $masterlist->brief_purpose ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Related Documents</label>
                            <input type="text" id="relatedDocuments" name="relatedDocuments" placeholder="Documents...">
                        </div>
                    </div>
                    <div class="reg-field">
                        <label>Upload Scanned Copy</label>
                        @if($masterlist && $masterlist->scanned_masterlist)
                            <div class="reg-current-file">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span>{{ basename($masterlist->scanned_masterlist) }}</span>
                                <a href="{{ asset('storage/' . $masterlist->scanned_masterlist) }}" target="_blank">View</a>
                            </div>
                        @endif
                        <label class="reg-upload">
                            <input type="file" id="uploadScannedCopy" name="uploadScannedCopy" accept=".pdf,.docx">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>{{ $masterlist && $masterlist->scanned_masterlist ? 'Replace file' : 'Choose .pdf or .docx file' }}</span>
                        </label>
                    </div>
                </div>
            </section>

            <!-- ═══ SECTION — APPROVAL ═══ -->
            <section class="reg-card" id="section-approval" style="display: {{ $approval ? 'block' : 'none' }};">
                <div class="reg-card-header">
                    <i class="fa-solid fa-stamp"></i>
                    <span>Approval Details</span>
                </div>
                <div class="reg-card-body">
                    <div class="reg-grid-3">
                        <div class="reg-field">
                            <label>Approval Body</label>
                            <select id="approvalBody" name="approvalBody" autocomplete="off">
                                <option value="" selected disabled>Select approval body</option>
                            </select>
                        </div>
                        <div class="reg-field">
                            <label>Date</label>
                            <input type="date" id="approvalDate" name="approvalDate" value="{{ fmtDate($approval->approval_date ?? '') }}">
                        </div>
                        <div class="reg-field">
                            <label>Approval No.</label>
                            <input type="text" id="approvalNo" name="approvalNo" placeholder="Approval number" value="{{ $approval->approval_no ?? '' }}">
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ SECTION 4 — RETRIEVAL ═══ -->
            <section class="reg-card" id="section-4" style="display: {{ $retrieval ? 'block' : 'none' }};">
                <div class="reg-card-header">
                    <i class="fa-solid fa-box-archive"></i>
                    <span>Document Retrieval</span>
                </div>
                <div class="reg-card-body reg-split">
                    <div class="reg-split-left">
                        <div class="reg-split-form-grid">
                            <div class="reg-split-form-stack">
                                <div class="reg-grid-2">
                                    <div class="reg-field">
                                        <label>Form Date</label>
                                        <input type="date" id="retrievalFormDate" name="retrievalFormDate" value="{{ fmtDate($retrieval->doc_retrieval_date_file ?? '') }}" oninput="calcRetrievalTimeSpent()">
                                    </div>
                                    <div class="reg-field">
                                        <label>Form Time</label>
                                        <input type="time" id="retrievalFormTime" name="retrievalFormTime" value="{{ fmtTime($retrieval->doc_retrieval_time_file ?? '') }}" oninput="calcRetrievalTimeSpent()">
                                    </div>
                                </div>
                                <div class="reg-grid-2">
                                    <div class="reg-field">
                                        <label>Retrieval Date</label>
                                        <input type="date" id="retrievalDate" name="retrievalDate" value="{{ fmtDate($retrieval->doc_retrieval_date_actual ?? '') }}" oninput="calcRetrievalTimeSpent()">
                                    </div>
                                    <div class="reg-field">
                                        <label>Retrieval Time</label>
                                        <input type="time" id="retrievalTime" name="retrievalTime" value="{{ fmtTime($retrieval->doc_retrieval_time_actual ?? '') }}" oninput="calcRetrievalTimeSpent()">
                                    </div>
                                </div>
                            </div>
                            <div class="reg-field">
                                <label>Time Spent</label>
                                <input type="text" id="retrievalTimeSpentDisplay" readonly placeholder="--">
                                <input type="hidden" id="retrievalTimeSpent" name="retrievalTimeSpent">
                            </div>
                        </div>
                        <div class="reg-field">
                            <label>Remarks</label>
                            <input type="text" id="retrievalRemarks" name="retrievalRemarks" placeholder="Optional remarks" value="{{ $retrieval->remarks ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Upload Scanned Retrieval</label>
                            @if($retrieval && $retrieval->scanned_retrieval)
                                <div class="reg-current-file">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    <span>{{ basename($retrieval->scanned_retrieval) }}</span>
                                    <a href="{{ asset('storage/' . $retrieval->scanned_retrieval) }}" target="_blank">View</a>
                                </div>
                            @endif
                            <label class="reg-upload">
                                <input type="file" id="scannedRet" name="scannedRet" accept=".pdf,.docx">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>{{ $retrieval && $retrieval->scanned_retrieval ? 'Replace file' : 'Choose .pdf or .docx file' }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="reg-split-right">
                        <div class="reg-field">
                            <label>Receiving Offices</label>
                            <div class="reg-search">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="retrievalSearch" placeholder="Search office..."
                                    oninput="handleSearch(this, 'retrievalResults', 'retrievalBody', 'retrievalTotal')"
                                    autocomplete="off">
                                <div id="retrievalResults" class="reg-search-dropdown"></div>
                            </div>
                        </div>
                        <div class="reg-office-table-wrap">
                            <table class="reg-dist-table">
                                <thead>
                                    <tr>
                                        <th>Office / Unit</th>
                                        <th style="width:80px; text-align:center;">Copies</th>
                                        <th style="width:40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="retrievalBody">
                                    @forelse($retrievalOffices as $retOff)
                                    @php $office = \App\Models\Office::find($retOff->office_id); @endphp
                                    <tr class="reg-office-added">
                                        <td>
                                            <input type="hidden" name="retrievalOffice[]" value="{{ $retOff->office_id }}">
                                            <div class="reg-office-name">
                                                <div class="reg-office-icon"><i class="fa-solid fa-building"></i></div>
                                                <span class="reg-office-text">{{ $office->office_name ?? 'Unknown' }}</span>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="number" name="retrievalCopies[]" value="{{ $retOff->copies }}" min="1" oninput="updateTotal('retrievalTotal')">
                                        </td>
                                        <td>
                                            <button type="button" class="btn-remove" onclick="removeOffice(this, 'retrievalTotal', 'retrievalBody')">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr class="reg-empty-row">
                                        <td colspan="3">
                                            <div class="reg-empty-state">
                                                <i class="fa-solid fa-building-circle-xmark"></i>
                                                <span>No offices added yet</span>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" style="text-align:right; font-weight:700;">Total Copies:</td>
                                        <td id="retrievalTotal" style="text-align:center; font-weight:700;">{{ $retrievalOffices->sum('copies') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ SECTION 5 — DISTRIBUTION ═══ -->
            <section class="reg-card" id="section-5" style="display: {{ $distribution ? 'block' : 'none' }};">
                <div class="reg-card-header">
                    <i class="fa-solid fa-share-from-square"></i>
                    <span>Document Distribution</span>
                </div>
                <div class="reg-card-body reg-split">
                    <div class="reg-split-left">
                        <div class="reg-split-form-grid">
                            <div class="reg-split-form-stack">
                                <div class="reg-grid-2">
                                    <div class="reg-field">
                                        <label>Form Date</label>
                                        <input type="date" id="distributionFormDate" name="distributionFormDate" value="{{ fmtDate($distribution->doc_distribution_date_file ?? '') }}" oninput="calcDistributionTimeSpent()">
                                    </div>
                                    <div class="reg-field">
                                        <label>Form Time</label>
                                        <input type="time" id="distributionFormTime" name="distributionFormTime" value="{{ fmtTime($distribution->doc_distribution_time_file ?? '') }}" oninput="calcDistributionTimeSpent()">
                                    </div>
                                </div>
                                <div class="reg-grid-2">
                                    <div class="reg-field">
                                        <label>Distribution Date</label>
                                        <input type="date" id="distributionDate" name="distributionDate" value="{{ fmtDate($distribution->doc_distribution_date_actual ?? '') }}" oninput="calcDistributionTimeSpent()">
                                    </div>
                                    <div class="reg-field">
                                        <label>Distribution Time</label>
                                        <input type="time" id="distributionTime" name="distributionTime" value="{{ fmtTime($distribution->doc_distribution_time_actual ?? '') }}" oninput="calcDistributionTimeSpent()">
                                    </div>
                                </div>
                            </div>
                            <div class="reg-field">
                                <label>Time Spent</label>
                                <input type="text" id="distributionTimeSpentDisplay" readonly placeholder="--">
                                <input type="hidden" id="distributionTimeSpent" name="distributionTimeSpent">
                            </div>
                        </div>
                        <div class="reg-field">
                            <label>Remarks</label>
                            <input type="text" id="distributionRemarks" name="distributionRemarks" placeholder="Optional remarks" value="{{ $distribution->remarks ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Upload Scanned Distribution</label>
                            @if($distribution && $distribution->scanned_distribution)
                                <div class="reg-current-file">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    <span>{{ basename($distribution->scanned_distribution) }}</span>
                                    <a href="{{ asset('storage/' . $distribution->scanned_distribution) }}" target="_blank">View</a>
                                </div>
                            @endif
                            <label class="reg-upload">
                                <input type="file" id="scanneddist" name="scanneddist" accept=".pdf,.docx">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>{{ $distribution && $distribution->scanned_distribution ? 'Replace file' : 'Choose .pdf or .docx file' }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="reg-split-right">
                        <div class="reg-field">
                            <label>Receiving Offices</label>
                            <div class="reg-search">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="distSearch" placeholder="Search office..."
                                    oninput="handleSearch(this, 'distResults', 'distBody', 'distTotal')"
                                    autocomplete="off">
                                <div id="distResults" class="reg-search-dropdown"></div>
                            </div>
                        </div>
                        <div class="reg-office-table-wrap">
                            <table class="reg-dist-table">
                                <thead>
                                    <tr>
                                        <th>Office / Unit</th>
                                        <th style="width:80px; text-align:center;">Copies</th>
                                        <th style="width:40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="distBody">
                                    @forelse($distributionOffices as $distOff)
                                    @php $office = \App\Models\Office::find($distOff->office_id); @endphp
                                    <tr class="reg-office-added">
                                        <td>
                                            <input type="hidden" name="distOffice[]" value="{{ $distOff->office_id }}">
                                            <div class="reg-office-name">
                                                <div class="reg-office-icon"><i class="fa-solid fa-building"></i></div>
                                                <span class="reg-office-text">{{ $office->office_name ?? 'Unknown' }}</span>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="number" name="distCopies[]" value="{{ $distOff->copies }}" min="1" oninput="updateTotal('distTotal')">
                                        </td>
                                        <td>
                                            <button type="button" class="btn-remove" onclick="removeOffice(this, 'distTotal', 'distBody')">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr class="reg-empty-row">
                                        <td colspan="3">
                                            <div class="reg-empty-state">
                                                <i class="fa-solid fa-building-circle-xmark"></i>
                                                <span>No offices added yet</span>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" style="text-align:right; font-weight:700;">Total Copies:</td>
                                        <td id="distTotal" style="text-align:center; font-weight:700;">{{ $distributionOffices->sum('copies') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ FORM ACTIONS ═══ -->
            <div class="reg-actions" id="formActions" style="display: flex;">
                <div class="reg-actions-left">
                    <a href="{{ route('register.update') }}" class="reg-btn reg-btn-cancel">
                        <i class="fa-solid fa-xmark"></i> Cancel
                    </a>
                </div>
                <div class="reg-actions-right">
                    <button type="button" class="reg-btn reg-btn-save" onclick="confirmSave()">
                        <i class="fa-solid fa-floppy-disk"></i> Update Document
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ═══ CONFIRM MODAL ═══ -->
    <div id="confirmModal" class="reg-modal-overlay" style="display:none;">
        <div class="reg-modal">
            <div class="reg-modal-header">
                <i class="fa-solid fa-circle-check"></i>
                <h3>Review Before Updating</h3>
                <button class="reg-modal-close" onclick="closeConfirmModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="reg-modal-body" id="reviewContent"></div>
            <div class="reg-modal-footer">
                <button class="reg-btn reg-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
                <button class="reg-btn reg-btn-save" onclick="submitForm()">
                    <i class="fa-solid fa-check"></i> Confirm Update
                </button>
            </div>
        </div>
    </div>

</body>
</html>