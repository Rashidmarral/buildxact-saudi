<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follow-up to the Quotation Offer layout: a user asked whether
 * activating it would also change their Invoice PDFs. It would, if
 * activated via the gallery (deliberately global — "one look everywhere",
 * see the gallery feature) — but the pre-existing per-document-type
 * override system (Advanced customization, Company::defaultTemplateFor())
 * already supports exactly the mixed setup they actually want: Quotation
 * Offer for Quotations only, a different layout (e.g. Bilingual Classic)
 * for everything else. This exercises that real mixed setup end-to-end.
 *
 * Also caught and fixed a real bug while confirming this path works: the
 * Advanced customization editor's Layout <select> never had a
 * "Quotation Offer" option — a template already carrying that layout
 * (e.g. one made via the gallery) would silently fall back to whichever
 * option happened to be first in the list the moment anyone re-saved
 * that form, corrupting the choice.
 */
class PerDocumentTypeQuotationOfferOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_quotation_specific_template_does_not_affect_invoices(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        // The company-wide default (covers Invoices and anything without
        // its own override) — Bilingual Classic, as the user wants.
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Bilingual default', 'document_type' => 'all',
            'layout' => 'bilingual_classic', 'language_mode' => 'bilingual', 'is_default' => true,
        ]);

        // A Quotation-only override using the new layout.
        $quotationTemplate = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Quotation Offer', 'document_type' => 'quotation',
            'layout' => 'quotation_offer', 'language_mode' => 'arabic_only', 'is_default' => true,
        ]);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Saed Est.', 'name_ar' => 'مؤسسة سائد']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Line', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-1',
            'type' => 'quotation', 'status' => 'issued', 'issue_date' => now()->toDateString(),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'description' => 'Line', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $this->assertSame($quotationTemplate->id, $company->fresh()->defaultTemplateFor('quotation')->id);
        $this->assertSame('bilingual_classic', $company->defaultTemplateFor('invoice')->layout);

        $invoiceResponse = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));
        $invoiceResponse->assertOk();
        $invoiceResponse->assertDontSee('Dear Sirs,');

        $quotationResponse = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));
        $quotationResponse->assertOk();
        $quotationResponse->assertSee('السلام عليكم ورحمة الله وبركاته،');
    }

    public function test_the_advanced_editor_layout_dropdown_offers_quotation_offer(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Quotation Offer', 'document_type' => 'quotation',
            'layout' => 'quotation_offer', 'language_mode' => 'arabic_only', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoice-templates.index', ['template' => $template->id]));

        $response->assertOk();
        $response->assertSee('<option value="quotation_offer" selected', false);
    }

    /**
     * Re-saving a quotation_offer template through the advanced editor
     * (e.g. to tweak an unrelated field like the accent color) must keep
     * layout=quotation_offer — this is the exact corruption scenario the
     * missing dropdown option would have caused.
     */
    public function test_resaving_a_quotation_offer_template_keeps_its_layout(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Quotation Offer', 'document_type' => 'quotation',
            'layout' => 'quotation_offer', 'language_mode' => 'arabic_only', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), [
            'name' => 'Quotation Offer', 'document_type' => 'quotation', 'accent_color' => '#123456',
            'layout' => 'quotation_offer', 'language_mode' => 'arabic_only', 'table_direction' => 'ltr',
        ]);

        $response->assertRedirect();
        $this->assertSame('quotation_offer', $template->fresh()->layout);
    }
}
