<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Accepts more than one key ('permission:pos,restaurant') for a route
     * reachable via either module, passing as soon as any one matches —
     * see EnsureModuleEnabled's docblock for why (a POS sale receipt).
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! collect($permissions)->contains(fn (string $permission) => $user->hasPermission($permission))) {
            abort(403);
        }

        return $next($request);
    }
}
