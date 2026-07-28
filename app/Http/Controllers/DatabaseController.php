<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\{DocumentRequest, DocType, Office, Originator, DocRevision, Syllabi};

class DatabaseController extends Controller
{
    // ──────────────────────────────────────────────────────────
    // INDEX — render the page
    // ──────────────────────────────────────────────────────────
    public function index()
    {
        $docTypes    = DocType::whereNull('parent_id')->get();
        $subTypes    = DocType::whereNotNull('parent_id')->orderBy('doc_type_name')->get();
        $offices     = Office::where('status', 'active')->orderBy('office_name')->get();
        $originators = Originator::orderBy('originator_name')->get();

        return view('pages.dcs.database.index', compact(
            'docTypes', 'subTypes', 'offices', 'originators'
        ));
    }

    // ──────────────────────────────────────────────────────────
    // DATA — JSON endpoint for the table
    // ──────────────────────────────────────────────────────────
    public function data(Request $request)
    {
        try {
            $perPage     = (int) $request->input('per_page', 20);
            $currentPage = max(1, (int) $request->input('page', 1));

            $query = DocumentRequest::with([
                'docType',
                'documentRequestForm',
                'documentRequestForm.drfOffices.office',
                'masterlistRegistration',
                'masterlistRegistration.sourceOffices.office',
                'masterlistRegistration.relatedDocuments',
                'masterlistRegistration.relatedToDocuments',
                'approvalRecords',
                'documentChangeNotice',
                'documentRetrieval',
                'documentRetrieval.offices',
                'documentDistribution',
                'documentDistribution.offices',
            ]);

            if ($request->input('doc_type_id') && $request->input('doc_type_id') !== 'all') {
                $query->where('doc_type_id', $request->input('doc_type_id'));
            }

            if ($request->filled('sub_type_id') && $request->input('sub_type_id') !== 'all') {
                $query->where('sub_type_id', $request->input('sub_type_id'));
            }

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

            if ($request->input('originator')) {
                $search = $request->input('originator');
                $query->whereHas('masterlistRegistration', function ($q) use ($search) {
                    $q->where('originator_name', $search);
                });
            }

            if ($request->filled('source_unit')) {
                $officeId = $request->input('source_unit');
                $query->whereHas('masterlistRegistration.sourceOffices', function ($q) use ($officeId) {
                    $q->where('office_id', $officeId);
                });
            }

            if ($request->filled('status')) {
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

            $allDocs = $query->get();

            $syllabiDrfIds = Syllabi::whereIn('request_id', $allDocs->pluck('request_id'))
                ->pluck('drf_id')->filter()->unique()->values();

            $allRows = $allDocs->map(function ($doc) use ($syllabiDrfIds) {
                $drf = null;
                if ($doc->documentRequestForm) {
                    $isSyllabiDrf = $syllabiDrfIds->contains($doc->documentRequestForm->drf_id);
                    $drf = $isSyllabiDrf ? null : $doc->documentRequestForm;
                }

                $ml   = $doc->masterlistRegistration;
                $appr = $doc->approvalRecords ? $doc->approvalRecords->first() : null;
                $dcn  = $doc->documentChangeNotice;
                $ret  = $doc->documentRetrieval;
                $dist = $doc->documentDistribution;

                $dcnPurpose = null;
                if ($dcn) {
                    $firstRev = DocRevision::where('dcn_id', $dcn->dcn_id)->first();
                    if ($firstRev) $dcnPurpose = $firstRev->brief_purpose;
                }

                $drfOfficesList = null;
                if ($drf && $drf->drfOffices) {
                    $drfOfficesList = $drf->drfOffices
                        ->map(fn ($o) => $o->office ? $o->office->office_name : null)
                        ->filter()->implode(', ') ?: null;
                }

                $retOffices = null;
                if ($ret && $ret->offices) {
                    $retOffices = $ret->offices
                        ->map(fn ($o) => $o->office ? $o->office->office_name : $o->office_name)
                        ->filter()->implode(', ') ?: null;
                }

                $distOffices = null;
                if ($dist && $dist->offices) {
                    $distOffices = $dist->offices
                        ->map(fn ($o) => $o->office ? $o->office->office_name : $o->office_name)
                        ->filter()->implode(', ') ?: null;
                }

                $sourceUnitName = null;
                if ($ml && $ml->sourceOffices->count() > 0) {
                    $sourceUnitName = $ml->sourceOffices
                        ->map(fn ($o) => $o->office ? $o->office->office_name : null)
                        ->filter()->implode(', ') ?: null;
                }

                $deadlineDiff = null;
                if ($ml && $ml->deadline && $ml->effectivity_date) {
                    $deadlineDiff = \Carbon\Carbon::parse($ml->effectivity_date)
                        ->diffInDays(\Carbon\Carbon::parse($ml->deadline));
                }

                return [
                    'request_id'       => $doc->request_id,
                    'doc_type_id'      => $doc->doc_type_id,
                    'sub_type_id'      => $doc->sub_type_id,
                    'doc_type_name'    => $doc->docType->doc_type_name ?? 'Uncategorized',
                    'doc_no'           => $ml ? $ml->doc_no : 'N/A',
                    'rev_no'           => $ml ? (int) $ml->revise_no : 0,
                    'title'            => ($ml && $ml->doc_title) ? $ml->doc_title : (($drf && $drf->doc_title) ? $drf->doc_title : 'N/A'),
                    'effectivity'      => ($ml && $ml->effectivity_date) ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : null,
                    'originator'       => $ml ? $ml->originator_name : null,
                    'pages'            => $ml ? $ml->no_pages : null,
                    'status'           => $doc->approval_status ?? 'Active',
                    'pdf_path'         => ($ml && $ml->scanned_masterlist) ? '/storage/' . $ml->scanned_masterlist : null,
                    'source_unit'      => $sourceUnitName,
                    'related'          => $ml ? $ml->allRelatedDocuments()->map(fn ($r) => [
                                            'doc_no' => $r->doc_no,
                                            'title'  => $r->doc_title,
                                        ])->values() : [],
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

            $grouped = $allRows->groupBy(function ($row) {
                if (!$row['doc_no'] || $row['doc_no'] === 'N/A') {
                    return 'no_ml_' . $row['request_id'];
                }
                return $row['doc_no'] . '||' . ($row['doc_type_id'] ?? 0) . '||' . ($row['sub_type_id'] ?? 0);
            });

            $groups = collect();
            foreach ($grouped as $groupKey => $rows) {
                $sorted = $rows->sortByDesc('rev_no')->values();
                $docNo  = str_starts_with($groupKey, 'no_ml_') ? 'N/A' : $groupKey;

                $parent = $sorted->first();
                $parent['status'] = ($docNo !== 'N/A') ? 'Latest' : 'Active';

                $children = $sorted->slice(1)->values()->map(function ($child) {
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

            if ($request->filled('revision_scope') && $request->input('revision_scope') !== 'all') {
                $wantObsoleteOnly = $request->input('revision_scope') === 'obsolete';
                $groups = $groups->filter(fn ($g) => $wantObsoleteOnly ? $g['has_revisions'] : true)->values();
            }

            $groups = $groups->sort(function ($a, $b) {
                $catA = $a['parent']['doc_type_name'] ?? 'zzz';
                $catB = $b['parent']['doc_type_name'] ?? 'zzz';
                $catCompare = strcmp($catA, $catB);
                if ($catCompare !== 0) return $catCompare;
                return ($b['parent']['request_id'] ?? 0) <=> ($a['parent']['request_id'] ?? 0);
            })->values();

            $totalGroups     = $groups->count();
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
                'error' => "An error occurred (ref: {$refId})", 'data' => [],
                'total' => 0, 'current_page' => 1, 'last_page' => 1, 'per_page' => 20,
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────
    // EXPORT
    // ──────────────────────────────────────────────────────────
    public function export()
    {
        return redirect()->route('database.index')->with('info', 'Export feature coming soon.');
    }

    // ──────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────
    private function formatTime($time)
    {
        if (!$time) return null;
        try {
            return \Carbon\Carbon::parse($time)->format('h:i A');
        } catch (\Exception $e) {
            return $time;
        }
    }
}