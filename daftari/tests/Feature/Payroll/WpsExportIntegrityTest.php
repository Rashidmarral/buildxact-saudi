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
 * Audit finding PAYROLL-02: the WPS SIF export silently emitted a blank
 * IBAN/national ID for any employee missing one on file, rather than
 * refusing to generate an incomplete government compliance file.
 */
class WpsExportIntegrityTest extends TestCase
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
            'company_id' => $company->id, 'employee_number' => $company->nextEmployeeNumber(),
            'full_name' => 'Ahmed Ali', 'is_saudi' => true, 'hire_date' => now()->subYears(2),
            'status' => 'active', 'basic_salary' => 8000, 'housing_allowance' => 2000,
        ], $overrides));
    }

    private function makeApprovedRun(Company $company, User $owner): PayrollRun
    {
        $this->actingAs($owner)->post(route('app.payroll.store'), [
            'period_month' => now()->month, 'period_year' => now()->year, 'pay_date' => now()->toDateString(),
        ]);
        $run = PayrollRun::first();
        $this->actingAs($owner)->post(route('app.payroll.approve', $run));

        return $run->refresh();
    }

    public function test_exporting_wps_for_an_employee_with_no_iban_on_file_is_refused(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company, ['national_id' => '1234567890']); // no iban
        $run = $this->makeApprovedRun($company, $owner);

        $response = $this->actingAs($owner)->get(route('app.payroll.wps', $run));

        $response->assertRedirect();
        $response->assertSessionHasErrors('wps');
    }

    public function test_exporting_wps_for_an_employee_with_no_national_id_on_file_is_refused(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company, ['iban' => 'SA0000000000000000000000']); // no national_id
        $run = $this->makeApprovedRun($company, $owner);

        $response = $this->actingAs($owner)->get(route('app.payroll.wps', $run));

        $response->assertRedirect();
        $response->assertSessionHasErrors('wps');
    }

    public function test_exporting_wps_succeeds_once_every_employee_has_an_iban_and_national_id(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company, ['iban' => 'SA0000000000000000000000', 'national_id' => '1234567890']);
        $run = $this->makeApprovedRun($company, $owner);

        $response = $this->actingAs($owner)->get(route('app.payroll.wps', $run));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_a_run_with_one_complete_and_one_incomplete_employee_is_refused_entirely(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company, ['full_name' => 'Complete Employee', 'iban' => 'SA0000000000000000000000', 'national_id' => '1234567890']);
        $this->makeEmployee($company, ['full_name' => 'Incomplete Employee']);
        $run = $this->makeApprovedRun($company, $owner);

        $response = $this->actingAs($owner)->get(route('app.payroll.wps', $run));

        $response->assertSessionHasErrors('wps');
        $errors = session('errors')->get('wps');
        $this->assertStringContainsString('Incomplete Employee', $errors[0]);
        $this->assertStringNotContainsString('Complete Employee', $errors[0]);
    }
}
