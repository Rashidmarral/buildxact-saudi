<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Zatca\ZatcaSyncService;
use App\Services\Zatca\ZatcaXmlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ZATCA compliance audit against the official spec PDF (section 13.3.1,
 * BR-KSA-23/24/69): a Zero-rated (Z) or Exempt (E) VAT category line in
 * the document-level cac:TaxSubtotal breakdown must carry a VATEX-SA-*
 * cbc:TaxExemptionReasonCode (and text) — Daftari's TaxRate model
 * previously had no field to capture one at all, so every zero-rated or
 * exempt invoice this generator ever produced was non-compliant.
 */
class ZatcaVatExemptionReasonTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '300012345600003']);
    }

    private function makeInvoiceWithRate(Company $company, TaxRate $rate): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Export Client', 'vat_number' => '300012345600003']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => 'SAR',
        ]);

        $item = new InvoiceItem([
            'invoice_id' => $invoice->id, 'description' => 'Exported goods', 'quantity' => 1,
            'unit_price' => 500, 'vat_rate' => $rate->rate, 'tax_rate_id' => $rate->id, 'sort_order' => 0,
        ]);
        $item->recalculate();
        $item->save();

        $invoice->refresh();
        $invoice->recalculateTotals();
        $invoice->load('items.taxRate', 'company', 'client');

        return $invoice;
    }

    public function test_a_zero_rated_line_with_a_reason_code_carries_it_in_the_tax_breakdown(): void
    {
        $company = $this->makeCompany();
        $rate = TaxRate::create(['company_id' => $company->id, 'name' => 'Export', 'rate' => 0, 'type' => TaxRate::TYPE_ZERO_RATED, 'exemption_reason_code' => 'VATEX-SA-32', 'is_active' => true]);
        $invoice = $this->makeInvoiceWithRate($company, $rate);

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid', 1);

        $this->assertStringContainsString('<cbc:TaxExemptionReasonCode>VATEX-SA-32</cbc:TaxExemptionReasonCode>', $xml);
        $this->assertStringContainsString('<cbc:TaxExemptionReason>Export of goods</cbc:TaxExemptionReason>', $xml);
    }

    public function test_an_exempt_line_with_a_reason_code_carries_it_in_the_tax_breakdown(): void
    {
        $company = $this->makeCompany();
        $rate = TaxRate::create(['company_id' => $company->id, 'name' => 'Financial services', 'rate' => 0, 'type' => TaxRate::TYPE_EXEMPT, 'exemption_reason_code' => 'VATEX-SA-29', 'is_active' => true]);
        $invoice = $this->makeInvoiceWithRate($company, $rate);

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid', 1);

        $this->assertStringContainsString('<cbc:TaxExemptionReasonCode>VATEX-SA-29</cbc:TaxExemptionReasonCode>', $xml);
    }

    /**
     * BR-S-10: a Standard-rated breakdown must never carry an exemption
     * reason code or text at all.
     */
    public function test_a_standard_rated_line_never_carries_an_exemption_reason(): void
    {
        $company = $this->makeCompany();
        TaxRate::seedDefaults($company->id);
        $rate = TaxRate::where('company_id', $company->id)->where('type', TaxRate::TYPE_STANDARD)->firstOrFail();
        $invoice = $this->makeInvoiceWithRate($company, $rate);

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid', 1);

        $this->assertStringNotContainsString('TaxExemptionReasonCode', $xml);
        $this->assertStringNotContainsString('TaxExemptionReason', $xml);
    }

    public function test_sync_is_blocked_when_a_zero_rated_line_has_no_exemption_reason_set(): void
    {
        $company = $this->makeCompany();
        $rate = TaxRate::create(['company_id' => $company->id, 'name' => 'VAT 0%', 'rate' => 0, 'type' => TaxRate::TYPE_ZERO_RATED, 'is_active' => true]);
        $invoice = $this->makeInvoiceWithRate($company, $rate);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('ZATCA exemption reason', $log->error_message);
        $this->assertFalse($log->exists, 'The rejection must not persist a ZatcaInvoiceLog row.');
    }

    public function test_sync_is_blocked_when_an_exempt_line_has_no_exemption_reason_set(): void
    {
        $company = $this->makeCompany();
        $rate = TaxRate::create(['company_id' => $company->id, 'name' => 'Exempt', 'rate' => 0, 'type' => TaxRate::TYPE_EXEMPT, 'is_active' => true]);
        $invoice = $this->makeInvoiceWithRate($company, $rate);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('ZATCA exemption reason', $log->error_message);
    }

    public function test_sync_proceeds_past_the_exemption_check_once_a_valid_reason_is_set(): void
    {
        $company = $this->makeCompany();
        $rate = TaxRate::create(['company_id' => $company->id, 'name' => 'Export', 'rate' => 0, 'type' => TaxRate::TYPE_ZERO_RATED, 'exemption_reason_code' => 'VATEX-SA-32', 'is_active' => true]);
        $invoice = $this->makeInvoiceWithRate($company, $rate);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        // Not onboarded with ZATCA in this test, so it still fails — but
        // for an onboarding reason, not the exemption-reason guard.
        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('exemption reason', $log->error_message);
    }

    public function test_the_tax_rate_form_requires_a_reason_for_a_zero_rated_or_exempt_type(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.tax-rates.store'), [
            'name' => 'Export', 'rate' => 0, 'type' => TaxRate::TYPE_ZERO_RATED,
        ]);

        $response->assertSessionHasErrors('exemption_reason_code');
        $this->assertSame(0, TaxRate::where('company_id', $company->id)->where('name', 'Export')->count());
    }

    public function test_the_tax_rate_form_accepts_a_valid_reason_for_the_type(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.tax-rates.store'), [
            'name' => 'Export', 'rate' => 0, 'type' => TaxRate::TYPE_ZERO_RATED, 'exemption_reason_code' => 'VATEX-SA-32',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('VATEX-SA-32', TaxRate::where('company_id', $company->id)->where('name', 'Export')->value('exemption_reason_code'));
    }

    public function test_a_reason_code_from_the_wrong_category_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        // VATEX-SA-29 is an Exempt (E) reason, not valid for a Zero-rated rate.
        $response = $this->actingAs($owner)->post(route('app.tax-rates.store'), [
            'name' => 'Export', 'rate' => 0, 'type' => TaxRate::TYPE_ZERO_RATED, 'exemption_reason_code' => 'VATEX-SA-29',
        ]);

        $response->assertSessionHasErrors('exemption_reason_code');
    }

    public function test_the_tax_rate_form_rejects_a_reason_code_on_a_standard_rate(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.tax-rates.store'), [
            'name' => 'VAT 15%', 'rate' => 15, 'type' => TaxRate::TYPE_STANDARD, 'exemption_reason_code' => 'VATEX-SA-32',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertNull(TaxRate::where('company_id', $company->id)->where('name', 'VAT 15%')->value('exemption_reason_code'));
    }

    /**
     * ZATCA's VAT category O ("Not subject to VAT", spec section 11.2.4 /
     * BR-KSA-18/24/78/83) has no fixed reason text list the way Zero-rated
     * (Z) and Exempt (E) do — it always uses the single fixed VATEX-SA-OOS
     * code, but the reason TEXT is free text the taxpayer supplies "on a
     * case to case basis" per the spec's own wording. Daftari's TaxRate
     * model previously had no type for this category at all.
     */
    public function test_a_not_subject_to_vat_line_carries_the_fixed_code_and_its_own_free_text_reason(): void
    {
        $company = $this->makeCompany();
        $rate = TaxRate::create([
            'company_id' => $company->id, 'name' => 'Out of scope', 'rate' => 0, 'type' => TaxRate::TYPE_NOT_SUBJECT,
            'exemption_reason_code' => 'VATEX-SA-OOS', 'exemption_reason_text' => 'Supply made entirely outside the Kingdom of Saudi Arabia.', 'is_active' => true,
        ]);
        $invoice = $this->makeInvoiceWithRate($company, $rate);

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid', 1);

        $this->assertStringContainsString('<cbc:ID>O</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:TaxExemptionReasonCode>VATEX-SA-OOS</cbc:TaxExemptionReasonCode>', $xml);
        $this->assertStringContainsString('<cbc:TaxExemptionReason>Supply made entirely outside the Kingdom of Saudi Arabia.</cbc:TaxExemptionReason>', $xml);
    }

    public function test_sync_is_blocked_when_a_not_subject_to_vat_line_has_no_free_text_reason_set(): void
    {
        $company = $this->makeCompany();
        // Simulates a row saved before this column existed, or created
        // directly rather than through the validated controller path.
        $rate = TaxRate::create(['company_id' => $company->id, 'name' => 'Out of scope', 'rate' => 0, 'type' => TaxRate::TYPE_NOT_SUBJECT, 'exemption_reason_code' => 'VATEX-SA-OOS', 'is_active' => true]);
        $invoice = $this->makeInvoiceWithRate($company, $rate);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('ZATCA exemption reason', $log->error_message);
    }

    public function test_the_tax_rate_form_requires_free_text_for_a_not_subject_to_vat_type(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.tax-rates.store'), [
            'name' => 'Out of scope', 'rate' => 0, 'type' => TaxRate::TYPE_NOT_SUBJECT,
        ]);

        $response->assertSessionHasErrors('exemption_reason_text');
        $this->assertSame(0, TaxRate::where('company_id', $company->id)->where('name', 'Out of scope')->count());
    }

    public function test_the_tax_rate_form_accepts_a_not_subject_to_vat_type_with_free_text_and_auto_sets_the_fixed_code(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.tax-rates.store'), [
            'name' => 'Out of scope', 'rate' => 0, 'type' => TaxRate::TYPE_NOT_SUBJECT, 'exemption_reason_text' => 'Outside KSA VAT scope.',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $rate = TaxRate::where('company_id', $company->id)->where('name', 'Out of scope')->firstOrFail();
        $this->assertSame('VATEX-SA-OOS', $rate->exemption_reason_code);
        $this->assertSame('Outside KSA VAT scope.', $rate->exemption_reason_text);
    }
}
