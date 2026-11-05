<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectBoqItem;
use App\Models\ProjectBudgetLine;
use App\Models\ProjectCost;
use App\Models\ProjectTask;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "first build the project module same as it is as the hamly erp with
 * complete details as they have with proper functionality" — Phase 1:
 * BOQ (the itemized scope progress billing will calculate against),
 * Budget lines (budgeted vs actual per cost head), the project's own
 * Cost ledger (manual/equipment/labour/material costs — see the
 * project_costs migration's docblock for why this is separate from
 * Expense/Bill), and Tasks.
 */
class ProjectBoqBudgetCostTasksTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeProject(Company $company): Project
    {
        return Project::create(['company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active']);
    }

    public function test_a_boq_item_can_be_added_and_its_amount_computed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $unit = Unit::create(['company_id' => $company->id, 'name' => 'Square meter', 'symbol' => 'm2']);

        $this->actingAs($owner)->post(route('app.projects.boq-items.store', $project), [
            'item_no' => '1.1', 'description' => 'Asphalt resurfacing', 'unit_id' => $unit->id,
            'quantity' => 1000, 'rate' => 50, 'cost_rate' => 35,
        ])->assertSessionDoesntHaveErrors();

        $item = ProjectBoqItem::first();
        $this->assertSame(50000.0, $item->amount());
        $this->assertSame(35000.0, $item->costAmount());
        $this->assertSame(0.0, $item->percentComplete());
    }

    public function test_updating_executed_quantity_tracks_percent_complete(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $item = $project->boqItems()->create(['description' => 'Asphalt', 'quantity' => 1000, 'rate' => 50]);

        $this->actingAs($owner)->put(route('app.projects.boq-items.update', [$project, $item]), [
            'executed_quantity' => 400, 'billed_quantity' => 200,
        ])->assertSessionDoesntHaveErrors();

        $item->refresh();
        $this->assertSame(40.0, $item->percentComplete());
        $this->assertSame(20000.0, $item->executedAmount());
        $this->assertSame(10000.0, $item->billedAmount());
        $this->assertSame(10000.0, $item->remainingToBill());
    }

    public function test_a_budget_line_can_be_added_and_approved(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);

        $this->actingAs($owner)->post(route('app.projects.budget-lines.store', $project), [
            'cost_head' => 'materials', 'description' => 'Asphalt & aggregate', 'budgeted_amount' => 100000,
        ])->assertSessionDoesntHaveErrors();

        $line = ProjectBudgetLine::first();
        $this->assertSame('draft', $line->status);

        $this->actingAs($owner)->post(route('app.projects.budget-lines.approve', [$project, $line]))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('approved', $line->fresh()->status);
    }

    public function test_a_budget_lines_actual_amount_only_counts_matching_cost_head_project_costs(): void
    {
        $company = $this->makeCompany();
        $project = $this->makeProject($company);
        $line = $project->budgetLines()->create(['cost_head' => 'materials', 'budgeted_amount' => 10000, 'status' => 'draft']);
        $project->projectCosts()->create(['cost_head' => 'materials', 'cost_date' => now(), 'description' => 'Cement', 'amount' => 3000]);
        $project->projectCosts()->create(['cost_head' => 'labour', 'cost_date' => now(), 'description' => 'Crew wages', 'amount' => 5000]);

        $this->assertSame(3000.0, $line->actualAmount());
    }

    public function test_a_manual_cost_can_be_added_and_counts_toward_project_costs(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);

        $this->actingAs($owner)->post(route('app.projects.costs.store', $project), [
            'cost_head' => 'equipment', 'cost_date' => now()->toDateString(), 'description' => 'Roller rental', 'amount' => 2500,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('project_costs', ['project_id' => $project->id, 'amount' => 2500, 'billable' => 1]);
        $this->assertSame(2500.0, $project->costs());
    }

    public function test_a_cost_can_be_removed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $cost = $project->projectCosts()->create(['cost_head' => 'other', 'cost_date' => now(), 'description' => 'Misc', 'amount' => 100]);

        $this->actingAs($owner)->delete(route('app.projects.costs.destroy', [$project, $cost]))
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('project_costs', ['id' => $cost->id]);
    }

    public function test_a_task_can_be_added_assigned_and_completed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-1', 'full_name' => 'Khalid', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active']);

        $this->actingAs($owner)->post(route('app.projects.tasks.store', $project), [
            'title' => 'Mobilize crew', 'assignee_employee_id' => $employee->id, 'due_date' => now()->addWeek()->toDateString(),
        ])->assertSessionDoesntHaveErrors();

        $task = ProjectTask::first();
        $this->assertSame('open', $task->status);
        $this->assertNull($task->completed_at);

        $this->actingAs($owner)->put(route('app.projects.tasks.update', [$project, $task]), ['status' => 'completed'])
            ->assertSessionDoesntHaveErrors();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_a_company_cannot_touch_another_companys_project_sub_resources(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $projectB = $this->makeProject($companyB);
        $boqItemB = $projectB->boqItems()->create(['description' => 'X', 'quantity' => 1, 'rate' => 1]);
        $taskB = $projectB->tasks()->create(['title' => 'X', 'status' => 'open']);
        $costB = $projectB->projectCosts()->create(['cost_head' => 'other', 'cost_date' => now(), 'description' => 'X', 'amount' => 1]);

        $this->actingAs($ownerA)->delete(route('app.projects.boq-items.destroy', [$projectB, $boqItemB]))->assertNotFound();
        $this->actingAs($ownerA)->delete(route('app.projects.tasks.destroy', [$projectB, $taskB]))->assertNotFound();
        $this->actingAs($ownerA)->delete(route('app.projects.costs.destroy', [$projectB, $costB]))->assertNotFound();
    }

    public function test_the_show_page_renders_all_new_tabs(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $project->boqItems()->create(['description' => 'Asphalt', 'quantity' => 100, 'rate' => 50]);
        $project->budgetLines()->create(['cost_head' => 'materials', 'budgeted_amount' => 5000, 'status' => 'draft']);
        $project->projectCosts()->create(['cost_head' => 'equipment', 'cost_date' => now(), 'description' => 'Roller rental', 'amount' => 2500]);
        $project->tasks()->create(['title' => 'Mobilize crew', 'status' => 'open']);

        $response = $this->actingAs($owner)->get(route('app.projects.show', $project));

        $response->assertOk()
            ->assertSee('Asphalt')
            ->assertSee('Roller rental')
            ->assertSee('Mobilize crew');
    }
}
