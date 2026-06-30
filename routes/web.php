<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RegisterController;

// Public — portal (no auth)
Route::get('/', function () {
    return view('pages.portal.portal');
});

// Login — both GET and POST protected by guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

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
        $totalDocuments = \App\Models\DocumentRequest::count();

        // Internal = doc_type_id 1 + its children (6,7,8,9,10)
        $internalIds = [1, 6, 7, 8, 9, 10];
        $internalCount = \App\Models\DocumentRequest::whereIn('doc_type_id', $internalIds)
            ->orWhereIn('sub_type_id', $internalIds)
            ->count();

        // Internal Forms = doc_type_id 2 + its children (11,12,13,14)
        $internalFormIds = [2, 11, 12, 13, 14];
        $internalFormsCount = \App\Models\DocumentRequest::whereIn('doc_type_id', $internalFormIds)
            ->orWhereIn('sub_type_id', $internalFormIds)
            ->count();

        // External = doc_type_id 3
        $externalCount = \App\Models\DocumentRequest::where('doc_type_id', 3)
            ->orWhere('sub_type_id', 3)
            ->count();

        // Forms = doc_type_id 4
        $formsCount = \App\Models\DocumentRequest::where('doc_type_id', 4)
            ->orWhere('sub_type_id', 4)
            ->count();

        // Logbooks = doc_type_id 5
        $logbooksCount = \App\Models\DocumentRequest::where('doc_type_id', 5)
            ->orWhere('sub_type_id', 5)
            ->count();

        return response()->json([
            'totalDocuments' => $totalDocuments,
            'internalCount' => $internalCount,
            'internalFormsCount' => $internalFormsCount,
            'externalCount' => $externalCount,
            'formsCount' => $formsCount,
            'logbooksCount' => $logbooksCount,
        ]);
    });
});

// Reports
Route::get('/reports', [RegisterController::class, 'reportIndex'])->name('generate-report.report');
Route::get('/reports/masterlist', [RegisterController::class, 'masterlistReport'])->name('generate-report.masterlist');
Route::get('/reports/masterlist/data', [RegisterController::class, 'masterlistData'])->name('generate-report.masterlist.data');
Route::get('/reports/masterlist/print', [RegisterController::class, 'masterlistPrint'])->name('generate-report.masterlist.print');
Route::get('/reports/monitoring', [RegisterController::class, 'monitoringReport'])->name('generate-report.monitoring');
Route::get('/reports/opcr', [RegisterController::class, 'opcrReport'])->name('generate-report.opcr');
Route::get('/reports/other', [RegisterController::class, 'otherReport'])->name('generate-report.other');

Route::get('/stamping', [RegisterController::class, 'stampingIndex'])->name('stamping.index');



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

    // Update
    Route::get('/register/update', [RegisterController::class, 'updateList'])->name('register.update');
    Route::get('/register/{id}/edit', [RegisterController::class, 'edit'])->name('register.edit');
    Route::put('/register/{id}', [RegisterController::class, 'updateDocument'])->name('register.updateDocument');
    Route::delete('/register/{id}', [RegisterController::class, 'destroy'])->name('register.destroy');
});



// Catch-all: redirect unknown routes to portal
Route::fallback(fn () => redirect('/login'));

