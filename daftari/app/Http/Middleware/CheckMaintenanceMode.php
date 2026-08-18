<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform-wide maintenance mode, toggled from Admin\PlatformSettingsController
 * rather than artisan down/up — so a super admin can flip it from the UI on
 * a host they don't have shell access to. A logged-in super admin, and the
 * login/logout routes themselves, stay reachable so the switch can always
 * be turned back off.
 */
class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::getBool('maintenance_mode')) {
            return $next($request);
        }

        if ($request->is('login', 'logout') || $request->user()?->isSuperAdmin()) {
            return $next($request);
        }

        return response()->view('errors.maintenance', [
            'message' => Setting::get('maintenance_message', ''),
        ], 503);
    }
}
