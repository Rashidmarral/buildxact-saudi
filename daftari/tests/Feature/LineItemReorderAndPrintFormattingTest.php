<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature request: drag-and-drop reordering of invoice/quotation line
 * items, the printed unit column showing the unit's short symbol (e.g.
 * "kg") instead of its full name ("Kilogram"), the signature block sitting
 * above the closing footer strip instead of below it, and Notes/Terms
 * rendering as a bulleted list instead of one plain paragraph.
 *
 * The line-item table's drag handle moves a <tr> without renumbering its
 * `items[N]` input names, so a dragged-reorder submission arrives with
 * array keys out of sequence (e.g. items[2] before items[0]) — exactly
 * what these tests construct by hand to prove syncItems() ranks
 * sort_order by submission order, not by the original index.
 */
class LineItemReorderAndPrintFormattingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Reorder Test Co.', 'slug' => 'reorder-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_a_dragged_reordered_invoice_lines_persist_their_new_order_not_original_index(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        // Simulates the DOM order after dragging the 3rd row to the top:
        // the browser submits items[2] first, but its input name still
        // says "2" because dragging never renames fields.
        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'items' => [
                2 => ['description' => 'Third (dragged to top)', 'quantity' => 1, 'unit_price' => 10, 'vat_rate' => 15],
                0 => ['description' => 'First (dragged to middle)', 'quantity' => 1, 'unit_price' => 20, 'vat_rate' => 15],
                1 => ['description' => 'Second (dragged to bottom)', 'quantity' => 1, 'unit_price' => 30, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $descriptions = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('sort_order')->pluck('description')->all();

        $this->assertSame([
            'Third (dragged to top)',
            'First (dragged to middle)',
            'Second (dragged to bottom)',
        ], $descriptions);
    }

    public function test_a_dragged_reordered_quotation_lines_persist_their_new_order_not_original_index(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'items' => [
                1 => ['description' => 'Moved to top', 'quantity' => 1, 'unit_price' => 10, 'vat_rate' => 15],
                0 => ['description' => 'Moved to bottom', 'quantity' => 1, 'unit_price' => 20, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $quotation = Quotation::latest('id')->first();
        $descriptions = QuotationItem::where('quotation_id', $quotation->id)->orderBy('sort_order')->pluck('description')->all();

        $this->assertSame(['Moved to top', 'Moved to bottom'], $descriptions);
    }

    public function test_the_printed_invoice_shows_the_unit_symbol_not_the_full_unit_name(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $unit = Unit::create(['company_id' => $company->id, 'name' => 'Kilogram', 'name_ar' => 'كيلوجرام', 'symbol' => 'kg', 'code' => 'KGM']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Steel Beam', 'unit_price' => 100]);

        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $item->id, 'unit_id' => $unit->id, 'description' => 'Steel Beam', 'quantity' => 5, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $response = $this->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('kg');
        $response->assertDontSee('Kilogram');
    }

    public function test_notes_and_terms_render_as_a_bulleted_list_not_a_plain_paragraph(): void
    {
        [$company, $owner] = $this->makeOwner();
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
            'terms_en' => "Payment due within 15 days\nGoods remain our property until paid in full",
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'notes' => "Thank you for your business\nPlease pay via bank transfer",
            'items' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $response = $this->get(route('app.invoices.show', $invoice));
        $html = $response->getContent();

        $response->assertOk();
        $this->assertStringContainsString('<ul', $html);
        $this->assertGreaterThanOrEqual(4, substr_count($html, '<li'));
        $response->assertSee('Thank you for your business');
        $response->assertSee('Payment due within 15 days');
    }

    public function test_the_signature_block_renders_above_the_closing_footer_line_not_below_it(): void
    {
        [$company, $owner] = $this->makeOwner();
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
            'show_signature' => true, 'signature_label_en' => 'Authorized Signature',
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $response = $this->get(route('app.invoices.show', $invoice));
        $html = $response->getContent();

        $response->assertOk();
        $signaturePos = strpos($html, 'Authorized Signature');
        $footerLinePos = strpos($html, 'mt-10 border-t border-slate-100');

        $this->assertNotFalse($signaturePos);
        $this->assertNotFalse($footerLinePos);
        $this->assertLessThan($footerLinePos, $signaturePos);
    }
}
