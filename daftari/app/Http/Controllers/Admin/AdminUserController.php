<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index()
    {
        $admins = User::withoutGlobalScopes()
            ->whereIn('role', ['super_admin', 'admin_staff'])
            ->with('adminRoles')
            ->orderBy('name')
            ->get();

        $adminRoles = AdminRole::orderBy('name')->get();

        return view('admin.admins.index', compact('admins', 'adminRoles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'admin_type' => ['required', 'in:super_admin,admin_staff'],
            'admin_role_ids' => ['nullable', 'array'],
            'admin_role_ids.*' => [Rule::exists('admin_roles', 'id')],
        ]);

        $admin = User::create([
            'company_id' => null,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['admin_type'],
            'status' => 'active',
        ]);

        if ($data['admin_type'] === 'admin_staff') {
            $admin->adminRoles()->sync($data['admin_role_ids'] ?? []);
        }

        AuditLog::record('admin.create', null, __('Added admin :name (:email) as :type', [
            'name' => $admin->name, 'email' => $admin->email, 'type' => $data['admin_type'],
        ]));

        return back()->with('status', __('Admin added.'));
    }

    public function destroy(User $user)
    {
        if (! in_array($user->role, ['super_admin', 'admin_staff'], true)) {
            abort(404);
        }

        if ($user->id === Auth::id()) {
            return back()->withErrors(['user' => __('You cannot remove yourself.')]);
        }

        $name = $user->name;
        $email = $user->email;
        $user->delete();

        AuditLog::record('admin.delete', null, __('Removed admin :name (:email)', ['name' => $name, 'email' => $email]));

        return back()->with('status', __('Admin removed.'));
    }
}
