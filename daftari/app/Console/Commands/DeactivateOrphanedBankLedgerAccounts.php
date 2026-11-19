<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Admin\OrphanedBankLedgerAccountService;
use Illuminate\Console\Command;

/**
 * Console counterpart of the same "Orphaned bank ledger lines" cleanup
 * reachable from the super-admin company page (Admin\CompanyController)
 * — see OrphanedBankLedgerAccountService's own docblock for exactly what
 * this finds and why it's always safe. Kept for a host where artisan is
 * reachable; the admin UI is the one to use on shared hosting without
 * SSH/terminal access.
 */
class DeactivateOrphanedBankLedgerAccounts extends Command
{
    protected $signature = 'accounts:deactivate-orphaned-bank-lines
        {--company= : Only this company\'s id — omit to scan every company}
        {--dry-run : List what would be deactivated without changing anything}';

    protected $description = 'Deactivate chart-of-accounts ledger lines left behind by a deleted bank account';

    public function handle(OrphanedBankLedgerAccountService $service): int
    {
        $companies = $this->option('company')
            ? Company::where('id', (int) $this->option('company'))->get()
            : Company::all();

        if ($companies->isEmpty()) {
            $this->error('No matching company found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($companies as $company) {
            $orphans = $service->preview($company);

            foreach ($orphans as $account) {
                $this->line("[{$company->name}] {$account->code} {$account->name}".($dryRun ? ' (would deactivate)' : ' — deactivated'));
            }

            $total += $dryRun ? $orphans->count() : $service->execute($company);
        }

        $this->info($dryRun
            ? "Found {$total} orphaned bank/cash ledger line(s). Re-run without --dry-run to deactivate them."
            : "Deactivated {$total} orphaned bank/cash ledger line(s).");

        return self::SUCCESS;
    }
}
