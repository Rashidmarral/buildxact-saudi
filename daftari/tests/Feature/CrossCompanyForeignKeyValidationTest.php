<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Item;
use App\Models\Plan;
use App\Models\PosRegister;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding D-8: PosRegisterController, EmployeeController,
 * and PosController::checkout()/openShift() all validated a tenant-owned
 * foreign key (branch_id/warehouse_id/client_id/item_id/register_id)
 * with a bare, unscoped 'exists:table,id' rule instead of the
 * Rule::exists(...)->where('company_id', ...) pattern used everywhere
 * else in the codebase. Most were "safe" only because a later re-fetch
 * through the tenant-scoped Eloquent model happened to catch a
 * cross-company ID (producing a confusing 404 instead of a clean
 * validation error) — but PosController::checkout()'s client_id had no
 * such re-fetch at all, and would have been written directly onto the
 * new PosSale row.
 */
class CrossCompanyForeignKeyValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR',
        ], $overrides));
    }

    /**
     * pos/employees routes are gated behind module:pos / module:payroll
     * (FeatureAccessService) on top of company scoping — a plan with the
     * matching has_pos/has_payroll flag and an active subscription is
     * needed for the request to even reach the controller.
     */
    private function makeCompanyWithFeatures(array $features): Company
    {
        $plan = Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ], $features));

        $company = $this->makeCompany();
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

    // -----------------------------------------------------------------
    // PosRegisterController
    // -----------------------------------------------------------------

    public function test_a_pos_register_cannot_be_linked_to_another_companys_branch_or_warehouse(): void
    {
        $companyA = $this->makeCompanyWithFeatures(['has_pos' => true]);
        $ownerA = $this->makeOwner($companyA);
        $companyB = $this->makeCompany();
        $branchB = Branch::create(['company_id' => $companyB->id, 'name' => 'Branch B']);
        $warehouseB = Warehouse::create(['company_id' => $companyB->id, 'name' => 'Warehouse B']);

        $response = $this->actingAs($ownerA)->post(route('app.pos-registers.store'), [
            'name' => 'Register 1', 'branch_id' => $branchB->id, 'warehouse_id' => $warehouseB->id,
        ]);

        $response->assertSessionHasErrors(['branch_id', 'warehouse_id']);
        $this->assertSame(0, PosRegister::where('company_id', $companyA->id)->count());
    }

    public function test_a_pos_register_can_still_be_linked_to_its_own_companys_branch_and_warehouse(): void
    {
        $company = $this->makeCompanyWithFeatures(['has_pos' => true]);
        $owner = $this->makeOwner($company);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'HQ']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);

        $response = $this->actingAs($owner)->post(route('app.pos-registers.store'), [
            'name' => 'Register 1', 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('pos_registers', ['company_id' => $company->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id]);
    }

    // -----------------------------------------------------------------
    // EmployeeController
    // -----------------------------------------------------------------

    public function test_an_employee_cannot_be_linked_to_another_companys_branch(): void
    {
        $companyA = $this->makeCompanyWithFeatures(['has_payroll' => true]);
        $ownerA = $this->makeOwner($companyA);
        $companyB = $this->makeCompany();
        $branchB = Branch::create(['company_id' => $companyB->id, 'name' => 'Branch B']);

        $response = $this->actingAs($ownerA)->post(route('app.employees.store'), [
            'branch_id' => $branchB->id, 'full_name' => 'John Doe',
            'hire_date' => now()->toDateString(), 'basic_salary' => 5000,
        ]);

        $response->assertSessionHasErrors('branch_id');
        $this->assertDatabaseMissing('employees', ['company_id' => $companyA->id, 'full_name' => 'John Doe']);
    }

    // -----------------------------------------------------------------
    // PosController::checkout() — client_id was the one genuinely
    // unmitigated gap (no compensating re-fetch existed anywhere).
    // -----------------------------------------------------------------

    public function test_a_pos_sale_cannot_be_linked_to_another_companys_client(): void
    {
        $companyA = $this->makeCompanyWithFeatures(['has_pos' => true]);
        $ownerA = $this->makeOwner($companyA);
        $warehouseA = Warehouse::create(['company_id' => $companyA->id, 'name' => 'Main']);
        $registerA = PosRegister::create(['company_id' => $companyA->id, 'warehouse_id' => $warehouseA->id, 'name' => 'Register 1', 'is_active' => true]);
        $itemA = Item::create(['company_id' => $companyA->id, 'name' => 'Water', 'unit_price' => 5, 'vat_rate' => 15, 'is_active' => true]);
        $this->actingAs($ownerA)->post(route('app.pos.shift.open'), ['register_id' => $registerA->id, 'opening_cash' => 100]);

        $companyB = $this->makeCompany();
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Client B']);

        $response = $this->actingAs($ownerA)->postJson(route('app.pos.checkout'), [
            'register_id' => $registerA->id,
            'client_id' => $clientB->id,
            'lines' => [['item_id' => $itemA->id, 'quantity' => 1, 'unit_price' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 5.75]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('client_id');
        $this->assertSame(0, \App\Models\PosSale::count(), 'No sale should be created when the client belongs to another company.');
    }
}
