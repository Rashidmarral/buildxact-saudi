<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorChallengeController extends Controller
{
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
        if (! $request->session()->has('two_factor_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $userId = $request->session()->get('two_factor_user_id');
        abort_unless($userId, 403);

        $user = User::find($userId);
        abort_unless($user && $user->hasTwoFactorEnabled(), 403);

        $data = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (! empty($data['code'])) {
            $valid = Totp::verify($user->two_factor_secret, $data['code']);
        } elseif (! empty($data['recovery_code'])) {
            $valid = $this->consumeRecoveryCode($user, $data['recovery_code']);
        } else {
            $valid = false;
        }

        if (! $valid) {
            return back()->withErrors(['code' => __('That code is invalid or has already been used.')]);
        }

        $remember = $request->session()->pull('two_factor_remember', false);
        $request->session()->forget('two_factor_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        if ($user->isSuperAdmin() || $user->isAdminStaff()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('app.dashboard'));
    }

    private function consumeRecoveryCode(User $user, string $submitted): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $submitted = strtoupper(trim($submitted));

        foreach ($codes as $code) {
            if (hash_equals($code, $submitted)) {
                $user->update([
                    'two_factor_recovery_codes' => array_values(array_diff($codes, [$code])),
                ]);

                return true;
            }
        }

        return false;
    }
}
