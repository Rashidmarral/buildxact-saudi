<?php

namespace App\Http\Middleware;

use App\Services\Features\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit finding API-01: ApiTokenController::store() already refuses to
 * ISSUE a new token when the company's plan doesn't include the 'api'
 * feature (FeatureAccessService), but nothing re-checked that on each
 * actual API request — a token minted while on an API-enabled plan kept
 * working against every v1 endpoint indefinitely after the company
 * downgraded to a plan without API access, or had the feature revoked by
 * an admin override. API-side sibling of EnsurePlanFeature, the same way
 * EnsureApiCompanyActive is the API-side sibling of EnsureCompanyActive —
 * a JSON 403 instead of a web redirect, since there's no session to send
 * a token holder back to.
 */
class EnsureApiFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $company = $request->user()?->company;

        if ($company && ! app(FeatureAccessService::class)->enabled($company, $feature)) {
            return response()->json([
                'message' => __("The API isn't included in your current plan. Upgrade your plan to continue using it."),
            ], 403);
        }

        return $next($request);
    }
}
