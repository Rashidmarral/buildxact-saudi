<?php

namespace Tests\Feature\Machinery;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Please add operator/driver, where can I add operators" — a dedicated
 * quick-add page inside the Machinery module, rather than only a bare
 * Employee dropdown with no way to flag someone as an operator. Still
 * backed by the real Employee record (payroll/GOSI/termination apply),
 * just with an is_operator flag and a couple of license fields.
 */
class MachineryOperatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_machinery_equipment' => true, 'has_payroll' => true,
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

    public function test_adding_an_operator_creates_a_flagged_employee(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.machinery.operators.store'), [
            'full_name' => 'Khalid Al-Harbi', 'full_name_ar' => 'خالد الحربي',
            'mobile' => '0501112222', 'license_number' => 'DRV-1234', 'license_expiry_date' => now()->addYear()->toDateString(),
            'hire_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('app.machinery.operators.index'));
        $operator = Employee::where('company_id', $company->id)->first();
        $this->assertNotNull($operator);
        $this->assertTrue($operator->is_operator);
        $this->assertSame('DRV-1234', $operator->license_number);
        $this->assertSame('Equipment Operator', $operator->job_title);
        $this->assertSame('active', $operator->status);
    }

    public function test_the_operators_index_lists_only_flagged_employees(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Office Staff', 'hire_date' => now(), 'basic_salary' => 5000, 'status' => 'active', 'is_operator' => false]);
        Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00002', 'full_name' => 'Khalid The Driver', 'hire_date' => now(), 'basic_salary' => 0, 'status' => 'active', 'is_operator' => true]);

        $response = $this->actingAs($owner)->get(route('app.machinery.operators.index'));

        $response->assertOk();
        $response->assertSee('Khalid The Driver');
        $response->assertDontSee('Office Staff');
    }

    public function test_only_operator_flagged_employees_appear_in_the_asset_operator_dropdown(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Office Staff Member', 'hire_date' => now(), 'basic_salary' => 5000, 'status' => 'active', 'is_operator' => false]);
        Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00002', 'full_name' => 'Khalid The Driver', 'hire_date' => now(), 'basic_salary' => 0, 'status' => 'active', 'is_operator' => true]);

        $response = $this->actingAs($owner)->get(route('app.machinery.assets.create'));

        $response->assertOk();
        $response->assertSee('Khalid The Driver');
        $response->assertDontSee('Office Staff Member');
    }

    public function test_an_existing_employee_can_be_flagged_as_an_operator_from_the_regular_hr_form(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Nasser', 'hire_date' => now(), 'basic_salary' => 5000, 'status' => 'active']);

        $response = $this->actingAs($owner)->put(route('app.employees.update', $employee), [
            'full_name' => 'Nasser', 'hire_date' => now()->toDateString(), 'basic_salary' => 5000,
            'is_operator' => '1', 'license_number' => 'DRV-9999',
        ]);

        $response->assertRedirect(route('app.employees.index'));
        $this->assertTrue($employee->fresh()->is_operator);
        $this->assertSame('DRV-9999', $employee->fresh()->license_number);
    }
}
