<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Console\Command;

/**
 * Batch-syncs pending invoices, credit notes, and debit notes to ZATCA for
 * every onboarded company whose chosen sync frequency matches the given
 * --frequency option. "instant" syncing happens separately (dispatched
 * right when a document is issued, see SyncInvoiceToZatca /
 * SyncCreditNoteToZatca / SyncDebitNoteToZatca jobs) — this command only
 * drives the batched hourly/daily/weekly cadences. "manual" companies are
 * never picked up here; they only sync via the dashboard's "Sync now"
 * button.
 */
class ZatcaSyncInvoices extends Command
{
    protected $signature = 'zatca:sync-invoices {--frequency=hourly : hourly, daily, or weekly}';

    protected $description = 'Sync pending invoices, credit notes, and debit notes to ZATCA for companies on a matching scheduled sync frequency';

    public function handle(ZatcaSyncService $sync): int
    {
        $frequency = $this->option('frequency');

        $companies = Company::where('zatca_onboarding_status', 'onboarded')
            ->where('zatca_sync_frequency', $frequency)
            ->get()
            // A company that downgraded off the Phase 2 feature stays
            // marked 'onboarded' (its ZATCA credentials are still valid),
            // but isZatcaOnboarded() now returns false for it — skip here
            // rather than loop it into repeated "failed" log entries.
            ->filter(fn (Company $company) => $company->isZatcaOnboarded());

        if ($companies->isEmpty()) {
            $this->info("No onboarded companies on the '{$frequency}' sync frequency.");

            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $invoices = $sync->pendingInvoices($company);
            $creditNotes = $sync->pendingCreditNotes($company);
            $debitNotes = $sync->pendingDebitNotes($company);

            if ($invoices->isEmpty() && $creditNotes->isEmpty() && $debitNotes->isEmpty()) {
                continue;
            }

            $cleared = 0;
            $failed = 0;

            foreach ($invoices as $invoice) {
                $log = $sync->submit($invoice);
                in_array($log->status, ['cleared', 'reported'], true) ? $cleared++ : $failed++;
            }

            foreach ($creditNotes as $creditNote) {
                $log = $sync->submitCreditNote($creditNote);
                in_array($log->status, ['cleared', 'reported'], true) ? $cleared++ : $failed++;
            }

            foreach ($debitNotes as $debitNote) {
                $log = $sync->submitDebitNote($debitNote);
                in_array($log->status, ['cleared', 'reported'], true) ? $cleared++ : $failed++;
            }

            $this->info("Company #{$company->id} ({$company->name}): {$cleared} synced, {$failed} failed.");
        }

        return self::SUCCESS;
    }
}
