<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccountType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BankAccountTypeController extends Controller
{
    public function index()
    {
        $types = BankAccountType::withCount('bankAccounts')->orderBy('name')->get();

        return view('user.bank-account-types.index', compact('types'));
    }

    public function create()
    {
        return view('user.bank-account-types.form', ['type' => new BankAccountType]);
    }

    public function store(Request $request)
    {
        $type = BankAccountType::create($this->validated($request));

        AuditLog::record('bank_account_type.create', $type, __('Created account type :name', ['name' => $type->name]));

        return redirect()->route('app.bank-account-types.index')->with('status', __('Account type added.'));
    }

    public function edit(BankAccountType $bankAccountType)
    {
        return view('user.bank-account-types.form', ['type' => $bankAccountType]);
    }

    public function update(Request $request, BankAccountType $bankAccountType)
    {
        $bankAccountType->update($this->validated($request, $bankAccountType));

        AuditLog::record('bank_account_type.update', $bankAccountType, __('Updated account type :name', ['name' => $bankAccountType->name]));

        return redirect()->route('app.bank-account-types.index')->with('status', __('Account type updated.'));
    }

    public function destroy(BankAccountType $bankAccountType)
    {
        if ($bankAccountType->bankAccounts()->exists()) {
            return back()->withErrors(['bank_account_type' => __('This type is assigned to one or more accounts and cannot be deleted.')]);
        }

        AuditLog::record('bank_account_type.delete', $bankAccountType, __('Deleted account type :name', ['name' => $bankAccountType->name]));

        $bankAccountType->delete();

        return redirect()->route('app.bank-account-types.index')->with('status', __('Account type deleted.'));
    }

    private function validated(Request $request, ?BankAccountType $type = null): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('bank_account_types', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($type?->id),
            ],
            'nature' => ['required', 'in:bank,cash'],
        ]);
    }
}
