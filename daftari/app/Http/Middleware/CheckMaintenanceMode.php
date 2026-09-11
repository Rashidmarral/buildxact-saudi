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
        if (! $this->maintenanceActive()) {
            return $next($request);
        }

        $allowSuperAdmin = Setting::getBool('maintenance_allow_super_admin', true);

        if ($request->is('login', 'logout') || ($allowSuperAdmin && $request->user()?->isSuperAdmin())) {
            return $next($request);
        }

        return response()->view('errors.maintenance', [
            'message' => Setting::get('maintenance_message', ''),
        ], 503);
    }

    /**
     * On, either because the toggle is flipped, or because "now" falls
     * inside an admin-scheduled maintenance window (both bounds set).
     */
    private function maintenanceActive(): bool
    {
        if (Setting::getBool('maintenance_mode')) {
            return true;
        }

        $start = Setting::get('maintenance_scheduled_start');
        $end = Setting::get('maintenance_scheduled_end');

        if (! $start || ! $end) {
            return false;
        }

        try {
            $now = now();

            return $now->betweenIncluded(\Illuminate\Support\Carbon::parse($start), \Illuminate\Support\Carbon::parse($end));
        } catch (\Throwable) {
            return false;
        }
    }
}
