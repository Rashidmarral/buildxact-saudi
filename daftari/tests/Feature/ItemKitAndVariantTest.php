<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ItemKitComponent;
use App\Models\ItemStock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding LOW-26: items had no way to represent a bundle sold as one
 * line (a kit) or a family of related items sharing a base product (a
 * variant) — every item was a fully standalone SKU. Kits expand into their
 * components' own stock at sale time rather than carrying a stock row of
 * their own; variants are lightweight, full standalone Items linked back
 * to a shared parent via parent_item_id.
 */
class ItemKitAndVariantTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Bundle Co.', 'slug' => 'bundle-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_creating_a_kit_item_persists_its_components(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $fruit = Item::create(['company_id' => $company->id, 'name' => 'Fruit', 'item_type' => 'physical', 'unit_price' => 5, 'track_inventory' => true]);
        $box = Item::create(['company_id' => $company->id, 'name' => 'Box', 'item_type' => 'physical', 'unit_price' => 2, 'track_inventory' => true]);

        $response = $this->actingAs($owner)->post(route('app.items.store'), [
            'name' => 'Gift Basket', 'item_type' => 'physical', 'unit_price' => 25, 'vat_rate' => 15,
            'is_kit' => '1',
            'kit_components' => [
                ['component_item_id' => $fruit->id, 'quantity' => 3],
                ['component_item_id' => $box->id, 'quantity' => 1],
            ],
        ]);

        $kit = Item::where('name', 'Gift Basket')->first();
        $response->assertRedirect(route('app.items.index'));
        $this->assertNotNull($kit);
        $this->assertTrue($kit->is_kit);
        $this->assertFalse($kit->track_inventory);
        $this->assertEquals(2, $kit->kitComponents()->count());
        $this->assertEquals(3, (float) $kit->kitComponents()->where('component_item_id', $fruit->id)->first()->quantity);
    }

    public function test_selling_a_kit_deducts_each_components_stock_and_not_the_kits_own(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);

        $fruit = Item::create(['company_id' => $company->id, 'name' => 'Fruit', 'track_inventory' => true, 'purchase_price' => 2, 'unit_price' => 5]);
        $box = Item::create(['company_id' => $company->id, 'name' => 'Box', 'track_inventory' => true, 'purchase_price' => 1, 'unit_price' => 2]);
        $kit = Item::create(['company_id' => $company->id, 'name' => 'Gift Basket', 'is_kit' => true, 'track_inventory' => false, 'unit_price' => 25]);
        ItemKitComponent::create(['company_id' => $company->id, 'kit_item_id' => $kit->id, 'component_item_id' => $fruit->id, 'quantity' => 3]);
        ItemKitComponent::create(['company_id' => $company->id, 'kit_item_id' => $kit->id, 'component_item_id' => $box->id, 'quantity' => 1]);

        ItemStock::create(['item_id' => $fruit->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50]);
        ItemStock::create(['item_id' => $box->id, 'warehouse_id' => $warehouse->id, 'quantity' => 20]);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'draft', 'currency' => 'SAR',
            'warehouse_id' => $warehouse->id, 'stock_deducted' => false,
            'subtotal' => 50, 'vat_total' => 7.5, 'total' => 57.5,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'item_id' => $kit->id, 'description' => 'Gift Basket', 'quantity' => 2, 'unit_price' => 25, 'vat_rate' => 15, 'vat_amount' => 7.5, 'line_total' => 57.5]);

        $owner = $this->makeOwner($company);
        $response = $this->actingAs($owner)->post(route('app.invoices.send', $invoice));
        $response->assertRedirect();

        // 2 kits sold * 3 fruit each = 6 deducted; 2 kits * 1 box each = 2 deducted.
        $this->assertEquals(44, (float) ItemStock::where('item_id', $fruit->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertEquals(18, (float) ItemStock::where('item_id', $box->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertNull(ItemStock::where('item_id', $kit->id)->first());

        $response = $this->actingAs($owner)->post(route('app.invoices.cancel', $invoice->fresh()));
        $response->assertRedirect();

        $this->assertEquals(50, (float) ItemStock::where('item_id', $fruit->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertEquals(20, (float) ItemStock::where('item_id', $box->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
    }

    public function test_creating_a_variant_clones_shared_fields_and_links_to_the_parent(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'T-Shirt', 'item_type' => 'physical', 'unit_price' => 40, 'purchase_price' => 20, 'vat_rate' => 15, 'track_inventory' => true]);

        $response = $this->actingAs($owner)->post(route('app.items.variants.store', $item), [
            'variant_label' => 'Red / Large',
            'sku' => 'TSHIRT-RED-L',
        ]);

        $variant = Item::where('sku', 'TSHIRT-RED-L')->first();
        $response->assertRedirect(route('app.items.edit', $variant));
        $this->assertNotNull($variant);
        $this->assertEquals($item->id, $variant->parent_item_id);
        $this->assertEquals('Red / Large', $variant->variant_label);
        $this->assertEquals(40, (float) $variant->unit_price);
        $this->assertTrue($variant->isVariant());
        $this->assertEquals(1, $item->fresh()->variants()->count());
    }

    public function test_creating_a_variant_of_a_variant_flattens_to_the_original_parent(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'T-Shirt', 'item_type' => 'physical', 'unit_price' => 40]);
        $variant = Item::create(['company_id' => $company->id, 'name' => 'T-Shirt — Red', 'parent_item_id' => $item->id, 'variant_label' => 'Red', 'unit_price' => 40]);

        $this->actingAs($owner)->post(route('app.items.variants.store', $variant), [
            'variant_label' => 'Red / Small',
        ]);

        $grandchild = Item::where('variant_label', 'Red / Small')->first();
        $this->assertNotNull($grandchild);
        $this->assertEquals($item->id, $grandchild->parent_item_id);
        $this->assertEquals(2, $item->fresh()->variants()->count());
    }
}
