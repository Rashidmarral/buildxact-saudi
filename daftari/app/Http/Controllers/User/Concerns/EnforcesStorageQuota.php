<?php

namespace App\Http\Controllers\User\Concerns;

use App\Models\Company;
use App\Services\Limits\UsageLimitService;
use Illuminate\Http\RedirectResponse;

/**
 * Security audit finding M-11: storageUsedBytes() was tracked and shown
 * on the billing/usage pages, but nothing actually stopped a company from
 * uploading past its plan's storage cap — every attachment/document
 * upload endpoint accepted the file regardless. Same check-then-act
 * imprecision as every other plan limit in this app (usage is checked
 * before the new file is added, not after), which is an accepted,
 * pre-existing tradeoff (see audit finding M-04), not something this fix
 * introduces.
 */
trait EnforcesStorageQuota
{
    protected function rejectIfStorageQuotaReached(Company $company): ?RedirectResponse
    {
        $limitService = app(UsageLimitService::class);

        if ($limitService->reached($company, 'storage')) {
            return back()->withErrors(['file' => $limitService->friendlyMessage($company, 'storage')]);
        }

        return null;
    }
}
