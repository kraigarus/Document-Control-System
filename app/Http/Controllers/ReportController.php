<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\MasterlistRegistration;
use App\Models\DocumentRequestForm;
use App\Models\DocumentChangeNotice;
use App\Models\DocRevision;
use App\Models\DocumentRetrieval;
use App\Models\DocumentDistribution;
use App\Models\MasterlistOrigin;
use App\Models\DocType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    // ════════════════════════════════════════════
    // REPORT DEFINITIONS
    // ════════════════════════════════════════════

    private function getReportCategories(): array
    {
        return [
            'masterlist' => [
                'label' => 'Masterlist',
                'icon'  => 'fa-solid fa-list-check',
                'subs'  => [
                    'internal_docs'  => 'Internal Documented Information',
                    'external_docs'  => 'External Documented Information',
                    'internal_forms' => 'Internal Forms',
                    'forms'          => 'Forms',
                    'logbooks'       => 'Logbooks',
                ],
            ],
            'monitoring' => [
                'label' => 'Monitoring Reports',
                'icon'  => 'fa-solid fa-chart-line',
                'subs'  => [
                    'internal_docs'  => 'Internal Documented Information',
                    'external_docs'  => 'External Documented Information',
                    'internal_forms' => 'Internal Forms',
                    'forms'          => 'Forms',
                    'logbooks'       => 'Logbooks',
                    'drf'            => 'DRF',
                    'dcn'            => 'DCN',
                ],
            ],
            'opcr' => [
                'label' => 'OPCR Targets (PMT Report Accomplishment Evidence)',
                'icon'  => 'fa-solid fa-bullseye',
                'subs'  => [
                    'update_masterlist'     => 'Updating of Masterlist',
                    'issuance_internal'     => 'Issuance of Controlled Internal Documents',
                    'issuance_external'     => 'Issuance of Controlled External Documents',
                    'control_forms'         => 'Controlling of Forms',
                    'control_logbooks'      => 'Controlling of Logbooks',
                    'control_internal_forms'=> 'Controlling of Internal Forms',
                ],
            ],
            'others' => [
                'label' => 'Others',
                'icon'  => 'fa-solid fa-folder-open',
                'subs'  => [
                    'general' => 'General Report',
                ],
            ],
        ];
    }

    // Map subcategories to doc_type names in the database
    private function getDocTypeMapping(): array
    {
        return [
            'internal_docs'  => ['Internal Documented Information'],
            'external_docs'  => ['External Documented Information'],
            'internal_forms' => ['Internal Forms'],
            'forms'          => ['Forms'],
            'logbooks'       => ['Logbooks'],
        ];
    }

    // ════════════════════════════════════════════
    // INDEX — Report selection page
    // ════════════════════════════════════════════

    public function index()
    {
        $categories = $this->getReportCategories();
        $docTypes   = DocType::whereNull('parent_id')->orderBy('doc_type_name')->get();

        return view('pages.dcs.reports.index', compact('categories', 'docTypes'));
    }

    // ════════════════════════════════════════════
    // DATA — JSON for report table
    // ════════════════════════════════════════════

    public function data(Request $request)
    {
        try {
            $category = $request->input('category');
            $sub      = $request->input('sub');
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');

            if (!$category) {
                return response()->json(['error' => 'Category is required.'], 400);
            }

            switch ($category) {
                case 'masterlist':
                    return $this->masterlistData($sub, $dateFrom, $dateTo);
                case 'monitoring':
                    return $this->monitoringData($sub, $dateFrom, $dateTo);
                case 'opcr':
                    return $this->opcrData($sub, $dateFrom, $dateTo);
                case 'others':
                    return $this->othersData($dateFrom, $dateTo);
                default:
                    return response()->json(['error' => 'Invalid category.'], 400);
            }
        } catch (\Exception $e) {
            $refId = uniqid('err_');
            \Log::error("Report data error [{$refId}]: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'error'   => "An error occurred (ref: {$refId})",
                'rows'    => [],
                'summary' => [],
            ], 500);
        }
    }

    // ════════════════════════════════════════════
    // MASTERLIST REPORT
    // ════════════════════════════════════════════

    private function masterlistData(?string $sub, ?string $dateFrom, ?string $dateTo)
    {
        $docTypeNames = $this->getDocTypeMapping()[$sub] ?? null;

        $query = DocumentRequest::with([
            'masterlistRegistration',
            'masterlistRegistration.origins.office',
            'docType',
        ])->whereHas('masterlistRegistration', function ($q) {
            $q->whereNotNull('doc_no')->where('doc_no', '!=', '');
        });

        if ($docTypeNames) {
            $query->whereHas('docType', function ($q) use ($docTypeNames) {
                $q->whereIn('doc_type_name', $docTypeNames);
            });
        }

        if ($dateFrom) {
            $query->whereHas('masterlistRegistration', function ($q) use ($dateFrom) {
                $q->where('effectivity_date', '>=', $dateFrom);
            });
        }
        if ($dateTo) {
            $query->whereHas('masterlistRegistration', function ($q) use ($dateTo) {
                $q->where('effectivity_date', '<=', $dateTo);
            });
        }

        $docs = $query->orderBy('request_id', 'desc')->get();

        $rows = $docs->map(function ($doc, $index) {
            $ml = $doc->masterlistRegistration;
            if (!$ml) return null;

            $originator = $ml->origins->count() > 0
                ? $ml->origins->map(fn($o) => $o->office ? $o->office->office_name : $o->originator_name)
                    ->filter()->implode(', ')
                : null;

            return [
                'item_no'          => $index + 1,
                'doc_no'           => $ml->doc_no,
                'rev_no'           => (int) $ml->revise_no,
                'doc_title'        => $ml->doc_title,
                'effectivity_date' => $ml->effectivity_date
                    ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : null,
                'originator'       => $originator,
                'no_pages'         => $ml->no_pages,
                'doc_type'         => $doc->docType->doc_type_name ?? 'N/A',
                'pdf_path'         => $ml->scanned_masterlist
                    ? '/storage/' . $ml->scanned_masterlist : null,
            ];
        })->filter()->values();

        $columns = [
            'item_no'          => 'ITEM NO.',
            'doc_no'           => 'DOCUMENT NO.',
            'rev_no'           => 'REV.',
            'doc_title'        => 'DOCUMENT TITLE',
            'effectivity_date' => 'EFFECTIVITY DATE',
            'originator'       => 'ORIGINATOR',
            'no_pages'         => 'PAGES',
            'pdf_path'         => 'PDF FILE',
        ];

        return response()->json([
            'rows'       => $rows,
            'columns'    => $columns,
            'title'      => 'Masterlist Report',
            'total_rows' => $rows->count(),
        ]);
    }

    // ════════════════════════════════════════════
    // MONITORING REPORT
    // ════════════════════════════════════════════

    private function monitoringData(?string $sub, ?string $dateFrom, ?string $dateTo)
    {
        // DRF-specific report
        if ($sub === 'drf') {
            return $this->drfReport($dateFrom, $dateTo);
        }

        // DCN-specific report
        if ($sub === 'dcn') {
            return $this->dcnReport($dateFrom, $dateTo);
        }

        // Doc-type-based monitoring
        $docTypeNames = $this->getDocTypeMapping()[$sub] ?? null;

        $query = DocumentRequest::with([
            'masterlistRegistration',
            'masterlistRegistration.origins.office',
            'documentRequestForm',
            'documentChangeNotice',
            'documentRetrieval',
            'documentRetrieval.offices',
            'documentDistribution',
            'documentDistribution.offices',
            'docType',
        ]);

        if ($docTypeNames) {
            $query->whereHas('docType', function ($q) use ($docTypeNames) {
                $q->whereIn('doc_type_name', $docTypeNames);
            });
        }

        if ($dateFrom) {
            $query->whereHas('masterlistRegistration', function ($q) use ($dateFrom) {
                $q->where('effectivity_date', '>=', $dateFrom);
            });
        }
        if ($dateTo) {
            $query->whereHas('masterlistRegistration', function ($q) use ($dateTo) {
                $q->where('effectivity_date', '<=', $dateTo);
            });
        }

        $docs = $query->orderBy('request_id', 'desc')->get();

        $rows = $docs->map(function ($doc, $index) {
            $ml   = $doc->masterlistRegistration;
            $drf  = $doc->documentRequestForm;
            $dcn  = $doc->documentChangeNotice;
            $ret  = $doc->documentRetrieval;
            $dist = $doc->documentDistribution;

            $originator = $ml && $ml->origins->count() > 0
                ? $ml->origins->map(fn($o) => $o->office ? $o->office->office_name : $o->originator_name)
                    ->filter()->implode(', ')
                : null;

            $retOffices = ($ret && $ret->offices) ? $ret->offices->pluck('office_name')->implode(', ') : null;
            $distOffices = ($dist && $dist->offices) ? $dist->offices->pluck('office_name')->implode(', ') : null;

            return [
                'item_no'           => $index + 1,
                'doc_no'            => $ml ? $ml->doc_no : null,
                'rev_no'            => $ml ? (int) $ml->revise_no : 0,
                'doc_title'         => $ml ? $ml->doc_title : ($drf ? $drf->doc_title : null),
                'effectivity_date'  => $ml && $ml->effectivity_date
                    ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : null,
                'originator'        => $originator,
                'drf_no'            => $drf ? $drf->drf_no : null,
                'drf_date'          => $drf && $drf->drf_date
                    ? \Carbon\Carbon::parse($drf->drf_date)->format('M d, Y') : null,
                'dcn_no'            => $dcn ? $dcn->dcn_no : null,
                'dcn_date'          => $dcn && $dcn->dcn_date
                    ? \Carbon\Carbon::parse($dcn->dcn_date)->format('M d, Y') : null,
                'ml_registered'     => $ml && $ml->doc_registered_date
                    ? \Carbon\Carbon::parse($ml->doc_registered_date)->format('M d, Y') : null,
                'retrieval_status'  => $ret ? 'Retrieved' : 'Not Retrieved',
                'distribution_status' => $dist ? 'Distributed' : 'Not Distributed',
                'ret_offices'       => $retOffices,
                'dist_offices'      => $distOffices,
                'pdf_path'          => $ml && $ml->scanned_masterlist
                    ? '/storage/' . $ml->scanned_masterlist : null,
            ];
        })->filter()->values();

        $columns = [
            'item_no'             => 'ITEM NO.',
            'doc_no'              => 'DOCUMENT NO.',
            'rev_no'              => 'REV.',
            'doc_title'           => 'DOCUMENT TITLE',
            'effectivity_date'    => 'EFFECTIVITY DATE',
            'originator'          => 'ORIGINATOR',
            'drf_no'              => 'DRF NO.',
            'drf_date'            => 'DRF DATE',
            'dcn_no'              => 'DCN NO.',
            'dcn_date'            => 'DCN DATE',
            'ml_registered'       => 'DATE REGISTERED',
            'retrieval_status'    => 'RETRIEVAL',
            'distribution_status' => 'DISTRIBUTION',
        ];

        return response()->json([
            'rows'       => $rows,
            'columns'    => $columns,
            'title'      => 'Monitoring Report',
            'total_rows' => $rows->count(),
        ]);
    }

    private function drfReport(?string $dateFrom, ?string $dateTo)
    {
        $query = DocumentRequestForm::with(['request.docType'])
            ->whereHas('request');

        if ($dateFrom) {
            $query->where('drf_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('drf_date', '<=', $dateTo);
        }

        $drfs = $query->orderBy('drf_date', 'desc')->get();

        $rows = $drfs->map(function ($drf, $index) {
            return [
                'item_no'          => $index + 1,
                'drf_no'           => $drf->drf_no,
                'drf_date'         => $drf->drf_date
                    ? \Carbon\Carbon::parse($drf->drf_date)->format('M d, Y') : null,
                'doc_title'        => $drf->doc_title,
                'receipt_date'     => $drf->drf_receipt_date
                    ? \Carbon\Carbon::parse($drf->drf_receipt_date)->format('M d, Y') : null,
                'receipt_time'     => $drf->drf_receipt_time
                    ? $this->formatTime($drf->drf_receipt_time) : null,
                'doc_type'         => $drf->request && $drf->request->docType
                    ? $drf->request->docType->doc_type_name : 'N/A',
                'pdf_path'         => $drf->scanned_drf
                    ? '/storage/' . $drf->scanned_drf : null,
            ];
        })->values();

        $columns = [
            'item_no'      => 'ITEM NO.',
            'drf_no'       => 'DRF NO.',
            'drf_date'     => 'DRF DATE',
            'doc_title'    => 'DOCUMENT TITLE',
            'receipt_date' => 'RECEIPT DATE',
            'receipt_time' => 'RECEIPT TIME',
            'doc_type'     => 'DOC TYPE',
            'pdf_path'     => 'SCANNED DRF',
        ];

        return response()->json([
            'rows'       => $rows,
            'columns'    => $columns,
            'title'      => 'DRF Monitoring Report',
            'total_rows' => $rows->count(),
        ]);
    }

    private function dcnReport(?string $dateFrom, ?string $dateTo)
    {
        $query = DocumentChangeNotice::with(['request.docType'])
            ->whereHas('request');

        if ($dateFrom) {
            $query->where('dcn_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('dcn_date', '<=', $dateTo);
        }

        $dcns = $query->orderBy('dcn_date', 'desc')->get();

        $rows = $dcns->map(function ($dcn, $index) {
            $revisions = DocRevision::where('dcn_id', $dcn->dcn_id)->get();
            $purpose = $revisions->first()?->brief_purpose;

            return [
                'item_no'          => $index + 1,
                'dcn_no'           => $dcn->dcn_no,
                'dcn_date'         => $dcn->dcn_date
                    ? \Carbon\Carbon::parse($dcn->dcn_date)->format('M d, Y') : null,
                'receipt_date'     => $dcn->dcn_receipt_date
                    ? \Carbon\Carbon::parse($dcn->dcn_receipt_date)->format('M d, Y') : null,
                'receipt_time'     => $dcn->dcn_receipt_time
                    ? $this->formatTime($dcn->dcn_receipt_time) : null,
                'purpose'          => $purpose,
                'doc_type'         => $dcn->request && $dcn->request->docType
                    ? $dcn->request->docType->doc_type_name : 'N/A',
                'revision_count'   => $revisions->count(),
                'pdf_path'         => $dcn->scanned_dcn
                    ? '/storage/' . $dcn->scanned_dcn : null,
            ];
        })->values();

        $columns = [
            'item_no'        => 'ITEM NO.',
            'dcn_no'         => 'DCN NO.',
            'dcn_date'       => 'DCN DATE',
            'receipt_date'   => 'RECEIPT DATE',
            'receipt_time'   => 'RECEIPT TIME',
            'purpose'        => 'PURPOSE',
            'doc_type'       => 'DOC TYPE',
            'revision_count' => 'REVISIONS',
            'pdf_path'       => 'SCANNED DCN',
        ];

        return response()->json([
            'rows'       => $rows,
            'columns'    => $columns,
            'title'      => 'DCN Monitoring Report',
            'total_rows' => $rows->count(),
        ]);
    }

    // ════════════════════════════════════════════
    // OPCR TARGETS
    // ════════════════════════════════════════════

    private function opcrData(?string $sub, ?string $dateFrom, ?string $dateTo)
    {
        $startDate = $dateFrom ? \Carbon\Carbon::parse($dateFrom) : \Carbon\Carbon::now()->startOfYear();
        $endDate   = $dateTo   ? \Carbon\Carbon::parse($dateTo)   : \Carbon\Carbon::now();

        $rows = collect();
        $subLabel = $this->getReportCategories()['opcr']['subs'][$sub] ?? 'OPCR Targets';

        switch ($sub) {
            case 'update_masterlist':
                $rows = $this->opcrMasterlistUpdates($startDate, $endDate);
                break;
            case 'issuance_internal':
                $rows = $this->opcrIssuance($startDate, $endDate, ['Internal Documented Information']);
                break;
            case 'issuance_external':
                $rows = $this->opcrIssuance($startDate, $endDate, ['External Documented Information']);
                break;
            case 'control_forms':
                $rows = $this->opcrControlled($startDate, $endDate, ['Forms']);
                break;
            case 'control_logbooks':
                $rows = $this->opcrControlled($startDate, $endDate, ['Logbooks']);
                break;
            case 'control_internal_forms':
                $rows = $this->opcrControlled($startDate, $endDate, ['Internal Forms']);
                break;
            default:
                $rows = $this->opcrMasterlistUpdates($startDate, $endDate);
                break;
        }

        $columns = [
            'item_no'          => 'ITEM NO.',
            'doc_no'           => 'DOCUMENT NO.',
            'doc_title'        => 'DOCUMENT TITLE',
            'rev_no'           => 'REV.',
            'effectivity_date' => 'EFFECTIVITY DATE',
            'date_registered'  => 'DATE REGISTERED',
            'doc_type'         => 'DOC TYPE',
            'status'           => 'STATUS',
        ];

        return response()->json([
            'rows'       => $rows,
            'columns'    => $columns,
            'title'      => $subLabel,
            'total_rows' => $rows->count(),
        ]);
    }

    private function opcrMasterlistUpdates($startDate, $endDate)
    {
        $docs = DocumentRequest::with(['masterlistRegistration', 'masterlistRegistration.origins.office', 'docType'])
            ->whereHas('masterlistRegistration', function ($q) use ($startDate, $endDate) {
                $q->whereNotNull('doc_no')
                    ->where('doc_no', '!=', '')
                    ->whereBetween('doc_registered_date', [$startDate, $endDate]);
            })
            ->orderBy('request_id', 'desc')
            ->get();

        return $docs->map(function ($doc, $index) {
            $ml = $doc->masterlistRegistration;
            return [
                'item_no'          => $index + 1,
                'doc_no'           => $ml->doc_no,
                'doc_title'        => $ml->doc_title,
                'rev_no'           => (int) $ml->revise_no,
                'effectivity_date' => $ml->effectivity_date
                    ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : null,
                'date_registered'  => $ml->doc_registered_date
                    ? \Carbon\Carbon::parse($ml->doc_registered_date)->format('M d, Y') : null,
                'doc_type'         => $doc->docType->doc_type_name ?? 'N/A',
                'status'           => 'Accomplished',
            ];
        })->values();
    }

    private function opcrIssuance($startDate, $endDate, array $docTypeNames)
    {
        $docs = DocumentRequest::with(['masterlistRegistration', 'docType'])
            ->whereHas('docType', function ($q) use ($docTypeNames) {
                $q->whereIn('doc_type_name', $docTypeNames);
            })
            ->whereHas('masterlistRegistration', function ($q) use ($startDate, $endDate) {
                $q->whereNotNull('doc_no')
                    ->where('doc_no', '!=', '')
                    ->whereBetween('doc_registered_date', [$startDate, $endDate]);
            })
            ->orderBy('request_id', 'desc')
            ->get();

        return $docs->map(function ($doc, $index) {
            $ml = $doc->masterlistRegistration;
            return [
                'item_no'          => $index + 1,
                'doc_no'           => $ml->doc_no,
                'doc_title'        => $ml->doc_title,
                'rev_no'           => (int) $ml->revise_no,
                'effectivity_date' => $ml->effectivity_date
                    ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : null,
                'date_registered'  => $ml->doc_registered_date
                    ? \Carbon\Carbon::parse($ml->doc_registered_date)->format('M d, Y') : null,
                'doc_type'         => $doc->docType->doc_type_name ?? 'N/A',
                'status'           => 'Issued',
            ];
        })->values();
    }

    private function opcrControlled($startDate, $endDate, array $docTypeNames)
    {
        $docs = DocumentRequest::with(['masterlistRegistration', 'docType'])
            ->whereHas('docType', function ($q) use ($docTypeNames) {
                $q->whereIn('doc_type_name', $docTypeNames);
            })
            ->whereHas('masterlistRegistration', function ($q) use ($startDate, $endDate) {
                $q->whereNotNull('doc_no')
                    ->where('doc_no', '!=', '')
                    ->whereBetween('doc_registered_date', [$startDate, $endDate]);
            })
            ->orderBy('request_id', 'desc')
            ->get();

        return $docs->map(function ($doc, $index) {
            $ml = $doc->masterlistRegistration;
            return [
                'item_no'          => $index + 1,
                'doc_no'           => $ml->doc_no,
                'doc_title'        => $ml->doc_title,
                'rev_no'           => (int) $ml->revise_no,
                'effectivity_date' => $ml->effectivity_date
                    ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : null,
                'date_registered'  => $ml->doc_registered_date
                    ? \Carbon\Carbon::parse($ml->doc_registered_date)->format('M d, Y') : null,
                'doc_type'         => $doc->docType->doc_type_name ?? 'N/A',
                'status'           => 'Controlled',
            ];
        })->values();
    }

    // ════════════════════════════════════════════
    // OTHERS
    // ════════════════════════════════════════════

    private function othersData(?string $dateFrom, ?string $dateTo)
    {
        $query = DocumentRequest::with([
            'masterlistRegistration',
            'masterlistRegistration.origins.office',
            'documentRequestForm',
            'documentChangeNotice',
            'docType',
        ]);

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $docs = $query->orderBy('request_id', 'desc')->get();

        $rows = $docs->map(function ($doc, $index) {
            $ml  = $doc->masterlistRegistration;
            $drf = $doc->documentRequestForm;
            $dcn = $doc->documentChangeNotice;

            $originator = $ml && $ml->origins->count() > 0
                ? $ml->origins->map(fn($o) => $o->office ? $o->office->office_name : $o->originator_name)
                    ->filter()->implode(', ')
                : null;

            $checklists = collect();
            if ($drf) $checklists->push('DRF');
            if ($dcn) $checklists->push('DCN');
            if ($ml)  $checklists->push('Masterlist');

            return [
                'item_no'         => $index + 1,
                'request_id'      => $doc->request_id,
                'doc_no'          => $ml ? $ml->doc_no : 'N/A',
                'doc_title'       => $ml ? $ml->doc_title : ($drf ? $drf->doc_title : 'N/A'),
                'rev_no'          => $ml ? (int) $ml->revise_no : 0,
                'doc_type'        => $doc->docType->doc_type_name ?? 'N/A',
                'originator'      => $originator,
                'checklists'      => $checklists->implode(', '),
                'date_created'    => $doc->created_at
                    ? \Carbon\Carbon::parse($doc->created_at)->format('M d, Y h:i A') : null,
            ];
        })->values();

        $columns = [
            'item_no'      => 'ITEM NO.',
            'request_id'   => 'REQUEST ID',
            'doc_no'       => 'DOCUMENT NO.',
            'doc_title'    => 'DOCUMENT TITLE',
            'rev_no'       => 'REV.',
            'doc_type'     => 'DOC TYPE',
            'originator'   => 'ORIGINATOR',
            'checklists'   => 'CHECKLISTS',
            'date_created' => 'DATE CREATED',
        ];

        return response()->json([
            'rows'       => $rows,
            'columns'    => $columns,
            'title'      => 'General Report',
            'total_rows' => $rows->count(),
        ]);
    }

    // ════════════════════════════════════════════
    // EXPORT — Print-friendly HTML
    // ════════════════════════════════════════════

    public function export(Request $request)
    {
        $category = $request->input('category');
        $sub      = $request->input('sub');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        // Call data method internally
        $response = $this->data($request);
        $json = $response->getData(true);

        if (isset($json['error'])) {
            return back()->with('error', 'Failed to generate report.');
        }

        $rows    = $json['rows'];
        $columns = $json['columns'];
        $title   = $json['title'];
        $categories = $this->getReportCategories();
        $categoryLabel = $categories[$category]['label'] ?? ucfirst($category);
        $subLabel = $categories[$category]['subs'][$sub] ?? '';

        return view('pages.dcs.reports.export', compact(
            'rows', 'columns', 'title', 'categoryLabel', 'subLabel',
            'dateFrom', 'dateTo'
        ));
    }

    // ════════════════════════════════════════════
    // HELPER
    // ════════════════════════════════════════════

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
}