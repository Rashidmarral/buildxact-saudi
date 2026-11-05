<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA follow-up finding: Saudi VAT Implementing Regulations Article
 * 53(5) requires a tax invoice to be issued in Arabic — a translation
 * into another language may accompany it, but cannot replace it. The
 * invoice-template editor let a company set language_mode=english_only
 * on a design covering document_type 'invoice' (or 'all', which
 * includes it), producing a tax invoice with no Arabic on it at all.
 * Quotations, vouchers, statements, reports and letters carry no such
 * requirement and stay unrestricted.
 */
class TaxInvoiceArabicLanguageEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Lang Enforce Co.', 'slug' => 'lang-enforce-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'My Template',
            'document_types' => ['invoice'],
            'accent_color' => '#0f766e',
            'layout' => 'minimal',
            'language_mode' => 'english_only',
            'table_direction' => 'ltr',
        ], $overrides);
    }

    public function test_english_only_is_rejected_for_a_template_covering_invoices(): void
    {
        [$company, $owner] = $this->makeOwner();
        $template = InvoiceTemplate::create(['company_id' => $company->id, 'name' => 'T', 'document_type' => 'invoice', 'layout' => 'minimal', 'is_default' => true]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->updatePayload());

        $response->assertSessionHasErrors('language_mode');
        $this->assertNotSame('english_only', $template->fresh()->language_mode);
    }

    public function test_english_only_is_rejected_for_a_template_covering_all_document_types(): void
    {
        [$company, $owner] = $this->makeOwner();
        $template = InvoiceTemplate::create(['company_id' => $company->id, 'name' => 'T', 'document_type' => 'all', 'layout' => 'minimal', 'is_default' => true]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->updatePayload(['document_types' => ['all']]));

        $response->assertSessionHasErrors('language_mode');
    }

    public function test_english_only_is_still_allowed_for_a_template_covering_only_quotations(): void
    {
        [$company, $owner] = $this->makeOwner();
        $template = InvoiceTemplate::create(['company_id' => $company->id, 'name' => 'T', 'document_type' => 'quotation', 'layout' => 'minimal', 'is_default' => true]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->updatePayload(['document_types' => ['quotation']]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('english_only', $template->fresh()->language_mode);
    }

    public function test_arabic_only_and_bilingual_remain_allowed_for_invoices(): void
    {
        [$company, $owner] = $this->makeOwner();
        $template = InvoiceTemplate::create(['company_id' => $company->id, 'name' => 'T', 'document_type' => 'invoice', 'layout' => 'minimal', 'is_default' => true]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->updatePayload(['language_mode' => 'arabic_only']));

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('arabic_only', $template->fresh()->language_mode);
    }

    public function test_an_english_only_preset_cannot_be_applied_to_invoices_via_use_template(): void
    {
        [$company, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.invoice-templates.use'), [
            'preset' => 'quotation_offer_en',
            'document_types' => ['invoice'],
        ]);

        $response->assertSessionHasErrors('document_types');
        $this->assertSame(0, InvoiceTemplate::where('company_id', $company->id)->count());
    }

    public function test_an_english_only_preset_can_still_be_applied_to_quotations(): void
    {
        [$company, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.invoice-templates.use'), [
            'preset' => 'quotation_offer_en',
            'document_types' => ['quotation'],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('english_only', InvoiceTemplate::where('company_id', $company->id)->first()->language_mode);
    }
}
