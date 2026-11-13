<?php

namespace Tests\Feature\Machinery;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Employee;
use App\Models\MachineryAsset;
use App\Models\MachineryTripLog;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "build both those next" (maintenance scheduling + trip logs) — a
 * day-by-day record of who had a machine out, where, and why.
 */
class MachineryTripLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_machinery_equipment' => true,
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

    private function makeAsset(Company $company): MachineryAsset
    {
        return MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-00001', 'name' => 'Pickup (Hilux)', 'status' => 'available']);
    }

    public function test_it_logs_a_trip_with_operator_and_project(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $asset = $this->makeAsset($company);
        $operator = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Khalid The Driver', 'hire_date' => now(), 'basic_salary' => 0, 'status' => 'active', 'is_operator' => true]);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ_00001', 'name' => 'Jumum Yard', 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.machinery.trips.store'), [
            'machinery_asset_id' => $asset->id,
            'operator_employee_id' => $operator->id,
            'project_id' => $project->id,
            'trip_date' => now()->toDateString(),
            'purpose' => 'Material pickup from Jeddah',
            'from_location' => 'Yard',
            'to_location' => 'Jeddah',
        ]);

        $response->assertRedirect(route('app.machinery.trips.index'));
        $trip = MachineryTripLog::where('machinery_asset_id', $asset->id)->first();
        $this->assertNotNull($trip);
        $this->assertSame($operator->id, $trip->operator_employee_id);
        $this->assertSame($project->id, $trip->project_id);
        $this->assertSame('Material pickup from Jeddah', $trip->purpose);
    }

    public function test_operator_and_project_are_optional(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $asset = $this->makeAsset($company);

        $response = $this->actingAs($owner)->post(route('app.machinery.trips.store'), [
            'machinery_asset_id' => $asset->id,
            'trip_date' => now()->toDateString(),
            'purpose' => 'Fuel run',
        ]);

        $response->assertRedirect(route('app.machinery.trips.index'));
        $this->assertSame(1, MachineryTripLog::where('machinery_asset_id', $asset->id)->count());
    }

    public function test_the_create_form_only_lists_operator_flagged_employees(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->makeAsset($company);
        Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Office Staff', 'hire_date' => now(), 'basic_salary' => 5000, 'status' => 'active', 'is_operator' => false]);
        Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00002', 'full_name' => 'Khalid The Driver', 'hire_date' => now(), 'basic_salary' => 0, 'status' => 'active', 'is_operator' => true]);

        $response = $this->actingAs($owner)->get(route('app.machinery.trips.create'));

        $response->assertOk();
        $response->assertSee('Khalid The Driver');
        $response->assertDontSee('Office Staff');
    }

    public function test_the_index_page_lists_logged_trips(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $asset = $this->makeAsset($company);
        MachineryTripLog::create(['company_id' => $company->id, 'machinery_asset_id' => $asset->id, 'trip_date' => now(), 'purpose' => 'Site transfer']);

        $response = $this->actingAs($owner)->get(route('app.machinery.trips.index'));

        $response->assertOk();
        $response->assertSee('Site transfer');
    }
}
