<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fired right when an invoice is sent for companies on "instant" ZATCA
 * sync — everything else (hourly/daily/weekly) is picked up in batch by
 * the zatca:sync-invoices scheduled command instead.
 */
class SyncInvoiceToZatca implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $invoiceId) {}

    public function handle(ZatcaSyncService $sync): void
    {
        $invoice = Invoice::find($this->invoiceId);

        // Company::isOperational() is checked here (not folded into
        // isZatcaOnboarded() itself) because that method is also used by
        // admin-initiated retry/sync actions, which a Super Admin must
        // still be able to use for a suspended company — e.g. to
        // diagnose or fix its ZATCA state while reactivating it. This
        // job only fires automatically, so it's the one that must not
        // keep submitting real invoices on a suspended company's behalf
        // (security audit finding D-0).
        if (! $invoice || ! $invoice->company?->isOperational() || ! $invoice->company?->isZatcaOnboarded()) {
            return;
        }

        $company = $invoice->company;

        if ($invoice->type === 'standard' && ! $company->zatca_sync_b2b) {
            return;
        }

        if ($invoice->type === 'simplified' && ! $company->zatca_sync_b2c) {
            return;
        }

        if ($invoice->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->exists()) {
            return;
        }

        $sync->submit($invoice);
    }
}
