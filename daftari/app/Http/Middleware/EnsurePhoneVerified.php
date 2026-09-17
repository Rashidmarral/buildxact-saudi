<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirrors Laravel's built-in 'verified' (email) middleware, but for phone —
 * only actually blocks when Platform Settings → Signup → "Require phone
 * verification" is on (User::needsPhoneVerification() checks the setting
 * itself), so it's a no-op for every install that hasn't turned it on.
 */
class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->needsPhoneVerification() && ! $request->routeIs('phone.*', 'logout')) {
            return redirect()->route('phone.verify');
        }

        return $next($request);
    }
}
