<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function index()
    {
        $company = Auth::user()->company;

        return view('user.settings.index', compact('company'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'cr_number' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'invoice_prefix' => ['required', 'string', 'max:10'],
        ]);

        Auth::user()->company->update($data);

        return back()->with('status', __('Settings saved.'));
    }
}
