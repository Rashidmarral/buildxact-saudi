<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\PaymentMode;
use App\Models\Plan;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more... Payment Mode as GL-mapped dimension" — a POS/restaurant/
 * repair-shop sale's tender used to post to one of three accounts
 * hardcoded in LedgerPostingService (DEFAULT_CASH/POS_CARD_CLEARING/
 * DEFAULT_BANK). PaymentMode makes that company-configurable: remap an
 * existing tender's account, or add a brand new named tender type with
 * its own account, with zero change for a company that never visits
 * Settings.
 */
class PaymentModeTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithPos(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_pos' => true,
        ]);

        $company = Company::create(['name' => 'Acme Retail', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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

    public function test_visiting_settings_seeds_the_four_default_payment_modes(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);

        $page = $this->actingAs($owner)->get(route('app.settings.payment-modes.index'));

        $page->assertOk()->assertSee('Cash')->assertSee('Card')->assertSee('Other')->assertSee('Loyalty card');
        $this->assertSame(4, PaymentMode::where('company_id', $company->id)->count());
    }

    public function test_remapping_the_cash_mode_changes_which_account_a_cash_sale_posts_to(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        PaymentMode::seedDefaults($company->id);
        $pettyCash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Petty Cash Drawer', 'type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true]);
        $cashMode = PaymentMode::where('company_id', $company->id)->where('code', 'cash')->first();

        $this->actingAs($owner)->put(route('app.settings.payment-modes.update', $cashMode), [
            'name' => 'Cash', 'category' => 'cash', 'linked_account_id' => $pettyCash->id, 'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame($pettyCash->id, $cashMode->fresh()->linked_account_id);

        $warehouse = \App\Models\Warehouse::create(['company_id' => $company->id, 'name' => 'Main Store']);
        $register = PosRegister::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Register 1', 'is_active' => true]);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Bottled Water', 'unit_price' => 5, 'vat_rate' => 0, 'is_active' => true, 'track_inventory' => false]);
        $this->actingAs($owner)->post(route('app.pos.shift.open'), ['register_id' => $register->id, 'opening_cash' => 100]);

        $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 5]],
        ])->assertRedirect();

        $sale = PosSale::first();
        $entry = JournalEntry::where('source_type', 'pos_sale')->where('source_id', $sale->id)->with('lines')->first();
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account_id === $pettyCash->id && (float) $line->debit === 5.0));
    }

    public function test_a_custom_payment_mode_is_selectable_at_checkout_and_posts_to_its_own_account(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        PaymentMode::seedDefaults($company->id);
        $stcPayClearing = Account::create(['company_id' => $company->id, 'code' => '1160', 'name' => 'STC Pay Clearing', 'type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.settings.payment-modes.store'), [
            'code' => 'stc_pay', 'name' => 'STC Pay', 'category' => 'other', 'linked_account_id' => $stcPayClearing->id, 'is_active' => '1',
        ])->assertRedirect();

        $warehouse = \App\Models\Warehouse::create(['company_id' => $company->id, 'name' => 'Main Store']);
        $register = PosRegister::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Register 1', 'is_active' => true]);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Bottled Water', 'unit_price' => 5, 'vat_rate' => 0, 'is_active' => true, 'track_inventory' => false]);
        $this->actingAs($owner)->post(route('app.pos.shift.open'), ['register_id' => $register->id, 'opening_cash' => 100]);

        $response = $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 5]],
            'payments' => [['method' => 'stc_pay', 'amount' => 5]],
        ]);

        $response->assertRedirect();

        $sale = PosSale::first();
        $entry = JournalEntry::where('source_type', 'pos_sale')->where('source_id', $sale->id)->with('lines')->first();
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account_id === $stcPayClearing->id && (float) $line->debit === 5.0));
    }

    public function test_checkout_rejects_a_payment_method_that_is_not_a_configured_mode(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        $warehouse = \App\Models\Warehouse::create(['company_id' => $company->id, 'name' => 'Main Store']);
        $register = PosRegister::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Register 1', 'is_active' => true]);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Bottled Water', 'unit_price' => 5, 'vat_rate' => 0, 'is_active' => true, 'track_inventory' => false]);
        $this->actingAs($owner)->post(route('app.pos.shift.open'), ['register_id' => $register->id, 'opening_cash' => 100]);

        $response = $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 5]],
            'payments' => [['method' => 'bitcoin', 'amount' => 5]],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, PosSale::count());
    }

    public function test_deleting_a_payment_mode_with_sales_is_blocked(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        PaymentMode::seedDefaults($company->id);
        $warehouse = \App\Models\Warehouse::create(['company_id' => $company->id, 'name' => 'Main Store']);
        $register = PosRegister::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Register 1', 'is_active' => true]);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Bottled Water', 'unit_price' => 5, 'vat_rate' => 0, 'is_active' => true, 'track_inventory' => false]);
        $this->actingAs($owner)->post(route('app.pos.shift.open'), ['register_id' => $register->id, 'opening_cash' => 100]);
        $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 5]],
        ])->assertRedirect();

        $cashMode = PaymentMode::where('company_id', $company->id)->where('code', 'cash')->first();

        $this->actingAs($owner)->delete(route('app.settings.payment-modes.destroy', $cashMode))->assertRedirect();

        $this->assertNotNull($cashMode->fresh());
    }
}
