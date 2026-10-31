<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "companies can issue delivery note for the customers" — proof of
 * delivery for goods shipped to a client, standalone or generated from an
 * existing invoice. The only real business rule lives here: a delivery
 * note generated from an invoice can never ship more of a line than that
 * invoice actually billed, checked under a row lock so two people
 * fulfilling the same invoice at once can't together over-ship it —
 * same discipline as LeaveRequestService::approve()'s balance re-check.
 */
class DeliveryNoteService
{
    /**
     * @param  array{client_id?: int, invoice_id?: null, issue_date: string, driver_name?: ?string, vehicle_number?: ?string, received_by_name?: ?string, notes?: ?string}  $data
     * @param  array<int, array{item_id?: ?int, unit_id?: ?int, description: string, quantity: float}>  $lines
     */
    public function createStandalone(Company $company, array $data, array $lines): DeliveryNote
    {
        if (empty($lines)) {
            throw new RuntimeException(__('A delivery note needs at least one line.'));
        }

        return DB::transaction(function () use ($company, $data, $lines) {
            $note = DeliveryNote::create([
                'company_id' => $company->id,
                'client_id' => $data['client_id'],
                'branch_id' => $data['branch_id'] ?? $company->default_branch_id,
                'created_by' => Auth::id(),
                'delivery_note_number' => $company->nextDeliveryNoteNumber(),
                'status' => 'issued',
                'issue_date' => $data['issue_date'],
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'received_by_name' => $data['received_by_name'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $sort => $line) {
                $note->items()->create([
                    'item_id' => $line['item_id'] ?? null,
                    'unit_id' => $line['unit_id'] ?? null,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'sort_order' => $sort,
                ]);
            }

            return $note;
        });
    }

    /**
     * @param  array{issue_date: string, driver_name?: ?string, vehicle_number?: ?string, received_by_name?: ?string, notes?: ?string}  $data
     * @param  array<int, array{invoice_item_id: int, quantity: float}>  $lines
     */
    public function createFromInvoice(Invoice $invoice, array $data, array $lines): DeliveryNote
    {
        $lines = array_values(array_filter($lines, fn ($line) => (float) $line['quantity'] > 0));

        if (empty($lines)) {
            throw new RuntimeException(__('Enter a quantity to deliver on at least one line.'));
        }

        return DB::transaction(function () use ($invoice, $data, $lines) {
            $company = $invoice->company;

            $note = DeliveryNote::create([
                'company_id' => $company->id,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'branch_id' => $invoice->branch_id ?? $company->default_branch_id,
                'created_by' => Auth::id(),
                'delivery_note_number' => $company->nextDeliveryNoteNumber(),
                'status' => 'issued',
                'issue_date' => $data['issue_date'],
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'received_by_name' => $data['received_by_name'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $sort => $line) {
                // Locked so a second delivery note submitted for the same
                // invoice in the same instant can't also read the
                // pre-this-note remaining quantity and together ship more
                // than the invoice line actually billed.
                $invoiceItem = InvoiceItem::whereKey($line['invoice_item_id'])
                    ->where('invoice_id', $invoice->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $remaining = $invoiceItem->remainingQuantity();

                if ((float) $line['quantity'] > $remaining + 0.004) {
                    throw new RuntimeException(__('Only :remaining of ":description" is left to deliver on this invoice.', [
                        'remaining' => rtrim(rtrim(number_format($remaining, 2), '0'), '.'),
                        'description' => $invoiceItem->description,
                    ]));
                }

                $note->items()->create([
                    'invoice_item_id' => $invoiceItem->id,
                    'item_id' => $invoiceItem->item_id,
                    'unit_id' => $invoiceItem->unit_id,
                    'description' => $invoiceItem->description,
                    'quantity' => $line['quantity'],
                    'sort_order' => $sort,
                ]);
            }

            return $note;
        });
    }

    public function cancel(DeliveryNote $note, ?string $reason): void
    {
        if (! $note->isIssued()) {
            throw new RuntimeException(__('This delivery note has already been cancelled.'));
        }

        $note->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);
    }
}
