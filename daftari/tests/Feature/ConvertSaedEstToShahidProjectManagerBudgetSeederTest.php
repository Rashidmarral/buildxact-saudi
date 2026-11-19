<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use Database\Seeders\ConvertSaedEstToShahidProjectManagerBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "The PM budget should replace the subcontractor record" — converts
 * the real SAED EST Subcontract (party_type='supplier') in place into
 * Shahid's Project Manager Budget (party_type='employee'), preserving
 * every related row (milestones, payments, BOQ, report categories)
 * since nothing is copied or recreated — only party_type/employee_id/
 * supplier_id change on the same row.
 */
class ConvertSaedEstToShahidProjectManagerBudgetSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedRealSaedEstSubcontract(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ_00001', 'name' => 'مشروع ساحة الجموم', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST', 'cr_number' => '4031259996']);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'contract_value' => 373650.00, 'retention_percent' => 5, 'status' => 'active',
        ]);
        $subcontract->milestones()->create(['sequence' => 1, 'name' => 'Advance', 'percentage' => 20, 'amount' => 74730.00]);
        $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 161860.00, 'paid_at' => now(), 'milestone_id' => $subcontract->milestones->first()->id]);
        $subcontract->reportCategories()->create(['label' => 'Shahid', 'display_mode' => 'itemized', 'match_terms' => 'shahid']);

        return [$company, $project, $supplier, $subcontract];
    }

    public function test_it_errors_cleanly_when_no_matching_subcontract_exists(): void
    {
        Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);

        (new ConvertSaedEstToShahidProjectManagerBudgetSeeder)->run();

        $this->assertSame(0, Employee::count());
    }

    public function test_it_converts_the_subcontract_in_place_preserving_every_related_row(): void
    {
        [$company, $project, $supplier, $subcontract] = $this->seedRealSaedEstSubcontract();

        (new ConvertSaedEstToShahidProjectManagerBudgetSeeder)->run();

        $subcontract->refresh();
        $this->assertSame('employee', $subcontract->party_type);
        $this->assertNull($subcontract->supplier_id);
        $this->assertNotNull($subcontract->employee_id);
        $this->assertSame('Shahid', $subcontract->employee->full_name);

        // Same row, same id — every related row untouched.
        $this->assertEquals(373650.00, (float) $subcontract->contract_value);
        $this->assertCount(1, $subcontract->milestones);
        $this->assertEquals(161860.00, $subcontract->totalPaid());
        $this->assertCount(1, $subcontract->reportCategories);

        // The supplier record itself is untouched (not deleted) — only
        // this one subcontract's link to it is cleared.
        $this->assertNotNull($supplier->fresh());
    }

    public function test_the_converted_record_only_opens_under_project_manager_budgets(): void
    {
        [$company, $project, $supplier, $subcontract] = $this->seedRealSaedEstSubcontract();
        $owner = \App\Models\User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        (new ConvertSaedEstToShahidProjectManagerBudgetSeeder)->run();
        $subcontract->refresh();

        $this->actingAs($owner)->get(route('app.project-manager-budgets.show', $subcontract))->assertOk();
        $this->actingAs($owner)->get(route('app.subcontracts.show', $subcontract))->assertNotFound();
    }

    public function test_reseeding_does_not_duplicate_or_recreate_the_employee(): void
    {
        [$company, $project, $supplier, $subcontract] = $this->seedRealSaedEstSubcontract();

        (new ConvertSaedEstToShahidProjectManagerBudgetSeeder)->run();
        (new ConvertSaedEstToShahidProjectManagerBudgetSeeder)->run();

        $this->assertSame(1, Employee::where('company_id', $company->id)->count());
        $this->assertSame(1, Subcontract::where('company_id', $company->id)->count());
    }

    public function test_an_existing_empty_duplicate_budget_is_deleted(): void
    {
        [$company, $project, $supplier, $subcontract] = $this->seedRealSaedEstSubcontract();
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-EXTRA', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 500]);
        $emptyDuplicate = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650.00, 'retention_percent' => 0, 'status' => 'active',
        ]);

        (new ConvertSaedEstToShahidProjectManagerBudgetSeeder)->run();

        $this->assertSoftDeleted($emptyDuplicate);
        $this->assertSame(1, Subcontract::where('company_id', $company->id)->count());
        $this->assertSame($employee->id, $subcontract->fresh()->employee_id);
    }

    public function test_a_duplicate_budget_with_real_payments_is_left_alone(): void
    {
        [$company, $project, $supplier, $subcontract] = $this->seedRealSaedEstSubcontract();
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-EXTRA', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 500]);
        $busyDuplicate = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650.00, 'retention_percent' => 0, 'status' => 'active',
        ]);
        $busyDuplicate->payments()->create(['company_id' => $company->id, 'amount' => 5000, 'paid_at' => now()]);

        (new ConvertSaedEstToShahidProjectManagerBudgetSeeder)->run();

        $this->assertNotNull($busyDuplicate->fresh());
        $this->assertSame('supplier', $subcontract->fresh()->party_type, 'The conversion must not proceed while a duplicate with real payments is unresolved.');
    }

    /**
     * "It has the same vouchers already on SAED EST (duplicate)" — the
     * real Subcontract #2 the seeder refused on turned out to have the
     * SAME payment vouchers linked to it as SAED EST, not genuinely
     * separate cash. The seeder now recognizes this: a duplicate
     * payment whose payment_voucher_id is also already linked to SAED
     * EST is removed from the duplicate (never from SAED EST), and the
     * conversion then proceeds as normal.
     */
    public function test_a_duplicate_budget_whose_payments_are_the_same_vouchers_already_on_saed_est_is_cleared_and_deleted(): void
    {
        [$company, $project, $supplier, $subcontract] = $this->seedRealSaedEstSubcontract();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'type' => 'bank', 'is_active' => true]);
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id, 'supplier_id' => $supplier->id, 'project_id' => $project->id,
            'voucher_number' => 'PV-DUP', 'date' => now(), 'payee_name' => 'SAED EST', 'amount' => 20000,
            'method' => 'bank_transfer', 'status' => 'issued',
        ]);
        // The same real voucher is linked to SAED EST...
        $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 20000, 'paid_at' => now(), 'payment_voucher_id' => $voucher->id]);

        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-EXTRA', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 500]);
        $duplicateBudget = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650.00, 'retention_percent' => 0, 'status' => 'active',
        ]);
        // ...and was also accidentally linked to the duplicate budget.
        $duplicateBudget->payments()->create(['company_id' => $company->id, 'amount' => 20000, 'paid_at' => now(), 'payment_voucher_id' => $voucher->id]);

        (new ConvertSaedEstToShahidProjectManagerBudgetSeeder)->run();

        $this->assertSoftDeleted($duplicateBudget);
        $this->assertSame(1, Subcontract::where('company_id', $company->id)->count());
        $this->assertSame('employee', $subcontract->fresh()->party_type);
        $this->assertSame($employee->id, $subcontract->fresh()->employee_id);
        // The real voucher link stays exactly once, on SAED EST.
        $this->assertEquals(161860.00 + 20000.00, $subcontract->fresh()->totalPaid());
        $this->assertDatabaseHas('subcontract_payments', ['subcontract_id' => $subcontract->id, 'payment_voucher_id' => $voucher->id]);
    }

    public function test_a_duplicate_budget_with_a_payment_not_matching_any_saed_est_voucher_is_left_alone(): void
    {
        [$company, $project, $supplier, $subcontract] = $this->seedRealSaedEstSubcontract();
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-EXTRA', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 500]);
        $duplicateBudget = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650.00, 'retention_percent' => 0, 'status' => 'active',
        ]);
        // No payment_voucher_id at all — a genuinely separate manual payment, not a duplicate link.
        $duplicateBudget->payments()->create(['company_id' => $company->id, 'amount' => 5000, 'paid_at' => now()]);

        (new ConvertSaedEstToShahidProjectManagerBudgetSeeder)->run();

        $this->assertNotNull($duplicateBudget->fresh());
        $this->assertSame('supplier', $subcontract->fresh()->party_type);
    }
}
