<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * $level is 'view' (default, satisfied by a view-only or full grant) or
 * 'manage' (requires a full grant — see AdminRole::hasManagePermission()).
 * Route definitions pass it as a second middleware parameter, e.g.
 * 'admin.permission:companies,manage' for a mutating route, or just
 * 'admin.permission:companies' for a read-only one. Security audit
 * finding CRIT-04: without this distinction, the seeded "Read-only
 * auditor" role — which grants every permission key — could reach every
 * mutating admin action too.
 */
class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission, string $level = 'view'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAdminPermission($permission, $level)) {
            abort(403);
        }

        return $next($request);
    }
}
