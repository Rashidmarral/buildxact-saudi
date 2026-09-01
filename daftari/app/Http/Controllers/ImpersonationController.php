<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ends a support impersonation session started from
 * Admin\CompanyController::impersonate(). Lives outside the admin/{role:
 * super_admin} route group on purpose: while impersonating, the
 * authenticated user really is the company's owner, not a super admin, so
 * a route gated on that role would 403 the very button meant to end it.
 * session('impersonator_id') is the actual guard instead.
 */
class ImpersonationController extends Controller
{
    public function stop(Request $request)
    {
        $adminId = $request->session()->pull('impersonator_id');
        $request->session()->forget('impersonation_started_at');

        if (! $adminId || ! ($admin = User::withoutGlobalScopes()->find($adminId)) || ! $admin->isSuperAdmin()) {
            abort(403);
        }

        // Recorded with the admin's own id, not auth()->id() — at this
        // point the authenticated user is still the impersonated owner, so
        // AuditLog::record()'s default (auth()->id()) would misattribute
        // this action to them instead of the admin who actually ended it.
        AuditLog::record(
            'company.stop_impersonate',
            Auth::user()->company,
            __('Stopped impersonating :name', ['name' => Auth::user()->company?->name]),
            $admin->id,
        );

        Auth::login($admin);

        return redirect()->route('admin.dashboard');
    }
}
