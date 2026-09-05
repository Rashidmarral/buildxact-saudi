<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PhoneOtp;
use App\Models\SmsConfig;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Phone OTP verification, gated behind Platform Settings → Signup →
 * "Require phone verification". Reuses the company's own SMS gateway
 * (App\Models\SmsConfig / App\Services\SmsService — the same one used for
 * invoice/expense SMS notifications) rather than a separate platform-level
 * sender, so no new outbound integration is introduced.
 */
class PhoneVerificationController extends Controller
{
    public function show(Request $request)
    {
        if (! $request->user()->needsPhoneVerification()) {
            return redirect()->route('app.dashboard');
        }

        return view('auth.verify-phone', [
            'phone' => $request->user()->phone,
            'smsConfigured' => (bool) SmsConfig::where('is_enabled', true)->first(),
        ]);
    }

    public function sendCode(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $user = $request->user();

        if ($user->phone !== $data['phone']) {
            $user->forceFill(['phone' => $data['phone'], 'phone_verified_at' => null])->save();
        }

        $config = SmsConfig::where('is_enabled', true)->first();

        if (! $config) {
            return back()->withErrors(['phone' => __('SMS is not configured for your company yet. Ask an admin to set it up under Settings → SMS, or contact support.')]);
        }

        $existing = PhoneOtp::find($user->id);
        if ($existing && $existing->created_at?->addSeconds(60)->isFuture()) {
            return back()->withErrors(['phone' => __('Please wait a moment before requesting another code.')]);
        }

        $code = (string) random_int(100000, 999999);

        PhoneOtp::updateOrCreate(
            ['user_id' => $user->id],
            ['code' => $code, 'expires_at' => now()->addMinutes(10), 'attempts' => 0]
        );

        $result = app(SmsService::class)->send($config, $data['phone'], __('Your Daftari verification code is: :code', ['code' => $code]));

        if (! $result['success']) {
            return back()->withErrors(['phone' => __('Could not send the code: :error', ['error' => $result['error']])]);
        }

        return back()->with('status', __('Code sent — check your phone.'));
    }

    public function verifyCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $otp = PhoneOtp::find($user->id);

        if (! $otp || $otp->expires_at->isPast()) {
            return back()->withErrors(['code' => __('That code has expired. Request a new one.')]);
        }

        if ($otp->attempts >= 5) {
            return back()->withErrors(['code' => __('Too many attempts. Request a new code.')]);
        }

        if (! Str::of($data['code'])->trim()->exactly($otp->code)) {
            $otp->increment('attempts');

            return back()->withErrors(['code' => __('That code is incorrect.')]);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();
        $otp->delete();

        return redirect()->route('app.dashboard')->with('status', __('Phone verified — thanks!'));
    }
}
