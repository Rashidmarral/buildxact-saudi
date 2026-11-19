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
 * "i am unable to select vouchers that is not assign to project
 * manager... i can select any voucher if that voucher assign or not
 * assign to project manager" — linkVoucher()/the Payments > Link
 * existing Payment Voucher list used to require a voucher's own
 * supplier_id/employee_id to already match this specific subcontract's
 * party before it was even selectable — real vouchers are routinely
 * issued under a different payee and only later turn out to really be
 * this budget's own spend. Both the list and the link action now only
 * require the voucher to be issued, unlinked, and on the same project —
 * not tied to a matching party_type/supplier_id/employee_id.
 */
class SubcontractCrossPartyVoucherLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function bank(Company $company): BankAccount
    {
        return BankAccount::firstOrCreate(['company_id' => $company->id, 'name' => 'Main Account'], ['type' => 'bank', 'is_active' => true]);
    }

    public function test_a_voucher_issued_to_a_different_supplier_shows_up_for_and_can_be_linked_to_a_project_manager_budget(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $otherSupplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Jazeera Trading']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-1', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 500]);
        $budget = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650, 'retention_percent' => 5, 'status' => 'active',
        ]);
        // Issued under a completely different supplier, not Shahid.
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $this->bank($company)->id, 'supplier_id' => $otherSupplier->id, 'project_id' => $project->id,
            'voucher_number' => 'PV-1', 'date' => now(), 'payee_name' => 'Al Jazeera Trading', 'amount' => 25000,
            'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $show = $this->actingAs($owner)->get(route('app.project-manager-budgets.show', $budget));
        $show->assertOk()->assertSee('PV-1');

        $response = $this->actingAs($owner)->post(route('app.project-manager-budgets.link-voucher', $budget), [
            'payment_voucher_id' => [$voucher->id],
        ]);

        $response->assertRedirect();
        $this->assertEquals(25000.0, $budget->fresh()->totalPaid());
        $this->assertDatabaseHas('subcontract_payments', ['subcontract_id' => $budget->id, 'payment_voucher_id' => $voucher->id]);
    }

    public function test_a_voucher_issued_to_an_employee_shows_up_for_and_can_be_linked_to_a_subcontractor_record(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $otherEmployee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-1', 'full_name' => 'Khalid', 'hire_date' => now(), 'basic_salary' => 500]);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'contract_value' => 373650, 'retention_percent' => 5, 'status' => 'active',
        ]);
        $subcontract->milestones()->create(['sequence' => 1, 'name' => 'Full', 'percentage' => 100, 'amount' => 373650]);
        // Issued under an employee, not SAED EST.
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $this->bank($company)->id, 'party_type' => 'employee', 'employee_id' => $otherEmployee->id, 'project_id' => $project->id,
            'voucher_number' => 'PV-2', 'date' => now(), 'payee_name' => 'Khalid', 'amount' => 5000,
            'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $show = $this->actingAs($owner)->get(route('app.subcontracts.show', $subcontract));
        $show->assertOk()->assertSee('PV-2');

        $response = $this->actingAs($owner)->post(route('app.subcontracts.link-voucher', $subcontract), [
            'payment_voucher_id' => [$voucher->id],
        ]);

        $response->assertRedirect();
        $this->assertEquals(5000.0, $subcontract->fresh()->totalPaid());
    }

    public function test_a_voucher_already_linked_elsewhere_is_not_offered_again(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplierA = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier A']);
        $supplierB = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier B']);
        $subA = Subcontract::create(['company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'supplier', 'supplier_id' => $supplierA->id, 'contract_value' => 100000, 'retention_percent' => 5, 'status' => 'active']);
        $subB = Subcontract::create(['company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'supplier', 'supplier_id' => $supplierB->id, 'contract_value' => 100000, 'retention_percent' => 5, 'status' => 'active']);
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $this->bank($company)->id, 'supplier_id' => $supplierA->id, 'project_id' => $project->id,
            'voucher_number' => 'PV-3', 'date' => now(), 'payee_name' => 'Supplier A', 'amount' => 1000,
            'method' => 'bank_transfer', 'status' => 'issued',
        ]);
        $subA->payments()->create(['company_id' => $company->id, 'payment_voucher_id' => $voucher->id, 'amount' => 1000, 'paid_at' => now()]);

        $show = $this->actingAs($owner)->get(route('app.subcontracts.show', $subB));

        $show->assertOk()->assertDontSee('PV-3');
    }
}
