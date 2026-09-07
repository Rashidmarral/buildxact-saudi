<?php

namespace Tests\Feature\Pos;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\JournalEntry;
use App\Models\Plan;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\PlatformFeatureToggle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requested: a full retail POS module (register/shift sessions, touch
 * checkout, split payments, Z-report reconciliation, ZATCA receipts),
 * gated behind the platform feature-toggle system. Covers the
 * open-shift -> checkout -> close-shift flow, GL posting balance,
 * stock deduction/restoration, feature gating, and tenant isolation.
 */
class PosSaleTest extends TestCase
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

    private function makeRegisterWithStock(Company $company, float $qty = 50): array
    {
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main Store']);
        $register = PosRegister::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Register 1', 'is_active' => true]);
        $item = Item::create([
            'company_id' => $company->id, 'name' => 'Bottled Water', 'unit_price' => 5, 'vat_rate' => 15,
            'is_active' => true, 'track_inventory' => true,
        ]);
        $stock = ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => $qty]);

        return compact('warehouse', 'register', 'item', 'stock');
    }

    private function openShift(User $owner, PosRegister $register, float $openingCash = 200): PosShift
    {
        $this->actingAs($owner)->post(route('app.pos.shift.open'), [
            'register_id' => $register->id,
            'opening_cash' => $openingCash,
        ]);

        return $register->openShift();
    }

    public function test_checkout_deducts_stock_and_posts_a_balanced_journal_entry(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        ['register' => $register, 'item' => $item, 'stock' => $stock] = $this->makeRegisterWithStock($company);
        $shift = $this->openShift($owner, $register);

        $response = $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 3, 'unit_price' => 5],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 17.25],
            ],
        ]);

        $response->assertRedirect();

        $sale = PosSale::first();
        $this->assertNotNull($sale);
        $this->assertSame('completed', $sale->status);
        $this->assertSame(15.0, (float) $sale->subtotal);
        $this->assertSame(2.25, (float) $sale->vat_total);
        $this->assertSame(17.25, (float) $sale->total);

        $stock->refresh();
        $this->assertSame(47.0, (float) $stock->quantity);

        $entry = JournalEntry::where('source_type', 'pos_sale')->where('source_id', $sale->id)->with('lines')->first();
        $this->assertNotNull($entry);
        $totalDebit = (float) $entry->lines->sum('debit');
        $totalCredit = (float) $entry->lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.01);
        $this->assertEqualsWithDelta(17.25, $totalDebit, 0.01);
    }

    public function test_checkout_rejects_a_cart_when_payments_do_not_add_up_to_the_total(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        ['register' => $register, 'item' => $item] = $this->makeRegisterWithStock($company);
        $this->openShift($owner, $register);

        $response = $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 3, 'unit_price' => 5],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 10],
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('cart');
        $this->assertSame(0, PosSale::count());
    }

    public function test_checkout_splits_across_cash_and_card_payments(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        ['register' => $register, 'item' => $item] = $this->makeRegisterWithStock($company);
        $this->openShift($owner, $register);

        $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 10, 'unit_price' => 5],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 30],
                ['method' => 'card', 'amount' => 27.5],
            ],
        ]);

        $sale = PosSale::first();
        $this->assertSame(2, $sale->payments()->count());
        $this->assertSame(57.5, (float) $sale->total);
    }

    public function test_voiding_a_sale_restores_stock_and_reverses_the_journal_entry(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        ['register' => $register, 'item' => $item, 'stock' => $stock] = $this->makeRegisterWithStock($company);
        $shift = $this->openShift($owner, $register);

        $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 5, 'unit_price' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 28.75]],
        ]);
        $sale = PosSale::first();
        $stock->refresh();
        $this->assertSame(45.0, (float) $stock->quantity);

        $response = $this->actingAs($owner)->post(route('app.pos.sales.void', $sale), [
            'void_reason' => 'Customer changed their mind',
        ]);

        $response->assertRedirect();
        $sale->refresh();
        $this->assertSame('void', $sale->status);

        $stock->refresh();
        $this->assertSame(50.0, (float) $stock->quantity);

        $reversal = JournalEntry::where('source_type', 'pos_sale_reversal')->where('source_id', $sale->id)->with('lines')->first();
        $this->assertNotNull($reversal);
        $this->assertEqualsWithDelta((float) $reversal->lines->sum('debit'), (float) $reversal->lines->sum('credit'), 0.01);
    }

    public function test_closing_a_shift_reconciles_expected_versus_counted_cash(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        ['register' => $register, 'item' => $item] = $this->makeRegisterWithStock($company);
        $shift = $this->openShift($owner, $register, 200);

        $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 11.5]],
        ]);

        $response = $this->actingAs($owner)->post(route('app.pos.shift.close', $shift), [
            'counted_cash' => 210,
        ]);

        $response->assertRedirect(route('app.pos.shifts.show', $shift));
        $shift->refresh();
        $this->assertSame('closed', $shift->status);
        $this->assertSame(211.5, (float) $shift->expected_cash);
        $this->assertSame(-1.5, (float) $shift->cash_difference);
    }

    public function test_a_second_shift_cannot_be_opened_on_the_same_register_while_one_is_open(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        ['register' => $register] = $this->makeRegisterWithStock($company);
        $this->openShift($owner, $register);

        $response = $this->actingAs($owner)->post(route('app.pos.shift.open'), [
            'register_id' => $register->id,
            'opening_cash' => 100,
        ]);

        $response->assertSessionHasErrors('register');
        $this->assertSame(1, PosShift::count());
    }

    public function test_a_company_without_the_pos_plan_feature_is_blocked(): void
    {
        $plan = Plan::create([
            'name' => 'Basic', 'slug' => 'basic-'.uniqid(), 'price_monthly' => 10, 'price_yearly' => 100,
            'is_active' => true, 'has_pos' => false,
        ]);
        $company = Company::create(['name' => 'NoPos', 'slug' => 'nopos-'.uniqid(), 'status' => 'active']);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.pos-registers.index'));

        $response->assertRedirect(route('app.dashboard'));
        $response->assertSessionHasErrors('feature');
    }

    public function test_the_platform_wide_toggle_blocks_pos_even_for_a_company_whose_plan_includes_it(): void
    {
        PlatformFeatureToggle::setEnabled('pos', false);
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.pos-registers.index'));

        $response->assertRedirect(route('app.dashboard'));
    }

    public function test_a_company_cannot_see_another_companys_registers_shifts_or_sales(): void
    {
        $companyA = $this->makeCompanyWithPos();
        $companyB = $this->makeCompanyWithPos();
        $ownerA = $this->makeOwner($companyA);
        $ownerB = $this->makeOwner($companyB);
        ['register' => $registerA, 'item' => $itemA] = $this->makeRegisterWithStock($companyA);
        $shiftA = $this->openShift($ownerA, $registerA);

        $this->actingAs($ownerA)->postJson(route('app.pos.checkout'), [
            'register_id' => $registerA->id,
            'lines' => [['item_id' => $itemA->id, 'quantity' => 1, 'unit_price' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 5.75]],
        ]);
        $saleA = PosSale::first();

        $this->actingAs($ownerB)->get(route('app.pos.sales.show', $saleA))->assertNotFound();
        $this->actingAs($ownerB)->get(route('app.pos.shifts.show', $shiftA))->assertNotFound();
        $this->actingAs($ownerB)->postJson(route('app.pos.checkout'), [
            'register_id' => $registerA->id,
            'lines' => [['item_id' => $itemA->id, 'quantity' => 1, 'unit_price' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 5.75]],
        ])->assertNotFound();
    }

    public function test_a_completed_sale_receipt_renders_a_zatca_qr_code(): void
    {
        $company = $this->makeCompanyWithPos();
        $company->update(['vat_number' => '300000000000003']);
        $owner = $this->makeOwner($company);
        ['register' => $register, 'item' => $item] = $this->makeRegisterWithStock($company);
        $this->openShift($owner, $register);

        $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 5.75]],
        ]);
        $sale = PosSale::first();

        $response = $this->actingAs($owner)->get(route('app.pos.sales.show', $sale));

        $response->assertOk();
        $response->assertSee('data:image/png;base64,', false);
    }
}
