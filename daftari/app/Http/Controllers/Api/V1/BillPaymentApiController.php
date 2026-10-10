<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillPayment;
use Illuminate\Http\Request;

class BillPaymentApiController extends Controller
{
    public function index(Bill $bill, Request $request)
    {
        $payments = $bill->billPayments()
            ->orderByDesc('paid_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($payments->through(fn (BillPayment $payment) => $this->transform($payment)));
    }

    private function transform(BillPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'bill_id' => $payment->bill_id,
            'amount' => $payment->amount,
            'exchange_rate' => $payment->exchange_rate,
            'paid_at' => $payment->paid_at,
            'method' => $payment->method,
            'reference' => $payment->reference,
        ];
    }
}
