<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature request: an Item Profitability report next to Inventory
 * Valuation — margin per item sold in a period, using the item's current
 * purchase price as its cost basis (the same cost basis Valuation uses).
 */
class ItemProfitabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Profit Co.', 'slug' => 'profit-'.uniqid()]);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeItem(Company $company, string $name, float $purchasePrice): Item
    {
        return Item::create([
            'company_id' => $company->id, 'name' => $name, 'item_type' => 'product',
            'unit_price' => 0, 'purchase_price' => $purchasePrice, 'vat_rate' => 15, 'is_active' => true,
        ]);
    }

    private function makeInvoiceLine(Company $company, Client $client, string $issueDate, Item $item, float $qty, float $unitPrice, string $status = 'sent'): void
    {
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => $status, 'issue_date' => $issueDate,
            'currency' => $company->currency, 'total' => $qty * $unitPrice,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => $item->name,
            'quantity' => $qty, 'unit_price' => $unitPrice, 'vat_rate' => 15,
            'vat_amount' => 0, 'line_total' => $qty * $unitPrice,
        ]);
    }

    public function test_margin_is_computed_from_revenue_and_current_purchase_price(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Margin Client']);
        $item = $this->makeItem($company, 'Widget', purchasePrice: 40);

        $this->makeInvoiceLine($company, $client, now()->subDays(5)->toDateString(), $item, qty: 3, unitPrice: 100);

        $response = $this->actingAs($owner)->get(route('app.inventory.profitability'));

        $response->assertOk();
        $response->assertSee('Widget');
        // Revenue 300.00, cost 120.00, margin 180.00.
        $response->assertSee('300.00');
        $response->assertSee('120.00');
        $response->assertSee('180.00');
    }

    public function test_items_with_no_sales_in_the_period_do_not_appear(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Old Sale Client']);
        $item = $this->makeItem($company, 'Stale Gadget', purchasePrice: 10);

        // Sold a year ago — outside the default (this-month) period.
        $this->makeInvoiceLine($company, $client, now()->subYear()->toDateString(), $item, qty: 1, unitPrice: 50);

        $response = $this->actingAs($owner)->get(route('app.inventory.profitability'));

        $response->assertOk();
        $response->assertDontSee('Stale Gadget');
        $response->assertSee(__('No items were sold in this period.'));
    }

    public function test_draft_and_cancelled_invoices_are_excluded(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Draft Client']);
        $item = $this->makeItem($company, 'Draft Widget', purchasePrice: 5);

        $this->makeInvoiceLine($company, $client, now()->toDateString(), $item, qty: 2, unitPrice: 20, status: 'draft');
        $this->makeInvoiceLine($company, $client, now()->toDateString(), $item, qty: 2, unitPrice: 20, status: 'cancelled');

        $response = $this->actingAs($owner)->get(route('app.inventory.profitability'));

        $response->assertOk();
        $response->assertDontSee('Draft Widget');
    }

    public function test_the_csv_export_lists_each_item(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'CSV Client']);
        $item = $this->makeItem($company, 'Exported Item', purchasePrice: 10);

        $this->makeInvoiceLine($company, $client, now()->toDateString(), $item, qty: 5, unitPrice: 30);

        $response = $this->actingAs($owner)->get(route('app.inventory.profitability', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Exported Item', $csv);
        $this->assertStringContainsString('150.00', $csv);
    }

    public function test_the_profitability_tab_is_reachable_from_valuation(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.inventory.valuation'));

        $response->assertOk();
        $response->assertSee(route('app.inventory.profitability'), false);
    }
}
