<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MpdfRenderer;
use App\Services\Payments\PaymentCheckoutService;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::withoutGlobalScopes()
            ->with('plan')
            ->join('companies', 'companies.id', '=', 'payments.company_id')
            ->select('payments.*', 'companies.name as company_name');

        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where('companies.name', 'like', "%{$term}%");
        }

        if ($request->filled('status')) {
            $query->where('payments.status', $request->status);
        }

        if ($request->filled('gateway')) {
            $query->where('payments.method', $request->gateway);
        }

        if ($request->filled('plan_id')) {
            $query->where('payments.plan_id', $request->plan_id);
        }

        if ($request->filled('amount_min')) {
            $query->where('payments.amount', '>=', (float) $request->amount_min);
        }
        if ($request->filled('amount_max')) {
            $query->where('payments.amount', '<=', (float) $request->amount_max);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payments.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('payments.created_at', '<=', $request->date_to);
        }

        $payments = $query->latest('payments.created_at')->latest('payments.id')->paginate(25)->withQueryString();

        $pageCompanyIds = $payments->pluck('company_id')->unique();
        $owners = User::withoutGlobalScopes()
            ->whereIn('company_id', $pageCompanyIds)
            ->where('role', 'owner')
            ->orderBy('id')
            ->get(['id', 'company_id', 'name', 'email'])
            ->groupBy('company_id')
            ->map(fn ($group) => $group->first());

        $gateways = array_merge(PaymentGateway::PROVIDERS, [PaymentGateway::BANK_TRANSFER, 'manual']);
        $plans = Plan::orderBy('sort_order')->get(['id', 'name']);

        return view('admin.payments.index', compact('payments', 'owners', 'gateways', 'plans'));
    }

    public function show(int $payment)
    {
        // Payment uses BelongsToCompany, whose global scope filters by the
        // authenticated user's company — an admin has no company_id, so
        // implicit route-model-binding would 404 every request before this
        // method even ran. Bind by raw id and query around the scope
        // explicitly instead (same pattern as every other method here).
        $payment = Payment::withoutGlobalScopes()->with(['company', 'subscription.plan', 'plan', 'transaction'])->findOrFail($payment);

        $owner = User::withoutGlobalScopes()->where('company_id', $payment->company_id)->where('role', 'owner')->first();

        $webhookEvents = $payment->payment_transaction_id
            ? PaymentGatewayWebhookEvent::where('payment_transaction_id', $payment->payment_transaction_id)->orderBy('created_at')->get()
            : collect();

        $auditLogs = AuditLog::with('admin')
            ->where('subject_type', Payment::class)
            ->where('subject_id', $payment->id)
            ->latest('created_at')
            ->get();

        $timeline = $this->buildTimeline($payment, $webhookEvents, $auditLogs);

        return view('admin.payments.show', compact('payment', 'owner', 'webhookEvents', 'auditLogs', 'timeline'));
    }

    /**
     * Renders and streams the same SaaS receipt PDF the company itself can
     * download from Billing — the closest existing analog to an "invoice"
     * for a subscription payment, reused rather than building a second
     * invoicing system. Admin-only: BillingController::downloadReceipt()
     * enforces the payment belongs to the logged-in company, which an
     * admin (no company_id) can never satisfy.
     */
    public function receipt(int $payment, MpdfRenderer $renderer)
    {
        $payment = Payment::withoutGlobalScopes()->with(['plan', 'company'])->findOrFail($payment);
        abort_unless($payment->status === 'paid', 404);

        $pdf = $renderer->render('documents.print.saas-receipt', [
            'payment' => $payment,
            'company' => $payment->company,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="receipt-'.$payment->id.'.pdf"',
        ]);
    }

    /**
     * Marks a payment refunded (fully, or partially when $amount is given)
     * in Daftari's own records. There is no live payment gateway wired up
     * yet by default — so absent a real gateway transaction this is
     * bookkeeping, not a real money-back transaction. It exists so support
     * can accurately reflect what actually happened outside the system (a
     * manual bank transfer, a gateway refund done elsewhere) rather than
     * leaving the record permanently marked "paid".
     */
    public function refund(Request $request, int $payment)
    {
        $payment = Payment::withoutGlobalScopes()->with('company')->findOrFail($payment);

        abort_unless(in_array($payment->status, ['paid', 'partially_refunded'], true), 404);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $remaining = $payment->remainingRefundable();
        $refundAmount = isset($data['amount']) ? (float) $data['amount'] : $remaining;

        if ($refundAmount > $remaining + 0.001) {
            return back()->withErrors(['amount' => __('Refund amount cannot exceed the remaining refundable balance of :amount :currency.', ['amount' => number_format($remaining, 2), 'currency' => $payment->currency])]);
        }

        $old = $payment->only(['status', 'refunded_amount']);
        $newRefundedTotal = round((float) ($payment->refunded_amount ?? 0) + $refundAmount, 2);
        $isFullRefund = $newRefundedTotal >= (float) $payment->amount - 0.001;

        $payment->update([
            'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
            'refunded_amount' => $newRefundedTotal,
            'refund_reason' => $data['reason'] ?? $payment->refund_reason,
            'refunded_at' => now(),
        ]);

        AuditLog::record(
            'payment.refund',
            $payment,
            __('Refunded :amount :currency of payment #:id (:name)', [
                'amount' => number_format($refundAmount, 2), 'currency' => $payment->currency, 'id' => $payment->id, 'name' => $payment->company?->name ?? '',
            ]),
            old: $old,
            new: $payment->only(['status', 'refunded_amount'])
        );

        return back()->with('status', $isFullRefund ? __('Payment marked as refunded.') : __('Partial refund recorded.'));
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

    /**
     * Starts a brand-new checkout attempt for a failed online-gateway
     * payment, using the same provider/subscription/amount as the
     * original — the customer gets a fresh checkout link the next time
     * they visit Billing (or the admin can copy the URL from here).
     * Bank-transfer and manual payments have no gateway to retry through;
     * they go through "Mark manual payment" or "Confirm transfer" instead.
     */
    public function retry(int $payment, PaymentCheckoutService $checkout)
    {
        $payment = Payment::withoutGlobalScopes()->with('subscription.plan')->findOrFail($payment);

        abort_unless($payment->status === 'failed', 404);
        abort_unless($payment->subscription_id && in_array($payment->method, PaymentGateway::PROVIDERS, true), 404);

        $gateway = PaymentGateway::whereNull('company_id')->where('provider', $payment->method)->where('is_enabled', true)->first();
        abort_unless($gateway, 404);

        $subscription = $payment->subscription;
        $company = Company::findOrFail($payment->company_id);
        $owner = $company->owners()->first();

        try {
            $transaction = $checkout->start(
                $gateway,
                $subscription,
                (float) $payment->amount,
                $payment->currency,
                __('Retry payment for :plan plan', ['plan' => $subscription->plan->name ?? '']),
                ['name' => $owner?->name ?? '', 'email' => $owner?->email ?? ''],
                route('admin.payments.index')
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['payment' => __('Could not start a new checkout attempt: :error', ['error' => $e->getMessage()])]);
        }

        $newPayment = $company->payments()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $payment->plan_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'pending',
            'method' => $gateway->provider,
            'payment_transaction_id' => $transaction->id,
        ]);

        AuditLog::record('payment.retry', $payment, __('Retried failed payment #:id via :provider (new payment #:new_id)', [
            'id' => $payment->id, 'provider' => $gateway->provider, 'new_id' => $newPayment->id,
        ]), companyId: $company->id);

        return back()->with('status', __('A new payment attempt was started.'));
    }

    /**
     * Records money received outside any tracked gateway (cash, a wire
     * that didn't go through the bank_transfer flow, a manually-negotiated
     * arrangement) — support's tool for "the company paid, but not through
     * anything Daftari can verify automatically". Activates the
     * subscription immediately, same as a confirmed bank transfer.
     */
    public function storeManual(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        $subscription = $company->activeSubscription();
        abort_unless($subscription, 404, __('This company has no subscription to record a payment against.'));

        $payment = DB::transaction(function () use ($company, $subscription, $data) {
            $payment = $company->payments()->create([
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'amount' => $data['amount'],
                'currency' => $company->currency,
                'status' => 'paid',
                'method' => 'manual',
                'reference' => $data['reference'] ?? null,
                'paid_at' => now(),
            ]);

            if (in_array($subscription->status, ['pending', 'past_due', 'grace_period', 'suspended'], true)) {
                $subscription->update(['status' => 'active']);
            }

            AuditLog::record(
                'payment.mark_manual',
                $payment,
                __('Recorded manual payment of :amount :currency for :company', [
                    'amount' => number_format((float) $data['amount'], 2), 'currency' => $company->currency, 'company' => $company->name,
                ]),
                companyId: $company->id
            );

            return $payment;
        });

        return redirect()->route('admin.payments.show', $payment)->with('status', __('Manual payment recorded.'));
    }

    /**
     * @return array<int, array{label: string, at: ?\Illuminate\Support\Carbon}>
     */
    private function buildTimeline(Payment $payment, $webhookEvents, $auditLogs): array
    {
        $timeline = collect();

        $timeline->push(['label' => __('Payment record created'), 'at' => $payment->created_at]);

        foreach ($webhookEvents as $event) {
            $timeline->push(['label' => __('Webhook received (:status)', ['status' => $event->status]), 'at' => $event->created_at]);
        }

        if ($payment->paid_at) {
            $timeline->push(['label' => __('Marked paid'), 'at' => $payment->paid_at]);
        }

        if ($payment->refunded_at) {
            $timeline->push(['label' => $payment->status === 'refunded' ? __('Refunded') : __('Partially refunded'), 'at' => $payment->refunded_at]);
        }

        foreach ($auditLogs as $log) {
            $timeline->push(['label' => $log->description ?? $log->action, 'at' => $log->created_at]);
        }

        return $timeline->filter(fn ($item) => $item['at'])->sortBy('at')->values()->all();
    }
}
