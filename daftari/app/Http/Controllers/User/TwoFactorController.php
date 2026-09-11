<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Totp;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        if ($user->hasTwoFactorEnabled()) {
            return view('user.settings.two-factor', ['user' => $user, 'enabled' => true]);
        }

        // Reuse the pending secret across page loads (e.g. the user
        // navigated away mid-setup) instead of generating a new one — and
        // therefore a new QR code — on every visit.
        if (! $user->two_factor_secret) {
            $user->update(['two_factor_secret' => Totp::generateSecret()]);
            $user->refresh();
        }

        $qr = Builder::create()
            ->data(Totp::provisioningUri($user->two_factor_secret, $user->email, config('app.name')))
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Low)
            ->size(220)
            ->margin(4)
            ->build();

        return view('user.settings.two-factor', [
            'user' => $user,
            'enabled' => false,
            'secret' => $user->two_factor_secret,
            'qrImage' => base64_encode($qr->getString()),
        ]);
    }

    public function confirm(Request $request)
    {
        $user = Auth::user();
        abort_if($user->hasTwoFactorEnabled() || ! $user->two_factor_secret, 404);

        $data = $request->validate(['code' => ['required', 'string']]);

        if (! Totp::verify($user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => __('That code is incorrect. Please try again.')]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        AuditLog::record('user.two_factor_enabled', $user, __('Enabled two-factor authentication'));

        return view('user.settings.two-factor-recovery-codes', ['codes' => $recoveryCodes]);
    }

    public function disable(Request $request)
    {
        $user = Auth::user();
        $request->validate(['current_password' => ['required', 'string']]);

        if (! Hash::check($request->string('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => __('The current password is incorrect.')]);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        AuditLog::record('user.two_factor_disabled', $user, __('Disabled two-factor authentication'));

        return redirect()->route('app.settings.two-factor')->with('status', __('Two-factor authentication turned off.'));
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->hasTwoFactorEnabled(), 404);

        $request->validate(['current_password' => ['required', 'string']]);

        if (! Hash::check($request->string('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => __('The current password is incorrect.')]);
        }

        $codes = $this->generateRecoveryCodes();
        $user->update(['two_factor_recovery_codes' => $codes]);

        AuditLog::record('user.two_factor_recovery_codes_regenerated', $user, __('Regenerated two-factor recovery codes'));

        return view('user.settings.two-factor-recovery-codes', ['codes' => $codes]);
    }

    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)))
            ->all();
    }
}
