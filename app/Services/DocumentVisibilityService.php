<?php

namespace App\Services;

use App\Models\DocumentRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DocumentVisibilityService
{
    /**
     * Request IDs shown in dashboard/listings: latest revision per doc_no (scoped by
     * type/sub-type), plus requests that do not yet have a masterlist doc number.
     */
    public function getVisibleRequestIds(): Collection
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

        $noMlIds = DocumentRequest::whereDoesntHave('masterlistRegistration')
            ->orWhereHas('masterlistRegistration', function ($q) {
                $q->whereNull('doc_no')->orWhere('doc_no', '');
            })
            ->pluck('id');

        return $latestIds->merge($noMlIds)->unique();
    }
}
