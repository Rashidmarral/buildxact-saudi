<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Console\Command;

/**
 * Batch-syncs pending invoices to ZATCA for every onboarded company whose
 * chosen sync frequency matches the given --frequency option. "instant"
 * syncing happens separately (dispatched right when an invoice is sent,
 * see SyncInvoiceToZatca job) — this command only drives the batched
 * hourly/daily/weekly cadences. "manual" companies are never picked up
 * here; they only sync via the dashboard's "Sync now" button.
 */
class ZatcaSyncInvoices extends Command
{
    protected $signature = 'zatca:sync-invoices {--frequency=hourly : hourly, daily, or weekly}';

    protected $description = 'Sync pending invoices to ZATCA for companies on a matching scheduled sync frequency';

    public function handle(ZatcaSyncService $sync): int
    {
        $frequency = $this->option('frequency');

        $companies = Company::where('zatca_onboarding_status', 'onboarded')
            ->where('zatca_sync_frequency', $frequency)
            ->get();

        if ($companies->isEmpty()) {
            $this->info("No onboarded companies on the '{$frequency}' sync frequency.");

            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $invoices = $sync->pendingInvoices($company);

            if ($invoices->isEmpty()) {
                continue;
            }

            $cleared = 0;
            $failed = 0;

            foreach ($invoices as $invoice) {
                $log = $sync->submit($invoice);
                in_array($log->status, ['cleared', 'reported'], true) ? $cleared++ : $failed++;
            }

            $this->info("Company #{$company->id} ({$company->name}): {$cleared} synced, {$failed} failed.");
        }

        return self::SUCCESS;
    }
}
