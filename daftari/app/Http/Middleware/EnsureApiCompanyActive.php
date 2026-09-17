<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API-side sibling of EnsureCompanyActive: same Company::isOperational()
 * check, but a JSON 403 instead of a web redirect+logout (there's no
 * session to log a token holder out of). Security audit finding D-0: a
 * suspended company's Sanctum API token used to keep working indefinitely
 * after EnsureCompanyActive had already force-logged-out its web users —
 * this middleware closes that gap for routes/api.php.
 */
class EnsureApiCompanyActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->company && ! $user->company->isOperational()) {
            return response()->json([
                'message' => __('This account has been suspended. Please contact support.'),
            ], 403);
        }

        return $next($request);
    }
}
