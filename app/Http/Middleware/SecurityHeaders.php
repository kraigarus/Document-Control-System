<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // These are safe in ALL environments
        // SAMEORIGIN allows same-site report preview iframes; keep in sync with nginx (or nginx omits this header).
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->remove('X-Powered-By');

        // Only apply CSP and HSTS in production
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set('Content-Security-Policy',
                "default-src 'self'; " .
                "script-src 'self'; " .
                "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
                "img-src 'self' data:; " .
                "font-src 'self' https://cdnjs.cloudflare.com;"
            );
        }

        return $response;
    }
}
