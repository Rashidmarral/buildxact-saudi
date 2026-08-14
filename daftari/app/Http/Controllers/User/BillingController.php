<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
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
            ];
        }

        return view('user.billing.index', compact('company', 'subscription', 'plans', 'payments', 'tab', 'usage'));
    }

    public function upgrade(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $company = Auth::user()->company;
        $plan = Plan::findOrFail($data['plan_id']);
        $periodEnd = $data['billing_cycle'] === 'yearly' ? now()->addYear() : now()->addMonth();

        DB::transaction(function () use ($company, $plan, $data, $periodEnd) {
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $data['billing_cycle'],
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]);

            // PAYMENT_GATEWAY=manual is a stub: it records the charge as paid
            // immediately. Wire up a real Saudi gateway (Moyasar, HyperPay,
            // PayTabs, Tap) here before taking this to production.
            $company->payments()->create([
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'amount' => $plan->priceFor($data['billing_cycle']),
                'currency' => $company->currency,
                'status' => 'paid',
                'method' => config('daftari.payment_gateway'),
                'paid_at' => now(),
            ]);
        });

        return redirect()->route('app.billing.index')->with('status', __('Subscription updated.'));
    }
}
