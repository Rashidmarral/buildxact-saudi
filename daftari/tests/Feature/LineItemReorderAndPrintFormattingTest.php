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

    // ------------------------------------------------------------------
    // The three fixes above only touched documents.print.body, which
    // renders the on-screen page and the browser's own Print button.
    // "Download PDF" and "Email PDF" go through a completely separate
    // template (documents.print.pdf + its pdf-notes-stamp/pdf-signature
    // partials) built for mPDF, which doesn't support the flex/grid CSS
    // body.blade.php uses — it still had the old unit-name, plain-
    // paragraph-notes and signature-after-footer code. These tests
    // render that template the same way MpdfRenderer does (the actual
    // HTML mPDF converts to a PDF), without invoking mPDF itself.
    // ------------------------------------------------------------------

    private function renderPdfHtml(Invoice $invoice): string
    {
        return view('documents.print.pdf', $invoice->pdfData() + ['embed' => fn () => null])->render();
    }

    public function test_the_downloaded_pdf_shows_the_unit_symbol_not_the_full_unit_name(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $unit = Unit::create(['company_id' => $company->id, 'name' => 'Square Meter', 'name_ar' => 'متر مربع', 'symbol' => 'm²', 'code' => 'MTK']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Tiling', 'unit_price' => 100]);

        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $item->id, 'unit_id' => $unit->id, 'description' => 'Tiling', 'quantity' => 5, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $html = $this->renderPdfHtml($invoice);

        $this->assertStringContainsString('m²', $html);
        $this->assertStringNotContainsString('Square Meter', $html);
    }

    /**
     * The tests above render the intermediate HTML directly (fast, and
     * precise about what changed) — this one goes through the real
     * "Download PDF" route end to end, actually invoking mPDF, since its
     * HTML parser is stricter than a browser's and could reject the new
     * table/border-radius bullet markup in ways Blade rendering alone
     * would never catch.
     */
    public function test_the_real_download_pdf_route_still_produces_a_valid_pdf_with_bulleted_notes_and_a_repositioned_signature(): void
    {
        [$company, $owner] = $this->makeOwner();
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
            'show_signature' => true, 'signature_label_en' => 'Authorized Signature',
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
        $response = $this->get(route('app.invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_downloaded_pdf_renders_notes_and_terms_as_a_list_not_a_plain_paragraph(): void
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
        $html = $this->renderPdfHtml($invoice);

        // Each line becomes its own table row with a bullet dot, so a
        // 2-line notes field plus a 2-line terms field is 4 bullet rows —
        // the old markup had none, just a single whitespace:pre-line div.
        $this->assertGreaterThanOrEqual(4, substr_count($html, 'border-radius: 3px'));
        $this->assertStringNotContainsString('white-space: pre-line', $html);
        $this->assertStringContainsString('Thank you for your business', $html);
        $this->assertStringContainsString('Payment due within 15 days', $html);
    }

    public function test_the_downloaded_pdf_signature_renders_above_the_closing_footer_line_not_below_it(): void
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
        $html = $this->renderPdfHtml($invoice);

        $signaturePos = strpos($html, 'Authorized Signature');
        $footerLinePos = strpos($html, 'class="footer-note"');

        $this->assertNotFalse($signaturePos);
        $this->assertNotFalse($footerLinePos);
        $this->assertLessThan($footerLinePos, $signaturePos);
    }

    // ------------------------------------------------------------------
    // Bug report: a bilingual-mode document's template Notes showed
    // Arabic only. notesFor()/termsFor() picked a single language keyed
    // off app()->getLocale() — the current VIEWER's own UI language
    // (e.g. an admin whose account is set to Arabic), not the document's
    // language_mode. Simulating that exact trigger below: the app locale
    // is set to Arabic while the template's language_mode is bilingual,
    // which used to make notesFor()/termsFor() return notes_ar/terms_ar
    // only — English should still appear as the primary language.
    // ------------------------------------------------------------------

    public function test_bilingual_template_notes_and_terms_show_both_languages_even_when_the_viewers_own_locale_is_arabic(): void
    {
        [$company, $owner] = $this->makeOwner();
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'language_mode' => 'bilingual', 'is_default' => true,
            'notes_en' => 'Thank you for your business',
            'notes_ar' => 'شكرا لتعاملكم معنا',
            'terms_en' => 'Payment due within 15 days',
            'terms_ar' => 'الدفع خلال 15 يومًا',
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

        app()->setLocale('ar');

        $response = $this->get(route('app.invoices.show', $invoice));
        $response->assertOk();
        $response->assertSee('Thank you for your business');
        $response->assertSee('شكرا لتعاملكم معنا');
        $response->assertSee('Payment due within 15 days');
        $response->assertSee('الدفع خلال 15 يومًا');

        $html = $this->renderPdfHtml($invoice->fresh());
        $this->assertStringContainsString('Thank you for your business', $html);
        $this->assertStringContainsString('شكرا لتعاملكم معنا', $html);
        $this->assertStringContainsString('Payment due within 15 days', $html);
        $this->assertStringContainsString('الدفع خلال 15 يومًا', $html);
    }

    public function test_english_only_template_notes_never_show_arabic(): void
    {
        [$company, $owner] = $this->makeOwner();
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'language_mode' => 'english_only', 'is_default' => true,
            'notes_en' => 'English only note',
            'notes_ar' => 'ملاحظة بالعربية فقط',
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

        $response->assertOk();
        $response->assertSee('English only note');
        $response->assertDontSee('ملاحظة بالعربية فقط');
    }
}
