<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug report: creating a Bill or Purchase Order from the item catalog
 * pre-filled the line price with the item's SALE price (unit_price),
 * never its purchase/cost price (purchase_price) — both fields exist on
 * Item, but the Bill/PO create pages' $catalogJson builder only ever
 * read unit_price. A purchasing document should default to what the
 * item costs, not what it sells for.
 *
 * The catalog is embedded as Illuminate\Support\Js::from(...), which
 * HTML/JS-escapes every " as the literal 6-character sequence "
 * rather than emitting a raw quote — the assertions below match that
 * encoding, not raw JSON quotes.
 */
class PurchaseDocumentPricePrefillTest extends TestCase
{
    use RefreshDatabase;

    private const Q = '\u0022';

    private function makeOwnerWithItem(): array
    {
        $company = Company::create(['name' => 'Pricing Co.', 'slug' => 'pricing-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $item = Item::create([
            'company_id' => $company->id, 'name' => 'Impeller', 'unit_price' => 950.00, 'purchase_price' => 850.00,
            'vat_rate' => 15, 'is_active' => true,
        ]);

        return [$company, $owner, $item];
    }

    public function test_bill_create_page_prefills_the_catalog_with_purchase_price_not_sale_price(): void
    {
        [, $owner, $item] = $this->makeOwnerWithItem();

        $response = $this->actingAs($owner)->get(route('app.bills.create'));

        $response->assertOk();
        $response->assertSee(self::Q.'unit_price'.self::Q.':850', false);
        $response->assertDontSee(self::Q.'unit_price'.self::Q.':950', false);
    }

    public function test_purchase_order_create_page_prefills_the_catalog_with_purchase_price_not_sale_price(): void
    {
        [, $owner, $item] = $this->makeOwnerWithItem();

        $response = $this->actingAs($owner)->get(route('app.purchase-orders.create'));

        $response->assertOk();
        $response->assertSee(self::Q.'unit_price'.self::Q.':850', false);
        $response->assertDontSee(self::Q.'unit_price'.self::Q.':950', false);
    }

    public function test_invoice_create_page_still_prefills_the_catalog_with_sale_price(): void
    {
        [, $owner, $item] = $this->makeOwnerWithItem();

        $response = $this->actingAs($owner)->get(route('app.invoices.create'));

        $response->assertOk();
        $response->assertSee(self::Q.'unit_price'.self::Q.':950', false);
    }

    public function test_purchase_price_for_unit_divides_by_the_alt_units_conversion_factor(): void
    {
        [$company, , $item] = $this->makeOwnerWithItem();
        $bag = Unit::create(['company_id' => $company->id, 'name' => 'Bag', 'symbol' => 'bag']);
        ItemUnit::create(['item_id' => $item->id, 'unit_id' => $bag->id, 'conversion_factor' => 10]);

        $this->assertEqualsWithDelta(85.0, $item->purchasePriceForUnit($bag->id), 0.001);
    }

    public function test_purchase_price_for_unit_returns_zero_when_no_purchase_price_is_set(): void
    {
        $company = Company::create(['name' => 'No Cost Co.', 'slug' => 'no-cost-'.uniqid()]);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Freebie', 'unit_price' => 100, 'vat_rate' => 15, 'is_active' => true]);

        $this->assertSame(0.0, $item->purchasePriceForUnit(null));
    }
}
