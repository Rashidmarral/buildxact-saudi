<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Site-wide maintenance mode, toggled from the admin Settings screen
 * (no CLI access needed). Admin routes and logged-in admins always bypass
 * it, so the team can keep working on the site while it's shown to the
 * public — this is a soft, DB-driven alternative to `php artisan down`.
 */
class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return $next($request);
        }

        if ($request->user()?->is_admin) {
            return $next($request);
        }

        if (Setting::get('maintenance_mode_enabled') !== '1') {
            return $next($request);
        }

        return response()
            ->view('maintenance', [
                'message' => Setting::get('maintenance_message'),
            ], 503)
            ->header('Retry-After', 3600);
    }
}
