<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route when the signed-in user's company plan doesn't include the
 * given feature (see Plan::FEATURE_KEYS / Company::hasFeature()) — e.g.
 * quotations, cost centers, purchase orders. Distinct from the `permission`
 * middleware, which gates by the user's role within their own company;
 * this gates by what the company's subscription plan actually includes.
 */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user || ! $user->company || ! $user->company->hasFeature($feature)) {
            return redirect()->route('app.dashboard')->withErrors([
                'feature' => __("This feature isn't included in your current plan. Upgrade your plan to unlock it."),
            ]);
        }

        return $next($request);
    }
}
