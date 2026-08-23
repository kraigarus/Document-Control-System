<?php

use App\Helpers\CalendarHelper;
use App\Helpers\RegisterPersistHelper;
use App\Helpers\RegisterQueryHelper;
use App\Services\RegisterScanService;
use App\Helpers\RegisterUpdateHelper;
use App\Helpers\ReportHelper;
use App\Helpers\ReportTemplateHelper;
use App\Services\StampService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', fn () => Auth::check() ? redirect()->route('portal') : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Volt::route('/login', 'pages.portal.login')->name('login');
});

Route::post('/logout', function (Request $request) {
    $user = Auth::user();
    if ($user && $user->details) {
        $user->details->update([
            'is_currently_online' => false,
            'last_online_time' => now(),
        ]);
    }
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout')->middleware('auth');

Route::post('/keep-alive', function () {
    session()->put('last_activity_time', now()->timestamp);

    return response()->json(['status' => 'ok']);
})->name('keep-alive')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::post('/api/session/ping', function () {
        session()->put('last_activity_time', now()->timestamp);
        if ($user = Auth::user()) {
            \Illuminate\Support\Facades\DB::table('account_details')
                ->where('account_id', $user->id)
                ->update([
                    'is_currently_online' => true,
                    'last_online_time' => now(),
                ]);
        }

        return response()->json(['status' => 'active']);
    });

    Route::post('/api/session/tab-closed', function () {
        if ($user = Auth::user()) {
            \Illuminate\Support\Facades\DB::table('account_details')
                ->where('account_id', $user->id)
                ->update([
                    'is_currently_online' => false,
                ]);
        }

        return response()->json(['status' => 'closed']);
    });
});

Route::middleware(['auth', 'active'])->group(function () {
    Volt::route('/portal', 'pages.portal.access-page')->name('portal');
});

Route::middleware(['auth', 'active', 'can.access.dcs'])->group(function () {
    Volt::route('/dcs', 'pages.dcs.index')->name('dcs');

    Route::prefix('dcs')->name('dcs.')->group(function () {
        Volt::route('/dashboard', 'pages.dcs.index')->name('dashboard');

        Route::get('/api/documents/search', fn (Request $request) => RegisterQueryHelper::searchDocuments($request));
        Route::get('/api/documents/{id}/checklist/{type}', function (int $id, string $type) {
            return response()->json(RegisterQueryHelper::documentChecklistPreview($id, $type));
        })->whereIn('type', ['drf', 'dcn', 'masterlist', 'distribution', 'retrieval']);
        Route::get('/api/calendar/categories', fn () => CalendarHelper::categories());
        Route::post('/api/calendar/categories', fn (Request $request) => CalendarHelper::storeCategory($request));
        Route::delete('/api/calendar/categories/{id}', fn (int $id) => CalendarHelper::destroyCategory($id));
        Route::get('/api/calendar/events', fn () => CalendarHelper::events());
        Route::post('/api/calendar/events', fn (Request $request) => CalendarHelper::storeEvent($request));
        Route::put('/api/calendar/events/{id}', fn (Request $request, int $id) => CalendarHelper::updateEvent($request, $id));
        Route::delete('/api/calendar/events/{id}', fn (int $id) => CalendarHelper::destroyEvent($id));

        Route::get('/register/check-docno', fn (Request $request) => response()->json(RegisterQueryHelper::checkDocNo($request)))
            ->name('register.checkDocNo');
        Route::post('/register/extract-scan', fn (Request $request) => response()->json(RegisterScanService::extract($request)))
            ->name('register.extractScan');

        Volt::route('/register', 'pages.dcs.register.index')->name('register.create');
        Route::post('/register', function (Request $request) {
            return RegisterPersistHelper::persist($request);
        })->name('register.store');
        Route::get('/register/revised', fn () => redirect()->route('dcs.register.create', ['type' => 'revised']))
            ->name('register.revised');

        Volt::route('/register/update', 'pages.dcs.register.update')->name('register.update');
        Volt::route('/register/history/{docNo}', 'pages.dcs.register.history')->name('register.history');
        Volt::route('/register/{id}/edit', 'pages.dcs.register.edit')->name('register.edit');
        Route::put('/register/{id}', function (Request $request, $id) {
            return RegisterUpdateHelper::update($request, (int) $id);
        })->name('register.updateDoc');

        Volt::route('/reports/masterlist', 'pages.dcs.reports.show')->name('reports.masterlist');
        Volt::route('/reports/monitoring', 'pages.dcs.reports.show')->name('reports.monitoring');
        Volt::route('/reports/opcr', 'pages.dcs.reports.show')->name('reports.opcr');
        Volt::route('/reports/others', 'pages.dcs.reports.show')->name('reports.others');
        Route::get('/reports/export', fn (Request $request) => app(ReportHelper::class)->export($request))->name('reports.export');
        Route::get('/reports/distribution-template', fn (Request $request) => ReportTemplateHelper::render($request))->name('reports.distributionTemplate');
        Route::post('/reports/distribution-template', fn (Request $request) => ReportTemplateHelper::render($request))->name('reports.distributionTemplate.store');
        Route::get('/api/report-templates', fn () => response()->json(ReportTemplateHelper::list()));
        Route::post('/api/report-templates', fn (Request $request) => ReportTemplateHelper::store($request));
        Route::delete('/api/report-templates/{id}', fn (int $id) => ReportTemplateHelper::destroy($id));

        Volt::route('/stamping', 'pages.dcs.stamping.index')->name('stamping.index');
        Route::post('/stamp/apply', fn (Request $request) => app(StampService::class)->apply($request))->name('stamp.apply');
        Route::post('/stamp/download', fn (Request $request) => app(StampService::class)->download($request))->name('stamp.download');
        Route::post('/stamp/preview', fn (Request $request) => app(StampService::class)->preview($request))->name('stamp.preview');

        Volt::route('/database', 'pages.dcs.database.index')->name('database.index');

        Volt::route('/settings', 'pages.dcs.settings.index')->name('settings.index');
    });
});

Route::fallback(function () {
    return Auth::check()
        ? redirect()->route('portal')
        : redirect()->route('login');
});
