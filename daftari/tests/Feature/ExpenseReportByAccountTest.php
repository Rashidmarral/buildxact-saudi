<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i need to track project expenses from operating account" — money
 * moved from an operating bank account into a separate custody/petty-cash
 * account (both plain BankAccount rows) looked identical on the Expense
 * Report: it had no account or project filter, and no way to see totals
 * split by account. Adds both.
 */
class ExpenseReportByAccountTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_filtering_the_expense_report_by_account_only_shows_that_accounts_expenses(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $operating = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $custody = BankAccount::create(['company_id' => $company->id, 'name' => 'Custody Account', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Sada Al Jeul Road Works']);

        Expense::create(['company_id' => $company->id, 'bank_account_id' => $operating->id, 'project_id' => $project->id, 'expense_date' => now(), 'vendor_name' => 'Asphalt Supplier', 'description' => 'Project material', 'amount' => 50000, 'vat_amount' => 7500, 'status' => 'approved']);
        Expense::create(['company_id' => $company->id, 'bank_account_id' => $custody->id, 'expense_date' => now(), 'vendor_name' => 'Fuel Station', 'description' => 'Daily fuel', 'amount' => 300, 'vat_amount' => 45, 'status' => 'approved']);

        $response = $this->actingAs($owner)->get(route('app.reports.expenses', ['bank_account_id' => $operating->id]));

        $response->assertOk();
        $response->assertSee('Asphalt Supplier');
        $response->assertDontSee('Fuel Station');
    }

    public function test_the_expense_report_breaks_totals_down_by_account(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $operating = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $custody = BankAccount::create(['company_id' => $company->id, 'name' => 'Custody Account', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);

        Expense::create(['company_id' => $company->id, 'bank_account_id' => $operating->id, 'expense_date' => now(), 'vendor_name' => 'A', 'description' => 'x', 'amount' => 50000, 'vat_amount' => 7500, 'status' => 'approved']);
        Expense::create(['company_id' => $company->id, 'bank_account_id' => $custody->id, 'expense_date' => now(), 'vendor_name' => 'B', 'description' => 'y', 'amount' => 300, 'vat_amount' => 45, 'status' => 'approved']);

        $response = $this->actingAs($owner)->get(route('app.reports.expenses'));

        $response->assertOk();
        $response->assertSeeInOrder(['Operating Account', 'Custody Account']);
    }

    public function test_filtering_the_expense_report_by_project_only_shows_that_projects_expenses(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $projectA = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Road Works']);
        $projectB = Project::create(['company_id' => $company->id, 'code' => 'PROJ-2', 'name' => 'Warehouse Fitout']);

        Expense::create(['company_id' => $company->id, 'project_id' => $projectA->id, 'expense_date' => now(), 'vendor_name' => 'Road Vendor', 'description' => 'x', 'amount' => 1000, 'vat_amount' => 150, 'status' => 'approved']);
        Expense::create(['company_id' => $company->id, 'project_id' => $projectB->id, 'expense_date' => now(), 'vendor_name' => 'Warehouse Vendor', 'description' => 'y', 'amount' => 2000, 'vat_amount' => 300, 'status' => 'approved']);

        $response = $this->actingAs($owner)->get(route('app.reports.expenses', ['project_id' => $projectA->id]));

        $response->assertOk();
        $response->assertSee('Road Vendor');
        $response->assertDontSee('Warehouse Vendor');
    }

    public function test_csv_export_includes_the_account_and_project_columns(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $operating = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Road Works']);

        Expense::create(['company_id' => $company->id, 'bank_account_id' => $operating->id, 'project_id' => $project->id, 'expense_date' => now(), 'vendor_name' => 'Asphalt Supplier', 'description' => 'x', 'amount' => 50000, 'vat_amount' => 7500, 'status' => 'approved']);

        $response = $this->actingAs($owner)->get(route('app.reports.expenses', ['export' => 'csv']));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Operating Account', $content);
        $this->assertStringContainsString('Road Works', $content);
    }
}
