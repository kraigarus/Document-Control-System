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

    // POST /register
    public function store(Request $request)
    {
         $mode = $request->input('registration_mode', 'new');

        if ($mode === 'revised') {
            $docNo = $request->input('masterlistDocNo');
            $docTypeId = $request->input('doc_type_id');

            if (!$docNo) {
                return back()->withInput()
                    ->with('error', 'Document No. is required for revised registration.');
            }

            // Check if document exists under the SAME document type
            $existing = MasterlistRegistration::where('doc_no', $docNo)
                ->where('doc_type_id', $docTypeId)
                ->orderByDesc('revise_no')
                ->first();

            if (!$existing) {
                // Check if it exists under a different type
                $existingAny = MasterlistRegistration::where('doc_no', $docNo)->first();

                if (!$existingAny) {
                    return back()->withInput()
                        ->with('error', 'Document "' . $docNo . '" is not registered. You must register it as a New Document first before revising it.');
                }

                $type = \App\Models\DocType::find($existingAny->doc_type_id);
                return back()->withInput()
                    ->with('error', 'Document "' . $docNo . '" is registered under "' . ($type->doc_type_name ?? 'Unknown') . '", not the selected Document Type.');
            }

            // Validate revision number
            $nextRev = (int) $existing->revise_no + 1;
            if ((int) $request->input('masterlistRevisionNo') !== $nextRev) {
                return back()->withInput()
                    ->with('error', 'Revision number must be ' . $nextRev . '.');
            }
        }

        // New document validation
        if ($mode === 'new') {
            $request->validate([
                'doc_type_id'          => 'required|integer',
                'approval_status'      => 'required|in:applicable,not_applicable',
                'masterlistRevisionNo' => 'nullable|integer|min:0|max:0',
            ], [
                'masterlistRevisionNo.max' => 'A newly registered document must start at Revision 0.',
            ]);
        }

        DB::beginTransaction();

        $uploadedFiles = []; // Track all uploaded files

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
                    $uploadedFiles[] = $drfFile;
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
                    $uploadedFiles[] = $dcnFile;
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

                if ($request->has('documentTitle')) {
                    foreach ($request->documentTitle as $i => $title) {
                        if (empty($title)) continue;

                        $scannedCopy = null;
                        if ($request->hasFile('scannedCopy') && isset($request->file('scannedCopy')[$i])) {
                            $scannedCopy = $request->file('scannedCopy')[$i]->store('scans/revisions', 'public');
                            $uploadedFiles[] = $scannedCopy;
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
                    $uploadedFiles[] = $masterlistFile;
                }

                $masterlistTimeSpent = null;
                if ($request->filled('masterlistTimeSpent') && is_numeric($request->masterlistTimeSpent) && $request->masterlistTimeSpent >= 0) {
                    $totalMin = intval($request->masterlistTimeSpent);
                    $hours = intdiv($totalMin, 60);
                    $minutes = $totalMin % 60;
                    if ($hours > 838) {
                        $masterlistTimeSpent = '838:59:59';
                    } else {
                        $masterlistTimeSpent = sprintf('%02d:%02d:00', $hours, $minutes);
                    }
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
                    'office_id'             => null,
                    'originator_name'       => $request->filled('masterlistSourceUnit') ? trim($request->masterlistSourceUnit) : null,
                    'deadline'              => $request->deadlineOfSubmission,
                    'in_charge'             => $request->masterlistInCharge,
                    'brief_purpose'         => $request->briefPurpose,
                    'scanned_masterlist'    => $masterlistFile,
                    'created_by'            => auth()->id(),
                ]);
            }

            // ── Syllabi ──
            $subType = \App\Models\DocType::find($request->sub_type_id);
            $isSyllabi = $subType && strtolower($subType->doc_type_name) === 'syllabi';

            if ($isSyllabi && $request->has('syllabiCourseName')) {
                foreach ($request->syllabiCourseName as $i => $courseName) {
                    if (empty($courseName)) continue;

                    $scannedDrf = null;
                    if ($request->hasFile('syllabiScannedDrf') && isset($request->file('syllabiScannedDrf')[$i])) {
                        $scannedDrf = $request->file('syllabiScannedDrf')[$i]->store('scans/syllabi', 'public');
                        $uploadedFiles[] = $scannedDrf;
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
                    $uploadedFiles[] = $retrievalFile;
                }

                $retrievalTimeSpent = null;
                if ($request->filled('retrievalTimeSpent') && is_numeric($request->retrievalTimeSpent) && $request->retrievalTimeSpent >= 0) {
                    $totalMin = intval($request->retrievalTimeSpent);
                    $hours = intdiv($totalMin, 60);
                    $minutes = $totalMin % 60;
                    if ($hours > 838) {
                        $retrievalTimeSpent = '838:59:59';
                    } else {
                        $retrievalTimeSpent = sprintf('%02d:%02d:00', $hours, $minutes);
                    }
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
                    $uploadedFiles[] = $distFile;
                }

                $distTimeSpent = null;
                if ($request->filled('distributionTimeSpent') && is_numeric($request->distributionTimeSpent) && $request->distributionTimeSpent >= 0) {
                    $totalMin = intval($request->distributionTimeSpent);
                    $hours = intdiv($totalMin, 60);
                    $minutes = $totalMin % 60;
                    if ($hours > 838) {
                        $distTimeSpent = '838:59:59';
                    } else {
                        $distTimeSpent = sprintf('%02d:%02d:00', $hours, $minutes);
                    }
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

            // ── Clean up orphaned files ──
            foreach ($uploadedFiles as $file) {
                \Storage::disk('public')->delete($file);
            }

            \Log::error('Document registration failed: ' . $e->getMessage());
            return back()->withInput()
                        ->with('error', 'Failed to save document. Please try again.');
        }
    }

    public function checkDocNo(Request $request)
    {
        $docNo = $request->input('doc_no');
        $docTypeId = $request->input('doc_type_id');

        if (!$docNo) {
            return response()->json([
                'exists'  => false,
                'message' => 'No document number provided.',
            ]);
        }

        // Check under the selected type (or all types if none selected)
        $query = MasterlistRegistration::where('doc_no', $docNo);
        if ($docTypeId) {
            $query->where('doc_type_id', $docTypeId);
        }
        $registrations = $query->orderByDesc('revise_no')->get();

        if ($registrations->isNotEmpty()) {
            $latest = $registrations->first();
            $latestRev = (int) $latest->revise_no;

            return response()->json([
                'exists'            => true,
                'message'           => 'Document found. Latest revision: ' . $latestRev,
                'next_rev'          => $latestRev + 1,
                'latest_rev'        => $latestRev,
                'latest_title'      => $latest->doc_title,
                'latest_originator' => $latest->originator_name,
                'revision_count'    => $registrations->count(),
            ]);
        }

        // Not found under selected type — check if it exists under a DIFFERENT type
        if ($docTypeId) {
            $anyRegistration = MasterlistRegistration::where('doc_no', $docNo)->first();
            if ($anyRegistration) {
                $existingType = \App\Models\DocType::find($anyRegistration->doc_type_id);
                return response()->json([
                    'exists'             => false,
                    'wrong_type'         => true,
                    'existing_type_name' => $existingType ? $existingType->doc_type_name : 'Unknown',
                    'message'            => 'Document "' . $docNo . '" is registered under "'
                        . ($existingType ? $existingType->doc_type_name : 'Unknown')
                        . '", not the selected Document Type.',
                    'next_rev'           => null,
                ]);
            }
        }

        // Truly not registered anywhere
        $message = 'This document number is not registered';
        if ($docTypeId) {
            $type = \App\Models\DocType::find($docTypeId);
            $message .= ' under "' . ($type ? $type->doc_type_name : 'this document type') . '"';
        }
        $message .= '. Please register it as a New Document first.';

        return response()->json([
            'exists'   => false,
            'message'  => $message,
            'next_rev' => null,
        ]);
    }
    
    // ══════════════════════════════════════════════
    // UPDATE — List all registered documents
    // ══════════════════════════════════════════════
    public function updateList(Request $request)
    {
        // Get the request_id of the LATEST revision for each doc_no
        $latestIds = DB::table('masterlist_registration as m1')
            ->leftJoin('masterlist_registration as m2', function ($join) {
                $join->on('m1.doc_no', '=', 'm2.doc_no')
                    ->whereRaw('CAST(m1.revise_no AS UNSIGNED) < CAST(m2.revise_no AS UNSIGNED)');
            })
            ->whereNull('m2.request_id')
            ->whereNotNull('m1.doc_no')
            ->where('m1.doc_no', '!=', '')
            ->pluck('m1.request_id');

        // Documents that have NO masterlist at all
        $noMlIds = DocumentRequest::whereDoesntHave('masterlistRegistration')
            ->orWhereHas('masterlistRegistration', function ($q) {
                $q->whereNull('doc_no')->orWhere('doc_no', '');
            })
            ->pluck('request_id');

        $visibleIds = $latestIds->merge($noMlIds)->unique();

        $query = DocumentRequest::with(['docType', 'version'])
            ->whereIn('request_id', $visibleIds)
            ->orderBy('request_id', 'desc');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('documentRequestForm', function ($q2) use ($search) {
                    $q2->where('drf_no', 'like', "%{$search}%")
                    ->orWhere('doc_title', 'like', "%{$search}%");
                })
                ->orWhereHas('documentChangeNotice', function ($q2) use ($search) {
                    $q2->where('dcn_no', 'like', "%{$search}%");
                })
                ->orWhereHas('masterlistRegistration', function ($q2) use ($search) {
                    $q2->where('doc_no', 'like', "%{$search}%")
                    ->orWhere('doc_title', 'like', "%{$search}%");
                })
                ->orWhere('request_id', 'like', "%{$search}%");
            });
        }

        // Filter by doc type
        if ($request->filled('doc_type_id')) {
            $query->where('doc_type_id', $request->doc_type_id);
        }

        $documents = $query->paginate(10)->withQueryString();
        $docTypes = \App\Models\DocType::whereNull('parent_id')->get();

        return view('pages.dcs.create-update.update', compact('documents', 'docTypes'));
    }

    // ══════════════════════════════════════════════
    // EDIT — Show edit form for a specific document
    // ══════════════════════════════════════════════
    public function edit($id)
    {
        $docRequest = DocumentRequest::findOrFail($id);

        // ── Guard: only the latest revision can be edited ──
        $ml = MasterlistRegistration::where('request_id', $id)->first();
        if ($ml && $ml->doc_no) {
            $latestRev = MasterlistRegistration::where('doc_no', $ml->doc_no)->max('revise_no');
            if ((int) $ml->revise_no < (int) $latestRev) {
                return redirect()->route('register.update')
                    ->with('error', "Only the latest revision (Rev {$latestRev}) can be edited. This document is Rev {$ml->revise_no}.");
            }
        }
        // ── End guard ──

        $drf = DocumentRequestForm::where('request_id', $id)->first();
        $dcn = DocumentChangeNotice::where('request_id', $id)->first();
        $revisions = $dcn ? DocRevision::where('dcn_id', $dcn->dcn_id)->get() : collect();
        $masterlist = $ml; // reuse from guard above
        $retrieval = DocumentRetrieval::where('request_id', $id)->first();
        $retrievalOffices = $retrieval ? RetrievalOffice::where('retrieval_id', $retrieval->retrieval_id)->get() : collect();
        $distribution = DocumentDistribution::where('request_id', $id)->first();
        $distributionOffices = $distribution ? DistributionOffice::where('distribution_id', $distribution->distribution_id)->get() : collect();
        $approval = ApprovalRecord::where('request_id', $id)->first();
        $syllabi = Syllabi::where('request_id', $id)->get();

        // Load dropdown data for the edit form
        $offices = \App\Models\Office::orderBy('office_name')->get();
        $docTypes = \App\Models\DocType::orderBy('doc_type_name')->get();
        $versionTypes = \App\Models\VersionType::all();
        $approvalBodies = \App\Models\ApprovalBody::all();

        return view('pages.dcs.create-update.edit', compact(
            'docRequest', 'drf', 'dcn', 'revisions', 'masterlist',
            'retrieval', 'retrievalOffices', 'distribution',
            'distributionOffices', 'approval', 'syllabi',
            'offices', 'docTypes', 'versionTypes', 'approvalBodies'
        ));
    }

    // ══════════════════════════════════════════════
    // UPDATE — Save changes
    // ══════════════════════════════════════════════
    public function updateDoc(Request $request, $id)
    {
        DB::beginTransaction();
        $uploadedFiles = [];

        try {
            $docRequest = DocumentRequest::findOrFail($id);

            // ── 1. Update master request ──
            $docRequest->update([
                'version_id'       => $request->version_id,
                'doc_type_id'      => $request->doc_type_id,
                'sub_type_id'      => $request->sub_type_id,
                'approval_status'  => $request->approval_status,
            ]);

            $requestId = $docRequest->request_id;
            $docTypeId = $request->doc_type_id;
            $versionId = $request->version_id;

            // ── 2. DRF ──
            if ($request->filled('drfNo')) {
                $drf = DocumentRequestForm::where('request_id', $requestId)->first();
                $drfFile = $drf ? $drf->scanned_drf : null;

                if ($request->hasFile('drfFile')) {
                    if ($drfFile) {
                        \Storage::disk('public')->delete($drfFile);
                    }
                    $drfFile = $request->file('drfFile')->store('scans/drf', 'public');
                    $uploadedFiles[] = $drfFile;
                }

                $drfData = [
                    'checklist_id'     => 1,
                    'version_id'       => $versionId,
                    'doc_type_id'      => $docTypeId,
                    'drf_no'           => $request->drfNo,
                    'drf_date'         => $request->drfDate,
                    'drf_receipt_date' => $request->drfReceiptDate,
                    'drf_receipt_time' => $request->drfTime,
                    'office_id'        => $request->drfSourceUnit,
                    'doc_title'        => $request->drfTitle,
                    'scanned_drf'      => $drfFile,
                ];

                if ($drf) {
                    $drf->update($drfData);
                } else {
                    DocumentRequestForm::create(array_merge($drfData, [
                        'request_id' => $requestId,
                        'created_by' => auth()->id(),
                    ]));
                }
            }

            // ── 3. DCN ──
            if ($request->filled('dcnNumber')) {
                $dcn = DocumentChangeNotice::where('request_id', $requestId)->first();
                $dcnFile = $dcn ? $dcn->scanned_dcn : null;

                if ($request->hasFile('dcnFile')) {
                    if ($dcnFile) {
                        \Storage::disk('public')->delete($dcnFile);
                    }
                    $dcnFile = $request->file('dcnFile')->store('scans/dcn', 'public');
                    $uploadedFiles[] = $dcnFile;
                }

                $dcnData = [
                    'checklist_id'     => 2,
                    'version_id'       => $versionId,
                    'doc_type_id'      => $docTypeId,
                    'dcn_no'           => $request->dcnNumber,
                    'dcn_date'         => $request->noticeDate,
                    'dcn_receipt_date' => $request->receiptDate,
                    'dcn_receipt_time' => $request->receiptTime,
                    'office_id'        => $request->dcnSourceUnit,
                    'scanned_dcn'      => $dcnFile,
                ];

                if ($dcn) {
                    $dcn->update($dcnData);
                } else {
                    $dcn = DocumentChangeNotice::create(array_merge($dcnData, [
                        'request_id' => $requestId,
                        'created_by' => auth()->id(),
                    ]));
                }

                // Revisions — delete old, insert new
                DocRevision::where('dcn_id', $dcn->dcn_id)->delete();
                if ($request->has('documentTitle')) {
                    foreach ($request->documentTitle as $i => $title) {
                        if (empty($title)) continue;

                        $scannedCopy = null;
                        if ($request->hasFile('scannedCopy') && isset($request->file('scannedCopy')[$i])) {
                            $scannedCopy = $request->file('scannedCopy')[$i]->store('scans/revisions', 'public');
                            $uploadedFiles[] = $scannedCopy;
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

            // ── 4. Masterlist ──
            if ($request->filled('masterlistDocNo')) {
                $masterlist = MasterlistRegistration::where('request_id', $requestId)->first();
                $masterlistFile = $masterlist ? $masterlist->scanned_masterlist : null;

                if ($request->hasFile('uploadScannedCopy')) {
                    if ($masterlistFile) {
                        \Storage::disk('public')->delete($masterlistFile);
                    }
                    $masterlistFile = $request->file('uploadScannedCopy')->store('scans/masterlist', 'public');
                    $uploadedFiles[] = $masterlistFile;
                }

                $masterlistTimeSpent = null;
                if ($request->filled('masterlistTimeSpent') && is_numeric($request->masterlistTimeSpent) && $request->masterlistTimeSpent >= 0) {
                    $totalMin = intval($request->masterlistTimeSpent);
                    $hours = intdiv($totalMin, 60);
                    $minutes = $totalMin % 60;
                    $masterlistTimeSpent = ($hours > 838) ? '838:59:59' : sprintf('%02d:%02d:00', $hours, $minutes);
                }

                $masterlistData = [
                    'checklist_id'          => 3,
                    'version_id'            => $versionId,
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
                    'office_id'             => null,
                    'originator_name'       => $request->filled('masterlistSourceUnit') ? trim($request->masterlistSourceUnit) : null,
                    'deadline'              => $request->deadlineOfSubmission,
                    'in_charge'             => $request->masterlistInCharge,
                    'brief_purpose'         => $request->briefPurpose,
                    'scanned_masterlist'    => $masterlistFile,
                ];

                if ($masterlist) {
                    $masterlist->update($masterlistData);
                } else {
                    MasterlistRegistration::create(array_merge($masterlistData, [
                        'request_id' => $requestId,
                        'created_by' => auth()->id(),
                    ]));
                }
            }

            // ── Syllabi ──
            $subType = \App\Models\DocType::find($request->sub_type_id);
            $isSyllabi = $subType && strtolower($subType->doc_type_name) === 'syllabi';

            if ($isSyllabi) {
                Syllabi::where('request_id', $requestId)->delete();

                if ($request->has('syllabiCourseName')) {
                    foreach ($request->syllabiCourseName as $i => $courseName) {
                        if (empty($courseName)) continue;

                        $scannedDrf = null;
                        if ($request->hasFile('syllabiScannedDrf') && isset($request->file('syllabiScannedDrf')[$i])) {
                            $scannedDrf = $request->file('syllabiScannedDrf')[$i]->store('scans/syllabi', 'public');
                            $uploadedFiles[] = $scannedDrf;
                        }

                        Syllabi::create([
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
            }

            // ── 5. Retrieval ──
            if ($request->filled('retrievalDate')) {
                $retrieval = DocumentRetrieval::where('request_id', $requestId)->first();
                $retrievalFile = $retrieval ? $retrieval->scanned_retrieval : null;

                if ($request->hasFile('scannedRet')) {
                    if ($retrievalFile) {
                        \Storage::disk('public')->delete($retrievalFile);
                    }
                    $retrievalFile = $request->file('scannedRet')->store('scans/retrieval', 'public');
                    $uploadedFiles[] = $retrievalFile;
                }

                $retrievalTimeSpent = null;
                if ($request->filled('retrievalTimeSpent') && is_numeric($request->retrievalTimeSpent) && $request->retrievalTimeSpent >= 0) {
                    $totalMin = intval($request->retrievalTimeSpent);
                    $hours = intdiv($totalMin, 60);
                    $minutes = $totalMin % 60;
                    $retrievalTimeSpent = ($hours > 838) ? '838:59:59' : sprintf('%02d:%02d:00', $hours, $minutes);
                }

                $retrievalData = [
                    'checklist_id'                 => 4,
                    'version_id'                   => $versionId,
                    'doc_type_id'                  => $docTypeId,
                    'doc_retrieval_date_actual'    => $request->retrievalDate,
                    'doc_retrieval_time_actual'    => $request->retrievalTime,
                    'doc_retrieval_date_file'      => $request->retrievalFormDate,
                    'doc_retrieval_time_file'      => $request->retrievalFormTime,
                    'time_spent'                   => $retrievalTimeSpent,
                    'remarks'                      => $request->retrievalRemarks,
                    'scanned_retrieval'            => $retrievalFile,
                ];

                if ($retrieval) {
                    $retrieval->update($retrievalData);
                } else {
                    $retrieval = DocumentRetrieval::create(array_merge($retrievalData, [
                        'request_id' => $requestId,
                        'created_by' => auth()->id(),
                    ]));
                }

                // Offices — delete old, insert new
                RetrievalOffice::where('retrieval_id', $retrieval->retrieval_id)->delete();
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

            // ── 6. Distribution ──
            if ($request->filled('distributionDate')) {
                $distribution = DocumentDistribution::where('request_id', $requestId)->first();
                $distFile = $distribution ? $distribution->scanned_distribution : null;

                if ($request->hasFile('scanneddist')) {
                    if ($distFile) {
                        \Storage::disk('public')->delete($distFile);
                    }
                    $distFile = $request->file('scanneddist')->store('scans/distribution', 'public');
                    $uploadedFiles[] = $distFile;
                }

                $distTimeSpent = null;
                if ($request->filled('distributionTimeSpent') && is_numeric($request->distributionTimeSpent) && $request->distributionTimeSpent >= 0) {
                    $totalMin = intval($request->distributionTimeSpent);
                    $hours = intdiv($totalMin, 60);
                    $minutes = $totalMin % 60;
                    $distTimeSpent = ($hours > 838) ? '838:59:59' : sprintf('%02d:%02d:00', $hours, $minutes);
                }

                $distData = [
                    'checklist_id'                     => 5,
                    'version_id'                       => $versionId,
                    'doc_type_id'                      => $docTypeId,
                    'doc_distribution_date_actual'     => $request->distributionDate,
                    'doc_distribution_time_actual'     => $request->distributionTime,
                    'doc_distribution_date_file'       => $request->distributionFormDate,
                    'doc_distribution_time_file'       => $request->distributionFormTime,
                    'time_spent'                       => $distTimeSpent,
                    'remarks'                          => $request->distributionRemarks,
                    'scanned_distribution'             => $distFile,
                ];

                if ($distribution) {
                    $distribution->update($distData);
                } else {
                    $distribution = DocumentDistribution::create(array_merge($distData, [
                        'request_id' => $requestId,
                        'created_by' => auth()->id(),
                    ]));
                }

                // Offices — delete old, insert new
                DistributionOffice::where('distribution_id', $distribution->distribution_id)->delete();
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
                $approval = ApprovalRecord::where('request_id', $requestId)->first();
                $approvalData = [
                    'checklist_id'     => null,
                    'version_id'       => $versionId,
                    'doc_type_id'      => $docTypeId,
                    'approval_body_id' => $request->approvalBody,
                    'approval_date'    => $request->approvalDate,
                    'approval_no'      => $request->approvalNo,
                ];

                if ($approval) {
                    $approval->update($approvalData);
                } else {
                    ApprovalRecord::create(array_merge($approvalData, [
                        'request_id' => $requestId,
                    ]));
                }
            }

            DB::commit();

            return redirect()->route('register.edit', $id)
                            ->with('success', 'Document updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            foreach ($uploadedFiles as $file) {
                \Storage::disk('public')->delete($file);
            }
            \Log::error('Document update failed: ' . $e->getMessage());
            return back()->withInput()
                        ->with('error', 'Failed to update document. Please try again.');
        }
    }

    // ══════════════════════════════════════════════
    // DELETE
    // ══════════════════════════════════════════════
    public function destroy($id)
    {
        $docRequest = DocumentRequest::findOrFail($id);

        // ── Guard: only the latest revision can be deleted ──
        $ml = MasterlistRegistration::where('request_id', $id)->first();
        if ($ml && $ml->doc_no) {
            $latestRev = MasterlistRegistration::where('doc_no', $ml->doc_no)->max('revise_no');
            if ((int) $ml->revise_no < (int) $latestRev) {
                return redirect()->route('register.update')
                    ->with('error', "Only the latest revision (Rev {$latestRev}) can be deleted. This document is Rev {$ml->revise_no}.");
            }
        }
        // ── End guard ──

        DB::beginTransaction();

        try {
            $requestId = $docRequest->request_id;

            // Delete related records
            $drf = DocumentRequestForm::where('request_id', $requestId)->first();
            if ($drf) {
                if ($drf->scanned_drf) \Storage::disk('public')->delete($drf->scanned_drf);
                $drf->delete();
            }

            $dcn = DocumentChangeNotice::where('request_id', $requestId)->first();
            if ($dcn) {
                if ($dcn->scanned_dcn) \Storage::disk('public')->delete($dcn->scanned_dcn);
                DocRevision::where('dcn_id', $dcn->dcn_id)->delete();
                $dcn->delete();
            }

            $masterlist = MasterlistRegistration::where('request_id', $requestId)->first();
            if ($masterlist) {
                if ($masterlist->scanned_masterlist) \Storage::disk('public')->delete($masterlist->scanned_masterlist);
                $masterlist->delete();
            }

            Syllabi::where('request_id', $requestId)->delete();

            $retrieval = DocumentRetrieval::where('request_id', $requestId)->first();
            if ($retrieval) {
                if ($retrieval->scanned_retrieval) \Storage::disk('public')->delete($retrieval->scanned_retrieval);
                RetrievalOffice::where('retrieval_id', $retrieval->retrieval_id)->delete();
                $retrieval->delete();
            }

            $distribution = DocumentDistribution::where('request_id', $requestId)->first();
            if ($distribution) {
                if ($distribution->scanned_distribution) \Storage::disk('public')->delete($distribution->scanned_distribution);
                DistributionOffice::where('distribution_id', $distribution->distribution_id)->delete();
                $distribution->delete();
            }

            ApprovalRecord::where('request_id', $requestId)->delete();

            $docRequest->delete();

            DB::commit();

            return redirect()->route('register.update')
                            ->with('success', 'Document deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Document deletion failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete document. Please try again.');
        }
    }

    // ══════════════════════════════════════════════
    // HISTORY — Read-only timeline of all revisions
    // ══════════════════════════════════════════════
    public function history($docNo)
    {
        $revisions = DocumentRequest::whereHas('masterlistRegistration', function ($q) use ($docNo) {
            $q->where('doc_no', $docNo);
        })
        ->with(['docType', 'masterlistRegistration', 'documentRequestForm', 'documentChangeNotice'])
        ->get()
        ->sortByDesc(function ($doc) {
            return $doc->masterlistRegistration->revise_no ?? 0;
        })
        ->values();

        if ($revisions->isEmpty()) {
            abort(404, 'Document not found.');
        }

        $docTitle = $revisions->first()->masterlistRegistration->doc_title ?? $docNo;

        return view('pages.dcs.create-update.history', compact('revisions', 'docNo', 'docTitle'));
    }

    // ══════════════════════════════════════════════
    // GENERATE REPORTS
    // ══════════════════════════════════════════════

    public function reportIndex()
    {
        return view('pages.dcs.generate-report.report');
    }

    public function masterlistReport()
    {
        $docTypes = \App\Models\DocType::whereNull('parent_id')->get();
        $offices = \App\Models\Office::where('status', 'active')->orderBy('office_name')->get();
        return view('pages.dcs.generate-report.masterlist', compact('docTypes', 'offices'));
    }

    public function masterlistData()
    {
        $query = \App\Models\DocumentRequest::with([
            'version', 'docType', 'subType',
            'masterlistRegistration',
            'documentRequestForm',
        ])->whereHas('masterlistRegistration');

        if (request('category')) {
            $cat = request('category');
            $catMap = [
                'internal' => [1, 6, 7, 8, 9, 10],
                'internal_forms' => [2, 11, 12, 13, 14],
                'external' => [3],
                'forms' => [4],
                'logbooks' => [5],
            ];
            if (isset($catMap[$cat])) {
                $query->whereIn('doc_type_id', $catMap[$cat]);
            }
        }

        if (request('title')) {
            $query->whereHas('masterlistRegistration', function ($q) {
                $q->where('doc_title', 'like', '%' . request('title') . '%');
            });
        }

        if (request('doc_no')) {
            $query->whereHas('masterlistRegistration', function ($q) {
                $q->where('doc_no', 'like', '%' . request('doc_no') . '%');
            });
        }

        if (request('rev_no')) {
            $query->whereHas('masterlistRegistration', function ($q) {
                $q->where('revise_no', 'like', '%' . request('rev_no') . '%');
            });
        }

        if (request('originator')) {
            $query->whereHas('masterlistRegistration', function ($q) {
                $q->where('originator_name', 'like', '%' . request('originator') . '%');
            });
        }

        if (request('effectivity_from')) {
            $query->whereHas('masterlistRegistration', function ($q) {
                $q->where('effectivity_date', '>=', request('effectivity_from'));
            });
        }

        if (request('effectivity_to')) {
            $query->whereHas('masterlistRegistration', function ($q) {
                $q->where('effectivity_date', '<=', request('effectivity_to'));
            });
        }

        $query->orderBy('request_id', 'asc');
        $documents = $query->get();

        $rows = $documents->map(function ($doc, $index) {
            $ml = $doc->masterlistRegistration;
            $drf = $doc->documentRequestForm;

            return [
                'id' => $doc->request_id,
                'item_no' => $index + 1,
                'doc_no' => $ml->doc_no ?? 'N/A',
                'title' => $ml->doc_title ?? $drf->doc_title ?? 'N/A',
                'rev_no' => $ml->revise_no ?? '0',
                'originator' => $ml->originator_name ?? 'N/A',
                'effectivity' => $ml->effectivity_date ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : 'N/A',
                'status' => 'Active',
                'category' => $doc->docType->doc_type_name ?? 'N/A',
            ];
        });

        if (request('item_from') || request('item_to')) {
            $from = (int) (request('item_from') ?: 1);
            $to = (int) (request('item_to') ?: 99999);
            $rows = $rows->filter(fn($r) => $r['item_no'] >= $from && $r['item_no'] <= $to);
        }

        return response()->json($rows->values());
    }

    public function masterlistPrint()
    {
        $mode = request('mode', 'complete');
        $ids = request('ids') ? explode(',', request('ids')) : [];
        $generatedBy = request('generated_by', auth()->user()->name ?? 'System');

        $query = \App\Models\DocumentRequest::with([
            'version', 'docType', 'subType',
            'masterlistRegistration',
            'documentRequestForm',
        ])->whereHas('masterlistRegistration');

        if ($mode === 'selected' && !empty($ids)) {
            $query->whereIn('request_id', $ids);
        }

        if ($mode === 'filtered') {
            if (request('category')) {
                $catMap = [
                    'internal' => [1, 6, 7, 8, 9, 10],
                    'internal_forms' => [2, 11, 12, 13, 14],
                    'external' => [3],
                    'forms' => [4],
                    'logbooks' => [5],
                ];
                $cat = request('category');
                if (isset($catMap[$cat])) {
                    $query->whereIn('doc_type_id', $catMap[$cat]);
                }
            }
            if (request('title')) {
                $query->whereHas('masterlistRegistration', fn($q) => $q->where('doc_title', 'like', '%' . request('title') . '%'));
            }
        }

        $query->orderBy('request_id', 'asc');
        $documents = $query->get();

        $rows = $documents->map(function ($doc, $index) {
            $ml = $doc->masterlistRegistration;
            $drf = $doc->documentRequestForm;
            return [
                'item_no' => $index + 1,
                'doc_no' => $ml->doc_no ?? 'N/A',
                'title' => $ml->doc_title ?? $drf->doc_title ?? 'N/A',
                'rev_no' => $ml->revise_no ?? '0',
                'originator' => $ml->originator_name ?? 'N/A',
                'effectivity' => $ml->effectivity_date ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : 'N/A',
                'status' => 'Active',
            ];
        });

        $title = match($mode) {
            'selected' => 'Masterlist — Selected Documents',
            'filtered' => 'Masterlist — Filtered Results',
            default => 'Complete Masterlist',
        };

        return view('pages.dcs.generate-report.masterlist-print', compact('rows', 'title', 'generatedBy'));
    }

    public function monitoringReport()
    {
        return view('pages.dcs.generate-report.monitoring');
    }

    public function opcrReport()
    {
        return view('pages.dcs.generate-report.opcr');
    }

    public function otherReport()
    {
        return view('pages.dcs.generate-report.other');
    }

    public function stampingIndex()
    {
        $docTypes = \App\Models\DocType::whereNull('parent_id')->get();
        return view('pages.dcs.stamping.index', compact('docTypes'));
    }

    //============================================================
    //DATABASE
    //============================================================
    public function databaseIndex()
    {
        $docTypes = \App\Models\DocType::whereNull('parent_id')->get();
        $offices = \App\Models\Office::where('status', 'active')->orderBy('office_name')->get();
        return view('pages.dcs.database.index', compact('docTypes', 'offices'));
    }

    public function databaseData()
    {
        try {
            $perPage = (int) request('per_page', 20);

            $query = \App\Models\DocumentRequest::with([
                'docType',
                'documentRequestForm',
                'masterlistRegistration',
                'approvalRecords',
            ]);

            // Doc type filter
            if (request('doc_type_id') && request('doc_type_id') !== 'all') {
                $query->where('doc_type_id', request('doc_type_id'));
            }

            // Search
            if (request('search')) {
                $search = request('search');
                $query->where(function ($q) use ($search) {
                    $q->whereHas('documentRequestForm', function ($q2) use ($search) {
                        $q2->where('doc_title', 'like', "%{$search}%")
                            ->orWhere('drf_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('masterlistRegistration', function ($q2) use ($search) {
                        $q2->where('doc_no', 'like', "%{$search}%")
                            ->orWhere('doc_title', 'like', "%{$search}%");
                    });
                });
            }

            // Advanced filters
            if (request('originator')) {
                $query->whereHas('masterlistRegistration', function ($q) {
                    $q->where('originator_name', 'like', '%' . request('originator') . '%');
                });
            }
            if (request('source_unit')) {
                $query->whereHas('documentRequestForm', function ($q) {
                    $q->where('office_id', request('source_unit'));
                });
            }
            if (request('status')) {
                $query->where('approval_status', request('status'));
            }
            if (request('date_from')) {
                $query->whereHas('masterlistRegistration', function ($q) {
                    $q->where('effectivity_date', '>=', request('date_from'));
                });
            }
            if (request('date_to')) {
                $query->whereHas('masterlistRegistration', function ($q) {
                    $q->where('effectivity_date', '<=', request('date_to'));
                });
            }
            if (request('rev_no')) {
                $query->whereHas('masterlistRegistration', function ($q) {
                    $q->where('revise_no', request('rev_no'));
                });
            }

            // Get ALL matching documents
            $allDocs = $query->get();

            // Build all rows
            $allRows = $allDocs->map(function ($doc) {
                $drf  = $doc->documentRequestForm;
                $ml   = $doc->masterlistRegistration;
                $appr = $doc->approvalRecords ? $doc->approvalRecords->first() : null;

                $dcn = null;
                $dcnPurpose = null;
                try {
                    $dcn = \App\Models\DocumentChangeNotice::where('request_id', $doc->request_id)->first();
                    if ($dcn) {
                        $firstRev = \App\Models\DocRevision::where('dcn_id', $dcn->dcn_id)->first();
                        if ($firstRev) {
                            $dcnPurpose = $firstRev->brief_purpose;
                        }
                    }
                } catch (\Exception $e) { }

                $ret = null;
                $retOffices = null;
                try {
                    $ret = \App\Models\DocumentRetrieval::where('request_id', $doc->request_id)->first();
                    if ($ret) {
                        $retOffices = \App\Models\RetrievalOffice::where('retrieval_id', $ret->retrieval_id)
                            ->join('offices', 'retrieval_offices.office_id', '=', 'offices.office_id')
                            ->pluck('offices.office_name')
                            ->implode(', ') ?: null;
                    }
                } catch (\Exception $e) { }

                $dist = null;
                $distOffices = null;
                try {
                    $dist = \App\Models\DocumentDistribution::where('request_id', $doc->request_id)->first();
                    if ($dist) {
                        $distOffices = \App\Models\DistributionOffice::where('distribution_id', $dist->distribution_id)
                            ->join('offices', 'distribution_offices.office_id', '=', 'offices.office_id')
                            ->pluck('offices.office_name')
                            ->implode(', ') ?: null;
                    }
                } catch (\Exception $e) { }

                $sourceUnitName = null;
                if ($drf && $drf->office_id) {
                    try {
                        $office = \App\Models\Office::find($drf->office_id);
                        $sourceUnitName = $office ? $office->office_name : null;
                    } catch (\Exception $e) { }
                }

                $deadlineDiff = null;
                if ($ml && $ml->deadline && $ml->effectivity_date) {
                    $deadlineDiff = \Carbon\Carbon::parse($ml->effectivity_date)->diffInDays(\Carbon\Carbon::parse($ml->deadline));
                }

                return [
                    'request_id'       => $doc->request_id,
                    'doc_no'           => $ml ? $ml->doc_no : 'N/A',
                    'rev_no'           => $ml ? (int) $ml->revise_no : 0,
                    'title'            => ($ml && $ml->doc_title) ? $ml->doc_title : (($drf && $drf->doc_title) ? $drf->doc_title : 'N/A'),
                    'effectivity'      => ($ml && $ml->effectivity_date) ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : null,
                    'originator'       => $ml ? $ml->originator_name : null,
                    'pages'            => $ml ? $ml->no_pages : null,
                    'status'           => $doc->approval_status ?? 'Active',
                    'pdf_path'         => ($ml && $ml->scanned_masterlist) ? \Storage::disk('public')->url($ml->scanned_masterlist) : null,
                    'source_unit'      => $sourceUnitName,
                    'approval_no'      => $appr ? $appr->approval_no : null,
                    'approval_date'    => ($appr && $appr->approval_date) ? \Carbon\Carbon::parse($appr->approval_date)->format('M d, Y') : null,
                    'deadline_date'    => ($ml && $ml->deadline) ? \Carbon\Carbon::parse($ml->deadline)->format('M d, Y') : null,
                    'deadline_diff'    => $deadlineDiff !== null ? $deadlineDiff . ' days' : null,
                    'ml_receipt_date'  => ($ml && $ml->doc_receipt_date) ? \Carbon\Carbon::parse($ml->doc_receipt_date)->format('M d, Y') : null,
                    'ml_receipt_time'  => $ml && $ml->doc_receipt_time ? $this->formatTime($ml->doc_receipt_time) : null,
                    'ml_register_date' => ($ml && $ml->doc_registered_date) ? \Carbon\Carbon::parse($ml->doc_registered_date)->format('M d, Y') : null,
                    'ml_register_time' => $ml && $ml->doc_registered_time ? $this->formatTime($ml->doc_registered_time) : null,
                    'dcn_no'           => $dcn ? $dcn->dcn_no : null,
                    'dcn_date'         => ($dcn && $dcn->dcn_date) ? \Carbon\Carbon::parse($dcn->dcn_date)->format('M d, Y') : null,
                    'dcn_receipt_date' => ($dcn && $dcn->dcn_receipt_date) ? \Carbon\Carbon::parse($dcn->dcn_receipt_date)->format('M d, Y') : null,
                    'dcn_receipt_time' => $dcn && $dcn->dcn_receipt_time ? $this->formatTime($dcn->dcn_receipt_time) : null,
                    'dcn_purpose'      => $dcnPurpose,
                    'dcn_scan'         => ($dcn && $dcn->scanned_dcn) ? \Storage::disk('public')->url($dcn->scanned_dcn) : null,
                    'drf_no'           => $drf ? $drf->drf_no : null,
                    'drf_date'         => ($drf && $drf->drf_date) ? \Carbon\Carbon::parse($drf->drf_date)->format('M d, Y') : null,
                    'drf_receipt_date' => ($drf && $drf->drf_receipt_date) ? \Carbon\Carbon::parse($drf->drf_receipt_date)->format('M d, Y') : null,
                    'drf_receipt_time' => $drf && $drf->drf_receipt_time ? $this->formatTime($drf->drf_receipt_time) : null,
                    'drf_scan'         => ($drf && $drf->scanned_drf) ? \Storage::disk('public')->url($drf->scanned_drf) : null,
                    'dist_onfile_date' => ($dist && $dist->doc_distribution_date_file) ? \Carbon\Carbon::parse($dist->doc_distribution_date_file)->format('M d, Y') : null,
                    'dist_onfile_time' => $dist && $dist->doc_distribution_time_file ? $this->formatTime($dist->doc_distribution_time_file) : null,
                    'dist_actual_date' => ($dist && $dist->doc_distribution_date_actual) ? \Carbon\Carbon::parse($dist->doc_distribution_date_actual)->format('M d, Y') : null,
                    'dist_actual_time' => $dist && $dist->doc_distribution_time_actual ? $this->formatTime($dist->doc_distribution_time_actual) : null,
                    'dist_offices'     => $distOffices,
                    'dist_scan'        => ($dist && $dist->scanned_distribution) ? \Storage::disk('public')->url($dist->scanned_distribution) : null,
                    'ret_onfile'       => ($ret && $ret->doc_retrieval_date_file) ? \Carbon\Carbon::parse($ret->doc_retrieval_date_file)->format('M d, Y') : null,
                    'ret_actual'       => ($ret && $ret->doc_retrieval_date_actual) ? \Carbon\Carbon::parse($ret->doc_retrieval_date_actual)->format('M d, Y') : null,
                    'ret_offices'      => $retOffices,
                    'ret_scan'         => ($ret && $ret->scanned_retrieval) ? \Storage::disk('public')->url($ret->scanned_retrieval) : null,
                ];
            });

            // ── Group by doc_no ──
            $grouped = $allRows->groupBy('doc_no');

            $groups = collect();
            foreach ($grouped as $docNo => $rows) {
                $sorted = $rows->sortByDesc('rev_no')->values();
                $groups->push([
                    'doc_no'          => $docNo,
                    'parent'          => $sorted->first(),
                    'children'        => $sorted->slice(1)->values(),
                    'has_revisions'   => $sorted->count() > 1,
                    'revision_count'  => $sorted->count(),
                ]);
            }

            // Sort groups: most recently registered at the top
            $groups = $groups->sortByDesc(function ($g) {
                return $g['parent']['request_id'] ?? 0;
            })->values();

            // Paginate groups
            $totalGroups = $groups->count();
            $currentPage = max(1, (int) request('page', 1));
            $lastPage = max(1, (int) ceil($totalGroups / $perPage));
            $currentPage = min($currentPage, $lastPage);
            $paginatedGroups = $groups->slice(($currentPage - 1) * $perPage, $perPage)->values();

            return response()->json([
                'data'         => $paginatedGroups,
                'total'        => $totalGroups,
                'current_page' => $currentPage,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
            ]);

        } catch (\Exception $e) {
            \Log::error('Database data error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'error'        => $e->getMessage(),
                'data'         => [],
                'total'        => 0,
                'current_page' => 1,
                'last_page'    => 1,
                'per_page'     => 20,
            ], 500);
        }
    }

    public function databaseExport()
    {
        // Placeholder for Excel/CSV export
        return redirect()->route('database.index')->with('info', 'Export feature coming soon.');
    }

    private function formatTime($value)
    {
        if (!$value) return null;

        // Already a clean time string like "09:15 AM"
        if (is_string($value) && !str_contains($value, 'T') && !str_contains($value, '-')) {
            return $value;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('h:i A');
        } catch (\Exception $e) {
            return $value;
        }
    }

    
}