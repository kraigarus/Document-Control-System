<?php

namespace App\Http\Controllers;

use App\Models\DocType;
use App\Models\Office;
use App\Models\VersionType;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestForm;
use App\Models\DocumentChangeNotice;
use App\Models\MasterlistOrigin;
use App\Models\RetrievalOffice;
use App\Models\DistributionOffice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * NOTE ON COLUMN NAME ASSUMPTIONS
 * ─────────────────────────────────
 * These are inferred from how RegisterController / ReportController already
 * use these models:
 *   - offices:        office_id, office_name, status ('active' | 'inactive')
 *   - doc_types:       doc_type_id, doc_type_name, parent_id (nullable — null = top-level type, set = sub-type)
 *   - version_type:    version_id, version_name   ← confirm this column name
 *   - approval_bodies: approval_body_id, name     ← confirm this column name
 *
 * If your migrations use different names for version_type / approval_bodies,
 * just swap the field keys below (search for VERSION_NAME_FIELD / APPROVAL_NAME_FIELD).
 */
class SettingsController extends Controller
{
    private const VERSION_NAME_FIELD  = 'version_name';

    // ──────────────────────────────────────────────────────────
    // INDEX
    // ──────────────────────────────────────────────────────────

    public function index()
    {
        $docTypes = DocType::whereNull('parent_id')
            ->with(['subTypes' => function ($q) {
                $q->orderBy('doc_type_name');
            }])
            ->orderBy('doc_type_name')
            ->get();

        $offices = Office::orderBy('office_name')->get();

        $versionTypes = VersionType::orderBy(self::VERSION_NAME_FIELD)->get();

        return view('pages.dcs.settings.index', compact(
            'docTypes', 'offices', 'versionTypes'
        ));
    }

    // ══════════════════════════════════════════════════════════
    // DOCUMENT TYPES & SUB-TYPES
    // ══════════════════════════════════════════════════════════

    public function storeDocType(Request $request)
    {
        $validated = $request->validate([
            'doc_type_name' => 'required|string|max:255',
            'parent_id'     => 'nullable|integer|exists:doc_types,doc_type_id',
        ]);

        $exists = DocType::where('doc_type_name', $validated['doc_type_name'])
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A ' . (($validated['parent_id'] ?? null) ? 'sub-type' : 'document type') . ' with this name already exists.',
            ], 422);
        }

        $docType = DocType::create($validated);

        return response()->json([
            'success'  => true,
            'message'  => ($validated['parent_id'] ?? null) ? 'Sub-type added.' : 'Document type added.',
            'doc_type' => $docType,
        ]);
    }

    public function updateDocType(Request $request, $id)
    {
        $docType = DocType::findOrFail($id);

        $validated = $request->validate([
            'doc_type_name' => 'required|string|max:255',
        ]);

        $exists = DocType::where('doc_type_name', $validated['doc_type_name'])
            ->where('parent_id', $docType->parent_id)
            ->where('doc_type_id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Another entry with this name already exists at the same level.',
            ], 422);
        }

        $docType->update($validated);

        return response()->json([
            'success'  => true,
            'message'  => 'Updated successfully.',
            'doc_type' => $docType,
        ]);
    }

    public function destroyDocType($id)
    {
        $docType = DocType::findOrFail($id);

        // Block deletion if it has sub-types
        if (DocType::where('parent_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This document type still has sub-types under it. Remove or reassign those first.',
            ], 422);
        }

        // Block deletion if any document request references it
        $inUse = DocumentRequest::where('doc_type_id', $id)
            ->orWhere('sub_type_id', $id)
            ->exists();

        if ($inUse) {
            return response()->json([
                'success' => false,
                'message' => 'This type is already used by one or more registered documents and cannot be deleted.',
            ], 422);
        }

        $docType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully.',
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // OFFICES  (supports active / inactive — inactive offices are
    // excluded from Office::where('status','active') calls used
    // throughout RegisterController, so they simply stop showing
    // up in the registration/retrieval/distribution dropdowns)
    // ══════════════════════════════════════════════════════════

    public function storeOffice(Request $request)
    {
        $validated = $request->validate([
            'office_name' => 'required|string|max:255|unique:offices,office_name',
            'status'      => 'nullable|in:active,inactive',
        ]);

        $validated['status'] = $validated['status'] ?? 'active';

        $office = Office::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Office added.',
            'office'  => $office,
        ]);
    }

    public function updateOffice(Request $request, $id)
    {
        $office = Office::findOrFail($id);

        $validated = $request->validate([
            'office_name' => 'required|string|max:255|unique:offices,office_name,' . $id . ',office_id',
        ]);

        $office->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Office updated.',
            'office'  => $office,
        ]);
    }

    /**
     * Flip active <-> inactive. Inactive offices are hidden from the
     * frontend (they simply won't be returned by any query filtering
     * on status = 'active'), but stay intact for historical records.
     */
    public function toggleOfficeStatus($id)
    {
        $office = Office::findOrFail($id);
        $office->status = $office->status === 'active' ? 'inactive' : 'active';
        $office->save();

        return response()->json([
            'success' => true,
            'message' => 'Office is now ' . $office->status . '.',
            'status'  => $office->status,
        ]);
    }

    public function destroyOffice($id)
    {
        $office = Office::findOrFail($id);

        $inUse = DocumentRequestForm::where('office_id', $id)->exists()
            || DocumentChangeNotice::where('office_id', $id)->exists()
            || MasterlistOrigin::where('office_id', $id)->exists()
            || RetrievalOffice::where('office_id', $id)->exists()
            || DistributionOffice::where('office_id', $id)->exists();

        if ($inUse) {
            return response()->json([
                'success' => false,
                'message' => 'This office is referenced by existing document records. Set it to Inactive instead of deleting.',
            ], 422);
        }

        $office->delete();

        return response()->json([
            'success' => true,
            'message' => 'Office deleted.',
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // VERSION TYPES
    // ══════════════════════════════════════════════════════════

    public function storeVersionType(Request $request)
    {
        $field = self::VERSION_NAME_FIELD;

        $validated = $request->validate([
            $field => 'required|string|max:255|unique:version_type,' . $field,
        ]);

        $versionType = VersionType::create($validated);

        return response()->json([
            'success'      => true,
            'message'      => 'Version type added.',
            'version_type' => $versionType,
        ]);
    }

    public function updateVersionType(Request $request, $id)
    {
        $field = self::VERSION_NAME_FIELD;
        $versionType = VersionType::findOrFail($id);

        $validated = $request->validate([
            $field => 'required|string|max:255|unique:version_type,' . $field . ',' . $id . ',version_id',
        ]);

        $versionType->update($validated);

        return response()->json([
            'success'      => true,
            'message'      => 'Version type updated.',
            'version_type' => $versionType,
        ]);
    }

    public function destroyVersionType($id)
    {
        $inUse = DocumentRequest::where('version_id', $id)->exists();

        if ($inUse) {
            return response()->json([
                'success' => false,
                'message' => 'This version type is used by existing documents and cannot be deleted.',
            ], 422);
        }

        VersionType::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Version type deleted.',
        ]);
    }

}