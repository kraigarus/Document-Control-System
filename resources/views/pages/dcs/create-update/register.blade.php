<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


@vite([
'resources/css/dcs/register.css',
'resources/js/dcs/register.js'
])

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

<main class="reg-container">

    @if(session('success'))
    <div class="reg-toast reg-toast-success" id="successToast">
        <div class="reg-toast-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="reg-toast-content">
            <span class="reg-toast-title">Success</span>
            <span class="reg-toast-message">{{ session('success') }}</span>
        </div>
        <button type="button" class="reg-toast-close" onclick="closeToast()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="reg-toast-progress"></div>
    </div>
    @endif

    @if(session('error'))
    <div class="reg-toast reg-toast-error" id="errorToast">
        <div class="reg-toast-icon">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>
        <div class="reg-toast-content">
            <span class="reg-toast-title">Error</span>
            <span class="reg-toast-message">{{ session('error') }}</span>
        </div>
        <button type="button" class="reg-toast-close" onclick="closeToast()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="reg-toast-progress"></div>
    </div>
    @endif

    <div class="reg-header">
        <div class="reg-header-text">
            <p class="reg-breadcrumb">Document Control System / Document Register / <span>Registration</span></p>
            <h1 class="reg-title">Register Document</h1>
        </div>
    </div>

    <form id="masterForm" enctype="multipart/form-data" method="POST" action="{{ route('register.store') }}" auto-complete="off">
        @csrf
        <input type="hidden" id="registrationMode" name="registration_mode" value="new">

        <!-- ═══ TOP SELECTION PANEL ═══ -->
        <section class="reg-panel">
            <div class="reg-panel-grid">
                <div class="reg-field">
                    <label>Version Type</label>
                    <select id="versionType" name="version_id" autocomplete="off">
                        <option value="" selected disabled>Select version</option>
                    </select>
                </div>
                <div class="reg-field">
                    <label>Document Type</label>
                    <select id="docType" name="doc_type_id" disabled autocomplete="off">
                        <option value="" selected disabled>Select type</option>
                    </select>
                </div>
                <div class="reg-field">
                    <label>Sub-Type Document</label>
                    <select id="subType" name="sub_type_id" disabled autocomplete="off">
                        <option value="" selected disabled>Select sub-type</option>
                    </select>
                </div>
            </div>
            <div class="reg-panel-bottom">
                <div class="reg-checklist" id="dynamicCheckboxes"></div>
                <div class="reg-approval-toggle">
                    <span class="reg-toggle-label">Approval</span>
                    <div class="reg-toggle-options">
                        <label class="reg-radio">
                            <input type="radio" name="approval_status" value="applicable"
                                onchange="handleApprovalToggle(true)" disabled>
                            <span>Applicable</span>
                        </label>
                        <label class="reg-radio">
                            <input type="radio" name="approval_status" value="not_applicable"
                                onchange="handleApprovalToggle(false)" checked disabled>
                            <span>Not Applicable</span>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <section class="reg-card" id="section-2" style="display: none;">
            <div class="reg-card-header">
                <span>Document Change Notice</span>
            </div>
            <div class="reg-card-body">
                <div class="reg-grid-3">
                    <div class="reg-field">
                        <label>DCN No.</label>
                        <input type="text" id="dcnNumber" name="dcnNumber" placeholder="Enter DCN No.">
                    </div>
                    <div class="reg-field">
                        <label>DCN Date</label>
                        <input type="date" id="noticeDate" name="noticeDate">
                    </div>
                    <div class="reg-field">
                        <label>DCN Receipt</label>
                        <div class="reg-dual">
                            <input type="date" id="receiptDate" name="receiptDate">
                            <input type="time" id="receiptTime" name="receiptTime">
                        </div>
                    </div>
                </div>
                <div class="reg-grid-2-1">
                    <div class="reg-field">
                        <label>Upload Scanned DCN</label>
                        <label class="reg-upload">
                            <input type="file" id="dcnFile" name="dcnFile" accept=".pdf,.docx">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>Choose .pdf or .docx file</span>
                        </label>
                    </div>
                    <div class="reg-field">
                        <label>Source Unit</label>
                        <select id="dcnSourceUnit" name="dcnSourceUnit">
                            <option value="" selected disabled>Select office</option>
                        </select>
                    </div>
                </div>

                <div class="reg-field">
                    <label>Documents for Revision</label>
                    <div class="reg-table-wrap">
                        <table class="reg-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Document No.</th>
                                    <th>Effectivity Date</th>
                                    <th>Revision No.</th>
                                    <th>Scanned Copy</th>
                                    <th>Brief Purpose</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="revisionTableBody">
                                <tr>
                                    <td><input type="text" name="documentTitle[]" placeholder="Enter Document Title"></td>
                                    <td><input type="text" name="documentNo[]" placeholder="Enter Document No."></td>
                                    <td><input type="date" name="effectiveDate[]"></td>
                                    <td><input type="number" name="revisionNo[]" placeholder="0"></td>
                                    <td><input type="file" name="scannedCopy[]"></td>
                                    <td><input type="text" name="revisionPurpose[]" placeholder="Enter Purpose"></td>
                                    <td>
                                        <button type="button" class="reg-row-del" onclick="this.closest('tr').remove()">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" id="btnAddRevisionRow" onclick="addRevisionRow()">
                        <i class="fa-solid fa-plus"></i> Add Row
                    </button>
                </div>
            </div>
        </section>

        <!-- ═══ SECTION SYLLABI ═══ -->
        <section class="reg-card" id="section-syllabi" style="display: none;">
            <div class="reg-card-header">
                <span>Syllabi</span>
            </div>
            <div class="reg-card-body">

                <!-- ═══ CONTEXT: College / Program / Semester / School Year ═══ -->
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

                <!-- ═══ DOCUMENT INFO ═══ -->
                <div class="reg-grid-4">
                    <div class="reg-field">
                        <label>Document No.</label>
                        <input type="text" id="syllabiDocNo" name="syllabiDocNo" placeholder="Enter Document No.">
                    </div>
                    <div class="reg-field">
                        <label>Document Title</label>
                        <input type="text" id="syllabiDocTitle" name="syllabiDocTitle" placeholder="Enter Document Title">
                    </div>
                    <div class="reg-field">
                        <label>Effectivity Date</label>
                        <input type="date" id="syllabiEffectivityDate" name="syllabiEffectivityDate">
                    </div>
                    <div class="reg-field">
                        <label>Deadline of Submission</label>
                        <input type="date" id="syllabiDeadline" name="syllabiDeadline">
                    </div>
                </div>

                <!-- ═══ WIZARD STEP INDICATOR ═══ -->
                <div class="reg-wizard-steps" id="syllabiStepIndicator">
                    <div class="reg-wizard-step is-active" data-step="1"><span>1</span> Course Info</div>
                    <div class="reg-wizard-step" data-step="2"><span>2</span> DRF</div>
                    <div class="reg-wizard-step" data-step="3"><span>3</span> Registration</div>
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
                                    <th class="col-step2">DRF Received</th>
                                    <th class="col-step2">Scanned DRF</th>

                                    <th class="col-step3">Registered</th>
                                    <th class="col-step3">Date of Registration</th>
                                    <th class="col-step3">Time of Registration</th>
                                    <th class="col-step3">Time Spent</th>

                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="syllabiTableBody"></tbody>
                        </table>
                    </div>
                    <button type="button" id="btnAddSyllabiRow" onclick="addSyllabiRow()">
                        <i class="fa-solid fa-plus"></i> Add Course
                    </button>
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

        <!-- ═══ SECTION 1 — DRF ═══ -->
        <section class="reg-card" id="section-1" style="display: none;">
            <div class="reg-card-header">
                <span>Document Request Form</span>
            </div>
            <div class="reg-card-body">
                <div class="reg-grid-3">
                    <div class="reg-field">
                        <label>DRF No.</label>
                        <input type="text" id="drfNo" name="drfNo" placeholder="Enter DRF No.">
                    </div>
                    <div class="reg-field">
                        <label>DRF Date</label>
                        <input type="date" id="drfDate" name="drfDate">
                    </div>
                    <div class="reg-field">
                        <label>Date Receipt</label>
                        <div class="reg-dual">
                            <input type="date" id="drfReceiptDate" name="drfReceiptDate">
                            <input type="time" id="drfTime" name="drfTime">
                        </div>
                    </div>
                </div>
                <div class="reg-grid-2-1">
                    <div class="reg-field">
                        <label>Document Title</label>
                        <input type="text" id="drfTitle" name="drfTitle" placeholder="Enter document title">
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
                    <label class="reg-upload">
                        <input type="file" id="drfFile" name="drfFile" accept=".pdf,.docx">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Choose .pdf or .docx file</span>
                    </label>
                </div>
            </div>
        </section>

        <!-- ═══ SECTION 3 — MASTERLIST ═══ -->
        <section class="reg-card" id="section-3" style="display: none;">
            <div class="reg-card-header">
                <span>Masterlist Registration</span>
            </div>
            <div class="reg-card-body">
                <!-- Rows 1-2: 4-column grid, Time Spent spans 2 rows -->
                <div class="reg-ml-grid">
                    <!-- Row 1 -->
                    <div class="reg-field" id="mlFieldDocNo">
                        <label>Document No.</label>
                        <input type="text" id="masterlistDocNo" name="masterlistDocNo" placeholder="Enter Document no.">
                        <span id="docNoHint" style="display:block;margin-top:4px;font-size:12px;"></span>
                    </div>
                    <div class="reg-field" id="mlFieldDeadline">
                        <label>Deadline of Submission</label>
                        <input type="date" id="deadlineOfSubmission" name="deadlineOfSubmission">
                    </div>
                    <div class="reg-field">
                        <label>Document Receipt</label>
                        <div class="reg-dual">
                            <input type="date" id="masterlistReceiptDate" name="masterlistReceiptDate" oninput="calcMasterlistTimeSpent()">
                            <input type="time" id="masterlistReceiptTime" name="masterlistReceiptTime" oninput="calcMasterlistTimeSpent()">
                        </div>
                    </div>
                    <div class="reg-field reg-ml-timespent">
                        <label>Time Spent</label>
                        <input type="text" id="masterlistTimeSpentDisplay" readonly placeholder="--"
                            style="background: #f8fafc; cursor: default; font-weight: 700; text-align: center; font-size: 18px; height: 100%; min-height: 80px;">
                        <input type="hidden" id="masterlistTimeSpent" name="masterlistTimeSpent">
                    </div>

                    <!-- Row 2 -->
                    <div class="reg-field reg-ml-title-span" id="mlFieldTitle">
                        <label>Document Title</label>
                        <input type="text" id="masterlistDocTitle" name="masterlistDocTitle" placeholder="Enter Document title">
                    </div>
                    <div class="reg-field">
                        <label>Document Registered</label>
                        <div class="reg-dual">
                            <input type="date" id="masterlistRegisteredDate" name="masterlistRegisteredDate" oninput="calcMasterlistTimeSpent()">
                            <input type="time" id="masterlistRegisteredTime" name="masterlistRegisteredTime" oninput="calcMasterlistTimeSpent()">
                        </div>
                    </div>
                </div>

                <!-- Row 3: 5-column grid -->
                <div class="reg-ml-mid">
                    <div class="reg-field" id="mlFieldEffectivity">
                        <label>Effectivity Date</label>
                        <input type="date" id="masterlistEffectivityDate" name="masterlistEffectivityDate">
                    </div>
                    <div class="reg-field">
                        <label>Revision No.</label>
                        <input type="number" id="masterlistRevisionNo" name="masterlistRevisionNo" min="0" placeholder="0">
                    </div>
                    <div class="reg-field">
                        <label>No. of Pages</label>
                        <input type="number" id="masterlistNoOfPages" name="masterlistNoOfPages" min="0" placeholder="0">
                    </div>
                    <div class="reg-field">
                        <label>Originator</label>
                        <div class="reg-reldocs" id="masterlistOriginatorWidget">
                            <div class="reg-reldocs-inputwrap">
                                <input type="text" id="masterlistOriginatorSearch" class="reg-reldocs-input"
                                    placeholder="Type a name"
                                    autocomplete="off">
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
                                    placeholder="Type office name"
                                    autocomplete="off">
                                <button type="button" class="reg-reldocs-arrow-btn" id="masterlistSourceArrowBtn">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </button>
                            </div>
                            <div id="masterlistSourceSuggestions" class="reg-reldocs-dropdown" style="display:none;"></div>
                            <div id="masterlistSourceInlineChips" class="reg-reldocs-dropdown reg-reldocs-selected-panel" style="display:none;"></div>
                        </div>
                    </div>
                </div>

                <!-- Row 4: 2-column grid -->
                <div class="reg-grid-2">
                    <div class="reg-field">
                        <label>Justification</label>
                        <input type="text" id="briefPurpose" name="briefPurpose" placeholder="Type here...">
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

                            <!-- Search results (shown while typing) -->
                            <div id="relatedDocsResults" class="reg-reldocs-dropdown" style="display:none;"></div>

                            <!-- Selected documents (shown when arrow is clicked) -->
                            <div id="relatedDocsSelectedPanel" class="reg-reldocs-dropdown reg-reldocs-selected-panel" style="display:none;">
                                <div id="relatedDocsChips" class="reg-reldocs-chips"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 5: Full width -->
                <div class="reg-field">
                    <label>Upload Scanned Copy</label>
                    <label class="reg-upload">
                        <input type="file" id="uploadScannedCopy" name="uploadScannedCopy" accept=".pdf,.docx">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Choose .pdf or .docx file</span>
                    </label>
                </div>
            </div>
        </section>

        <!-- ═══ APPROVAL DETAILS ═══ -->
        <section class="reg-card" id="section-approval" style="display: none;">
            <div class="reg-card-header">
                <span>Approval Details</span>
            </div>
            <div class="reg-card-body">
                <div class="reg-grid-3">
                    <div class="reg-field">
                        <label>Approval Body</label>
                        <select id="approvalBody" name="approvalBody">
                            <option value="" disabled selected>Select approval body</option>
                        </select>
                    </div>
                    <div class="reg-field">
                        <label>Approval Date</label>
                        <input type="date" id="approvalDate" name="approvalDate">
                    </div>
                    <div class="reg-field">
                        <label>Approval No.</label>
                        <input type="text" id="approvalNo" name="approvalNo" placeholder="Enter Approval No.">
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ SECTION 4 — DOCUMENT RETRIEVAL ═══ -->
        <section class="reg-card" id="section-4" style="display: none;">
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
                                    <input type="date" id="retrievalFormDate" name="retrievalFormDate" oninput="calcRetrievalTimeSpent()">
                                    <input type="time" id="retrievalFormTime" name="retrievalFormTime" oninput="calcRetrievalTimeSpent()">
                                </div>
                            </div>
                            <div class="reg-field">
                                <label>Retrieval Date & Time</label>
                                <div class="reg-dual">
                                    <input type="date" id="retrievalDate" name="retrievalDate" oninput="calcRetrievalTimeSpent()">
                                    <input type="time" id="retrievalTime" name="retrievalTime" oninput="calcRetrievalTimeSpent()">
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
                        <input type="text" id="retrievalRemarks" name="retrievalRemarks" placeholder="Type here...">
                    </div>
                    <div class="reg-field">
                        <label>Upload Scanned D&R</label>
                        <label class="reg-upload">
                            <input type="file" id="scannedRet" name="scannedRet" accept=".pdf,.docx">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>Choose .pdf or .docx file</span>
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
                            <input type="text" id="retrievalSearch" placeholder="Search and add office..." autocomplete="off"
                                oninput="handleSearch(this, 'retrievalResults', 'retrievalBody', 'totalRetrievalCopies')">
                            <div id="retrievalResults" class="reg-search-dropdown" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="reg-office-table-wrap">
                        <table class="reg-dist-table">
                            <thead>
                                <tr>
                                    <th>Receiving Office(s)</th>
                                    <th style="width: 110px; text-align: center;">No. of Copies</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="retrievalBody">
                                <tr class="reg-empty-row">
                                    <td colspan="3">
                                        <div class="reg-empty-state">
                                            <i class="fa-solid fa-building-circle-xmark"></i>
                                            <span>No offices added yet</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Total No. of Copies</td>
                                    <td id="totalRetrievalCopies" style="text-align: center; font-weight: 700;">0</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ SECTION 5 — DOCUMENT DISTRIBUTION ═══ -->
        <section class="reg-card" id="section-5" style="display: none;">
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
                                    <input type="date" id="distributionFormDate" name="distributionFormDate" oninput="calcDistributionTimeSpent()">
                                    <input type="time" id="distributionFormTime" name="distributionFormTime" oninput="calcDistributionTimeSpent()">
                                </div>
                            </div>
                            <div class="reg-field">
                                <label>Distribution Date & Time</label>
                                <div class="reg-dual">
                                    <input type="date" id="distributionDate" name="distributionDate" oninput="calcDistributionTimeSpent()">
                                    <input type="time" id="distributionTime" name="distributionTime" oninput="calcDistributionTimeSpent()">
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
                        <input type="text" id="distributionRemarks" name="distributionRemarks" placeholder="Type here...">
                    </div>
                    <div class="reg-field">
                        <label>Upload Scanned D&R</label>
                        <label class="reg-upload">
                            <input type="file" id="scanneddist" name="scanneddist" accept=".pdf,.docx">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>Choose .pdf or .docx file</span>
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
                            <input type="text" id="distSearch" placeholder="Search and add office..." autocomplete="off"
                                oninput="handleSearch(this, 'distResults', 'distBody', 'totalDistCopies')">
                            <div id="distResults" class="reg-search-dropdown" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="reg-office-table-wrap">
                        <table class="reg-dist-table">
                            <thead>
                                <tr>
                                    <th>Receiving Office(s)</th>
                                    <th style="width: 110px; text-align: center;">No. of Copies</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="distBody">
                                <tr class="reg-empty-row">
                                    <td colspan="3">
                                        <div class="reg-empty-state">
                                            <i class="fa-solid fa-building-circle-xmark"></i>
                                            <span>No offices added yet</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Total No. of Copies</td>
                                    <td id="totalDistCopies" style="text-align: center; font-weight: 700;">0</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ FORM ACTIONS ═══ -->
        <section class="reg-actions" id="formActions" style="display: none;">
            <div class="reg-actions-left">
                <a href="{{ route('dashboard') }}" class="reg-btn reg-btn-cancel">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </a>
            </div>
            <div class="reg-actions-right">
                <button type="button" class="reg-btn reg-btn-report" onclick="handleGenerateReport()">
                    <i class="fa-solid fa-file-pdf"></i> Generate Report
                </button>
                <button type="button" id="btnSaveDocument" class="reg-btn reg-btn-save" onclick="confirmSave()">
                    <i class="fa-solid fa-floppy-disk"></i> Save Document
                </button>
            </div>
        </section>

    </form>
</main>

<!-- ═══ CONFIRMATION MODAL ═══ -->
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
                <i class="fa-solid fa-check"></i> Confirm Save
            </button>
        </div>
    </div>
</div>


@push('styles')
    @vite(['resources/css/dcs/register.css'])
@endpush

@push('scripts')
    @vite(['resources/js/dcs/register.js'])
@endpush

$.ajax({
    url: '{{ route("register.checkDocNo") }}',
    data: {
        doc_no: someValue,
        doc_type_id: someValue,
        sub_type_id: someValue   // ← make sure this line exists
    },
});