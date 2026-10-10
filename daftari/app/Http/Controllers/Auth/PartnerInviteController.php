<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Mirrors Auth\TeamInviteController's single-signed-URL pattern, but for
 * a Partner's own login instead of a company team member's — kept
 * separate because that controller's accept() assumes a Company
 * (`$member->company->owners()`), which a partner user (company_id null)
 * doesn't have.
 */
class PartnerInviteController extends Controller
{
    public function show(Request $request, string $id, string $hash)
    {
        $member = User::findOrFail($id);

        if (! hash_equals(sha1($member->email), $hash) || $member->status !== 'invited' || ! $member->isPartner()) {
            return view('auth.invite-invalid');
        }

        return view('partner.invite-accept', ['member' => $member]);
    }

    public function accept(Request $request, int $id, string $hash)
    {
        $member = User::findOrFail($id);

        if (! hash_equals(sha1($member->email), $hash) || $member->status !== 'invited' || ! $member->isPartner()) {
            return view('auth.invite-invalid');
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $member->update([
            'password' => Hash::make($data['password']),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $member->partner?->update(['status' => 'active']);

        Auth::login($member);
        $request->session()->regenerate();

        return redirect()->route('partner.dashboard')->with('status', __('Welcome aboard!'));
    }
}
