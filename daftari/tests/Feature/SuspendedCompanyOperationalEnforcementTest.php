<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateRecurringExpenses;
use App\Console\Commands\GenerateRecurringInvoices;
use App\Jobs\SyncInvoiceToZatca;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Plan;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Security audit finding D-0: suspension/subscription-expiry enforcement
 * only ever reached the web UI. A company's Sanctum API token kept
 * working after EnsureCompanyActive had already force-logged-out its web
 * users, background commands (recurring invoices/expenses, ZATCA sync)
 * kept creating real records for a company nobody could log in to
 * review, and the automatic non-payment dunning ladder reaching
 * 'suspended' never actually blocked anything (only a manual
 * Company::status suspension did). Fixed via a single source of truth,
 * Company::isOperational(), checked identically everywhere.
 */
class SuspendedCompanyOperationalEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active',
        ], $overrides));
    }

    private function attachSubscription(Company $company, string $status): Subscription
    {
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true]);

        return Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => $status,
            'billing_cycle' => 'monthly', 'current_period_start' => now()->subMonth(), 'current_period_end' => now()->subDay(),
        ]);
    }

    // -----------------------------------------------------------------
    // Company::isOperational()
    // -----------------------------------------------------------------

    public function test_a_manually_suspended_company_is_not_operational(): void
    {
        $company = $this->makeCompany(['status' => 'suspended']);
        $this->assertFalse($company->isOperational());
    }

    public function test_a_company_auto_suspended_by_the_dunning_ladder_is_not_operational(): void
    {
        $company = $this->makeCompany();
        $this->attachSubscription($company, 'suspended');

        $this->assertFalse($company->isOperational());
    }

    public function test_a_company_mid_grace_period_is_still_operational(): void
    {
        $company = $this->makeCompany();
        $this->attachSubscription($company, 'grace_period');

        $this->assertTrue($company->isOperational(), 'past_due/grace_period are deliberately still-working stages.');
    }

    public function test_a_company_with_a_cancelled_or_expired_subscription_is_not_operational(): void
    {
        $company = $this->makeCompany();
        $this->attachSubscription($company, 'cancelled');
        $this->assertFalse($company->isOperational());

        $company2 = $this->makeCompany();
        $this->attachSubscription($company2, 'expired');
        $this->assertFalse($company2->isOperational());
    }

    public function test_a_company_that_never_had_a_subscription_is_still_operational(): void
    {
        $company = $this->makeCompany();
        $this->assertTrue($company->isOperational());
    }

    // -----------------------------------------------------------------
    // Web enforcement
    // -----------------------------------------------------------------

    public function test_a_web_session_is_logged_out_once_the_dunning_ladder_auto_suspends_the_company(): void
    {
        $company = $this->makeCompany();
        $this->attachSubscription($company, 'suspended');
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------
    // API enforcement
    // -----------------------------------------------------------------

    public function test_an_api_token_is_rejected_once_its_company_is_suspended(): void
    {
        $company = $this->makeCompany(['status' => 'suspended']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        Sanctum::actingAs($owner, ['read']);

        $response = $this->getJson(route('api.v1.me'));

        $response->assertStatus(403);
    }

    public function test_an_api_token_still_works_for_an_active_company(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        Sanctum::actingAs($owner, ['read']);

        $response = $this->getJson(route('api.v1.me'));

        $response->assertOk();
    }

    // -----------------------------------------------------------------
    // Background jobs
    // -----------------------------------------------------------------

    public function test_recurring_invoice_generation_skips_a_suspended_company(): void
    {
        $company = $this->makeCompany(['status' => 'suspended']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Service', 'type' => 'service', 'unit_price' => 100, 'vat_rate' => 15]);
        $recurring = RecurringInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'title' => 'Monthly Service', 'status' => 'active',
            'frequency' => 'monthly', 'next_run_date' => now()->subDay()->toDateString(), 'start_date' => now()->subMonth()->toDateString(),
        ]);
        RecurringInvoiceItem::create([
            'recurring_invoice_id' => $recurring->id, 'item_id' => $item->id, 'description' => 'Service',
            'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'sort_order' => 0,
        ]);

        $this->artisan(GenerateRecurringInvoices::class)->run();

        $this->assertSame(0, Invoice::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_recurring_invoice_generation_still_works_for_an_active_company(): void
    {
        $company = $this->makeCompany();
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Service', 'type' => 'service', 'unit_price' => 100, 'vat_rate' => 15]);
        $recurring = RecurringInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'title' => 'Monthly Service', 'status' => 'active',
            'frequency' => 'monthly', 'next_run_date' => now()->subDay()->toDateString(), 'start_date' => now()->subMonth()->toDateString(),
        ]);
        RecurringInvoiceItem::create([
            'recurring_invoice_id' => $recurring->id, 'item_id' => $item->id, 'description' => 'Service',
            'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'sort_order' => 0,
        ]);

        $this->artisan(GenerateRecurringInvoices::class)->run();

        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_recurring_expense_generation_skips_a_company_with_a_cancelled_subscription(): void
    {
        $company = $this->makeCompany();
        $this->attachSubscription($company, 'cancelled');
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $recurring = RecurringExpense::create([
            'company_id' => $company->id, 'title' => 'Rent', 'gross_amount' => 500,
            'status' => 'active', 'frequency' => 'monthly', 'next_run_date' => now()->subDay()->toDateString(),
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        $this->artisan(GenerateRecurringExpenses::class)->run();

        $this->assertSame('active', $recurring->fresh()->status);
        $this->assertSame(now()->subDay()->toDateString(), $recurring->fresh()->next_run_date->toDateString(), 'A skipped recurrence must not have its schedule advanced.');
    }

    public function test_instant_zatca_sync_job_skips_a_suspended_company_without_any_http_call(): void
    {
        Http::fake();

        $company = $this->makeCompany([
            'status' => 'suspended',
            'zatca_onboarding_status' => 'onboarded',
            'zatca_environment' => 'production',
            'zatca_production_csid' => 'csid', 'zatca_production_secret' => 'secret',
            'zatca_sync_frequency' => 'instant', 'zatca_sync_b2b' => true, 'zatca_sync_b2c' => true,
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'vat_number' => '300000000000003']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1', 'type' => 'standard',
            'status' => 'sent', 'issue_date' => now()->toDateString(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        (new SyncInvoiceToZatca($invoice->id))->handle(app(\App\Services\Zatca\ZatcaSyncService::class));

        Http::assertNothingSent();
        $this->assertSame(0, $invoice->zatcaInvoiceLogs()->count());
    }
}
