<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractWork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "the last voucher 6000 is against same project but different work
 * like MC1 and RC2 spraying... we have an option to add subproject
 * option or work for main project and assign voucher accordingly" —
 * a Work is a named slice of a budget (no percentage/handover schedule,
 * unlike a milestone) that a payment can be tagged to.
 */
class ProjectManagerBudgetWorkTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeBudget(Company $company): Subcontract
    {
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);

        return Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650, 'retention_percent' => 0, 'status' => 'active',
        ]);
    }

    public function test_a_work_can_be_added_updated_and_removed(): void
    {
        [$company, $owner] = $this->makeOwner();
        $budget = $this->makeBudget($company);

        $this->actingAs($owner)->post(route('app.project-manager-budgets.works.store', $budget), [
            'name' => 'MC1', 'budget_amount' => 10000,
        ])->assertRedirect();

        $work = SubcontractWork::first();
        $this->assertSame('MC1', $work->name);
        $this->assertEquals(10000.0, (float) $work->budget_amount);

        $this->actingAs($owner)->put(route('app.project-manager-budgets.works.update', [$budget, $work]), [
            'name' => 'MC1 Spraying', 'budget_amount' => 12000,
        ])->assertRedirect();
        $this->assertSame('MC1 Spraying', $work->fresh()->name);

        $this->actingAs($owner)->delete(route('app.project-manager-budgets.works.destroy', [$budget, $work]))->assertRedirect();
        $this->assertDatabaseMissing('subcontract_works', ['id' => $work->id]);
    }

    public function test_a_voucher_can_be_linked_and_tagged_to_a_specific_work(): void
    {
        [$company, $owner] = $this->makeOwner();
        $budget = $this->makeBudget($company);
        $work = $budget->works()->create(['name' => 'RC2 Spraying', 'sort_order' => 0]);

        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'type' => 'bank', 'is_active' => true]);
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id, 'party_type' => 'employee', 'employee_id' => $budget->employee_id, 'project_id' => $budget->project_id,
            'voucher_number' => 'PAY-00001', 'date' => now(), 'payee_name' => 'Shahid', 'amount' => 6000,
            'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $this->actingAs($owner)->post(route('app.project-manager-budgets.link-voucher', $budget), [
            'payment_voucher_id' => [$voucher->id], 'work_id' => $work->id,
        ])->assertRedirect();

        $payment = $budget->payments()->first();
        $this->assertSame($work->id, $payment->work_id);
        $this->assertEquals(6000.0, $work->paidAmount());
    }

    public function test_a_manual_payment_can_be_tagged_to_a_work(): void
    {
        [$company, $owner] = $this->makeOwner();
        $budget = $this->makeBudget($company);
        $work = $budget->works()->create(['name' => 'MC1', 'budget_amount' => 15000, 'sort_order' => 0]);

        $this->actingAs($owner)->post(route('app.project-manager-budgets.payments.store', $budget), [
            'paid_at' => now()->toDateString(), 'amount' => 4000, 'work_id' => $work->id,
        ])->assertRedirect();

        $payment = $budget->payments()->first();
        $this->assertSame($work->id, $payment->work_id);
        $this->assertEquals(4000.0, $work->paidAmount());
        $this->assertEquals(11000.0, $work->balance());
    }

    public function test_a_works_work_id_can_be_changed_via_the_inline_update_form_without_clearing_other_fields(): void
    {
        [$company, $owner] = $this->makeOwner();
        $budget = $this->makeBudget($company);
        $work = $budget->works()->create(['name' => 'MC1', 'sort_order' => 0]);
        $payment = $budget->payments()->create(['company_id' => $company->id, 'amount' => 5000, 'paid_at' => now(), 'reference' => 'TRF-9', 'method' => 'cash']);

        $this->actingAs($owner)->put(route('app.project-manager-budgets.payments.update', [$budget, $payment]), [
            'amount' => $payment->amount, 'paid_at' => $payment->paid_at->toDateString(),
            'reference' => $payment->reference, 'method' => $payment->method, 'work_id' => $work->id,
        ])->assertRedirect();

        $payment->refresh();
        $this->assertSame($work->id, $payment->work_id);
        $this->assertSame('TRF-9', $payment->reference, 'Setting work_id via the inline form must not clear the payment\'s other fields.');
        $this->assertSame('cash', $payment->method);
    }

    public function test_removing_a_work_leaves_its_payments_untouched_but_untagged(): void
    {
        [$company, $owner] = $this->makeOwner();
        $budget = $this->makeBudget($company);
        $work = $budget->works()->create(['name' => 'MC1', 'sort_order' => 0]);
        $payment = $budget->payments()->create(['company_id' => $company->id, 'amount' => 5000, 'paid_at' => now(), 'work_id' => $work->id]);

        $this->actingAs($owner)->delete(route('app.project-manager-budgets.works.destroy', [$budget, $work]));

        $this->assertNotNull($payment->fresh(), 'The payment itself must survive removing the work it was tagged to.');
        $this->assertNull($payment->fresh()->work_id);
    }

    public function test_the_show_page_renders_with_and_without_any_work_defined(): void
    {
        [$company, $owner] = $this->makeOwner();
        $budget = $this->makeBudget($company);

        $this->actingAs($owner)->get(route('app.project-manager-budgets.show', $budget))->assertOk();

        $budget->works()->create(['name' => 'MC1', 'budget_amount' => 10000, 'sort_order' => 0]);

        $this->actingAs($owner)->get(route('app.project-manager-budgets.show', $budget))
            ->assertOk()->assertSee('MC1');
    }
}
