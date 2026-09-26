<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ApprovalChainStep;
use App\Models\ApprovalProgress;
use App\Models\Company;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

/**
 * APPR-01: multi-tier approval chains, layered on top of the older flat
 * po_approval_threshold/expense_approval_threshold gate rather than
 * replacing it. A document type with zero configured ApprovalChainStep
 * rows is completely untouched by this — see PurchaseOrderApprovalTest-
 * style coverage elsewhere and InvoiceQuotationApprovalTest for the flat
 * gate's own regression coverage, which this file doesn't repeat.
 *
 * Scenario mirrors the product ask: a purchase order over 5,000 SAR needs
 * Manager then Finance; over 20,000 SAR it also needs GM afterward.
 */
class ApprovalChainTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Chain Co.', 'slug' => 'chain-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    /** A member holding the base module permission plus the named custom role. */
    private function makeRoleHolder(Company $company, Role $role, array $permissions): User
    {
        $modulePermRole = Role::create(['company_id' => $company->id, 'name' => 'Module-'.$role->id, 'slug' => 'module-'.uniqid(), 'permissions' => $permissions]);
        $member = User::factory()->create(['role' => 'member', 'company_id' => $company->id, 'status' => 'active']);
        $member->roles()->attach([$role->id, $modulePermRole->id]);

        return $member;
    }

    private function makeChainRoles(Company $company): array
    {
        return [
            'manager' => Role::create(['company_id' => $company->id, 'name' => 'Manager', 'slug' => 'manager-'.uniqid(), 'permissions' => []]),
            'finance' => Role::create(['company_id' => $company->id, 'name' => 'Finance', 'slug' => 'finance-'.uniqid(), 'permissions' => []]),
            'gm' => Role::create(['company_id' => $company->id, 'name' => 'GM', 'slug' => 'gm-'.uniqid(), 'permissions' => []]),
        ];
    }

    private function makePoChain(Company $company, array $roles): void
    {
        ApprovalChainStep::create(['company_id' => $company->id, 'document_type' => 'purchase_order', 'step_number' => 1, 'role_id' => $roles['manager']->id, 'min_amount' => 5000]);
        ApprovalChainStep::create(['company_id' => $company->id, 'document_type' => 'purchase_order', 'step_number' => 2, 'role_id' => $roles['finance']->id, 'min_amount' => 5000]);
        ApprovalChainStep::create(['company_id' => $company->id, 'document_type' => 'purchase_order', 'step_number' => 3, 'role_id' => $roles['gm']->id, 'min_amount' => 20000]);
    }

    private function makeSupplier(Company $company): Supplier
    {
        return Supplier::create(['company_id' => $company->id, 'name' => 'Gulf Supplies']);
    }

    private function poPayload(Supplier $supplier, float $unitPrice): array
    {
        return [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'post_immediately' => '1',
            'items' => [
                ['description' => 'Bulk order', 'quantity' => 1, 'unit_price' => $unitPrice, 'vat_rate' => 15],
            ],
        ];
    }

    public function test_a_po_below_every_configured_tier_is_approved_instantly(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makePoChain($company, $roles);
        $owner = $this->makeOwner($company);
        $supplier = $this->makeSupplier($company);

        $this->actingAs($owner)->post(route('app.purchase-orders.store'), $this->poPayload($supplier, 1000))
            ->assertSessionDoesntHaveErrors();

        $order = PurchaseOrder::latest('id')->first();
        $this->assertSame('approved', $order->status);
        $this->assertSame(0, ApprovalProgress::count());
    }

    public function test_a_po_crossing_the_first_tier_only_needs_manager_then_finance_in_order(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makePoChain($company, $roles);
        $owner = $this->makeOwner($company);
        $supplier = $this->makeSupplier($company);
        $manager = $this->makeRoleHolder($company, $roles['manager'], ['purchases']);
        $finance = $this->makeRoleHolder($company, $roles['finance'], ['purchases']);

        // 8,000 SAR reaches the manager/finance tier (5,000) but not GM's (20,000).
        $this->actingAs($owner)->post(route('app.purchase-orders.store'), $this->poPayload($supplier, 8000));
        $order = PurchaseOrder::latest('id')->first();
        $this->assertSame('pending_approval', $order->status);
        $this->assertSame(2, ApprovalProgress::where('approvable_id', $order->id)->count());

        // Finance can't jump ahead of Manager.
        $this->actingAs($finance)->post(route('app.purchase-orders.approve', $order))->assertForbidden();
        $this->assertSame('pending_approval', $order->fresh()->status);

        // Manager approves — chain not complete yet, order stays pending.
        $this->actingAs($manager)->post(route('app.purchase-orders.approve', $order))->assertSessionDoesntHaveErrors();
        $this->assertSame('pending_approval', $order->fresh()->status);

        // Manager can't approve a second time (their step is already done).
        $this->actingAs($manager)->post(route('app.purchase-orders.approve', $order))->assertForbidden();

        // Finance signs off last — the order is now fully approved.
        $this->actingAs($finance)->post(route('app.purchase-orders.approve', $order))->assertSessionDoesntHaveErrors();
        $order->refresh();
        $this->assertSame('approved', $order->status);
        $this->assertSame($finance->id, $order->approved_by);
    }

    public function test_a_po_crossing_the_top_tier_also_needs_gm_after_finance(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makePoChain($company, $roles);
        $owner = $this->makeOwner($company);
        $supplier = $this->makeSupplier($company);
        $manager = $this->makeRoleHolder($company, $roles['manager'], ['purchases']);
        $finance = $this->makeRoleHolder($company, $roles['finance'], ['purchases']);
        $gm = $this->makeRoleHolder($company, $roles['gm'], ['purchases']);

        $this->actingAs($owner)->post(route('app.purchase-orders.store'), $this->poPayload($supplier, 25000));
        $order = PurchaseOrder::latest('id')->first();
        $this->assertSame(3, ApprovalProgress::where('approvable_id', $order->id)->count());

        $this->actingAs($manager)->post(route('app.purchase-orders.approve', $order));
        $this->actingAs($finance)->post(route('app.purchase-orders.approve', $order));
        $this->assertSame('pending_approval', $order->fresh()->status);

        $this->actingAs($gm)->post(route('app.purchase-orders.approve', $order))->assertSessionDoesntHaveErrors();
        $this->assertSame('approved', $order->fresh()->status);
    }

    public function test_rejecting_at_the_manager_step_skips_the_remaining_steps_and_notifies_the_creator(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makePoChain($company, $roles);
        $owner = $this->makeOwner($company);
        $supplier = $this->makeSupplier($company);
        $manager = $this->makeRoleHolder($company, $roles['manager'], ['purchases']);

        $this->actingAs($owner)->post(route('app.purchase-orders.store'), $this->poPayload($supplier, 8000));
        $order = PurchaseOrder::latest('id')->first();

        $this->actingAs($manager)->post(route('app.purchase-orders.reject', $order), ['rejection_reason' => 'Wrong vendor'])
            ->assertSessionDoesntHaveErrors();

        $order->refresh();
        $this->assertSame('rejected', $order->status);
        $this->assertSame('Wrong vendor', $order->rejection_reason);

        $progress = ApprovalProgress::where('approvable_id', $order->id)->orderBy('step_number')->get();
        $this->assertCount(2, $progress);
        $this->assertSame('rejected', $progress[0]->status);
        $this->assertSame('skipped', $progress[1]->status);

        $this->assertTrue(DatabaseNotification::where('notifiable_id', $owner->id)->where('data->title', __('Purchase order rejected'))->exists());
    }

    public function test_the_owner_can_act_on_any_step_regardless_of_role(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makePoChain($company, $roles);
        $owner = $this->makeOwner($company);
        $supplier = $this->makeSupplier($company);

        $this->actingAs($owner)->post(route('app.purchase-orders.store'), $this->poPayload($supplier, 8000));
        $order = PurchaseOrder::latest('id')->first();

        $this->actingAs($owner)->post(route('app.purchase-orders.approve', $order))->assertSessionDoesntHaveErrors();
        $this->actingAs($owner)->post(route('app.purchase-orders.approve', $order))->assertSessionDoesntHaveErrors();

        $this->assertSame('approved', $order->fresh()->status);
    }

    private function makeExpenseChain(Company $company, Role $role): void
    {
        ApprovalChainStep::create(['company_id' => $company->id, 'document_type' => 'expense', 'step_number' => 1, 'role_id' => $role->id, 'min_amount' => 1000]);
    }

    public function test_an_expense_chain_step_only_posts_to_the_ledger_after_the_final_approval(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makeExpenseChain($company, $roles['finance']);
        $owner = $this->makeOwner($company);
        $finance = $this->makeRoleHolder($company, $roles['finance'], ['expenses']);

        $this->actingAs($owner)->post(route('app.expenses.store'), [
            'gross_amount' => 2000, 'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(),
        ]);

        $expense = Expense::latest('id')->first();
        $this->assertSame('pending_approval', $expense->status);

        $this->actingAs($finance)->post(route('app.expenses.approve', $expense))->assertSessionDoesntHaveErrors();

        $expense->refresh();
        $this->assertSame('approved', $expense->status);
        $this->assertSame($finance->id, $expense->approved_by);
    }

    public function test_settings_can_add_and_remove_chain_steps_with_step_numbers_kept_contiguous(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.settings.approvals.chain-steps.store'), [
            'document_type' => 'purchase_order', 'role_id' => $roles['manager']->id, 'min_amount' => 5000,
        ])->assertSessionDoesntHaveErrors();
        $this->actingAs($owner)->post(route('app.settings.approvals.chain-steps.store'), [
            'document_type' => 'purchase_order', 'role_id' => $roles['finance']->id, 'min_amount' => 5000,
        ])->assertSessionDoesntHaveErrors();

        $steps = ApprovalChainStep::orderBy('step_number')->get();
        $this->assertSame([1, 2], $steps->pluck('step_number')->all());

        $this->actingAs($owner)->delete(route('app.settings.approvals.chain-steps.destroy', $steps[0]))
            ->assertSessionDoesntHaveErrors();

        $remaining = ApprovalChainStep::orderBy('step_number')->get();
        $this->assertCount(1, $remaining);
        $this->assertSame(1, $remaining[0]->step_number);
        $this->assertSame($roles['finance']->id, $remaining[0]->role_id);
    }

    public function test_the_approvals_settings_page_renders_the_configured_chain_in_arabic(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makePoChain($company, $roles);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->get(route('locale.switch', 'ar'));

        $this->actingAs($owner)->get(route('app.settings.approvals'))
            ->assertOk()
            ->assertSee(__('Multi-tier approval chains'))
            ->assertSee('Manager');
    }

    public function test_a_company_cannot_configure_a_chain_step_using_another_companys_role(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $roleB = Role::create(['company_id' => $companyB->id, 'name' => 'Foreign Manager', 'slug' => 'foreign-manager', 'permissions' => []]);

        $this->actingAs($ownerA)->post(route('app.settings.approvals.chain-steps.store'), [
            'document_type' => 'purchase_order', 'role_id' => $roleB->id, 'min_amount' => 5000,
        ])->assertSessionHasErrors('role_id');

        $this->assertSame(0, ApprovalChainStep::withoutGlobalScopes()->where('company_id', $companyA->id)->count());
    }
}
