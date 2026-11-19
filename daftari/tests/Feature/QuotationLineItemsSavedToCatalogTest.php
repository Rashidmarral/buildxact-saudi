<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "products will save in database that's quotation or performa invoice
 * created" — confirmed with the operator: when a line is typed freeform
 * on a quotation or proforma (no item picked from the catalog), it
 * should also be saved into the Items catalog, so it's reusable next
 * time instead of being retyped from scratch. Quotation and Proforma
 * Invoice share one controller method (QuotationController::syncItems()),
 * so fixing it there covers both document types at once, matching
 * exactly what was asked.
 */
class QuotationLineItemsSavedToCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithClient(): array
    {
        $company = Company::create(['name' => 'Catalog Co.', 'slug' => 'catalog-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);

        return [$company, $owner, $client];
    }

    public function test_a_freeform_quotation_line_is_saved_into_the_items_catalog(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Bulk, shaft seal HQQE - CR 32 to 150', 'quantity' => 3, 'unit_price' => 16000, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $item = Item::where('company_id', $company->id)->where('name', 'Bulk, shaft seal HQQE - CR 32 to 150')->first();
        $this->assertNotNull($item, 'the typed line should have created a catalog item');
        $this->assertSame('service', $item->item_type);
        $this->assertFalse($item->track_inventory);
        $this->assertEqualsWithDelta(16000, $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(15, $item->vat_rate, 0.01);

        $quotation = Quotation::latest('id')->first();
        $this->assertSame($item->id, $quotation->items->first()->item_id, 'the quotation line should link back to the new catalog item');
    }

    public function test_a_freeform_proforma_line_is_also_saved_into_the_items_catalog(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'type' => 'proforma',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'KSB Movitec VF 40/4 - Shaft Impeller Set', 'quantity' => 1, 'unit_price' => 2500, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $this->assertNotNull(Item::where('company_id', $company->id)->where('name', 'KSB Movitec VF 40/4 - Shaft Impeller Set')->first());
    }

    public function test_quoting_the_same_product_twice_does_not_create_a_duplicate_catalog_item(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();

        $payload = [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Mechanical Seal ETN 080-065-250', 'quantity' => 1, 'unit_price' => 2540, 'vat_rate' => 15]],
        ];

        $this->actingAs($owner)->post(route('app.quotations.store'), $payload)->assertSessionDoesntHaveErrors();
        // Same name, different case, extra surrounding whitespace, a
        // later quotation — still the same product.
        $payload['items'][0]['description'] = '  mechanical seal etn 080-065-250  ';
        $this->actingAs($owner)->post(route('app.quotations.store'), $payload)->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Quotation::where('company_id', $company->id)->count());
        $this->assertSame(1, Item::where('company_id', $company->id)->count(), 'quoting the same product twice must reuse the catalog item, not duplicate it');
    }

    /**
     * Regression test: "i have save quotation as draft status but when i
     * back to edit it change the unit and just allow me to select only
     * pcs unit". A freeform line quoted in a non-default unit (here
     * "Box") auto-created its catalog item with no base_unit_id of its
     * own, so Item::booted()'s creating hook silently defaulted it to
     * the company's Piece unit — the edit form's unit picker only ever
     * offers a selected item's own configured units, so the line was
     * stuck showing Pcs with Box nowhere to be found, even though the
     * quotation itself still had unit_id pointing at Box.
     */
    public function test_a_freeform_lines_chosen_unit_is_preserved_on_its_auto_created_catalog_item(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $box = Unit::create(['company_id' => $company->id, 'name' => 'Box', 'symbol' => 'box']);

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Gasket Set, Full Box', 'quantity' => 2, 'unit_id' => $box->id, 'unit_price' => 75, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $item = Item::where('company_id', $company->id)->where('name', 'Gasket Set, Full Box')->first();
        $this->assertNotNull($item);
        $this->assertSame($box->id, $item->base_unit_id, 'the auto-created catalog item must keep the unit the freeform line was actually quoted in');

        $quotation = Quotation::latest('id')->first();
        $this->assertSame($box->id, $quotation->items->first()->unit_id);

        // The edit form's unit picker only offers a selected item's own
        // units — confirm Box (not just Pcs) is actually among them.
        $editPage = $this->actingAs($owner)->get(route('app.quotations.edit', $quotation));
        $editPage->assertOk()->assertSee($box->label(), false);
    }

    /**
     * Regression test for the follow-up report: "what happened to
     * already created quoted because still unit is not coming allow
     * option to select unit again as well on edit". Fixing
     * findOrCreateCatalogItem() only protects *new* freeform lines going
     * forward — a quotation saved before that fix (or, more generally,
     * any item whose own configured units were later edited to no
     * longer include a unit some existing document line still uses)
     * would stay permanently stuck: the edit page's unit picker only
     * ever offers an item's own units, so a saved unit_id missing from
     * that list had nowhere to be reselected from. Fixed at the view
     * layer in populateUnitOptions() (every line-item form) instead, so
     * already-affected documents recover without a data migration: the
     * saved unit is looked up in the company's full unit list and
     * folded into the dropdown whenever it isn't already one of the
     * item's own. This test simulates exactly that stuck state directly
     * (an item whose only unit is Pcs, a line that still points at Box)
     * and confirms the edit page gives the JS everything it needs to
     * recover — the saved unit_id and the full company unit list
     * (including Box's label) both still render.
     */
    public function test_edit_page_still_exposes_a_lines_saved_unit_even_when_its_items_own_units_no_longer_include_it(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $pcs = Unit::create(['company_id' => $company->id, 'name' => 'Pcs', 'symbol' => 'pcs']);
        $box = Unit::create(['company_id' => $company->id, 'name' => 'Box', 'symbol' => 'box']);
        $item = Item::create([
            'company_id' => $company->id, 'name' => 'Stuck Item', 'item_type' => 'service',
            'unit_price' => 50, 'vat_rate' => 15, 'is_active' => true, 'base_unit_id' => $pcs->id,
        ]);

        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'type' => 'quotation',
            'quotation_number' => 'QTN-STUCK', 'status' => 'draft',
            'issue_date' => now()->toDateString(), 'expiry_date' => now()->addDays(30)->toDateString(),
        ]);
        $quotation->items()->create([
            'item_id' => $item->id, 'unit_id' => $box->id, 'description' => 'Stuck Item',
            'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 15,
        ]);

        $editPage = $this->actingAs($owner)->get(route('app.quotations.edit', $quotation));
        $editPage->assertOk()->assertSee('const ALL_UNITS', false);

        // Js::from() renders EXISTING as JSON.parse('<json with "
        // in place of every literal ">'), not a plain JS object literal —
        // unescape that back into real JSON before decoding it, to
        // confirm the line's own saved unit_id is still exactly what the
        // fixed populateUnitOptions() needs to try to select.
        $content = $editPage->getContent();
        preg_match("/const EXISTING = JSON\.parse\('(.*?)'\);/", $content, $existingMatch);
        $this->assertNotEmpty($existingMatch, 'could not find the EXISTING data array on the edit page');
        $existing = json_decode(str_replace('\\u0022', '"', $existingMatch[1]), true);
        $this->assertSame($box->id, $existing[0]['unit_id']);

        // Box's label must still be present in ALL_UNITS too, so there's
        // something for populateUnitOptions() to fall back to.
        $editPage->assertSee($box->label(), false);
    }

    public function test_a_line_picked_from_the_catalog_does_not_create_another_item(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $item = Item::create(['company_id' => $company->id, 'name' => 'SKF Bearing 7315 B', 'item_type' => 'service', 'unit_price' => 1250, 'vat_rate' => 15, 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [['item_id' => $item->id, 'description' => 'SKF Bearing 7315 B', 'quantity' => 6, 'unit_price' => 1250, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Item::where('company_id', $company->id)->count());
    }

    public function test_re_editing_a_quotation_does_not_duplicate_its_own_catalog_item(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Spare, Wear Ring D122/D136x10 BS', 'quantity' => 1, 'unit_price' => 443, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $quotation = Quotation::latest('id')->first();

        $this->actingAs($owner)->put(route('app.quotations.update', $quotation), [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Spare, Wear Ring D122/D136x10 BS', 'quantity' => 2, 'unit_price' => 443, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Item::where('company_id', $company->id)->count());
    }
}
