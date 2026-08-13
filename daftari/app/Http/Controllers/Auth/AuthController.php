<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => __('These credentials do not match our records.')])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->status !== 'active' || ($user->company && $user->company->isSuspended())) {
            Auth::logout();

            return back()->withErrors(['email' => __('This account is not active.')]);
        }

        if ($user->isSuperAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('app.dashboard'));
    }

    public function showRegister()
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('auth.register', compact('plans'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $slug = Str::slug($data['company_name']).'-'.Str::lower(Str::random(5));

            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => $slug,
                'trial_ends_at' => now()->addDays((int) config('daftari.trial_days')),
                'currency' => config('daftari.default_currency'),
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'owner',
                'status' => 'active',
            ]);

            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $data['plan_id'],
                'status' => 'trialing',
                'billing_cycle' => 'monthly',
                'current_period_start' => now(),
                'current_period_end' => $company->trial_ends_at,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('app.dashboard')->with('status', __('Welcome! Your :days-day free trial has started.', ['days' => config('daftari.trial_days')]));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
