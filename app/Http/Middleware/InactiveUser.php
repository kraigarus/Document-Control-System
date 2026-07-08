<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InactiveUser
{
    protected $inactiveMinutes = 15;

    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $lastActivity = session('last_activity_time');
        $inactiveSeconds = $this->inactiveMinutes * 60;

        // First visit — initialize timestamp
        if (!$lastActivity) {
            session(['last_activity_time' => now()->timestamp]);
            view()->share('session_remaining', $inactiveSeconds);
            return $next($request);
        }

        $idleTime = now()->timestamp - $lastActivity;

        // ── FIX #1: Always update activity BEFORE checking timeout ──
        // This ensures that if the user IS making a request, they're considered active.
        // The keep-alive ping and normal page loads both reset the clock.
        session(['last_activity_time' => now()->timestamp]);

        // Auto logout — only if TRULY inactive (no request within the window)
        if ($idleTime >= $inactiveSeconds) {
            if ($user->details) {
                $user->details->update([
                    'is_currently_online' => false,
                    'last_online_time' => now(),
                ]);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'You have been logged out due to inactivity.',
            ]);
        }

        // Share remaining time with view
        $remaining = $inactiveSeconds - $idleTime;
        view()->share('session_remaining', $remaining);

        return $next($request);
    }
}