<?php

namespace App\Services\Payments;

use App\Mail\PaymentReceiptMail;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\Webhook;
use App\Notifications\GenericNotification;
use App\Services\Accounting\LedgerPostingService;
use App\Services\MpdfRenderer;
use Illuminate\Support\Facades\Mail;

/**
 * Applies the business-level effect of a confirmed online payment — this
 * is only ever called after a webhook driver has verified the payment is
 * genuinely paid, never on unverified input.
 */
class PaymentSettlementService
{
    public function settle(PaymentTransaction $transaction): void
    {
        if ($transaction->payable_type === Subscription::class) {
            $this->settleSubscription($transaction);
        } elseif ($transaction->payable_type === Invoice::class) {
            $this->settleInvoice($transaction);
        }
    }

    private function settleSubscription(PaymentTransaction $transaction): void
    {
        $subscription = Subscription::withoutGlobalScopes()->find($transaction->payable_id);

        if (! $subscription || $subscription->status === 'active') {
            return;
        }

        $subscription->update(['status' => 'active']);

        $company = $subscription->company;

        // The checkout path (BillingController::upgrade()) creates this
        // Payment up front as 'pending' so a failed/abandoned attempt still
        // has a record — settle it in place. Fall back to creating one for
        // any transaction that predates that (defensive, not expected in
        // practice).
        $payment = Payment::withoutGlobalScopes()->where('payment_transaction_id', $transaction->id)->first();

        if ($payment) {
            $payment->update([
                'status' => 'paid',
                'reference' => $transaction->provider_reference,
                'paid_at' => now(),
            ]);
        } else {
            $payment = $company->payments()->create([
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'status' => 'paid',
                'method' => $transaction->provider,
                'reference' => $transaction->provider_reference,
                'payment_transaction_id' => $transaction->id,
                'paid_at' => now(),
            ]);
        }

        $this->sendSubscriptionReceipt($payment);
    }

    /**
     * Renders the SaaS receipt PDF and emails/notifies every company owner
     * — shared by the online-gateway settlement path above, the instant
     * "manual" stub in BillingController::upgrade(), and the admin action
     * that confirms an offline bank-transfer payment, so all three ways a
     * subscription payment can become "paid" produce the same receipt.
     */
    public function sendSubscriptionReceipt(Payment $payment): void
    {
        $payment->loadMissing('plan', 'company');
        $company = $payment->company;

        $renderer = app(MpdfRenderer::class);
        $pdf = $renderer->render('documents.print.saas-receipt', ['payment' => $payment, 'company' => $company]);

        foreach ($company->owners as $owner) {
            Mail::to($owner->email)->send(new PaymentReceiptMail($payment, $pdf));
            $owner->notify(new GenericNotification(
                title: __('Payment received'),
                body: __(':amount :currency for the :plan plan', [
                    'amount' => number_format($payment->amount, 2),
                    'currency' => $payment->currency,
                    'plan' => $payment->plan->name,
                ]),
                url: route('app.billing.index'),
                icon: 'billing',
            ));
        }
    }

    private function settleInvoice(PaymentTransaction $transaction): void
    {
        $invoice = Invoice::withoutGlobalScopes()->find($transaction->payable_id);

        if (! $invoice) {
            return;
        }

        $wasPaid = $invoice->status === 'paid';

        $payment = $invoice->invoicePayments()->create([
            'amount' => $transaction->amount,
            'paid_at' => now(),
            'method' => 'online_'.$transaction->provider,
            'reference' => $transaction->provider_reference,
        ]);

        $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
        $invoice->status = $invoice->isFullyPaid() ? 'paid' : 'partially_paid';
        $invoice->save();

        app(LedgerPostingService::class)->postInvoicePayment($payment);

        if ($invoice->status === 'paid' && ! $wasPaid) {
            Webhook::trigger($invoice->company_id, 'invoice.paid', [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'client_id' => $invoice->client_id,
                'total' => $invoice->total,
                'currency' => $invoice->currency,
            ]);
        }
    }
}
