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
        ]);

        Auth::user()->company->update([
            'po_approval_threshold' => $data['po_approval_threshold'] ?: null,
            'expense_approval_threshold' => $data['expense_approval_threshold'] ?: null,
        ]);

        AuditLog::record('company.approval_thresholds_update', null, __('Updated approval thresholds'));

        return back()->with('status', __('Approval settings saved.'));
    }
}
