<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;

class BillingController extends Controller
{
    public function index(): void
    {
        $companyId = Auth::companyId();
        $company = Company::find($companyId);
        $plans = Plan::query('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
        $currentPlan = $company['plan_id'] ? Plan::find((int) $company['plan_id']) : null;
        $subscription = Subscription::query('SELECT * FROM subscriptions WHERE company_id = ? ORDER BY created_at DESC LIMIT 1', [$companyId])->fetch();
        $payments = Payment::where('company_id', $companyId, 'created_at DESC');

        $this->view('user/billing/index', [
            'pageTitle' => 'Billing & Subscription',
            'company' => $company,
            'plans' => $plans,
            'currentPlan' => $currentPlan,
            'subscription' => $subscription,
            'payments' => $payments,
        ], 'layouts/app');
    }

    public function upgrade(): void
    {
        $this->verifyCsrf();
        if (!Auth::isCompanyOwner()) {
            $this->flash('error', 'Only the company owner can change the subscription plan.');
            self::redirect('/app/billing');
        }

        $companyId = Auth::companyId();
        $plan = Plan::first('slug', (string) $this->input('plan'));
        if (!$plan) {
            $this->flash('error', 'Invalid plan selected.');
            self::redirect('/app/billing');
        }

        $cycle = $this->input('cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $amount = $cycle === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly'];

        Company::update($companyId, ['plan_id' => $plan['id'], 'status' => 'active']);

        Subscription::create([
            'company_id' => $companyId,
            'plan_id' => $plan['id'],
            'billing_cycle' => $cycle,
            'status' => 'active',
            'current_period_end' => date('Y-m-d', strtotime($cycle === 'yearly' ? '+1 year' : '+30 days')),
        ]);

        Payment::create([
            'company_id' => $companyId,
            'amount' => $amount,
            'currency' => 'SAR',
            'method' => 'mada',
            'reference' => 'PMT-' . strtoupper(bin2hex(random_bytes(3))),
            'status' => 'paid',
        ]);

        $this->flash('success', "You're now on the {$plan['name']} plan.");
        self::redirect('/app/billing');
    }
}
