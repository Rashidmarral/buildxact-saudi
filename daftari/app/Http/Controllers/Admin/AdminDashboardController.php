<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\ZatcaInvoiceLog;
use App\Services\SystemHealthService;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(SystemHealthService $health)
    {
        $activeSubscriptions = Subscription::withoutGlobalScopes()->where('status', 'active')->with('plan')->get();
        $mrr = $activeSubscriptions->sum(function ($sub) {
            $price = (float) $sub->plan->priceFor($sub->billing_cycle);

            return $sub->billing_cycle === 'yearly' ? $price / 12 : $price;
        });

        $revenueThisMonth = Payment::withoutGlobalScopes()
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $revenuePreviousMonth = Payment::withoutGlobalScopes()
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->subMonthNoOverflow()->month)
            ->whereYear('paid_at', now()->subMonthNoOverflow()->year)
            ->sum('amount');

        $churnedThisMonth = Subscription::withoutGlobalScopes()
            ->where('status', 'cancelled')
            ->whereMonth('cancelled_at', now()->month)
            ->whereYear('cancelled_at', now()->year)
            ->count();

        // One row per company — its most recent subscription — used for
        // every "current state" metric below (active/trialing/cancelled/
        // expired counts, past-due detection, trial conversion). Avoids
        // per-company N+1 lookups: this is two queries total (the id
        // subquery is inlined into the whereIn by the query builder).
        $latestSubscriptionIds = Subscription::withoutGlobalScopes()
            ->selectRaw('MAX(id) as id')
            ->groupBy('company_id');
        $latestSubscriptions = Subscription::withoutGlobalScopes()
            ->whereIn('id', $latestSubscriptionIds)
            ->get(['id', 'company_id', 'status', 'cancelled_at', 'current_period_end']);

        $trialingCompaniesCount = $latestSubscriptions->where('status', 'trialing')->count();
        $activeSubscriptionsCount = $latestSubscriptions->where('status', 'active')->count();
        $cancelledSubscriptionsCount = $latestSubscriptions->where('status', 'cancelled')->count();
        $expiredSubscriptionsCount = $latestSubscriptions->where('status', 'expired')->count();

        // "Past due": the paid period has already ended but the renewal
        // never landed and nobody cancelled it either — a stuck payment,
        // not a scheduled cancellation (see ExpireCancelledSubscriptions,
        // which only flips subscriptions that DO have cancelled_at set).
        $pastDueSubscriptionsCount = $latestSubscriptions
            ->where('status', 'active')
            ->whereNull('cancelled_at')
            ->filter(fn ($sub) => $sub->current_period_end && $sub->current_period_end->isPast())
            ->count();

        $everTrialedIds = Subscription::withoutGlobalScopes()->where('status', 'trialing')->select('company_id')->distinct();
        $everTrialedCount = (clone $everTrialedIds)->count();
        $convertedFromTrialCount = Subscription::withoutGlobalScopes()
            ->whereIn('status', ['active', 'cancelled', 'expired'])
            ->whereIn('company_id', $everTrialedIds)
            ->distinct()
            ->count('company_id');
        $trialConversionRate = $everTrialedCount > 0 ? round($convertedFromTrialCount / $everTrialedCount * 100, 1) : 0.0;

        $activeCompaniesCount = Company::where('status', 'active')->count();

        $stats = [
            'total_companies' => Company::count(),
            'active_companies' => $activeCompaniesCount,
            'trialing_companies' => $trialingCompaniesCount,
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'revenue_this_month' => $revenueThisMonth,
            'revenue_previous_month' => $revenuePreviousMonth,
            'arpc' => $activeSubscriptions->count() > 0 ? $mrr / $activeSubscriptions->count() : 0,
            'arpu_this_month' => $revenueThisMonth / max(1, $activeCompaniesCount),
            'churned_this_month' => $churnedThisMonth,
            'active_subscriptions' => $activeSubscriptionsCount,
            'trial_conversion_rate' => $trialConversionRate,
            'failed_payments_total' => Payment::withoutGlobalScopes()->where('status', 'failed')->count(),
            'past_due_subscriptions' => $pastDueSubscriptionsCount,
            'suspended_companies' => Company::where('status', 'suspended')->count(),
            'cancelled_subscriptions' => $cancelledSubscriptionsCount,
            'outstanding_revenue' => Payment::withoutGlobalScopes()->where('status', 'pending')->sum('amount'),
            // Simplified churn-rate proxy: this app doesn't track a
            // start-of-period cohort size, so this reads as "cancellations
            // this month relative to the currently active base" rather
            // than a textbook cohort churn rate — documented here so it
            // isn't mistaken for one.
            'churn_percentage' => $activeSubscriptionsCount > 0
                ? round($churnedThisMonth / $activeSubscriptionsCount * 100, 1)
                : 0.0,
            'growth_percentage' => $revenuePreviousMonth > 0
                ? round(($revenueThisMonth - $revenuePreviousMonth) / $revenuePreviousMonth * 100, 1)
                : ($revenueThisMonth > 0 ? 100.0 : 0.0),
            'new_companies_today' => Company::whereDate('created_at', now()->toDateString())->count(),
            'new_companies_this_week' => Company::where('created_at', '>=', now()->startOfWeek())->count(),
            'new_companies_this_month' => Company::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
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

        // Subscription status distribution — current state per company,
        // reusing $latestSubscriptions computed above (no extra query).
        $subscriptionStatusLabels = [
            'active' => __('Active'),
            'trialing' => __('Trialing'),
            'cancelled' => __('Cancelled'),
            'expired' => __('Expired'),
            'pending' => __('Pending'),
        ];
        $subscriptionStatusDistribution = collect($subscriptionStatusLabels)
            ->map(fn ($label, $status) => [
                'label' => $label,
                'status' => $status,
                'count' => $latestSubscriptions->where('status', $status)->count(),
            ])
            ->filter(fn ($row) => $row['count'] > 0)
            ->values();

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

        // "Needs attention": companies whose ZATCA sync has failed at least
        // once in the last 30 days, and any payment that failed to collect
        // — surfaced so support can catch a stuck company before the
        // customer has to report it themselves.
        $failedZatcaCompanies = ZatcaInvoiceLog::withoutGlobalScopes()
            ->where('status', 'failed')
            ->where('submitted_at', '>=', now()->subDays(30))
            ->select('company_id', DB::raw('COUNT(*) as failed_count'), DB::raw('MAX(submitted_at) as last_failed_at'))
            ->groupBy('company_id')
            ->orderByDesc('last_failed_at')
            ->take(8)
            ->get()
            ->load('company');

        $failedPayments = Payment::withoutGlobalScopes()
            ->where('status', 'failed')
            ->with('company')
            ->latest('paid_at')
            ->take(8)
            ->get();

        $expiredSubscriptionCompanies = Company::whereIn('id', $latestSubscriptions->where('status', 'expired')->pluck('company_id'))
            ->latest()
            ->take(8)
            ->get();

        $failedJobsCount = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

        // Companies with no session activity in the last 30 days, skipping
        // brand-new signups (a company that joined yesterday hasn't had a
        // chance to look "inactive" yet). One query for who HAS been
        // active, one for the companies not in that set — no per-company
        // lookups.
        $recentlyActiveCompanyIds = DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->where('sessions.last_activity', '>=', now()->subDays(30)->timestamp)
            ->whereNotNull('users.company_id')
            ->distinct()
            ->pluck('users.company_id');

        $noRecentLoginCompanies = Company::where('status', 'active')
            ->where('created_at', '<=', now()->subDays(30))
            ->whereNotIn('id', $recentlyActiveCompanyIds)
            ->latest()
            ->take(8)
            ->get();

        $systemHealthChecks = $health->checks();
        $recentErrorCount = $health->recentErrorCount();
        $storageCheck = collect($systemHealthChecks)->firstWhere('key', 'storage');

        return view('admin.dashboard', compact(
            'stats', 'recentCompanies', 'signupsTrend', 'revenueTrend',
            'planDistribution', 'trialsEndingSoon', 'recentPayments',
            'failedZatcaCompanies', 'failedPayments',
            'subscriptionStatusDistribution', 'expiredSubscriptionCompanies',
            'failedJobsCount', 'noRecentLoginCompanies',
            'systemHealthChecks', 'recentErrorCount', 'storageCheck'
        ));
    }
}
