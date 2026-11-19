<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Project;
use App\Services\BankAccountLedgerService;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Project Cash Flow module: a running-balance statement of the actual cash
 * received, paid, and transferred for one Project (or one BankAccount),
 * plus a branded PDF export of it. Sits entirely on top of the existing,
 * ungated Cash & Banks records (ReceiptVoucher/PaymentVoucher/BankTransfer)
 * plus directly-paid Expenses (Purchases & Expenses' own money-out record),
 * InvoicePayments tagged to a bank account (Sales' own money-in record —
 * an invoice payment with no bank_account_id set, e.g. one recorded before
 * that field existed, still isn't included here; it was never tied to a
 * specific account in the first place), and Income tagged to a bank
 * account (the general "money received that isn't tied to an invoice"
 * record — same caveat: one left as "Unreceived" has no account yet)
 * — every route here is gated behind the project_cash_flow permission and
 * module (see routes/web.php), so this controller never needs to check
 * access itself.
 */
class ProjectCashFlowController extends Controller
{
    public function __construct(private BankAccountLedgerService $ledger)
    {
    }

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
        [$from, $to] = $this->periodFromRequest($request);

        $ledger = $this->ledger->build(projectId: $project->id, bankAccountId: $bankAccountId, from: $from, to: $to);

        return view('user.project-cash-flow.show', [
            'project' => $project,
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'selectedBankAccountId' => $bankAccountId,
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => $this->ledger->summarize($ledger['rows']),
            'ledger' => $ledger,
        ]);
    }

    public function pdf(Project $project, Request $request, MpdfRenderer $renderer)
    {
        $bankAccountId = $request->integer('bank_account_id') ?: null;
        [$from, $to] = $this->periodFromRequest($request);
        $ledger = $this->ledger->build(projectId: $project->id, bankAccountId: $bankAccountId, from: $from, to: $to);

        $pdf = $renderer->render('documents.print.project-cash-flow-pdf', [
            'title' => __('Project Cash Flow Statement'),
            'subject' => $project->name,
            'company' => $project->company,
            'template' => $project->company->defaultTemplateFor('project_cash_flow'),
            'period' => ['from' => $from, 'to' => $to],
            'summary' => $this->ledger->summarize($ledger['rows']),
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
        [$from, $to] = $this->periodFromRequest($request);

        $ledger = $this->ledger->build(projectId: $projectId, bankAccountId: $bankAccount->id, from: $from, to: $to);

        return view('user.project-cash-flow.bank-account-show', [
            'bankAccount' => $bankAccount,
            'projects' => Project::orderBy('name')->get(),
            'selectedProjectId' => $projectId,
            'filters' => ['from' => $from, 'to' => $to],
            'ledger' => $ledger,
        ]);
    }

    public function bankAccountPdf(BankAccount $bankAccount, Request $request, MpdfRenderer $renderer)
    {
        $projectId = $request->integer('project_id') ?: null;
        [$from, $to] = $this->periodFromRequest($request);
        $ledger = $this->ledger->build(projectId: $projectId, bankAccountId: $bankAccount->id, from: $from, to: $to);

        $pdf = $renderer->render('documents.print.project-cash-flow-pdf', [
            'title' => __('Bank Account Statement'),
            'subject' => $bankAccount->name,
            'company' => $bankAccount->company,
            'template' => $bankAccount->company->defaultTemplateFor('project_cash_flow'),
            'period' => ['from' => $from, 'to' => $to],
            'summary' => null,
            'ledger' => $ledger,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($bankAccount->name).'-statement.pdf"',
        ]);
    }

    private function periodFromRequest(Request $request): array
    {
        return [
            $request->filled('from') ? $request->query('from') : null,
            $request->filled('to') ? $request->query('to') : null,
        ];
    }
}
