<?php

namespace App\Http\Middleware;

use App\Support\Installer\InstallerLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A fresh install has no `sessions` table yet — migrations only run at
 * Step 5 of the wizard (Module 24) — so if SESSION_DRIVER is 'database'
 * (this app's usual default), Laravel's own StartSession middleware would
 * fail to save the session on *every* request, not just the wizard's own
 * (it runs unconditionally in the 'web' group, for CSRF tokens if nothing
 * else) — including the marketing pages a fresh, not-yet-installed
 * deployment would otherwise serve at '/'.
 *
 * Forcing file-based sessions for as long as InstallerLock says the app
 * isn't installed sidesteps that with nothing more than a writable
 * storage/framework/sessions directory, which Step 1's requirements check
 * already verifies. Stops applying the moment InstallerLock exists, since
 * by then migrations have run and the app's real configured driver is
 * safe again. Registered with `prepend` in bootstrap/app.php so this runs
 * before StartSession in the 'web' group.
 */
class PrepareInstallerEnvironment
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('session.driver') === 'database' && ! InstallerLock::isInstalled()) {
            config(['session.driver' => 'file']);
        }

        return $next($request);
    }
}
