<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        if (Auth::user()->company->hasReachedPlanLimit('bank_accounts')) {
            return back()->withErrors(['plan_limit' => __('You have reached your plan\'s bank/cash account limit. Upgrade your plan to add more accounts.')])->withInput();
        }

        $bankAccount = BankAccount::create($this->validated($request) + [
            'is_active' => $request->boolean('is_active', true),
            'is_personal' => $request->boolean('is_personal'),
        ]);

        $bankAccount->glAccount();

        return redirect()->route('app.bank-accounts.index')->with('status', __('Bank account added.'));
    }

    public function edit(BankAccount $bankAccount)
    {
        return view('user.bank-accounts.form', ['account' => $bankAccount]);
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $bankAccount->update($this->validated($request) + [
            'is_active' => $request->boolean('is_active', true),
            'is_personal' => $request->boolean('is_personal'),
        ]);

        // Keep the dedicated ledger line's name matching this account's —
        // a renamed "SAB Bank – old" shouldn't leave its GL line stuck
        // with the old name. Accounts created before this existed get
        // their dedicated line created here too, the first time they're
        // touched.
        $glAccount = $bankAccount->glAccount();
        if ($glAccount->name !== $bankAccount->name) {
            $glAccount->update(['name' => $bankAccount->name]);
        }

        return redirect()->route('app.bank-accounts.index')->with('status', __('Bank account updated.'));
    }

    /**
     * Optional, one-time, user-reviewed: moves a confirmed amount from
     * the shared Default Bank/Default Cash account into this account's
     * own dedicated ledger line (see BankAccount::glAccount()). Deliber-
     * ately NOT computed automatically from BankAccount::currentBalance()
     * — that figure includes this account's own never-posted
     * opening_balance and isn't guaranteed to match what's actually
     * sitting in the shared GL account today, so splitting history
     * between banks is something the user (or their accountant) confirms
     * the number for, not something code silently guesses at.
     */
    public function transferGlOpeningBalance(Request $request, BankAccount $bankAccount, LedgerPostingService $ledger)
    {
        $sourceType = 'bank_account_gl_transfer';

        if (JournalEntry::where('company_id', $bankAccount->company_id)->where('source_type', $sourceType)->where('source_id', $bankAccount->id)->exists()) {
            return back()->withErrors(['amount' => __('A GL opening balance has already been transferred for this account.')]);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'date' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($bankAccount, $data, $ledger, $sourceType) {
            $company = $bankAccount->company;
            $dedicated = $bankAccount->glAccount();
            $shared = AccountMapping::resolve($company->id, $bankAccount->type === 'cash' ? 'DEFAULT_CASH' : 'DEFAULT_BANK');

            abort_unless($shared, 422, __('The shared Default Cash/Default Bank account mapping is not configured.'));

            $amount = (float) $data['amount'];
            $lines = $amount > 0
                ? [
                    ['account_id' => $dedicated->id, 'debit' => $amount],
                    ['account_id' => $shared->id, 'credit' => $amount],
                ]
                : [
                    ['account_id' => $shared->id, 'debit' => abs($amount)],
                    ['account_id' => $dedicated->id, 'credit' => abs($amount)],
                ];

            $ledger->post($company, $sourceType, $bankAccount->id, __('GL opening balance transfer for :name', ['name' => $bankAccount->name]), \Carbon\Carbon::parse($data['date']), $lines);
        });

        return back()->with('status', __('GL opening balance transferred to this account\'s own ledger line.'));
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
                'status' => $t->status,
                'url' => route('app.bank-transfers.show', $t),
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
            'personal_owner_name' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
