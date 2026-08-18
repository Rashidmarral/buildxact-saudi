<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::withoutGlobalScopes()
            ->with('plan')
            ->join('companies', 'companies.id', '=', 'payments.company_id')
            ->select('payments.*', 'companies.name as company_name')
            ->latest('payments.paid_at')
            ->latest('payments.id')
            ->paginate(25);

        return view('admin.payments.index', compact('payments'));
    }

    /**
     * Marks a payment refunded in Daftari's own records. There is no live
     * payment gateway wired up yet (see BillingController::upgrade()) — so
     * this is bookkeeping, not a real money-back transaction. It exists so
     * support can accurately reflect what actually happened outside the
     * system (a manual bank transfer, a gateway refund done elsewhere)
     * rather than leaving the record permanently marked "paid".
     */
    public function refund(int $payment)
    {
        // Payment uses BelongsToCompany, whose global scope filters by the
        // authenticated user's company — an admin has no company_id, so
        // implicit route-model-binding on Payment would 404 every request
        // before this method even ran. Bind by raw id and query around the
        // scope explicitly instead.
        $payment = Payment::withoutGlobalScopes()->findOrFail($payment);

        if ($payment->status === 'refunded') {
            return back()->withErrors(['payment' => __('This payment is already marked refunded.')]);
        }

        $payment->update(['status' => 'refunded']);
        AuditLog::record('payment.refund', $payment, __('Marked payment #:id (:amount :currency) refunded', [
            'id' => $payment->id, 'amount' => number_format($payment->amount, 2), 'currency' => $payment->currency,
        ]));

        return back()->with('status', __('Payment marked as refunded.'));
    }
}
