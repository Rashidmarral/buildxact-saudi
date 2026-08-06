<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;

class DashboardController extends Controller
{
    public function index(): void
    {
        $companyId = Auth::companyId();

        $activeProjects = Project::count('company_id = ? AND status != ?', [$companyId, 'completed']);
        $totalBudget = Project::sum('budget', 'company_id = ?', [$companyId]);
        $outstanding = Invoice::sum('total', 'company_id = ? AND status != ?', [$companyId, 'paid']);

        $paidInvoices = Invoice::query("SELECT total, created_at FROM invoices WHERE company_id = ? AND status = 'paid'", [$companyId])->fetchAll();
        $thisMonth = date('Y-m');
        $paidThisMonth = array_sum(array_map(
            fn($row) => substr((string) $row['created_at'], 0, 7) === $thisMonth ? (float) $row['total'] : 0,
            $paidInvoices
        ));

        $recentProjects = Project::query('SELECT * FROM projects WHERE company_id = ? ORDER BY created_at DESC LIMIT 5', [$companyId])->fetchAll();
        $upcomingTasks = Task::query("SELECT * FROM schedule_tasks WHERE company_id = ? AND status != 'done' ORDER BY start_date ASC LIMIT 6", [$companyId])->fetchAll();
        $recentEstimates = Estimate::query('SELECT * FROM estimates WHERE company_id = ? ORDER BY created_at DESC LIMIT 5', [$companyId])->fetchAll();

        $this->view('user/dashboard', [
            'pageTitle' => 'Dashboard',
            'activeProjects' => $activeProjects,
            'totalBudget' => $totalBudget,
            'outstanding' => $outstanding,
            'paidThisMonth' => $paidThisMonth,
            'recentProjects' => $recentProjects,
            'upcomingTasks' => $upcomingTasks,
            'recentEstimates' => $recentEstimates,
            'clientCount' => Client::count('company_id = ?', [$companyId]),
        ], 'layouts/app');
    }
}
