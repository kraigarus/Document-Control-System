<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestForm;
use App\Models\DrfOffice;
use App\Models\DocumentChangeNotice;
use App\Models\DocRevision;
use App\Models\MasterlistRegistration;
use App\Models\MasterlistSourceOffice;
use App\Models\DocumentRetrieval;
use App\Models\RetrievalOffice;
use App\Models\DocumentDistribution;
use App\Models\DistributionOffice;
use App\Models\ApprovalRecord;
use App\Models\Syllabi;
use App\Services\StampBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Pdf;
use thiagoalessio\TesseractOCR\TesseractOCR;

class RegisterController extends Controller
{
    // ──────────────────────────────────────────────────────────
    // SHARED HELPERS
    // ──────────────────────────────────────────────────────────
    /**
     * Safely delete a file from the public disk.
     */
    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Find matching registration for a document number + type + optional sub-type.
     *
     * NOTE: every table's own primary key is now the default Eloquent `id`.
     * `request_id` is only a *foreign key column name* on child tables (masterlist,
     * DRF, DCN, etc.) that points at dcs_document_requests.id — DocumentRequest
     * itself has no `request_id` attribute, only `id`.
     *
     * @return array{found: bool, reason?: string, latest?: MasterlistRegistration, existing?: DocumentRequest}
     */
    private function findMatchingRegistration(string $docNo, int $docTypeId, ?int $subTypeId): array
    {
        $allMl = MasterlistRegistration::where('doc_no', $docNo)->get();

        if ($allMl->isEmpty()) {
            return ['found' => false, 'reason' => 'not_registered'];
        }

        // These are dcs_document_requests.id values (the FK column on masterlist rows).
        $requestIds         = $allMl->pluck('request_id')->unique();
        $relatedDocRequests = DocumentRequest::whereIn('id', $requestIds)->get();
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
            $matchingIds = $matching->pluck('id');
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

        if (!$existingDr) {
            return ['found' => false, 'reason' => 'not_registered'];
        }

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

    public function apiSearchDocuments(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (mb_strlen($q) < 1) return response()->json([]);

        $visibleIds = $this->getLatestRevisionIds(); // only current/latest revisions are linkable

        $results = MasterlistRegistration::whereIn('request_id', $visibleIds)
            ->whereNotNull('doc_no')
            ->where(function ($qr) use ($q) {
                $qr->where('doc_title', 'like', "%{$q}%")
                ->orWhere('doc_no', 'like', "%{$q}%");
            })
            ->when($request->filled('exclude_request_id'), function ($qr) use ($request) {
                $qr->where('request_id', '!=', $request->exclude_request_id);
            })
            ->orderBy('doc_title')
            ->limit(15)
            ->get(['id', 'request_id', 'doc_no', 'doc_title', 'revise_no', 'effectivity_date', 'brief_purpose', 'scanned_masterlist']);

        return response()->json($results->map(fn ($m) => [
            'masterlist_id'     => $m->id,
            'request_id'        => $m->request_id,
            'doc_no'            => $m->doc_no,
            'doc_title'         => $m->doc_title,
            'revise_no'         => $m->revise_no,
            'effectivity_date'  => $m->effectivity_date ? \Carbon\Carbon::parse($m->effectivity_date)->format('Y-m-d') : null,
            'brief_purpose'     => $m->brief_purpose,
            'scanned_copy_url'  => $m->scanned_masterlist ? \Storage::disk('public')->url($m->scanned_masterlist) : null,
            'label'             => $m->doc_title . ($m->doc_no ? ' (' . $m->doc_no . ')' : ''),
        ]));
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

    private function getLatestRevisionIds(): \Illuminate\Support\Collection
    {
        return app(\App\Services\DocumentVisibilityService::class)->getVisibleRequestIds();
    }

    /** IDs from the doc_types seeder: 11 = Syllabi, 12 = TOS/Rubrics — both share the same wizard/table. */
    private const SYLLABI_LIKE_SUBTYPE_IDS = [11, 12];

    private function isSyllabiLikeSubType(?\App\Models\DocType $subType): bool
    {
        if (!$subType) return false;
        return in_array((int) $subType->id, self::SYLLABI_LIKE_SUBTYPE_IDS, true);
    }

    /**
     * Shared syllabi / TOS-Rubrics validation for store and update.
     *
     * @return \Illuminate\Http\RedirectResponse|null
     */
    private function validateSyllabiLikeRequest(Request $request)
    {
        $subType   = \App\Models\DocType::find($request->sub_type_id);
        $isSyllabi = $this->isSyllabiLikeSubType($subType);

        if (!$isSyllabi) {
            return null;
        }

        if ($request->has('syllabiCourseName')) {
            $courseNames = $request->syllabiCourseName;
            $copiesArr   = $request->syllabiCopies ?? [];
            $total       = count($courseNames);
            $i           = 0;

            while ($i < $total) {
                $courseName = $courseNames[$i];
                $copies     = max(1, (int) ($copiesArr[$i] ?? 1));
                $courseLabel = $courseName ?: ('Course group starting row ' . ($i + 1));

                if (empty($courseName)) {
                    $i += $copies;
                    continue;
                }

                if (empty($request->syllabiNoPages[$i]) || $request->syllabiNoPages[$i] <= 0) {
                    return back()->withInput()->with('error', "Syllabi \"{$courseLabel}\": No. of Pages must be greater than 0.");
                }

                for ($c = 0; $c < $copies; $c++) {
                    $rowIdx  = $i + $c;
                    if ($rowIdx >= $total) {
                        break;
                    }
                    $copyNum  = $c + 1;
                    $rowLabel = "Syllabi \"{$courseLabel}\" (Copy {$copyNum})";

                    $drfAvailArr = $request->syllabiDrfAvailability ?? [];
                    $isDrfAvailable = ($drfAvailArr[$rowIdx] ?? 'not available') === 'available';

                    if ($isDrfAvailable) {
                        if (empty($request->syllabiDrfNo[$rowIdx])) {
                            return back()->withInput()->with('error', "{$rowLabel}: DRF No. is required.");
                        }
                        if (empty($request->syllabiDrfDate[$rowIdx])) {
                            return back()->withInput()->with('error', "{$rowLabel}: DRF Date is required.");
                        }
                        if (empty($request->syllabiDrfReceived[$rowIdx])) {
                            return back()->withInput()->with('error', "{$rowLabel}: DRF Received Date is required.");
                        }
                    }

                    if ($copies > 1) {
                        $facultyCount = count(array_filter(array_map('trim', explode(',', $request->syllabiFaculty[$rowIdx] ?? ''))));
                        if ($facultyCount > 1) {
                            return back()->withInput()->with('error',
                                "{$rowLabel}: Only one faculty per row is allowed when copies are split across rows.");
                        }
                    }

                    if ($request->hasFile('syllabiScannedDrf') && isset($request->file('syllabiScannedDrf')[$rowIdx])) {
                        $file = $request->file('syllabiScannedDrf')[$rowIdx];
                        $ext = strtolower($file->getClientOriginalExtension());
                        if (!in_array($ext, ['pdf', 'docx'])) {
                            return back()->withInput()->with('error', "{$rowLabel}: Scanned DRF — only .pdf and .docx files are accepted.");
                        }
                        if ($file->getSize() > 10 * 1024 * 1024) {
                            return back()->withInput()->with('error', "{$rowLabel}: Scanned DRF — file size must not exceed 10MB.");
                        }
                    }
                }

                $i += $copies;
            }
        }

        $request->validate([
            'college_id'             => 'required|integer|exists:dcs_colleges,id',
            'program_id'             => 'required|integer|exists:dcs_programs,id',
            'semester_id'            => 'required|integer|exists:dcs_semesters,id',
            'school_year_id'         => 'required|integer|exists:dcs_school_years,id',
            'syllabiDocNo'           => 'required|string',
            'syllabiDocTitle'        => 'required|string',
            'syllabiEffectivityDate' => 'required|date',
            'syllabiDeadline'        => 'required|date',
        ]);

        return null;
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
            $subType   = \App\Models\DocType::find($request->input('sub_type_id'));
            $isSyllabi = $this->isSyllabiLikeSubType($subType);
            $docNo     = $isSyllabi
                ? $request->input('syllabiDocNo')
                : $request->input('masterlistDocNo');
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

            $existing    = $result['latest'];
            $requestedRev = (int) $request->input('masterlistRevisionNo');

            // Allow any revision number (including lower than latest) as long as it
            // doesn't already exist for this document + type combination.
            $matchingIds   = $result['matches']->pluck('id');
            $duplicateExists = MasterlistRegistration::whereIn('request_id', $matchingIds)
                ->where('doc_no', $docNo)
                ->where('revise_no', $requestedRev)
                ->exists();

            if ($duplicateExists) {
                return back()->withInput()
                    ->with('error', 'Revision ' . $requestedRev . ' for document "' . $docNo . '" already exists. Please use a different revision number.');
            }
        }

        // ── New mode validation ──
        if ($mode === 'new') {
            $request->validate([
                'doc_type_id'          => 'required|integer|exists:dcs_doc_types,id',
                'version_id'           => 'required|integer|exists:dcs_version_type,id',
                'approval_status'      => 'required|in:applicable,not_applicable',
                'masterlistRevisionNo' => 'nullable|integer|min:0',
            ]);

        }

        // ── Common validation ──
        $request->validate([
            'doc_type_id'     => 'required|integer|exists:dcs_doc_types,id',
            'version_id'      => 'required|integer|exists:dcs_version_type,id',
            'approval_status' => 'required|in:applicable,not_applicable',
            'drfFile' => 'nullable|file|mimes:pdf,docx|max:10240',
            'dcnFile' => 'nullable|file|mimes:pdf,docx|max:10240',
            'uploadScannedCopy' => 'nullable|file|mimes:pdf,docx|max:10240',
            'scannedRet' => 'nullable|file|mimes:pdf,docx|max:10240',
            'scanneddist' => 'nullable|file|mimes:pdf,docx|max:10240'
        ]);

        $subType   = \App\Models\DocType::find($request->sub_type_id);
        $isSyllabi = $this->isSyllabiLikeSubType($subType);

        if ($redirect = $this->validateSyllabiLikeRequest($request)) {
            return $redirect;
        }

        // ── Prevent duplicate registration (new mode only) ──
        if ($mode === 'new') {
            $docNo     = $isSyllabi ? $request->input('syllabiDocNo') : $request->input('masterlistDocNo');
            $docTypeId = (int) $request->input('doc_type_id');
            $subTypeId = $request->input('sub_type_id');

            if ($docNo) {
                $result = $this->findMatchingRegistration($docNo, $docTypeId, $subTypeId);

                if ($result['found']) {
                    $existing = $result['latest'];
                    return back()->withInput()
                        ->with('error', 'Document "' . $docNo . '" is already registered (Rev ' . $existing->revise_no . '). Please use Revised Registration to create a new revision.');
                }
            }
        }

        DB::beginTransaction();

        $uploadedFiles = [];
        $filesToDelete = [];

        try {
            // ── 1. Create the master Document Request ──
            $docRequest = DocumentRequest::create([
                'version_id'      => $request->version_id,
                'doc_type_id'     => $request->doc_type_id,
                'sub_type_id'     => $request->sub_type_id,
                'approval_status' => $request->approval_status,
                'created_by'      => auth()->id(),
            ]);

            $requestId = $docRequest->id;
            $docTypeId = $request->doc_type_id;
            $versionId = $request->version_id;

            // ── 2. DRF (Section 1) ──
            if ($request->filled('drfNo')) {
                $drfFile = null;
                if ($request->hasFile('drfFile')) {
                    $drfFile = $request->file('drfFile')->store('scans/drf', 'public');
                    $uploadedFiles[] = $drfFile;
                }

                $drfOfficeIds = array_values(array_filter($request->input('drfSourceUnit', [])));

                // NOTE: dcs_document_request_form has no office_id column — offices
                // for a DRF are tracked only through the dcs_drf_offices pivot below.
                $drf = DocumentRequestForm::create([
                    'checklist_id'     => 1,
                    'version_id'       => $versionId,
                    'request_id'       => $requestId,
                    'doc_type_id'      => $docTypeId,
                    'drf_no'           => $request->drfNo,
                    'drf_date'         => $request->drfDate,
                    'drf_receipt_date' => $request->drfReceiptDate,
                    'drf_receipt_time' => $request->drfTime,
                    'doc_title'        => $request->drfTitle,
                    'scanned_drf'      => $drfFile,
                    'created_by'       => auth()->id(),
                ]);

                foreach ($drfOfficeIds as $officeId) {
                    DrfOffice::create([
                        'document_request_form_id' => $drf->id,
                        'office_id'                => $officeId,
                    ]);
                }
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
                            'dcn_id'           => $dcn->id,
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
            if (!$isSyllabi && $request->filled('masterlistDocNo')) {
                $masterlistFile = null;
                if ($request->hasFile('uploadScannedCopy')) {
                    $masterlistFile = $request->file('uploadScannedCopy')->store('scans/masterlist', 'public');
                    $uploadedFiles[] = $masterlistFile;
                }

                $masterlistTimeSpent = null;
                if ($request->filled('masterlistTimeSpent') && is_numeric($request->masterlistTimeSpent) && $request->masterlistTimeSpent >= 0) {
                    $masterlistTimeSpent = intval($request->masterlistTimeSpent);   // ← just the raw minutes
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
                    'originator_name'     => $request->masterlistOriginator,
                    'deadline'            => $request->deadlineOfSubmission,
                    'brief_purpose'       => $request->briefPurpose,
                    'scanned_masterlist'  => $masterlistFile,
                    'created_by'          => auth()->id(),
                ]);

                // ── Origins (offices only — see saveOriginsFromArrays note) ──
                $this->saveOriginsFromArrays(
                    $masterlist,
                    $request->input('masterlistOfficeIds', [])
                );

                $relatedIds = array_filter(array_map('intval', $request->input('relatedDocumentIds', [])));
                $this->saveRelatedDocuments($masterlist, $relatedIds);
            }

            // ── Syllabi (inside transaction — just data operations) ──
            if ($isSyllabi) {
                $totalPages = 0;
                if ($request->has('syllabiNoPages')) {
                    $totalPages = array_sum(
                        array_filter($request->syllabiNoPages, fn($p) => is_numeric($p) && $p > 0)
                    );
                }

                $masterlist     = MasterlistRegistration::where('request_id', $requestId)->first();
                $masterlistFile = $masterlist ? $masterlist->scanned_masterlist : null;

                if ($request->hasFile('uploadScannedCopy')) {
                    if ($masterlistFile) $filesToDelete[] = $masterlistFile;
                    $masterlistFile = $request->file('uploadScannedCopy')->store('scans/masterlist', 'public');
                    $uploadedFiles[] = $masterlistFile;
                }

                $masterlistTimeSpent = null;
                if ($request->filled('masterlistTimeSpent') && is_numeric($request->masterlistTimeSpent) && $request->masterlistTimeSpent >= 0) {
                    $masterlistTimeSpent = intval($request->masterlistTimeSpent);
                }

                $masterlistData = [
                    'checklist_id'        => 3,
                    'version_id'          => $versionId,
                    'doc_type_id'         => $docTypeId,
                    'doc_no'              => $request->syllabiDocNo,
                    'doc_title'           => $request->syllabiDocTitle,
                    'doc_receipt_date'    => $request->masterlistReceiptDate,
                    'doc_receipt_time'    => $request->masterlistReceiptTime,
                    'doc_registered_date' => $request->masterlistRegisteredDate,
                    'doc_registered_time' => $request->masterlistRegisteredTime,
                    'time_spent'          => $masterlistTimeSpent,
                    'effectivity_date'    => $request->syllabiEffectivityDate,
                    'deadline'            => $request->syllabiDeadline,
                    'revise_no'           => $request->masterlistRevisionNo ?? 0,
                    'no_pages'            => $totalPages,
                    'originator_name'     => $request->masterlistOriginator,
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

                MasterlistSourceOffice::where('masterlist_id', $masterlist->id)->delete();
                $this->saveOriginsFromArrays(
                    $masterlist,
                    $request->input('masterlistOfficeIds', [])
                );

                $relatedIds = array_filter(array_map('intval', $request->input('relatedDocumentIds', [])));
                $this->saveRelatedDocuments($masterlist, $relatedIds);

                $this->saveSyllabiRows($requestId, $versionId, $docTypeId, $request, $uploadedFiles);
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
                    $retrievalTimeSpent = intval($request->retrievalTimeSpent);
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
                            'retrieval_id' => $retrieval->id,
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
                    $distTimeSpent = intval($request->distributionTimeSpent);
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
                            'distribution_id' => $distribution->id,
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

            foreach ($filesToDelete as $file) {
                $this->deleteFile($file);
            }

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

    /** Save Source Unit origins for a Masterlist registration (defined offices only). */
    private function saveOriginsFromArrays(MasterlistRegistration $masterlist, array $officeIds): void
    {
        foreach ($officeIds as $officeId) {
            $id = (int) trim((string) $officeId);
            if ($id <= 0) continue;
            MasterlistSourceOffice::create([
                'masterlist_id' => $masterlist->id,
                'office_id'     => $id,
            ]);
        }
    }

    public function apiColleges()
    {
        return response()->json(
            \App\Models\College::orderBy('college_name')->get()->map(fn ($c) => [
                'college_id'   => $c->id,
                'college_name' => $c->college_name,
            ])
        );
    }

    public function apiPrograms($collegeId)
    {
        return response()->json(
            \App\Models\Program::where('college_id', $collegeId)
                ->orderBy('program_name')
                ->get(['id', 'program_name', 'program_code'])
                ->map(fn ($p) => [
                    'program_id'   => $p->id,
                    'program_name' => $p->program_name,
                    'program_code' => $p->program_code,
                ])
        );
    }

    public function apiSemesters()
    {
        return response()->json(
            \App\Models\Semester::all()->map(fn ($s) => [
                'semester_id'   => $s->id,
                'semester_name' => $s->semester_name,
            ])
        );
    }

    public function apiSchoolYears()
    {
        return response()->json(
            \App\Models\SchoolYear::orderBy('school_year')->get()->map(fn ($y) => [
                'school_year_id' => $y->id,
                'school_year'    => $y->school_year,
            ])
        );
    }

    public function apiOriginators()
    {
        return response()->json(
            \App\Models\Originator::orderBy('originator_name')->get()->map(fn ($o) => [
                'originator_id'   => $o->id,
                'originator_name' => $o->originator_name,
            ])
        );
    }

    public function apiFaculties(Request $request)
    {
        $query = \App\Models\Faculty::orderBy('faculty_name');

        if ($request->filled('college_id')) {
            $query->where('college_id', (int) $request->input('college_id'));
        }

        return response()->json(
            $query->get(['id', 'faculty_name', 'college_id'])
        );
    }

    public function apiProgramCourses($programId, $semesterId)
    {
        return response()->json(
            \App\Models\ProgramCourse::where('program_id', $programId)
                ->where('semester_id', $semesterId)
                ->orderBy('course_name')
                ->get(['id', 'course_name'])
        );
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
                    $result['matches']->pluck('id')
                )->where('doc_no', $docNo)->orderByDesc('revise_no')->get();

                $latestDistribution = DocumentDistribution::where('request_id', $latest->request_id)->first();
                $latestDistributionOffices = [];
                if ($latestDistribution) {
                    $latestDistributionOffices = DistributionOffice::where('distribution_id', $latestDistribution->id)
                        ->with('office')
                        ->get()
                        ->map(fn ($o) => [
                            'office_id'   => $o->office_id,
                            'office_name' => $o->office->office_name ?? 'Unknown Office',
                            'copies'      => $o->copies ?? 1,
                        ])
                        ->values();
                }

                // ── Pull everything else from the latest Masterlist row so the
                // whole section can be pre-filled for the new revision ──
                $sourceOffices = MasterlistSourceOffice::where('masterlist_id', $latest->id)->with('office')->get();
                $latestSourceUnit = $sourceOffices->map(fn ($o) => $o->office->office_name ?? null)
                    ->filter()->implode(', ');

                $latestRelatedDocs = $latest->relatedDocuments()
                    ->get(['dcs_masterlist_registration.id', 'doc_no', 'doc_title'])
                    ->map(fn ($d) => [
                        'masterlist_id' => $d->id,
                        'doc_no'        => $d->doc_no,
                        'doc_title'     => $d->doc_title,
                    ])
                    ->values();

                return response()->json([
                    'exists'                       => true,
                    'message'                      => 'Document found.',
                    'next_rev'                     => $latestRev + 1,
                    'latest_rev'                   => $latestRev,
                    'latest_title'                 => $latest->doc_title,
                    'latest_originator'            => $latest->originator_name,
                    'revision_count'               => $registrations->count(),
                    'latest_distribution_offices'  => $latestDistributionOffices,

                    'latest_source_unit'           => $latestSourceUnit,
                    'latest_effectivity_date'      => $latest->effectivity_date ? \Carbon\Carbon::parse($latest->effectivity_date)->format('Y-m-d') : null,
                    'latest_no_pages'              => $latest->no_pages,
                    'latest_deadline'              => $latest->deadline ? \Carbon\Carbon::parse($latest->deadline)->format('Y-m-d') : null,
                    'latest_brief_purpose'         => $latest->brief_purpose,
                    'latest_related_documents'     => $latestRelatedDocs,
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
            ->whereIn('id', $visibleIds)
            ->orderBy('id', 'desc');

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
                ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('doc_type_id')) {
            $query->where('doc_type_id', $request->doc_type_id);
        }

        $documents = $query->paginate(10)->withQueryString();
        $docTypes  = \App\Models\DocType::whereNull('parent_id')->orderBy('id')->get();

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
                'documentRequestForm.drfOffices.office',
                'masterlistRegistration',
                'documentChangeNotice',
                'documentRetrieval',
                'documentDistribution',
            ])
            ->whereIn('id', $visibleIds)
            ->orderBy('id', 'desc');

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
                    ->orWhere('id', 'like', "%{$search}%");
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
                $checklistNames = \App\Models\ChecklistType::pluck('checklist_name', 'id')->toArray();
            } catch (\Exception $e) {
                $checklistNames = [
                    1 => 'Document Request Form',
                    2 => 'Document Change Notice',
                    3 => 'Masterlist Registration',
                    4 => 'Document Retrieval',
                    5 => 'Document Distribution',
                ];
            }

            // NOTE: Syllabi DRF data no longer lives in dcs_document_request_form
            // (it moved to dcs_syllabi_drf), so there's no longer a need to exclude
            // "syllabi DRFs" from the Section 1 DRF shown in the listing.
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
                    'request_id'  => $doc->id,
                    'doc_no'      => $docNo ?? 'N/A',
                    'title'       => $title,
                    'rev_no'      => $revNo,
                    'doc_type'    => $doc->docType->doc_type_name ?? 'N/A',
                    'checklists'  => $checklists,
                    'edit_url'    => route('register.edit', $doc->id),
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
                ->pluck('id');

            $latestRev = MasterlistRegistration::where('doc_no', $ml->doc_no)
                ->whereIn('request_id', $sameTypeRequestIds)
                ->max('revise_no');

            if ((int) $ml->revise_no < (int) $latestRev) {
                return redirect()->route('register.update')
                    ->with('error', "Only the latest revision (Rev {$latestRev}) can be edited.");
            }
        }

        $drf        = $this->getSection1Drf($id);
        $drfOffices = $drf ? DrfOffice::where('document_request_form_id', $drf->id)->with('office')->get() : collect();
        $dcn                 = DocumentChangeNotice::where('request_id', $id)->first();
        $revisions           = $dcn ? DocRevision::where('dcn_id', $dcn->id)->get() : collect();
        $masterlist          = $ml;
        $retrieval           = DocumentRetrieval::where('request_id', $id)->first();
        $retrievalOffices    = $retrieval ? RetrievalOffice::where('retrieval_id', $retrieval->id)->get() : collect();
        $distribution        = DocumentDistribution::where('request_id', $id)->first();
        $distributionOffices = $distribution ? DistributionOffice::where('distribution_id', $distribution->id)->get() : collect();
        $approval            = ApprovalRecord::where('request_id', $id)->first();
        $syllabi             = Syllabi::with(['drfs', 'course'])->where('request_id', $id)->orderBy('id')->get();

        $sourceOffices = collect();
        $masterlistSourceUnit = '';
        if ($masterlist) {
            $sourceOffices = MasterlistSourceOffice::where('masterlist_id', $masterlist->id)->with('office')->get();
            $masterlistSourceUnit = $sourceOffices
                ->map(fn ($o) => $o->office?->office_name)
                ->filter()
                ->implode(', ');
                    }

        $offices        = \App\Models\Office::orderBy('office_name')->get();
        $docTypes       = \App\Models\DocType::orderBy('id')->get();
        $versionTypes   = \App\Models\VersionType::all();
        $approvalBodies = \App\Models\ApprovalBody::all();

        return view('pages.dcs.create-update.edit', compact(
            'docRequest', 'drf', 'drfOffices', 'dcn', 'revisions', 'masterlist',
            'retrieval', 'retrievalOffices', 'distribution',
            'distributionOffices', 'approval', 'syllabi',
            'offices', 'docTypes', 'versionTypes', 'approvalBodies', 'masterlistSourceUnit', 'sourceOffices'
        ));
    }

    /**
     * Section 1 DRF for a document request. Syllabi documents no longer create a
     * dcs_document_request_form row at all (their DRF data lives per-copy in
     * dcs_syllabi_drf), so there's nothing left to exclude here.
     */
    private function getSection1Drf(int $requestId): ?DocumentRequestForm
    {
        return DocumentRequestForm::where('request_id', $requestId)->first();
    }

    /**
     * Validate and save Syllabi rows for a document request.
     *
     * Schema: dcs_syllabi holds one row per COURSE (college/program/semester/
     * school_year/course_id/is_available/no_copies/no_pages/date_received/
     * time_received). Per-copy faculty + DRF details live in dcs_syllabi_drf,
     * one row per copy (or per faculty name, when a shared single-copy syllabus
     * lists more than one faculty).
     *
     * The front-end submits one array *entry per row* (one row = one copy), with
     * course-level fields (name/availability/copies/pages) mirrored identically
     * across every row belonging to the same course group. Since each group's
     * first row's "Copies" value tells us exactly how many contiguous rows
     * belong to that group, we can walk the arrays and re-derive the grouping.
     *
     * @throws \Exception on file validation failure (caller should wrap in transaction)
     */
    private function saveSyllabiRows(
        int $requestId,
        int $versionId,
        int $docTypeId,
        Request $request,
        array &$uploadedFiles
    ): void {
        if (!$request->has('syllabiCourseName')) {
            return;
        }

        $courseNames  = $request->syllabiCourseName;
        $availability = $request->syllabiAvailability ?? [];
        $copiesArr    = $request->syllabiCopies ?? [];
        $pagesArr     = $request->syllabiNoPages ?? [];
        $dateReceived = $request->syllabiDateReceived ?? [];
        $timeReceived = $request->syllabiTimeReceived ?? [];
        $facultyArr   = $request->syllabiFaculty ?? [];
        $drfAvailArr  = $request->syllabiDrfAvailability ?? [];
        $drfNoArr     = $request->syllabiDrfNo ?? [];
        $drfDateArr   = $request->syllabiDrfDate ?? [];
        $drfRecvArr   = $request->syllabiDrfReceived ?? [];
        $existingScanned = $request->input('syllabiExistingScannedDrf', []);

        $total = count($courseNames);
        $i = 0;

        while ($i < $total) {
            $courseName = $courseNames[$i];
            $copies     = max(1, (int) ($copiesArr[$i] ?? 1));

            if (empty($courseName)) {
                $i += $copies;
                continue;
            }

            // Find/create the ProgramCourse this row refers to (covers both
            // courses picked from the auto-populated list and manually typed ones).
            $course = \App\Models\ProgramCourse::firstOrCreate([
                'program_id'  => $request->program_id,
                'semester_id' => $request->semester_id,
                'course_name' => $courseName,
            ]);

            $syllabi = Syllabi::create([
                'request_id'     => $requestId,
                'doc_type_id'    => $docTypeId,
                'college_id'     => $request->college_id,
                'program_id'     => $request->program_id,
                'semester_id'    => $request->semester_id,
                'school_year_id' => $request->school_year_id,
                'course_id'      => $course->id,
                'is_available'   => ($availability[$i] ?? 'not available') === 'available',
                'no_copies'      => $copies,
                'no_pages'       => $pagesArr[$i] ?? null,
                'date_received'  => $dateReceived[$i] ?? null,
                'time_received'  => $timeReceived[$i] ?? null,
            ]);

            for ($c = 0; $c < $copies; $c++) {
                $rowIdx = $i + $c;
                if ($rowIdx >= $total) break;

                $scannedDrf = $this->storeOptionalFile(
                    $request, 'syllabiScannedDrf', $rowIdx, 'scans/syllabi-drf',
                    "Syllabi \"{$courseName}\" copy " . ($c + 1) . ": Scanned DRF"
                );
                if (!$scannedDrf && !empty($existingScanned[$rowIdx])) {
                    $scannedDrf = $existingScanned[$rowIdx];
                }
                if ($scannedDrf && $request->hasFile('syllabiScannedDrf') && isset($request->file('syllabiScannedDrf')[$rowIdx])) {
                    $uploadedFiles[] = $scannedDrf;
                }

                $facultyNames = array_filter(array_map('trim', explode(',', $facultyArr[$rowIdx] ?? '')));
                if (empty($facultyNames)) $facultyNames = [''];

                foreach ($facultyNames as $facultyName) {
                    $facultyId = null;
                    if ($facultyName !== '') {
                        $faculty   = \App\Models\Faculty::firstOrCreate(['faculty_name' => $facultyName]);
                        $facultyId = $faculty->id;
                    }

                    \App\Models\SyllabiDrf::create([
                        'syllabi_id'        => $syllabi->id,
                        'faculty_id'        => $facultyId,
                        'faculty_name'      => $facultyName,
                        'is_drf_available'  => ($drfAvailArr[$rowIdx] ?? 'not available') === 'available',
                        'drf_no'            => $drfNoArr[$rowIdx] ?? null,
                        'drf_date'          => $drfDateArr[$rowIdx] ?? null,
                        'drf_received_date' => $drfRecvArr[$rowIdx] ?? null,
                        'scanned_drf'       => $scannedDrf,
                    ]);
                }
            }

            $i += $copies;
        }
    }

    /**
     * Replace all syllabi rows on update — deletes stale rows/files, then re-saves from the form.
     */
    private function replaceSyllabiRows(
        int $requestId,
        int $versionId,
        int $docTypeId,
        Request $request,
        array &$uploadedFiles,
        array &$filesToDelete
    ): void {
        if (!$request->has('syllabiCourseName')) {
            return;
        }

        $keepPaths = array_values(array_filter($request->input('syllabiExistingScannedDrf', [])));

        $oldSyllabi = Syllabi::with('drfs')->where('request_id', $requestId)->get();
        foreach ($oldSyllabi as $old) {
            foreach ($old->drfs as $sd) {
                if ($sd->scanned_drf && !in_array($sd->scanned_drf, $keepPaths, true)) {
                    $filesToDelete[] = $sd->scanned_drf;
                }
            }
        }
        $oldSyllabi->each->delete();

        $this->saveSyllabiRows($requestId, $versionId, $docTypeId, $request, $uploadedFiles);
    }

    /**
     * Store an optional uploaded file with validation.
     * Returns the storage path or null if no file was provided.
     *
     * @throws \Exception if the file fails validation
     */
    private function storeOptionalFile(
        Request $request,
        string $inputName,
        int $index,
        string $directory,
        string $label
    ): ?string {
        if (!$request->hasFile($inputName) || !isset($request->file($inputName)[$index])) {
            return null;
        }

        $file = $request->file($inputName)[$index];
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['pdf', 'docx'])) {
            throw new \Exception("{$label}: only .pdf and .docx files are accepted.");
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \Exception("{$label}: file size must not exceed 10MB.");
        }

        return $file->store($directory, 'public');
    }

    private function saveRelatedDocuments(MasterlistRegistration $masterlist, array $relatedIds): void
    {
        // Remove ALL existing links touching this masterlist, in either direction
        \App\Models\MasterlistRelatedDoc::where('masterlist_id', $masterlist->id)
            ->orWhere('related_doc_id', $masterlist->id)
            ->delete();

        // Re-create forward-direction links for what's currently selected
        foreach ($relatedIds as $id) {
            if ((int) $id === (int) $masterlist->id) continue;
            \App\Models\MasterlistRelatedDoc::create([
                'masterlist_id'   => $masterlist->id,
                'related_doc_id'  => (int) $id,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────
    // UPDATE — Save changes
    // ──────────────────────────────────────────────────────────
    public function updateDoc(Request $request, $id)
    {
        if ($redirect = $this->validateSyllabiLikeRequest($request)) {
            return $redirect;
        }

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

            $requestId = $docRequest->id;
            $docTypeId = $request->doc_type_id;
            $versionId = $request->version_id;

            // ── Delete records for unchecked checklists ──
            $checkedChecklists = array_map('intval', $request->input('checklists', []));

            // DRF unchecked → delete (Section 1 only — Syllabi DRFs handled separately)
            if (!in_array(1, $checkedChecklists)) {
                $existingDrf = $this->getSection1Drf($requestId);
                if ($existingDrf) {
                    if ($existingDrf->scanned_drf) $filesToDelete[] = $existingDrf->scanned_drf;
                    DrfOffice::where('document_request_form_id', $existingDrf->id)->delete();
                    $existingDrf->delete();
                }
            }

            // DCN unchecked → delete
            if (!in_array(2, $checkedChecklists)) {
                $existingDcn = DocumentChangeNotice::where('request_id', $requestId)->first();
                if ($existingDcn) {
                    if ($existingDcn->scanned_dcn) $filesToDelete[] = $existingDcn->scanned_dcn;
                    $oldRevisions = DocRevision::where('dcn_id', $existingDcn->id)->get();
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
                    MasterlistSourceOffice::where('masterlist_id', $existingMl->id)->delete();
                    $existingMl->relatedDocuments()->detach();
                    $existingMl->delete();
                }

                // Also clean up any Syllabi records and their DRF rows
                $oldSyllabi = Syllabi::with('drfs')->where('request_id', $requestId)->get();
                foreach ($oldSyllabi as $old) {
                    foreach ($old->drfs as $sd) {
                        if ($sd->scanned_drf) $filesToDelete[] = $sd->scanned_drf;
                    }
                }
                $oldSyllabi->each->delete(); // dcs_syllabi_drf rows cascade-delete via FK
            }

            // Retrieval unchecked → delete
            if (!in_array(4, $checkedChecklists)) {
                $existingRet = DocumentRetrieval::where('request_id', $requestId)->first();
                if ($existingRet) {
                    if ($existingRet->scanned_retrieval) $filesToDelete[] = $existingRet->scanned_retrieval;
                    RetrievalOffice::where('retrieval_id', $existingRet->id)->delete();
                    $existingRet->delete();
                }
            }

            // Distribution unchecked → delete
            if (!in_array(5, $checkedChecklists)) {
                $existingDist = DocumentDistribution::where('request_id', $requestId)->first();
                if ($existingDist) {
                    if ($existingDist->scanned_distribution) $filesToDelete[] = $existingDist->scanned_distribution;
                    DistributionOffice::where('distribution_id', $existingDist->id)->delete();
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
                $drf     = $this->getSection1Drf($requestId);
                $drfFile = $drf ? $drf->scanned_drf : null;

                if ($request->hasFile('drfFile')) {
                    StampBackupService::invalidate($requestId, 'drf');
                    if ($drfFile) $filesToDelete[] = $drfFile;
                    $drfFile = $request->file('drfFile')->store('scans/drf', 'public');
                    $uploadedFiles[] = $drfFile;
                }

                $drfOfficeIds = array_values(array_filter($request->input('drfSourceUnit', [])));

                // NOTE: no office_id column on dcs_document_request_form (see store()).
                $drfData = [
                    'checklist_id'     => 1,
                    'version_id'       => $versionId,
                    'doc_type_id'      => $docTypeId,
                    'drf_no'           => $request->drfNo,
                    'drf_date'         => $request->drfDate,
                    'drf_receipt_date' => $request->drfReceiptDate,
                    'drf_receipt_time' => $request->drfTime,
                    'doc_title'        => $request->drfTitle,
                    'scanned_drf'      => $drfFile,
                ];

                if ($drf) {
                    $drf->update($drfData);
                } else {
                    $drf = DocumentRequestForm::create(array_merge($drfData, [
                        'request_id' => $requestId,
                        'created_by' => auth()->id(),
                    ]));
                }

                DrfOffice::where('document_request_form_id', $drf->id)->delete();
                foreach ($drfOfficeIds as $officeId) {
                    DrfOffice::create([
                        'document_request_form_id' => $drf->id,
                        'office_id'                => $officeId,
                    ]);
                }
            }

            // ── 3. DCN ──
            if ($request->filled('dcnNumber')) {
                $dcn     = DocumentChangeNotice::where('request_id', $requestId)->first();
                $dcnFile = $dcn ? $dcn->scanned_dcn : null;

                if ($request->hasFile('dcnFile')) {
                    StampBackupService::invalidate($requestId, 'dcn');
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
                $oldRevisions = DocRevision::where('dcn_id', $dcn->id)->get();
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
                            'dcn_id'           => $dcn->id,
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
            if (in_array(3, $checkedChecklists, true) && $request->filled('masterlistDocNo')) {
                $masterlist     = MasterlistRegistration::where('request_id', $requestId)->first();
                $masterlistFile = $masterlist ? $masterlist->scanned_masterlist : null;

                if ($request->hasFile('uploadScannedCopy')) {
                    StampBackupService::invalidate($requestId, 'masterlist');
                    if ($masterlistFile) $filesToDelete[] = $masterlistFile;
                    $masterlistFile = $request->file('uploadScannedCopy')->store('scans/masterlist', 'public');
                    $uploadedFiles[] = $masterlistFile;
                }

                $masterlistTimeSpent = null;
                if ($request->filled('masterlistTimeSpent') && is_numeric($request->masterlistTimeSpent) && $request->masterlistTimeSpent >= 0) {
                    $masterlistTimeSpent = intval($request->masterlistTimeSpent);   // ← just the raw minutes
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
                    'originator_name'     => $request->masterlistOriginator,
                    'deadline'            => $request->deadlineOfSubmission,
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

                MasterlistSourceOffice::where('masterlist_id', $masterlist->id)->delete();
                $this->saveOriginsFromArrays(
                    $masterlist,
                    $request->input('masterlistOfficeIds', [])
                );

                $relatedIds = array_filter(array_map('intval', $request->input('relatedDocumentIds', [])));
                $this->saveRelatedDocuments($masterlist, $relatedIds);
            }

            // ── Syllabi ──
            $subType   = \App\Models\DocType::find($request->sub_type_id);
            $isSyllabi = $this->isSyllabiLikeSubType($subType);

            // ── Clean up Syllabi if sub_type changed away from Syllabi ──
            if (!$isSyllabi) {
                $oldSyllabi = Syllabi::with('drfs')->where('request_id', $requestId)->get();
                foreach ($oldSyllabi as $old) {
                    foreach ($old->drfs as $sd) {
                        if ($sd->scanned_drf) $filesToDelete[] = $sd->scanned_drf;
                    }
                }
                $oldSyllabi->each->delete();
            }

            if ($isSyllabi && in_array(3, $checkedChecklists, true)) {
                $totalPages = 0;
                if ($request->has('syllabiNoPages')) {
                    $totalPages = array_sum(
                        array_filter($request->syllabiNoPages, fn($p) => is_numeric($p) && $p > 0)
                    );
                }

                $masterlist     = MasterlistRegistration::where('request_id', $requestId)->first();
                $masterlistFile = $masterlist ? $masterlist->scanned_masterlist : null;

                if ($request->hasFile('uploadScannedCopy')) {
                    StampBackupService::invalidate($requestId, 'masterlist');
                    if ($masterlistFile) $filesToDelete[] = $masterlistFile;
                    $masterlistFile = $request->file('uploadScannedCopy')->store('scans/masterlist', 'public');
                    $uploadedFiles[] = $masterlistFile;
                }

                $masterlistTimeSpent = null;
                if ($request->filled('masterlistTimeSpent') && is_numeric($request->masterlistTimeSpent) && $request->masterlistTimeSpent >= 0) {
                    $masterlistTimeSpent = intval($request->masterlistTimeSpent);
                }

                $masterlistData = [
                    'checklist_id'        => 3,
                    'version_id'          => $versionId,
                    'doc_type_id'         => $docTypeId,
                    'doc_no'              => $request->syllabiDocNo,
                    'doc_title'           => $request->syllabiDocTitle,
                    'doc_receipt_date'    => $request->masterlistReceiptDate,
                    'doc_receipt_time'    => $request->masterlistReceiptTime,
                    'doc_registered_date' => $request->masterlistRegisteredDate,
                    'doc_registered_time' => $request->masterlistRegisteredTime,
                    'time_spent'          => $masterlistTimeSpent,
                    'effectivity_date'    => $request->syllabiEffectivityDate,
                    'deadline'            => $request->syllabiDeadline,
                    'revise_no'           => $request->masterlistRevisionNo ?? 0,
                    'no_pages'            => $totalPages,
                    'originator_name'     => $request->masterlistOriginator,
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

                MasterlistSourceOffice::where('masterlist_id', $masterlist->id)->delete();
                $this->saveOriginsFromArrays(
                    $masterlist,
                    $request->input('masterlistOfficeIds', [])
                );

                $relatedIds = array_filter(array_map('intval', $request->input('relatedDocumentIds', [])));
                $this->saveRelatedDocuments($masterlist, $relatedIds);

                $this->replaceSyllabiRows($requestId, $versionId, $docTypeId, $request, $uploadedFiles, $filesToDelete);
            }

            // ── 5. Retrieval ──
            if ($request->filled('retrievalDate')) {
                $retrieval     = DocumentRetrieval::where('request_id', $requestId)->first();
                $retrievalFile = $retrieval ? $retrieval->scanned_retrieval : null;

                if ($request->hasFile('scannedRet')) {
                    StampBackupService::invalidate($requestId, 'retrieval');
                    if ($retrievalFile) $filesToDelete[] = $retrievalFile;
                    $retrievalFile = $request->file('scannedRet')->store('scans/retrieval', 'public');
                    $uploadedFiles[] = $retrievalFile;
                }

                $retrievalTimeSpent = null;
                if ($request->filled('retrievalTimeSpent') && is_numeric($request->retrievalTimeSpent) && $request->retrievalTimeSpent >= 0) {
                    $retrievalTimeSpent = intval($request->retrievalTimeSpent);
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
                RetrievalOffice::where('retrieval_id', $retrieval->id)->delete();
                if ($request->has('retrievalOffice')) {
                    foreach ($request->retrievalOffice as $i => $officeId) {
                        RetrievalOffice::create([
                            'retrieval_id' => $retrieval->id,
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
                    StampBackupService::invalidate($requestId, 'distribution');
                    if ($distFile) $filesToDelete[] = $distFile;
                    $distFile = $request->file('scanneddist')->store('scans/distribution', 'public');
                    $uploadedFiles[] = $distFile;
                }

                $distTimeSpent = null;
                if ($request->filled('distributionTimeSpent') && is_numeric($request->distributionTimeSpent) && $request->distributionTimeSpent >= 0) {
                    $distTimeSpent = intval($request->distributionTimeSpent);
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
                DistributionOffice::where('distribution_id', $distribution->id)->delete();
                if ($request->has('distOffice')) {
                    foreach ($request->distOffice as $i => $officeId) {
                        DistributionOffice::create([
                            'distribution_id' => $distribution->id,
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
                ->pluck('id');

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
            $requestId = $docRequest->id;

            // DRF (Section 1 only — Syllabi DRFs are handled in the Syllabi block below)
            $drf = $this->getSection1Drf($requestId);
            if ($drf) {
                if ($drf->scanned_drf) $filesToDelete[] = $drf->scanned_drf;
                DrfOffice::where('document_request_form_id', $drf->id)->delete();
                $drf->delete();
            }

            // DCN + revisions
            $dcn = DocumentChangeNotice::where('request_id', $requestId)->first();
            if ($dcn) {
                if ($dcn->scanned_dcn) $filesToDelete[] = $dcn->scanned_dcn;
                $revisions = DocRevision::where('dcn_id', $dcn->id)->get();
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
                MasterlistSourceOffice::where('masterlist_id', $masterlist->id)->delete();
                \App\Models\MasterlistRelatedDoc::where('masterlist_id', $masterlist->id)
                    ->orWhere('related_doc_id', $masterlist->id)
                    ->delete();
                $masterlist->delete();
            }

            // Syllabi (+ per-copy DRF rows, cascade-deleted via FK)
            $syllabiRecords = Syllabi::with('drfs')->where('request_id', $requestId)->get();
            foreach ($syllabiRecords as $syl) {
                foreach ($syl->drfs as $sd) {
                    if ($sd->scanned_drf) $filesToDelete[] = $sd->scanned_drf;
                }
            }
            $syllabiRecords->each->delete();

            // Retrieval + offices
            $retrieval = DocumentRetrieval::where('request_id', $requestId)->first();
            if ($retrieval) {
                if ($retrieval->scanned_retrieval) $filesToDelete[] = $retrieval->scanned_retrieval;
                RetrievalOffice::where('retrieval_id', $retrieval->id)->delete();
                $retrieval->delete();
            }

            // Distribution + offices
            $distribution = DocumentDistribution::where('request_id', $requestId)->first();
            if ($distribution) {
                if ($distribution->scanned_distribution) $filesToDelete[] = $distribution->scanned_distribution;
                DistributionOffice::where('distribution_id', $distribution->id)->delete();
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


    /**
     * Label-based extraction tuned to your DRF form's own vocabulary.
     * We'll adjust these labels once we see a real scanned DRF's raw OCR text.
     */
    private function parseDrfFields(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);

        $get = function (string $label) use ($lines) {
            foreach ($lines as $line) {
                if (stripos($line, $label) !== false) {
                    // strip label + any trailing period/colon/whitespace
                    $parts = preg_split('/' . preg_quote($label, '/') . '[.:\s]*/i', $line, 2);
                    if (isset($parts[1]) && trim($parts[1]) !== '') {
                        return trim($parts[1]);
                    }
                }
            }
            return null;
        };

        return [
            'drfNo'    => $get('DRF No'),
            'drfDate'  => $get('DRF Date'),
            'drfTitle' => $get('Document Title'),
        ];
    }

    public function extractScan(Request $request)
    {
        $request->validate([
            'scan' => 'required|file|mimes:pdf|max:10240',
            'section' => 'required|string|in:drf',
        ]);

        $file = $request->file('scan');
        $tempPath = $file->store('temp/scans', 'local');
        $fullPath = Storage::disk('local')->path($tempPath);
        $imagePath = Storage::disk('local')->path('temp/scans/' . uniqid() . '.jpg');

        try {
            (new Pdf($fullPath))
                ->selectPage(1)
                ->save($imagePath);

            $rawText = (new TesseractOCR($imagePath))
                ->lang('eng')
                ->run();

            $fields = $this->parseDrfFields($rawText);

            return response()->json([
                'extracted' => true,
                'fields' => $fields,
                'raw_text_preview' => \Str::limit($rawText, 500),
            ]);

        } catch (\Exception $e) {
            \Log::warning('OCR extraction failed: ' . $e->getMessage());
            return response()->json(['extracted' => false, 'reason' => 'ocr_failed']);
        } finally {
            Storage::disk('local')->delete($tempPath);
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
    }
}