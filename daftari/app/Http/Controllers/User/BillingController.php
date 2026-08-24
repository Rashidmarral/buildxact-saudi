<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MpdfRenderer;
use App\Services\Payments\PaymentCheckoutService;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $company = Auth::user()->company;
        $subscription = $company->activeSubscription();
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $payments = $company->payments()->latest('paid_at')->latest('id')->paginate(15);

        $tab = in_array($request->query('tab'), ['overview', 'plans', 'addons'], true) ? $request->query('tab') : 'overview';

        $usage = null;
        if ($subscription) {
            $periodStart = $subscription->current_period_start ?? $company->created_at;

            $usage = [
                'invoices' => [
                    'used' => $company->invoices()->where('created_at', '>=', $periodStart)->count(),
                    'limit' => $subscription->plan->max_invoices_per_month,
                ],
                'customers' => [
                    'used' => $company->clients()->count(),
                    'limit' => $subscription->plan->max_customers,
                ],
                'suppliers' => [
                    'used' => $company->suppliers()->count(),
                    'limit' => $subscription->plan->max_suppliers,
                ],
                'users' => [
                    'used' => $company->users()->count(),
                    'limit' => $subscription->plan->max_users,
                ],
                'invoice_templates' => [
                    'used' => $company->invoiceTemplates()->count(),
                    'limit' => $subscription->plan->max_invoice_templates,
                ],
                'warehouses' => [
                    'used' => $company->warehouses()->count(),
                    'limit' => $subscription->plan->max_warehouses,
                ],
                'bank_accounts' => [
                    'used' => $company->bankAccounts()->count(),
                    'limit' => $subscription->plan->max_bank_accounts,
                ],
                'branches' => [
                    'used' => $company->branches()->count(),
                    'limit' => $subscription->plan->max_branches,
                ],
            ];
        }

        $enabledProviders = PaymentGateway::whereNull('company_id')->where('is_enabled', true)->pluck('provider')->all();

        return view('user.billing.index', compact('company', 'subscription', 'plans', 'payments', 'tab', 'usage', 'enabledProviders'));
    }

    public function upgrade(Request $request, PaymentCheckoutService $checkout, PaymentSettlementService $settlement)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'provider' => ['nullable', 'string'],
        ]);

        $company = Auth::user()->company;
        $plan = Plan::findOrFail($data['plan_id']);
        $periodEnd = $data['billing_cycle'] === 'yearly' ? now()->addYear() : now()->addMonth();
        $amount = $plan->priceFor($data['billing_cycle']);

        $gateway = ! empty($data['provider'])
            ? PaymentGateway::whereNull('company_id')->where('provider', $data['provider'])->where('is_enabled', true)->first()
            : null;

        if ($gateway && $gateway->provider === PaymentGateway::BANK_TRANSFER) {
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'billing_cycle' => $data['billing_cycle'],
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]);

            $payment = $company->payments()->create([
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'amount' => $amount,
                'currency' => $company->currency,
                'status' => 'pending',
                'method' => PaymentGateway::BANK_TRANSFER,
            ]);

            AuditLog::record('subscription.checkout_started', $subscription, __('Started offline bank-transfer payment for :plan plan', ['plan' => $plan->name]));

            return redirect()->route('app.billing.bank-transfer', $payment->id);
        }

        if ($gateway) {
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'billing_cycle' => $data['billing_cycle'],
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]);

            $owner = Auth::user();
            $transaction = $checkout->start(
                $gateway,
                $subscription,
                $amount,
                $company->currency,
                __(':plan plan (:cycle)', ['plan' => $plan->name, 'cycle' => $data['billing_cycle']]),
                ['name' => $owner->name, 'email' => $owner->email],
                route('app.billing.index')
            );

            AuditLog::record('subscription.checkout_started', $subscription, __('Started online payment for :plan plan via :provider', ['plan' => $plan->name, 'provider' => $gateway->provider]));

            return redirect()->away($transaction->checkout_url);
        }

        $payment = DB::transaction(function () use ($company, $plan, $data, $periodEnd) {
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $data['billing_cycle'],
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]);

            // PAYMENT_GATEWAY=manual is a stub: it records the charge as paid
            // immediately. Used when the platform admin hasn't enabled any
            // real gateway (online or offline bank transfer) yet.
            return $company->payments()->create([
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'amount' => $plan->priceFor($data['billing_cycle']),
                'currency' => $company->currency,
                'status' => 'paid',
                'method' => config('daftari.payment_gateway'),
                'paid_at' => now(),
            ]);
        });

        $settlement->sendSubscriptionReceipt($payment);

        return redirect()->route('app.billing.index')->with('status', __('Subscription updated.'));
    }

    /**
     * Shown after choosing to pay a subscription by offline bank transfer —
     * Daftari's own bank details plus the pending payment reference. The
     * subscription stays "pending" (no feature access yet) until an admin
     * confirms the transfer arrived (Admin\PaymentController::confirmBankTransfer).
     */
    public function bankTransferInstructions(int $payment)
    {
        $payment = Payment::where('company_id', Auth::user()->company_id)
            ->where('method', PaymentGateway::BANK_TRANSFER)
            ->with('plan')
            ->findOrFail($payment);

        $gateway = PaymentGateway::platform(PaymentGateway::BANK_TRANSFER);
        abort_unless($gateway && $gateway->is_enabled, 404);

        return view('user.billing.bank-transfer', [
            'payment' => $payment,
            'bank' => $gateway->credentials,
        ]);
    }

    /**
     * "Cancels" a subscription without cutting off access mid-period: the
     * company already paid for this period, so status stays trialing/active
     * and every existing feature/limit check keeps working unchanged.
     * cancelled_at just marks it as scheduled to lapse — the daily
     * subscriptions:expire-cancelled command flips status to "cancelled"
     * once current_period_end actually passes.
     */
    public function cancel()
    {
        $company = Auth::user()->company;
        $subscription = $company->activeSubscription();

        abort_unless($subscription && ! $subscription->cancelled_at, 404);

        $subscription->update(['cancelled_at' => now()]);

        AuditLog::record(
            'subscription.cancel',
            $subscription,
            __('Cancelled subscription, effective :date', ['date' => $subscription->current_period_end->format('Y-m-d')])
        );

        return back()->with('status', __('Your subscription will end on :date. You can keep using :plan until then.', [
            'date' => $subscription->current_period_end->format('Y-m-d'),
            'plan' => $subscription->plan->name,
        ]));
    }

    public function resume()
    {
        $company = Auth::user()->company;
        $subscription = $company->activeSubscription();

        abort_unless($subscription && $subscription->cancelled_at, 404);

        $subscription->update(['cancelled_at' => null]);

        AuditLog::record('subscription.resume', $subscription, __('Resumed subscription'));

        return back()->with('status', __('Your subscription has been resumed.'));
    }

    public function downloadReceipt(Payment $payment, MpdfRenderer $renderer)
    {
        abort_unless($payment->company_id === Auth::user()->company_id, 404);
        abort_unless($payment->status === 'paid', 404);

        $payment->loadMissing('plan', 'company');

        $pdf = $renderer->render('documents.print.saas-receipt', [
            'payment' => $payment,
            'company' => $payment->company,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="receipt-'.$payment->id.'.pdf"',
        ]);
    }
}
