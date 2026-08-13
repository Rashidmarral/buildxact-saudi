<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payment;
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
        ];

        $recentCompanies = Company::latest()->take(8)->get();

        return view('admin.dashboard', compact('stats', 'recentCompanies'));
    }
}
