<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Mail\TeamInviteMail;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    /**
     * How long an invite link stays valid before the owner has to resend
     * it. Kept generous since, unlike a password reset, the invitee may
     * not check their inbox right away.
     */
    public const INVITE_EXPIRY_HOURS = 48;

    public function index()
    {
        $members = User::with('roles')->where('company_id', Auth::user()->company_id)->orderBy('name')->get();
        $roles = Role::orderByDesc('is_system')->orderBy('name')->get();

        return view('user.team.index', compact('members', 'roles'));
    }

    public function store(Request $request)
    {
        if (Auth::user()->company->hasReachedPlanLimit('users')) {
            return back()->withErrors(['plan_limit' => __('You have reached your plan\'s team member limit. Upgrade your plan to add more users.')]);
        }

        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:owner,staff'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => [Rule::exists('roles', 'id')->where('company_id', $companyId)],
        ]);

        // No password is set yet — the invitee chooses their own when they
        // accept, via the signed link below. An unusable random hash keeps
        // the column non-null and guarantees the account can't be logged
        // into before that happens (nobody knows this string, and status
        // stays 'invited' — not 'active' — as a second, independent gate).
        $member = User::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(40)),
            'role' => $data['role'],
            'status' => 'invited',
        ]);

        $member->roles()->sync($data['role_ids'] ?? []);

        AuditLog::record('team.add', $member, __('Invited team member :email', ['email' => $member->email]));

        $devInviteUrl = $this->sendInvite($member);

        return back()->with('status', __('Invitation sent to :email.', ['email' => $member->email]))
            ->with('dev_invite_url', $devInviteUrl);
    }

    public function resendInvite(User $user)
    {
        if ($user->company_id !== Auth::user()->company_id) {
            abort(404);
        }

        if ($user->status !== 'invited') {
            return back()->withErrors(['user' => __('This member has already accepted their invite.')]);
        }

        $devInviteUrl = $this->sendInvite($user);

        return back()->with('status', __('Invitation resent to :email.', ['email' => $user->email]))
            ->with('dev_invite_url', $devInviteUrl);
    }

    protected function sendInvite(User $member): ?string
    {
        $acceptUrl = URL::temporarySignedRoute(
            'team.invite.accept',
            now()->addHours(self::INVITE_EXPIRY_HOURS),
            ['id' => $member->getKey(), 'hash' => sha1($member->email)]
        );

        Mail::to($member->email)->send(new TeamInviteMail($member, $acceptUrl));

        // Same dev-convenience already used for password resets and email
        // verification: nothing is actually emailed unless a real mail
        // transport is configured, so surface the link directly too.
        if (app()->environment(['local', 'testing']) || config('mail.default') === 'log') {
            return $acceptUrl;
        }

        return null;
    }

    public function destroy(User $user)
    {
        if ($user->company_id !== Auth::user()->company_id) {
            abort(404);
        }

        if ($user->id === Auth::id()) {
            return back()->withErrors(['user' => __('You cannot remove yourself.')]);
        }

        AuditLog::record('team.remove', $user, __('Removed team member :email', ['email' => $user->email]));

        $user->delete();

        return back()->with('status', __('Team member removed.'));
    }
}
