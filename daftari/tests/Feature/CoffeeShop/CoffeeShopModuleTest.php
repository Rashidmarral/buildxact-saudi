<?php

namespace Tests\Feature\CoffeeShop;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyCardTransaction;
use App\Models\Plan;
use App\Models\PosSale;
use App\Models\RestaurantOrder;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coffee Shop: a thin preset on top of Restaurant (same order/table/
 * kitchen/checkout machinery a quick-service counter needs — no separate
 * domain model) plus its one genuinely new piece of value, a prepaid
 * loyalty card customers can top up and spend at checkout, redeemed via
 * LoyaltyCardService alongside PosSaleService's existing cash/card/other
 * payment methods (see RestaurantOrderService::checkout()).
 */
class CoffeeShopModuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(bool $withCoffeeShop, bool $withRestaurant = false): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_coffee_shop' => $withCoffeeShop, 'has_restaurant' => $withRestaurant,
        ]);

        $company = Company::create(['name' => 'Bean & Brew', 'slug' => 'bean-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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

    // ------------------------------------------------------------------
    // Module gating
    // ------------------------------------------------------------------

    public function test_a_company_without_coffee_shop_is_blocked_from_loyalty_cards(): void
    {
        $company = $this->makeCompany(withCoffeeShop: false);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.coffee-shop.loyalty-cards.index'))
            ->assertRedirect(route('app.dashboard'));
    }

    public function test_a_company_whose_plan_includes_coffee_shop_can_access_loyalty_cards(): void
    {
        $company = $this->makeCompany(withCoffeeShop: true);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.coffee-shop.loyalty-cards.index'))->assertOk();
    }

    public function test_an_admin_override_installs_coffee_shop_for_one_company_without_a_plan_change(): void
    {
        $company = $this->makeCompany(withCoffeeShop: false);
        $owner = $this->makeOwner($company);
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'coffee_shop', 'value' => '1', 'reason' => 'test']);

        $this->actingAs($owner)->get(route('app.coffee-shop.loyalty-cards.index'))->assertOk();
    }

    /**
     * Coffee Shop is a preset on Restaurant, not a fork of it — a
     * coffee-shop-only company (no separate `restaurant` module) must
     * still reach the shared order/table/kitchen routes, and vice versa a
     * plain restaurant (no coffee_shop) must NOT reach loyalty cards.
     */
    public function test_a_coffee_shop_only_company_can_reach_the_shared_restaurant_order_routes(): void
    {
        $company = $this->makeCompany(withCoffeeShop: true, withRestaurant: false);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.restaurant.orders.index'))->assertOk();
        $this->actingAs($owner)->get(route('app.restaurant.tables.index'))->assertOk();
    }

    public function test_a_restaurant_only_company_cannot_reach_loyalty_cards(): void
    {
        $company = $this->makeCompany(withCoffeeShop: false, withRestaurant: true);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.coffee-shop.loyalty-cards.index'))
            ->assertRedirect(route('app.dashboard'));
    }

    // ------------------------------------------------------------------
    // Loyalty cards
    // ------------------------------------------------------------------

    public function test_a_card_can_be_created_with_an_initial_top_up(): void
    {
        $company = $this->makeCompany(withCoffeeShop: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sara']);

        $this->actingAs($owner)->post(route('app.coffee-shop.loyalty-cards.store'), [
            'client_id' => $client->id, 'initial_top_up' => 50,
        ])->assertRedirect();

        $card = LoyaltyCard::first();
        $this->assertSame($client->id, $card->client_id);
        $this->assertSame(50.0, (float) $card->balance);
        $this->assertSame(1, LoyaltyCardTransaction::where('type', 'top_up')->count());
    }

    public function test_an_existing_card_can_be_topped_up(): void
    {
        $company = $this->makeCompany(withCoffeeShop: true);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.coffee-shop.loyalty-cards.store'), []);
        $card = LoyaltyCard::first();

        $this->actingAs($owner)->post(route('app.coffee-shop.loyalty-cards.top-up', $card), ['amount' => 25])->assertRedirect();

        $this->assertSame(25.0, (float) $card->fresh()->balance);
    }

    public function test_card_lookup_finds_an_active_card_by_number(): void
    {
        $company = $this->makeCompany(withCoffeeShop: true);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.coffee-shop.loyalty-cards.store'), ['initial_top_up' => 40]);
        $card = LoyaltyCard::first();

        $found = $this->actingAs($owner)->getJson(route('app.coffee-shop.loyalty-cards.lookup', ['q' => $card->card_number]));
        $found->assertOk()->assertJson(['found' => true, 'id' => $card->id, 'balance' => 40]);

        $notFound = $this->actingAs($owner)->getJson(route('app.coffee-shop.loyalty-cards.lookup', ['q' => 'NOPE']));
        $notFound->assertOk()->assertJson(['found' => false]);
    }

    public function test_a_company_cannot_top_up_another_companys_card(): void
    {
        $companyA = $this->makeCompany(withCoffeeShop: true);
        $companyB = $this->makeCompany(withCoffeeShop: true);
        $ownerA = $this->makeOwner($companyA);
        $this->actingAs($this->makeOwner($companyB))->post(route('app.coffee-shop.loyalty-cards.store'), []);
        $cardB = LoyaltyCard::withoutGlobalScopes()->where('company_id', $companyB->id)->first();

        $this->actingAs($ownerA)->post(route('app.coffee-shop.loyalty-cards.top-up', $cardB), ['amount' => 10])
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Checkout with a loyalty card
    // ------------------------------------------------------------------

    public function test_a_takeaway_order_can_be_paid_partly_with_a_loyalty_card(): void
    {
        $company = $this->makeCompany(withCoffeeShop: true);
        $owner = $this->makeOwner($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Flat White', 'unit_price' => 20, 'vat_rate' => 15, 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.coffee-shop.loyalty-cards.store'), ['initial_top_up' => 15]);
        $card = LoyaltyCard::first();

        $this->actingAs($owner)->post(route('app.restaurant.orders.store'), [
            'order_type' => 'takeaway', 'customer_name' => 'Sara',
        ]);
        $order = RestaurantOrder::first();

        $this->actingAs($owner)->postJson(route('app.restaurant.orders.items.store', $order), [
            'lines' => [['item_id' => $item->id, 'quantity' => 1]],
        ]);

        // 20 * 1.15 = 23.00 total; 15 from the card, 8 cash.
        $response = $this->actingAs($owner)->postJson(route('app.restaurant.orders.checkout', $order), [
            'payments' => [
                ['method' => 'loyalty_card', 'amount' => 15, 'loyalty_card_id' => $card->id],
                ['method' => 'cash', 'amount' => 8],
            ],
        ]);
        $response->assertRedirect();

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(0.0, (float) $card->fresh()->balance);

        $sale = PosSale::first();
        $this->assertSame(23.0, (float) $sale->total);

        $redemption = LoyaltyCardTransaction::where('type', 'redeem')->first();
        $this->assertNotNull($redemption);
        $this->assertSame(15.0, (float) $redemption->amount);
        $this->assertSame($sale->id, $redemption->pos_sale_id);

        $entry = JournalEntry::where('source_type', 'pos_sale')->where('source_id', $sale->id)->with('lines')->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta((float) $entry->lines->sum('debit'), (float) $entry->lines->sum('credit'), 0.01);

        // The sale's payment method is generically 'other' (PosSaleService
        // doesn't know what a loyalty card is), so the receipt shows the
        // reference ("Loyalty card :number") instead of the raw method —
        // otherwise a customer would see an unhelpful "Other" line item.
        $this->actingAs($owner)->get(route('app.pos.sales.show', $sale))
            ->assertOk()->assertSee(__('Loyalty card :number', ['number' => $card->card_number]))->assertDontSee('>Other<', false);
    }

    public function test_checkout_is_rejected_when_the_card_balance_is_insufficient(): void
    {
        $company = $this->makeCompany(withCoffeeShop: true);
        $owner = $this->makeOwner($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Latte', 'unit_price' => 20, 'vat_rate' => 15, 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.coffee-shop.loyalty-cards.store'), ['initial_top_up' => 5]);
        $card = LoyaltyCard::first();

        $this->actingAs($owner)->post(route('app.restaurant.orders.store'), ['order_type' => 'takeaway']);
        $order = RestaurantOrder::first();
        $this->actingAs($owner)->postJson(route('app.restaurant.orders.items.store', $order), [
            'lines' => [['item_id' => $item->id, 'quantity' => 1]],
        ]);

        $response = $this->actingAs($owner)->postJson(route('app.restaurant.orders.checkout', $order), [
            'payments' => [['method' => 'loyalty_card', 'amount' => 23, 'loyalty_card_id' => $card->id]],
        ]);

        $response->assertSessionHasErrors('checkout');
        $this->assertTrue($order->fresh()->isOpen());
        $this->assertSame(5.0, (float) $card->fresh()->balance);
        $this->assertSame(0, PosSale::count());
    }
}
