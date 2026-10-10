<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Illuminate\Http\Request;

class BillApiController extends Controller
{
    public function index(Request $request)
    {
        $bills = Bill::with('supplier')
            ->orderByDesc('bill_date')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($bills->through(fn (Bill $bill) => $this->transform($bill)));
    }

    public function show(Bill $bill)
    {
        $bill->load(['supplier', 'items']);

        return response()->json($this->transform($bill, withLines: true));
    }

    private function transform(Bill $bill, bool $withLines = false): array
    {
        $data = [
            'id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'supplier_reference' => $bill->supplier_reference,
            'status' => $bill->status,
            'bill_date' => $bill->bill_date,
            'due_date' => $bill->due_date,
            'currency' => $bill->currency,
            'subtotal' => $bill->subtotal,
            'vat_total' => $bill->vat_total,
            'total' => $bill->total,
            'amount_paid' => $bill->amount_paid,
            'balance_due' => $bill->balanceDue(),
            'supplier' => $bill->supplier ? [
                'id' => $bill->supplier->id,
                'name' => $bill->supplier->name,
            ] : null,
        ];

        if ($withLines) {
            $data['items'] = $bill->items->map(fn ($line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_amount' => $line->vat_amount,
            ]);
        }

        return $data;
    }
}
