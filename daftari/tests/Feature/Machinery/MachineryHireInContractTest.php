<?php

namespace Tests\Feature\Machinery;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Expense;
use App\Models\MachineryHireInContract;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reverse of renting the company's own machine OUT: hiring equipment
 * IN from an external supplier (the real attached contract — an MC1
 * spray tanker + driver hired from another contractor, billed per
 * square metre of completed work). Costs flow through the ordinary
 * Expense pipeline, the same way a company-owned machine's costs already
 * do via machinery_asset_id tagging — no parallel accounting.
 */
class MachineryHireInContractTest extends TestCase
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

    private function makeContract(Company $company): MachineryHireInContract
    {
        return MachineryHireInContract::create([
            'company_id' => $company->id,
            'contract_number' => $company->nextHireInContractNumber(),
            'supplier_name' => 'Simat Al-Rifah Co.',
            'supplier_cr_number' => '7053564584',
            'equipment_description' => 'MC1 & RC2 spray tanker',
            'start_date' => now()->toDateString(),
            'rate' => 0.25,
            'rate_type' => 'per_unit',
            'rate_unit_label' => 'sq. meter',
            'operator_included' => true,
            'fuel_responsibility' => 'supplier',
            'status' => 'active',
        ]);
    }

    public function test_a_contract_can_be_created_with_a_per_unit_rate(): void
    {
        $company = $this->makeCompany();
        $contract = $this->makeContract($company);

        $this->assertSame('HC-00001', $contract->contract_number);
        $this->assertSame('per_unit', $contract->rate_type);
        $this->assertSame('sq. meter', $contract->rate_unit_label);
        $this->assertSame('Simat Al-Rifah Co.', $contract->supplierDisplayName());
    }

    public function test_an_expense_tagged_to_the_contract_counts_toward_its_total_cost(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $contract = $this->makeContract($company);

        $this->actingAs($owner)->post(route('app.expenses.store'), [
            'vendor_name' => 'Simat Al-Rifah Co.', 'description' => 'September measured work',
            'gross_amount' => 10000, 'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(),
            'machinery_hire_in_contract_id' => $contract->id,
        ]);

        $expense = Expense::where('company_id', $company->id)->first();
        $this->assertNotNull($expense);
        $this->assertSame($contract->id, $expense->machinery_hire_in_contract_id);
        $this->assertSame('approved', $expense->status);
        $this->assertEqualsWithDelta(10000, $contract->fresh()->totalCost(), 0.01);
    }

    public function test_the_expense_form_prefills_the_contract_from_the_query_string(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $contract = $this->makeContract($company);

        $response = $this->actingAs($owner)->get(route('app.expenses.create', ['machinery_hire_in_contract_id' => $contract->id]));

        $response->assertOk();
        $response->assertSee('selected', false);
        $response->assertSee($contract->contract_number);
    }

    public function test_a_real_supplier_record_can_be_linked_instead_of_free_text(): void
    {
        $company = $this->makeCompany();
        $supplier = Supplier::create(['company_id' => $company->id, 'supplier_code' => 'SUP-001', 'name' => 'Simat Al-Rifah Co.', 'type' => 'company']);
        $contract = MachineryHireInContract::create([
            'company_id' => $company->id, 'contract_number' => $company->nextHireInContractNumber(),
            'supplier_id' => $supplier->id, 'equipment_description' => 'MC1 spray tanker',
            'start_date' => now()->toDateString(), 'rate' => 500, 'rate_type' => 'daily', 'status' => 'active',
        ]);

        $this->assertSame('Simat Al-Rifah Co.', $contract->supplierDisplayName());
        $this->assertSame($supplier->id, $contract->supplier->id);
    }
}
