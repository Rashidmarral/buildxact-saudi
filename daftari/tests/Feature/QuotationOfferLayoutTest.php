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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature request: a user shared a real price-quotation document as a
 * reference and asked for it to be added to the app's PDF layouts "100%
 * same design" — logo top-left, an unboxed No./Date/Hijri date/C.R. block
 * top-right, a centered title, a "Messrs. X — Peace be upon you"
 * salutation, the items table with totals appended below it, a numbered
 * payment-terms section, a signature+stamp+phone block, and a bilingual
 * address footer — as two presets (one Arabic, one English), same
 * structure, different language throughout.
 *
 * This is a new layout in the shared documents.print.pdf/body system
 * (see InvoiceTemplatePresets::quotation_offer_ar/_en), so — like the 5
 * existing layouts — it applies globally to every document type once
 * activated, not just Quotations.
 */
class QuotationOfferLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(string $languageMode): Invoice
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'name_ar' => 'شركة دايناميك كور', 'slug' => 'dcc-'.uniqid(), 'cr_number' => '7053180563', 'phone' => '0568582270']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($owner);

        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Quotation Offer', 'document_type' => 'all',
            'layout' => 'quotation_offer', 'language_mode' => $languageMode, 'is_default' => true,
        ]);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Saed Est.', 'name_ar' => 'مؤسسة سائد']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Subbase Layer', 'name_ar' => 'طبقة ما تحت الأساس', 'unit_price' => 6]);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => '2026-09-22', 'currency' => $company->currency,
            'subtotal' => 570000, 'discount_total' => 0, 'vat_total' => 85500, 'total' => 655500,
            'notes' => "20% advance payment on signing.\n30% on completion of Subbase.",
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => $item->name, 'name_ar' => $item->name_ar,
            'quantity' => 10000, 'unit_price' => 6, 'vat_rate' => 15, 'vat_amount' => 9000, 'line_total' => 69000,
        ]);

        return $invoice;
    }

    #[DataProvider('languageModes')]
    public function test_show_page_and_pdf_render_for_both_language_modes(string $mode): void
    {
        $invoice = $this->makeInvoice($mode);

        $this->get(route('app.invoices.show', $invoice))->assertOk();

        $pdfResponse = $this->get(route('app.invoices.pdf', $invoice));
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-type', 'application/pdf');
    }

    public static function languageModes(): array
    {
        return [['arabic_only'], ['english_only']];
    }

    public function test_arabic_mode_shows_arabic_salutation_and_labels(): void
    {
        $invoice = $this->makeInvoice('arabic_only');

        $response = $this->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('السادة/ مؤسسة سائد المحترمين');
        $response->assertSee('السلام عليكم ورحمة الله وبركاته،');
        $response->assertSee(__('Payment Terms', [], 'ar'));
        $response->assertSee(__('Total including VAT', [], 'ar'));
        $response->assertDontSee('Dear Sirs,');
    }

    public function test_english_mode_shows_english_salutation_and_labels(): void
    {
        $invoice = $this->makeInvoice('english_only');

        $response = $this->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Dear Sirs,');
        $response->assertSee('Payment Terms');
        $response->assertSee('Total including VAT');
        $response->assertDontSee('السلام عليكم');
    }

    /**
     * The bug this session's earlier layout work would have shipped:
     * building the English-mode labels with __() instead of the
     * locale-forcing $lbl() closure means they'd silently flip to Arabic
     * whenever the *viewer's* current UI language happens to be Arabic —
     * regardless of the template's own english_only setting. Forcing the
     * app locale to Arabic here and asserting the English-mode PDF still
     * comes out in English is exactly what would have caught that.
     */
    public function test_english_mode_stays_english_even_when_the_viewers_app_locale_is_arabic(): void
    {
        $invoice = $this->makeInvoice('english_only');
        App::setLocale('ar');

        try {
            $response = $this->get(route('app.invoices.show', $invoice));

            $response->assertOk();
            $response->assertSee('Dear Sirs,');
            $response->assertSee('Payment Terms');
            $response->assertDontSee('السلام عليكم');
        } finally {
            App::setLocale('en');
        }
    }

    public function test_the_hijri_date_appears_alongside_the_gregorian_date(): void
    {
        $invoice = $this->makeInvoice('arabic_only');

        $response = $this->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        // 2026-09-22 (Gregorian) is 11/04/1448 on the Umm al-Qura calendar.
        $response->assertSee('1448');
    }

    public function test_payment_terms_notes_render_as_a_numbered_list(): void
    {
        $invoice = $this->makeInvoice('english_only');

        $response = $this->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('20% advance payment on signing.');
        $response->assertSee('30% on completion of Subbase.');
    }

    public function test_the_layout_applies_globally_to_a_quotation_too(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Quotation Offer', 'document_type' => 'all',
            'layout' => 'quotation_offer', 'language_mode' => 'english_only', 'is_default' => true,
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Saed Est.']);
        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-1',
            'type' => 'quotation', 'status' => 'issued', 'issue_date' => now()->toDateString(),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'description' => 'Line', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));

        $response->assertOk();
        $response->assertSee('Dear Sirs,');
    }

    public function test_activating_the_arabic_and_english_presets_sets_the_right_language_mode(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'quotation_offer_ar'))->assertRedirect();
        $template = InvoiceTemplate::where('company_id', $company->id)->where('preset_key', 'quotation_offer_ar')->firstOrFail();
        $this->assertSame('quotation_offer', $template->layout);
        $this->assertSame('arabic_only', $template->language_mode);

        $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'quotation_offer_en'));
        $template->refresh();
        $enTemplate = InvoiceTemplate::where('company_id', $company->id)->where('preset_key', 'quotation_offer_en')->firstOrFail();
        $this->assertSame('quotation_offer', $enTemplate->layout);
        $this->assertSame('english_only', $enTemplate->language_mode);
        $this->assertTrue($enTemplate->is_default);
        $this->assertFalse($template->is_default);
    }

    public function test_the_gallery_lists_both_new_presets(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.invoice-templates.gallery'));

        $response->assertOk();
        $response->assertSee('Quotation Offer (Arabic)');
        $response->assertSee('Quotation Offer (English)');
    }
}
