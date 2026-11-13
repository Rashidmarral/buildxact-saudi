<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Services\BankAccountLedgerService;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * "add reports for bank accounts like custody accounts cash bank using
 * beautiful and modern layouts so i can show the owner each account
 * reports" — a proper running-balance statement per account (opening
 * balance, every receipt/payment/transfer/expense/income that moved
 * through it, closing balance) plus a branded PDF, for every account
 * type: a plain bank account, a cash drawer, or a personal/custody
 * float. This already existed, but only inside the separately-gated
 * Project Cash Flow module (ProjectCashFlowController::bankAccountShow()/
 * bankAccountPdf()) — a company without that module/permission had no
 * statement for a bank/cash/custody account at all, just the flat,
 * unstyled "Transactions" list. Shares the exact same running-balance
 * ledger (BankAccountLedgerService) with that module so the two can never
 * disagree, but is reachable from the ordinary cash_banks permission
 * every company already has, with no bank-account-only project dimension
 * to think about.
 */
class BankAccountReportController extends Controller
{
    public function __construct(private BankAccountLedgerService $ledger)
    {
    }

    /**
     * The landing page: every active account grouped the way an owner
     * actually thinks about them — Bank, Cash, and Custody/advances (the
     * personal, pass-through accounts Cash Custody already tracks) —
     * each with its live balance and a link into its own statement.
     */
    public function index()
    {
        $accounts = BankAccount::where('is_active', true)
            ->orderBy('name')
            ->get();

        $groups = [
            'bank' => $accounts->where('is_personal', false)->where('type', 'bank')->values(),
            'cash' => $accounts->where('is_personal', false)->where('type', 'cash')->values(),
            'custody' => $accounts->where('is_personal', true)->values(),
        ];

        return view('user.account-reports.index', [
            'groups' => $groups,
            'totalBalance' => $accounts->sum(fn (BankAccount $a) => $a->currentBalance()),
        ]);
    }

    public function show(BankAccount $bankAccount, Request $request)
    {
        [$from, $to] = $this->period($request);
        $ledger = $this->ledger->build(projectId: null, bankAccountId: $bankAccount->id, from: $from, to: $to);

        return view('user.account-reports.show', [
            'account' => $bankAccount,
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => $this->ledger->summarize($ledger['rows']),
            'ledger' => $ledger,
        ]);
    }

    public function pdf(BankAccount $bankAccount, Request $request, MpdfRenderer $renderer)
    {
        [$from, $to] = $this->period($request);
        $ledger = $this->ledger->build(projectId: null, bankAccountId: $bankAccount->id, from: $from, to: $to);
        $company = Auth::user()->company;

        $pdf = $renderer->render('documents.print.bank-account-report-pdf', [
            'company' => $company,
            'account' => $bankAccount,
            'template' => $company->defaultTemplateFor('project_cash_flow'),
            'period' => ['from' => $from, 'to' => $to],
            'summary' => $this->ledger->summarize($ledger['rows']),
            'ledger' => $ledger,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($bankAccount->name).'-statement.pdf"',
        ]);
    }

    private function period(Request $request): array
    {
        return [
            $request->filled('from') ? $request->query('from') : null,
            $request->filled('to') ? $request->query('to') : null,
        ];
    }
}
