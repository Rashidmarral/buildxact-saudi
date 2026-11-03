<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "we need to separate project manager from subcontractor is other
 * thing... make it completely separate from subcontractor like add
 * nav link or something in project module" — cash advanced to an
 * employee/project manager (Shahid's situation: a 373,650 budget, 167,800
 * advanced so far, spent against cost categories like Sub Base/Asphalt)
 * gets the same budget/BOQ/category-breakdown/voucher-linking tracking a
 * Subcontract gives a Supplier (ProjectManagerBudgetController extends
 * SubcontractController), but is a genuinely separate module: its own
 * URL prefix/route names/views, and it can never be created against a
 * Supplier — the Subcontracts module can likewise never be created
 * against an Employee, and neither module's URLs can open the other's
 * records.
 */
class ProjectManagerBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_a_budget_can_be_created_for_an_employee_project_manager(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);

        $response = $this->actingAs($owner)->post(route('app.project-manager-budgets.store'), [
            'project_id' => $project->id, 'employee_id' => $employee->id,
            'contract_value' => 373650, 'retention_percent' => 0, 'status' => 'active',
            'boq_items' => [
                ['description' => 'Sub base', 'quantity' => 11200, 'unit' => 'm2', 'rate' => 6, 'amount' => 67200],
                ['description' => 'Base Course', 'quantity' => 11200, 'unit' => 'm2', 'rate' => 14, 'amount' => 156800],
                ['description' => 'Asphalt', 'quantity' => 4100, 'unit' => 'm2', 'rate' => 36.5, 'amount' => 149650],
            ],
        ]);

        $subcontract = Subcontract::first();
        $response->assertRedirect(route('app.project-manager-budgets.show', $subcontract));

        $this->assertSame('employee', $subcontract->party_type);
        $this->assertSame($employee->id, $subcontract->employee_id);
        $this->assertNull($subcontract->supplier_id);
        $this->assertSame('Shahid', $subcontract->partyName());
        $this->assertTrue($subcontract->isEmployeeParty());
        $this->assertCount(3, $subcontract->boqItems);
        $this->assertCount(0, $subcontract->milestones, 'A project manager budget has no contractual milestone schedule — unlike a Subcontract, creating one must not fall back to the default 5-stage milestone schedule.');
    }

    public function test_a_posted_supplier_party_type_is_ignored_and_forced_to_employee(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);

        $this->actingAs($owner)->post(route('app.project-manager-budgets.store'), [
            'project_id' => $project->id, 'employee_id' => $employee->id, 'supplier_id' => $supplier->id, 'party_type' => 'supplier',
            'contract_value' => 100000, 'retention_percent' => 0, 'status' => 'active',
        ]);

        $subcontract = Subcontract::first();
        $this->assertSame('employee', $subcontract->party_type, 'ProjectManagerBudgetController must force party_type=employee regardless of what a request posts.');
        $this->assertNull($subcontract->supplier_id);
    }

    public function test_a_posted_employee_party_type_on_the_subcontracts_route_is_ignored_and_forced_to_supplier(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);

        $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'supplier_id' => $supplier->id, 'employee_id' => $employee->id, 'party_type' => 'employee',
            'contract_value' => 100000, 'retention_percent' => 5, 'status' => 'active',
            'milestones' => [['name' => 'Full', 'percentage' => 100, 'requires_handover_approval' => false]],
        ]);

        $subcontract = Subcontract::first();
        $this->assertSame('supplier', $subcontract->party_type, 'SubcontractController must force party_type=supplier regardless of what a request posts.');
        $this->assertNull($subcontract->employee_id);
    }

    public function test_an_employee_budget_cannot_be_opened_or_edited_through_the_subcontracts_url(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        $budget = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 180000, 'retention_percent' => 0, 'status' => 'active',
        ]);

        $this->actingAs($owner)->get(route('app.subcontracts.show', $budget))->assertNotFound();
        $this->actingAs($owner)->get(route('app.subcontracts.edit', $budget))->assertNotFound();
    }

    public function test_a_supplier_subcontract_cannot_be_opened_or_edited_through_the_project_manager_budgets_url(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'contract_value' => 180000, 'retention_percent' => 5, 'status' => 'active',
        ]);

        $this->actingAs($owner)->get(route('app.project-manager-budgets.show', $subcontract))->assertNotFound();
        $this->actingAs($owner)->get(route('app.project-manager-budgets.edit', $subcontract))->assertNotFound();
    }

    public function test_the_index_pages_only_list_their_own_party_type(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        Subcontract::create(['company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id, 'contract_value' => 1, 'retention_percent' => 5, 'status' => 'active']);
        Subcontract::create(['company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id, 'contract_value' => 1, 'retention_percent' => 0, 'status' => 'active']);

        $this->actingAs($owner)->get(route('app.subcontracts.index'))
            ->assertOk()->assertSee('Al Fahad Subcontracting')->assertDontSee('Shahid');

        $this->actingAs($owner)->get(route('app.project-manager-budgets.index'))
            ->assertOk()->assertSee('Shahid')->assertDontSee('Al Fahad Subcontracting');
    }

    public function test_an_employee_partys_voucher_can_be_linked_the_same_way_a_suppliers_can(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'type' => 'bank', 'is_active' => true]);
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id, 'party_type' => 'employee', 'employee_id' => $employee->id, 'project_id' => $project->id,
            'voucher_number' => 'PAY-00001', 'date' => now(), 'payee_name' => $employee->full_name, 'amount' => 73000,
            'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $this->actingAs($owner)->post(route('app.project-manager-budgets.store'), [
            'project_id' => $project->id, 'employee_id' => $employee->id,
            'contract_value' => 373650, 'retention_percent' => 0, 'status' => 'active',
        ]);
        $subcontract = Subcontract::first();

        $response = $this->actingAs($owner)->post(route('app.project-manager-budgets.link-voucher', $subcontract), ['payment_voucher_id' => [$voucher->id]]);
        $response->assertRedirect();

        $this->assertDatabaseCount('subcontract_payments', 1);
        $this->assertEquals(73000.0, $subcontract->totalPaid());
    }

    public function test_the_create_edit_and_show_pages_render(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650, 'retention_percent' => 0, 'status' => 'active',
        ]);
        $subcontract->boqItems()->create(['description' => 'Sub base', 'quantity' => 11200, 'unit' => 'm2', 'rate' => 6, 'amount' => 67200, 'sort_order' => 0]);

        $this->actingAs($owner)->get(route('app.project-manager-budgets.create'))->assertOk()->assertSee(__('Project manager'));
        $this->actingAs($owner)->get(route('app.project-manager-budgets.edit', $subcontract))->assertOk()->assertSee('Shahid');
        $this->actingAs($owner)->get(route('app.project-manager-budgets.show', $subcontract))->assertOk()->assertSee('Shahid')->assertSee('Sub base');
    }

    public function test_updating_a_budget_does_not_touch_milestones_even_if_some_already_exist(): void
    {
        // Simulates Shahid's real existing subcontract record, which
        // already has a default 5-stage milestone schedule from before
        // this module existed — editing it through the Project Manager
        // Budgets form (no milestone editor) must leave that schedule
        // (and any payments already assigned to it) untouched.
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650, 'retention_percent' => 0, 'status' => 'active',
        ]);
        $subcontract->milestones()->create(['sequence' => 1, 'name' => 'Advance', 'percentage' => 20, 'amount' => 74730]);

        $this->actingAs($owner)->put(route('app.project-manager-budgets.update', $subcontract), [
            'project_id' => $project->id, 'employee_id' => $employee->id,
            'contract_value' => 373650, 'status' => 'active',
        ]);

        $this->assertCount(1, $subcontract->milestones()->get(), 'Updating via the Project Manager Budgets form must not delete or reset existing milestones.');
    }
}
