<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ImportsCsv;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\BankTransfer;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BankReconciliationController extends Controller
{
    use ImportsCsv;

    public function index(BankAccount $bankAccount)
    {
        return view('user.bank-accounts.reconciliations.index', [
            'bankAccount' => $bankAccount,
            'reconciliations' => $bankAccount->reconciliations()->latest('statement_date')->latest('id')->get(),
        ]);
    }

    public function create(BankAccount $bankAccount)
    {
        return view('user.bank-accounts.reconciliations.create', ['bankAccount' => $bankAccount]);
    }

    public function store(Request $request, BankAccount $bankAccount)
    {
        $data = $request->validate([
            'statement_date' => ['required', 'date'],
            'statement_ending_balance' => ['required', 'numeric'],
        ]);

        $reconciliation = BankReconciliation::create($data + [
            'bank_account_id' => $bankAccount->id,
            'created_by' => Auth::id(),
            'status' => 'in_progress',
        ]);

        return redirect()->route('app.bank-reconciliations.show', $reconciliation)->with('status', __('Reconciliation started.'));
    }

    public function show(BankReconciliation $bankReconciliation)
    {
        $bankReconciliation->load('bankAccount', 'lines');

        return view('user.bank-accounts.reconciliations.show', [
            'reconciliation' => $bankReconciliation,
            'candidates' => $this->unmatchedBookTransactions($bankReconciliation),
        ]);
    }

    public function import(Request $request, BankReconciliation $bankReconciliation)
    {
        abort_if($bankReconciliation->status !== 'in_progress', 422, __('This reconciliation is already completed.'));

        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $result = $this->runCsvImport(
            $request->file('file'),
            function (array $row) {
                if (empty($row['date']) || empty($row['amount'])) {
                    throw ValidationException::withMessages(['row' => __('Date and amount are required.')]);
                }

                return [
                    'date' => $row['date'],
                    'description' => $row['description'] ?? '',
                    'reference' => $row['reference'] ?? null,
                    'amount' => (float) str_replace(',', '', $row['amount']),
                ];
            },
            fn (array $data) => $bankReconciliation->lines()->create($data),
        );

        $this->autoMatch($bankReconciliation);

        return back()->with('status', __(':imported of :total statement lines imported.', ['imported' => $result['imported'], 'total' => $result['total']]))
            ->with('import_errors', $result['errors']);
    }

    public function match(Request $request, BankStatementLine $bankStatementLine)
    {
        abort_if($bankStatementLine->reconciliation->status !== 'in_progress', 422, __('This reconciliation is already completed.'));

        $data = $request->validate([
            'matched_type' => ['required', Rule::in(array_keys(BankStatementLine::MATCHABLE_TYPES))],
            'matched_id' => ['required', 'integer'],
        ]);

        $bankStatementLine->update($data);

        return back()->with('status', __('Line matched.'));
    }

    public function unmatch(BankStatementLine $bankStatementLine)
    {
        $bankStatementLine->update(['matched_type' => null, 'matched_id' => null]);

        return back()->with('status', __('Match removed.'));
    }

    public function complete(BankReconciliation $bankReconciliation)
    {
        $bankReconciliation->update(['status' => 'completed', 'completed_at' => now()]);

        return redirect()->route('app.bank-reconciliations.index', $bankReconciliation->bank_account_id)->with('status', __('Reconciliation completed.'));
    }

    public function destroy(BankReconciliation $bankReconciliation)
    {
        abort_if($bankReconciliation->status !== 'in_progress', 422, __('Only an in-progress reconciliation can be discarded.'));

        $bankAccountId = $bankReconciliation->bank_account_id;
        $bankReconciliation->delete();

        return redirect()->route('app.bank-reconciliations.index', $bankAccountId)->with('status', __('Reconciliation discarded.'));
    }

    /**
     * Matches each freshly-imported, still-unmatched line to a candidate
     * book transaction of the same sign, same amount, and the closest
     * date within a 5-day window — narrow enough to avoid false positives
     * between genuinely different transactions, generous enough to absorb
     * ordinary bank clearing delay. Anything left over waits for a manual
     * match.
     */
    private function autoMatch(BankReconciliation $reconciliation): void
    {
        $candidates = $this->unmatchedBookTransactions($reconciliation);

        foreach ($reconciliation->lines()->whereNull('matched_type')->get() as $line) {
            $match = $candidates
                ->where('amount', (float) $line->amount)
                ->filter(fn ($c) => abs($c['date']->diffInDays($line->date)) <= 5)
                ->sortBy(fn ($c) => abs($c['date']->diffInDays($line->date)))
                ->first();

            if (! $match) {
                continue;
            }

            $line->update(['matched_type' => $match['type'], 'matched_id' => $match['id']]);
            $candidates = $candidates->reject(fn ($c) => $c['type'] === $match['type'] && $c['id'] === $match['id']);
        }
    }

    /**
     * Every receipt voucher, payment voucher, and bank transfer that moved
     * through this bank account and isn't already matched to a statement
     * line in ANY reconciliation (a voucher represents one real bank
     * movement — it can only ever be reconciled once). Amount sign is
     * normalized to how it would read on a statement: receipts and
     * incoming transfers positive, payments and outgoing transfers
     * negative.
     */
    private function unmatchedBookTransactions(BankReconciliation $reconciliation)
    {
        $accountId = $reconciliation->bank_account_id;

        $alreadyMatched = fn (string $type) => BankStatementLine::where('matched_type', $type)->pluck('matched_id')->all();

        $receipts = ReceiptVoucher::where('bank_account_id', $accountId)
            ->where('status', 'issued')
            ->whereNotIn('id', $alreadyMatched('receipt_voucher'))
            ->get()
            ->map(fn ($v) => ['type' => 'receipt_voucher', 'id' => $v->id, 'date' => $v->date, 'amount' => (float) $v->amount, 'label' => __('Receipt :number — :party', ['number' => $v->voucher_number, 'party' => $v->payer_name])]);

        $payments = PaymentVoucher::where('bank_account_id', $accountId)
            ->where('status', 'issued')
            ->whereNotIn('id', $alreadyMatched('payment_voucher'))
            ->get()
            ->map(fn ($v) => ['type' => 'payment_voucher', 'id' => $v->id, 'date' => $v->date, 'amount' => -(float) $v->amount, 'label' => __('Payment :number — :party', ['number' => $v->voucher_number, 'party' => $v->payee_name])]);

        $transfersOut = BankTransfer::where('from_bank_account_id', $accountId)
            ->whereNotIn('id', $alreadyMatched('bank_transfer'))
            ->get()
            ->map(fn ($t) => ['type' => 'bank_transfer', 'id' => $t->id, 'date' => $t->date, 'amount' => -(float) $t->amount, 'label' => __('Transfer to :account', ['account' => $t->toAccount->name])]);

        $transfersIn = BankTransfer::where('to_bank_account_id', $accountId)
            ->whereNotIn('id', $alreadyMatched('bank_transfer'))
            ->get()
            ->map(fn ($t) => ['type' => 'bank_transfer', 'id' => $t->id, 'date' => $t->date, 'amount' => (float) $t->amount, 'label' => __('Transfer from :account', ['account' => $t->fromAccount->name])]);

        return $receipts->concat($payments)->concat($transfersOut)->concat($transfersIn)->values();
    }
}
