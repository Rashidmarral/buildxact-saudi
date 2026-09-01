<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;

class InvoicePaymentApiController extends Controller
{
    public function index(Invoice $invoice, Request $request)
    {
        $payments = $invoice->invoicePayments()
            ->orderByDesc('paid_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($payments->through(fn (InvoicePayment $payment) => $this->transform($payment)));
    }

    private function transform(InvoicePayment $payment): array
    {
        return [
            'id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'amount' => $payment->amount,
            'exchange_rate' => $payment->exchange_rate,
            'paid_at' => $payment->paid_at,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
        ];
    }
}
