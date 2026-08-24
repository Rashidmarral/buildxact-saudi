<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class TeamInviteController extends Controller
{
    /**
     * Both actions share this single signed URL: the GET renders the
     * "set your password" form, the POST (submitted back to the exact
     * same URL, expires/signature included) accepts it. Reusing one URL
     * for both verbs means the "signed" middleware protects them
     * identically, with no separate token storage needed.
     */
    public function show(Request $request, string $id, string $hash)
    {
        $member = User::findOrFail($id);

        if (! hash_equals(sha1($member->email), $hash) || $member->status !== 'invited') {
            return view('auth.invite-invalid');
        }

        return view('auth.invite-accept', ['member' => $member]);
    }

    public function accept(Request $request, int $id, string $hash)
    {
        $member = User::findOrFail($id);

        if (! hash_equals(sha1($member->email), $hash) || $member->status !== 'invited') {
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

        Auth::login($member);
        $request->session()->regenerate();

        return redirect()->route('app.dashboard')->with('status', __('Welcome aboard!'));
    }
}
