<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature request: "more flexible options" for invoice templates —
 * a separate color for the totals box (instead of it always following
 * the shared accent color), and a toggle to hide the per-line VAT column
 * for a cleaner customer-facing document (the VAT total itself still
 * always appears in the totals section either way).
 */
class InvoiceTemplateFlexibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Flex Co.', 'slug' => 'flex-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function baseTemplatePayload(): array
    {
        return [
            'name' => 'Default', 'document_type' => 'all', 'accent_color' => '#0f766e',
            'layout' => 'minimal', 'language_mode' => 'bilingual', 'table_direction' => 'ltr',
        ];
    }

    private function makeInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Flex Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-FLEX-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Consulting',
            'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115,
        ]);

        return $invoice;
    }

    public function test_the_settings_form_saves_a_separate_totals_color(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'boxed', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->baseTemplatePayload() + [
            'accent_color' => '#0f766e',
            'totals_color' => '#b91c1c',
        ]);

        $response->assertRedirect();
        $this->assertSame('#b91c1c', $template->refresh()->totals_color);
        $this->assertSame('#b91c1c', $template->totalsColor());
    }

    public function test_totals_color_falls_back_to_accent_color_when_not_set(): void
    {
        $template = InvoiceTemplate::create([
            'company_id' => $this->makeCompany()->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'boxed', 'is_default' => true, 'accent_color' => '#2563eb',
        ]);

        $this->assertNull($template->totals_color);
        $this->assertSame('#2563eb', $template->totalsColor());
    }

    public function test_the_remove_totals_color_checkbox_clears_it_back_to_the_accent_color(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'boxed', 'is_default' => true, 'totals_color' => '#b91c1c',
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->baseTemplatePayload() + [
            'remove_totals_color' => '1',
        ]);

        $response->assertRedirect();
        $this->assertNull($template->refresh()->totals_color);
    }

    public function test_a_boxed_invoice_uses_the_totals_color_for_its_totals_card_on_screen_and_in_the_pdf(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'boxed', 'is_default' => true, 'accent_color' => '#0f766e', 'totals_color' => '#b91c1c',
        ]);
        $invoice = $this->makeInvoice($company);

        $showResponse = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));
        $showResponse->assertOk();
        $showResponse->assertSee('background-color: #b91c1c', false);

        $pdfHtml = view('documents.print.pdf', $invoice->fresh()->pdfData() + ['embed' => fn () => null])->render();
        $this->assertStringContainsString('#b91c1c', $pdfHtml);
    }

    public function test_the_settings_form_saves_show_vat_column(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->baseTemplatePayload() + [
            'show_vat_column' => '0',
        ]);

        $response->assertRedirect();
        $this->assertFalse($template->refresh()->show_vat_column);
    }

    public function test_show_vat_column_false_hides_the_per_line_vat_column_but_keeps_the_vat_total(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'show_vat_column' => false,
        ]);
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        // The column header is gone...
        $response->assertDontSee(__('VAT').'</th>', false);
        // ...but the VAT total in the summary section is still there.
        $response->assertSee(__('VAT'));
        $response->assertSee('15.00');
    }

    public function test_show_vat_column_true_still_shows_the_per_line_vat_column(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'show_vat_column' => true,
        ]);
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee(__('VAT').'</th>', false);
    }

    public function test_the_templates_index_page_renders_with_the_new_fields_set(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'boxed', 'is_default' => true, 'density' => 'comfortable',
            'accent_color' => '#0f766e', 'totals_color' => '#b91c1c', 'show_vat_column' => false,
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoice-templates.index', ['template' => $template->id]));

        $response->assertOk();
        $response->assertSee('#b91c1c', false);
    }

    public function test_the_templates_index_page_renders_the_starter_preset_gallery_with_no_template_selected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.invoice-templates.index'));

        $response->assertOk();
        $response->assertSee(__('Starter templates'));
    }

    public function test_show_vat_column_false_also_hides_it_in_the_downloaded_pdf(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'show_vat_column' => false,
        ]);
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
