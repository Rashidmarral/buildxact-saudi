<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
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

        if ($user->isSuperAdmin() || $user->isAdminStaff()) {
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
                'trial_ends_at' => now()->addDays((int) Setting::get('trial_days', config('daftari.trial_days'))),
                'currency' => config('daftari.default_currency'),
            ]);

            Role::seedSystemRoles($company->id);
            Account::seedSystemAccounts($company->id);
            AccountMapping::seedDefaults($company->id);

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

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $resetUrl = null;

        $status = PasswordBroker::sendResetLink(
            $request->only('email'),
            function ($user, $token) use (&$resetUrl) {
                $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);
            }
        );

        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            // Don't reveal whether the email exists — always show the same
            // generic confirmation, matching standard password-reset practice.
            return back()->with('status', __('If an account exists for that email, a password reset link has been sent.'));
        }

        // No real mail transport is configured out of the box (MAIL_MAILER
        // defaults to "log"), so in local/testing — or whenever mail isn't
        // actually going anywhere — surface the real link directly instead
        // of silently stranding the user, the same convenience already used
        // for team invite passwords.
        if (app()->environment(['local', 'testing']) || config('mail.default') === 'log') {
            return back()->with('status', __('If an account exists for that email, a password reset link has been sent.'))
                ->with('dev_reset_url', $resetUrl);
        }

        return back()->with('status', __('If an account exists for that email, a password reset link has been sent.'));
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $status = PasswordBroker::reset(
            $data,
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return back()->withErrors(['email' => __(match ($status) {
                PasswordBroker::INVALID_TOKEN => 'This password reset link is invalid or has expired.',
                PasswordBroker::INVALID_USER => 'We could not find an account for that email.',
                default => 'Unable to reset the password. Please request a new link.',
            })])->onlyInput('email');
        }

        return redirect()->route('login')->with('status', __('Your password has been reset. You can now log in.'));
    }
}
