<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestForm;
use App\Models\DocumentChangeNotice;
use App\Models\DocRevision;
use App\Models\MasterlistRegistration;
use App\Models\MasterlistOrigin;
use App\Models\DocumentRetrieval;
use App\Models\RetrievalOffice;
use App\Models\DocumentDistribution;
use App\Models\DistributionOffice;
use App\Models\ApprovalRecord;
use App\Models\Syllabi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RegisterController extends Controller
{
    // ──────────────────────────────────────────────────────────
    // SHARED HELPERS
    // ──────────────────────────────────────────────────────────

    /**
     * Convert minutes to MySQL TIME format (HH:MM:00).
     */
    private function minutesToTime(?int $minutes): ?string
    {
        if ($minutes === null || $minutes < 0) {
            return null;
        }
        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;
        if ($hours > 838) {
            return '838:59:59';
        }
        return sprintf('%02d:%02d:00', $hours, $mins);
    }

    /**
     * Parse a time value and return a clean display string.
     */
    private function formatTime($value)
    {
        if (!$value) return null;

        if (is_string($value) && !str_contains($value, 'T') && !str_contains($value, '-')) {
            return $value;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('h:i A');
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Find matching registration for a document number + type + optional sub-type.
     *
     * @return array{found: bool, reason?: string, latest?: MasterlistRegistration, existing?: DocumentRequest}
     */
    private function findMatchingRegistration(string $docNo, int $docTypeId, ?int $subTypeId): array
    {
        $allMl = MasterlistRegistration::where('doc_no', $docNo)->get();

        if ($allMl->isEmpty()) {
            return ['found' => false, 'reason' => 'not_registered'];
        }

        $requestIds         = $allMl->pluck('request_id')->unique();
        $relatedDocRequests = DocumentRequest::whereIn('request_id', $requestIds)->get();
        $hasSubType         = $subTypeId && (int) $subTypeId > 0;

        $matching = $relatedDocRequests->filter(function ($dr) use ($docTypeId, $subTypeId, $hasSubType) {
            if ((int) $dr->doc_type_id !== (int) $docTypeId) {
                return false;
            }
            if ($hasSubType) {
                return $dr->sub_type_id && (int) $dr->sub_type_id === (int) $subTypeId;
            }
            return true;
        });

        if ($matching->isNotEmpty()) {
            $matchingIds = $matching->pluck('request_id');
            $latest = MasterlistRegistration::whereIn('request_id', $matchingIds)
                ->where('doc_no', $docNo)
                ->orderByDesc('revise_no')
                ->first();

            return [
                'found'   => true,
                'latest'  => $latest,
                'all'     => $allMl,
                'matches' => $matching,
            ];
        }

        $existingDr = $relatedDocRequests->first();

        if ($hasSubType && (int) $existingDr->doc_type_id === (int) $docTypeId) {
            return [
                'found'          => false,
                'reason'         => 'wrong_subtype',
                'existing_dr'    => $existingDr,
            ];
        }

        return [
            'found'       => false,
            'reason'      => 'wrong_type',
            'existing_dr' => $existingDr,
        ];
    }

    /**
     * Build a user-friendly mismatch error message.
     */
    private function mismatchErrorMessage(string $docNo, array $result): string
    {
        $existingDr = $result['existing_dr'];

        if ($result['reason'] === 'wrong_subtype') {
            $existingSubType = \App\Models\DocType::find($existingDr->sub_type_id);
            return 'Document "' . $docNo . '" is registered under "'
                . ($existingSubType ? $existingSubType->doc_type_name : 'Unknown sub-type')
                . '", not the selected sub-type.';
        }

        $type = \App\Models\DocType::find($existingDr->doc_type_id);
        return 'Document "' . $docNo . '" is registered under "'
            . ($type ? $type->doc_type_name : 'Unknown')
            . '", not the selected Document Type.';
    }

    /**
     * Return the request_ids of the latest revision for each doc_no.
     */
    private function getLatestRevisionIds(): \Illuminate\Support\Collection
    {
        // Find latest revision per (doc_no, doc_type, sub_type)
        $latestIds = DB::select("
            SELECT ml1.request_id
            FROM masterlist_registration ml1
            JOIN document_requests dr1 ON ml1.request_id = dr1.request_id
            WHERE ml1.doc_no IS NOT NULL AND ml1.doc_no != ''
            AND NOT EXISTS (
                SELECT 1
                FROM masterlist_registration ml2
                JOIN document_requests dr2 ON ml2.request_id = dr2.request_id
                WHERE ml2.doc_no = ml1.doc_no
                AND dr2.doc_type_id = dr1.doc_type_id
                AND IFNULL(dr2.sub_type_id, 0) = IFNULL(dr1.sub_type_id, 0)
                AND CAST(ml2.revise_no AS UNSIGNED) > CAST(ml1.revise_no AS UNSIGNED)
            )
        ");

        $latestIds = collect($latestIds)->pluck('request_id');

        // Documents with no masterlist registration (or empty doc_no)
        $noMlIds = DocumentRequest::where(function ($q) {
            $q->whereDoesntHave('masterlistRegistration')
                ->orWhereHas('masterlistRegistration', function ($q2) {
                    $q2->whereNull('doc_no')->orWhere('doc_no', '');
                });
        })->pluck('request_id');

        return $latestIds->merge($noMlIds)->unique();
    }

    /**
     * Safely delete a file from the public disk.
     */
    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    // ──────────────────────────────────────────────────────────
    // REGISTER — Create
    // ──────────────────────────────────────────────────────────

    // GET /register
    public function index()
    {
        $offices = \App\Models\Office::where('status', 'active')->orderBy('office_name')->get();
        return view('pages.dcs.create-update.register', compact('offices'));
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

        // ── Revised mode validation ──
        if ($mode === 'revised') {
            $docNo     = $request->input('masterlistDocNo');
            $docTypeId = $request->input('doc_type_id');
            $subTypeId = $request->input('sub_type_id');

            if (!$docNo) {
                return back()->withInput()
                    ->with('error', 'Document No. is required for revised registration.');
            }

            $result = $this->findMatchingRegistration($docNo, $docTypeId, $subTypeId);

            if (!$result['found']) {
                if ($result['reason'] === 'not_registered') {
                    return back()->withInput()
                        ->with('error', 'Document "' . $docNo . '" is not registered. You must register it as a New Document first before revising it.');
                }

                return back()->withInput()
                    ->with('error', $this->mismatchErrorMessage($docNo, $result));
            }

            $existing = $result['latest'];
            $nextRev  = (int) $existing->revise_no + 1;

            if ((int) $request->input('masterlistRevisionNo') !== $nextRev) {
                return back()->withInput()
                    ->with('error', 'Revision number must be ' . $nextRev . '.');
            }
        }

        // ── New mode validation ──
        if ($mode === 'new') {
            $request->validate([
                'doc_type_id'          => 'required|integer|exists:doc_types,doc_type_id',
                'version_id'           => 'required|integer|exists:version_type,version_id',
                'approval_status'      => 'required|in:applicable,not_applicable',
                'masterlistRevisionNo' => 'nullable|integer|min:0|max:0',
            ], [
                'masterlistRevisionNo.max' => 'A newly registered document must start at Revision 0.',
            ]);
        }

        // ── Common validation ──
        $request->validate([
            'doc_type_id'     => 'required|integer|exists:doc_types,doc_type_id',
            'version_id'      => 'required|integer|exists:version_type,version_id',
            'approval_status' => 'required|in:applicable,not_applicable',
            'drfFile' => 'nullable|file|mimes:pdf,docx|max:10240',
            'dcnFile' => 'nullable|file|mimes:pdf,docx|max:10240',
            'uploadScannedCopy' => 'nullable|file|mimes:pdf,docx|max:10240',
            'scannedRet' => 'nullable|file|mimes:pdf,docx|max:10240',
            'scanneddist' => 'nullable|file|mimes:pdf,docx|max:10240'
        ]);

        $subType   = \App\Models\DocType::find($request->sub_type_id);
        $isSyllabi = $subType && strtolower($subType->doc_type_name) === 'syllabi';

        if ($isSyllabi && $request->has('syllabiCourseName')) {
            foreach ($request->syllabiCourseName as $i => $courseName) {
                $rowNum = $i + 1;

                if (empty($courseName)) {
                    return back()->withInput()->with('error', "Syllabi Row {$rowNum}: Course Name is required.");
                }
                if (empty($request->syllabiAvailability[$i])) {
                    return back()->withInput()->with('error', "Syllabi Row {$rowNum}: Availability is required.");
                }
                if (empty($request->syllabiNoPages[$i]) || $request->syllabiNoPages[$i] <= 0) {
                    return back()->withInput()->with('error', "Syllabi Row {$rowNum}: No. of Pages must be greater than 0.");
                }
                if (empty($request->syllabiDrfAvailability[$i])) {
                    return back()->withInput()->with('error', "Syllabi Row {$rowNum}: DRF Availability is required.");
                }
                if (empty($request->syllabiDrfNo[$i])) {
                    return back()->withInput()->with('error', "Syllabi Row {$rowNum}: DRF No. is required.");
                }
                if (empty($request->syllabiDrfDate[$i])) {
                    return back()->withInput()->with('error', "Syllabi Row {$rowNum}: DRF Date is required.");
                }
                if (empty($request->syllabiDrfReceived[$i])) {
                    return back()->withInput()->with('error', "Syllabi Row {$rowNum}: DRF Received Date is required.");
                }

                // File is NOT required — validate only if provided
                if ($request->hasFile('syllabiScannedDrf') && isset($request->file('syllabiScannedDrf')[$i])) {
                    $file = $request->file('syllabiScannedDrf')[$i];
                    $ext = strtolower($file->getClientOriginalExtension());
                    if (!in_array($ext, ['pdf', 'docx'])) {
                        return back()->withInput()->with('error', "Syllabi Row {$rowNum}: Only .pdf and .docx files are accepted.");
                    }
                    if ($file->getSize() > 10 * 1024 * 1024) {
                        return back()->withInput()->with('error', "Syllabi Row {$rowNum}: File size must not exceed 10MB.");
                    }
                }
            }
        }

        DB::beginTransaction();

        $uploadedFiles = [];

        try {
            // ── 1. Create the master Document Request ──
            $docRequest = DocumentRequest::create([
                'version_id'      => $request->version_id,
                'doc_type_id'     => $request->doc_type_id,
                'sub_type_id'     => $request->sub_type_id,
                'approval_status' => $request->approval_status,
                'created_by'      => auth()->id(),
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
                    $masterlistTimeSpent = $this->minutesToTime(intval($request->masterlistTimeSpent));
                }

                $masterlist = MasterlistRegistration::create([
                    'checklist_id'        => 3,
                    'version_id'          => $versionId,
                    'request_id'          => $requestId,
                    'doc_type_id'         => $docTypeId,
                    'doc_no'              => $request->masterlistDocNo,
                    'doc_receipt_date'    => $request->masterlistReceiptDate,
                    'doc_receipt_time'    => $request->masterlistReceiptTime,
                    'doc_registered_date' => $request->masterlistRegisteredDate,
                    'doc_registered_time' => $request->masterlistRegisteredTime,
                    'time_spent'          => $masterlistTimeSpent,
                    'doc_title'           => $request->masterlistDocTitle,
                    'effectivity_date'    => $request->masterlistEffectivityDate,
                    'revise_no'           => $request->masterlistRevisionNo,
                    'no_pages'            => $request->masterlistNoOfPages,
                    'office_id'           => null,
                    'originator_name'     => null,
                    'deadline'            => $request->deadlineOfSubmission,
                    'in_charge'           => $request->masterlistInCharge,
                    'brief_purpose'       => $request->briefPurpose,
                    'scanned_masterlist'  => $masterlistFile,
                    'created_by'          => auth()->id(),
                ]);

                // ── Origins (offices + person names from single comma-separated field) ──
                $primaryOfficeId = $this->saveOrigins($masterlist, $request->input('masterlistSourceUnit'));
                if ($primaryOfficeId) {
                    $masterlist->update(['office_id' => $primaryOfficeId]);
                }
            }

            // ── Syllabi (inside transaction — just data operations) ──
            if ($isSyllabi) {
                $oldSyllabi = Syllabi::where('request_id', $requestId)->get();
                foreach ($oldSyllabi as $syl) {
                    if ($syl->scanned_drf) $filesToDelete[] = $syl->scanned_drf;
                }
                $oldSyllabi->each->delete();

                if ($request->has('syllabiCourseName')) {
                    foreach ($request->syllabiCourseName as $i => $courseName) {
                        if (empty($courseName)) continue;

                        $scannedDrf = null;
                        if ($request->hasFile('syllabiScannedDrf') && isset($request->file('syllabiScannedDrf')[$i])) {
                            $scannedDrf = $request->file('syllabiScannedDrf')[$i]->store('scans/syllabi', 'public');
                            $uploadedFiles[] = $scannedDrf;
                        }

                        Syllabi::create([
                            'request_id'           => $requestId,
                            'course_name'          => $courseName,
                            'syllabi_availability' => $request->syllabiAvailability[$i] ?? null,
                            'no_pages'             => $request->syllabiNoPages[$i] ?? null,
                            'drf_availability'     => $request->syllabiDrfAvailability[$i] ?? null,
                            'drf_no'               => $request->syllabiDrfNo[$i] ?? null,
                            'drf_date'             => $request->syllabiDrfDate[$i] ?? null,
                            'drf_received_date'    => $request->syllabiDrfReceived[$i] ?? null,
                            'scanned_drf'          => $scannedDrf,
                        ]);
                    }
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
                    $retrievalTimeSpent = $this->minutesToTime(intval($request->retrievalTimeSpent));
                }

                $retrieval = DocumentRetrieval::create([
                    'checklist_id'              => 4,
                    'version_id'                => $versionId,
                    'request_id'                => $requestId,
                    'doc_type_id'               => $docTypeId,
                    'doc_retrieval_date_actual' => $request->retrievalDate,
                    'doc_retrieval_time_actual' => $request->retrievalTime,
                    'doc_retrieval_date_file'   => $request->retrievalFormDate,
                    'doc_retrieval_time_file'   => $request->retrievalFormTime,
                    'time_spent'                => $retrievalTimeSpent,
                    'remarks'                   => $request->retrievalRemarks,
                    'scanned_retrieval'         => $retrievalFile,
                    'created_by'                => auth()->id(),
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
                    $distTimeSpent = $this->minutesToTime(intval($request->distributionTimeSpent));
                }

                $distribution = DocumentDistribution::create([
                    'checklist_id'                    => 5,
                    'version_id'                      => $versionId,
                    'request_id'                      => $requestId,
                    'doc_type_id'                     => $docTypeId,
                    'doc_distribution_date_actual'    => $request->distributionDate,
                    'doc_distribution_time_actual'    => $request->distributionTime,
                    'doc_distribution_date_file'      => $request->distributionFormDate,
                    'doc_distribution_time_file'      => $request->distributionFormTime,
                    'time_spent'                      => $distTimeSpent,
                    'remarks'                         => $request->distributionRemarks,
                    'scanned_distribution'            => $distFile,
                    'created_by'                      => auth()->id(),
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

            foreach ($uploadedFiles as $file) {
                Storage::disk('public')->delete($file);
            }

            $refId = uniqid('err_');
            \Log::error("Document registration failed [{$refId}]: " . $e->getMessage());
            return back()->withInput()
                ->with('error', 'Failed to save document. Please try again. (ref: ' . $refId . ')');
        }
    }

    // ──────────────────────────────────────────────────────────
    // CHECK DOC NO (AJAX)
    // ──────────────────────────────────────────────────────────

    public function checkDocNo(Request $request)
    {
        $docNo     = $request->input('doc_no');
        $docTypeId = $request->input('doc_type_id');
        $subTypeId = $request->input('sub_type_id');

        if (!$docNo) {
            return response()->json([
                'exists'  => false,
                'message' => 'No document number provided.',
            ]);
        }

        $result = $this->findMatchingRegistration($docNo, $docTypeId, $subTypeId);

        // ── Match found ──
        if ($result['found']) {
            $latest = $result['latest'];
            if ($latest) {
                $latestRev       = (int) $latest->revise_no;
                $registrations   = MasterlistRegistration::whereIn(
                    'request_id',
                    $result['matches']->pluck('request_id')
                )->where('doc_no', $docNo)->orderByDesc('revise_no')->get();

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
        }

        // ── Not registered at all ──
        if ($result['reason'] === 'not_registered') {
            $hasSubType = $subTypeId && (int) $subTypeId > 0;
            $message    = 'This document number is not registered';
            if ($hasSubType) {
                $subType   = \App\Models\DocType::find($subTypeId);
                $message  .= ' under "' . ($subType ? $subType->doc_type_name : 'this sub-type') . '"';
            } elseif ($docTypeId) {
                $type     = \App\Models\DocType::find($docTypeId);
                $message .= ' under "' . ($type ? $type->doc_type_name : 'this document type') . '"';
            }
            $message .= '. Please register it as a New Document first.';

            return response()->json([
                'exists'   => false,
                'message'  => $message,
                'next_rev' => null,
            ]);
        }

        // ── Mismatch ──
        $existingDr = $result['existing_dr'];

        if ($result['reason'] === 'wrong_subtype') {
            $existingSubType = \App\Models\DocType::find($existingDr->sub_type_id);
            return response()->json([
                'exists'                => false,
                'wrong_subtype'         => true,
                'existing_subtype_name' => $existingSubType ? $existingSubType->doc_type_name : 'Unknown',
                'message'               => $this->mismatchErrorMessage($docNo, $result),
                'next_rev'              => null,
            ]);
        }

        $existingType = \App\Models\DocType::find($existingDr->doc_type_id);
        return response()->json([
            'exists'             => false,
            'wrong_type'         => true,
            'existing_type_name' => $existingType ? $existingType->doc_type_name : 'Unknown',
            'message'            => $this->mismatchErrorMessage($docNo, $result),
            'next_rev'           => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // UPDATE LIST — Blade view
    // ──────────────────────────────────────────────────────────
    public function updateList(Request $request)
    {
        $visibleIds = $this->getLatestRevisionIds();

        $query = DocumentRequest::with(['docType', 'version'])
            ->whereIn('request_id', $visibleIds)
            ->orderBy('request_id', 'desc');

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

        if ($request->filled('doc_type_id')) {
            $query->where('doc_type_id', $request->doc_type_id);
        }

        $documents = $query->paginate(10)->withQueryString();
        $docTypes  = \App\Models\DocType::whereNull('parent_id')->get();

        return view('pages.dcs.create-update.update', compact('documents', 'docTypes'));
    }

    // ──────────────────────────────────────────────────────────
    // UPDATE DATA — JSON for listing page
    // ──────────────────────────────────────────────────────────
    public function updateData(Request $request)
    {
        try {
            $perPage   = (int) $request->input('per_page', 15);
            $search    = $request->input('search', '');
            $docTypeId = $request->input('doc_type_id');

            $visibleIds = $this->getLatestRevisionIds();

            $query = DocumentRequest::with([
                'docType',
                'documentRequestForm',
                'masterlistRegistration',
                'documentChangeNotice',
                'documentRetrieval',
                'documentDistribution',
            ])
            ->whereIn('request_id', $visibleIds)
            ->orderBy('request_id', 'desc');

            if ($docTypeId && $docTypeId !== 'all') {
                $query->where('doc_type_id', $docTypeId);
            }

            if ($search) {
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

            $total       = $query->count();
            $currentPage = max(1, (int) $request->input('page', 1));
            $lastPage    = max(1, (int) ceil($total / $perPage));
            $currentPage = min($currentPage, $lastPage);

            $documents = $query->skip(($currentPage - 1) * $perPage)
                ->take($perPage)
                ->get();

            $checklistNames = [];
            try {
                $checklistNames = \App\Models\ChecklistType::pluck('checklist_name', 'checklist_id')->toArray();
            } catch (\Exception $e) {
                $checklistNames = [
                    1 => 'Document Request Form',
                    2 => 'Document Change Notice',
                    3 => 'Masterlist Registration',
                    4 => 'Document Retrieval',
                    5 => 'Document Distribution',
                ];
            }

            $rows = $documents->map(function ($doc) use ($checklistNames) {
                $ml   = $doc->masterlistRegistration;
                $drf  = $doc->documentRequestForm;
                $dcn  = $doc->documentChangeNotice;
                $ret  = $doc->documentRetrieval;
                $dist = $doc->documentDistribution;

                $docNo = $ml ? $ml->doc_no : null;
                $title = ($ml && $ml->doc_title) ? $ml->doc_title
                    : (($drf && $drf->doc_title) ? $drf->doc_title : 'N/A');
                $revNo = $ml ? (int) $ml->revise_no : 0;

                $checklists = [];
                if ($drf)  $checklists[] = $checklistNames[1] ?? 'DRF';
                if ($dcn)  $checklists[] = $checklistNames[2] ?? 'DCN';
                if ($ml)   $checklists[] = $checklistNames[3] ?? 'Masterlist';
                if ($ret)  $checklists[] = $checklistNames[4] ?? 'Retrieval';
                if ($dist) $checklists[] = $checklistNames[5] ?? 'Distribution';

                return [
                    'request_id'  => $doc->request_id,
                    'doc_no'      => $docNo ?? 'N/A',
                    'title'       => $title,
                    'rev_no'      => $revNo,
                    'doc_type'    => $doc->docType->doc_type_name ?? 'N/A',
                    'checklists'  => $checklists,
                    'edit_url'    => route('register.edit', $doc->request_id),
                    'history_url' => $docNo ? route('register.history', $docNo) : null,
                ];
            });

            return response()->json([
                'data'         => $rows,
                'total'        => $total,
                'current_page' => $currentPage,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
            ]);

        } catch (\Exception $e) {
            $refId = uniqid('err_');
            \Log::error("Update data error [{$refId}]: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'error'        => "An error occurred (ref: {$refId})",
                'data'         => [],
                'total'        => 0,
                'current_page' => 1,
                'last_page'    => 1,
                'per_page'     => 15,
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────
    // EDIT — Show edit form
    // ──────────────────────────────────────────────────────────
    public function edit($id)
    {
        $docRequest = DocumentRequest::findOrFail($id);

        $ml = MasterlistRegistration::where('request_id', $id)->first();
        if ($ml && $ml->doc_no) {
            // Scope latest revision to same doc_type + sub_type
            $sameTypeRequestIds = DocumentRequest::where('doc_type_id', $docRequest->doc_type_id)
                ->where('sub_type_id', $docRequest->sub_type_id)
                ->pluck('request_id');

            $latestRev = MasterlistRegistration::where('doc_no', $ml->doc_no)
                ->whereIn('request_id', $sameTypeRequestIds)
                ->max('revise_no');

            if ((int) $ml->revise_no < (int) $latestRev) {
                return redirect()->route('register.update')
                    ->with('error', "Only the latest revision (Rev {$latestRev}) can be edited.");
            }
        }

        $drf                = DocumentRequestForm::where('request_id', $id)->first();
        $dcn                = DocumentChangeNotice::where('request_id', $id)->first();
        $revisions          = $dcn ? DocRevision::where('dcn_id', $dcn->dcn_id)->get() : collect();
        $masterlist         = $ml;
        $retrieval          = DocumentRetrieval::where('request_id', $id)->first();
        $retrievalOffices   = $retrieval ? RetrievalOffice::where('retrieval_id', $retrieval->retrieval_id)->get() : collect();
        $distribution       = DocumentDistribution::where('request_id', $id)->first();
        $distributionOffices = $distribution ? DistributionOffice::where('distribution_id', $distribution->distribution_id)->get() : collect();
        $approval           = ApprovalRecord::where('request_id', $id)->first();
        $syllabi            = Syllabi::where('request_id', $id)->get();

        $origins = collect();
        $masterlistSourceUnit = '';
        if ($masterlist) {
            $origins = MasterlistOrigin::where('masterlist_id', $masterlist->masterlist_id)->with('office')->get();
            $parts = [];
            foreach ($origins as $o) {
                if ($o->office) {
                    $parts[] = $o->office->office_name;
                } elseif ($o->originator_name) {
                    $parts[] = $o->originator_name;
                }
            }
            $masterlistSourceUnit = implode(', ', $parts);
        }

        $offices        = \App\Models\Office::orderBy('office_name')->get();
        $docTypes       = \App\Models\DocType::orderBy('doc_type_name')->get();
        $versionTypes   = \App\Models\VersionType::all();
        $approvalBodies = \App\Models\ApprovalBody::all();

        return view('pages.dcs.create-update.edit', compact(
            'docRequest', 'drf', 'dcn', 'revisions', 'masterlist',
            'retrieval', 'retrievalOffices', 'distribution',
            'distributionOffices', 'approval', 'syllabi',
            'offices', 'docTypes', 'versionTypes', 'approvalBodies', 'masterlistSourceUnit'
        ));
    }

    // ──────────────────────────────────────────────────────────
    // UPDATE — Save changes
    // ──────────────────────────────────────────────────────────
    public function updateDoc(Request $request, $id)
    {
        DB::beginTransaction();
        $uploadedFiles = [];
        $filesToDelete = []; // Defer deletion until after commit

        try {
            $docRequest = DocumentRequest::findOrFail($id);

            // ── 1. Update master request ──
            $docRequest->update([
                'version_id'      => $request->version_id,
                'doc_type_id'     => $request->doc_type_id,
                'sub_type_id'     => $request->sub_type_id,
                'approval_status' => $request->approval_status,
            ]);

            $requestId = $docRequest->request_id;
            $docTypeId = $request->doc_type_id;
            $versionId = $request->version_id;

            // ── Delete records for unchecked checklists ──
            $checkedChecklists = array_map('intval', $request->input('checklists', []));

            // DRF unchecked → delete
            if (!in_array(1, $checkedChecklists)) {
                $existingDrf = DocumentRequestForm::where('request_id', $requestId)->first();
                if ($existingDrf) {
                    if ($existingDrf->scanned_drf) $filesToDelete[] = $existingDrf->scanned_drf;
                    $existingDrf->delete();
                }
            }

            // DCN unchecked → delete
            if (!in_array(2, $checkedChecklists)) {
                $existingDcn = DocumentChangeNotice::where('request_id', $requestId)->first();
                if ($existingDcn) {
                    if ($existingDcn->scanned_dcn) $filesToDelete[] = $existingDcn->scanned_dcn;
                    $oldRevisions = DocRevision::where('dcn_id', $existingDcn->dcn_id)->get();
                    foreach ($oldRevisions as $rev) {
                        if ($rev->scanned_copy) $filesToDelete[] = $rev->scanned_copy;
                    }
                    $oldRevisions->each->delete();
                    $existingDcn->delete();
                }
            }

            // Masterlist unchecked → delete
            if (!in_array(3, $checkedChecklists)) {
                $existingMl = MasterlistRegistration::where('request_id', $requestId)->first();
                if ($existingMl) {
                    if ($existingMl->scanned_masterlist) $filesToDelete[] = $existingMl->scanned_masterlist;
                    $existingMl->delete();
                }
            }

            // Retrieval unchecked → delete
            if (!in_array(4, $checkedChecklists)) {
                $existingRet = DocumentRetrieval::where('request_id', $requestId)->first();
                if ($existingRet) {
                    if ($existingRet->scanned_retrieval) $filesToDelete[] = $existingRet->scanned_retrieval;
                    RetrievalOffice::where('retrieval_id', $existingRet->retrieval_id)->delete();
                    $existingRet->delete();
                }
            }

            // Distribution unchecked → delete
            if (!in_array(5, $checkedChecklists)) {
                $existingDist = DocumentDistribution::where('request_id', $requestId)->first();
                if ($existingDist) {
                    if ($existingDist->scanned_distribution) $filesToDelete[] = $existingDist->scanned_distribution;
                    DistributionOffice::where('distribution_id', $existingDist->distribution_id)->delete();
                    $existingDist->delete();
                }
            }

            // Approval not applicable → delete
            if ($request->approval_status !== 'applicable') {
                $existingAppr = ApprovalRecord::where('request_id', $requestId)->first();
                if ($existingAppr) {
                    $existingAppr->delete();
                }
            }

            // ── 2. DRF ──
            if ($request->filled('drfNo')) {
                $drf     = DocumentRequestForm::where('request_id', $requestId)->first();
                $drfFile = $drf ? $drf->scanned_drf : null;

                if ($request->hasFile('drfFile')) {
                    if ($drfFile) $filesToDelete[] = $drfFile;
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
                $dcn     = DocumentChangeNotice::where('request_id', $requestId)->first();
                $dcnFile = $dcn ? $dcn->scanned_dcn : null;

                if ($request->hasFile('dcnFile')) {
                    if ($dcnFile) $filesToDelete[] = $dcnFile;
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

                // Revisions — delete old (with file cleanup), insert new
                $oldRevisions = DocRevision::where('dcn_id', $dcn->dcn_id)->get();
                foreach ($oldRevisions as $rev) {
                    if ($rev->scanned_copy) $filesToDelete[] = $rev->scanned_copy;
                }
                $oldRevisions->each->delete();

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
                $masterlist     = MasterlistRegistration::where('request_id', $requestId)->first();
                $masterlistFile = $masterlist ? $masterlist->scanned_masterlist : null;

                if ($request->hasFile('uploadScannedCopy')) {
                    if ($masterlistFile) $filesToDelete[] = $masterlistFile;
                    $masterlistFile = $request->file('uploadScannedCopy')->store('scans/masterlist', 'public');
                    $uploadedFiles[] = $masterlistFile;
                }

                $masterlistTimeSpent = null;
                if ($request->filled('masterlistTimeSpent') && is_numeric($request->masterlistTimeSpent) && $request->masterlistTimeSpent >= 0) {
                    $masterlistTimeSpent = $this->minutesToTime(intval($request->masterlistTimeSpent));
                }

                $masterlistData = [
                    'checklist_id'        => 3,
                    'version_id'          => $versionId,
                    'doc_type_id'         => $docTypeId,
                    'doc_no'              => $request->masterlistDocNo,
                    'doc_receipt_date'    => $request->masterlistReceiptDate,
                    'doc_receipt_time'    => $request->masterlistReceiptTime,
                    'doc_registered_date' => $request->masterlistRegisteredDate,
                    'doc_registered_time' => $request->masterlistRegisteredTime,
                    'time_spent'          => $masterlistTimeSpent,
                    'doc_title'           => $request->masterlistDocTitle,
                    'effectivity_date'    => $request->masterlistEffectivityDate,
                    'revise_no'           => $request->masterlistRevisionNo,
                    'no_pages'            => $request->masterlistNoOfPages,
                    'office_id'           => null,          // ← reset, will be restored below
                    'originator_name'     => null,
                    'deadline'            => $request->deadlineOfSubmission,
                    'in_charge'           => $request->masterlistInCharge,
                    'brief_purpose'       => $request->briefPurpose,
                    'scanned_masterlist'  => $masterlistFile,
                ];

                if ($masterlist) {
                    $masterlist->update($masterlistData);
                } else {
                    $masterlist = MasterlistRegistration::create(array_merge($masterlistData, [
                        'request_id' => $requestId,
                        'created_by' => auth()->id(),
                    ]));
                }

                // ── Fix: replace origins AND restore primary office_id ──
                MasterlistOrigin::where('masterlist_id', $masterlist->masterlist_id)->delete();
                $primaryOfficeId = $this->saveOrigins($masterlist, $request->input('masterlistSourceUnit'));
                if ($primaryOfficeId) {
                    $masterlist->update(['office_id' => $primaryOfficeId]);
                }
            }

            // ── Syllabi ──
            $subType   = \App\Models\DocType::find($request->sub_type_id);
            $isSyllabi = $subType && strtolower($subType->doc_type_name) === 'syllabi';

            if ($isSyllabi) {
                // ── Syllabi validation ──
                if ($request->has('syllabiCourseName')) {
                    foreach ($request->syllabiCourseName as $i => $courseName) {
                        $rowNum = $i + 1;

                        if (empty($courseName)) {
                            return back()->withInput()->with('error', "Syllabi Row {$rowNum}: Course Name is required.");
                        }
                        if (empty($request->syllabiAvailability[$i])) {
                            return back()->withInput()->with('error', "Syllabi Row {$rowNum}: Availability is required.");
                        }
                        if (empty($request->syllabiNoPages[$i]) || $request->syllabiNoPages[$i] <= 0) {
                            return back()->withInput()->with('error', "Syllabi Row {$rowNum}: No. of Pages must be greater than 0.");
                        }
                        if (empty($request->syllabiDrfAvailability[$i])) {
                            return back()->withInput()->with('error', "Syllabi Row {$rowNum}: DRF Availability is required.");
                        }
                        if (empty($request->syllabiDrfNo[$i])) {
                            return back()->withInput()->with('error', "Syllabi Row {$rowNum}: DRF No. is required.");
                        }
                        if (empty($request->syllabiDrfDate[$i])) {
                            return back()->withInput()->with('error', "Syllabi Row {$rowNum}: DRF Date is required.");
                        }
                        if (empty($request->syllabiDrfReceived[$i])) {
                            return back()->withInput()->with('error', "Syllabi Row {$rowNum}: DRF Received Date is required.");
                        }

                        $file = $request->hasFile('syllabiScannedDrf') && isset($request->file('syllabiScannedDrf')[$i])
                            ? $request->file('syllabiScannedDrf')[$i]
                            : null;

                        // On update, file is only required for new rows or if no existing file
                        if (!$file) {
                            // Check if existing syllabi has a file
                            $existingSyl = Syllabi::where('request_id', $requestId)->get();
                            if (!$existingSyl[$i] ?? null || !$existingSyl[$i]->scanned_drf) {
                                return back()->withInput()->with('error', "Syllabi Row {$rowNum}: Scanned DRF file is required.");
                            }
                        }
                    }
                }
            }

            // ── 5. Retrieval ──
            if ($request->filled('retrievalDate')) {
                $retrieval     = DocumentRetrieval::where('request_id', $requestId)->first();
                $retrievalFile = $retrieval ? $retrieval->scanned_retrieval : null;

                if ($request->hasFile('scannedRet')) {
                    if ($retrievalFile) $filesToDelete[] = $retrievalFile;
                    $retrievalFile = $request->file('scannedRet')->store('scans/retrieval', 'public');
                    $uploadedFiles[] = $retrievalFile;
                }

                $retrievalTimeSpent = null;
                if ($request->filled('retrievalTimeSpent') && is_numeric($request->retrievalTimeSpent) && $request->retrievalTimeSpent >= 0) {
                    $retrievalTimeSpent = $this->minutesToTime(intval($request->retrievalTimeSpent));
                }

                $retrievalData = [
                    'checklist_id'              => 4,
                    'version_id'                => $versionId,
                    'doc_type_id'               => $docTypeId,
                    'doc_retrieval_date_actual' => $request->retrievalDate,
                    'doc_retrieval_time_actual' => $request->retrievalTime,
                    'doc_retrieval_date_file'   => $request->retrievalFormDate,
                    'doc_retrieval_time_file'   => $request->retrievalFormTime,
                    'time_spent'                => $retrievalTimeSpent,
                    'remarks'                   => $request->retrievalRemarks,
                    'scanned_retrieval'         => $retrievalFile,
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
                $distFile     = $distribution ? $distribution->scanned_distribution : null;

                if ($request->hasFile('scanneddist')) {
                    if ($distFile) $filesToDelete[] = $distFile;
                    $distFile = $request->file('scanneddist')->store('scans/distribution', 'public');
                    $uploadedFiles[] = $distFile;
                }

                $distTimeSpent = null;
                if ($request->filled('distributionTimeSpent') && is_numeric($request->distributionTimeSpent) && $request->distributionTimeSpent >= 0) {
                    $distTimeSpent = $this->minutesToTime(intval($request->distributionTimeSpent));
                }

                $distData = [
                    'checklist_id'                    => 5,
                    'version_id'                      => $versionId,
                    'doc_type_id'                     => $docTypeId,
                    'doc_distribution_date_actual'    => $request->distributionDate,
                    'doc_distribution_time_actual'    => $request->distributionTime,
                    'doc_distribution_date_file'      => $request->distributionFormDate,
                    'doc_distribution_time_file'      => $request->distributionFormTime,
                    'time_spent'                      => $distTimeSpent,
                    'remarks'                         => $request->distributionRemarks,
                    'scanned_distribution'            => $distFile,
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
            if ($request->approval_status === 'applicable') {
                if ($request->filled('approvalBody')) {
                    $approval     = ApprovalRecord::where('request_id', $requestId)->first();
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
            }

            DB::commit();

            // Now safe to delete old files
            foreach ($filesToDelete as $file) {
                Storage::disk('public')->delete($file);
            }

            return redirect()->route('register.edit', $id)
                ->with('success', 'Document updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();

            // Rollback succeeded — delete newly uploaded files only
            foreach ($uploadedFiles as $file) {
                Storage::disk('public')->delete($file);
            }

            // $filesToDelete are NOT deleted because the transaction was rolled back
            // and the database still references those files

            $refId = uniqid('err_');
            \Log::error("Document update failed [{$refId}]: " . $e->getMessage());
            return back()->withInput()
                ->with('error', 'Failed to update document. Please try again. (ref: ' . $refId . ')');
        }
    }

    private function saveOrigins(MasterlistRegistration $masterlist, ?string $originString): ?int
    {
        if (!$originString || trim($originString) === '') return null;

        $items = array_filter(array_map('trim', explode(',', $originString)));
        if (empty($items)) return null;

        $offices = \App\Models\Office::where('status', 'active')->get()->keyBy(fn($o) => strtolower($o->office_name));
        $firstOfficeId = null;

        foreach ($items as $item) {
            $data = ['masterlist_id' => $masterlist->masterlist_id];
            $matchedOffice = $offices->get(strtolower($item));

            if ($matchedOffice) {
                $data['office_id']       = $matchedOffice->office_id;
                $data['originator_name'] = null;
                if ($firstOfficeId === null) {
                    $firstOfficeId = $matchedOffice->office_id;
                }
            } else {
                $data['office_id']       = null;
                $data['originator_name'] = $item;
            }

            MasterlistOrigin::create($data);
        }

        return $firstOfficeId;
    }

    // ──────────────────────────────────────────────────────────
    // DELETE
    // ──────────────────────────────────────────────────────────
    public function destroy($id)
    {
        $docRequest = DocumentRequest::findOrFail($id);

        $ml = MasterlistRegistration::where('request_id', $id)->first();
        if ($ml && $ml->doc_no) {
            $sameTypeRequestIds = DocumentRequest::where('doc_type_id', $docRequest->doc_type_id)
                ->where('sub_type_id', $docRequest->sub_type_id)
                ->pluck('request_id');

            $latestRev = MasterlistRegistration::where('doc_no', $ml->doc_no)
                ->whereIn('request_id', $sameTypeRequestIds)
                ->max('revise_no');

            if ((int) $ml->revise_no < (int) $latestRev) {
                return redirect()->route('register.update')
                    ->with('error', "Only the latest revision (Rev {$latestRev}) can be deleted.");
            }
        }

        DB::beginTransaction();
        $filesToDelete = [];

        try {
            $requestId = $docRequest->request_id;

            // DRF
            $drf = DocumentRequestForm::where('request_id', $requestId)->first();
            if ($drf) {
                if ($drf->scanned_drf) $filesToDelete[] = $drf->scanned_drf;
                $drf->delete();
            }

            // DCN + revisions
            $dcn = DocumentChangeNotice::where('request_id', $requestId)->first();
            if ($dcn) {
                if ($dcn->scanned_dcn) $filesToDelete[] = $dcn->scanned_dcn;
                $revisions = DocRevision::where('dcn_id', $dcn->dcn_id)->get();
                foreach ($revisions as $rev) {
                    if ($rev->scanned_copy) $filesToDelete[] = $rev->scanned_copy;
                }
                $revisions->each->delete();
                $dcn->delete();
            }

            // Masterlist
            $masterlist = MasterlistRegistration::where('request_id', $requestId)->first();
            if ($masterlist) {
                if ($masterlist->scanned_masterlist) $filesToDelete[] = $masterlist->scanned_masterlist;
                $masterlist->delete();
            }

            // Syllabi
            $syllabiRecords = Syllabi::where('request_id', $requestId)->get();
            foreach ($syllabiRecords as $syl) {
                if ($syl->scanned_drf) $filesToDelete[] = $syl->scanned_drf;
            }
            $syllabiRecords->each->delete();

            // Retrieval + offices
            $retrieval = DocumentRetrieval::where('request_id', $requestId)->first();
            if ($retrieval) {
                if ($retrieval->scanned_retrieval) $filesToDelete[] = $retrieval->scanned_retrieval;
                RetrievalOffice::where('retrieval_id', $retrieval->retrieval_id)->delete();
                $retrieval->delete();
            }

            // Distribution + offices
            $distribution = DocumentDistribution::where('request_id', $requestId)->first();
            if ($distribution) {
                if ($distribution->scanned_distribution) $filesToDelete[] = $distribution->scanned_distribution;
                DistributionOffice::where('distribution_id', $distribution->distribution_id)->delete();
                $distribution->delete();
            }

            // Approval
            ApprovalRecord::where('request_id', $requestId)->delete();

            // The master record
            $docRequest->delete();

            DB::commit();

            // Now safe to delete files
            foreach ($filesToDelete as $file) {
                Storage::disk('public')->delete($file);
            }

            return redirect()->route('register.update')
                ->with('success', 'Document deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            $refId = uniqid('err_');
            \Log::error("Document deletion failed [{$refId}]: " . $e->getMessage());
            return back()->with('error', 'Failed to delete document. Please try again. (ref: ' . $refId . ')');
        }
    }

    // ──────────────────────────────────────────────────────────
    // HISTORY — Read-only timeline of all revisions
    // ──────────────────────────────────────────────────────────
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

    // ──────────────────────────────────────────────────────────
    // DATABASE
    // ──────────────────────────────────────────────────────────
    public function databaseIndex()
    {
        $docTypes = \App\Models\DocType::whereNull('parent_id')->get();
        $offices  = \App\Models\Office::where('status', 'active')->orderBy('office_name')->get();
        return view('pages.dcs.database.index', compact('docTypes', 'offices'));
    }

    public function databaseData(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 20);

            $query = DocumentRequest::with([
                'docType',
                'documentRequestForm',
                'masterlistRegistration',
                'masterlistRegistration.origins.office',
                'approvalRecords',
                'documentChangeNotice',
                'documentRetrieval',
                'documentRetrieval.offices',
                'documentDistribution',
                'documentDistribution.offices',
            ]);

            // Doc type filter
            if ($request->input('doc_type_id') && $request->input('doc_type_id') !== 'all') {
                $query->where('doc_type_id', $request->input('doc_type_id'));
            }

            // Search
            if ($request->input('search')) {
                $search = $request->input('search');
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
            if ($request->input('originator')) {
                $search = $request->input('originator');
                $query->whereHas('masterlistRegistration', function ($q) use ($search) {
                    $q->whereHas('origins', function ($q2) use ($search) {
                        $q2->where('originator_name', 'like', "%{$search}%")
                        ->orWhereHas('office', function ($q3) use ($search) {
                            $q3->where('office_name', 'like', "%{$search}%");
                        });
                    });
                });
            }
            if ($request->input('source_unit')) {
                $query->whereHas('documentRequestForm', function ($q) use ($request) {
                    $q->where('office_id', $request->input('source_unit'));
                });
            }
            if ($request->input('status')) {
                $query->where('approval_status', $request->input('status'));
            }
            if ($request->input('date_from')) {
                $query->whereHas('masterlistRegistration', function ($q) use ($request) {
                    $q->where('effectivity_date', '>=', $request->input('date_from'));
                });
            }
            if ($request->input('date_to')) {
                $query->whereHas('masterlistRegistration', function ($q) use ($request) {
                    $q->where('effectivity_date', '<=', $request->input('date_to'));
                });
            }
            if ($request->input('rev_no')) {
                $query->whereHas('masterlistRegistration', function ($q) use ($request) {
                    $q->where('revise_no', $request->input('rev_no'));
                });
            }

            // Get all matching documents
            $allDocs = $query->get();

            // Build rows using eager-loaded relationships
            $allRows = $allDocs->map(function ($doc) {
                $drf  = $doc->documentRequestForm;
                $ml   = $doc->masterlistRegistration;
                $appr = $doc->approvalRecords ? $doc->approvalRecords->first() : null;
                $dcn  = $doc->documentChangeNotice;
                $ret  = $doc->documentRetrieval;
                $dist = $doc->documentDistribution;

                $dcnPurpose = null;
                if ($dcn) {
                    $firstRev = DocRevision::where('dcn_id', $dcn->dcn_id)->first();
                    if ($firstRev) {
                        $dcnPurpose = $firstRev->brief_purpose;
                    }
                }

                $retOffices = null;
                if ($ret && $ret->offices) {
                    $retOffices = $ret->offices->pluck('office_name')->implode(', ') ?: null;
                }

                $distOffices = null;
                if ($dist && $dist->offices) {
                    $distOffices = $dist->offices->pluck('office_name')->implode(', ') ?: null;
                }

                $sourceUnitName = null;
                if ($drf && $drf->office_id) {
                    $office = \App\Models\Office::find($drf->office_id);
                    $sourceUnitName = $office ? $office->office_name : null;
                }

                $deadlineDiff = null;
                if ($ml && $ml->deadline && $ml->effectivity_date) {
                    $deadlineDiff = \Carbon\Carbon::parse($ml->effectivity_date)->diffInDays(\Carbon\Carbon::parse($ml->deadline));
                }

                return [
                    'request_id'       => $doc->request_id,
                    'doc_type_id'      => $doc->doc_type_id,      // ← add
                    'sub_type_id'      => $doc->sub_type_id,
                    'doc_no'           => $ml ? $ml->doc_no : 'N/A',
                    'rev_no'           => $ml ? (int) $ml->revise_no : 0,
                    'title'            => ($ml && $ml->doc_title) ? $ml->doc_title : (($drf && $drf->doc_title) ? $drf->doc_title : 'N/A'),
                    'effectivity'      => ($ml && $ml->effectivity_date) ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : null,
                    'originator'       => $ml && $ml->origins->count() > 0
                                            ? $ml->origins->map(function ($o) {
                                                return $o->office ? $o->office->office_name : $o->originator_name;
                                            })->filter()->implode(', ') ?: null
                                            : null,
                    'pages'            => $ml ? $ml->no_pages : null,
                    'status'           => $doc->approval_status ?? 'Active',
                    'pdf_path'         => ($ml && $ml->scanned_masterlist) ? '/storage/' . $ml->scanned_masterlist : null,
                    'source_unit'      => $sourceUnitName, //the source unit of masterlist
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
                    'dcn_scan'         => ($dcn && $dcn->scanned_dcn) ? '/storage/' . $dcn->scanned_dcn : null,
                    'drf_no'           => $drf ? $drf->drf_no : null,
                    'drf_date'         => ($drf && $drf->drf_date) ? \Carbon\Carbon::parse($drf->drf_date)->format('M d, Y') : null,
                    'drf_receipt_date' => ($drf && $drf->drf_receipt_date) ? \Carbon\Carbon::parse($drf->drf_receipt_date)->format('M d, Y') : null,
                    'drf_receipt_time' => $drf && $drf->drf_receipt_time ? $this->formatTime($drf->drf_receipt_time) : null,
                    'drf_scan'         => ($drf && $drf->scanned_drf) ? '/storage/' . $drf->scanned_drf : null,
                    'dist_onfile_date' => ($dist && $dist->doc_distribution_date_file) ? \Carbon\Carbon::parse($dist->doc_distribution_date_file)->format('M d, Y') : null,
                    'dist_onfile_time' => $dist && $dist->doc_distribution_time_file ? $this->formatTime($dist->doc_distribution_time_file) : null,
                    'dist_actual_date' => ($dist && $dist->doc_distribution_date_actual) ? \Carbon\Carbon::parse($dist->doc_distribution_date_actual)->format('M d, Y') : null,
                    'dist_actual_time' => $dist && $dist->doc_distribution_time_actual ? $this->formatTime($dist->doc_distribution_time_actual) : null,
                    'dist_offices'     => $distOffices,
                    'dist_scan'        => ($dist && $dist->scanned_distribution) ? '/storage/' . $dist->scanned_distribution : null,
                    'ret_onfile'       => ($ret && $ret->doc_retrieval_date_file) ? \Carbon\Carbon::parse($ret->doc_retrieval_date_file)->format('M d, Y') : null,
                    'ret_actual'       => ($ret && $ret->doc_retrieval_date_actual) ? \Carbon\Carbon::parse($ret->doc_retrieval_date_actual)->format('M d, Y') : null,
                    'ret_offices'      => $retOffices,
                    'ret_scan'         => ($ret && $ret->scanned_retrieval) ? '/storage/' . $ret->scanned_retrieval : null,
                ];
            });

            // ── Group by doc_no + doc_type + sub_type ──
            $grouped = $allRows->groupBy(function ($row) {
                if (!$row['doc_no'] || $row['doc_no'] === 'N/A') {
                    return 'no_ml_' . $row['request_id'];
                }
                return $row['doc_no'] . '||' . ($row['doc_type_id'] ?? 0) . '||' . ($row['sub_type_id'] ?? 0);
            });

            $groups = collect();
            foreach ($grouped as $groupKey => $rows) {
                $sorted = $rows->sortByDesc('rev_no')->values();
                $docNo  = $groupKey;

                if (str_starts_with($groupKey, 'no_ml_')) {
                    $docNo = 'N/A';
                }

                // Parent = latest revision
                $parent = $sorted->first();
                $parent['status'] = ($docNo !== 'N/A') ? 'Latest' : 'Active';

                // Children = older revisions
                $children = $sorted->slice(1)->values();
                $children = $children->map(function ($child) {
                    $child['status'] = 'Obsolete';
                    return $child;
                });

                $groups->push([
                    'doc_no'         => $docNo,
                    'parent'         => $parent,
                    'children'       => $children,
                    'has_revisions'  => $sorted->count() > 1,
                    'revision_count' => $sorted->count(),
                ]);
            }

            // Sort groups: most recently registered at the top
            $groups = $groups->sortByDesc(function ($g) {
                return $g['parent']['request_id'] ?? 0;
            })->values();

            // Paginate groups
            $totalGroups    = $groups->count();
            $currentPage    = max(1, (int) $request->input('page', 1));
            $lastPage        = max(1, (int) ceil($totalGroups / $perPage));
            $currentPage     = min($currentPage, $lastPage);
            $paginatedGroups = $groups->slice(($currentPage - 1) * $perPage, $perPage)->values();

            return response()->json([
                'data'         => $paginatedGroups,
                'total'        => $totalGroups,
                'current_page' => $currentPage,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
            ]);

        } catch (\Exception $e) {
            $refId = uniqid('err_');
            \Log::error("Database data error [{$refId}]: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'error'        => "An error occurred (ref: {$refId})",
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
        return redirect()->route('database.index')->with('info', 'Export feature coming soon.');
    }
}