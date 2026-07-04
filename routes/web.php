<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReportController;

// Public — portal (no auth)
Route::get('/', function () {
    return view('pages.portal.portal');
});

// Login — both GET and POST protected by guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Catch-all: redirect unknown routes to portal
Route::fallback(fn () => redirect('/login'));

Route::middleware('auth')->group(function () {
    Route::get('/api/offices', fn () => \App\Models\Office::where('status', 'active')->orderBy('office_name')->get());
    Route::get('/api/doc-types', fn () => \App\Models\DocType::orderBy('doc_type_id')->get());
    Route::get('/api/version-types', fn () => \App\Models\VersionType::all());
    Route::get('/api/approval-bodies', fn () => \App\Models\ApprovalBody::all());
    Route::get('/api/checklist-types', fn () => \App\Models\ChecklistType::orderBy('checklist_id')->get());
    Route::get('/api/checklist-versions/{versionId}', function ($versionId) {
        return \App\Models\ChecklistVersion::where('checklist_version.version_id', $versionId)
            ->join('checklist_types', 'checklist_version.checklist_id', '=', 'checklist_types.checklist_id')
            ->select('checklist_types.checklist_id', 'checklist_types.checklist_name')
            ->orderBy('checklist_types.checklist_id')
            ->get();
    });

    Route::get('/api/dashboard-stats', function () {
        // Get latest revision's request_id for each doc_no
        $latestIds = DB::table('masterlist_registration as m1')
            ->leftJoin('masterlist_registration as m2', function ($join) {
                $join->on('m1.doc_no', '=', 'm2.doc_no')
                    ->whereRaw('CAST(m1.revise_no AS UNSIGNED) < CAST(m2.revise_no AS UNSIGNED)');
            })
            ->whereNull('m2.request_id')
            ->whereNotNull('m1.doc_no')
            ->where('m1.doc_no', '!=', '')
            ->pluck('m1.request_id');

        // Documents with no masterlist at all
        $noMlIds = \App\Models\DocumentRequest::whereDoesntHave('masterlistRegistration')
            ->orWhereHas('masterlistRegistration', function ($q) {
                $q->whereNull('doc_no')->orWhere('doc_no', '');
            })
            ->pluck('request_id');

        $visibleIds = $latestIds->merge($noMlIds)->unique();

        // Base query: latest revisions only, not obsolete
        $base = function () use ($visibleIds) {
            return \App\Models\DocumentRequest::whereIn('request_id', $visibleIds)
                ->where('approval_status', '!=', 'obsolete');
        };

        $totalDocuments = $base()->count();

        $internalIds = [1, 6, 7, 8, 9, 10];
        $internalCount = $base()->where(function ($q) use ($internalIds) {
            $q->whereIn('doc_type_id', $internalIds)
            ->orWhereIn('sub_type_id', $internalIds);
        })->count();

        $internalFormIds = [2, 11, 12, 13, 14];
        $internalFormsCount = $base()->where(function ($q) use ($internalFormIds) {
            $q->whereIn('doc_type_id', $internalFormIds)
            ->orWhereIn('sub_type_id', $internalFormIds);
        })->count();

        $externalCount = $base()->where(function ($q) {
            $q->where('doc_type_id', 3)->orWhere('sub_type_id', 3);
        })->count();

        $formsCount = $base()->where(function ($q) {
            $q->where('doc_type_id', 4)->orWhere('sub_type_id', 4);
        })->count();

        $logbooksCount = $base()->where(function ($q) {
            $q->where('doc_type_id', 5)->orWhere('sub_type_id', 5);
        })->count();

        return response()->json([
            'totalDocuments' => $totalDocuments,
            'internalCount' => $internalCount,
            'internalFormsCount' => $internalFormsCount,
            'externalCount' => $externalCount,
            'formsCount' => $formsCount,
            'logbooksCount' => $logbooksCount,
        ]);
    });

    Route::get('/api/registered-documents', [RegisterController::class, 'getRegisteredDocuments']);
});

// Reports
Route::get('/reports', [App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/data', [App\Http\Controllers\ReportController::class, 'data'])->name('reports.data');
Route::get('/reports/export', [App\Http\Controllers\ReportController::class, 'export'])->name('reports.export');

//stamping
Route::get('/stamping', [RegisterController::class, 'stampingIndex'])->name('stamping.index');

//database
Route::get('/database', [RegisterController::class, 'databaseIndex'])->name('database.index');
Route::get('/database/data', [RegisterController::class, 'databaseData'])->name('database.data');
Route::get('/database/export', [RegisterController::class, 'databaseExport'])->name('database.export');


// Logout (POST only, auth required)
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// Protected routes — auth + inactivity check
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', fn () => view('pages.dcs.dashboard'))
        ->name('dashboard');

    // Register
    Route::get('/register', [RegisterController::class, 'index'])->name('register.create');
    Route::get('/register/check-docno', [\App\Http\Controllers\RegisterController::class, 'checkDocNo'])->name('register.checkDocNo');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    Route::get('/register/revised', [RegisterController::class, 'revised'])->name('register.revised');


    //update
    Route::get('/register/update', [UpdateController::class, 'update'])->name('register.update');
    Route::get('/register/update/data', [RegisterController::class, 'updateData'])->name('register.update.data');
    Route::get('/register/update', [RegisterController::class, 'updateList'])->name('register.update');
    Route::get('/register/{id}/edit',       [RegisterController::class, 'edit'])->name('register.edit');
    Route::put('/register/{id}',            [RegisterController::class, 'updateDoc'])->name('register.updateDoc');
    Route::delete('/register/{id}',         [RegisterController::class, 'destroy'])->name('register.destroy');
    Route::get('/register/history/{docNo}', [RegisterController::class, 'history'])->name('register.history');
});
