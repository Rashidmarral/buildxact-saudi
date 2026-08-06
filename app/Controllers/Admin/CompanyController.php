<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;

class CompanyController extends Controller
{
    public function index(): void
    {
        $companies = Company::query(
            'SELECT c.*, p.name AS plan_name FROM companies c LEFT JOIN plans p ON p.id = c.plan_id ORDER BY c.created_at DESC'
        )->fetchAll();
        $this->view('admin/companies/index', ['pageTitle' => 'Companies', 'companies' => $companies], 'layouts/admin');
    }

    public function show(string $id): void
    {
        $company = Company::find((int) $id);
        if (!$company) {
            http_response_code(404);
            die('Company not found.');
        }
        $users = User::where('company_id', $company['id'], 'created_at ASC');
        $subscriptions = Subscription::query(
            'SELECT s.*, p.name AS plan_name FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.company_id = ? ORDER BY s.created_at DESC',
            [$company['id']]
        )->fetchAll();
        $payments = Payment::where('company_id', $company['id'], 'created_at DESC');
        $projectCount = Project::count('company_id = ?', [$company['id']]);

        $this->view('admin/companies/show', [
            'pageTitle' => $company['name'],
            'company' => $company,
            'users' => $users,
            'subscriptions' => $subscriptions,
            'payments' => $payments,
            'projectCount' => $projectCount,
        ], 'layouts/admin');
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        $company = Company::find((int) $id);
        if (!$company) {
            http_response_code(404);
            die('Company not found.');
        }
        $status = (string) $this->input('status');
        if (in_array($status, ['trial', 'active', 'suspended', 'cancelled'], true)) {
            Company::update($company['id'], ['status' => $status]);
            $this->flash('success', 'Company status updated.');
        }
        self::redirect('/admin/companies/' . $company['id']);
    }
}
