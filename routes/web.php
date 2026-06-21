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
});

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
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    // Register revised
    Route::get('/register/revised', [RegisterController::class, 'revised'])->name('register.revised');

    // Update
    Route::get('/register/update', [RegisterController::class, 'update'])->name('register.update');
});



// Catch-all: redirect unknown routes to portal
Route::fallback(fn () => redirect('/login'));

