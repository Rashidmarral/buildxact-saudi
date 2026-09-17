<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceApiController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with('client')
            ->orderByDesc('issue_date')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($invoices->through(fn (Invoice $invoice) => $this->transform($invoice)));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['client', 'items']);

        return response()->json($this->transform($invoice, withLines: true));
    }

    private function transform(Invoice $invoice, bool $withLines = false): array
    {
        $data = [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date,
            'due_date' => $invoice->due_date,
            'currency' => $invoice->currency,
            'subtotal' => $invoice->subtotal,
            'vat_total' => $invoice->vat_total,
            'total' => $invoice->total,
            'amount_paid' => $invoice->amount_paid,
            'balance_due' => $invoice->balanceDue(),
            'client' => $invoice->client ? [
                'id' => $invoice->client->id,
                'name' => $invoice->client->name,
                'client_code' => $invoice->client->client_code,
            ] : null,
        ];

        if ($withLines) {
            $data['items'] = $invoice->items->map(fn ($line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_amount' => $line->vat_amount,
            ]);
        }

        return $data;
    }
}
