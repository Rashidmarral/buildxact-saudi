<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
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
        return view('user.roles.form', ['role' => new Role, 'catalog' => Permissions::catalog()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        $data['company_id'] = Auth::user()->company_id;
        $data['is_system'] = false;

        Role::create($data);

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

        $role->update($this->validated($request));

        return redirect()->route('app.roles.index')->with('status', __('Role updated.'));
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            abort(403);
        }

        $role->delete();

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
