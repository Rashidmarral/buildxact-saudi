<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug report: on the quotation (and every other line-item) create page,
 * the unit dropdown for a "Custom" line (one with no linked catalog item)
 * was permanently disabled. populateUnitOptions() only ever populated the
 * unit select from the selected item's own alternate-units list, so a row
 * with no item_id had nothing to populate it with. Units are actually a
 * company-wide list independent of any item (Unit model, no per-item
 * scoping beyond the optional item_units conversion join), so a custom
 * line can safely offer the full company unit list even though it has no
 * per-unit price override. Fixed by passing the company's units to every
 * line-item form and falling back to that full list whenever no catalog
 * item is selected.
 */
class CustomLineItemUnitPickerTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Units Co.', 'slug' => 'units-'.uniqid()]);
        Unit::create(['company_id' => $company->id, 'name' => 'Ton', 'symbol' => 't']);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_quotation_create_page_exposes_company_units_for_custom_lines(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.quotations.create'));

        $response->assertOk();
        $response->assertSee('const ALL_UNITS', false);
        $response->assertSee('Ton');
    }

    public function test_invoice_create_page_exposes_company_units_for_custom_lines(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.invoices.create'));

        $response->assertOk();
        $response->assertSee('const ALL_UNITS', false);
        $response->assertSee('Ton');
    }

    public function test_bill_create_page_exposes_company_units_for_custom_lines(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.bills.create'));

        $response->assertOk();
        $response->assertSee('const ALL_UNITS', false);
        $response->assertSee('Ton');
    }

    public function test_purchase_order_create_page_exposes_company_units_for_custom_lines(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.purchase-orders.create'));

        $response->assertOk();
        $response->assertSee('const ALL_UNITS', false);
        $response->assertSee('Ton');
    }

    public function test_recurring_invoice_create_page_exposes_company_units_for_custom_lines(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.recurring-invoices.create'));

        $response->assertOk();
        $response->assertSee('const ALL_UNITS', false);
        $response->assertSee('Ton');
    }

    public function test_a_custom_line_can_be_submitted_with_a_company_unit_and_no_linked_item(): void
    {
        $owner = $this->makeOwner();
        $unit = Unit::where('company_id', $owner->company_id)->first();
        $client = \App\Models\Client::create(['company_id' => $owner->company_id, 'name' => 'Custom Line Client', 'type' => 'individual']);

        $response = $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['item_id' => '', 'unit_id' => $unit->id, 'description' => 'Freeform labour charge', 'quantity' => 2, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('quotation_items', [
            'item_id' => null,
            'unit_id' => $unit->id,
            'description' => 'Freeform labour charge',
        ]);
    }
}
