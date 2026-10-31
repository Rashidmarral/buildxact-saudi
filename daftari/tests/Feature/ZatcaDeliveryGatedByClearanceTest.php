<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding ZATCA-01: a standard (B2B) tax invoice is regulated under
 * ZATCA's Clearance Model — the government must cryptographically clear
 * it BEFORE it reaches the buyer. Marking an invoice "sent" in this app
 * only ever queues the actual ZATCA submission (an async job, or a wait
 * for the next batched sync run) — nothing previously stopped the literal
 * document handoff to the customer (email) from happening before that
 * submission ever completed, or even ran at all.
 */
class ZatcaDeliveryGatedByClearanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOnboardedB2bCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Delivery Gate Co.', 'slug' => 'delivery-gate-'.uniqid(),
            'currency' => 'SAR',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => 'csid', 'zatca_production_secret' => 'secret',
            'zatca_sync_b2b' => true,
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(Company $company, array $overrides = []): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'email' => 'client@example.com', 'vat_number' => '300012345600003']);

        return Invoice::create(array_merge([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ], $overrides));
    }

    public function test_emailing_an_uncleared_standard_invoice_is_blocked_for_a_b2b_onboarded_company(): void
    {
        $company = $this->makeOnboardedB2bCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->post(route('app.invoices.email', $invoice));

        $response->assertSessionHasErrors('invoice');
    }

    public function test_emailing_a_cleared_standard_invoice_succeeds(): void
    {
        $company = $this->makeOnboardedB2bCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company);
        ZatcaInvoiceLog::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'status' => 'cleared',
            'invoice_type' => 'b2b', 'direction' => 'clearance', 'environment' => 'production',
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $response = $this->actingAs($owner)->post(route('app.invoices.email', $invoice));

        $response->assertSessionDoesntHaveErrors();
        $response->assertSessionHas('status');
    }

    public function test_emailing_a_simplified_b2c_invoice_is_never_blocked_by_clearance(): void
    {
        $company = $this->makeOnboardedB2bCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company, ['type' => 'simplified']);

        $response = $this->actingAs($owner)->post(route('app.invoices.email', $invoice));

        $response->assertSessionDoesntHaveErrors();
    }

    public function test_emailing_an_uncleared_standard_invoice_is_not_blocked_when_the_company_has_not_turned_on_b2b_sync(): void
    {
        $company = $this->makeOnboardedB2bCompany();
        $company->update(['zatca_sync_b2b' => false]);
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->post(route('app.invoices.email', $invoice));

        $response->assertSessionDoesntHaveErrors();
    }

    public function test_emailing_an_uncleared_standard_invoice_is_not_blocked_for_a_company_not_yet_onboarded(): void
    {
        $company = Company::create(['name' => 'Not Onboarded Co.', 'slug' => 'not-onboarded-'.uniqid(), 'currency' => 'SAR']);
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->post(route('app.invoices.email', $invoice));

        $response->assertSessionDoesntHaveErrors();
    }
}
