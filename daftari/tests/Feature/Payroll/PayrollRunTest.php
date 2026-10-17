<?php

namespace Tests\Feature\Payroll;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\PayrollRun;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payroll\WpsSifExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requested: a full WPS-compliant Payroll module, gated behind the new
 * platform feature-toggle system. Covers the generate-draft -> approve
 * -> GL-posting flow, the platform/plan gate, and tenant isolation.
 */
class PayrollRunTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithPayroll(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_payroll' => true,
        ]);

        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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

    private function makeEmployee(Company $company, array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $company->id,
            'employee_number' => $company->nextEmployeeNumber(),
            'full_name' => 'Ahmed Ali',
            'is_saudi' => true,
            'hire_date' => now()->subYears(2),
            'status' => 'active',
            'basic_salary' => 8000,
            'housing_allowance' => 2000,
        ], $overrides));
    }

    public function test_generating_a_payroll_run_snapshots_every_active_employee_with_gosi_calculated(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company);

        $response = $this->actingAs($owner)->post(route('app.payroll.store'), [
            'period_month' => now()->month,
            'period_year' => now()->year,
            'pay_date' => now()->toDateString(),
        ]);

        $run = PayrollRun::first();
        $response->assertRedirect(route('app.payroll.show', $run));
        $this->assertSame('draft', $run->status);
        $this->assertCount(1, $run->items);
        $this->assertSame(975.0, (float) $run->items->first()->gosi_employee_contribution);
        $this->assertSame(10000.0 - 975.0, (float) $run->total_net);
    }

    public function test_a_second_run_for_the_same_period_is_rejected(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company);

        $payload = ['period_month' => now()->month, 'period_year' => now()->year, 'pay_date' => now()->toDateString()];
        $this->actingAs($owner)->post(route('app.payroll.store'), $payload);

        $response = $this->actingAs($owner)->post(route('app.payroll.store'), $payload);

        $response->assertSessionHasErrors('period_month');
        $this->assertSame(1, PayrollRun::count());
    }

    public function test_approving_a_run_posts_a_balanced_journal_entry(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company, ['basic_salary' => 8000, 'housing_allowance' => 2000]);
        $this->makeEmployee($company, ['is_saudi' => false, 'basic_salary' => 5000, 'housing_allowance' => 0, 'full_name' => 'John Smith']);

        $this->actingAs($owner)->post(route('app.payroll.store'), [
            'period_month' => now()->month, 'period_year' => now()->year, 'pay_date' => now()->toDateString(),
        ]);
        $run = PayrollRun::first();

        $response = $this->actingAs($owner)->post(route('app.payroll.approve', $run));

        $response->assertRedirect();
        $run->refresh();
        $this->assertSame('approved', $run->status);

        $entry = \App\Models\JournalEntry::where('source_type', 'payroll_run')->where('source_id', $run->id)->with('lines')->first();
        $this->assertNotNull($entry);
        $totalDebit = (float) $entry->lines->sum('debit');
        $totalCredit = (float) $entry->lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.01);
        $this->assertGreaterThan(0, $totalDebit);
    }

    public function test_only_a_draft_run_can_have_a_payslip_deduction_edited(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company);

        $this->actingAs($owner)->post(route('app.payroll.store'), [
            'period_month' => now()->month, 'period_year' => now()->year, 'pay_date' => now()->toDateString(),
        ]);
        $run = PayrollRun::first();
        $this->actingAs($owner)->post(route('app.payroll.approve', $run));

        $item = $run->items->first();
        $response = $this->actingAs($owner)->post(route('app.payroll.items.update', [$run, $item]), [
            'other_deductions' => 100, 'days_worked' => 30,
        ]);

        $response->assertSessionHasErrors('payroll_run');
    }

    public function test_terminating_an_employee_calculates_and_posts_an_end_of_service_settlement(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $employee = $this->makeEmployee($company, ['hire_date' => now()->subYears(6), 'basic_salary' => 9000]);

        $response = $this->actingAs($owner)->post(route('app.employees.terminate', $employee), [
            'termination_date' => now()->toDateString(),
            'reason' => 'termination',
        ]);

        $response->assertRedirect(route('app.employees.index'));
        $employee->refresh();
        $this->assertSame('terminated', $employee->status);

        $settlement = $employee->endOfServiceSettlements()->first();
        $this->assertNotNull($settlement);
        $this->assertGreaterThan(0, $settlement->gratuity_amount);

        $entry = \App\Models\JournalEntry::where('source_type', 'end_of_service_settlement')->where('source_id', $settlement->id)->with('lines')->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta((float) $entry->lines->sum('debit'), (float) $entry->lines->sum('credit'), 0.01);
    }

    public function test_a_company_without_the_payroll_plan_feature_is_blocked(): void
    {
        $plan = Plan::create([
            'name' => 'Basic', 'slug' => 'basic-'.uniqid(), 'price_monthly' => 10, 'price_yearly' => 100,
            'is_active' => true, 'has_payroll' => false,
        ]);
        $company = Company::create(['name' => 'NoPayroll', 'slug' => 'nopay-'.uniqid(), 'status' => 'active']);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.employees.index'));

        $response->assertRedirect(route('app.dashboard'));
        $response->assertSessionHasErrors('feature');
    }

    public function test_the_platform_wide_toggle_blocks_payroll_even_for_a_company_whose_plan_includes_it(): void
    {
        \App\Support\PlatformFeatureToggle::setEnabled('payroll', false);
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.employees.index'));

        $response->assertRedirect(route('app.dashboard'));
    }

    public function test_a_company_cannot_see_another_companys_employees_or_payroll_runs(): void
    {
        $companyA = $this->makeCompanyWithPayroll();
        $companyB = $this->makeCompanyWithPayroll();
        $employeeA = $this->makeEmployee($companyA);
        $ownerB = $this->makeOwner($companyB);

        $response = $this->actingAs($ownerB)->get(route('app.employees.edit', $employeeA));

        $response->assertNotFound();
    }

    public function test_wps_export_contains_a_header_detail_and_trailer_record_with_matching_totals(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company, ['iban' => 'SA0000000000000000000000', 'bank_name' => 'Test Bank']);

        $this->actingAs($owner)->post(route('app.payroll.store'), [
            'period_month' => now()->month, 'period_year' => now()->year, 'pay_date' => now()->toDateString(),
        ]);
        $run = PayrollRun::first();
        $this->actingAs($owner)->post(route('app.payroll.approve', $run));
        $run->refresh();

        $sif = app(WpsSifExporter::class)->export($company, $run);
        $lines = explode("\r\n", trim($sif));

        $this->assertStringStartsWith('H,', $lines[0]);
        $this->assertStringStartsWith('D,', $lines[1]);
        $this->assertStringStartsWith('T,', $lines[2]);
        $this->assertStringContainsString(number_format((float) $run->total_net, 2, '.', ''), $lines[0]);
        $this->assertStringContainsString(number_format((float) $run->total_net, 2, '.', ''), $lines[2]);
    }
}
