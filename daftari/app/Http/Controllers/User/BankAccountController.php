<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
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
        BankAccount::create($this->validated($request) + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('app.bank-accounts.index')->with('status', __('Bank account added.'));
    }

    public function edit(BankAccount $bankAccount)
    {
        return view('user.bank-accounts.form', ['account' => $bankAccount]);
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $bankAccount->update($this->validated($request) + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('app.bank-accounts.index')->with('status', __('Bank account updated.'));
    }

    public function transactions(Request $request)
    {
        $accountId = $request->integer('bank_account_id') ?: null;

        $receipts = ReceiptVoucher::with('bankAccount')
            ->when($accountId, fn ($q) => $q->where('bank_account_id', $accountId))
            ->get()
            ->map(fn ($v) => [
                'type' => 'receipt',
                'date' => $v->date,
                'number' => $v->voucher_number,
                'party' => $v->payer_name,
                'account' => $v->bankAccount->name,
                'amount' => (float) $v->amount,
                'status' => $v->status,
                'url' => route('app.receipt-vouchers.show', $v),
            ]);

        $payments = PaymentVoucher::with('bankAccount')
            ->when($accountId, fn ($q) => $q->where('bank_account_id', $accountId))
            ->get()
            ->map(fn ($v) => [
                'type' => 'payment',
                'date' => $v->date,
                'number' => $v->voucher_number,
                'party' => $v->payee_name,
                'account' => $v->bankAccount->name,
                'amount' => (float) $v->amount,
                'status' => $v->status,
                'url' => route('app.payment-vouchers.show', $v),
            ]);

        $transfers = BankTransfer::with('fromAccount', 'toAccount')
            ->when($accountId, fn ($q) => $q->where(fn ($q2) => $q2->where('from_bank_account_id', $accountId)->orWhere('to_bank_account_id', $accountId)))
            ->get()
            ->map(fn ($t) => [
                'type' => 'transfer',
                'date' => $t->date,
                'number' => null,
                'party' => __(':from → :to', ['from' => $t->fromAccount->name, 'to' => $t->toAccount->name]),
                'account' => $accountId && (int) $t->to_bank_account_id === $accountId ? $t->toAccount->name : $t->fromAccount->name,
                'amount' => (float) $t->amount,
                'status' => 'issued',
                'url' => route('app.bank-transfers.index'),
            ]);

        $transactions = $receipts->concat($payments)->concat($transfers)
            ->sortByDesc(fn ($t) => $t['date']->format('Y-m-d').'-'.$t['type'])
            ->values();

        $accounts = BankAccount::orderBy('name')->get();

        return view('user.bank-accounts.transactions', compact('transactions', 'accounts', 'accountId'));
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
            'currency' => ['required', 'string', 'size:3'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bank_phone' => ['nullable', 'string', 'max:30'],
            'bank_address' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric'],
            'opening_balance_date' => ['nullable', 'date'],
            'opening_balance_reference' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
