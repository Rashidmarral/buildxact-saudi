<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ApprovalChainStep;
use App\Models\AuditLog;
use App\Models\PeriodClosure;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Company-level approval thresholds — a purchase order or expense at or
 * above the configured amount can no longer be approved/posted by whoever
 * created it; it needs a user holding the separate "approvals" permission
 * to sign off first. Null/0 keeps today's behavior (no gate at all).
 *
 * This page also configures the optional multi-tier approval chains that
 * sit on top of these flat thresholds — see ApprovalChainController and
 * ApprovalChainService.
 */
class ApprovalSettingsController extends Controller
{
    public function show()
    {
        return view('user.settings.approvals', [
            'company' => Auth::user()->company,
            'chainSteps' => ApprovalChainStep::query()->with('role')->orderBy('document_type')->orderBy('step_number')->get()->groupBy('document_type'),
            'roles' => Role::orderBy('name')->get(),
            'periodClosures' => PeriodClosure::with('performer')->orderByDesc('id')->take(20)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'po_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'expense_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'invoice_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'quotation_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            // Audit findings GL-6/CASH-06.
            'manual_journal_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'payment_voucher_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'receipt_voucher_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'bill_approval_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        Auth::user()->company->update([
            'po_approval_threshold' => $data['po_approval_threshold'] ?: null,
            'expense_approval_threshold' => $data['expense_approval_threshold'] ?: null,
            'invoice_approval_threshold' => $data['invoice_approval_threshold'] ?: null,
            'quotation_approval_threshold' => $data['quotation_approval_threshold'] ?: null,
            'manual_journal_approval_threshold' => $data['manual_journal_approval_threshold'] ?: null,
            'payment_voucher_approval_threshold' => $data['payment_voucher_approval_threshold'] ?: null,
            'receipt_voucher_approval_threshold' => $data['receipt_voucher_approval_threshold'] ?: null,
            'bill_approval_threshold' => $data['bill_approval_threshold'] ?: null,
        ]);

        AuditLog::record('company.approval_thresholds_update', null, __('Updated approval thresholds'));

        return back()->with('status', __('Approval settings saved.'));
    }

    /**
     * Audit finding MEDIUM-13: lets a company opt out of the automated
     * overdue-invoice dunning ladder entirely (e.g. if they handle
     * collections manually or through a different channel).
     */
    public function updateDunning(Request $request)
    {
        $company = Auth::user()->company;

        $company->update(['invoice_dunning_enabled' => $request->boolean('invoice_dunning_enabled')]);

        AuditLog::record(
            'company.invoice_dunning_update',
            null,
            $company->invoice_dunning_enabled
                ? __('Enabled automated overdue-invoice reminders')
                : __('Disabled automated overdue-invoice reminders')
        );

        return back()->with('status', __('Reminder settings saved.'));
    }

    /**
     * Audit finding GL-4: moving the lock date FORWARD only — closing
     * further, never reopening — recorded as a structured PeriodClosure
     * with a named approver, alongside LedgerPostingService's own
     * enforcement (unchanged). A forward-only guard here means this
     * action can never be the one that reopens a period; that's
     * reopenPeriod()'s job alone, with its own required reason.
     */
    public function closePeriod(Request $request)
    {
        $data = $request->validate([
            'accounting_lock_date' => ['required', 'date'],
        ]);

        $company = Auth::user()->company;
        $new = $data['accounting_lock_date'];

        if ($company->accounting_lock_date && \Illuminate\Support\Carbon::parse($new)->lte($company->accounting_lock_date)) {
            return back()->withErrors(['accounting_lock_date' => __('The new lock date must be after the current one — use "Reopen" below to move it backward.')]);
        }

        $old = $company->accounting_lock_date?->toDateString();
        $company->update(['accounting_lock_date' => $new]);

        PeriodClosure::create(['company_id' => $company->id, 'action' => 'close', 'lock_date' => $new, 'performed_by' => Auth::id()]);

        AuditLog::record(
            'company.lock_date_update',
            null,
            __('Locked the books through :date', ['date' => $new]),
            old: ['accounting_lock_date' => $old],
            new: ['accounting_lock_date' => $new],
        );

        return back()->with('status', __('Period closed through :date.', ['date' => $new]));
    }

    /**
     * The only path that can move the lock date backward or clear it —
     * always requires a reason, which the close action above does not.
     */
    public function reopenPeriod(Request $request)
    {
        $data = $request->validate([
            'accounting_lock_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $company = Auth::user()->company;
        $old = $company->accounting_lock_date?->toDateString();
        $new = $data['accounting_lock_date'] ?: null;

        if ($new !== null && $company->accounting_lock_date && \Illuminate\Support\Carbon::parse($new)->gt($company->accounting_lock_date)) {
            return back()->withErrors(['accounting_lock_date' => __('That date is not earlier than the current lock date — use "Close" above to move it forward.')]);
        }

        $company->update(['accounting_lock_date' => $new]);

        PeriodClosure::create(['company_id' => $company->id, 'action' => 'reopen', 'lock_date' => $new, 'performed_by' => Auth::id(), 'reason' => $data['reason']]);

        AuditLog::record(
            'company.lock_date_update',
            null,
            $new
                ? __('Reopened the books back to :date — :reason', ['date' => $new, 'reason' => $data['reason']])
                : __('Reopened the books entirely — :reason', ['reason' => $data['reason']]),
            old: ['accounting_lock_date' => $old],
            new: ['accounting_lock_date' => $new],
        );

        return back()->with('status', __('Period reopened.'));
    }
}
