<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Register/Update/Edit/History reads. PK is always `id`.
 */
class RegisterQueryHelper
{
    public static function pgBool(mixed $val): bool
    {
        if (is_bool($val)) {
            return $val;
        }
        if (is_int($val) || is_float($val)) {
            return (bool) $val;
        }

        return in_array(strtolower(trim((string) $val)), ['1', 't', 'true', 'yes', 'on'], true);
    }

    public static function isSyllabiLikeName(?string $name): bool
    {
        return $name !== null && str_contains(mb_strtolower($name), 'syllab');
    }

    /** Parent doc-type IDs keyed by report/dashboard tab. */
    public static function parentTypeIdMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $byName = DB::table('dcs_doc_types')
            ->whereNull('parent_id')
            ->get(['id', 'doc_type_name'])
            ->mapWithKeys(fn ($row) => [mb_strtolower(trim($row->doc_type_name)) => (int) $row->id]);

        $map = [
            'internal_docs' => $byName['internal'] ?? 1,
            'internal_forms' => $byName['internal forms'] ?? 2,
            'external_docs' => $byName['external'] ?? 3,
            'forms' => $byName['forms'] ?? 4,
            'logbooks' => $byName['logbooks'] ?? 5,
        ];

        return $map;
    }

    public static function requestIdsWithSameDocType(object $docRequest): array
    {
        $query = DB::table('dcs_document_requests')
            ->where('doc_type_id', $docRequest->doc_type_id);

        if ($docRequest->sub_type_id === null || $docRequest->sub_type_id === '') {
            $query->whereNull('sub_type_id');
        } else {
            $query->where('sub_type_id', $docRequest->sub_type_id);
        }

        return $query->pluck('id')->all();
    }

    public static function formatDate(mixed $val): string
    {
        if (!$val) {
            return '';
        }

        return Carbon::parse($val)->format('Y-m-d');
    }

    public static function formatTime(mixed $val): string
    {
        if (!$val) {
            return '';
        }

        return Carbon::parse($val)->format('H:i');
    }

    public static function parentDocTypes()
    {
        return DB::table('dcs_doc_types')
            ->whereNull('parent_id')
            ->orderBy('id')
            ->get(['id', 'doc_type_name']);
    }

    public static function visibleRequestIds(): array
    {
        $latestIds = collect(DB::select("
            SELECT request_id FROM (
                SELECT
                    ml.request_id,
                    ROW_NUMBER() OVER (
                        PARTITION BY ml.doc_no, dr.doc_type_id, COALESCE(dr.sub_type_id, 0)
                        ORDER BY ml.revise_no DESC
                    ) AS rn
                FROM dcs_masterlist_registration ml
                JOIN dcs_document_requests dr ON ml.request_id = dr.id
                WHERE ml.doc_no IS NOT NULL AND ml.doc_no != ''
            ) ranked
            WHERE rn = 1
        "))->pluck('request_id');

        $noMlIds = DB::table('dcs_document_requests as dr')
            ->leftJoin('dcs_masterlist_registration as ml', 'ml.request_id', '=', 'dr.id')
            ->where(function ($q) {
                $q->whereNull('ml.id')
                    ->orWhereNull('ml.doc_no')
                    ->orWhere('ml.doc_no', '');
            })
            ->pluck('dr.id');

        return $latestIds->merge($noMlIds)->unique()->values()->all();
    }

    public static function updateList(string $search, string $docTypeId, int $page, int $perPage = 15): array
    {
        $visibleIds = self::visibleRequestIds();
        if ($visibleIds === []) {
            return [
                'rows' => [],
                'total' => 0,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $perPage,
            ];
        }

        $query = DB::table('dcs_document_requests as dr')
            ->leftJoin('dcs_doc_types as dt', 'dt.id', '=', 'dr.doc_type_id')
            ->leftJoin('dcs_masterlist_registration as ml', 'ml.request_id', '=', 'dr.id')
            ->leftJoin('dcs_document_request_form as drf', 'drf.request_id', '=', 'dr.id')
            ->leftJoin('dcs_document_change_notice as dcn', 'dcn.request_id', '=', 'dr.id')
            ->leftJoin('dcs_document_retrieval as ret', 'ret.request_id', '=', 'dr.id')
            ->leftJoin('dcs_document_distribution as dist', 'dist.request_id', '=', 'dr.id')
            ->whereIn('dr.id', $visibleIds)
            ->orderByDesc('dr.id')
            ->select([
                'dr.id',
                'dt.doc_type_name',
                'ml.id as ml_id',
                'ml.doc_no',
                'ml.doc_title as ml_title',
                'ml.revise_no',
                'drf.doc_title as drf_title',
                'drf.id as drf_id',
                'dcn.id as dcn_id',
                'ret.id as ret_id',
                'dist.id as dist_id',
            ]);

        if ($docTypeId !== '' && $docTypeId !== 'all') {
            $query->where('dr.doc_type_id', (int) $docTypeId);
        }

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('dr.id::text ilike ?', [$like])
                    ->orWhere('ml.doc_no', 'ilike', $like)
                    ->orWhere('ml.doc_title', 'ilike', $like)
                    ->orWhere('drf.drf_no', 'ilike', $like)
                    ->orWhere('drf.doc_title', 'ilike', $like)
                    ->orWhere('dcn.dcn_no', 'ilike', $like);
            });
        }

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        $documents = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $checklistNames = [
            1 => 'Document Request Form',
            2 => 'Document Change Notice',
            3 => 'Masterlist',
            4 => 'Retrieval',
            5 => 'Distribution',
        ];
        foreach (DB::table('dcs_checklist_types')->get(['id', 'checklist_name']) as $row) {
            $checklistNames[$row->id] = $row->checklist_name;
        }

        $rows = $documents->map(function ($doc) use ($checklistNames) {
            $docNo = $doc->doc_no;
            $title = $doc->ml_title ?: ($doc->drf_title ?: 'N/A');
            $checklists = [];
            if ($doc->drf_id) {
                $checklists[] = $checklistNames[1] ?? 'DRF';
            }
            if ($doc->dcn_id) {
                $checklists[] = $checklistNames[2] ?? 'DCN';
            }
            if ($doc->ml_id) {
                $checklists[] = $checklistNames[3] ?? 'Masterlist';
            }
            if ($doc->ret_id) {
                $checklists[] = $checklistNames[4] ?? 'Retrieval';
            }
            if ($doc->dist_id) {
                $checklists[] = $checklistNames[5] ?? 'Distribution';
            }

            return [
                'request_id' => $doc->id,
                'doc_no' => $docNo ?: 'N/A',
                'title' => $title,
                'rev_no' => (int) ($doc->revise_no ?? 0),
                'doc_type' => $doc->doc_type_name ?? 'N/A',
                'checklists' => $checklists,
                'edit_url' => route('dcs.register.edit', $doc->id),
                'history_url' => $docNo ? route('dcs.register.history', $docNo) : null,
            ];
        })->all();

        return [
            'rows' => $rows,
            'total' => $total,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
        ];
    }

    public static function history(string $docNo): array
    {
        $mls = DB::table('dcs_masterlist_registration as ml')
            ->join('dcs_document_requests as dr', 'dr.id', '=', 'ml.request_id')
            ->leftJoin('dcs_doc_types as dt', 'dt.id', '=', 'dr.doc_type_id')
            ->leftJoin('dcs_document_request_form as drf', 'drf.request_id', '=', 'dr.id')
            ->leftJoin('dcs_document_change_notice as dcn', 'dcn.request_id', '=', 'dr.id')
            ->where('ml.doc_no', $docNo)
            ->orderByDesc('ml.revise_no')
            ->get([
                'dr.id',
                'dr.created_at',
                'ml.doc_title',
                'ml.revise_no',
                'ml.effectivity_date',
                'ml.no_pages',
                'ml.originator_name',
                'ml.brief_purpose',
                'drf.id as drf_id',
                'dcn.id as dcn_id',
            ]);

        if ($mls->isEmpty()) {
            abort(404, 'Document not found.');
        }

        return [
            'docNo' => $docNo,
            'docTitle' => $mls->first()->doc_title ?: $docNo,
            'revisions' => $mls,
        ];
    }

    public static function searchDocuments(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (mb_strlen($q) < 1) {
            return [];
        }

        $visibleIds = self::visibleRequestIds();
        $field = $request->input('field');
        $docTypeId = $request->input('doc_type_id');
        $subTypeId = $request->input('sub_type_id');

        $query = DB::table('dcs_masterlist_registration as ml')
            ->join('dcs_document_requests as dr', 'dr.id', '=', 'ml.request_id')
            ->whereIn('ml.request_id', $visibleIds)
            ->whereNotNull('ml.doc_no')
            ->where('ml.doc_no', '!=', '');

        if ($docTypeId) {
            $query->where('dr.doc_type_id', $docTypeId);
            if ($subTypeId) {
                $query->where('dr.sub_type_id', $subTypeId);
            }
        }

        $query->where(function ($qr) use ($q, $field) {
            if ($field === 'no') {
                $qr->where('ml.doc_no', 'ilike', "%{$q}%");
            } elseif ($field === 'title') {
                $qr->where('ml.doc_title', 'ilike', "%{$q}%");
            } else {
                $qr->where('ml.doc_title', 'ilike', "%{$q}%")
                    ->orWhere('ml.doc_no', 'ilike', "%{$q}%");
            }
        });

        if ($request->filled('exclude_request_id')) {
            $query->where('ml.request_id', '!=', $request->exclude_request_id);
        }

        return $query->orderBy('ml.doc_no')
            ->orderBy('ml.doc_title')
            ->limit(15)
            ->get([
                'ml.id',
                'ml.request_id',
                'ml.doc_no',
                'ml.doc_title',
                'ml.revise_no',
                'ml.effectivity_date',
                'ml.brief_purpose',
                'ml.scanned_masterlist',
            ])
            ->map(function ($m) {
                $docNo = $m->doc_no ?: 'No number';
                $title = $m->doc_title ?: 'Untitled';

                return [
                    'masterlist_id' => $m->id,
                    'request_id' => $m->request_id,
                    'doc_no' => $m->doc_no,
                    'doc_title' => $m->doc_title,
                    'revise_no' => $m->revise_no,
                    'effectivity_date' => $m->effectivity_date ? Carbon::parse($m->effectivity_date)->format('Y-m-d') : null,
                    'brief_purpose' => $m->brief_purpose,
                    'scanned_copy_url' => $m->scanned_masterlist ? Storage::disk('public')->url($m->scanned_masterlist) : null,
                    'scanned_copy_path' => $m->scanned_masterlist,
                    'label' => $docNo . ' — ' . $title . ' (Rev ' . (int) $m->revise_no . ')',
                ];
            })
            ->values()
            ->all();
    }

    public static function checkDocNo(Request $request)
    {
        $docNo = $request->input('doc_no');
        $docTypeId = (int) $request->input('doc_type_id');
        $subTypeId = $request->input('sub_type_id');

        if (!$docNo) {
            return ['exists' => false, 'message' => 'No document number provided.'];
        }

        $result = RegisterPersistHelper::findMatchingRegistrationRows(
            $docNo,
            $docTypeId,
            $subTypeId ? (int) $subTypeId : null
        );

        if ($result['found']) {
            $latest = $result['latest'];
            if ($latest) {
                $latestRev = (int) $latest->revise_no;
                $registrations = DB::table('dcs_masterlist_registration')
                    ->whereIn('request_id', $result['matches']->pluck('id'))
                    ->where('doc_no', $docNo)
                    ->orderByDesc('revise_no')
                    ->get();

                $latestDistribution = DB::table('dcs_document_distribution')
                    ->where('request_id', $latest->request_id)
                    ->first();
                $latestDistributionOffices = [];
                if ($latestDistribution) {
                    $latestDistributionOffices = DB::table('dcs_distribution_offices as d')
                        ->leftJoin('office as o', 'o.id', '=', 'd.office_id')
                        ->where('d.distribution_id', $latestDistribution->id)
                        ->get([
                            'd.office_id',
                            'o.office_name',
                            'd.copies',
                        ])
                        ->map(fn ($o) => [
                            'office_id' => $o->office_id,
                            'office_name' => $o->office_name ?? 'Unknown Office',
                            'copies' => $o->copies ?? 1,
                        ])
                        ->values();
                }

                $sourceOffices = DB::table('dcs_masterlist_source_offices as s')
                    ->leftJoin('office as o', 'o.id', '=', 's.office_id')
                    ->where('s.masterlist_id', $latest->id)
                    ->get(['o.office_name']);
                $latestSourceUnit = $sourceOffices->pluck('office_name')->filter()->implode(', ');

                $relatedIds = DB::table('dcs_masterlist_related_docs')
                    ->where('masterlist_id', $latest->id)
                    ->pluck('related_doc_id');
                $latestRelatedDocs = DB::table('dcs_masterlist_registration')
                    ->whereIn('id', $relatedIds)
                    ->get(['id', 'doc_no', 'doc_title'])
                    ->map(fn ($d) => [
                        'masterlist_id' => $d->id,
                        'doc_no' => $d->doc_no,
                        'doc_title' => $d->doc_title,
                    ])
                    ->values();

                return [
                    'exists' => true,
                    'message' => 'Document found.',
                    'next_rev' => $latestRev + 1,
                    'latest_rev' => $latestRev,
                    'latest_title' => $latest->doc_title,
                    'latest_originator' => $latest->originator_name,
                    'revision_count' => $registrations->count(),
                    'latest_distribution_offices' => $latestDistributionOffices,
                    'latest_source_unit' => $latestSourceUnit,
                    'latest_effectivity_date' => $latest->effectivity_date ? Carbon::parse($latest->effectivity_date)->format('Y-m-d') : null,
                    'latest_no_pages' => $latest->no_pages,
                    'latest_deadline' => $latest->deadline ? Carbon::parse($latest->deadline)->format('Y-m-d') : null,
                    'latest_brief_purpose' => $latest->brief_purpose,
                    'latest_related_documents' => $latestRelatedDocs,
                ];
            }
        }

        if (($result['reason'] ?? '') === 'not_registered') {
            $hasSubType = $subTypeId && (int) $subTypeId > 0;
            $message = 'This document number is not registered';
            if ($hasSubType) {
                $subType = RegisterPersistHelper::dcsDocType($subTypeId);
                $message .= ' under "' . ($subType ? $subType->doc_type_name : 'this sub-type') . '"';
            } elseif ($docTypeId) {
                $type = RegisterPersistHelper::dcsDocType($docTypeId);
                $message .= ' under "' . ($type ? $type->doc_type_name : 'this document type') . '"';
            }
            $message .= '. Please register it as a New Document first.';

            return ['exists' => false, 'message' => $message, 'next_rev' => null];
        }

        $existingDr = $result['existing_dr'] ?? null;
        if (($result['reason'] ?? '') === 'wrong_subtype') {
            $existingSubType = RegisterPersistHelper::dcsDocType($existingDr->sub_type_id ?? null);

            return [
                'exists' => false,
                'wrong_subtype' => true,
                'existing_subtype_name' => $existingSubType ? $existingSubType->doc_type_name : 'Unknown',
                'message' => RegisterPersistHelper::mismatchErrorMessageFromRow($docNo, $result),
                'next_rev' => null,
            ];
        }

        $existingType = RegisterPersistHelper::dcsDocType($existingDr->doc_type_id ?? null);

        return [
            'exists' => false,
            'wrong_type' => true,
            'existing_type_name' => $existingType ? $existingType->doc_type_name : 'Unknown',
            'message' => RegisterPersistHelper::mismatchErrorMessageFromRow($docNo, $result),
            'next_rev' => null,
        ];
    }

    public static function editPayload(int $id): array
    {
        $docRequest = DB::table('dcs_document_requests')->where('id', $id)->first();
        abort_unless($docRequest, 404);

        $ml = DB::table('dcs_masterlist_registration')->where('request_id', $id)->first();
        if ($ml && $ml->doc_no) {
            $sameTypeRequestIds = self::requestIdsWithSameDocType($docRequest);
            $latestRev = DB::table('dcs_masterlist_registration')
                ->where('doc_no', $ml->doc_no)
                ->whereIn('request_id', $sameTypeRequestIds)
                ->max('revise_no');
            if ((int) $ml->revise_no < (int) $latestRev) {
                return [
                    'blocked' => true,
                    'error' => "Only the latest revision (Rev {$latestRev}) can be edited.",
                ];
            }
        }

        $drf = DB::table('dcs_document_request_form')->where('request_id', $id)->first();
        $drfOffices = $drf
            ? DB::table('dcs_drf_offices as d')
                ->leftJoin('office as o', 'o.id', '=', 'd.office_id')
                ->where('d.document_request_form_id', $drf->id)
                ->get(['d.office_id', 'o.office_name'])
            : collect();

        $dcn = DB::table('dcs_document_change_notice')->where('request_id', $id)->first();
        $dcnOfficeName = null;
        if ($dcn && $dcn->office_id) {
            $dcnOfficeName = DB::table('office')->where('id', $dcn->office_id)->value('office_name');
        }
        $dcnOffices = $dcn
            ? DB::table('dcs_dcn_offices as d')
                ->leftJoin('office as o', 'o.id', '=', 'd.office_id')
                ->where('d.dcn_id', $dcn->id)
                ->get(['d.office_id', 'o.office_name'])
            : collect();
        $revisions = $dcn
            ? DB::table('dcs_doc_revision')->where('dcn_id', $dcn->id)->get()
            : collect();

        $retrieval = DB::table('dcs_document_retrieval')->where('request_id', $id)->first();
        $retrievalOffices = $retrieval
            ? DB::table('dcs_retrieval_offices as r')
                ->leftJoin('office as o', 'o.id', '=', 'r.office_id')
                ->where('r.retrieval_id', $retrieval->id)
                ->get(['r.office_id', 'r.copies', 'o.office_name'])
            : collect();

        $distribution = DB::table('dcs_document_distribution')->where('request_id', $id)->first();
        $distributionOffices = $distribution
            ? DB::table('dcs_distribution_offices as d')
                ->leftJoin('office as o', 'o.id', '=', 'd.office_id')
                ->where('d.distribution_id', $distribution->id)
                ->get(['d.office_id', 'd.copies', 'o.office_name'])
            : collect();

        $approval = DB::table('dcs_approval_records')->where('request_id', $id)->first();

        $sourceOffices = collect();
        if ($ml) {
            $sourceOffices = DB::table('dcs_masterlist_source_offices as s')
                ->leftJoin('office as o', 'o.id', '=', 's.office_id')
                ->where('s.masterlist_id', $ml->id)
                ->get(['s.office_id', 'o.office_name']);
        }

        $syllabi = DB::table('dcs_syllabi as s')
            ->leftJoin('dcs_program_courses as pc', 'pc.id', '=', 's.course_id')
            ->where('s.request_id', $id)
            ->orderBy('s.id')
            ->get([
                's.*',
                'pc.course_name',
            ]);

        $syllabiGroupsSeed = $syllabi->map(function ($syl) {
            $drfs = DB::table('dcs_syllabi_drf')->where('syllabi_id', $syl->id)->orderBy('id')->get();
            $copies = max(1, (int) $syl->no_copies);
            $rows = [];
            if ($copies === 1) {
                $firstDrf = $drfs->first();
                $rows[] = [
                    'faculty' => $drfs->pluck('faculty_name')->filter()->implode(', '),
                    'date_received' => self::formatDate($syl->date_received),
                    'time_received' => self::formatTime($syl->time_received),
                    'drf_available' => self::pgBool($firstDrf->is_drf_available ?? false),
                    'drf_no' => $firstDrf->drf_no ?? null,
                    'drf_date' => self::formatDate($firstDrf->drf_date ?? null),
                    'drf_received_date' => self::formatDate($firstDrf->drf_received_date ?? null),
                    'scanned_drf' => $firstDrf->scanned_drf ?? null,
                    'scanned_drf_name' => !empty($firstDrf->scanned_drf ?? null) ? basename($firstDrf->scanned_drf) : null,
                ];
            } else {
                foreach ($drfs->take($copies) as $drf) {
                    $rows[] = [
                        'faculty' => $drf->faculty_name,
                        'date_received' => self::formatDate($syl->date_received),
                        'time_received' => self::formatTime($syl->time_received),
                        'drf_available' => self::pgBool($drf->is_drf_available),
                        'drf_no' => $drf->drf_no,
                        'drf_date' => self::formatDate($drf->drf_date),
                        'drf_received_date' => self::formatDate($drf->drf_received_date),
                        'scanned_drf' => $drf->scanned_drf,
                        'scanned_drf_name' => $drf->scanned_drf ? basename($drf->scanned_drf) : null,
                    ];
                }
            }

            return [
                'course_name' => $syl->course_name ?? '',
                'availability' => self::pgBool($syl->is_available),
                'no_pages' => $syl->no_pages,
                'copies' => $copies,
                'college_id' => $syl->college_id,
                'program_id' => $syl->program_id,
                'semester_id' => $syl->semester_id,
                'school_year_id' => $syl->school_year_id,
                'rows' => $rows,
            ];
        })->values();

        $dcnOfficesSeed = $dcnOffices->map(fn ($o) => [
            'id' => $o->office_id,
            'label' => $o->office_name ?? 'Unknown',
        ])->values();
        if ($dcnOfficesSeed->isEmpty() && $dcn && $dcn->office_id) {
            $dcnOfficesSeed = collect([[
                'id' => $dcn->office_id,
                'label' => $dcnOfficeName ?? 'Unknown',
            ]]);
        }

        $relatedDocsData = collect();
        if ($ml) {
            $relatedIds = DB::table('dcs_masterlist_related_docs')
                ->where('masterlist_id', $ml->id)
                ->pluck('related_doc_id');
            $relatedDocsData = DB::table('dcs_masterlist_registration')
                ->whereIn('id', $relatedIds)
                ->get(['id', 'doc_no', 'doc_title'])
                ->map(fn ($m) => [
                    'masterlist_id' => $m->id,
                    'doc_no' => $m->doc_no,
                    'doc_title' => $m->doc_title,
                    'label' => $m->doc_title . ($m->doc_no ? ' (' . $m->doc_no . ')' : ''),
                ]);
        }

        return [
            'blocked' => false,
            'docRequest' => $docRequest,
            'drf' => $drf,
            'dcn' => $dcn,
            'revisions' => $revisions,
            'masterlist' => $ml,
            'retrieval' => $retrieval,
            'retrievalOffices' => $retrievalOffices,
            'distribution' => $distribution,
            'distributionOffices' => $distributionOffices,
            'approval' => $approval,
            'drfOfficesSeed' => $drfOffices->map(fn ($o) => [
                'id' => $o->office_id,
                'label' => $o->office_name ?? 'Unknown',
            ])->values(),
            'dcnOfficesSeed' => $dcnOfficesSeed,
            'masterlistSourceSeed' => $sourceOffices->map(fn ($o) => [
                'type' => 'office',
                'id' => $o->office_id,
                'label' => $o->office_name ?? 'Unknown',
            ])->filter(fn ($o) => $o['id'])->values(),
            'masterlistOriginatorSeed' => ($ml && $ml->originator_name)
                ? [['label' => $ml->originator_name]]
                : [],
            'syllabiGroupsSeed' => $syllabiGroupsSeed,
            'syllabiContextSeed' => $syllabi->first(),
            'isSyllabiLikeEdit' => RegisterPersistHelper::isSyllabiLikeSubTypeRow(
                RegisterPersistHelper::dcsDocType($docRequest->sub_type_id)
            ),
            'relatedDocsData' => $relatedDocsData,
        ];
    }

    public static function hydrateRequests(Collection $docs): Collection
    {
        if ($docs->isEmpty()) {
            return $docs;
        }

        $ids = $docs->pluck('id')->all();
        $typeIds = $docs->pluck('doc_type_id')->merge($docs->pluck('sub_type_id'))->filter()->unique()->all();
        $types = $typeIds
            ? DB::table('dcs_doc_types')->whereIn('id', $typeIds)->get()->keyBy('id')
            : collect();

        $mls = DB::table('dcs_masterlist_registration')->whereIn('request_id', $ids)->get()->keyBy('request_id');
        $mlIds = $mls->pluck('id')->all();

        $sourceByMl = collect();
        $relatedByMl = collect();
        if ($mlIds) {
            $sourceByMl = DB::table('dcs_masterlist_source_offices as so')
                ->leftJoin('office as o', 'o.id', '=', 'so.office_id')
                ->whereIn('so.masterlist_id', $mlIds)
                ->get(['so.masterlist_id', 'so.office_id', 'o.office_name'])
                ->groupBy('masterlist_id')
                ->map(fn ($rows) => $rows->map(function ($row) {
                    $row->office = (object) ['office_name' => $row->office_name];

                    return $row;
                }));

            $relatedA = DB::table('dcs_masterlist_related_docs as rd')
                ->join('dcs_masterlist_registration as rel', 'rel.id', '=', 'rd.related_doc_id')
                ->whereIn('rd.masterlist_id', $mlIds)
                ->get(['rd.masterlist_id', 'rel.doc_no', 'rel.doc_title']);
            $relatedB = DB::table('dcs_masterlist_related_docs as rd')
                ->join('dcs_masterlist_registration as rel', 'rel.id', '=', 'rd.masterlist_id')
                ->whereIn('rd.related_doc_id', $mlIds)
                ->get(['rd.related_doc_id as masterlist_id', 'rel.doc_no', 'rel.doc_title']);
            $relatedByMl = $relatedA->concat($relatedB)->groupBy('masterlist_id');
        }

        $drfs = DB::table('dcs_document_request_form')->whereIn('request_id', $ids)->get()->keyBy('request_id');
        $dcns = DB::table('dcs_document_change_notice')->whereIn('request_id', $ids)->get()->keyBy('request_id');
        $dcnIds = $dcns->pluck('id')->all();
        $revsByDcn = $dcnIds
            ? DB::table('dcs_doc_revision')->whereIn('dcn_id', $dcnIds)->orderBy('id')->get()->groupBy('dcn_id')
            : collect();

        $dists = DB::table('dcs_document_distribution')->whereIn('request_id', $ids)->get()->keyBy('request_id');
        $distIds = $dists->pluck('id')->all();
        $distOffices = $distIds
            ? DB::table('dcs_distribution_offices as dof')
                ->leftJoin('office as o', 'o.id', '=', 'dof.office_id')
                ->whereIn('dof.distribution_id', $distIds)
                ->get(['dof.distribution_id', 'dof.office_id', 'o.office_name'])
                ->groupBy('distribution_id')
                ->map(fn ($rows) => $rows->map(function ($row) {
                    $row->office = (object) ['office_name' => $row->office_name];

                    return $row;
                }))
            : collect();

        $rets = DB::table('dcs_document_retrieval')->whereIn('request_id', $ids)->get()->keyBy('request_id');
        $retIds = $rets->pluck('id')->all();
        $retOffices = $retIds
            ? DB::table('dcs_retrieval_offices as rof')
                ->leftJoin('office as o', 'o.id', '=', 'rof.office_id')
                ->whereIn('rof.retrieval_id', $retIds)
                ->get(['rof.retrieval_id', 'rof.office_id', 'o.office_name'])
                ->groupBy('retrieval_id')
                ->map(fn ($rows) => $rows->map(function ($row) {
                    $row->office = (object) ['office_name' => $row->office_name];

                    return $row;
                }))
            : collect();

        $approvals = DB::table('dcs_approval_records')->whereIn('request_id', $ids)->get()->groupBy('request_id');
        $stamps = DB::table('dcs_document_stamps')->whereIn('document_request_id', $ids)->get()->groupBy('document_request_id');

        $syllabi = DB::table('dcs_syllabi as s')
            ->leftJoin('dcs_program_courses as c', 'c.id', '=', 's.course_id')
            ->whereIn('s.request_id', $ids)
            ->get(['s.*', 'c.course_name']);
        $sylIds = $syllabi->pluck('id')->all();
        $drfsBySyl = $sylIds
            ? DB::table('dcs_syllabi_drf')->whereIn('syllabi_id', $sylIds)->get()->groupBy('syllabi_id')
            : collect();
        $syllabiByReq = $syllabi->groupBy('request_id')->map(fn ($rows) => $rows->map(function ($row) use ($drfsBySyl) {
            $row->course = (object) ['course_name' => $row->course_name];
            $row->drfs = $drfsBySyl->get($row->id, collect());

            return $row;
        }));

        foreach ($docs as $doc) {
            $doc->docType = $types->get($doc->doc_type_id);
            $doc->subType = $types->get($doc->sub_type_id);
            $ml = $mls->get($doc->id);
            if ($ml) {
                $ml->sourceOffices = $sourceByMl->get($ml->id, collect());
                $ml->relatedList = $relatedByMl->get($ml->id, collect())->unique(fn ($r) => $r->doc_no . '|' . $r->doc_title)->values();
            }
            $doc->masterlistRegistration = $ml;
            $doc->documentRequestForm = $drfs->get($doc->id);
            $dcn = $dcns->get($doc->id);
            if ($dcn) {
                $dcn->revisions = $revsByDcn->get($dcn->id, collect());
            }
            $doc->documentChangeNotice = $dcn;
            $dist = $dists->get($doc->id);
            if ($dist) {
                $dist->offices = $distOffices->get($dist->id, collect());
            }
            $doc->documentDistribution = $dist;
            $ret = $rets->get($doc->id);
            if ($ret) {
                $ret->offices = $retOffices->get($ret->id, collect());
            }
            $doc->documentRetrieval = $ret;
            $doc->approvalRecords = $approvals->get($doc->id, collect());
            $doc->stamps = $stamps->get($doc->id, collect());
            $doc->syllabi = $syllabiByReq->get($doc->id, collect());
        }

        return $docs;
    }

    public static function hydrateMasterlists(Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return $records;
        }

        $requestIds = $records->pluck('request_id')->filter()->unique()->all();
        $requests = $requestIds
            ? self::hydrateRequests(DB::table('dcs_document_requests')->whereIn('id', $requestIds)->get())
                ->keyBy(fn ($row) => (int) $row->id)
            : collect();
        $typeIds = $records->pluck('doc_type_id')->filter()->unique()->all();
        $types = $typeIds
            ? DB::table('dcs_doc_types')->whereIn('id', $typeIds)->get()->keyBy('id')
            : collect();
        $mlIds = $records->pluck('id')->all();
        $sourceByMl = DB::table('dcs_masterlist_source_offices as so')
            ->leftJoin('office as o', 'o.id', '=', 'so.office_id')
            ->whereIn('so.masterlist_id', $mlIds)
            ->get(['so.masterlist_id', 'so.office_id', 'o.office_name'])
            ->groupBy('masterlist_id')
            ->map(fn ($rows) => $rows->map(function ($row) {
                $row->office = (object) ['office_name' => $row->office_name];

                return $row;
            }));

        foreach ($records as $ml) {
            $ml->request = $requests->get((int) $ml->request_id);
            $ml->docType = $types->get($ml->doc_type_id);
            $ml->sourceOffices = $sourceByMl->get($ml->id, collect());
        }

        return $records;
    }

    /** JSON-shaped catalog matching the Register Alpine/UI keys. */
    public static function jsCatalog(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $docTypes = DB::table('dcs_doc_types')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'doc_type_name'])
            ->map(fn ($d) => [
                'doc_type_id' => $d->id,
                'parent_id' => $d->parent_id,
                'doc_type_name' => $d->doc_type_name,
                'is_syllabi_like' => self::isSyllabiLikeName($d->doc_type_name),
            ])
            ->values()
            ->all();

        $checklistsByVersion = [];
        $rows = DB::table('dcs_checklist_version as cv')
            ->join('dcs_checklist_types as ct', 'ct.id', '=', 'cv.checklist_id')
            ->orderBy('ct.id')
            ->get([
                'cv.version_id',
                'ct.id as checklist_id',
                'ct.checklist_name',
            ]);

        foreach ($rows as $row) {
            $checklistsByVersion[(string) $row->version_id][] = [
                'checklist_id' => $row->checklist_id,
                'checklist_name' => $row->checklist_name,
            ];
        }

        $programsByCollege = [];
        foreach (DB::table('dcs_programs')->orderBy('program_name')->get(['id', 'college_id', 'program_name', 'program_code']) as $p) {
            $programsByCollege[(string) $p->college_id][] = [
                'program_id' => $p->id,
                'program_name' => $p->program_name,
                'program_code' => $p->program_code,
            ];
        }

        $coursesByProgramSemester = [];
        foreach (DB::table('dcs_program_courses')->orderBy('course_name')->get(['id', 'program_id', 'semester_id', 'course_name']) as $c) {
            $coursesByProgramSemester[$c->program_id . ':' . $c->semester_id][] = [
                'id' => $c->id,
                'course_name' => $c->course_name,
            ];
        }

        return $cache = [
            'offices' => DB::table('office')
                ->where('is_active', true)
                ->orderBy('office_name')
                ->get(['id', 'office_name'])
                ->map(fn ($o) => [
                    'office_id' => $o->id,
                    'office_name' => $o->office_name,
                ])
                ->values()
                ->all(),
            'docTypes' => $docTypes,
            'versionTypes' => DB::table('dcs_version_type')
                ->orderBy('version_name')
                ->get(['id', 'version_name'])
                ->map(fn ($v) => [
                    'version_id' => $v->id,
                    'version_name' => $v->version_name,
                ])
                ->values()
                ->all(),
            'approvalBodies' => DB::table('dcs_approval_body')
                ->orderBy('approval_name')
                ->get(['id', 'approval_name'])
                ->map(fn ($a) => [
                    'approval_body_id' => $a->id,
                    'approval_name' => $a->approval_name,
                ])
                ->values()
                ->all(),
            'originators' => DB::table('dcs_originators')
                ->orderBy('originator_name')
                ->get(['id', 'originator_name'])
                ->map(fn ($o) => [
                    'originator_id' => $o->id,
                    'originator_name' => $o->originator_name,
                ])
                ->values()
                ->all(),
            'checklistsByVersion' => $checklistsByVersion,
            'colleges' => DB::table('dcs_colleges')
                ->orderBy('college_name')
                ->get(['id', 'college_name'])
                ->map(fn ($c) => [
                    'college_id' => $c->id,
                    'college_name' => $c->college_name,
                ])
                ->values()
                ->all(),
            'semesters' => DB::table('dcs_semesters')
                ->orderBy('id')
                ->get(['id', 'semester_name'])
                ->map(fn ($s) => [
                    'semester_id' => $s->id,
                    'semester_name' => $s->semester_name,
                ])
                ->values()
                ->all(),
            'schoolYears' => DB::table('dcs_school_years')
                ->orderBy('school_year')
                ->get(['id', 'school_year'])
                ->map(fn ($y) => [
                    'school_year_id' => $y->id,
                    'school_year' => $y->school_year,
                ])
                ->values()
                ->all(),
            'programsByCollege' => $programsByCollege,
            'coursesByProgramSemester' => $coursesByProgramSemester,
            'faculties' => DB::table('dcs_faculties')
                ->orderBy('faculty_name')
                ->get(['id', 'faculty_name', 'college_id'])
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'faculty_name' => $f->faculty_name,
                    'college_id' => $f->college_id,
                ])
                ->values()
                ->all(),
        ];
    }
}
