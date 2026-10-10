<?php

namespace Tests\Feature\ProjectCashFlow;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentVoucher;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ReceiptVoucher;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please expand the project cash flow it's very limitted like i am
 * unable to calculate the expenses or add more features" — the summary
 * tiles used to call Project::cashReceived()/cashPaid()/etc. (all-time,
 * ignoring any bank-account/date filter already narrowing the ledger
 * below them) and lumped directly-paid Expenses into the same "paid"
 * figure as Payment Vouchers. This covers: a date-range filter that
 * narrows both the ledger and the tiles together, Expenses getting a
 * total (and a by-category breakdown) distinct from Payment Vouchers,
 * and the account-specific filter actually affecting the tiles too.
 */
class ProjectCashFlowExpansionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_project_cash_flow' => true,
        ]);

        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeBankAccount(Company $company, string $name = 'SNB Current Account'): BankAccount
    {
        return BankAccount::create(['company_id' => $company->id, 'name' => $name, 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
    }

    public function test_a_date_range_filter_narrows_both_the_ledger_and_the_summary_tiles_together(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);
        $account = $this->makeBankAccount($company);

        ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'party_type' => 'manual', 'voucher_number' => 'RV-OLD', 'date' => '2026-01-01',
            'payer_name' => 'Old receipt', 'amount' => 10000, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);
        ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'party_type' => 'manual', 'voucher_number' => 'RV-NEW', 'date' => now()->toDateString(),
            'payer_name' => 'New receipt', 'amount' => 4000, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.show', [
            'project' => $project, 'from' => now()->startOfMonth()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertDontSee('RV-OLD');
        $response->assertSee('RV-NEW');
        // The "Total received" tile must reflect only the filtered 4,000 —
        // not the unfiltered 14,000 — proving the tiles are now derived
        // from the same filtered ledger as the table beneath them.
        $response->assertSee(\App\Support\Money::format(4000));
        $response->assertDontSee(\App\Support\Money::format(14000));
    }

    public function test_expenses_get_their_own_total_separate_from_payment_vouchers(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);
        $account = $this->makeBankAccount($company);

        PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'party_type' => 'manual', 'voucher_number' => 'PV-1', 'date' => now()->toDateString(),
            'payee_name' => 'Subcontractor', 'amount' => 20000, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);
        Expense::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'vendor_name' => 'Fuel Station', 'description' => 'Diesel',
            'amount' => 3500, 'gross_amount' => 3500, 'vat_amount' => 0, 'tax_category' => 'zero_rated',
            'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.show', $project));

        $response->assertOk();
        // The two totals must each be individually visible — not only
        // their 23,500 sum — so a user can read "Expenses" without first
        // subtracting Payment Vouchers from a combined figure.
        $response->assertSee(\App\Support\Money::format(20000));
        $response->assertSee(\App\Support\Money::format(3500));
    }

    public function test_the_expense_by_category_breakdown_totals_each_category_separately(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);
        $account = $this->makeBankAccount($company);
        $fuel = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Fuel']);
        $materials = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Materials']);

        Expense::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'expense_category_id' => $fuel->id, 'vendor_name' => 'Fuel Station A',
            'amount' => 1000, 'gross_amount' => 1000, 'vat_amount' => 0, 'tax_category' => 'zero_rated',
            'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);
        Expense::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'expense_category_id' => $fuel->id, 'vendor_name' => 'Fuel Station B',
            'amount' => 1500, 'gross_amount' => 1500, 'vat_amount' => 0, 'tax_category' => 'zero_rated',
            'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);
        Expense::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'expense_category_id' => $materials->id, 'vendor_name' => 'Steel Supplier',
            'amount' => 9000, 'gross_amount' => 9000, 'vat_amount' => 0, 'tax_category' => 'zero_rated',
            'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.show', $project));

        $response->assertOk();
        $response->assertSee('Fuel');
        $response->assertSee('Materials');
        // Fuel's two expenses (1,000 + 1,500) must be combined into one
        // category total, distinct from Materials' 9,000.
        $response->assertSee(\App\Support\Money::format(2500));
        $response->assertSee(\App\Support\Money::format(9000));
    }

    /**
     * Regression: before this fix, selecting a specific bank account from
     * the dropdown narrowed the ledger table but the summary tiles above
     * it kept showing the company-wide, all-account total — a mismatch
     * between the table and the tiles sitting right above it.
     */
    public function test_selecting_a_bank_account_narrows_the_summary_tiles_too(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);
        $accountA = $this->makeBankAccount($company, 'SNB Current Account');
        $accountB = $this->makeBankAccount($company, 'Al Rajhi Account');

        ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $accountA->id, 'project_id' => $project->id,
            'party_type' => 'manual', 'voucher_number' => 'RV-A', 'date' => now()->toDateString(),
            'payer_name' => 'Via SNB', 'amount' => 7000, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);
        ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $accountB->id, 'project_id' => $project->id,
            'party_type' => 'manual', 'voucher_number' => 'RV-B', 'date' => now()->toDateString(),
            'payer_name' => 'Via Al Rajhi', 'amount' => 2000, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.show', [
            'project' => $project, 'bank_account_id' => $accountA->id,
        ]));

        $response->assertOk();
        $response->assertSee(\App\Support\Money::format(7000));
        $response->assertDontSee(\App\Support\Money::format(9000));
    }

    public function test_income_and_invoice_payment_rows_are_labeled_correctly_not_as_a_transfer(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.incomes.store'), [
            'income_date' => now()->toDateString(), 'gross_amount' => 5000, 'tax_category' => 'zero_rated',
            'bank_account_id' => $account->id, 'project_id' => $project->id, 'payer_name' => 'Scrap buyer',
        ])->assertRedirect();

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.show', $project));
        $response->assertOk();
        $response->assertSee(__('Income'));
        $response->assertDontSeeText(__('Transfer'));
    }
}
