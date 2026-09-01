<?php

namespace App\Jobs;

use App\Models\DebitNote;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fired right when a debit note is issued for companies on "instant"
 * ZATCA sync — mirrors SyncCreditNoteToZatca. Everything else is picked
 * up in batch by the zatca:sync-invoices scheduled command.
 */
class SyncDebitNoteToZatca implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $debitNoteId) {}

    public function handle(ZatcaSyncService $sync): void
    {
        $debitNote = DebitNote::find($this->debitNoteId);

        if (! $debitNote || ! $debitNote->company?->isZatcaOnboarded()) {
            return;
        }

        $company = $debitNote->company;
        $isB2b = $sync->isB2bDebitNote($debitNote);

        if ($isB2b && ! $company->zatca_sync_b2b) {
            return;
        }

        if (! $isB2b && ! $company->zatca_sync_b2c) {
            return;
        }

        if ($debitNote->zatcaDebitNoteLogs()->whereIn('status', ['cleared', 'reported'])->exists()) {
            return;
        }

        $sync->submitDebitNote($debitNote);
    }
}
