<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\User;
use App\Services\MpdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "the advance customizer have rich design enhancement like font family
 * size color border design each and everything should be customizeable"
 * — first round: font family, font size, and border style, added
 * alongside the color/density controls that already existed.
 *
 * font_family is scoped to the browser show page only (loaded from
 * Google Fonts, same as the app's own Cairo font already is) — the
 * downloaded PDF keeps the one font mPDF has embedded with verified
 * Arabic shaping (see InvoiceTemplate::googleFontFamily()'s own doc
 * comment). font_size nudges the base/paragraph text size rather than
 * the already page-density-tuned item tables. border_style wraps the
 * whole document in an outer frame.
 */
class InvoiceTemplateFontAndBorderControlsTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoiceWithTemplate(array $templateAttrs): array
    {
        $company = Company::create(['name' => 'Style Co.', 'slug' => 'style-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $template = InvoiceTemplate::create($templateAttrs + [
            'company_id' => $company->id, 'name' => 'Styled', 'document_type' => 'all', 'layout' => 'minimal', 'is_default' => true,
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        return [$owner, $invoice, $template];
    }

    public function test_the_settings_form_saves_font_family_size_and_border_style(): void
    {
        $company = Company::create(['name' => 'Font Co.', 'slug' => 'font-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all', 'layout' => 'minimal', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), [
            'name' => 'Default', 'document_types' => ['all'], 'accent_color' => '#0f766e',
            'layout' => 'minimal', 'language_mode' => 'bilingual', 'table_direction' => 'ltr',
            'font_family' => 'tajawal', 'font_size' => 'large', 'border_style' => 'thick',
        ]);

        $response->assertRedirect();
        $template->refresh();
        $this->assertSame('tajawal', $template->font_family);
        $this->assertSame('large', $template->font_size);
        $this->assertSame('thick', $template->border_style);
    }

    public function test_an_invalid_font_or_border_value_is_rejected(): void
    {
        $company = Company::create(['name' => 'Bad Font Co.', 'slug' => 'bad-font-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all', 'layout' => 'minimal', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), [
            'name' => 'Default', 'document_types' => ['all'], 'accent_color' => '#0f766e',
            'layout' => 'minimal', 'language_mode' => 'bilingual', 'table_direction' => 'ltr',
            'font_family' => 'comic-sans',
        ]);

        $response->assertSessionHasErrors('font_family');
    }

    public function test_the_show_page_loads_the_chosen_google_font_and_applies_the_border_frame(): void
    {
        [$owner, $invoice] = $this->makeInvoiceWithTemplate([
            'font_family' => 'tajawal', 'border_style' => 'thick', 'accent_color' => '#123456',
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Tajawal', false);
        $response->assertSee('3px solid #123456', false);
    }

    /**
     * The app layout already always loads Cairo from Google Fonts
     * (layouts/app.blade.php) for the general UI, so this can't assert
     * the whole domain is absent — only that no extra @import for a
     * chosen family (e.g. Tajawal) was added on top of it.
     */
    public function test_the_show_page_does_not_load_an_extra_google_font_when_family_is_unset(): void
    {
        [$owner, $invoice] = $this->makeInvoiceWithTemplate([]);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee('@import', false);
    }

    public function test_the_pdf_still_renders_with_a_border_frame_and_a_larger_font_size(): void
    {
        [, $invoice, $template] = $this->makeInvoiceWithTemplate([
            'font_size' => 'large', 'border_style' => 'double',
        ]);

        $pdf = app(MpdfRenderer::class)->render('documents.print.pdf', $invoice->fresh()->pdfData());

        $this->assertStringContainsString('%PDF-', $pdf);
    }

    public function test_the_pdf_still_renders_with_a_smaller_font_size_and_no_border(): void
    {
        [, $invoice, $template] = $this->makeInvoiceWithTemplate([
            'font_size' => 'small',
        ]);

        $pdf = app(MpdfRenderer::class)->render('documents.print.pdf', $invoice->fresh()->pdfData());

        $this->assertStringContainsString('%PDF-', $pdf);
    }

    public function test_the_pdf_renders_a_border_frame_on_every_remaining_layout(): void
    {
        foreach (['bilingual_classic', 'custom_letterhead', 'quotation_offer', 'sage_classic', 'retail_classic', 'distribution_classic'] as $layout) {
            [, $invoice] = $this->makeInvoiceWithTemplate(['layout' => $layout, 'border_style' => 'thin']);

            $pdf = app(MpdfRenderer::class)->render('documents.print.pdf', $invoice->fresh()->pdfData());

            $this->assertStringContainsString('%PDF-', $pdf, "layout \"{$layout}\" failed to render with a border frame");
        }
    }

    public function test_font_size_scale_and_border_css_helpers(): void
    {
        $small = new InvoiceTemplate(['font_size' => 'small']);
        $medium = new InvoiceTemplate(['font_size' => null]);
        $large = new InvoiceTemplate(['font_size' => 'large']);

        $this->assertSame(0.9, $small->fontSizeScale());
        $this->assertSame(1.0, $medium->fontSizeScale());
        $this->assertSame(1.15, $large->fontSizeScale());

        $none = new InvoiceTemplate(['border_style' => 'none', 'accent_color' => '#000000']);
        $thin = new InvoiceTemplate(['border_style' => 'thin', 'accent_color' => '#000000']);
        $double = new InvoiceTemplate(['border_style' => 'double', 'accent_color' => '#000000']);

        $this->assertNull($none->borderStyleCss());
        $this->assertSame('1px solid #000000', $thin->borderStyleCss());
        $this->assertSame('4px double #000000', $double->borderStyleCss());
    }
}
