<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\TaxRate;
use Illuminate\Console\Command;

/**
 * One-time backfill for companies created before tax rate management
 * existed — TaxRate::seedDefaults() is idempotent (firstOrCreate keyed on
 * name), so re-running this is always safe.
 */
class BackfillTaxRates extends Command
{
    protected $signature = 'tax-rates:backfill';

    protected $description = 'Seed the default VAT 15% / VAT 0% / Exempt tax rates for any company that does not have tax rates yet';

    public function handle(): int
    {
        $seeded = 0;

        foreach (Company::withoutGlobalScopes()->get() as $company) {
            if (TaxRate::where('company_id', $company->id)->exists()) {
                continue;
            }

            TaxRate::seedDefaults($company->id);
            $seeded++;
        }

        $this->info("Seeded default tax rates for {$seeded} company(ies).");

        return self::SUCCESS;
    }
}
