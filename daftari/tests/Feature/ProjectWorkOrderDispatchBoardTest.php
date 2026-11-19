<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\MachineryAsset;
use App\Models\Project;
use App\Models\ProjectWorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "first build the project module same as it is as the hamly erp with
 * complete details as they have with proper functionality" — Phase 6:
 * Work Orders (status auto-advances open -> assigned the moment a
 * technician is set) and the Dispatch Board (a cross-project weekly
 * Technician x Day schedule, unlike every other piece of this module
 * which is scoped to one project).
 */
class ProjectWorkOrderDispatchBoardTest extends TestCase
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

    private function makeEmployee(Company $company, string $name = 'Khalid'): Employee
    {
        return Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-'.uniqid(), 'full_name' => $name, 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active']);
    }

    public function test_a_work_order_is_numbered_and_stays_open_without_a_technician(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);

        $this->actingAs($owner)->post(route('app.projects.work-orders.store', $project), [
            'kind' => 'breakdown', 'priority' => 'high', 'title' => 'Roller hydraulic leak', 'raised_date' => now()->toDateString(),
        ])->assertSessionDoesntHaveErrors();

        $workOrder = ProjectWorkOrder::first();
        $this->assertSame('WO-00001', $workOrder->work_order_number);
        $this->assertSame('open', $workOrder->status);
    }

    public function test_assigning_a_technician_on_creation_auto_advances_status_to_assigned(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $technician = $this->makeEmployee($company);

        $this->actingAs($owner)->post(route('app.projects.work-orders.store', $project), [
            'kind' => 'breakdown', 'priority' => 'high', 'title' => 'Roller hydraulic leak',
            'raised_date' => now()->toDateString(), 'technician_employee_id' => $technician->id,
        ])->assertSessionDoesntHaveErrors();

        $workOrder = ProjectWorkOrder::first();
        $this->assertSame('assigned', $workOrder->status);
    }

    public function test_assigning_a_technician_to_an_open_work_order_via_update_also_advances_status(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $technician = $this->makeEmployee($company);
        $workOrder = $project->workOrders()->create([
            'company_id' => $company->id, 'work_order_number' => 'WO-00001', 'kind' => 'breakdown',
            'priority' => 'high', 'title' => 'Roller hydraulic leak', 'raised_date' => now(), 'status' => 'open',
        ]);

        $this->actingAs($owner)->put(route('app.projects.work-orders.update', [$project, $workOrder]), [
            'technician_employee_id' => $technician->id, 'status' => 'open',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('assigned', $workOrder->fresh()->status);
    }

    public function test_a_work_order_can_reference_a_machinery_asset(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $machine = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-1', 'name' => 'Roller', 'status' => 'available']);

        $this->actingAs($owner)->post(route('app.projects.work-orders.store', $project), [
            'kind' => 'breakdown', 'priority' => 'urgent', 'title' => 'Hydraulic leak',
            'raised_date' => now()->toDateString(), 'machinery_asset_id' => $machine->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('project_work_orders', ['machinery_asset_id' => $machine->id]);
    }

    public function test_the_dispatch_board_groups_scheduled_work_orders_by_technician_and_day(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $technician = $this->makeEmployee($company, 'Khalid');
        $monday = now()->startOfWeek(\Carbon\Carbon::MONDAY);
        $project->workOrders()->create([
            'company_id' => $company->id, 'work_order_number' => 'WO-00001', 'kind' => 'breakdown', 'priority' => 'high',
            'title' => 'Roller hydraulic leak', 'raised_date' => $monday, 'scheduled_at' => $monday->copy()->setTime(9, 0),
            'technician_employee_id' => $technician->id, 'status' => 'assigned',
        ]);

        $response = $this->actingAs($owner)->get(route('app.dispatch-board.index'));

        $response->assertOk()->assertSee('Khalid')->assertSee('Roller hydraulic leak');
    }

    public function test_the_dispatch_board_flags_unassigned_work_orders_separately(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $project->workOrders()->create([
            'company_id' => $company->id, 'work_order_number' => 'WO-00001', 'kind' => 'breakdown', 'priority' => 'medium',
            'title' => 'Unassigned job', 'raised_date' => now(), 'status' => 'open',
        ]);

        $response = $this->actingAs($owner)->get(route('app.dispatch-board.index'));

        $response->assertOk()->assertSee('Unassigned job')->assertSee('Unassigned work orders');
    }

    public function test_a_company_cannot_touch_another_companys_work_orders(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $projectB = $this->makeProject($companyB);
        $workOrderB = $projectB->workOrders()->create([
            'company_id' => $companyB->id, 'work_order_number' => 'WO-00001', 'kind' => 'breakdown',
            'priority' => 'high', 'title' => 'X', 'raised_date' => now(), 'status' => 'open',
        ]);

        $this->actingAs($ownerA)->delete(route('app.projects.work-orders.destroy', [$projectB, $workOrderB]))->assertNotFound();
    }
}
