<?php

namespace App\Http\Controllers\User\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Audit finding SEC-06: approval thresholds (manual journals, payment and
 * receipt vouchers, bills) routed a submission to a "pending_approval"
 * queue but never checked who was doing the approving — the person who
 * created the submission could open it right back up and approve their
 * own work, which defeats the entire point of a second-approver control.
 * A company with only one active user has no second person to require,
 * so that sole-owner case is exempted rather than locking them out of
 * their own books.
 */
trait EnforcesApprovalSeparation
{
    private function approvalBlockedBySelfApproval(?int $createdBy, int $companyId): bool
    {
        if ($createdBy === null || Auth::id() !== $createdBy) {
            return false;
        }

        return User::where('company_id', $companyId)->where('status', 'active')->count() > 1;
    }
}
