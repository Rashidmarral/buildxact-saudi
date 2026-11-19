<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class TwoFactorChallengeController extends Controller
{
    /**
     * How long a password-verified-but-not-yet-2FA'd session may sit on
     * the challenge screen. Security audit finding SEC-09: this used to
     * have no expiry at all — the pending challenge lived as long as the
     * session did.
     */
    private const CHALLENGE_TTL_MINUTES = 5;

    /**
     * Reached only after AuthController::login() has already verified the
     * password and then deliberately logged the user back out — this
     * controller is what actually completes the login, once the second
     * factor checks out. Identifies the pending user via a session key
     * only the password step could have set, not a route parameter, so a
     * guest can't jump straight here for an arbitrary account.
     */
    public function show(Request $request)
    {
        if (! $this->hasValidPendingChallenge($request)) {
            $request->session()->forget(['two_factor_user_id', 'two_factor_remember', 'two_factor_challenge_expires_at']);

            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        abort_unless($this->hasValidPendingChallenge($request), 403);

        $user = User::find($request->session()->get('two_factor_user_id'));
        abort_unless($user && $user->hasTwoFactorEnabled(), 403);

        $data = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (! empty($data['code'])) {
            $valid = $this->consumeTotpCode($user, $data['code']);
        } elseif (! empty($data['recovery_code'])) {
            $valid = $this->consumeRecoveryCode($user, $data['recovery_code']);
        } else {
            $valid = false;
        }

        if (! $valid) {
            // Security audit finding D-3: no one is authenticated yet at
            // this point (Auth::login() hasn't run), so — same as a failed
            // password attempt in AuthController::login() — attribute the
            // entry to the account being challenged explicitly rather than
            // relying on AuditLog::record()'s auth()->user() default.
            AuditLog::record('auth.two_factor_failed', $user, __('Failed two-factor code at login'), actorId: $user->id);

            return back()->withErrors(['code' => __('That code is invalid or has already been used.')]);
        }

        // Security audit finding SEC-09: recheck account state right
        // before establishing the session — it could have changed between
        // the password step and now (an admin suspending the account
        // mid-challenge, for instance), and the password step's own check
        // is stale by the time this runs.
        $user->refresh();
        if ($user->status !== 'active' || ($user->company && $user->company->isSuspended())) {
            AuditLog::record('auth.login_blocked', $user, __('Login blocked at two-factor step — account or company is not active'), actorId: $user->id);
            $request->session()->forget(['two_factor_user_id', 'two_factor_remember', 'two_factor_challenge_expires_at']);

            return redirect()->route('login')->withErrors(['email' => __('This account is not active.')]);
        }

        $remember = $request->session()->pull('two_factor_remember', false);
        $request->session()->forget(['two_factor_user_id', 'two_factor_challenge_expires_at']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        AuditLog::record('auth.login', $user, __('Logged in (two-factor verified)'));

        if ($user->isSuperAdmin() || $user->isAdminStaff()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('app.dashboard'));
    }

    private function hasValidPendingChallenge(Request $request): bool
    {
        if (! $request->session()->has('two_factor_user_id')) {
            return false;
        }

        $expiresAt = $request->session()->get('two_factor_challenge_expires_at');

        return $expiresAt && now()->lessThan(Carbon::parse($expiresAt));
    }

    /**
     * Security audit finding SEC-09: a bare Totp::verify() call has no
     * persisted state, so the same valid code could be replayed. Locks the
     * user row, re-derives the matched time step, and only accepts it if
     * it's strictly newer than the last one consumed — atomic against two
     * concurrent submissions of the same code.
     */
    private function consumeTotpCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $locked = User::lockForUpdate()->find($user->id);
            $step = Totp::matchedStep($locked->two_factor_secret, $code);

            if ($step === null) {
                return false;
            }

            if ($locked->two_factor_last_totp_step !== null && $step <= $locked->two_factor_last_totp_step) {
                return false;
            }

            $locked->update(['two_factor_last_totp_step' => $step]);

            return true;
        });
    }

    /**
     * Security audit finding SEC-09: the previous read-then-update had no
     * lock, so two concurrent submissions of the same recovery code could
     * both read it as still valid before either write removed it.
     */
    private function consumeRecoveryCode(User $user, string $submitted): bool
    {
        $submitted = strtoupper(trim($submitted));

        return DB::transaction(function () use ($user, $submitted) {
            $locked = User::lockForUpdate()->find($user->id);
            $codes = $locked->two_factor_recovery_codes ?? [];

            foreach ($codes as $code) {
                if (hash_equals($code, $submitted)) {
                    $locked->update([
                        'two_factor_recovery_codes' => array_values(array_diff($codes, [$code])),
                    ]);

                    return true;
                }
            }

            return false;
        });
    }
}
