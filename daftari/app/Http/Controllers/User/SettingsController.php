<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        $company = Auth::user()->company;
        $branches = Branch::orderBy('name')->get();
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('name')->get();

        return view('user.settings.index', compact('company', 'branches', 'bankAccounts'));
    }

    public function update(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'cr_number' => ['nullable', 'string', 'max:32'],
            'alternative_seller_id_type' => ['nullable', 'in:commercial_registration,momra_license,mhrsd_license,passport_number,gcc_id,other_id'],
            'alternative_seller_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'invoice_prefix' => ['required', 'string', 'max:10'],
            'primary_customer_type' => ['required', 'in:b2b,b2c,mixed'],
            'negative_number_format' => ['required', 'in:minus,parentheses'],
            'default_branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'default_bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $company = Auth::user()->company;
        unset($data['logo']);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $company->update($data);

        return back()->with('status', __('Settings saved.'));
    }
}
