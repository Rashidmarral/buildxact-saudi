<?php

namespace App\Jobs;

use App\Models\CreditNote;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fired right when a credit note is issued for companies on "instant"
 * ZATCA sync — mirrors SyncInvoiceToZatca. Everything else is picked up
 * in batch by the zatca:sync-invoices scheduled command.
 */
class SyncCreditNoteToZatca implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $creditNoteId) {}

    public function handle(ZatcaSyncService $sync): void
    {
        $creditNote = CreditNote::find($this->creditNoteId);

        if (! $creditNote || ! $creditNote->company?->isZatcaOnboarded()) {
            return;
        }

        $company = $creditNote->company;
        $isB2b = $sync->isB2bCreditNote($creditNote);

        if ($isB2b && ! $company->zatca_sync_b2b) {
            return;
        }

        if (! $isB2b && ! $company->zatca_sync_b2c) {
            return;
        }

        if ($creditNote->zatcaCreditNoteLogs()->whereIn('status', ['cleared', 'reported'])->exists()) {
            return;
        }

        $sync->submitCreditNote($creditNote);
    }
}
