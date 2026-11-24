<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers on every web response:
 *   - no MIME sniffing, no framing by other sites (clickjacking),
 *   - referrers only to the same origin in full,
 *   - camera, microphone, location and payment APIs switched off,
 *   - HSTS when served over HTTPS in production.
 * A Content-Security-Policy is not set: Livewire and Alpine need inline and
 * eval'd scripts, so a strict policy would break the app; the server (nginx)
 * is the place to add one if required.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
