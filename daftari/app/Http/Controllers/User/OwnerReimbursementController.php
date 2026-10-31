<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Expense;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "how can i add or track company owner expenses" — money the owner (or
 * any staff member) paid for the company personally, out of their own
 * pocket, that the company owes back. This reuses the existing accrued-
 * expense mechanism exactly (Expense with no bank_account_id posts to
 * Accounts Payable, settled later by a Payment Voucher referencing it —
 * see LedgerPostingService::postExpense()/postPaymentVoucher()); the only
 * new thing is the paid_by_owner flag, so these are reportable on their
 * own instead of mixed in with ordinary unpaid supplier bills. Settling
 * one is just recording a normal Payment Voucher against it — no new
 * posting logic needed here.
 */
class OwnerReimbursementController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $expenses = Expense::with('category', 'paymentVouchers')
            ->where('paid_by_owner', true)
            ->orderByDesc('expense_date');

        $all = $expenses->get();
        $outstanding = $all->reject(fn (Expense $e) => $e->isSettled());

        return view('user.owner-reimbursements.index', [
            'expenses' => (clone $expenses)->paginate($this->resolvePerPage($request))->withQueryString(),
            'totalOutstanding' => $outstanding->sum(fn (Expense $e) => $e->gross_amount ?? ($e->amount + $e->vat_amount)),
            'outstandingCount' => $outstanding->count(),
        ]);
    }

    public function pdf(MpdfRenderer $renderer)
    {
        $expenses = Expense::with('category', 'paymentVouchers')
            ->where('paid_by_owner', true)
            ->orderByDesc('expense_date')
            ->get();

        $outstanding = $expenses->reject(fn (Expense $e) => $e->isSettled());

        $pdf = $renderer->render('user.owner-reimbursements.pdf', [
            'company' => Auth::user()->company,
            'expenses' => $expenses,
            'totalOutstanding' => $outstanding->sum(fn (Expense $e) => $e->gross_amount ?? ($e->amount + $e->vat_amount)),
            'template' => Auth::user()->company->defaultTemplateFor('owner_reimbursements_report'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="owner-reimbursements.pdf"',
        ]);
    }
}
