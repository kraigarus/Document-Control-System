<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('pages.dcs.auth.login');
    }

    public function login(Request $request)
    {
        // Rate limit: 3 attempts per minute per IP
        $key = 'login.' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        // Validate input format
        $request->validate([
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // Account lockout after 10 failed attempts (tracked per email)
        $failKey = 'login_fails.' . $request->email;
        $failedAttempts = Cache::get($failKey, 0);

        if ($failedAttempts >= 10) {
            throw ValidationException::withMessages([
                'email' => 'Account temporarily locked. Contact administrator.',
            ]);
        }

        // Find user
        $user = User::where('email', $request->email)->first();

        // Fake hash check if user doesn't exist (prevents timing attacks)
        $passwordValid = $user
            ? Hash::check($request->password, $user->password)
            : Hash::check($request->password, bcrypt('dummy-to-prevent-timing'));

        // ALWAYS use the same generic message
        if (!$user || !$passwordValid) {
            RateLimiter::hit($key, 60);
            Cache::put($failKey, $failedAttempts + 1, now()->addMinutes(30));

            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        // Login without remember me
        Auth::login($user);

        // Clear all locks on success
        RateLimiter::clear($key);
        Cache::forget($failKey);

        // Regenerate session
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        // Update online status before logging out
        if ($user && $user->details) {
            $user->details->update([
                'is_currently_online' => false,
                'last_online_time'    => now(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
