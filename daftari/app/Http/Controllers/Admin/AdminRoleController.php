<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\AuditLog;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRoleController extends Controller
{
    public function index()
    {
        return view('admin.admin-roles.index', [
            'adminRoles' => AdminRole::withCount('users')->orderByDesc('is_system')->orderBy('name')->get(),
            'permissionCatalog' => AdminPermissions::catalog(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $adminRole = AdminRole::create($data);

        AuditLog::record('admin_role.create', null, __('Created admin role :name with permissions: :permissions', [
            'name' => $adminRole->name, 'permissions' => implode(', ', $adminRole->permissions) ?: __('none'),
        ]), new: $adminRole->only(['name', 'slug', 'permissions']));

        return back()->with('status', __('Admin role created.'));
    }

    public function update(Request $request, AdminRole $adminRole)
    {
        if ($adminRole->is_system) {
            return back()->withErrors(['admin_role' => __('System admin roles cannot be edited.')]);
        }

        $old = $adminRole->only(['name', 'slug', 'permissions']);

        $adminRole->update($this->validated($request, $adminRole));

        AuditLog::record('admin_role.update', null, __('Updated admin role :name — permissions now: :permissions', [
            'name' => $adminRole->name, 'permissions' => implode(', ', $adminRole->permissions) ?: __('none'),
        ]), old: $old, new: $adminRole->only(['name', 'slug', 'permissions']));

        return back()->with('status', __('Admin role updated.'));
    }

    public function destroy(AdminRole $adminRole)
    {
        if ($adminRole->is_system) {
            return back()->withErrors(['admin_role' => __('System admin roles cannot be deleted.')]);
        }

        $old = $adminRole->only(['name', 'slug', 'permissions']);
        $adminRole->delete();

        AuditLog::record('admin_role.delete', null, __('Deleted admin role :name', ['name' => $old['name']]), old: $old);

        return back()->with('status', __('Admin role deleted.'));
    }

    private function validated(Request $request, ?AdminRole $adminRole = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('admin_roles', 'slug')->ignore($adminRole?->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(AdminPermissions::keys())],
        ]);

        $data['permissions'] = $data['permissions'] ?? [];

        return $data;
    }
}
