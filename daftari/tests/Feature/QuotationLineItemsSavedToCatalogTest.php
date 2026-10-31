<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Item;
use App\Models\Quotation;
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
