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
 * "we have to make shahid as a project manager not a supplier... assign
 * employee a project and then select it on vouchers as well" — the same
 * budget/milestone/BOQ/category-breakdown tracking a Subcontract already
 * gives a Supplier now also works for an Employee acting as a project's
 * managing party (cash advanced to them, then spent against cost
 * categories like base course/asphalt), without touching how a
 * supplier-based subcontract behaves.
 */
class SubcontractEmployeePartyTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_a_subcontract_can_be_created_with_an_employee_as_its_party(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);

        $response = $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 180000, 'retention_percent' => 5, 'status' => 'active',
            'milestones' => [['name' => 'Full', 'percentage' => 100, 'requires_handover_approval' => false]],
        ]);

        $subcontract = Subcontract::first();
        $response->assertRedirect(route('app.subcontracts.show', $subcontract));

        $this->assertSame('employee', $subcontract->party_type);
        $this->assertSame($employee->id, $subcontract->employee_id);
        $this->assertNull($subcontract->supplier_id);
        $this->assertSame('Shahid', $subcontract->partyName());
        $this->assertTrue($subcontract->isEmployeeParty());
    }

    public function test_switching_party_type_on_update_nulls_the_other_partys_id(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'contract_value' => 180000, 'retention_percent' => 5, 'status' => 'active',
        ]);

        $response = $this->actingAs($owner)->put(route('app.subcontracts.update', $subcontract), [
            'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 180000, 'retention_percent' => 5, 'status' => 'active',
            'milestones' => [['name' => 'Full', 'percentage' => 100, 'requires_handover_approval' => false]],
        ]);

        $response->assertRedirect(route('app.subcontracts.show', $subcontract));
        $subcontract->refresh();
        $this->assertSame('employee', $subcontract->party_type);
        $this->assertSame($employee->id, $subcontract->employee_id);
        $this->assertNull($subcontract->supplier_id);
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

        $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 180800, 'retention_percent' => 5, 'status' => 'active',
            'milestones' => [['name' => 'Full', 'percentage' => 100, 'requires_handover_approval' => false]],
        ]);
        $subcontract = Subcontract::first();

        $response = $this->actingAs($owner)->post(route('app.subcontracts.link-voucher', $subcontract), ['payment_voucher_id' => $voucher->id]);
        $response->assertRedirect();

        $this->assertDatabaseCount('subcontract_payments', 1);
        $this->assertEquals(73000.0, $subcontract->totalPaid());
    }

    public function test_a_subcontract_without_party_type_still_defaults_to_supplier(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);

        $response = $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'contract_value' => 180000, 'retention_percent' => 5, 'status' => 'active',
            'milestones' => [['name' => 'Full', 'percentage' => 100, 'requires_handover_approval' => false]],
        ]);

        $subcontract = Subcontract::first();
        $response->assertRedirect(route('app.subcontracts.show', $subcontract));
        $this->assertSame('supplier', $subcontract->party_type);
        $this->assertSame($supplier->id, $subcontract->supplier_id);
    }
}
