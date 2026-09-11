<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Step-up re-authentication screen for EnsurePasswordConfirmed. Confirming
 * only stamps the session so the next attempt at the originally-blocked
 * action succeeds — it never replays that action itself (the guarded
 * routes are POST/DELETE with no request body to hold onto here), so the
 * admin lands back on the page they were on and re-clicks the action.
 */
class PasswordConfirmationController extends Controller
{
    public function show()
    {
        return view('admin.auth.confirm-password');
    }

    public function confirm(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), Auth::user()->password)) {
            return back()->withErrors(['password' => __('That password is incorrect.')]);
        }

        $request->session()->put('auth.password_confirmed_at', now()->timestamp);

        return redirect()->to($request->session()->pull('url.intended', route('admin.dashboard')));
    }
}
