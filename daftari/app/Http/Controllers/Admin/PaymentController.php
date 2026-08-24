<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Subscription;
use App\Services\Payments\PaymentSettlementService;

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

    /**
     * Activates a subscription that was paid via offline bank transfer,
     * once an admin has actually checked Daftari's bank statement and seen
     * the money arrive — there's no automated confirmation for this method
     * by definition, so a human has to do it here.
     */
    public function confirmBankTransfer(int $payment, PaymentSettlementService $settlement)
    {
        $payment = Payment::withoutGlobalScopes()->findOrFail($payment);

        abort_unless($payment->method === PaymentGateway::BANK_TRANSFER, 404);

        if ($payment->status !== 'pending') {
            return back()->withErrors(['payment' => __('This payment is not awaiting confirmation.')]);
        }

        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        $subscription = Subscription::withoutGlobalScopes()->find($payment->subscription_id);
        $subscription?->update(['status' => 'active']);

        $settlement->sendSubscriptionReceipt($payment);

        AuditLog::record('payment.confirm_bank_transfer', $payment, __('Confirmed bank-transfer payment #:id (:amount :currency)', [
            'id' => $payment->id, 'amount' => number_format($payment->amount, 2), 'currency' => $payment->currency,
        ]));

        return back()->with('status', __('Payment confirmed — subscription activated.'));
    }
}
