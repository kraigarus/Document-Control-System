<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SampleDocumentsSeeder extends Seeder
{
    private const PREFIX = 'SAMPLE-';

    public function run(): void
    {
        $createdBy = DB::table('account')->where('username', 'admin')->value('id')
            ?? DB::table('account')->orderBy('id')->value('id');

        if (!$createdBy) {
            $this->command?->error('No account found. Run migrations first so the admin account exists.');

            return;
        }

        $sampleRows = DB::table('dcs_masterlist_registration as mr')
            ->join('dcs_version_type as vt', 'vt.id', '=', 'mr.version_id')
            ->where('mr.doc_no', 'like', self::PREFIX . '%')
            ->get(['mr.doc_no', 'vt.version_name']);

        $newDocNos = $sampleRows->where('version_name', 'New')->pluck('doc_no')->unique();
        $revisedDocNos = $sampleRows->where('version_name', 'Revised')->pluck('doc_no')->unique();
        $revisedHaveParent = $revisedDocNos->isNotEmpty()
            && $revisedDocNos->every(fn ($docNo) => $newDocNos->contains($docNo));

        if ($newDocNos->count() >= 50 && $revisedHaveParent) {
            $this->command?->info(
                "Sample documents already present ({$newDocNos->count()} new, {$revisedDocNos->count()} revised of those same doc nos). Skipping."
            );

            return;
        }

        $this->purgeExistingSamples();

        $versionNew = (int) (DB::table('dcs_version_type')->where('version_name', 'New')->value('id') ?? 1);
        $versionRevised = (int) (DB::table('dcs_version_type')->where('version_name', 'Revised')->value('id') ?? 2);
        $officeIds = DB::table('office')->where('is_active', true)->orderBy('id')->pluck('id')->all();
        $originators = DB::table('dcs_originators')->orderBy('id')->pluck('originator_name')->all();
        $approvalBodies = DB::table('dcs_approval_body')->orderBy('id')->pluck('id')->all();
        $subTypesByParent = DB::table('dcs_doc_types')
            ->whereNotNull('parent_id')
            ->orderBy('id')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();

        if ($officeIds === [] || $originators === []) {
            $this->command?->error('Offices or originators are missing. Run migrations first.');

            return;
        }

        $samples = $this->samples();
        $now = now();
        $ctx = compact(
            'createdBy', 'versionNew', 'versionRevised',
            'officeIds', 'originators', 'approvalBodies', 'subTypesByParent', 'now'
        );

        DB::transaction(function () use ($samples, $ctx) {
            foreach ($samples as $i => $sample) {
                $this->insertSample($sample, $i + 1, $ctx, false);
            }

            foreach ($samples as $i => $sample) {
                $n = $i + 1;
                if ($n % 5 !== 0) {
                    continue;
                }

                $this->insertSample($sample, $n, $ctx, true);
            }
        });

        $this->command?->info('Inserted 50 new sample documents and 10 revised versions of those same doc nos (SAMPLE- prefix).');
    }

    private function insertSample(array $sample, int $n, array $ctx, bool $isRevised): void
    {
        [
            'createdBy' => $createdBy,
            'versionNew' => $versionNew,
            'versionRevised' => $versionRevised,
            'officeIds' => $officeIds,
            'originators' => $originators,
            'approvalBodies' => $approvalBodies,
            'subTypesByParent' => $subTypesByParent,
            'now' => $now,
        ] = $ctx;

        $versionId = $isRevised ? $versionRevised : $versionNew;
        $reviseNo = $isRevised ? 1 : 0;
        $seq = $isRevised ? $n + 100 : $n;
        $approvalStatus = $n % 5 === 0 ? 'not_applicable' : 'applicable';
        $subTypeId = $this->pickSubType($sample['doc_type_id'], $n, $subTypesByParent);
        $originator = $originators[$n % count($originators)];
        $sourceOffice = $officeIds[$n % count($officeIds)];
        $registered = Carbon::parse('2026-01-06')->addDays($n * 3);
        if ($isRevised) {
            $registered = $registered->copy()->addMonths(3);
        }
        $received = $registered->copy()->subDays(2);
        $effectivity = $registered->copy()->addDays(14);
        $title = $isRevised ? $sample['title'] . ' (Rev 1)' : $sample['title'];
        $purpose = $isRevised
            ? 'Revision of existing document ' . $sample['doc_no'] . '. ' . $sample['purpose']
            : $sample['purpose'];

        $requestId = DB::table('dcs_document_requests')->insertGetId([
            'version_id' => $versionId,
            'doc_type_id' => $sample['doc_type_id'],
            'sub_type_id' => $subTypeId,
            'approval_status' => $approvalStatus,
            'created_by' => $createdBy,
            'created_at' => $registered,
            'updated_at' => $now,
        ]);

        $masterlistId = DB::table('dcs_masterlist_registration')->insertGetId([
            'checklist_id' => 3,
            'version_id' => $versionId,
            'request_id' => $requestId,
            'doc_type_id' => $sample['doc_type_id'],
            'doc_no' => $sample['doc_no'],
            'doc_receipt_date' => $received->toDateString(),
            'doc_receipt_time' => '09:00:00',
            'doc_registered_date' => $registered->toDateString(),
            'doc_registered_time' => '10:30:00',
            'time_spent' => 90,
            'doc_title' => $title,
            'effectivity_date' => $effectivity->toDateString(),
            'revise_no' => $reviseNo,
            'no_pages' => 4 + ($n % 24),
            'originator_name' => $originator,
            'deadline' => $effectivity->copy()->addDays(30)->toDateString(),
            'brief_purpose' => $purpose,
            'created_by' => $createdBy,
            'created_at' => $registered,
            'updated_at' => $now,
        ]);

        DB::table('dcs_masterlist_source_offices')->insert([
            'masterlist_id' => $masterlistId,
            'office_id' => $sourceOffice,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $drfId = DB::table('dcs_document_request_form')->insertGetId([
            'checklist_id' => 1,
            'version_id' => $versionId,
            'request_id' => $requestId,
            'doc_type_id' => $sample['doc_type_id'],
            'drf_no' => 'DRF-2026-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'drf_date' => $received->copy()->subDay()->toDateString(),
            'drf_receipt_date' => $received->toDateString(),
            'drf_receipt_time' => '08:45:00',
            'doc_title' => $title,
            'created_by' => $createdBy,
            'created_at' => $registered,
            'updated_at' => $now,
        ]);

        DB::table('dcs_drf_offices')->insert([
            'document_request_form_id' => $drfId,
            'office_id' => $sourceOffice,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($isRevised) {
            $dcnId = DB::table('dcs_document_change_notice')->insertGetId([
                'checklist_id' => 2,
                'version_id' => $versionId,
                'request_id' => $requestId,
                'doc_type_id' => $sample['doc_type_id'],
                'dcn_no' => 'DCN-2026-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'dcn_date' => $received->toDateString(),
                'dcn_receipt_date' => $received->toDateString(),
                'dcn_receipt_time' => '09:15:00',
                'office_id' => $sourceOffice,
                'created_by' => $createdBy,
                'created_at' => $registered,
                'updated_at' => $now,
            ]);

            DB::table('dcs_doc_revision')->insert([
                'dcn_id' => $dcnId,
                'title' => $title,
                'document_no' => $sample['doc_no'],
                'effectivity_date' => $effectivity->toDateString(),
                'revision_no' => $reviseNo,
                'brief_purpose' => $purpose,
                'created_at' => $registered,
            ]);
        }

        if ($n % 2 === 0 || $isRevised) {
            $distId = DB::table('dcs_document_distribution')->insertGetId([
                'checklist_id' => 5,
                'version_id' => $versionId,
                'request_id' => $requestId,
                'doc_type_id' => $sample['doc_type_id'],
                'doc_distribution_date_actual' => $registered->copy()->addDays(1)->toDateString(),
                'doc_distribution_time_actual' => '14:00:00',
                'doc_distribution_date_file' => $registered->copy()->addDays(1)->toDateString(),
                'doc_distribution_time_file' => '13:45:00',
                'time_spent' => 15,
                'remarks' => 'Distributed to concerned offices.',
                'created_by' => $createdBy,
                'created_at' => $registered,
                'updated_at' => $now,
            ]);

            DB::table('dcs_distribution_offices')->insert([
                'distribution_id' => $distId,
                'office_id' => $sourceOffice,
                'copies' => 2,
            ]);
        }

        if ($n % 4 === 0 || $isRevised) {
            $retId = DB::table('dcs_document_retrieval')->insertGetId([
                'checklist_id' => 4,
                'version_id' => $versionId,
                'request_id' => $requestId,
                'doc_type_id' => $sample['doc_type_id'],
                'doc_retrieval_date_actual' => $registered->copy()->addDays(5)->toDateString(),
                'doc_retrieval_time_actual' => '11:00:00',
                'doc_retrieval_date_file' => $registered->copy()->addDays(5)->toDateString(),
                'doc_retrieval_time_file' => '10:50:00',
                'time_spent' => 10,
                'remarks' => $isRevised ? 'Obsolete copies retrieved after revision.' : 'Obsolete copies retrieved.',
                'created_by' => $createdBy,
                'created_at' => $registered,
                'updated_at' => $now,
            ]);

            DB::table('dcs_retrieval_offices')->insert([
                'retrieval_id' => $retId,
                'office_id' => $sourceOffice,
                'copies' => 1,
            ]);
        }

        if ($approvalStatus === 'applicable' && $approvalBodies !== []) {
            DB::table('dcs_approval_records')->insert([
                'version_id' => $versionId,
                'request_id' => $requestId,
                'doc_type_id' => $sample['doc_type_id'],
                'approval_body_id' => $approvalBodies[$n % count($approvalBodies)],
                'approval_date' => $registered->copy()->subDays(1)->toDateString(),
                'approval_no' => 'APR-2026-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            ]);
        }
    }

    private function purgeExistingSamples(): void
    {
        $requestIds = DB::table('dcs_masterlist_registration')
            ->where('doc_no', 'like', self::PREFIX . '%')
            ->pluck('request_id')
            ->filter()
            ->unique()
            ->all();

        if ($requestIds === []) {
            return;
        }

        $drfIds = DB::table('dcs_document_request_form')->whereIn('request_id', $requestIds)->pluck('id');
        $dcnIds = DB::table('dcs_document_change_notice')->whereIn('request_id', $requestIds)->pluck('id');
        $distIds = DB::table('dcs_document_distribution')->whereIn('request_id', $requestIds)->pluck('id');
        $retIds = DB::table('dcs_document_retrieval')->whereIn('request_id', $requestIds)->pluck('id');
        $mlIds = DB::table('dcs_masterlist_registration')->whereIn('request_id', $requestIds)->pluck('id');

        if ($drfIds->isNotEmpty()) {
            DB::table('dcs_drf_offices')->whereIn('document_request_form_id', $drfIds)->delete();
        }
        if ($dcnIds->isNotEmpty()) {
            DB::table('dcs_doc_revision')->whereIn('dcn_id', $dcnIds)->delete();
            if (Schema::hasTable('dcs_dcn_offices')) {
                DB::table('dcs_dcn_offices')->whereIn('dcn_id', $dcnIds)->delete();
            }
        }
        if ($distIds->isNotEmpty()) {
            DB::table('dcs_distribution_offices')->whereIn('distribution_id', $distIds)->delete();
        }
        if ($retIds->isNotEmpty()) {
            DB::table('dcs_retrieval_offices')->whereIn('retrieval_id', $retIds)->delete();
        }
        if ($mlIds->isNotEmpty()) {
            DB::table('dcs_masterlist_source_offices')->whereIn('masterlist_id', $mlIds)->delete();
            DB::table('dcs_masterlist_related_docs')->where(function ($q) use ($mlIds) {
                $q->whereIn('masterlist_id', $mlIds)->orWhereIn('related_doc_id', $mlIds);
            })->delete();
        }

        if (Schema::hasTable('dcs_syllabi')) {
            $sylIds = DB::table('dcs_syllabi')->whereIn('request_id', $requestIds)->pluck('id');
            if ($sylIds->isNotEmpty() && Schema::hasTable('dcs_syllabi_drf')) {
                DB::table('dcs_syllabi_drf')->whereIn('syllabi_id', $sylIds)->delete();
            }
            DB::table('dcs_syllabi')->whereIn('request_id', $requestIds)->delete();
        }
        if (Schema::hasTable('dcs_document_stamps')) {
            DB::table('dcs_document_stamps')->whereIn('document_request_id', $requestIds)->delete();
        }

        DB::table('dcs_approval_records')->whereIn('request_id', $requestIds)->delete();
        DB::table('dcs_document_request_form')->whereIn('request_id', $requestIds)->delete();
        DB::table('dcs_document_change_notice')->whereIn('request_id', $requestIds)->delete();
        DB::table('dcs_document_distribution')->whereIn('request_id', $requestIds)->delete();
        DB::table('dcs_document_retrieval')->whereIn('request_id', $requestIds)->delete();
        DB::table('dcs_masterlist_registration')->whereIn('request_id', $requestIds)->delete();
        DB::table('dcs_document_requests')->whereIn('id', $requestIds)->delete();
    }

    private function pickSubType(int $parentId, int $n, array $subTypesByParent): ?int
    {
        $subs = $subTypesByParent[$parentId] ?? [];
        if ($subs === []) {
            return null;
        }

        return $subs[$n % count($subs)];
    }

    private function samples(): array
    {
        $internal = [
            ['Quality Manual', 'Defines the CSPC quality management system scope and processes.'],
            ['Records Control Procedure', 'Establishes how controlled records are created, stored, and disposed.'],
            ['Internal Audit Procedure', 'Guides planning and reporting of internal QMS audits.'],
            ['Corrective Action Procedure', 'Describes how nonconformities are corrected and verified.'],
            ['Document Control Policy', 'Sets rules for numbering, revision, and distribution of controlled documents.'],
            ['Risk Management Procedure', 'Identifies and treats operational and academic risks.'],
            ['Management Review Procedure', 'Covers agenda, inputs, and outputs of management review.'],
            ['Customer Feedback Procedure', 'Collects and acts on client and student feedback.'],
            ['Control of Nonconforming Outputs', 'Handles outputs that fail specified requirements.'],
            ['Continual Improvement Policy', 'Framework for process and service improvement.'],
            ['Work Instruction — Incoming Documents', 'Step-by-step receiving of incoming controlled copies.'],
            ['Work Instruction — Outgoing Documents', 'Step-by-step releasing of controlled copies.'],
            ['FMEA — Records Process', 'Failure modes for records receiving and filing.'],
            ['Quality Objectives — RFIO', 'Annual quality objectives of the Records office.'],
            ['Curriculum Development Guidelines', 'Process for proposing and approving curriculum changes.'],
        ];

        $internalForms = [
            ['Faculty Loading Form', 'Used to record faculty teaching assignments per term.'],
            ['Course Syllabus Template', 'Standard syllabus format for undergraduate courses.'],
            ['TOS Template', 'Table of specifications for major examinations.'],
            ['Rubrics Template', 'Scoring guide for performance-based assessment.'],
            ['Preventive Maintenance Plan Form', 'Schedules equipment maintenance by office.'],
            ['Faculty Profile Form', 'Collects faculty credentials and training records.'],
            ['Classroom Observation Form', 'Used during instructional supervision.'],
            ['Student Consultation Log Form', 'Records faculty-student consultation sessions.'],
            ['Make-up Class Request Form', 'Request to conduct a make-up class session.'],
            ['Grade Submission Form', 'Transmittal of final grades to the registrar.'],
        ];

        $external = [
            ['CHED Memorandum Order — Sample', 'External issuance used as reference for academic policy.'],
            ['DBM Circular — Sample', 'Budget and compensation reference document.'],
            ['CSC MC — Sample', 'Civil service rules applicable to plantilla positions.'],
            ['ISO 21001 Guidance Notes', 'External guidance for educational organization management.'],
            ['COA Circular — Sample', 'Audit and liquidation reference for offices.'],
            ['TESDA Training Regulation — Sample', 'Competency standards for TESDA-related programs.'],
            ['NEDA Guidelines — Sample', 'Planning and investment reference document.'],
            ['DepEd Advisory — Sample', 'External advisory kept for partnership programs.'],
        ];

        $forms = [
            ['Leave Application Form', 'Official form for filing leave of absence.'],
            ['Travel Order Form', 'Authorization for official travel.'],
            ['Purchase Request Form', 'Initiates procurement of supplies and equipment.'],
            ['Job Request Form', 'Request for general services or repairs.'],
            ['Incident Report Form', 'Documents workplace or campus incidents.'],
            ['Visitor Log Form', 'Records visitors entering offices.'],
            ['Property Acknowledgement Receipt', 'Acknowledges issued government property.'],
            ['Liquidation Report Form', 'Liquidates cash advances after official travel.'],
        ];

        $logbooks = [
            ['Incoming Documents Logbook', 'Daily log of documents received by RFIO.'],
            ['Outgoing Documents Logbook', 'Daily log of documents released by RFIO.'],
            ['Visitor Logbook', 'Log of visitors to the Document Control office.'],
            ['Equipment Borrowing Logbook', 'Tracks borrowed office equipment.'],
            ['Key Control Logbook', 'Records issuance and return of office keys.'],
            ['Overtime Logbook', 'Records overtime rendered by personnel.'],
            ['Meeting Attendance Logbook', 'Attendance for unit meetings and briefings.'],
            ['Maintenance Logbook', 'Logs completed maintenance work orders.'],
            ['Security Round Logbook', 'Records security inspection rounds.'],
        ];

        $out = [];
        $packs = [
            [1, 'INT', $internal],
            [2, 'IF', $internalForms],
            [3, 'EXT', $external],
            [4, 'FRM', $forms],
            [5, 'LB', $logbooks],
        ];

        $n = 0;
        while (count($out) < 50) {
            foreach ($packs as [$typeId, $code, $items]) {
                foreach ($items as $j => [$title, $purpose]) {
                    $n++;
                    $out[] = [
                        'doc_type_id' => $typeId,
                        'doc_no' => self::PREFIX . $code . '-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                        'title' => $title . ($n > count($internal) + count($internalForms) + count($external) + count($forms) + count($logbooks) ? ' (' . $n . ')' : ''),
                        'purpose' => $purpose,
                    ];
                    if (count($out) >= 50) {
                        return $out;
                    }
                }
            }
        }

        return $out;
    }
}
