<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index()
    {
        $accounts = BankAccount::orderBy('name')->get();

        return view('user.bank-accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('user.bank-accounts.form', ['account' => new BankAccount]);
    }

    public function store(Request $request)
    {
        BankAccount::create($this->validated($request));

        return redirect()->route('app.bank-accounts.index')->with('status', __('Bank account added.'));
    }

    public function edit(BankAccount $bankAccount)
    {
        return view('user.bank-accounts.form', ['account' => $bankAccount]);
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $bankAccount->update($this->validated($request));

        return redirect()->route('app.bank-accounts.index')->with('status', __('Bank account updated.'));
    }

    public function destroy(BankAccount $bankAccount)
    {
        if ($bankAccount->receiptVouchers()->exists() || $bankAccount->paymentVouchers()->exists()) {
            return back()->withErrors(['bank_account' => __('This account has vouchers recorded against it and cannot be deleted.')]);
        }

        $bankAccount->delete();

        return redirect()->route('app.bank-accounts.index')->with('status', __('Bank account deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:bank,cash'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric'],
        ]);
    }
}
