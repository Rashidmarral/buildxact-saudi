<?php

namespace Tests\Feature\Restaurant;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Plan;
use App\Models\PosSale;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature request: "if I have a customer who has a restaurant, there
 * should be a restaurant module — installed as a module from the admin
 * panel if someone purchases it." Restaurant Management is wired up as a
 * Module 07 'gated' feature (see FeatureRegistry) exactly like Payroll/
 * POS: off on every plan by default, so a Super Admin turns it on for
 * one company at a time via the existing generic CompanyOverride
 * mechanism (Admin > Companies > show > Modules) — no plan upgrade
 * required, matching "install it for the company that purchased it."
 *
 * Covers: module gating (plan column + per-company override), table
 * management, and the full dine-in/takeaway order lifecycle — including
 * checkout, which deliberately reuses PosSaleService as-is (same GL
 * posting/payment recording a retail POS sale gets) rather than
 * duplicating that logic for restaurant orders.
 */
class RestaurantModuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(bool $withRestaurant): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_restaurant' => $withRestaurant,
        ]);

        $company = Company::create(['name' => 'Saed Est.', 'slug' => 'saed-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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

    private function withConfirmedPassword(User $user)
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    public function test_a_company_without_the_restaurant_plan_feature_or_override_is_blocked(): void
    {
        $company = $this->makeCompany(withRestaurant: false);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.restaurant.orders.index'));

        $response->assertRedirect(route('app.dashboard'));
        $response->assertSessionHasErrors('feature');
    }

    public function test_a_company_whose_plan_includes_restaurant_can_access_it(): void
    {
        $company = $this->makeCompany(withRestaurant: true);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.restaurant.orders.index'))->assertOk();
    }

    /**
     * The exact "admin installs it for a company that purchased it" story:
     * the company's plan does NOT include Restaurant Management, but a
     * Super Admin's per-company override (the same CompanyOverride
     * mechanism Payroll/POS already use) turns it on for this one company
     * only — reusing the existing generic admin UI with zero new code.
     */
    public function test_an_admin_override_installs_the_module_for_one_company_without_a_plan_change(): void
    {
        $company = $this->makeCompany(withRestaurant: false);
        $owner = $this->makeOwner($company);
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'restaurant', 'value' => '1']);

        $this->actingAs($owner)->get(route('app.restaurant.orders.index'))->assertOk();
    }

    public function test_tables_can_be_created_updated_and_deleted(): void
    {
        $company = $this->makeCompany(withRestaurant: true);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.restaurant.tables.store'), [
            'name' => 'T1', 'area' => 'Main Hall', 'seats' => 4,
        ])->assertRedirect();
        $table = RestaurantTable::first();
        $this->assertSame('T1', $table->name);
        $this->assertSame('available', $table->status);

        $this->actingAs($owner)->put(route('app.restaurant.tables.update', $table), [
            'name' => 'T1', 'area' => 'Terrace', 'seats' => 6,
        ])->assertRedirect();
        $this->assertSame('Terrace', $table->fresh()->area);

        $this->actingAs($owner)->delete(route('app.restaurant.tables.destroy', $table))->assertRedirect();
        $this->assertSame(0, RestaurantTable::count());
    }

    public function test_a_dine_in_order_runs_end_to_end_from_table_to_paid_pos_sale(): void
    {
        $company = $this->makeCompany(withRestaurant: true);
        $owner = $this->makeOwner($company);
        $table = RestaurantTable::create(['company_id' => $company->id, 'name' => 'T1', 'seats' => 4, 'status' => 'available']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Grilled Chicken', 'unit_price' => 40, 'vat_rate' => 15, 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.restaurant.orders.store'), [
            'order_type' => 'dine_in', 'table_id' => $table->id,
        ])->assertRedirect();

        $order = RestaurantOrder::first();
        $this->assertSame('open', $order->status);
        $this->assertSame('occupied', $table->fresh()->status);

        // A second dine-in order cannot start on the same (now occupied) table.
        $this->actingAs($owner)->post(route('app.restaurant.orders.store'), [
            'order_type' => 'dine_in', 'table_id' => $table->id,
        ])->assertSessionHasErrors('order');

        $this->actingAs($owner)->postJson(route('app.restaurant.orders.items.store', $order), [
            'lines' => [['item_id' => $item->id, 'quantity' => 2, 'notes' => 'No spice']],
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('kitchen', $order->status);
        $line = $order->items()->first();
        $this->assertSame('pending', $line->kitchen_status);
        $this->assertSame('No spice', $line->notes);

        // The order-builder page (open order: item select dropdowns, the
        // add-items cart, and the checkout panel all render) and the
        // kitchen display board (this order should now be listed on it).
        $this->actingAs($owner)->get(route('app.restaurant.orders.show', $order))->assertOk()->assertSee('No spice');
        $this->actingAs($owner)->get(route('app.restaurant.kitchen.index'))->assertOk()->assertSee($order->order_number);
        $this->actingAs($owner)->get(route('app.restaurant.kitchen.feed'))->assertOk()->assertSee($order->order_number);
        $this->actingAs($owner)->get(route('app.restaurant.tables.index'))->assertOk()->assertSee('T1');

        $this->actingAs($owner)->postJson(route('app.restaurant.order-items.status', $line), [
            'kitchen_status' => 'ready',
        ])->assertOk();

        $order->refresh();
        $this->assertSame('ready', $order->status);

        $response = $this->actingAs($owner)->postJson(route('app.restaurant.orders.checkout', $order), [
            'payments' => [['method' => 'cash', 'amount' => 92.0]], // 2 * 40 = 80 + 15% VAT = 92
        ]);
        $response->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('available', $table->fresh()->status);
        $this->assertNotNull($order->pos_sale_id);

        $sale = PosSale::find($order->pos_sale_id);
        $this->assertSame('completed', $sale->status);
        $this->assertSame(80.0, (float) $sale->subtotal);
        $this->assertSame(92.0, (float) $sale->total);

        // The completed order's page (no select dropdowns/cancel button —
        // the "closed" branches of the view — plus the receipt link) and
        // the now-empty kitchen board both still render cleanly.
        $this->actingAs($owner)->get(route('app.restaurant.orders.show', $order))->assertOk()->assertSee(__('View receipt'));
        $this->actingAs($owner)->get(route('app.restaurant.kitchen.index'))->assertOk()->assertDontSee($order->order_number);

        $entry = JournalEntry::where('source_type', 'pos_sale')->where('source_id', $sale->id)->with('lines')->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta((float) $entry->lines->sum('debit'), (float) $entry->lines->sum('credit'), 0.01);
    }

    /**
     * Security audit finding: RestaurantOrderService::checkout() used to
     * check isOpen() on the PHP object it was handed, before opening its DB
     * transaction — exactly the shape two concurrent requests (a
     * double-click, or two open tabs) would each have: both load the order
     * while it's still open, both pass the isOpen() check, both create a
     * PosSale. Reproduces that shape with two independent, stale
     * RestaurantOrder instances (see DocumentNumberingRaceTest for the same
     * pattern) and asserts the fix — a lockForUpdate() re-fetch as the
     * first thing inside the transaction — makes the second call see the
     * first call's 'completed' status instead of also checking out.
     */
    public function test_two_stale_order_instances_cannot_both_check_out_the_same_order(): void
    {
        $company = $this->makeCompany(withRestaurant: true);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Grilled Chicken', 'unit_price' => 40, 'vat_rate' => 15, 'is_active' => true]);

        $order = RestaurantOrder::create([
            'company_id' => $company->id, 'order_number' => $company->nextRestaurantOrderNumber(),
            'order_type' => 'takeaway', 'status' => 'ready', 'created_by' => $owner->id,
        ]);
        RestaurantOrderItem::create([
            'company_id' => $company->id, 'restaurant_order_id' => $order->id, 'item_id' => $item->id,
            'description' => $item->name, 'quantity' => 1, 'unit_price' => 40, 'vat_rate' => 15, 'kitchen_status' => 'ready',
        ]);

        // Two independent instances of the same row, as two concurrent
        // checkout requests would each have — both still holding
        // status='ready' as it was at load time.
        $orderA = RestaurantOrder::find($order->id);
        $orderB = RestaurantOrder::find($order->id);

        $service = app(\App\Services\Restaurant\RestaurantOrderService::class);
        $ledger = app(\App\Services\Accounting\LedgerPostingService::class);
        $payments = [['method' => 'cash', 'amount' => 46.0]];

        $saleA = $service->checkout($orderA, $payments, $ledger);
        $this->assertSame('completed', $saleA->status);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This order is already closed.');
        $service->checkout($orderB, $payments, $ledger);
    }

    public function test_a_takeaway_order_needs_no_table_and_checks_out_the_same_way(): void
    {
        $company = $this->makeCompany(withRestaurant: true);
        $owner = $this->makeOwner($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Shawarma Wrap', 'unit_price' => 12, 'vat_rate' => 15, 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.restaurant.orders.store'), [
            'order_type' => 'takeaway', 'customer_name' => 'Waleed', 'customer_phone' => '0500000000',
        ])->assertRedirect();

        $order = RestaurantOrder::first();
        $this->assertNull($order->table_id);
        $this->assertSame('Waleed', $order->customer_name);

        $this->actingAs($owner)->postJson(route('app.restaurant.orders.items.store', $order), [
            'lines' => [['item_id' => $item->id, 'quantity' => 1]],
        ])->assertRedirect();

        $response = $this->actingAs($owner)->postJson(route('app.restaurant.orders.checkout', $order), [
            'payments' => [['method' => 'card', 'amount' => 13.8]],
        ]);
        $response->assertRedirect();

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, PosSale::count());
    }

    public function test_cancelling_a_dine_in_order_frees_the_table(): void
    {
        $company = $this->makeCompany(withRestaurant: true);
        $owner = $this->makeOwner($company);
        $table = RestaurantTable::create(['company_id' => $company->id, 'name' => 'T2', 'seats' => 2, 'status' => 'available']);

        $this->actingAs($owner)->post(route('app.restaurant.orders.store'), [
            'order_type' => 'dine_in', 'table_id' => $table->id,
        ]);
        $order = RestaurantOrder::first();
        $this->assertSame('occupied', $table->fresh()->status);

        $this->actingAs($owner)->post(route('app.restaurant.orders.cancel', $order), [
            'cancel_reason' => 'Guest left',
        ])->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('available', $table->fresh()->status);
    }

    public function test_checkout_is_rejected_with_no_items_on_the_order(): void
    {
        $company = $this->makeCompany(withRestaurant: true);
        $owner = $this->makeOwner($company);
        $table = RestaurantTable::create(['company_id' => $company->id, 'name' => 'T3', 'seats' => 2, 'status' => 'available']);
        $this->actingAs($owner)->post(route('app.restaurant.orders.store'), ['order_type' => 'dine_in', 'table_id' => $table->id]);
        $order = RestaurantOrder::first();

        $response = $this->actingAs($owner)->postJson(route('app.restaurant.orders.checkout', $order), [
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ]);

        $response->assertSessionHasErrors('checkout');
        $this->assertSame(0, PosSale::count());
    }

    public function test_a_company_cannot_add_another_companys_item_to_its_order(): void
    {
        $companyA = $this->makeCompany(withRestaurant: true);
        $companyB = $this->makeCompany(withRestaurant: true);
        $ownerA = $this->makeOwner($companyA);
        $itemB = Item::create(['company_id' => $companyB->id, 'name' => 'Other Co Item', 'unit_price' => 10, 'vat_rate' => 15, 'is_active' => true]);
        $table = RestaurantTable::create(['company_id' => $companyA->id, 'name' => 'T1', 'seats' => 2, 'status' => 'available']);
        $this->actingAs($ownerA)->post(route('app.restaurant.orders.store'), ['order_type' => 'dine_in', 'table_id' => $table->id]);
        $order = RestaurantOrder::first();

        $response = $this->actingAs($ownerA)->postJson(route('app.restaurant.orders.items.store', $order), [
            'lines' => [['item_id' => $itemB->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['lines.0.item_id']);
        $this->assertSame(0, RestaurantOrderItem::withoutGlobalScopes()->count());
    }

    /**
     * The full "purchase → install" admin workflow end to end, using only
     * the existing generic admin screens (no Restaurant-specific admin
     * code was written): Restaurant Management shows up automatically on
     * the plan editor, the company override list, and the platform-wide
     * kill switch, because it's just another FeatureRegistry entry.
     */
    public function test_a_super_admin_can_install_and_remove_the_module_from_the_existing_generic_admin_screens(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        $company = $this->makeCompany(withRestaurant: false);
        $owner = $this->makeOwner($company);

        $this->withConfirmedPassword($admin)->get(route('admin.plans.create'))->assertOk()->assertSee('has_restaurant', false);
        $this->withConfirmedPassword($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee(__('Restaurant Management'));

        $companyShow = $this->withConfirmedPassword($admin)->get(route('admin.companies.show', $company));
        $companyShow->assertOk()->assertSee(__('Restaurant Management'));

        $this->withConfirmedPassword($admin)->post(route('admin.companies.overrides.set', $company), [
            'type' => 'feature', 'key' => 'restaurant', 'value' => '1', 'reason' => 'Customer purchased the restaurant add-on',
        ])->assertRedirect();

        $this->actingAs($owner)->get(route('app.restaurant.orders.index'))->assertOk();

        $override = CompanyOverride::where('company_id', $company->id)->where('key', 'restaurant')->firstOrFail();
        $this->withConfirmedPassword($admin)->delete(route('admin.companies.overrides.clear', [$company, $override]))->assertRedirect();

        $this->actingAs($owner)->get(route('app.restaurant.orders.index'))
            ->assertRedirect(route('app.dashboard'));
    }
}
