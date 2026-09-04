<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Template rework: invoice_templates gained language_mode (bilingual /
 * english_only / arabic_only), table_direction (ltr / rtl) and a
 * show_signature block. This exercises a real HTTP render of the invoice
 * show page and the downloaded PDF under each language mode and each of
 * the 5 print layouts, with a client and item that actually carry Arabic
 * names — Blade::compileString() alone would not have caught the earlier
 * @php/@endphp collision bug in this same view tree, so these are real
 * GET requests through the full controller + view stack.
 */
class InvoiceTemplateLanguageModeTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(string $languageMode, string $layout, bool $signature = false): Invoice
    {
        $company = Company::create(['name' => 'Bilingual Test Co.', 'name_ar' => 'شركة الاختبار', 'slug' => 'bilingual-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($owner);

        InvoiceTemplate::create([
            'company_id' => $company->id,
            'name' => 'Test Template',
            'document_type' => 'all',
            'layout' => $layout,
            'language_mode' => $languageMode,
            'table_direction' => $languageMode === 'arabic_only' ? 'rtl' : 'ltr',
            'show_signature' => $signature,
            'signature_label_en' => 'Authorized Signature',
            'signature_label_ar' => 'التوقيع المعتمد',
            'is_default' => true,
        ]);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Acme Trading', 'name_ar' => 'أكمي للتجارة']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Steel Beam', 'name_ar' => 'عارضة فولاذية', 'unit_price' => 100]);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => $item->name,
            'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115,
        ]);

        return $invoice;
    }

    public static function layoutAndModeProvider(): array
    {
        $layouts = ['minimal', 'bordered', 'boxed', 'bilingual_classic', 'custom_letterhead'];
        $modes = ['bilingual', 'english_only', 'arabic_only'];

        $cases = [];
        foreach ($layouts as $layout) {
            foreach ($modes as $mode) {
                $cases["{$layout}/{$mode}"] = [$layout, $mode];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider layoutAndModeProvider
     */
    public function test_invoice_show_page_renders_for_every_layout_and_language_mode(string $layout, string $mode): void
    {
        $invoice = $this->makeInvoice($mode, $layout, signature: true);

        $response = $this->get(route('app.invoices.show', $invoice));

        $response->assertOk();
    }

    /**
     * @dataProvider layoutAndModeProvider
     */
    public function test_invoice_pdf_download_renders_for_every_layout_and_language_mode(string $layout, string $mode): void
    {
        $invoice = $this->makeInvoice($mode, $layout, signature: true);

        $response = $this->get(route('app.invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_bilingual_mode_shows_both_english_and_arabic_item_names(): void
    {
        $invoice = $this->makeInvoice('bilingual', 'minimal');

        $response = $this->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Steel Beam');
        $response->assertSee('عارضة فولاذية');
    }

    public function test_english_only_mode_hides_the_arabic_item_name(): void
    {
        $invoice = $this->makeInvoice('english_only', 'minimal');

        $response = $this->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Steel Beam');
        $response->assertDontSee('عارضة فولاذية');
    }

    public function test_arabic_only_mode_shows_the_arabic_item_name_as_primary(): void
    {
        $invoice = $this->makeInvoice('arabic_only', 'minimal');

        $response = $this->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('عارضة فولاذية');
    }

    public function test_template_settings_screen_saves_language_mode_direction_and_signature(): void
    {
        $company = Company::create(['name' => 'Settings Co.', 'slug' => 'settings-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), [
            'name' => 'Default', 'document_type' => 'all', 'accent_color' => '#0f766e',
            'layout' => 'minimal', 'language_mode' => 'arabic_only', 'table_direction' => 'rtl',
            'show_signature' => '1', 'signature_label_en' => 'Signed', 'signature_label_ar' => 'موقّع',
        ]);

        $response->assertRedirect();
        $template->refresh();
        $this->assertSame('arabic_only', $template->language_mode);
        $this->assertSame('rtl', $template->table_direction);
        $this->assertTrue($template->show_signature);
        $this->assertSame('Signed', $template->signature_label_en);
        $this->assertSame('موقّع', $template->signature_label_ar);
    }
}
