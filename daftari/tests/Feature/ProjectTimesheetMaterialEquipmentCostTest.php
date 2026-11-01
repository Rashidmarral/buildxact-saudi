<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\MachineryAsset;
use App\Models\MachineryProjectDeployment;
use App\Models\Project;
use App\Models\ProjectMaterialIssue;
use App\Models\ProjectTimesheet;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "first build the project module same as it is as the hamly erp with
 * complete details as they have with proper functionality" — Phase 2:
 * Timesheets (labour cost, posted to the project cost ledger on
 * approval), Materials (real stock deduction + materials cost), and
 * Equipment (user-confirmed: machinery deployment now posts a real
 * cost once it ends, replacing the previous notional-only behavior).
 */
class ProjectTimesheetMaterialEquipmentCostTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeProject(Company $company): Project
    {
        return Project::create(['company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active']);
    }

    private function makeEmployee(Company $company): Employee
    {
        return Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-'.uniqid(), 'full_name' => 'Khalid', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active']);
    }

    public function test_a_timesheet_entry_only_posts_cost_once_approved(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $employee = $this->makeEmployee($company);

        $this->actingAs($owner)->post(route('app.projects.timesheets.store', $project), [
            'employee_id' => $employee->id, 'work_date' => now()->toDateString(),
            'hours' => 8, 'overtime_hours' => 2, 'cost_rate' => 20, 'bill_rate' => 35,
        ])->assertSessionDoesntHaveErrors();

        $timesheet = ProjectTimesheet::first();
        $this->assertSame('draft', $timesheet->status);
        $this->assertSame(0.0, $project->costs());

        $this->actingAs($owner)->post(route('app.projects.timesheets.approve', [$project, $timesheet]))
            ->assertSessionDoesntHaveErrors();

        $timesheet->refresh();
        $this->assertSame('approved', $timesheet->status);
        $this->assertNotNull($timesheet->project_cost_id);
        $this->assertSame(200.0, $timesheet->costAmount());
        $this->assertSame(350.0, $timesheet->billAmount());
        $this->assertSame(200.0, $project->costs());
    }

    public function test_removing_an_approved_timesheet_also_removes_its_posted_cost(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $employee = $this->makeEmployee($company);
        $timesheet = $project->timesheets()->create(['employee_id' => $employee->id, 'work_date' => now(), 'hours' => 8, 'cost_rate' => 20, 'billable' => true, 'status' => 'draft']);
        $timesheet->postCost();

        $this->actingAs($owner)->delete(route('app.projects.timesheets.destroy', [$project, $timesheet]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('project_timesheets', ['id' => $timesheet->id]);
        $this->assertSame(0.0, $project->costs());
    }

    public function test_issuing_material_deducts_stock_and_posts_a_materials_cost(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Cement bags', 'is_active' => true, 'purchase_price' => 20]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main yard']);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 100]);

        $this->actingAs($owner)->post(route('app.projects.material-issues.store', $project), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 30, 'issued_at' => now()->toDateString(),
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('item_stocks', ['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 70]);
        $issue = ProjectMaterialIssue::first();
        $this->assertSame(600.0, (float) $issue->total);
        $this->assertSame(600.0, $project->costs());
    }

    public function test_removing_a_material_issue_restores_stock_and_removes_its_cost(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Cement bags', 'is_active' => true, 'purchase_price' => 20]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main yard']);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 100]);
        $this->actingAs($owner)->post(route('app.projects.material-issues.store', $project), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 30, 'issued_at' => now()->toDateString(),
        ]);
        $issue = ProjectMaterialIssue::first();

        $this->actingAs($owner)->delete(route('app.projects.material-issues.destroy', [$project, $issue]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('item_stocks', ['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 100]);
        $this->assertSame(0.0, $project->costs());
    }

    public function test_an_active_machinery_deployment_posts_no_cost_until_it_ends(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $machine = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-1', 'name' => 'Roller', 'status' => 'available']);

        $deployment = MachineryProjectDeployment::create([
            'company_id' => $company->id, 'machinery_asset_id' => $machine->id, 'project_id' => $project->id,
            'start_date' => now()->subDays(4)->toDateString(), 'internal_daily_rate' => 500,
            'status' => 'active', 'created_by' => $owner->id,
        ]);
        $deployment->postCost();

        $this->assertNull($deployment->fresh()->project_cost_id);
        $this->assertSame(0.0, $project->costs());
    }

    public function test_ending_a_machinery_deployment_posts_its_rate_times_days_as_equipment_cost(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $machine = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-1', 'name' => 'Roller', 'status' => 'deployed']);
        $deployment = MachineryProjectDeployment::create([
            'company_id' => $company->id, 'machinery_asset_id' => $machine->id, 'project_id' => $project->id,
            'start_date' => now()->subDays(4)->toDateString(), 'internal_daily_rate' => 500,
            'status' => 'active', 'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('app.machinery.deployments.end', $deployment), ['end_date' => now()->toDateString()])
            ->assertSessionDoesntHaveErrors();

        $deployment->refresh();
        $this->assertNotNull($deployment->project_cost_id);
        $this->assertSame(2500.0, $deployment->notionalCost());
        $this->assertSame(2500.0, $project->costs());
        $this->assertDatabaseHas('project_costs', ['id' => $deployment->project_cost_id, 'cost_head' => 'equipment', 'amount' => 2500]);
    }

    public function test_a_deployment_with_no_daily_rate_posts_nothing(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $machine = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-1', 'name' => 'Roller', 'status' => 'deployed']);
        $deployment = MachineryProjectDeployment::create([
            'company_id' => $company->id, 'machinery_asset_id' => $machine->id, 'project_id' => $project->id,
            'start_date' => now()->subDays(4)->toDateString(), 'status' => 'active', 'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('app.machinery.deployments.end', $deployment), ['end_date' => now()->toDateString()]);

        $this->assertNull($deployment->fresh()->project_cost_id);
        $this->assertSame(0.0, $project->costs());
    }

    public function test_a_company_cannot_touch_another_companys_timesheets_or_materials(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $projectB = $this->makeProject($companyB);
        $employeeB = $this->makeEmployee($companyB);
        $timesheetB = $projectB->timesheets()->create(['employee_id' => $employeeB->id, 'work_date' => now(), 'hours' => 8, 'status' => 'draft']);

        $this->actingAs($ownerA)->post(route('app.projects.timesheets.approve', [$projectB, $timesheetB]))->assertNotFound();
        $this->actingAs($ownerA)->delete(route('app.projects.timesheets.destroy', [$projectB, $timesheetB]))->assertNotFound();
    }
}
