<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StampingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ProfileController;

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

    // Register — Create
    Route::get('/register', [RegisterController::class, 'index'])->name('register.create');
    Route::get('/register/check-docno', [RegisterController::class, 'checkDocNo'])->name('register.checkDocNo');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    Route::get('/register/revised', [RegisterController::class, 'revised'])->name('register.revised');

    Route::get('/api/colleges', [RegisterController::class, 'apiColleges']);
    Route::get('/api/programs/{collegeId}', [RegisterController::class, 'apiPrograms']);
    Route::get('/api/semesters', [RegisterController::class, 'apiSemesters']);
    Route::get('/api/school-years', [RegisterController::class, 'apiSchoolYears']);
    Route::get('/api/documents/search', [RegisterController::class, 'apiSearchDocuments']);
    Route::get('/api/originators', [RegisterController::class, 'apiOriginators']);

    // Register — Update
    Route::get('/register/update', [RegisterController::class, 'updateList'])->name('register.update');
    Route::get('/register/update/data', [RegisterController::class, 'updateData'])->name('register.update.data');
    Route::get('/register/{id}/edit', [RegisterController::class, 'edit'])->name('register.edit');
    Route::put('/register/{id}', [RegisterController::class, 'updateDoc'])->name('register.updateDoc');
    Route::delete('/register/{id}', [RegisterController::class, 'destroy'])->name('register.destroy');
    Route::get('/register/history/{docNo}', [RegisterController::class, 'history'])->name('register.history');
    Route::post('/register/extract-scan', [RegisterController::class, 'extractScan'])
    ->name('register.extractScan');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/masterlist', [ReportController::class, 'masterlist'])->name('reports.masterlist');
    Route::get('/reports/monitoring', [ReportController::class, 'monitoring'])->name('reports.monitoring');
    Route::get('/reports/opcr', [ReportController::class, 'opcr'])->name('reports.opcr');
    Route::get('/reports/others', [ReportController::class, 'othersReport'])->name('reports.others');
    Route::get('/reports/data', [ReportController::class, 'data'])->name('reports.data');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::post('/reports/opcr/save', [ReportController::class, 'saveOpcrRatings'])->name('reports.opcr.save');

    // Stamping
    Route::get('/stamping', [StampingController::class, 'index'])->name('stamping.index');
    Route::post('/stamp/apply',    [StampingController::class, 'apply'])->name('dcs.stamp.apply');
    Route::post('/stamp/download', [StampingController::class, 'download'])->name('dcs.stamp.download');
    Route::post('/stamp/preview',  [StampingController::class, 'preview'])->name('dcs.stamp.preview');
    Route::post('/stamp/check',  [StampingController::class, 'checkStamp'])->name('dcs.stamp.check');
    Route::post('/stamp/remove', [StampingController::class, 'remove'])->name('dcs.stamp.remove');

    // Database
    Route::get('/database', [RegisterController::class, 'databaseIndex'])->name('database.index');
    Route::get('/database/data', [RegisterController::class, 'databaseData'])->name('database.data');
    Route::get('/database/export', [RegisterController::class, 'databaseExport'])->name('database.export');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');

        // Document Types & Sub-types
        Route::post('/doc-types', [SettingsController::class, 'storeDocType'])->name('doctypes.store');
        Route::put('/doc-types/{id}', [SettingsController::class, 'updateDocType'])->name('doctypes.update');
        Route::delete('/doc-types/{id}', [SettingsController::class, 'destroyDocType'])->name('doctypes.destroy');

        // Offices
        Route::post('/offices', [SettingsController::class, 'storeOffice'])->name('offices.store');
        Route::put('/offices/{id}', [SettingsController::class, 'updateOffice'])->name('offices.update');
        Route::post('/offices/{id}/toggle-status', [SettingsController::class, 'toggleOfficeStatus'])->name('offices.toggle');
        Route::delete('/offices/{id}', [SettingsController::class, 'destroyOffice'])->name('offices.destroy');

        // Version Types
        Route::post('/version-types', [SettingsController::class, 'storeVersionType'])->name('versiontypes.store');
        Route::put('/version-types/{id}', [SettingsController::class, 'updateVersionType'])->name('versiontypes.update');
        Route::delete('/version-types/{id}', [SettingsController::class, 'destroyVersionType'])->name('versiontypes.destroy');
    });

    Route::middleware('auth')->prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('index');
        Route::put('/info', [ProfileController::class, 'updateInfo'])->name('info.update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
        Route::post('/photo', [ProfileController::class, 'updatePhoto'])->name('photo.update');
        Route::delete('/photo', [ProfileController::class, 'destroyPhoto'])->name('photo.destroy');
    });
});

// Catch-all: redirect unknown routes to login
Route::fallback(fn () => redirect('/login'));