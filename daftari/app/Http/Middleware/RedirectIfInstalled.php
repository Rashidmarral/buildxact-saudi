<?php

namespace App\Http\Middleware;

use App\Support\Installer\InstallerLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses every /install/* route once InstallerLock says a successful
 * install has already happened (Module 24) — the wizard can reconfigure
 * the database and create a platform administrator, so leaving it
 * reachable on a live site would be a standing security hole. Re-enabling
 * it is a deliberate CLI action only (`php artisan installer:enable`),
 * never anything reachable from here.
 */
class RedirectIfInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallerLock::isInstalled()) {
            return redirect()->route('login')->with('status', __('This application is already installed.'));
        }

        return $next($request);
    }
}
