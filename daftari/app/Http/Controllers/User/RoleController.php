<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderByDesc('is_system')->orderBy('name')->get();

        return view('user.roles.index', compact('roles'));
    }

    public function create()
    {
        if (! Auth::user()->company->hasFeature('roles_permissions')) {
            return redirect()->route('app.roles.index')
                ->withErrors(['plan_limit' => __('Custom roles aren\'t included in your current plan. Upgrade your plan to create custom roles.')]);
        }

        return view('user.roles.form', ['role' => new Role, 'catalog' => Permissions::catalog()]);
    }

    public function store(Request $request)
    {
        if (! Auth::user()->company->hasFeature('roles_permissions')) {
            return redirect()->route('app.roles.index')
                ->withErrors(['plan_limit' => __('Custom roles aren\'t included in your current plan. Upgrade your plan to create custom roles.')]);
        }

        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        $data['company_id'] = Auth::user()->company_id;
        $data['is_system'] = false;

        $role = Role::create($data);

        // Security audit finding D-4: role/permission changes went
        // completely unlogged — a compromised owner account (or a
        // disgruntled one on its way out) could grant itself or another
        // account broad permissions with no trace on the company's own
        // Activity page.
        AuditLog::record('role.create', $role, __('Created role :name with permissions: :permissions', [
            'name' => $role->name, 'permissions' => implode(', ', $role->permissions) ?: __('none'),
        ]), new: $role->only(['name', 'permissions']));

        return redirect()->route('app.roles.index')->with('status', __('Custom role created.'));
    }

    public function show(Role $role)
    {
        return view('user.roles.show', ['role' => $role, 'catalog' => Permissions::catalog()]);
    }

    public function edit(Role $role)
    {
        if ($role->is_system) {
            return redirect()->route('app.roles.show', $role);
        }

        return view('user.roles.form', ['role' => $role, 'catalog' => Permissions::catalog()]);
    }

    public function update(Request $request, Role $role)
    {
        if ($role->is_system) {
            abort(403);
        }

        $old = $role->only(['name', 'permissions']);

        $role->update($this->validated($request));

        AuditLog::record('role.update', $role, __('Updated role :name — permissions now: :permissions', [
            'name' => $role->name, 'permissions' => implode(', ', $role->permissions) ?: __('none'),
        ]), old: $old, new: $role->only(['name', 'permissions']));

        return redirect()->route('app.roles.index')->with('status', __('Role updated.'));
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            abort(403);
        }

        $old = $role->only(['name', 'permissions']);
        $role->delete();

        AuditLog::record('role.delete', null, __('Deleted role :name', ['name' => $old['name']]), old: $old, companyId: $role->company_id);

        return redirect()->route('app.roles.index')->with('status', __('Role deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $data['permissions'] = array_values(array_filter(
            $data['permissions'] ?? [],
            fn ($key) => Permissions::isValid($key)
        ));

        return $data;
    }
}
