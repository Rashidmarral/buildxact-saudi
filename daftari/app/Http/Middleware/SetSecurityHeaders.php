<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Go-live audit finding: X-Frame-Options/X-Content-Type-Options only
 * existed as a copy-pasteable line in the sample nginx config (§2 of
 * docs/codecanyon/DOCUMENTATION.md), so a buyer on Apache, a different
 * nginx template, or a CDN that overrides upstream headers would ship
 * with none of them. Setting them here means every deployment gets them
 * regardless of web server. HSTS is added only over an actual HTTPS
 * request in production — sending it over plain http (or in local/
 * testing) would tell browsers to force https on a host that doesn't
 * necessarily serve it yet.
 */
class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->isSecure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
