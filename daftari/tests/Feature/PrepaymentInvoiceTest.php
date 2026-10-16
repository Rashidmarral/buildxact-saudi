<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PrepaymentInvoice;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ZATCA compliance gap, now closed: a company collecting an advance or
 * deposit before the main supply had no way to issue the formal
 * "prepayment tax invoice" (InvoiceTypeCode 386) ZATCA requires for it —
 * see the create_prepayment_invoices_table migration and
 * ZatcaXmlGenerator::generateForPrepayment(). Covers the full lifecycle:
 * issuing one, syncing it to ZATCA through the same clearance/reporting
 * pipeline every other document type uses, downloading its PDF/XML, and
 * applying it to a later invoice (which must then report the reduced
 * PrepaidAmount/PayableAmount — see ZatcaPrepaymentInvoiceXmlTest for the
 * XML-level assertions on that).
 */
class PrepaymentInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Advance Co.', 'slug' => 'advance-co-'.uniqid(), 'vat_number' => '300012345600003']);
        TaxRate::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeOnboardedOwner(): array
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Advance Co.', 'slug' => 'advance-co-'.uniqid(), 'vat_number' => '300012345600003',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::TEST_CSID,
            'zatca_production_secret' => 'secret-value',
        ]);
        TaxRate::seedDefaults($company->id);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function standardRate(Company $company): TaxRate
    {
        return TaxRate::where('company_id', $company->id)->where('type', TaxRate::TYPE_STANDARD)->first();
    }

    public function test_an_owner_can_issue_a_prepayment_invoice(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client']);
        $rate = $this->standardRate($company);

        $response = $this->actingAs($owner)->post(route('app.prepayment-invoices.store'), [
            'client_id' => $client->id,
            'tax_rate_id' => $rate->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'description' => 'Advance against Contract 2026-014',
            'amount' => 10000,
        ]);

        $prepayment = PrepaymentInvoice::first();
        $response->assertRedirect(route('app.prepayment-invoices.show', $prepayment));
        $this->assertNotNull($prepayment);
        $this->assertSame('issued', $prepayment->status);
        $this->assertSame(10000.0, (float) $prepayment->amount);
        $this->assertSame(1500.0, (float) $prepayment->vat_amount);
        $this->assertSame(11500.0, (float) $prepayment->total);
        $this->assertStringStartsWith($company->refresh()->prepayment_prefix.'-', $prepayment->prepayment_number);
    }

    public function test_a_prepayment_invoice_cannot_be_created_for_another_companys_client(): void
    {
        [$company, $owner] = $this->makeOwner();
        [$otherCompany] = $this->makeOwner();
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Not Mine']);
        $rate = $this->standardRate($company);

        $response = $this->actingAs($owner)->post(route('app.prepayment-invoices.store'), [
            'client_id' => $otherClient->id,
            'tax_rate_id' => $rate->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'amount' => 5000,
        ]);

        $response->assertSessionHasErrors('client_id');
        $this->assertSame(0, PrepaymentInvoice::count());
    }

    public function test_the_index_and_show_pages_render(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client']);
        $rate = $this->standardRate($company);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'tax_rate_id' => $rate->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);

        $this->actingAs($owner)->get(route('app.prepayment-invoices.index'))->assertOk()->assertSee('PPI-00001');
        $this->actingAs($owner)->get(route('app.prepayment-invoices.show', $prepayment))->assertOk()->assertSee('PPI-00001');
    }

    public function test_the_pdf_downloads(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client']);
        $rate = $this->standardRate($company);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'tax_rate_id' => $rate->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->get(route('app.prepayment-invoices.pdf', $prepayment));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_syncing_a_prepayment_invoice_to_zatca_marks_it_cleared(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        [$company, $owner] = $this->makeOnboardedOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client', 'vat_number' => '399999999900003']);
        $rate = $this->standardRate($company);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'tax_rate_id' => $rate->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'issued', 'currency' => $company->currency,
            'issue_date' => now()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->post(route('app.zatca.sync.prepayment-invoice', $prepayment));

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $log = ZatcaInvoiceLog::where('prepayment_invoice_id', $prepayment->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('cleared', $log->status);
        $this->assertNull($log->invoice_id);
    }

    public function test_a_draft_or_cancelled_prepayment_invoice_cannot_be_synced(): void
    {
        Http::fake();
        [$company, $owner] = $this->makeOnboardedOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client']);
        $rate = $this->standardRate($company);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'tax_rate_id' => $rate->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'cancelled', 'currency' => $company->currency,
            'issue_date' => now()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);

        $log = app(\App\Services\Zatca\ZatcaSyncService::class)->submitPrepaymentInvoice($prepayment);

        Http::assertNothingSent();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('cancelled', $log->error_message);
        $this->assertFalse($log->exists, 'The rejection must not persist a ZatcaInvoiceLog row.');
        $this->assertSame(0, ZatcaInvoiceLog::where('prepayment_invoice_id', $prepayment->id)->count());
    }

    public function test_a_company_cannot_sync_another_companys_prepayment_invoice(): void
    {
        Http::fake();
        [, $owner] = $this->makeOnboardedOwner();
        [$otherCompany] = $this->makeOnboardedOwner();
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Not Mine']);
        $rate = $this->standardRate($otherCompany);
        $otherPrepayment = PrepaymentInvoice::create([
            'company_id' => $otherCompany->id, 'client_id' => $otherClient->id, 'tax_rate_id' => $rate->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'issued', 'currency' => $otherCompany->currency,
            'issue_date' => now()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->post(route('app.zatca.sync.prepayment-invoice', $otherPrepayment));

        $response->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_an_unapplied_unsynced_prepayment_invoice_can_be_deleted(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client']);
        $rate = $this->standardRate($company);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'tax_rate_id' => $rate->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->delete(route('app.prepayment-invoices.destroy', $prepayment));

        $response->assertRedirect(route('app.prepayment-invoices.index'));
        $this->assertSame(0, PrepaymentInvoice::count());
    }

    public function test_creating_an_invoice_can_apply_an_issued_prepayment_invoice(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client']);
        $rate = $this->standardRate($company);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'tax_rate_id' => $rate->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id, 'type' => 'standard', 'issue_date' => now()->toDateString(),
            'applies_prepayment_invoice_id' => $prepayment->id,
            'items' => [['description' => 'Full contract value', 'quantity' => 1, 'unit_price' => 50000, 'vat_rate' => 15]],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $invoice = $client->invoices()->first();
        $this->assertNotNull($invoice);
        $this->assertSame($prepayment->id, $invoice->applies_prepayment_invoice_id);
        $this->assertTrue($prepayment->isApplied());
    }

    public function test_an_invoice_cannot_apply_a_prepayment_invoice_belonging_to_another_client(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client']);
        $otherClient = Client::create(['company_id' => $company->id, 'name' => 'Different Client']);
        $rate = $this->standardRate($company);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $otherClient->id, 'tax_rate_id' => $rate->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id, 'type' => 'standard', 'issue_date' => now()->toDateString(),
            'applies_prepayment_invoice_id' => $prepayment->id,
            'items' => [['description' => 'Full contract value', 'quantity' => 1, 'unit_price' => 50000, 'vat_rate' => 15]],
        ]);

        $response->assertSessionHasErrors('applies_prepayment_invoice_id');
        $this->assertSame(0, Invoice::count());
    }

    public function test_an_invoice_cannot_apply_a_prepayment_invoice_already_applied_elsewhere(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client']);
        $rate = $this->standardRate($company);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'tax_rate_id' => $rate->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'applies_prepayment_invoice_id' => $prepayment->id,
            'invoice_number' => 'INV-00001', 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->toDateString(), 'currency' => $company->currency,
        ]);

        $response = $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id, 'type' => 'standard', 'issue_date' => now()->toDateString(),
            'applies_prepayment_invoice_id' => $prepayment->id,
            'items' => [['description' => 'More work', 'quantity' => 1, 'unit_price' => 5000, 'vat_rate' => 15]],
        ]);

        $response->assertSessionHasErrors('applies_prepayment_invoice_id');
        $this->assertSame(1, Invoice::count());
    }

    public function test_a_prepayment_invoice_already_applied_to_an_invoice_cannot_be_deleted(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client']);
        $rate = $this->standardRate($company);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'tax_rate_id' => $rate->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'applies_prepayment_invoice_id' => $prepayment->id,
            'invoice_number' => 'INV-00001', 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->toDateString(), 'currency' => $company->currency,
        ]);

        $response = $this->actingAs($owner)->delete(route('app.prepayment-invoices.destroy', $prepayment));

        $response->assertSessionHasErrors('prepayment');
        $this->assertSame(1, PrepaymentInvoice::count());
    }
}
