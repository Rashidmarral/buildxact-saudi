<?php

namespace App\Http\Middleware;

use App\Services\Features\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-group gate for a Module 07 FeatureRegistry key (payroll, pos, ...)
 * — checks FeatureAccessService::enabled(), which folds in the platform-
 * wide master switch (PlatformFeatureToggle), any Super Admin per-company
 * override, and the company's plan. Distinct from the pre-existing
 * `feature` middleware alias (EnsurePlanFeature), which only reads the
 * older Plan::FEATURE_KEYS system and has no platform-wide switch — a
 * module with many routes (Payroll, POS) gates the whole group here
 * instead of repeating an inline FeatureAccessService check in every
 * controller action, the way the single-action WhatsApp/API token gates
 * already do.
 *
 * Accepts more than one key ('module:pos,restaurant') for a route a
 * company can reach through either module — e.g. a POS sale receipt,
 * which a Restaurant-only company's checkout redirects to just as much
 * as a plain retail POS one — passing as soon as any one of them is
 * enabled, rather than requiring every module named.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        $user = $request->user();
        $access = app(FeatureAccessService::class);

        $enabled = $user && $user->company
            && collect($keys)->contains(fn (string $key) => $access->enabled($user->company, $key));

        if (! $enabled) {
            return redirect()->route('app.dashboard')->withErrors([
                'feature' => __("This feature isn't included in your current plan. Upgrade your plan to unlock it."),
            ]);
        }

        return $next($request);
    }
}
