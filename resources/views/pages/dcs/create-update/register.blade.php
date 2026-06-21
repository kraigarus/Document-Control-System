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
    <div class="reg-header">
        <div class="reg-header-text">
            <p class="reg-breadcrumb">Document Control System / Registration</p>
            <h1 class="reg-title">Register Document</h1>
        </div>
    </div>

    <form id="masterForm" enctype="multipart/form-data" method="POST" action="{{ route('register.store') }}">
        @csrf

        <!-- ═══ TOP SELECTION PANEL ═══ -->
        <section class="reg-panel">
            <div class="reg-panel-grid">
                <div class="reg-field">
                    <label>Version Type</label>
                    <select id="versionType" name="version_id" onchange="handleVersionChange()">
                        <option value="" selected disabled>Select version</option>
                    </select>
                </div>
                <div class="reg-field">
                    <label>Document Type</label>
                    <select id="docType" name="doc_type_id" onchange="handleDocTypeChange()" disabled>
                        <option value="" selected disabled>Select type</option>
                    </select>
                </div>
                <div class="reg-field">
                    <label>Sub-Type Document</label>
                    <select id="subType" name="sub_type_id" onchange="validateChecklistState()" disabled>
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

        <!-- ═══ SECTION SYLLABI ═══ -->
        <section class="reg-card" id="section-syllabi" style="display: none;">
            <div class="reg-card-header">
                <span>Syllabi</span>
            </div>
            <div class="reg-card-body">
                <div class="reg-table-wrap">
                    <table class="reg-table">
                        <thead>
                            <tr>
                                <th>Course Name</th>
                                <th>Syllabi Availability</th>
                                <th>No. of Pages</th>
                                <th>DRF Availability</th>
                                <th>DRF No.</th>
                                <th>DRF Date</th>
                                <th>DRF Received Date</th>
                                <th>Scanned DRF</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="syllabiTableBody">
                            <tr>
                                <td>
                                    <input type="text" name="syllabiCourseName[]" placeholder="Enter course name">
                                </td>
                                <td>
                                    <select name="syllabiAvailability[]">
                                        <option value="" disabled selected>Select</option>
                                        <option value="available">Available</option>
                                        <option value="not_available">Not Available</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="syllabiNoPages[]" min="0" placeholder="0">
                                </td>
                                <td>
                                    <select name="syllabiDrfAvailability[]">
                                        <option value="" disabled selected>Select</option>
                                        <option value="available">Available</option>
                                        <option value="not_available">Not Available</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="syllabiDrfNo[]" placeholder="DRF-001">
                                </td>
                                <td>
                                    <input type="date" name="syllabiDrfDate[]">
                                </td>
                                <td>
                                    <input type="date" name="syllabiDrfReceived[]">
                                </td>
                                <td>
                                    <label class="reg-upload-cell">
                                        <input type="file" name="syllabiScannedDrf[]" accept=".pdf,.jpg,.png">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <span>No file chosen</span>
                                    </label>
                                </td>
                                <td>
                                    <button type="button" class="reg-row-del" onclick="this.closest('tr').remove()">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="reg-add-row" onclick="addSyllabiRow()">
                    <i class="fa-solid fa-plus"></i> Add Row
                </button>
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
                        <input type="text" id="drfNo" name="drfNo" placeholder="DRF-2025-001">
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
                        <select id="drfSourceUnit" name="drfSourceUnit">
                            <option value="" disabled selected>Select source unit</option>
                        </select>
                    </div>
                </div>
                <div class="reg-field">
                    <label>Upload Scanned DRF</label>
                    <label class="reg-upload">
                        <input type="file" id="drfFile" name="drfFile">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Choose file or drag and drop</span>
                    </label>
                </div>
            </div>
        </section>

        <!-- ═══ SECTION 2 — DCN ═══ -->
        <section class="reg-card" id="section-2" style="display: none;">
            <div class="reg-card-header">
                <span>Document Change Notice</span>
            </div>
            <div class="reg-card-body">
                <div class="reg-grid-3">
                    <div class="reg-field">
                        <label>DCN No.</label>
                        <input type="text" id="dcnNumber" name="dcnNumber" placeholder="0000-00-000">
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
                            <input type="file" id="dcnFile" name="dcnFile">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>Choose file or drag and drop</span>
                        </label>
                    </div>
                    <div class="reg-field">
                        <label>Source Unit / Office</label>
                        <select id="dcnSourceUnit" name="dcnSourceUnit">
                            <option value="" disabled selected>Select</option>
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
                                    <td><input type="text" name="documentTitle[]" placeholder="Title"></td>
                                    <td><input type="text" name="documentNo[]" placeholder="Doc No."></td>
                                    <td><input type="date" name="effectiveDate[]"></td>
                                    <td><input type="text" name="revisionNo[]" placeholder="0"></td>
                                    <td><input type="file" name="scannedCopy[]"></td>
                                    <td><input type="text" name="revisionPurpose[]" placeholder="Purpose"></td>
                                    <td>
                                        <button type="button" class="reg-row-del" onclick="this.closest('tr').remove()">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" id="addRevisionRow" class="reg-add-row" onclick="addRevisionRow()">
                        <i class="fa-solid fa-plus"></i> Add Row
                    </button>
                </div>
            </div>
        </section>

        <!-- ═══ SECTION 3 — MASTERLIST ═══ -->
        <section class="reg-card" id="section-3" style="display: none;">
            <div class="reg-card-header">
                <span>Masterlist Registration</span>
            </div>
            <div class="reg-card-body">
                <div class="reg-grid-2">
                    <div class="reg-field">
                        <label>Document No.</label>
                        <input type="text" id="masterlistDocNo" name="masterlistDocNo" placeholder="CSPC-INT.DOC-137">
                    </div>
                    <div class="reg-field">
                        <label>Document Title</label>
                        <input type="text" id="masterlistDocTitle" name="masterlistDocTitle" placeholder="Document title">
                    </div>
                </div>
                <div class="reg-grid-4">
                    <div class="reg-field">
                        <label>Deadline of Submission</label>
                        <input type="date" id="deadlineOfSubmission" name="deadlineOfSubmission">
                    </div>
                    <div class="reg-field">
                        <label>Document Receipt</label>
                        <div class="reg-dual">
                            <input type="date" id="masterlistReceiptDate" name="masterlistReceiptDate">
                            <input type="time" id="masterlistReceiptTime" name="masterlistReceiptTime">
                        </div>
                    </div>
                    <div class="reg-field">
                        <label>Effectivity Date</label>
                        <input type="date" id="masterlistEffectivityDate" name="masterlistEffectivityDate">
                    </div>
                    <div class="reg-field">
                        <label>Time Spent (min)</label>
                        <input type="number" id="masterlistTimeSpent" name="masterlistTimeSpent" min="0" placeholder="0">
                    </div>
                </div>
                <div class="reg-grid-4">
                    <div class="reg-field">
                        <label>Revision No.</label>
                        <input type="text" id="masterlistRevisionNo" name="masterlistRevisionNo" placeholder="0">
                    </div>
                    <div class="reg-field">
                        <label>No. of Pages</label>
                        <input type="number" id="masterlistNoOfPages" name="masterlistNoOfPages" placeholder="0">
                    </div>
                    <div class="reg-field">
                        <label>Document Registered</label>
                        <div class="reg-dual">
                            <input type="date" id="masterlistRegisteredDate" name="masterlistRegisteredDate">
                            <input type="time" id="masterlistRegisteredTime" name="masterlistRegisteredTime">
                        </div>
                    </div>
                    <div class="reg-field">
                        <label>In-charge</label>
                        <input type="text" id="masterlistInCharge" name="masterlistInCharge" placeholder="Name">
                    </div>
                </div>
                <div class="reg-grid-2">
                    <div class="reg-field">
                        <label>Source Unit / Originator</label>
                        <select id="masterlistSourceUnit" name="masterlistSourceUnit">
                            <option value="" disabled selected>Select</option>
                        </select>
                    </div>
                    <div class="reg-field">
                        <label>Brief Purpose</label>
                        <input type="text" id="briefPurpose" name="briefPurpose" placeholder="Type here...">
                    </div>
                </div>
                <div class="reg-grid-2">
                    <div class="reg-field">
                        <label>Related Documents</label>
                        <input type="text" id="relatedDocuments" name="relatedDocuments" placeholder="Documents...">
                    </div>
                    <div class="reg-field">
                        <label>Upload Scanned Copy</label>
                        <label class="reg-upload">
                            <input type="file" id="uploadScannedCopy" name="uploadScannedCopy">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>Choose file or drag and drop</span>
                        </label>
                    </div>
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
                        <input type="text" id="approvalNo" name="approvalNo" placeholder="2025-042">
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
                    <div class="reg-grid-2">
                        <div class="reg-field">
                            <label>Retrieval Form Date</label>
                            <div class="reg-dual">
                                <input type="date" id="retrievalFormDate" name="retrievalFormDate">
                                <input type="time" id="retrievalFormTime" name="retrievalFormTime">
                            </div>
                        </div>
                        <div class="reg-field">
                            <label>Retrieval Date & Time</label>
                            <div class="reg-dual">
                                <input type="date" id="retrievalDate" name="retrievalDate">
                                <input type="time" id="retrievalTime" name="retrievalTime">
                            </div>
                        </div>
                    </div>
                    <div class="reg-grid-2">
                        <div class="reg-field">
                            <label>Time Spent (min)</label>
                            <input type="number" id="retrievalTimeSpent" name="retrievalTimeSpent" min="0" placeholder="0">
                        </div>
                        <div class="reg-field">
                            <label>Remarks</label>
                            <input type="text" id="retrievalRemarks" name="retrievalRemarks" placeholder="Type here...">
                        </div>
                    </div>
                    <div class="reg-field">
                        <label>Upload Scanned D&R</label>
                        <label class="reg-upload">
                            <input type="file" id="scannedRet" name="scannedRet">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span id="retFileName">Choose file or drag and drop</span>
                        </label>
                    </div>
                </div>
                <div class="reg-split-right">
                    <div class="reg-field">
                        <label>Select office(s) for retrieval</label>
                        <div class="reg-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="retrievalSearch" placeholder="Search office..." autocomplete="off"
                                oninput="handleSearch(this, 'retrievalResults', 'retrievalBody', 'totalRetrievalCopies')">
                            <div id="retrievalResults" class="reg-search-dropdown" style="display:none;"></div>
                        </div>
                    </div>
                    <table class="reg-dist-table">
                        <thead>
                            <tr>
                                <th>Receiving Office(s)</th>
                                <th>Copies</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="retrievalBody"></tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td id="totalRetrievalCopies">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
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
                    <div class="reg-grid-2">
                        <div class="reg-field">
                            <label>Distribution Form Date</label>
                            <div class="reg-dual">
                                <input type="date" id="distributionFormDate" name="distributionFormDate">
                                <input type="time" id="distributionFormTime" name="distributionFormTime">
                            </div>
                        </div>
                        <div class="reg-field">
                            <label>Distribution Date & Time</label>
                            <div class="reg-dual">
                                <input type="date" id="distributionDate" name="distributionDate">
                                <input type="time" id="distributionTime" name="distributionTime">
                            </div>
                        </div>
                    </div>
                    <div class="reg-grid-2">
                        <div class="reg-field">
                            <label>Time Spent (min)</label>
                            <input type="number" id="distributionTimeSpent" name="distributionTimeSpent" min="0" placeholder="0">
                        </div>
                        <div class="reg-field">
                            <label>Remarks</label>
                            <input type="text" id="distributionRemarks" name="distributionRemarks" placeholder="Type here...">
                        </div>
                    </div>
                    <div class="reg-field">
                        <label>Upload Scanned D&R</label>
                        <label class="reg-upload">
                            <input type="file" id="scanneddist" name="scanneddist">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span id="distFileName">Choose file or drag and drop</span>
                        </label>
                    </div>
                </div>
                <div class="reg-split-right">
                    <div class="reg-field">
                        <label>Select office(s) for distribution</label>
                        <div class="reg-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="distSearch" placeholder="Search office..." autocomplete="off"
                                oninput="handleSearch(this, 'distResults', 'distBody', 'totalDistCopies')">
                            <div id="distResults" class="reg-search-dropdown" style="display:none;"></div>
                        </div>
                    </div>
                    <table class="reg-dist-table">
                        <thead>
                            <tr>
                                <th>Receiving Office(s)</th>
                                <th>Copies</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="distBody"></tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td id="totalDistCopies">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </section>

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
                <button type="button" class="reg-btn reg-btn-save" onclick="confirmSave()">
                    <i class="fa-solid fa-floppy-disk"></i> Save Document
                </button>
            </div>
        </section>

    </form>
</main>

<div class="reg-modal-overlay" id="confirmModal" style="display: none;">
    <div class="reg-modal">
        <div class="reg-modal-header">
            <i class="fa-solid fa-circle-question"></i>
            <h3>Confirm Save</h3>
        </div>
        <div class="reg-modal-body">
            <p>Are you sure you want to save this document? Please review all fields before confirming.</p>
        </div>
        <div class="reg-modal-footer">
            <button type="button" class="reg-btn reg-btn-cancel" onclick="closeConfirmModal()">
                <i class="fa-solid fa-xmark"></i> Cancel
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