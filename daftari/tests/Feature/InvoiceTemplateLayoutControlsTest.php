<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
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
 * Feature request: "full control to make their invoices layout as they
 * want, each and everything" — expands the existing template settings
 * form with a table header color, unit-label and client-VAT-number
 * visibility toggles, a PDF paper size choice, and a separate Terms &
 * Conditions block (distinct from the existing footer note).
 */
class InvoiceTemplateLayoutControlsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Layout Co.', 'slug' => 'layout-'.uniqid()]);
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

    public function test_the_settings_form_saves_the_new_layout_control_fields(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->baseTemplatePayload() + [
            'table_header_color' => '#1e293b',
            'page_size' => 'letter',
            'show_unit_labels' => '0',
            'show_party_vat_number' => '0',
            'terms_en' => 'Payment due within 30 days.',
            'terms_ar' => 'يستحق الدفع خلال 30 يومًا.',
        ]);

        $response->assertRedirect();
        $template->refresh();

        $this->assertSame('#1e293b', $template->table_header_color);
        $this->assertSame('letter', $template->page_size);
        $this->assertFalse($template->show_unit_labels);
        $this->assertFalse($template->show_party_vat_number);
        $this->assertSame('Payment due within 30 days.', $template->terms_en);
        $this->assertSame('يستحق الدفع خلال 30 يومًا.', $template->terms_ar);
    }

    public function test_the_remove_table_header_color_checkbox_clears_it_back_to_the_layout_default(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'table_header_color' => '#1e293b',
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->baseTemplatePayload() + [
            'remove_table_header_color' => '1',
        ]);

        $response->assertRedirect();
        $this->assertNull($template->refresh()->table_header_color);
    }

    private function makeInvoiceWithUnitItem(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Layout Client']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Cement Bag', 'unit' => 'pcs', 'unit_price' => 20]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-LAYOUT-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 20, 'vat_total' => 3, 'total' => 23,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => 'Cement Bag',
            'quantity' => 1, 'unit_price' => 20, 'vat_rate' => 15, 'vat_amount' => 3, 'line_total' => 23,
        ]);

        return $invoice;
    }

    public function test_show_unit_labels_false_hides_the_unit_text_next_to_quantity(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'show_unit_labels' => false,
        ]);
        $invoice = $this->makeInvoiceWithUnitItem($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee('pcs');
    }

    public function test_show_unit_labels_true_shows_the_unit_text_next_to_quantity(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'show_unit_labels' => true,
        ]);
        $invoice = $this->makeInvoiceWithUnitItem($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('pcs');
    }

    public function test_show_party_vat_number_false_hides_the_clients_vat_number(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'show_party_vat_number' => false,
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'VAT Client', 'vat_number' => '300012345600099']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-LAYOUT-2',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Item', 'quantity' => 1,
            'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115,
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee('300012345600099');
    }

    public function test_terms_and_conditions_render_as_their_own_section_separate_from_the_footer_note(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
            'notes_en' => 'Thank you for your business.',
            'terms_en' => 'Late payments incur a 2% monthly fee.',
        ]);
        $invoice = $this->makeInvoiceWithUnitItem($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Thank you for your business.');
        $response->assertSee('Late payments incur a 2% monthly fee.');
        $response->assertSee(__('Terms & Conditions'));
    }

    public function test_the_pdf_still_downloads_with_letter_page_size_and_the_new_controls_set(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'page_size' => 'letter',
            'table_header_color' => '#1e293b', 'show_unit_labels' => false, 'show_party_vat_number' => false,
            'terms_en' => 'Standard terms apply.',
        ]);
        $invoice = $this->makeInvoiceWithUnitItem($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_settings_form_saves_show_item_description(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->baseTemplatePayload() + [
            'show_item_description' => '0',
        ]);

        $response->assertRedirect();
        $this->assertFalse($template->refresh()->show_item_description);
    }

    private function makeInvoiceWithDescribedItem(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Description Client']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Bearing', 'description' => 'SKF 6205 sealed ball bearing', 'unit_price' => 150]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-DESC-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 150, 'vat_total' => 22.5, 'total' => 172.5,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => 'Bearing',
            'item_description' => 'SKF 6205 sealed ball bearing',
            'quantity' => 1, 'unit_price' => 150, 'vat_rate' => 15, 'vat_amount' => 22.5, 'line_total' => 172.5,
        ]);

        return $invoice;
    }

    public function test_show_item_description_true_shows_the_items_description_on_the_invoice(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'show_item_description' => true,
        ]);
        $invoice = $this->makeInvoiceWithDescribedItem($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('SKF 6205 sealed ball bearing');
    }

    public function test_show_item_description_false_hides_the_items_description_on_the_invoice(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'show_item_description' => false,
        ]);
        $invoice = $this->makeInvoiceWithDescribedItem($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee('SKF 6205 sealed ball bearing');
    }
}
