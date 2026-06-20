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

// Logout (POST only, auth required)
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// Protected routes — auth + inactivity check
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', fn () => view('pages.dcs.dashboard'))
        ->name('dashboard');

    // Register
    Route::get('/register', [RegisterController::class, 'index'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    // Register revised
    Route::get('/register/revised', [RegisterController::class, 'revised'])->name('register.revised');

    // Update
    Route::get('/register/update', [RegisterController::class, 'update'])->name('register.update');
});

// Catch-all: redirect unknown routes to portal
Route::fallback(fn () => redirect('/login'));
