<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestForm;
use App\Models\DocumentChangeNotice;
use App\Models\DocRevision;
use App\Models\MasterlistRegistration;
use App\Models\DocumentRetrieval;
use App\Models\RetrievalOffice;
use App\Models\DocumentDistribution;
use App\Models\DistributionOffice;
use App\Models\ApprovalRecord;
use App\Models\Syllabi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    // GET /register
    public function index()
    {
        return view('pages.dcs.create-update.register');
    }

    // GET /register/revised
    public function revised()
    {
        return view('pages.dcs.register-revised');
    }

    // GET /register/update
    public function update()
    {
        return view('pages.dcs.create-update.update');
    }

    // POST /register
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            // ── 1. Create the master Document Request ──
            $docRequest = DocumentRequest::create([
                'version_id'       => $request->version_id,
                'doc_type_id'      => $request->doc_type_id,
                'sub_type_id'      => $request->sub_type_id,
                'approval_status'  => $request->approval_status,
                'created_by'       => auth()->id(),
            ]);

            $requestId = $docRequest->request_id;
            $docTypeId = $request->doc_type_id;
            $versionId = $request->version_id;

            // ── 2. DRF (Section 1) ──
            if ($request->filled('drfNo')) {
                $drfFile = null;
                if ($request->hasFile('drfFile')) {
                    $drfFile = $request->file('drfFile')->store('scans/drf', 'public');
                }

                DocumentRequestForm::create([
                    'checklist_id'     => 1,
                    'version_id'       => $versionId,
                    'request_id'       => $requestId,
                    'doc_type_id'      => $docTypeId,
                    'drf_no'           => $request->drfNo,
                    'drf_date'         => $request->drfDate,
                    'drf_receipt_date' => $request->drfReceiptDate,
                    'drf_receipt_time' => $request->drfTime,
                    'office_id'        => $request->drfSourceUnit,
                    'doc_title'        => $request->drfTitle,
                    'scanned_drf'      => $drfFile,
                    'created_by'       => auth()->id(),
                ]);
            }

            // ── 3. DCN (Section 2) ──
            if ($request->filled('dcnNumber')) {
                $dcnFile = null;
                if ($request->hasFile('dcnFile')) {
                    $dcnFile = $request->file('dcnFile')->store('scans/dcn', 'public');
                }

                $dcn = DocumentChangeNotice::create([
                    'checklist_id'     => 2,
                    'version_id'       => $versionId,
                    'request_id'       => $requestId,
                    'doc_type_id'      => $docTypeId,
                    'dcn_no'           => $request->dcnNumber,
                    'dcn_date'         => $request->noticeDate,
                    'dcn_receipt_date' => $request->receiptDate,
                    'dcn_receipt_time' => $request->receiptTime,
                    'office_id'        => $request->dcnSourceUnit,
                    'scanned_dcn'      => $dcnFile,
                    'created_by'       => auth()->id(),
                ]);

                // Save revision rows
                if ($request->has('documentTitle')) {
                    foreach ($request->documentTitle as $i => $title) {
                        if (empty($title)) continue;

                        $scannedCopy = null;
                        if ($request->hasFile('scannedCopy') && isset($request->file('scannedCopy')[$i])) {
                            $scannedCopy = $request->file('scannedCopy')[$i]->store('scans/revisions', 'public');
                        }

                        DocRevision::create([
                            'dcn_id'           => $dcn->dcn_id,
                            'title'            => $title,
                            'document_no'      => $request->documentNo[$i] ?? null,
                            'effectivity_date' => $request->effectiveDate[$i] ?? null,
                            'revision_no'      => $request->revisionNo[$i] ?? null,
                            'scanned_copy'     => $scannedCopy,
                            'brief_purpose'    => $request->revisionPurpose[$i] ?? null,
                        ]);
                    }
                }
            }

            // ── 4. Masterlist (Section 3) ──
            if ($request->filled('masterlistDocNo')) {
                $masterlistFile = null;
                if ($request->hasFile('uploadScannedCopy')) {
                    $masterlistFile = $request->file('uploadScannedCopy')->store('scans/masterlist', 'public');
                }

                $masterlistTimeSpent = null;
                if ($request->filled('masterlistTimeSpent')) {
                    $masterlistTimeSpent = sprintf('%02d:%02d:00', intval($request->masterlistTimeSpent / 60), $request->masterlistTimeSpent % 60);
                }

                MasterlistRegistration::create([
                    'checklist_id'          => 3,
                    'version_id'            => $versionId,
                    'request_id'            => $requestId,
                    'doc_type_id'           => $docTypeId,
                    'doc_no'                => $request->masterlistDocNo,
                    'doc_receipt_date'      => $request->masterlistReceiptDate,
                    'doc_receipt_time'      => $request->masterlistReceiptTime,
                    'doc_registered_date'   => $request->masterlistRegisteredDate,
                    'doc_registered_time'   => $request->masterlistRegisteredTime,
                    'time_spent'            => $masterlistTimeSpent,
                    'doc_title'             => $request->masterlistDocTitle,
                    'effectivity_date'      => $request->masterlistEffectivityDate,
                    'revise_no'             => $request->masterlistRevisionNo,
                    'no_pages'              => $request->masterlistNoOfPages,
                    'office_id'             => $request->masterlistSourceUnit,
                    'deadline'              => $request->deadlineOfSubmission,
                    'in_charge'             => $request->masterlistInCharge,
                    'brief_purpose'         => $request->briefPurpose,
                    'scanned_masterlist'    => $masterlistFile,
                    'created_by'            => auth()->id(),
                ]);
            }

            // ── Syllabi (only when sub-type is "Syllabi" — doc_type_id 11) ──
            if ($request->sub_type_id == 11 && $request->has('syllabiCourseName')) {
                foreach ($request->syllabiCourseName as $i => $courseName) {
                    if (empty($courseName)) continue;

                    $scannedDrf = null;
                    if ($request->hasFile('syllabiScannedDrf') && isset($request->file('syllabiScannedDrf')[$i])) {
                        $scannedDrf = $request->file('syllabiScannedDrf')[$i]->store('scans/syllabi', 'public');
                    }

                    \App\Models\Syllabi::create([
                        'request_id'            => $requestId,
                        'course_name'           => $courseName,
                        'syllabi_availability'  => $request->syllabiAvailability[$i] ?? null,
                        'no_pages'              => $request->syllabiNoPages[$i] ?? null,
                        'drf_availability'      => $request->syllabiDrfAvailability[$i] ?? null,
                        'drf_no'                => $request->syllabiDrfNo[$i] ?? null,
                        'drf_date'              => $request->syllabiDrfDate[$i] ?? null,
                        'drf_received_date'     => $request->syllabiDrfReceived[$i] ?? null,
                        'scanned_drf'           => $scannedDrf,
                    ]);
                }
            }

            // ── 5. Retrieval (Section 4) ──
            if ($request->filled('retrievalDate')) {
                $retrievalFile = null;
                if ($request->hasFile('scannedRet')) {
                    $retrievalFile = $request->file('scannedRet')->store('scans/retrieval', 'public');
                }

                $retrievalTimeSpent = null;
                if ($request->filled('retrievalTimeSpent')) {
                    $retrievalTimeSpent = sprintf('%02d:%02d:00', intval($request->retrievalTimeSpent / 60), $request->retrievalTimeSpent % 60);
                }

                $retrieval = DocumentRetrieval::create([
                    'checklist_id'                 => 4,
                    'version_id'                   => $versionId,
                    'request_id'                   => $requestId,
                    'doc_type_id'                  => $docTypeId,
                    'doc_retrieval_date_actual'    => $request->retrievalDate,
                    'doc_retrieval_time_actual'    => $request->retrievalTime,
                    'doc_retrieval_date_file'      => $request->retrievalFormDate,
                    'doc_retrieval_time_file'      => $request->retrievalFormTime,
                    'time_spent'                   => $retrievalTimeSpent,
                    'remarks'                      => $request->retrievalRemarks,
                    'scanned_retrieval'            => $retrievalFile,
                    'created_by'                   => auth()->id(),
                ]);

                if ($request->has('retrievalOffice')) {
                    foreach ($request->retrievalOffice as $i => $officeId) {
                        RetrievalOffice::create([
                            'retrieval_id' => $retrieval->retrieval_id,
                            'office_id'    => $officeId,
                            'copies'       => $request->retrievalCopies[$i] ?? 1,
                        ]);
                    }
                }
            }

            // ── 6. Distribution (Section 5) ──
            if ($request->filled('distributionDate')) {
                $distFile = null;
                if ($request->hasFile('scanneddist')) {
                    $distFile = $request->file('scanneddist')->store('scans/distribution', 'public');
                }

                $distTimeSpent = null;
                if ($request->filled('distributionTimeSpent')) {
                    $distTimeSpent = sprintf('%02d:%02d:00', intval($request->distributionTimeSpent / 60), $request->distributionTimeSpent % 60);
                }

                $distribution = DocumentDistribution::create([
                    'checklist_id'                     => 5,
                    'version_id'                       => $versionId,
                    'request_id'                       => $requestId,
                    'doc_type_id'                      => $docTypeId,
                    'doc_distribution_date_actual'     => $request->distributionDate,
                    'doc_distribution_time_actual'     => $request->distributionTime,
                    'doc_distribution_date_file'       => $request->distributionFormDate,
                    'doc_distribution_time_file'       => $request->distributionFormTime,
                    'time_spent'                       => $distTimeSpent,
                    'remarks'                          => $request->distributionRemarks,
                    'scanned_distribution'             => $distFile,
                    'created_by'                       => auth()->id(),
                ]);

                if ($request->has('distOffice')) {
                    foreach ($request->distOffice as $i => $officeId) {
                        DistributionOffice::create([
                            'distribution_id' => $distribution->distribution_id,
                            'office_id'       => $officeId,
                            'copies'          => $request->distCopies[$i] ?? 1,
                        ]);
                    }
                }
            }

            // ── 7. Approval ──
            if ($request->approval_status === 'applicable' && $request->filled('approvalBody')) {
                ApprovalRecord::create([
                    'checklist_id'     => null,
                    'version_id'       => $versionId,
                    'request_id'       => $requestId,
                    'doc_type_id'      => $docTypeId,
                    'approval_body_id' => $request->approvalBody,
                    'approval_date'    => $request->approvalDate,
                    'approval_no'      => $request->approvalNo,
                ]);
            }

            DB::commit();

            return redirect()->route('register.create')
                             ->with('success', 'Document registered successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                         ->with('error', 'Failed to register document: ' . $e->getMessage());
        }
    }
}