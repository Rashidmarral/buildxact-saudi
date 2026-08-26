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
        $client = $clientId ? Client::find($clientId) : null;

        if (! $client) {
            return redirect()->route('portal.login');
        }

        $request->attributes->set('portalClient', $client);

        return $next($request);
    }
}
