<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index()
    {
        $admins = User::withoutGlobalScopes()->where('role', 'super_admin')->orderBy('name')->get();

        return view('admin.admins.index', compact('admins'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create([
            'company_id' => null,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        return back()->with('status', __('Admin added.'));
    }

    public function destroy(User $user)
    {
        if ($user->role !== 'super_admin') {
            abort(404);
        }

        if ($user->id === Auth::id()) {
            return back()->withErrors(['user' => __('You cannot remove yourself.')]);
        }

        $user->delete();

        return back()->with('status', __('Admin removed.'));
    }
}
