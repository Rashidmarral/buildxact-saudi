<?php

namespace Tests\Feature\Machinery;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Expense;
use App\Models\MachineryHireInContract;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end: creating a hire-in contract through the controller/form,
 * recording an expense against it via the "Record an expense" link, and
 * ending the contract. Mirrors the shape of the existing rental-contract
 * flow tests, but for the reverse (hiring equipment IN) direction.
 */
class MachineryHireInContractFlowTest extends TestCase
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

    public function test_creating_a_hire_in_contract_through_the_form(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-001', 'name' => 'Asphalt Site A', 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.machinery.hire-in-contracts.store'), [
            'supplier_name' => 'Simat Al-Rifah Co.',
            'supplier_cr_number' => '7053564584',
            'equipment_description' => 'MC1 & RC2 spray tanker',
            'start_date' => now()->toDateString(),
            'rate' => 0.25,
            'rate_type' => 'per_unit',
            'rate_unit_label' => 'sq. meter',
            'fuel_responsibility' => 'supplier',
            'operator_included' => '1',
            'project_id' => $project->id,
        ]);

        $contract = MachineryHireInContract::first();
        $response->assertRedirect(route('app.machinery.hire-in-contracts.show', $contract));
        $this->assertSame('HC-00001', $contract->contract_number);
        $this->assertTrue($contract->operator_included);
        $this->assertSame($project->id, $contract->project_id);
    }

    public function test_leaving_the_operator_checkbox_unchecked_stores_false_not_the_db_default(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.machinery.hire-in-contracts.store'), [
            'supplier_name' => 'Simat Al-Rifah Co.', 'equipment_description' => 'Roller',
            'start_date' => now()->toDateString(), 'rate' => 500, 'rate_type' => 'daily',
            'fuel_responsibility' => 'supplier',
            // operator_included intentionally omitted — an unchecked checkbox.
        ]);

        $contract = MachineryHireInContract::first();
        $this->assertFalse($contract->operator_included);
    }

    public function test_the_show_page_lists_expenses_and_their_total(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $contract = MachineryHireInContract::create([
            'company_id' => $company->id, 'contract_number' => $company->nextHireInContractNumber(),
            'supplier_name' => 'Simat Al-Rifah Co.', 'equipment_description' => 'MC1 spray tanker',
            'start_date' => now()->toDateString(), 'rate' => 0.25, 'rate_type' => 'per_unit',
            'rate_unit_label' => 'sq. meter', 'fuel_responsibility' => 'supplier', 'status' => 'active',
        ]);
        Expense::create([
            'company_id' => $company->id, 'machinery_hire_in_contract_id' => $contract->id,
            'vendor_name' => 'Simat Al-Rifah Co.', 'description' => 'September measured work',
            'amount' => 8695.65, 'gross_amount' => 10000, 'vat_amount' => 1304.35, 'tax_category' => 'standard_15',
            'expense_date' => now(), 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->get(route('app.machinery.hire-in-contracts.show', $contract));

        $response->assertOk();
        $response->assertSee('September measured work');
        $response->assertSee('10,000.00');
    }

    public function test_ending_a_contract_sets_status_completed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $contract = MachineryHireInContract::create([
            'company_id' => $company->id, 'contract_number' => $company->nextHireInContractNumber(),
            'supplier_name' => 'Simat Al-Rifah Co.', 'equipment_description' => 'MC1 spray tanker',
            'start_date' => now()->subDays(10)->toDateString(), 'rate' => 500, 'rate_type' => 'daily',
            'fuel_responsibility' => 'supplier', 'status' => 'active',
        ]);

        $response = $this->actingAs($owner)->post(route('app.machinery.hire-in-contracts.end', $contract), [
            'end_date' => now()->toDateString(), 'return_condition_notes' => 'Returned in good condition.',
        ]);

        $response->assertRedirect(route('app.machinery.hire-in-contracts.show', $contract));
        $this->assertSame('completed', $contract->fresh()->status);
    }

    public function test_company_a_cannot_view_company_bs_hire_in_contract(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $contractB = MachineryHireInContract::create([
            'company_id' => $companyB->id, 'contract_number' => $companyB->nextHireInContractNumber(),
            'supplier_name' => 'B Supplier', 'equipment_description' => 'Roller',
            'start_date' => now()->toDateString(), 'rate' => 500, 'rate_type' => 'daily',
            'fuel_responsibility' => 'supplier', 'status' => 'active',
        ]);

        $response = $this->actingAs($ownerA)->get(route('app.machinery.hire-in-contracts.show', $contractB));

        $response->assertNotFound();
    }
}
