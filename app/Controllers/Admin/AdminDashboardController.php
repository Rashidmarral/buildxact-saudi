<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;

class AdminDashboardController extends Controller
{
    public function index(): void
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::count("status = 'active'");
        $trialCompanies = Company::count("status = 'trial'");

        $activeSubs = Subscription::query(
            "SELECT s.*, p.price_monthly, p.price_yearly FROM subscriptions s
             JOIN plans p ON p.id = s.plan_id
             WHERE s.status = 'active'
             AND s.id IN (SELECT MAX(id) FROM subscriptions GROUP BY company_id)"
        )->fetchAll();

        $mrr = 0.0;
        foreach ($activeSubs as $sub) {
            $mrr += $sub['billing_cycle'] === 'yearly' ? ((float) $sub['price_yearly'] / 12) : (float) $sub['price_monthly'];
        }

        $thisMonth = date('Y-m');
        $allPayments = Payment::query("SELECT amount, created_at FROM payments WHERE status = 'paid'")->fetchAll();
        $revenueThisMonth = array_sum(array_map(
            fn($p) => substr((string) $p['created_at'], 0, 7) === $thisMonth ? (float) $p['amount'] : 0,
            $allPayments
        ));

        $allCompanies = Company::query('SELECT created_at FROM companies')->fetchAll();
        $signupsThisMonth = count(array_filter($allCompanies, fn($c) => substr((string) $c['created_at'], 0, 7) === $thisMonth));

        $recentCompanies = Company::query(
            'SELECT c.*, p.name AS plan_name FROM companies c LEFT JOIN plans p ON p.id = c.plan_id ORDER BY c.created_at DESC LIMIT 8'
        )->fetchAll();

        $planCounts = Plan::query(
            'SELECT p.name, COUNT(c.id) AS company_count FROM plans p LEFT JOIN companies c ON c.plan_id = p.id GROUP BY p.id ORDER BY p.sort_order'
        )->fetchAll();

        $this->view('admin/dashboard', [
            'pageTitle' => 'Overview',
            'totalCompanies' => $totalCompanies,
            'activeCompanies' => $activeCompanies,
            'trialCompanies' => $trialCompanies,
            'mrr' => $mrr,
            'revenueThisMonth' => $revenueThisMonth,
            'signupsThisMonth' => $signupsThisMonth,
            'recentCompanies' => $recentCompanies,
            'planCounts' => $planCounts,
        ], 'layouts/admin');
    }
}
