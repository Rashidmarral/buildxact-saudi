<?php

namespace App\Http\Middleware;

use App\Support\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses any DELETE-method request from a demo company's own user
 * (Module 23) — every destructive action in the company panel (clients,
 * items, invoices, team members, roles, ...) is wired through a DELETE
 * route, so gating on HTTP method here is a single, comprehensive choke
 * point rather than adding a check to dozens of individual destroy()
 * actions. Applied once at the top of the 'app.' route group; a Super
 * Admin acting from the admin panel is unaffected (their own
 * company_id is null, so this never triggers for them) — deliberately,
 * since platform staff still need to be able to manage/reset a demo
 * company from the admin side.
 */
class PreventDemoDestruction
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->company;

        if ($company?->isDemo() && $request->isMethod('delete')) {
            return back()->withErrors(['demo' => DemoMode::deletingRecords()]);
        }

        return $next($request);
    }
}
