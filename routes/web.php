<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

// Public — portal (no auth)
Route::get('/', function () {
    return view('pages.portal.portal');
});

// Login — both GET and POST protected by guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Logout (POST only, auth required)
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// Protected routes — auth + inactivity check
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', fn () => view('pages.dcs.pages.dashboard'))
        ->name('dashboard');
});

// Catch-all: redirect unknown routes to portal
Route::fallback(fn () => redirect('/login'));
