<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug report: "there is no option to search item when selecting an item
 * for invoice or quote" — the line-item "Item" field was a plain <select>
 * with every catalog item dumped in as an <option>, so a company with a
 * large catalog (e.g. ~380 items) had no way to filter it; you had to
 * scroll or use the browser's jump-to-letter typeahead. This was the same
 * on every document type with line items (invoices, quotations, recurring
 * invoices, purchase orders, bills), all five sharing byte-identical
 * markup and JS.
 *
 * Replaced the native <select> with a type-to-search combobox (shared
 * across all five forms via resources/views/user/partials/item-picker.blade.php)
 * that filters the already-loaded CATALOG client-side by name, Arabic
 * name, SKU or barcode. These tests confirm the old <select> is gone, the
 * new search input and its catalog fields are present, and — most
 * importantly — that a document can still be submitted through the new
 * picker's hidden item_id field exactly as before.
 */
class LineItemSearchableItemPickerTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnerWithCatalog(): User
    {
        $company = Company::create(['name' => 'Searchable Catalog Co.', 'slug' => 'searchable-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        Item::create([
            'company_id' => $company->id,
            'name' => 'Portland Cement 50kg Bag',
            'name_ar' => 'أسمنت بورتلاندي',
            'sku' => 'CEM-050',
            'type' => 'product',
            'unit_price' => 25,
            'purchase_price' => 20,
            'vat_rate' => 15,
        ]);

        return $owner;
    }

    public function test_the_old_plain_select_item_dropdown_is_gone_from_every_line_item_form(): void
    {
        $owner = $this->makeOwnerWithCatalog();

        foreach (['app.invoices.create', 'app.quotations.create', 'app.recurring-invoices.create', 'app.purchase-orders.create', 'app.bills.create'] as $route) {
            $response = $this->actingAs($owner)->get(route($route));

            $response->assertOk();
            $response->assertDontSee('data-role="item"', false);
            $response->assertSee('data-role="item-search"', false);
            $response->assertSee('data-role="item_id"', false);
        }
    }

    public function test_the_catalog_exposes_sku_and_arabic_name_for_client_side_search_on_every_form(): void
    {
        $owner = $this->makeOwnerWithCatalog();
        $item = Item::where('company_id', $owner->company_id)->first();

        foreach (['app.invoices.create', 'app.quotations.create', 'app.recurring-invoices.create', 'app.purchase-orders.create', 'app.bills.create'] as $route) {
            $response = $this->actingAs($owner)->get(route($route));

            $response->assertOk();
            $response->assertSee('CEM-050', false);

            // The catalog is emitted via Js::from() as `JSON.parse('...')`,
            // double-encoded (a JSON document, itself re-encoded as a JS
            // string literal) — decode both layers back to a PHP array
            // instead of asserting on brittle, hand-escaped Unicode text.
            preg_match("/const CATALOG = JSON\.parse\('(.*?)'\);/s", $response->getContent(), $matches);
            $this->assertNotEmpty($matches, "Could not find the CATALOG JSON.parse(...) payload on {$route}");
            $catalog = json_decode(json_decode('"'.$matches[1].'"'), true);

            $catalogEntry = collect($catalog)->firstWhere('sku', 'CEM-050');
            $this->assertNotNull($catalogEntry, "SKU CEM-050 missing from the decoded catalog on {$route}");
            $this->assertSame($item->name_ar, $catalogEntry['name_ar']);
        }
    }

    public function test_an_invoice_can_still_be_submitted_with_a_catalog_item_selected_through_the_new_picker(): void
    {
        $owner = $this->makeOwnerWithCatalog();
        $item = Item::where('company_id', $owner->company_id)->first();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Picker Test Client', 'type' => 'individual']);

        $response = $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $item->id, 'description' => $item->name, 'quantity' => 3, 'unit_price' => 25, 'vat_rate' => 15],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('invoice_items', [
            'item_id' => $item->id,
            'description' => $item->name,
        ]);
    }
}
