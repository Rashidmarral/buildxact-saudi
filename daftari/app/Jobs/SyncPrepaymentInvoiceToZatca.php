<?php

namespace App\Jobs;

use App\Models\PrepaymentInvoice;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Mirrors SyncInvoiceToZatca exactly for a prepayment (386) invoice —
 * fired right when one is issued for companies on "instant" ZATCA sync;
 * everything else is picked up in batch by zatca:sync-invoices.
 */
class SyncPrepaymentInvoiceToZatca implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $prepaymentInvoiceId) {}

    public function handle(ZatcaSyncService $sync): void
    {
        $prepayment = PrepaymentInvoice::find($this->prepaymentInvoiceId);

        if (! $prepayment || ! $prepayment->company?->isOperational() || ! $prepayment->company?->isZatcaOnboarded()) {
            return;
        }

        $company = $prepayment->company;

        if ($prepayment->type === 'standard' && ! $company->zatca_sync_b2b) {
            return;
        }

        if ($prepayment->type === 'simplified' && ! $company->zatca_sync_b2c) {
            return;
        }

        if ($prepayment->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->exists()) {
            return;
        }

        $sync->submitPrepaymentInvoice($prepayment);
    }
}
