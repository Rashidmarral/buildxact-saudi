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
        $companies = Company::withoutGlobalScopes()->get();
        $totalPosted = 0;

        foreach ($companies as $company) {
            $totalPosted += $service->postForCompany($company);
        }

        $this->info("Posted depreciation for {$totalPosted} asset(s) across {$companies->count()} companies.");

        return self::SUCCESS;
    }
}
