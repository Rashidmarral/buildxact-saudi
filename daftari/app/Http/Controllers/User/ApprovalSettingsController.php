<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Company-level approval thresholds — a purchase order or expense at or
 * above the configured amount can no longer be approved/posted by whoever
 * created it; it needs a user holding the separate "approvals" permission
 * to sign off first. Null/0 keeps today's behavior (no gate at all).
 */
class ApprovalSettingsController extends Controller
{
    public function show()
    {
        return view('user.settings.approvals', ['company' => Auth::user()->company]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'po_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'expense_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'invoice_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'quotation_approval_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        Auth::user()->company->update([
            'po_approval_threshold' => $data['po_approval_threshold'] ?: null,
            'expense_approval_threshold' => $data['expense_approval_threshold'] ?: null,
            'invoice_approval_threshold' => $data['invoice_approval_threshold'] ?: null,
            'quotation_approval_threshold' => $data['quotation_approval_threshold'] ?: null,
        ]);

        AuditLog::record('company.approval_thresholds_update', null, __('Updated approval thresholds'));

        return back()->with('status', __('Approval settings saved.'));
    }

    /**
     * Nothing posts a journal entry dated on or before this date once it's
     * set — LedgerPostingService::post() enforces it for every module.
     * Gated behind the same "settings" permission as the rest of this
     * page, since moving the date backward (or clearing it) reopens a
     * period that was presumably closed for a reason.
     */
    public function updateLockDate(Request $request)
    {
        $data = $request->validate([
            'accounting_lock_date' => ['nullable', 'date'],
        ]);

        $company = Auth::user()->company;
        $old = $company->accounting_lock_date?->toDateString();
        $new = $data['accounting_lock_date'] ?: null;

        $company->update(['accounting_lock_date' => $new]);

        AuditLog::record(
            'company.lock_date_update',
            null,
            $new
                ? __('Locked the books through :date', ['date' => $new])
                : __('Removed the accounting lock date'),
            old: ['accounting_lock_date' => $old],
            new: ['accounting_lock_date' => $new],
        );

        return back()->with('status', __('Lock date saved.'));
    }
}
