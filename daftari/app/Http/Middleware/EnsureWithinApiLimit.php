<?php

namespace App\Http\Middleware;

use App\Models\ApiUsageCounter;
use App\Services\Limits\UsageLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the company's "API calls" plan limit (Module 07) once for every
 * Sanctum-authenticated request, rather than duplicating the check inside
 * every API controller action. Blocks with a friendly 429 once the
 * company's current-month usage has reached its cap (or its Super Admin
 * override); otherwise records the call and lets the request through.
 */
class EnsureWithinApiLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->company;

        if (! $company) {
            return $next($request);
        }

        $limitService = app(UsageLimitService::class);

        if ($limitService->reached($company, 'api_calls')) {
            return response()->json([
                'message' => $limitService->friendlyMessage($company, 'api_calls'),
            ], 429);
        }

        ApiUsageCounter::recordCall($company->id);

        return $next($request);
    }
}
