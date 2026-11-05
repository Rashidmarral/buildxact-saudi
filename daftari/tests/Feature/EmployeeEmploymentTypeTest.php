<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add option to add employees for project on contract or full time" —
 * employment_type is informational only (job classification for
 * record-keeping), so it defaults to 'full_time' and never blocks an
 * employee from being created, assigned to a project, or selected on a
 * voucher either way.
 */
class EmployeeEmploymentTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_an_employee_can_be_created_as_a_contract_employee(): void
    {
        [$company, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.employees.store'), [
            'full_name' => 'Shahid Al-Zubaidi', 'hire_date' => now()->toDateString(),
            'basic_salary' => 6000, 'employment_type' => 'contract',
        ]);

        $response->assertRedirect(route('app.employees.index'));
        $this->assertSame('contract', Employee::where('company_id', $company->id)->first()->employment_type);
    }

    public function test_an_employee_defaults_to_full_time_when_not_given(): void
    {
        [$company, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.employees.store'), [
            'full_name' => 'Nasser', 'hire_date' => now()->toDateString(), 'basic_salary' => 5000,
        ]);

        $response->assertRedirect(route('app.employees.index'));
        $this->assertSame('full_time', Employee::where('company_id', $company->id)->first()->employment_type);
    }

    public function test_updating_an_employee_preserves_its_employment_type_when_not_given(): void
    {
        [$company, $owner] = $this->makeOwner();
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Khalid', 'hire_date' => now(), 'basic_salary' => 5000, 'employment_type' => 'contract']);

        $response = $this->actingAs($owner)->put(route('app.employees.update', $employee), [
            'full_name' => 'Khalid', 'hire_date' => now()->toDateString(), 'basic_salary' => 5500,
        ]);

        $response->assertRedirect(route('app.employees.index'));
        $this->assertSame('contract', $employee->fresh()->employment_type);
    }
}
