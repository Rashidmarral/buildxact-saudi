<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\ReceiptVoucher;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Project Cash Flow module: a running-balance statement of the actual cash
 * received, paid, and transferred for one Project (or one BankAccount),
 * plus a branded PDF export of it. Sits entirely on top of the existing,
 * ungated Cash & Banks records (ReceiptVoucher/PaymentVoucher/BankTransfer)
 * — every route here is gated behind the project_cash_flow permission and
 * module (see routes/web.php), so this controller never needs to check
 * access itself.
 */
class ProjectCashFlowController extends Controller
{
    public function index()
    {
        return view('user.project-cash-flow.index', [
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Project $project, Request $request)
    {
        $bankAccountId = $request->integer('bank_account_id') ?: null;

        $ledger = $this->buildLedger(projectId: $project->id, bankAccountId: $bankAccountId);

        return view('user.project-cash-flow.show', [
            'project' => $project,
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'selectedBankAccountId' => $bankAccountId,
            'summary' => [
                'received' => $project->cashReceived(),
                'paid' => $project->cashPaid(),
                'transferred' => $project->cashTransferredOut(),
                'net' => $project->netCashPosition(),
            ],
            'ledger' => $ledger,
        ]);
    }

    public function pdf(Project $project, Request $request, MpdfRenderer $renderer)
    {
        $bankAccountId = $request->integer('bank_account_id') ?: null;
        $ledger = $this->buildLedger(projectId: $project->id, bankAccountId: $bankAccountId);

        $pdf = $renderer->render('documents.print.project-cash-flow-pdf', [
            'title' => __('Project Cash Flow Statement'),
            'subject' => $project->name,
            'company' => $project->company,
            'template' => $project->company->defaultTemplateFor('project_cash_flow'),
            'summary' => [
                'received' => $project->cashReceived(),
                'paid' => $project->cashPaid(),
                'transferred' => $project->cashTransferredOut(),
                'net' => $project->netCashPosition(),
            ],
            'ledger' => $ledger,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($project->name).'-cash-flow.pdf"',
        ]);
    }

    public function bankAccountShow(BankAccount $bankAccount, Request $request)
    {
        $projectId = $request->integer('project_id') ?: null;

        $ledger = $this->buildLedger(projectId: $projectId, bankAccountId: $bankAccount->id);

        return view('user.project-cash-flow.bank-account-show', [
            'bankAccount' => $bankAccount,
            'projects' => Project::orderBy('name')->get(),
            'selectedProjectId' => $projectId,
            'ledger' => $ledger,
        ]);
    }

    public function bankAccountPdf(BankAccount $bankAccount, Request $request, MpdfRenderer $renderer)
    {
        $projectId = $request->integer('project_id') ?: null;
        $ledger = $this->buildLedger(projectId: $projectId, bankAccountId: $bankAccount->id);

        $pdf = $renderer->render('documents.print.project-cash-flow-pdf', [
            'title' => __('Bank Account Statement'),
            'subject' => $bankAccount->name,
            'company' => $bankAccount->company,
            'template' => $bankAccount->company->defaultTemplateFor('project_cash_flow'),
            'summary' => null,
            'ledger' => $ledger,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($bankAccount->name).'-statement.pdf"',
        ]);
    }

    /**
     * The shared running-balance calculation behind both the Project and
     * the Bank Account statement: merges receipts/payments/transfers into
     * one chronological list with an explicit in/out split, then walks it
     * accumulating a running balance.
     *
     * For a bank-account context, a transfer's direction is derived from
     * which side of from/to matches the account (mirrors
     * BankAccount::currentBalance()'s own transfer handling). For a
     * project-only context (no bank account given), a transfer tagged to
     * the project is always an outflow — see Project::cashTransferredOut().
     */
    private function buildLedger(?int $projectId, ?int $bankAccountId): array
    {
        $receipts = ReceiptVoucher::with('bankAccount', 'project')
            ->where('status', 'issued')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->get()
            ->map(fn (ReceiptVoucher $v) => [
                'date' => $v->date,
                'id' => 'receipt-'.$v->id,
                'type' => 'receipt',
                'number' => $v->voucher_number,
                'party' => $v->payer_name,
                'account' => $v->bankAccount?->name,
                'in_amount' => (float) $v->amount,
                'out_amount' => 0.0,
                'url' => route('app.receipt-vouchers.show', $v),
            ]);

        $payments = PaymentVoucher::with('bankAccount', 'project')
            ->where('status', 'issued')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->get()
            ->map(fn (PaymentVoucher $v) => [
                'date' => $v->date,
                'id' => 'payment-'.$v->id,
                'type' => 'payment',
                'number' => $v->voucher_number,
                'party' => $v->payee_name,
                'account' => $v->bankAccount?->name,
                'in_amount' => 0.0,
                'out_amount' => (float) $v->amount,
                'url' => route('app.payment-vouchers.show', $v),
            ]);

        $transfers = BankTransfer::with('fromAccount', 'toAccount', 'project')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($bankAccountId, fn ($q) => $q->where(fn ($q2) => $q2->where('from_bank_account_id', $bankAccountId)->orWhere('to_bank_account_id', $bankAccountId)))
            ->get()
            ->map(function (BankTransfer $t) use ($bankAccountId) {
                $isIncoming = $bankAccountId && (int) $t->to_bank_account_id === $bankAccountId;

                return [
                    'date' => $t->date,
                    'id' => 'transfer-'.$t->id,
                    'type' => 'transfer',
                    'number' => null,
                    'party' => __(':from → :to', ['from' => $t->fromAccount->name, 'to' => $t->toAccount->name]),
                    'account' => $bankAccountId ? ($isIncoming ? $t->toAccount->name : $t->fromAccount->name) : null,
                    'in_amount' => $isIncoming ? (float) $t->amount : 0.0,
                    'out_amount' => $isIncoming ? 0.0 : (float) $t->amount,
                    'url' => route('app.bank-transfers.index'),
                ];
            });

        // PHP's sort functions have been stable since 8.0, so rows that
        // land on the same date keep this collection's original relative
        // order (receipts, then payments, then transfers) — a plain
        // ascending sort by date alone is enough for a deterministic
        // chronological order.
        $rows = $receipts->concat($payments)->concat($transfers)
            ->sortBy(fn (array $row) => $row['date'])
            ->values();

        $openingBalance = $bankAccountId ? (float) BankAccount::find($bankAccountId)?->opening_balance : 0.0;
        $balance = $openingBalance;

        $rows = $rows->map(function (array $row) use (&$balance) {
            $balance += $row['in_amount'] - $row['out_amount'];
            $row['balance_after'] = $balance;

            return $row;
        });

        return [
            'rows' => $rows,
            'opening_balance' => $openingBalance,
            'closing_balance' => $balance,
        ];
    }
}
