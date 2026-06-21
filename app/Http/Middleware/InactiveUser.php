<?php
// app/Http/Middleware/InactiveUserMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InactiveUser
{
    protected $inactiveMinutes = 15; // Change for production

    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $lastActivity = session('last_activity_time');
        $inactiveSeconds = $this->inactiveMinutes * 60;

        // First visit — set timestamp and share full remaining time
        if (!$lastActivity) {
            session(['last_activity_time' => now()->timestamp]);
            view()->share('session_remaining', $inactiveSeconds);
            return $next($request);
        }

        // Calculate idle time
        $idleTime = now()->timestamp - $lastActivity;

        // Auto logout
        if ($idleTime >= $inactiveSeconds) {
            if ($user->details) {
                $user->details->update([
                    'is_currently_online' => false,
                    'last_online_time'    => now(),
                ]);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'You have been logged out due to inactivity.',
            ]);
        }

        // Update activity timestamp
        session(['last_activity_time' => now()->timestamp]);

        // ALWAYS share remaining time (fixes the bug)
        $remaining = $inactiveSeconds - $idleTime;
        view()->share('session_remaining', $remaining);

        return $next($request);
    }
}
