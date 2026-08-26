<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', [
            'demoEnabled' => Setting::getBool('general_allow_demo_accounts'),
        ]);
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

        if ($user->hasTwoFactorEnabled()) {
            // Password is confirmed, but not fully trusted yet — log back
            // out and hand off to the challenge, which is the only place
            // that actually calls Auth::login() for this request cycle.
            $remember = $request->boolean('remember');
            Auth::logout();
            $request->session()->put('two_factor_user_id', $user->id);
            $request->session()->put('two_factor_remember', $remember);

            return redirect()->route('two-factor.challenge');
        }

        if ($user->isSuperAdmin() || $user->isAdminStaff()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('app.dashboard'));
    }

    public function showRegister()
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $defaultPlanId = Setting::get('signup_default_plan_id') ?: null;
        $phoneRequired = Setting::getBool('signup_require_phone_verification');

        return view('auth.register', compact('plans', 'defaultPlanId', 'phoneRequired'));
    }

    public function register(Request $request)
    {
        $phoneRequired = Setting::getBool('signup_require_phone_verification');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => [$phoneRequired ? 'required' : 'nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $slug = Str::slug($data['company_name']).'-'.Str::lower(Str::random(5));

            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => $slug,
                'trial_ends_at' => now()->addDays((int) Setting::get('trial_days', config('daftari.trial_days'))),
                'currency' => Setting::get('general_default_currency', config('daftari.default_currency')),
                'locale' => Setting::get('general_default_language', config('app.locale')),
                'timezone' => Setting::get('general_default_timezone', config('app.timezone')),
            ]);

            Role::seedSystemRoles($company->id);
            Account::seedSystemAccounts($company->id);
            AccountMapping::seedDefaults($company->id);
            TaxRate::seedDefaults($company->id);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => 'owner',
                'status' => 'active',
                // Skips the verification screen entirely when the admin has
                // turned the requirement off — same as if the address were
                // already confirmed.
                'email_verified_at' => Setting::getBool('signup_require_email_verification', true) ? null : now(),
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

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        Mail::to($user->email)->send(new WelcomeMail($user->company));

        return redirect()->route('app.dashboard')->with('status', __('Welcome! Your :days-day free trial has started.', ['days' => config('daftari.trial_days')]));
    }

    public function showVerifyEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('app.dashboard');
        }

        $devVerifyUrl = null;

        // Same dev-convenience already used for password resets: nothing
        // is actually emailed unless a real mail transport is configured,
        // so surface the same signed link the notification would have sent.
        if (app()->environment(['local', 'testing']) || config('mail.default') === 'log') {
            $devVerifyUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                ['id' => $request->user()->getKey(), 'hash' => sha1($request->user()->getEmailForVerification())]
            );
        }

        return view('auth.verify-email', compact('devVerifyUrl'));
    }

    public function verifyEmail(Request $request)
    {
        $user = User::findOrFail($request->route('id'));

        // Matches Laravel's own EmailVerificationRequest::authorize(): the
        // signed link only proves the {id}/{hash} pair is genuine, not that
        // whoever clicked it is that account's owner — someone else's
        // active session must not be able to verify a different account by
        // opening the same link.
        abort_unless(hash_equals((string) $request->user()->getKey(), (string) $user->getKey()), 403);

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route('app.dashboard')->with('status', __('Email verified — welcome aboard!'));
    }

    public function resendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('app.dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('Verification link sent. Check your inbox.'));
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
