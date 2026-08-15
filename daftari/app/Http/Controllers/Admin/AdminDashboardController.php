<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $activeSubscriptions = Subscription::withoutGlobalScopes()->where('status', 'active')->with('plan')->get();
        $mrr = $activeSubscriptions->sum(function ($sub) {
            $price = (float) $sub->plan->priceFor($sub->billing_cycle);

            return $sub->billing_cycle === 'yearly' ? $price / 12 : $price;
        });

        $stats = [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', 'active')->count(),
            'trialing_companies' => Subscription::withoutGlobalScopes()->where('status', 'trialing')->distinct('company_id')->count('company_id'),
            'mrr' => $mrr,
            'revenue_this_month' => Payment::withoutGlobalScopes()
                ->where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
            'arpc' => $activeSubscriptions->count() > 0 ? $mrr / $activeSubscriptions->count() : 0,
            'churned_this_month' => Subscription::withoutGlobalScopes()
                ->where('status', 'cancelled')
                ->whereMonth('cancelled_at', now()->month)
                ->whereYear('cancelled_at', now()->year)
                ->count(),
        ];

        $recentCompanies = Company::latest()->take(6)->get();

        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i));

        $signupsTrend = $months->map(function ($month) {
            return [
                'label' => $month->translatedFormat('M'),
                'value' => Company::whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
            ];
        })->values();

        $revenueTrend = $months->map(function ($month) {
            return [
                'label' => $month->translatedFormat('M'),
                'value' => (float) Payment::withoutGlobalScopes()
                    ->where('status', 'paid')
                    ->whereYear('paid_at', $month->year)
                    ->whereMonth('paid_at', $month->month)
                    ->sum('amount'),
            ];
        })->values();

        $planDistribution = Plan::withCount(['subscriptions' => function ($q) {
            $q->withoutGlobalScopes()->where('status', 'active');
        }])->orderBy('sort_order')->get()->filter(fn ($plan) => $plan->subscriptions_count > 0)->values();

        $trialsEndingSoon = Subscription::withoutGlobalScopes()
            ->where('status', 'trialing')
            ->where('current_period_end', '<=', now()->addDays(3))
            ->with(['company', 'plan'])
            ->orderBy('current_period_end')
            ->take(6)
            ->get();

        $recentPayments = Payment::withoutGlobalScopes()
            ->with(['company', 'plan'])
            ->latest('paid_at')
            ->take(8)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'recentCompanies', 'signupsTrend', 'revenueTrend',
            'planDistribution', 'trialsEndingSoon', 'recentPayments'
        ));
    }
}
