<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirror image of the 'role:super_admin,admin_staff' guard on the admin
 * panel: nothing previously stopped a platform admin (company_id always
 * null) from simply navigating into the company panel. That mattered
 * because BelongsToCompany's global scope only filters "if
 * Auth::user()->company_id" — for a null company_id it silently no-ops,
 * so an admin landing on e.g. /app/invoices saw every tenant's invoices
 * unscoped, and User::hasPermission() lets super_admin through every
 * permission:* check regardless. This middleware closes that gap at the
 * door instead of relying on every controller to defend against it.
 */
class EnsureUserBelongsToCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->company_id) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
