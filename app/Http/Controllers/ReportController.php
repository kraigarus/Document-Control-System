<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\MasterlistRegistration;
use App\Models\DocumentRequestForm;
use App\Models\DocumentChangeNotice;
use App\Models\DocRevision;
use App\Models\DocumentRetrieval;
use App\Models\DocumentDistribution;
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
            'label' => 'Document Masterlist',
            'icon'  => 'fa-solid fa-clipboard-list',
            'subs'  => [
                'internal_docs'  => 'Internal',
                'external_docs'  => 'External',
                'internal_forms' => 'Internal Forms',
                'forms'          => 'Forms',
                'logbooks'       => 'Logbooks',
            ],
        ],
            'monitoring' => [
                'label' => 'Monitoring Reports',
                'icon'  => 'fa-solid fa-chart-line',
                'subs'  => [
                    'internal_docs'  => 'Internal',
                    'external_docs'  => 'External',
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
            'internal_docs'  => ['Internal'],
            'external_docs'  => ['External'],
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
        $originators = $this->getOriginatorOptions();
        $offices     = $this->getSourceOfficeOptions();
        return view('pages.dcs.reports.index', compact('categories', 'docTypes', 'originators', 'offices'));
    }

    public function masterlist()
    {
        $categories = $this->getReportCategories();
        $docTypes   = DocType::whereNull('parent_id')->orderBy('doc_type_name')->get();
       $originators = $this->getOriginatorOptions();
        $offices     = $this->getSourceOfficeOptions();
        $activeCategory = 'masterlist';
        return view('pages.dcs.reports.masterlist', compact('categories', 'docTypes', 'originators', 'offices', 'activeCategory'));
    }

    public function monitoring()
    {
        $originators = $this->getOriginatorOptions();
        $offices     = $this->getSourceOfficeOptions();
        return view('pages.dcs.reports.monitoring', compact('originators', 'offices'));
    }


    public function othersReport()
    {
        $categories = $this->getReportCategories();
        $docTypes   = DocType::whereNull('parent_id')->orderBy('doc_type_name')->get();
        $originators = $this->getOriginatorOptions();
        $offices     = $this->getSourceOfficeOptions();
        $activeCategory = 'others';
        return view('pages.dcs.reports.others', compact('categories', 'docTypes', 'originators', 'offices', 'activeCategory'));
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

            $filters = [
                'originator'  => $request->input('originator'),
                'source_unit' => $request->input('source_unit'),
                'status'      => $request->input('status'),
                'rev_no'      => $request->input('rev_no'),
            ];

            if (!$category) {
                return response()->json(['error' => 'Category is required.'], 400);
            }

            switch ($category) {
                case 'masterlist':
                    return $this->masterlistData($sub, $dateFrom, $dateTo, $filters);
                case 'monitoring':
                    return $this->monitoringData($sub, $dateFrom, $dateTo, $filters);
                case 'opcr':
                    return $this->opcrData($sub, $dateFrom, $dateTo, $filters);
                case 'others':
                    return $this->othersData($dateFrom, $dateTo, $filters);
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

    private function applyCommonFilters($query, array $filters, string $mlRelation = 'masterlistRegistration')
    {
        if (!empty($filters['originator'])) {
            $originator = $filters['originator'];
            $query->whereHas($mlRelation, function ($q) use ($originator) {
                $q->where('originator_name', $originator);
            });
        }

        if (!empty($filters['source_unit'])) {
            $officeId = $filters['source_unit'];
            $query->whereHas("$mlRelation.sourceOffices", function ($q) use ($officeId) {
                $q->where('office_id', $officeId);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('approval_status', $filters['status']);
        }

        if (!empty($filters['rev_no'])) {
            $revNo = $filters['rev_no'];
            $query->whereHas($mlRelation, function ($q) use ($revNo) {
                $q->where('revise_no', $revNo);
            });
        }

        return $query;
    }

    // ════════════════════════════════════════════
    // MASTERLIST REPORT
    // ════════════════════════════════════════════

    private function masterlistData(?string $sub, ?string $dateFrom, ?string $dateTo, array $filters = [])
    {
        $docTypeNames = $this->getDocTypeMapping()[$sub] ?? null;

        $query = DocumentRequest::with([
            'masterlistRegistration',
            'masterlistRegistration.sourceOffices.office',
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

        $query = $this->applyCommonFilters($query, $filters);

        $docs = $query->orderBy('request_id', 'desc')->get();


        $rows = $docs->map(function ($doc, $index) {
            $ml = $doc->masterlistRegistration;
            if (!$ml) return null;

        $originator = $ml->originator_name;

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
            'title'      => 'Document Masterlist',
            'total_rows' => $rows->count(),
        ]);
    }

    // ════════════════════════════════════════════
    // MONITORING REPORT
    // ════════════════════════════════════════════
    private function monitoringData(?string $sub, ?string $dateFrom, ?string $dateTo, array $filters = [])
    {
        if ($sub === 'drf') return $this->drfReport($dateFrom, $dateTo);
        if ($sub === 'dcn') return $this->dcnReport($dateFrom, $dateTo);
        if ($sub === 'internal_docs') return $this->documentMonitoringLog('Internal', $dateFrom, $dateTo, $filters);
        if ($sub === 'external_docs') return $this->documentMonitoringLog('External', $dateFrom, $dateTo, $filters);
        if (in_array($sub, ['internal_forms', 'forms', 'logbooks'])) {
            return $this->formsLogbooksMonitoringLog($sub, $dateFrom, $dateTo, $filters);
        }

        return response()->json(['error' => 'Unknown monitoring report type.'], 400);
    }

    /**
     * Forms & Logbooks monitoring — matches the 3-row grouped header layout
     */
    private function formsLogbooksMonitoringLog(string $sub, ?string $dateFrom, ?string $dateTo, array $filters = [])
    {
        $docTypeMap = [
            'internal_forms' => 'Internal Forms',
            'forms'          => 'Forms',
            'logbooks'       => 'Logbooks',
        ];
        $docTypeName = $docTypeMap[$sub] ?? 'Forms';

        $query = DocumentRequest::with([
            'masterlistRegistration',
            'masterlistRegistration.sourceOffices.office',
            'documentRequestForm',
            'documentChangeNotice',
            'docType',
        ])
        ->whereHas('docType', function ($q) use ($docTypeName) {
            $q->where('doc_type_name', $docTypeName);
        });

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $query = $this->applyCommonFilters($query, $filters);

        $docs = $query->orderBy('request_id', 'desc')->get();

        $rows = $docs->map(function ($doc, $index) {
            $ml  = $doc->masterlistRegistration;
            $drf = $doc->documentRequestForm;
            $dcn = $doc->documentChangeNotice;

            // Date received
            $dateReceived = $drf && $drf->drf_date
                ? \Carbon\Carbon::parse($drf->drf_date)->format('m/d/Y') : null;

            // Time received
            $timeReceived = $drf && $drf->drf_receipt_time
                ? $this->formatTime($drf->drf_receipt_time) : null;

            // Source
            $source = $ml && $ml->sourceOffices->count() > 0
                ? $ml->sourceOffices->map(fn($o) => $o->office ? $o->office->office_name : $o->source_name)
                    ->filter()->implode(', ')
                : null;

            // Document number
            $docNumber = $ml ? $ml->doc_no : null;

            // Description
            $description = $ml ? $ml->doc_title : ($drf ? $drf->doc_title : null);

            // Category
            $category = $doc->docType->doc_type_name ?? null;

            // Masterlist registration date
            $mlRegDate = $ml && $ml->doc_registered_date
                ? \Carbon\Carbon::parse($ml->doc_registered_date)->format('m/d/Y') : null;

            // Masterlist registration time
            $mlRegTime = $ml && $ml->doc_registered_date
                ? \Carbon\Carbon::parse($ml->doc_registered_date)->format('h:i A') : null;

            // Time spent 1 (mins) — receipt to registration
            $timeSpent1 = null;
            if ($drf && $drf->drf_date && $drf->drf_receipt_time && $ml && $ml->doc_registered_date) {
                try {
                    $start = \Carbon\Carbon::parse($drf->drf_date . ' ' . $drf->drf_receipt_time);
                    $end   = \Carbon\Carbon::parse($ml->doc_registered_date);
                    $timeSpent1 = (int) $start->diffInMinutes($end);
                } catch (\Exception $e) {
                    $timeSpent1 = null;
                }
            }

            // Time released date
            $dateReleased = $ml && $ml->date_released
                ? \Carbon\Carbon::parse($ml->date_released)->format('m/d/Y') : null;

            // Time released time
            $timeReleased = $ml && $ml->date_released
                ? \Carbon\Carbon::parse($ml->date_released)->format('h:i A') : null;

            // Time spent 2 (mins) — registration to release
            $timeSpent2 = null;
            if ($ml && $ml->doc_registered_date && $ml->date_released) {
                try {
                    $start = \Carbon\Carbon::parse($ml->doc_registered_date);
                    $end   = \Carbon\Carbon::parse($ml->date_released);
                    $timeSpent2 = (int) $start->diffInMinutes($end);
                } catch (\Exception $e) {
                    $timeSpent2 = null;
                }
            }

            // Forwarded for DRR
            $forwardedDRR = null;

            // Remarks
            $remarks = $dcn && $dcn->dcn_no ? 'DCN: ' . $dcn->dcn_no : null;

            return [
                'no'            => $index + 1,
                'date_received' => $dateReceived,
                'time_received' => $timeReceived,
                'source'        => $source,
                'doc_number'    => $docNumber,
                'description'   => $description,
                'category'      => $category,
                'ml_reg_date'   => $mlRegDate,
                'ml_reg_time'   => $mlRegTime,
                'time_spent1'   => $timeSpent1,
                'date_released' => $dateReleased,
                'time_released' => $timeReleased,
                'time_spent2'   => $timeSpent2,
                'forwarded_drr' => $forwardedDRR,
                'remarks'       => $remarks,
                'pdf_path'      => $ml && $ml->scanned_masterlist
                    ? '/storage/' . $ml->scanned_masterlist : null,
            ];
        })->values();

        $subLabels = [
            'internal_forms' => 'Internal Forms',
            'forms'          => 'Forms',
            'logbooks'       => 'Logbooks',
        ];

        $title = 'Monitoring Reports (' . ($subLabels[$sub] ?? 'Forms') . ')';

        // Row 2 — sub-labels (for grouped columns only)
        $columns = [
            'no'            => 'No',
            'date_received' => 'Date',
            'time_received' => 'Time',
            'source'        => 'Source',
            'doc_number'    => 'Document Number',
            'description'   => 'Description',
            'category'      => 'Category',
            'ml_reg_date'   => 'Date',
            'ml_reg_time'   => 'Time',
            'time_spent1'   => 'Time Spent (Mins)',
            'date_released' => 'Date',
            'time_released' => 'Time',
            'time_spent2'   => 'Time Spent (Mins)',
            'forwarded_drr' => 'Forwarded for DRR?',
            'remarks'       => 'Remarks',
        ];

        // Row 1 — group headers (null = standalone, rowspan=2)
        $groupHeaders = [
            'no'            => null,
            'date_received' => 'Date Received',
            'time_received' => 'Date Received',
            'source'        => null,
            'doc_number'    => null,
            'description'   => null,
            'category'      => null,
            'ml_reg_date'   => 'Masterlist Registration',
            'ml_reg_time'   => 'Masterlist Registration',
            'time_spent1'   => null,
            'date_released' => 'Time Released',
            'time_released' => 'Time Released',
            'time_spent2'   => null,
            'forwarded_drr' => null,
            'remarks'       => null,
        ];

        return response()->json([
            'rows'          => $rows,
            'columns'       => $columns,
            'group_headers' => $groupHeaders,
            'title'         => $title,
            'total_rows'    => $rows->count(),
        ]);
    }

    /**
     * Shared monitoring log for Internal & External documents.
     * Matches the layout: No, Date Received, Document Time,
     * Registered Masterlist Time, Source, In-Charge, Control Number,
     * Subject Matter, Effectivity Date, DEADLINE, Date Released,
     * Days Spent, Remarks
     */
    private function documentMonitoringLog(string $docTypeName, ?string $dateFrom, ?string $dateTo, array $filters = [])
    {
        $query = DocumentRequest::with([
            'masterlistRegistration',
            'masterlistRegistration.sourceOffices.office',
            'documentRequestForm',
            'documentChangeNotice',
            'docType',
        ])
        ->whereHas('docType', function ($q) use ($docTypeName) {
            $q->where('doc_type_name', $docTypeName);
        });

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $query = $this->applyCommonFilters($query, $filters);

        $docs = $query->orderBy('request_id', 'desc')->get();

        $rows = $docs->map(function ($doc, $index) {
            $ml  = $doc->masterlistRegistration;
            $drf = $doc->documentRequestForm;
            $dcn = $doc->documentChangeNotice;

            // DRF reference number
            $drfRef = $drf && $drf->drf_no ? $drf->drf_no : null;

            // Date received (document date)
            $dateReceived = $drf && $drf->drf_date
                ? \Carbon\Carbon::parse($drf->drf_date)->format('m/d/Y') : null;

            // Time received
            $timeReceived = $drf && $drf->drf_receipt_time
                ? $this->formatTime($drf->drf_receipt_time) : null;

            // Registered to masterlist - date
            $dateRegistered = $ml && $ml->doc_registered_date
                ? \Carbon\Carbon::parse($ml->doc_registered_date)->format('m/d/Y') : null;

            // Registered to masterlist - time
            $timeRegistered = $ml && $ml->doc_registered_date
                ? \Carbon\Carbon::parse($ml->doc_registered_date)->format('h:i A') : null;

            // Minutes spent (between receipt and registration)
            $minsSpent = null;
            if ($drf && $drf->drf_date && $drf->drf_receipt_time && $ml && $ml->doc_registered_date) {
                try {
                    $start = \Carbon\Carbon::parse($drf->drf_date . ' ' . $drf->drf_receipt_time);
                    $end   = \Carbon\Carbon::parse($ml->doc_registered_date);
                    $minsSpent = (int) $start->diffInMinutes($end);
                } catch (\Exception $e) {
                    $minsSpent = null;
                }
            }

            // Source (originator)
            $source = $ml && $ml->sourceOffices->count() > 0
                ? $ml->sourceOffices->map(fn($o) => $o->office ? $o->office->office_name : $o->source_name)
                    ->filter()->implode(', ')
                : null;

            // Control number
            $controlNumber = $ml ? $ml->doc_no : null;

            // Subject matter
            $subjectMatter = $ml ? $ml->doc_title : ($drf ? $drf->doc_title : null);

            // Effectivity date
            $effectivityDate = $ml && $ml->effectivity_date
                ? \Carbon\Carbon::parse($ml->effectivity_date)->format('m/d/Y') : null;

            // Days spent
            $daysSpent = null;
            if ($drf && $drf->drf_date && $ml && $ml->effectivity_date) {
                try {
                    $start = \Carbon\Carbon::parse($drf->drf_date);
                    $end   = \Carbon\Carbon::parse($ml->effectivity_date);
                    $daysSpent = (int) $start->diffInDays($end);
                } catch (\Exception $e) {
                    $daysSpent = null;
                }
            }

            return [
                'no'               => $index + 1,
                'drf_no'           => $drfRef,
                'date_received'    => $dateReceived,
                'time_received'    => $timeReceived,
                'date_registered'  => $dateRegistered,
                'time_registered'  => $timeRegistered,
                'mins_spent'       => $minsSpent,
                'source'           => $source,
                'in_charge'        => $ml ? $ml->in_charge : null,
                'control_number'   => $controlNumber,
                'subject_matter'   => $subjectMatter,
                'effectivity_date' => $effectivityDate,
                'deadline'         => $ml && $ml->deadline
                    ? \Carbon\Carbon::parse($ml->deadline)->format('m/d/Y') : null,
                'date_released'    => $ml && $ml->date_released
                    ? \Carbon\Carbon::parse($ml->date_released)->format('m/d/Y') : null,
                'days_spent'       => $daysSpent,
                'remarks'          => $dcn && $dcn->dcn_no ? 'DCN: ' . $dcn->dcn_no : null,
                'pdf_path'         => $ml && $ml->scanned_masterlist
                    ? '/storage/' . $ml->scanned_masterlist : null,
            ];
        })->values();

        $title = $docTypeName === 'Internal'
            ? 'Monitoring Reports (Internal Documents)'
            : 'Monitoring Reports (External Documents)';

        // Row 2 — main column names
        $columns = [
            'no'               => 'No.',
            'drf_no'           => 'DRF',
            'date_received'    => 'Document',
            'time_received'    => 'Time',
            'date_registered'  => 'Date',
            'time_registered'  => 'Time',
            'mins_spent'       => 'Mins Spent',
            'source'           => 'Source',
            'in_charge'        => 'In charge',
            'control_number'   => 'Control Number',
            'subject_matter'   => 'Subject Matter',
            'effectivity_date' => 'Effectivity Date',
            'deadline'         => 'DEADLINE',
            'date_released'    => 'Date Released',
            'days_spent'       => 'Days Spent',
            'remarks'          => 'Remarks',
        ];

        // Row 1 — group headers (null = standalone, spans 2 rows)
        $groupHeaders = [
            'no'               => null,
            'drf_no'           => 'Date Received',
            'date_received'    => 'Date Received',
            'time_received'    => 'Date Received',
            'date_registered'  => 'Registered to Masterlist',
            'time_registered'  => 'Registered to Masterlist',
            'mins_spent'       => 'Registered to Masterlist',
            'source'           => null,
            'in_charge'        => null,
            'control_number'   => null,
            'subject_matter'   => null,
            'effectivity_date' => null,
            'deadline'         => null,
            'date_released'    => null,
            'days_spent'       => null,
            'remarks'          => null,
        ];

        return response()->json([
            'rows'          => $rows,
            'columns'       => $columns,
            'group_headers' => $groupHeaders,
            'title'         => $title,
            'total_rows'    => $rows->count(),
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
    public function opcr()
    {
        $originators = $this->getOriginatorOptions();
        $offices     = $this->getSourceOfficeOptions();
        return view('pages.dcs.reports.opcr', compact('originators', 'offices'));
    }

    private function opcrData(?string $sub, ?string $dateFrom, ?string $dateTo, array $filters = [])
    {
        $startDate = $dateFrom ? \Carbon\Carbon::parse($dateFrom) : \Carbon\Carbon::now()->startOfYear();
        $endDate   = $dateTo   ? \Carbon\Carbon::parse($dateTo)   : \Carbon\Carbon::now();

        $subLabel = $this->getReportCategories()['opcr']['subs'][$sub] ?? 'OPCR Targets';

        switch ($sub) {
            case 'update_masterlist':
                $docs = $this->getOpcrDocs($startDate, $endDate, null, $filters);
                break;
            case 'issuance_internal':
                $docs = $this->getOpcrDocs($startDate, $endDate, ['Internal Documented Information'], $filters);
                break;
            case 'issuance_external':
                $docs = $this->getOpcrDocs($startDate, $endDate, ['External Documented Information'], $filters);
                break;
            case 'control_forms':
                $docs = $this->getOpcrDocs($startDate, $endDate, ['Forms'], $filters);
                break;
            case 'control_logbooks':
                $docs = $this->getOpcrDocs($startDate, $endDate, ['Logbooks'], $filters);
                break;
            case 'control_internal_forms':
                $docs = $this->getOpcrDocs($startDate, $endDate, ['Internal Forms'], $filters);
                break;
            default:
                $docs = $this->getOpcrDocs($startDate, $endDate, null, $filters);
                break;
        }

        $rows = $docs->map(function ($doc, $index) use ($sub) {
            $ml = $doc->masterlistRegistration;

            $dateReceived = $ml && $ml->doc_registered_date
                ? \Carbon\Carbon::parse($ml->doc_registered_date)->format('m/d/Y') : null;

            $dateReleased = $ml && $ml->date_released
                ? \Carbon\Carbon::parse($ml->date_released)->format('m/d/Y') : null;

            // Calculate days advanced/delayed
            $daysDiff = null;
            $daysType = null;
            if ($ml && $ml->effectivity_date && $ml->date_released) {
                try {
                    $effectivity = \Carbon\Carbon::parse($ml->effectivity_date);
                    $released    = \Carbon\Carbon::parse($ml->date_released);
                    $diff        = $effectivity->diffInDays($released, false);
                    if ($diff >= 0) {
                        $daysDiff = $diff;
                        $daysType = 'advanced';
                    } else {
                        $daysDiff = abs($diff);
                        $daysType = 'delayed';
                    }
                } catch (\Exception $e) {
                    $daysDiff = null;
                    $daysType = null;
                }
            }

                        // Load saved ratings
            $opcrRating = null;
            try {
                if (\Schema::hasTable('opcr_ratings')) {
                    $opcrRating = \DB::table('opcr_ratings')
                        ->where('request_id', $doc->request_id)
                        ->where('sub_type', $sub)
                        ->first();
                }
            } catch (\Exception $e) {
                $opcrRating = null;
            }

            return [
                'no'            => $index + 1,
                'request_id'    => $doc->request_id,
                'doc_number'    => $ml ? $ml->doc_no : null,
                'date_received' => $dateReceived,
                'date_released' => $dateReleased,
                'days_diff'     => $daysDiff,
                'days_type'     => $daysType,
                'rating_q'      => $opcrRating->rating_q ?? null,
                'rating_e'      => $opcrRating->rating_e ?? null,
                'rating_t'      => $opcrRating->rating_t ?? null,
                'rating_a'      => $opcrRating->rating_a ?? null,
                'pdf_path'      => $ml && $ml->scanned_masterlist
                    ? '/storage/' . $ml->scanned_masterlist : null,
            ];
        })->values();

        $columns = [
            'no'            => 'No',
            'doc_number'    => 'Document Number',
            'date_received' => 'Date Received',
            'date_released' => 'Date Released',
            'days_diff'     => 'Days Advanced (+) / Delayed (-)',
            'rating_q'      => 'Q',
            'rating_e'      => 'E',
            'rating_t'      => 'T',
            'rating_a'      => 'A',
        ];

        return response()->json([
            'rows'       => $rows,
            'columns'    => $columns,
            'group_headers' => [
                'no'            => null,
                'doc_number'    => null,
                'date_received' => null,
                'date_released' => null,
                'days_diff'     => null,
                'rating_q'      => 'Ratings',
                'rating_e'      => 'Ratings',
                'rating_t'      => 'Ratings',
                'rating_a'      => 'Ratings',
            ],
            'title'      => $subLabel,
            'total_rows' => $rows->count(),
        ]);
    }

    private function getOpcrDocs($startDate, $endDate, ?array $docTypeNames, array $filters = [])
    {
        $query = DocumentRequest::with(['masterlistRegistration', 'docType'])
            ->whereHas('masterlistRegistration', function ($q) use ($startDate, $endDate) {
                $q->whereNotNull('doc_no')
                    ->where('doc_no', '!=', '')
                    ->whereBetween('doc_registered_date', [$startDate, $endDate]);
            });

        if ($docTypeNames) {
            $query->whereHas('docType', function ($q) use ($docTypeNames) {
                $q->whereIn('doc_type_name', $docTypeNames);
            });
        }

        $query = $this->applyCommonFilters($query, $filters);

        return $query->orderBy('request_id', 'desc')->get();
    }

    public function saveOpcrRatings(Request $request)
    {
        $request->validate([
            'request_id' => 'required|integer',
            'sub'        => 'required|string',
            'rating_q'   => 'nullable|numeric|min:0|max:10',
            'rating_e'   => 'nullable|numeric|min:0|max:10',
            'rating_t'   => 'nullable|numeric|min:0|max:10',
            'rating_a'   => 'nullable|numeric|min:0|max:10',
        ]);

        \App\Models\OpcrRating::updateOrCreate(
            [
                'request_id' => $request->request_id,
                'sub_type'   => $request->sub,
            ],
            [
                'rating_q' => $request->rating_q,
                'rating_e' => $request->rating_e,
                'rating_t' => $request->rating_t,
                'rating_a' => $request->rating_a,
            ]
        );

        return response()->json(['success' => true]);
    }

    // ════════════════════════════════════════════
    // OTHERS
    // ════════════════════════════════════════════

    private function othersData(?string $dateFrom, ?string $dateTo, array $filters = [])
    {
        $query = DocumentRequest::with([
            'masterlistRegistration',
            'masterlistRegistration.sourceOffices.office',
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
        
        $query = $this->applyCommonFilters($query, $filters);

        $docs = $query->orderBy('request_id', 'desc')->get();

        $rows = $docs->map(function ($doc, $index) {
            $ml  = $doc->masterlistRegistration;
            $drf = $doc->documentRequestForm;
            $dcn = $doc->documentChangeNotice;

            $originator = $ml && $ml->sourceOffices->count() > 0
                ? $ml->sourceOffices->map(fn($o) => $o->office ? $o->office->office_name : $o->source_name)
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
        $category = $request->get('category');
        $sub      = $request->get('sub');
        $dateFrom = $request->get('date_from');
        $dateTo   = $request->get('date_to');
        $format   = $request->get('format', 'html');

        $filters = [
            'originator'  => $request->get('originator'),
            'source_unit' => $request->get('source_unit'),
            'status'      => $request->get('status'),
            'rev_no'      => $request->get('rev_no'),
        ];
        
        if (!$category) {
            abort(400, 'Category is required.');
        }

        $data = $this->fetchReportData($category, $sub, $dateFrom, $dateTo, $filters);

        // ── Restrict to checked rows only, if the user selected specific
        //    rows in the UI before exporting. Indices refer to the row's
        //    position in the same filtered/sorted result set returned by
        //    /reports/data, so they line up with what's re-fetched here. ──
                $allRows = $data['rows']->values();
        $totalCount = $allRows->count();

        if (!$request->has('rows')) {
            // No rows parameter at all — return all
            $rows = $allRows;
            $isFiltered = false;
        } elseif ($request->get('rows') === 'none' || $request->get('rows') === '') {
            // User exported with nothing checked — return empty
            $rows = collect();
            $isFiltered = true;
        } else {
            // Filter to selected indices only
            $selectedIndices = collect(explode(',', $request->get('rows')))
                ->map(fn($v) => trim($v))
                ->filter(fn($v) => $v !== '' && is_numeric($v))
                ->map(fn($v) => (int) $v)
                ->values();

            $rows = $allRows->filter(function ($row, $idx) use ($selectedIndices) {
                return $selectedIndices->contains($idx);
            })->values();
            $isFiltered = true;
        }

        $categories = $this->getReportCategories();
        $catLabel   = $categories[$category]['label'] ?? '';
        $subLabel   = ($sub && isset($categories[$category]['subs'][$sub]))
                        ? $categories[$category]['subs'][$sub] : '';

        $filename = 'report-' . $category . '-' . now()->format('Y-m-d');

        $viewData = [
            'title'              => $data['title'],
            'columns'            => $data['columns'],
            'rows'               => $rows,
            'isFiltered'         => $isFiltered,
            'selectedCount'      => $rows->count(),
            'totalCount'         => $totalCount,
            'dateFrom'           => $dateFrom,
            'dateTo'             => $dateTo,
            'activeSub'          => $sub,
            'activeCategory'     => $category,
            'republic'           => 'Republic of the Philippines',
            'institutionName'    => 'Camarines Sur Polytechnic Colleges',
            'institutionAddress' => 'Nabua, Camarines Sur',
            'letterNumber'       => 'CSPC-QA-F001',
            'footerLeft'         => 'Effectivity Date:',
            'footerCenter'       => 'Rev.',
            'footerRight'        => '',
        ];

        // ── CSV ──
        if ($format === 'xlsx' || $format === 'csv') {
            return $this->generateCsv($data['columns'], $rows, $filename . '.csv');
        }

 
        // ── PDF via Dompdf ──
        if ($format === 'pdf') {
            $viewData['isPdf'] = true;

            $html = view('pages.dcs.reports.export', $viewData)->render();

            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('dpi', 96);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a4', 'portrait');
            $dompdf->render();

            // ── Footer: register page text AFTER render ──
                        // ── Footer via canvas ──
            $canvas  = $dompdf->getCanvas();
            $fm      = $dompdf->getFontMetrics();
            $font    = $fm->getFont('DejaVu Sans');
            $w       = $canvas->get_width();
            $h       = $canvas->get_height();

            // Text position near very bottom
            $footerY = $h - 18;

            // Line ABOVE the text (smaller Y = higher on page)
            $canvas->line(40, $footerY - 14, $w - 40, $footerY - 14, [13/255, 42/255, 122/255], 1.5);

            // Left
            $canvas->page_text(40, $footerY, 'Effectivity Date:', $font, 9, [0, 0, 0], 0, 1, '');

            // Center
            $centerText = 'Rev.';
            $centerW    = $fm->getTextWidth($centerText, $font, 9);
            $canvas->page_text(($w - $centerW) / 2, $footerY, $centerText, $font, 9, [0, 0, 0], 0, 1, '');

            // Right
            $canvas->page_text($w - 130, $footerY, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 9, [0, 0, 0], 0, 1, '');
            $output = $dompdf->output();

            return response($output, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
                'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            ]);
        }

        // ── HTML (browser view or auto-print) ──
        return view('pages.dcs.reports.export', $viewData);
    }

        // ════════════════════════════════════════════
    // SHARED DATA FETCHER — used by both data() and export()
    // Calls the existing private methods and extracts the array
    // ════════════════════════════════════════════

    private function fetchReportData(string $category, ?string $sub, ?string $dateFrom, ?string $dateTo, array $filters = []): array
    {
        switch ($category) {
            case 'masterlist':
                $response = $this->masterlistData($sub, $dateFrom, $dateTo, $filters);
                break;
            case 'monitoring':
                $response = $this->monitoringData($sub, $dateFrom, $dateTo, $filters);
                break;
            case 'opcr':
                $response = $this->opcrData($sub, $dateFrom, $dateTo, $filters);
                break;
            case 'others':
                $response = $this->othersData($dateFrom, $dateTo, $filters);
                break;
            default:
                return [
                    'title'      => 'Report',
                    'columns'    => [],
                    'rows'       => collect(),
                    'total_rows' => 0,
            ];
        }

        // Extract the JSON data from the response
        $data = $response->getData(true);

        return [
            'title'      => $data['title'] ?? 'Report',
            'columns'    => $data['columns'] ?? [],
            'rows'       => collect($data['rows'] ?? []),
            'total_rows' => $data['total_rows'] ?? 0,
        ];
    }

    private function generateCsv(array $columns, $rows, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $colKeys = array_keys($columns);

        if ($rows instanceof \Illuminate\Support\Collection) {
            $rows = $rows->toArray();
        }

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($columns, $rows, $colKeys) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, array_values($columns));

            foreach ($rows as $row) {
                $line = [];
                foreach ($colKeys as $key) {
                    $val = is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? '');
                    if ($key === 'pdf_path' && $val) {
                        $val = 'View File';
                    }
                    $line[] = $val;
                }
                fputcsv($handle, $line);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    private function formatTime($time): string
    {
        if (!$time) return '';

        try {
            return \Carbon\Carbon::parse($time)->format('h:i A');
        } catch (\Exception $e) {
            return (string) $time;
        }
    }

    private function getOriginatorOptions()
    {
        return \App\Models\Originator::orderBy('originator_name')->get();
    }

    private function getSourceOfficeOptions()
    {
        return \App\Models\Office::where('status', 'active')->orderBy('office_name')->get();
    }

}