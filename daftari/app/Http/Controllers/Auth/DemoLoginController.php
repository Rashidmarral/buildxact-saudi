<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * One-click "Try the demo" — logs the visitor straight into the seeded
 * demo company (the same owner@daftari.local account DemoSeeder always
 * keeps up to date), gated entirely behind Platform Settings → General →
 * "Allow demo accounts". Deliberately scoped to that one non-admin,
 * company-level account only — never the platform admin — so turning this
 * on can never hand out platform control.
 */
class DemoLoginController extends Controller
{
    public function login(Request $request)
    {
        abort_unless(Setting::getBool('general_allow_demo_accounts'), 404);

        $demoUser = User::where('email', 'owner@daftari.local')
            ->where('role', '!=', 'super_admin')
            ->whereNotNull('company_id')
            ->first();

        abort_unless($demoUser && $demoUser->status === 'active', 404);

        Auth::login($demoUser);
        $request->session()->regenerate();

        return redirect()->route('app.dashboard')
            ->with('status', __("You're in a shared demo account — data here resets periodically and is visible to other demo visitors."));
    }
}
