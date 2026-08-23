<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
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

        $temporaryPassword = Str::password(12);

        $member = User::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => $data['role'],
            'status' => 'active',
            // The owner (already authenticated) is vouching for this
            // address directly, unlike self-registration where nobody has
            // confirmed the signup form's email actually belongs to the
            // signer — so there's no separate address to verify here.
            'email_verified_at' => now(),
        ]);

        $member->roles()->sync($data['role_ids'] ?? []);

        AuditLog::record('team.add', $member, __('Added team member :email', ['email' => $member->email]));

        // No transactional email provider wired up yet — show the temporary
        // password once so the owner can share it with the invitee directly.
        return back()->with('status', __('Team member added.'))->with('temporary_password', [
            'email' => $member->email,
            'password' => $temporaryPassword,
        ]);
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
