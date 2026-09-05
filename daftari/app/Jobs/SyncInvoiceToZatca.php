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

    public function __construct(public readonly int $invoiceId) {}

    public function handle(ZatcaSyncService $sync): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice || ! $invoice->company?->isZatcaOnboarded()) {
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
