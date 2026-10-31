<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Accounting\AssetDepreciationService;
use Illuminate\Console\Command;

class RunAssetDepreciation extends Command
{
    protected $signature = 'assets:run-depreciation';

    protected $description = 'Post this month\'s straight-line depreciation for every active fixed asset, for every company';

    public function handle(AssetDepreciationService $service): int
    {
        // Skip a suspended or subscription-lapsed company entirely
        // (security audit finding D-0) — its users can't log in to review
        // these, so nothing should keep posting real depreciation entries
        // against its books while it's in that state.
        $companies = Company::withoutGlobalScopes()->get()->filter(fn (Company $company) => $company->isOperational());
        $totalPosted = 0;

        foreach ($companies as $company) {
            $totalPosted += $service->postForCompany($company);
        }

        $this->info("Posted depreciation for {$totalPosted} asset(s) across {$companies->count()} companies.");

        return self::SUCCESS;
    }
}
