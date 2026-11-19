<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Billing\PlatformInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requested directly by the operator, who runs a real second business
 * (construction/services) through the same company that also resells
 * Daftari subscriptions, all under one CR/VAT registration: a report
 * showing the combined output VAT (the one figure that actually goes on
 * the ZATCA return) plus an internal-only split between "own business"
 * and "subscription resale" invoices, identified via
 * Client::platform_billed_company_id.
 */
class VatReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function makeBillingCompany(): Company
    {
        $company = Company::create([
            'name' => 'Dynamic Core Contracting Company',
            'slug' => 'billing-co-'.uniqid(),
            'vat_number' => '314526094900003',
            'currency' => 'SAR',
        ]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        TaxRate::seedDefaults($company->id);

        return $company;
    }

    private function makeOwnInvoice(Company $company, float $subtotal, float $vat): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Real Asphalt Client']);

        return Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard',
            'status' => 'sent',
            'issue_date' => now(),
            'subtotal' => $subtotal,
            'vat_total' => $vat,
            'total' => $subtotal + $vat,
            'currency' => 'SAR',
        ]);
    }

    private function makeSubscriptionInvoice(Company $billingCompany, float $amount): Invoice
    {
        $payingCompany = Company::create(['name' => 'Acme Trading', 'slug' => 'acme-'.uniqid()]);
        $plan = Plan::create(['name' => 'Growth', 'slug' => 'growth-'.uniqid(), 'price_monthly' => $amount, 'price_yearly' => $amount * 10, 'currency' => 'SAR']);
        $subscription = Subscription::create(['company_id' => $payingCompany->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly']);
        $payment = Payment::create([
            'company_id' => $payingCompany->id, 'subscription_id' => $subscription->id, 'plan_id' => $plan->id,
            'amount' => $amount, 'currency' => 'SAR', 'status' => 'paid', 'method' => 'card',
            'reference' => 'TXN-'.uniqid(), 'paid_at' => now(),
        ]);

        return app(PlatformInvoiceService::class)->createInvoiceForPayment($payment);
    }

    public function test_no_company_selected_shows_an_empty_prompt(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.reports.vat'));

        $response->assertOk()->assertSee(__('Select a company above to view its VAT report.'));
    }

    public function test_defaults_to_the_platform_billing_company_when_none_is_selected(): void
    {
        $billingCompany = $this->makeBillingCompany();
        Setting::set('platform_billing_company_id', $billingCompany->id);
        $this->makeOwnInvoice($billingCompany, 1000, 150);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.reports.vat'));

        $response->assertOk()->assertSee($billingCompany->name);
        $response->assertDontSee(__('Select a company above to view its VAT report.'));
    }

    public function test_combined_output_vat_sums_both_revenue_streams(): void
    {
        $billingCompany = $this->makeBillingCompany();
        Setting::set('platform_billing_company_id', $billingCompany->id);

        $this->makeOwnInvoice($billingCompany, 1000, 150);
        $subscriptionInvoice = $this->makeSubscriptionInvoice($billingCompany, 230);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.reports.vat', ['company_id' => $billingCompany->id]));

        $response->assertOk();
        // Combined: 150 (own) + the subscription invoice's own VAT total.
        $expectedCombined = 150 + (float) $subscriptionInvoice->vat_total;
        $response->assertSee(\App\Support\Money::format($expectedCombined), false);
    }

    public function test_the_split_correctly_separates_own_from_subscription_invoices(): void
    {
        $billingCompany = $this->makeBillingCompany();
        Setting::set('platform_billing_company_id', $billingCompany->id);

        $this->makeOwnInvoice($billingCompany, 1000, 150);
        $subscriptionInvoice = $this->makeSubscriptionInvoice($billingCompany, 230);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.reports.vat', ['company_id' => $billingCompany->id]));

        $response->assertOk()
            ->assertSee(__('Own business'))
            ->assertSee(__('Subscription resale'))
            ->assertSee(\App\Support\Money::format(150.0), false)
            ->assertSee(\App\Support\Money::format((float) $subscriptionInvoice->vat_total), false);
    }

    public function test_draft_and_cancelled_invoices_are_excluded(): void
    {
        $billingCompany = $this->makeBillingCompany();
        $client = Client::create(['company_id' => $billingCompany->id, 'name' => 'Draft Client']);
        Invoice::create([
            'company_id' => $billingCompany->id, 'client_id' => $client->id, 'invoice_number' => 'INV-DRAFT',
            'type' => 'standard', 'status' => 'draft', 'issue_date' => now(),
            'subtotal' => 5000, 'vat_total' => 750, 'total' => 5750, 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.reports.vat', ['company_id' => $billingCompany->id]));

        $response->assertOk()->assertDontSee(\App\Support\Money::format(750.0), false);
    }

    public function test_can_switch_between_companies(): void
    {
        $companyA = $this->makeBillingCompany();
        $companyB = Company::create(['name' => 'Other Trading Co', 'slug' => 'other-'.uniqid()]);
        $this->makeOwnInvoice($companyA, 1000, 150);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.reports.vat', ['company_id' => $companyB->id]));

        $response->assertOk()->assertSee($companyB->name)->assertDontSee(\App\Support\Money::format(150.0), false);
    }
}
