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

    @php
        // ── Seed data for the chip-style source/originator widgets ──
        $drfOfficesSeed = collect($drfOffices ?? [])->map(fn($o) => [
            'id'    => $o->office->office_id ?? $o->office_id,
            'label' => $o->office->office_name ?? 'Unknown',
        ])->values();

        $masterlistSourceSeed = collect($sourceOffices ?? [])->map(fn($o) => [
            'id'    => $o->office->office_id ?? ('name_' . $o->masterlist_source_id),
            'label' => $o->office->office_name ?? $o->source_name ?? 'Unknown',
        ])->values();

        $masterlistOriginatorSeed = ($masterlist && $masterlist->originator_name)
            ? [['label' => $masterlist->originator_name]]
            : [];

        // ── Group flat Syllabi rows back into wizard "course groups" ──
        $syllabiGroupsSeed = collect($syllabi ?? [])->groupBy('course_name')->map(function ($rows, $courseName) {
            $first = $rows->first();
            return [
                'course_name'    => $courseName,
                'availability'   => $first->syllabi_availability === 'available',
                'college_id'     => $first->college_id,
                'program_id'     => $first->program_id,
                'semester_id'    => $first->semester_id,
                'school_year_id' => $first->school_year_id,
                'rows' => $rows->values()->map(function ($r) {
                    return [
                        'originator'             => $r->originator,
                        'no_pages'               => $r->no_pages,
                        'date_received'          => fmtDate($r->date_received),
                        'time_received'          => fmtTime($r->time_received),
                        'drf_available'          => $r->drf_availability === 'available',
                        'drf_no'                 => $r->drf_no,
                        'drf_date'               => fmtDate($r->drf_date),
                        'drf_received_date'      => fmtDate($r->drf_received_date),
                        'registered'             => $r->registered === 'registered',
                        'date_of_registration'   => fmtDate($r->date_of_registration),
                        'time_of_registration'   => fmtTime($r->time_of_registration),
                        'time_spent'             => $r->time_spent,
                        'scanned_registration_name' => $r->scanned_registration ? basename($r->scanned_registration) : null,
                    ];
                })->values(),
            ];
        })->values();

        $relatedDocsData = $masterlist
            ? $masterlist->allRelatedDocuments()->map(fn($m) => [
                'masterlist_id' => $m->masterlist_id,
                'doc_no' => $m->doc_no,
                'doc_title' => $m->doc_title,
                'label' => $m->doc_title . ($m->doc_no ? ' ('.$m->doc_no.')' : ''),
            ])
            : collect([]);
    @endphp

    <script>
        window.APP_CONFIG = {
            CURRENT_VERSION_ID: {{ isset($docRequest) ? $docRequest->version_id : 'null' }},
            CURRENT_DOC_TYPE_ID: {{ isset($docRequest) ? $docRequest->doc_type_id : 'null' }},
            CURRENT_SUB_TYPE_ID: {{ isset($docRequest) && $docRequest->sub_type_id ? $docRequest->sub_type_id : 'null' }},
            CURRENT_DCN_SOURCE: '{{ isset($dcn) && $dcn ? $dcn->office_id : "" }}',
            CURRENT_APPROVAL_BODY: '{{ isset($approval) && $approval ? $approval->approval_body_id : "" }}',
        };
        window.__existingRelatedDocs = {!! $relatedDocsData->toJson() !!};
        window.__existingDrfOffices = {!! $drfOfficesSeed->toJson() !!};
        window.__existingMasterlistSource = {!! $masterlistSourceSeed->toJson() !!};
        window.__existingMasterlistOriginator = {!! json_encode($masterlistOriginatorSeed) !!};
        window.__existingSyllabiGroups = {!! $syllabiGroupsSeed->toJson() !!};
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

    <div class="reg-container main-content">
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

        <form id="masterForm" method="POST" action="{{ route('register.updateDoc', $docRequest->id) }}" enctype="multipart/form-data">
            <input type="hidden" id="requestId" value="{{ $docRequest->request_id }}">
            @csrf
            @method('PUT')

            <!-- ═══ TOP PANEL ═══ -->
            <div class="reg-panel">
                <div class="reg-panel-grid">
                    <div class="reg-field">
                        <label>Version Type</label>
                        <select id="versionType" name="version_id" autocomplete="off" disabled data-last-valid="{{ $docRequest->version_id }}">
                            <option value="" disabled>Select version</option>
                        </select>
                        <input type="hidden" name="version_id" value="{{ $docRequest->version_id }}">
                    </div>
                    <div class="reg-field">
                        <label>Document Type</label>
                        <select id="docType" name="doc_type_id" autocomplete="off" disabled data-last-valid="{{ $docRequest->doc_type_id }}">
                            <option value="" disabled>Select document type</option>
                        </select>
                        <input type="hidden" name="doc_type_id" value="{{ $docRequest->doc_type_id }}">
                    </div>
                    <div class="reg-field">
                        <label>Sub-Type Document</label>
                        <select id="subType" name="sub_type_id" autocomplete="off" disabled>
                            <option value="" selected disabled>Select sub-type</option>
                        </select>
                        @if($docRequest->sub_type_id)
                            <input type="hidden" name="sub_type_id" value="{{ $docRequest->sub_type_id }}">
                        @endif
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

            <!-- ═══ SYLLABI (full wizard — mirrors register.blade.php) ═══ -->
            <section class="reg-card" id="section-syllabi" style="display: {{ $syllabi->count() > 0 ? 'block' : 'none' }};">
                <div class="reg-card-header">
                    <span>Syllabi</span>
                </div>
                <div class="reg-card-body">

                    <div class="reg-grid-4">
                        <div class="reg-field">
                            <label>College</label>
                            <select id="syllabiCollege" name="college_id">
                                <option value="" selected disabled>Select college</option>
                            </select>
                        </div>
                        <div class="reg-field">
                            <label>Program</label>
                            <select id="syllabiProgram" name="program_id" disabled>
                                <option value="" selected disabled>Select program</option>
                            </select>
                        </div>
                        <div class="reg-field">
                            <label>Semester</label>
                            <select id="syllabiSemester" name="semester_id" disabled>
                                <option value="" selected disabled>Select semester</option>
                            </select>
                        </div>
                        <div class="reg-field">
                            <label>School Year</label>
                            <select id="syllabiSchoolYear" name="school_year_id" disabled>
                                <option value="" selected disabled>Select school year</option>
                            </select>
                        </div>
                    </div>

                    <div class="reg-grid-4">
                        <div class="reg-field">
                            <label>Document No.</label>
                            <input type="text" id="syllabiDocNo" name="syllabiDocNo" placeholder="Enter Document No." value="{{ $masterlist->doc_no ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Document Title</label>
                            <input type="text" id="syllabiDocTitle" name="syllabiDocTitle" placeholder="Enter Document Title" value="{{ $masterlist->doc_title ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Effectivity Date</label>
                            <input type="date" id="syllabiEffectivityDate" name="syllabiEffectivityDate" value="{{ fmtDate($masterlist->effectivity_date ?? '') }}">
                        </div>
                        <div class="reg-field">
                            <label>Deadline of Submission</label>
                            <input type="date" id="syllabiDeadline" name="syllabiDeadline" value="{{ fmtDate($masterlist->deadline ?? '') }}">
                        </div>
                    </div>

                    <div class="reg-wizard-steps" id="syllabiStepIndicator">
                        <div class="reg-wizard-step is-active" data-step="1"><span>1</span> Course Info</div>
                        <div class="reg-wizard-step" data-step="2"><span>2</span> DRF</div>
                        <div class="reg-wizard-step" data-step="3"><span>3</span>Masterlist  Registration</div>
                    </div>

                    <div class="reg-field">
                        <div class="reg-table-wrap">
                            <table class="reg-table reg-wizard-table" id="syllabiWizardTable" data-active-step="1">
                                <thead>
                                    <tr>
                                        <th class="col-pinned">Course Name</th>
                                        <th class="col-step1">Syllabi Availability</th>
                                        <th class="col-step1">No. Copies</th>
                                        <th class="col-step1">Originator</th>
                                        <th class="col-step1">No. Pages</th>
                                        <th class="col-step1">Date Received</th>
                                        <th class="col-step1">Time Received</th>

                                        <th class="col-step2">DRF Availability</th>
                                        <th class="col-step2">DRF No.</th>
                                        <th class="col-step2">DRF Date</th>
                                        <th class="col-step2">DRF Received Date</th>

                                        <th class="col-step3">Registered</th>
                                        <th class="col-step3">Date of Registration</th>
                                        <th class="col-step3">Time of Registration</th>
                                        <th class="col-step3">Time Spent</th>
                                        <th class="col-step3">Scanned DRF</th>

                                        <th class="col-pinned"></th>
                                    </tr>
                                </thead>
                                <tbody id="syllabiTableBody"></tbody>
                            </table>
                        </div>
                        <button type="button" id="btnAddSyllabiRow" onclick="addSyllabiRow()">
                            <i class="fa-solid fa-plus"></i> Add Course
                        </button>
                        <div class="reg-syllabi-copies-total">
                            Total No. of Copies: <span id="totalSyllabiCopies">0</span>
                        </div>
                    </div>

                    <div class="reg-wizard-nav">
                        <button type="button" class="reg-btn reg-btn-cancel" id="syllabiBackBtn" onclick="syllabiStepBack()" style="display:none;">
                            <i class="fa-solid fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="reg-btn reg-btn-save" id="syllabiNextBtn" onclick="syllabiStepNext()">
                            Next <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </section>

            <section class="reg-card" id="section-2" style="display: {{ $dcn ? 'block' : 'none' }};">
                <div class="reg-card-header">
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
                            <label>DCN Receipt</label>
                            <div class="reg-dual">
                                <input type="date" id="receiptDate" name="receiptDate" value="{{ fmtDate($dcn->dcn_receipt_date ?? '') }}">
                                <input type="time" id="receiptTime" name="receiptTime" value="{{ fmtTime($dcn->dcn_receipt_time ?? '') }}">
                            </div>
                        </div>
                    </div>
                    <div class="reg-grid-2-1">
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
                        <div class="reg-field">
                            <label>Source Unit</label>
                            <select id="dcnSourceUnit" name="dcnSourceUnit" autocomplete="off">
                                <option value="" selected disabled>Select office</option>
                            </select>
                        </div>
                    </div>

                    <div class="reg-field reg-revision-table">
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
                                            <label class="reg-upload-cell">
                                                <input type="file" name="scannedCopy[]" accept=".pdf,.docx">
                                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                                <span>{{ $rev->scanned_copy ? 'Replace file' : 'No file chosen' }}</span>
                                            </label>
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
                                        <td>
                                            <label class="reg-upload-cell">
                                                <input type="file" name="scannedCopy[]" accept=".pdf,.docx">
                                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                                <span>No file chosen</span>
                                            </label>
                                        </td>
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

            <!-- ═══ SECTION 1 — DRF ═══ -->
            <section class="reg-card" id="section-1" style="display: {{ $drf ? 'block' : 'none' }};">
                <div class="reg-card-header">
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
                            <label>Date Receipt</label>
                            <div class="reg-dual">
                                <input type="date" id="drfReceiptDate" name="drfReceiptDate" value="{{ fmtDate($drf->drf_receipt_date ?? '') }}">
                                <input type="time" id="drfTime" name="drfTime" value="{{ fmtTime($drf->drf_receipt_time ?? '') }}">
                            </div>
                        </div>
                    </div>
                    <div class="reg-grid-2-1">
                        <div class="reg-field">
                            <label>Document Title</label>
                            <input type="text" id="drfTitle" name="drfTitle" placeholder="Title" value="{{ $drf->doc_title ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Source Unit</label>
                            <div class="reg-reldocs" id="drfSourceUnitWidget">
                                <div class="reg-reldocs-inputwrap">
                                    <input type="text" id="drfSourceUnitSearch" class="reg-reldocs-input"
                                        placeholder="Type to search offices..." autocomplete="off">
                                    <button type="button" class="reg-reldocs-arrow-btn" id="drfSourceArrowBtn">
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                </div>
                                <div id="drfSourceResults" class="reg-reldocs-dropdown" style="display:none;"></div>
                                <div id="drfSourceInlineChips" class="reg-reldocs-dropdown reg-reldocs-selected-panel" style="display:none;"></div>
                            </div>
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

            <!-- ═══ SECTION 3 — MASTERLIST ═══ -->
            <section class="reg-card" id="section-3" style="display: {{ $masterlist ? 'block' : 'none' }};">
                <div class="reg-card-header">
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
                            <label>Time Spent</label>
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
                            <input type="number" id="masterlistRevisionNo" placeholder="0"
                                value="{{ $masterlist->revise_no ?? '' }}" disabled
                                style="background:#f1f5f9; cursor:not-allowed; opacity:0.7;">
                            <input type="hidden" name="masterlistRevisionNo" value="{{ $masterlist->revise_no ?? '0' }}">
                        </div>
                        <div class="reg-field">
                            <label>No. of Pages</label>
                            <input type="number" id="masterlistNoOfPages" name="masterlistNoOfPages" min="0" placeholder="0" value="{{ $masterlist->no_pages ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Originator</label>
                            <div class="reg-reldocs" id="masterlistOriginatorWidget">
                                <div class="reg-reldocs-inputwrap">
                                    <input type="text" id="masterlistOriginatorSearch" class="reg-reldocs-input"
                                        placeholder="Type a name" autocomplete="off">
                                    <button type="button" class="reg-reldocs-arrow-btn" id="masterlistOriginatorArrowBtn">
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                </div>
                                <div id="masterlistOriginatorResults" class="reg-reldocs-dropdown" style="display:none;"></div>
                                <div id="masterlistOriginatorInlineChips" class="reg-reldocs-dropdown reg-reldocs-selected-panel" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="reg-field">
                            <label>Source Unit</label>
                            <div class="reg-reldocs" id="masterlistSourceWidget">
                                <div class="reg-reldocs-inputwrap">
                                    <input type="text" id="masterlistSourceSearch" class="reg-reldocs-input"
                                        placeholder="Type office name" autocomplete="off">
                                    <button type="button" class="reg-reldocs-arrow-btn" id="masterlistSourceArrowBtn">
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                </div>
                                <div id="masterlistSourceSuggestions" class="reg-reldocs-dropdown" style="display:none;"></div>
                                <div id="masterlistSourceInlineChips" class="reg-reldocs-dropdown reg-reldocs-selected-panel" style="display:none;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="reg-grid-2">
                        <div class="reg-field">
                            <label>Justification</label>
                            <input type="text" id="briefPurpose" name="briefPurpose" placeholder="Type here..." value="{{ $masterlist->brief_purpose ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Related Documents</label>
                            <div class="reg-reldocs" id="relatedDocsWidget">
                                <div class="reg-reldocs-inputwrap">
                                    <input type="text" id="relatedDocsSearch" class="reg-reldocs-input"
                                        placeholder="Type a document title to search..."
                                        autocomplete="off"
                                        oninput="handleRelatedDocSearch(this)"
                                        onfocus="handleRelatedDocFocus()">
                                    <button type="button" class="reg-reldocs-arrow-btn" onclick="toggleRelatedDocsSelected(event)">
                                        <i class="fa-solid fa-chevron-down" id="relatedDocsArrowIcon"></i>
                                    </button>
                                </div>
                                <div id="relatedDocsResults" class="reg-reldocs-dropdown" style="display:none;"></div>
                                <div id="relatedDocsSelectedPanel" class="reg-reldocs-dropdown reg-reldocs-selected-panel" style="display:none;">
                                    <div id="relatedDocsChips" class="reg-reldocs-chips"></div>
                                </div>
                            </div>
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

            <!-- ═══ APPROVAL ═══ -->
            <section class="reg-card" id="section-approval" style="display: {{ $approval ? 'block' : 'none' }};">
                <div class="reg-card-header">
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
                            <label>Approval Date</label>
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
                    <span>Document Retrieval</span>
                </div>
                <div class="reg-card-body reg-split">
                    <div class="reg-split-left">
                        <div class="reg-split-form-grid">
                            <div class="reg-split-form-stack">
                                <div class="reg-field">
                                    <label>Retrieval Form Date</label>
                                    <div class="reg-dual">
                                        <input type="date" id="retrievalFormDate" name="retrievalFormDate" value="{{ fmtDate($retrieval->doc_retrieval_date_file ?? '') }}" oninput="calcRetrievalTimeSpent()">
                                        <input type="time" id="retrievalFormTime" name="retrievalFormTime" value="{{ fmtTime($retrieval->doc_retrieval_time_file ?? '') }}" oninput="calcRetrievalTimeSpent()">
                                    </div>
                                </div>
                                <div class="reg-field">
                                    <label>Retrieval Date & Time</label>
                                    <div class="reg-dual">
                                        <input type="date" id="retrievalDate" name="retrievalDate" value="{{ fmtDate($retrieval->doc_retrieval_date_actual ?? '') }}" oninput="calcRetrievalTimeSpent()">
                                        <input type="time" id="retrievalTime" name="retrievalTime" value="{{ fmtTime($retrieval->doc_retrieval_time_actual ?? '') }}" oninput="calcRetrievalTimeSpent()">
                                    </div>
                                </div>
                            </div>
                            <div class="reg-field">
                                <label>Time Spent/Minute(s)</label>
                                <input type="text" id="retrievalTimeSpentDisplay" readonly placeholder="--" style="background: #f8fafc; cursor: default;">
                                <input type="hidden" id="retrievalTimeSpent" name="retrievalTimeSpent">
                            </div>
                        </div>
                        <div class="reg-field">
                            <label>Remarks</label>
                            <input type="text" id="retrievalRemarks" name="retrievalRemarks" placeholder="Optional remarks" value="{{ $retrieval->remarks ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Upload Scanned D&amp;R</label>
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
                            <label>Select office(s) for retrieval</label>
                            <div class="reg-search">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="M21 21l-4.35-4.35"/>
                                </svg>
                                <input type="text" id="retrievalSearch" placeholder="Search and add office..."
                                    oninput="handleSearch(this, 'retrievalResults', 'retrievalBody', 'retrievalTotal')"
                                    autocomplete="off">
                                <div id="retrievalResults" class="reg-search-dropdown" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="reg-office-table-wrap">
                            <table class="reg-dist-table">
                                <thead>
                                    <tr>
                                        <th>Receiving Office(s)</th>
                                        <th style="width:110px; text-align:center;">No. of Copies</th>
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
                                            <input type="number" name="retrievalCopies[]" value="{{ $retOff->copies }}" min="1" oninput="updateTotal('retrievalTotal', 'retrievalBody')">
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
                                        <td colspan="2" style="text-align:right; font-weight:700;">Total No. of Copies:</td>
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
                    <span>Document Distribution</span>
                </div>
                <div class="reg-card-body reg-split">
                    <div class="reg-split-left">
                        <div class="reg-split-form-grid">
                            <div class="reg-split-form-stack">
                                <div class="reg-field">
                                    <label>Distribution Form Date</label>
                                    <div class="reg-dual">
                                        <input type="date" id="distributionFormDate" name="distributionFormDate" value="{{ fmtDate($distribution->doc_distribution_date_file ?? '') }}" oninput="calcDistributionTimeSpent()">
                                        <input type="time" id="distributionFormTime" name="distributionFormTime" value="{{ fmtTime($distribution->doc_distribution_time_file ?? '') }}" oninput="calcDistributionTimeSpent()">
                                    </div>
                                </div>
                                <div class="reg-field">
                                    <label>Distribution Date & Time</label>
                                    <div class="reg-dual">
                                        <input type="date" id="distributionDate" name="distributionDate" value="{{ fmtDate($distribution->doc_distribution_date_actual ?? '') }}" oninput="calcDistributionTimeSpent()">
                                        <input type="time" id="distributionTime" name="distributionTime" value="{{ fmtTime($distribution->doc_distribution_time_actual ?? '') }}" oninput="calcDistributionTimeSpent()">
                                    </div>
                                </div>
                            </div>
                            <div class="reg-field">
                                <label>Time Spent/Minute(s)</label>
                                <input type="text" id="distributionTimeSpentDisplay" readonly placeholder="--" style="background: #f8fafc; cursor: default;">
                                <input type="hidden" id="distributionTimeSpent" name="distributionTimeSpent">
                            </div>
                        </div>
                        <div class="reg-field">
                            <label>Remarks</label>
                            <input type="text" id="distributionRemarks" name="distributionRemarks" placeholder="Optional remarks" value="{{ $distribution->remarks ?? '' }}">
                        </div>
                        <div class="reg-field">
                            <label>Upload Scanned D&amp;R</label>
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
                            <label>Select office(s) for distribution</label>
                            <div class="reg-search">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="M21 21l-4.35-4.35"/>
                                </svg>
                                <input type="text" id="distSearch" placeholder="Search and add office..."
                                    oninput="handleSearch(this, 'distResults', 'distBody', 'distTotal')"
                                    autocomplete="off">
                                <div id="distResults" class="reg-search-dropdown" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="reg-office-table-wrap">
                            <table class="reg-dist-table">
                                <thead>
                                    <tr>
                                        <th>Receiving Office(s)</th>
                                        <th style="width:110px; text-align:center;">No. of Copies</th>
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
                                            <input type="number" name="distCopies[]" value="{{ $distOff->copies }}" min="1" oninput="updateTotal('distTotal', 'distBody')">
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
                                        <td colspan="2" style="text-align:right; font-weight:700;">Total No. of Copies:</td>
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
    <div class="reg-modal-overlay" id="confirmModal" style="display: none;">
        <div class="reg-modal">
            <div class="reg-modal-header">
                <i class="fa-solid fa-clipboard-check"></i>
                <h3>Review & Confirm</h3>
                <button type="button" class="reg-modal-close" onclick="closeConfirmModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="reg-modal-body" id="reviewContent">
                <!-- Populated by JS -->
            </div>
            <div class="reg-modal-footer">
                <button type="button" class="reg-btn reg-btn-cancel" onclick="closeConfirmModal()">
                    <i class="fa-solid fa-xmark"></i> Go Back
                </button>
                <button type="button" class="reg-btn reg-btn-save" onclick="submitForm()">
                    <i class="fa-solid fa-check"></i> Confirm Update
                </button>
            </div>
        </div>
    </div>

</body>
</html>