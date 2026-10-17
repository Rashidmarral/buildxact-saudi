<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The client portal has nothing to do with Auth::user() — Client records
 * aren't Authenticatable — so this checks a completely separate plain-
 * session key set only by ClientPortalController::authenticate() after a
 * valid magic-link token. A staff member's own login session can never
 * satisfy this, and vice versa.
 */
class EnsureClientPortalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->session()->get('portal_client_id');
        // Security audit finding M-24: resolved without the tenant scope
        // (there's no Auth::user() here for BelongsToCompany to key off)
        // and with no explicit re-check afterward — this worked only
        // because the one place that sets portal_client_id validated a
        // real magic-link token first. Re-checking company() ->exists()
        // here as well is cheap, more readable defense-in-depth, and
        // closes a genuine gap this session's design otherwise had: a
        // suspended/cancelled company's clients could still browse their
        // invoice history in the self-service portal (security audit
        // finding D-0's enforcement never reached this surface).
        $client = $clientId ? Client::withoutGlobalScopes()->find($clientId) : null;

        if (! $client || ! $client->company?->isOperational()) {
            $request->session()->forget('portal_client_id');

            return redirect()->route('portal.login');
        }

        $request->attributes->set('portalClient', $client);

        return $next($request);
    }
}
