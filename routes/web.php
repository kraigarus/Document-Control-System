<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StampingController;

// Public — portal (no auth)
Route::get('/', fn () => view('pages.portal.portal'));

// Login — guest only
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Logout (POST only, auth required)
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Keep-alive (auth required)
Route::post('/keep-alive', function () {
    // Just touching the session resets its expiry
    session()->put('last_activity', now());
    return response()->json(['status' => 'ok']);
    })->name('keep-alive')->middleware('auth');

// ── Protected routes ──
Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard
    Route::get('/dashboard', fn () => view('pages.dcs.dashboard'))->name('dashboard');

    // API endpoints
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
        $latestIds = DB::table('masterlist_registration as m1')
            ->leftJoin('masterlist_registration as m2', function ($join) {
                $join->on('m1.doc_no', '=', 'm2.doc_no')
                    ->whereRaw('CAST(m1.revise_no AS UNSIGNED) < CAST(m2.revise_no AS UNSIGNED)');
            })
            ->whereNull('m2.request_id')
            ->whereNotNull('m1.doc_no')
            ->where('m1.doc_no', '!=', '')
            ->pluck('m1.request_id');

        $noMlIds = \App\Models\DocumentRequest::whereDoesntHave('masterlistRegistration')
            ->orWhereHas('masterlistRegistration', function ($q) {
                $q->whereNull('doc_no')->orWhere('doc_no', '');
            })
            ->pluck('request_id');

        $visibleIds = $latestIds->merge($noMlIds)->unique();

        $base = fn () => \App\Models\DocumentRequest::whereIn('request_id', $visibleIds)
            ->where('approval_status', '!=', 'obsolete');

        $internalIds = [1, 6, 7, 8, 9, 10];
        $internalFormIds = [2, 11, 12, 13, 14];

        return response()->json([
            'totalDocuments'    => $base()->count(),
            'internalCount'     => $base()->whereIn('doc_type_id', $internalIds)->count(),
            'internalFormsCount'=> $base()->whereIn('doc_type_id', $internalFormIds)->count(),
            'externalCount'     => $base()->where('doc_type_id', 3)->count(),
            'formsCount'        => $base()->where('doc_type_id', 4)->count(),
            'logbooksCount'     => $base()->where('doc_type_id', 5)->count(),
        ]);
    });
    Route::get('/api/registered-documents', [RegisterController::class, 'getRegisteredDocuments']);

    // Register — Create
    Route::get('/register', [RegisterController::class, 'index'])->name('register.create');
    Route::get('/register/check-docno', [RegisterController::class, 'checkDocNo'])->name('register.checkDocNo');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    Route::get('/register/revised', [RegisterController::class, 'revised'])->name('register.revised');

    // Register — Update
    Route::get('/register/update', [RegisterController::class, 'updateList'])->name('register.update');
    Route::get('/register/update/data', [RegisterController::class, 'updateData'])->name('register.update.data');
    Route::get('/register/{id}/edit', [RegisterController::class, 'edit'])->name('register.edit');
    Route::put('/register/{id}', [RegisterController::class, 'updateDoc'])->name('register.updateDoc');
    Route::delete('/register/{id}', [RegisterController::class, 'destroy'])->name('register.destroy');
    Route::get('/register/history/{docNo}', [RegisterController::class, 'history'])->name('register.history');

    // Stamping
    Route::get('/stamping', [StampingController::class, 'index'])->name('stamping.index');
    Route::post('/stamp/apply',    [StampingController::class, 'apply'])->name('dcs.stamp.apply');
    Route::post('/stamp/download', [StampingController::class, 'download'])->name('dcs.stamp.download');
    Route::post('/stamp/preview',  [StampingController::class, 'preview'])->name('dcs.stamp.preview');

    // Database
    Route::get('/database', [RegisterController::class, 'databaseIndex'])->name('database.index');
    Route::get('/database/data', [RegisterController::class, 'databaseData'])->name('database.data');
    Route::get('/database/export', [RegisterController::class, 'databaseExport'])->name('database.export');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/data', [ReportController::class, 'data'])->name('reports.data');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
});

// Catch-all: redirect unknown routes to login
Route::fallback(fn () => redirect('/login'));