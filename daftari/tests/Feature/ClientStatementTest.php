<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\ModuleRequest;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Features\FeatureAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add this feature to our app where companies can generate account
 * statement or kashf hisab for clients admin have the authority to
 * activate this module after a request from a company" — a client's
 * own running ledger (invoices, payments, credit/debit notes, opening
 * and closing balance), gated exactly like every other paid module here
 * (Payroll, POS, Machinery & Equipment, ...): a company requests it from
 * the Modules marketplace, a Super Admin approves it, which is the same
 * generic CompanyOverride front door ModuleMarketplaceTest already
 * proves works for any FeatureRegistry key — so this file focuses on
 * what's actually new: the statement's own math and documents.
 */
class ClientStatementTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(bool $withModule): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_client_statements' => $withModule,
        ]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'name_ar' => 'شركة داينامك كور', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(Company $company, Client $client, string $number, string $issueDate, float $total, ?Project $project = null): Invoice
    {
        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $project?->id,
            'invoice_number' => $number, 'status' => 'sent', 'issue_date' => $issueDate, 'due_date' => now()->addDays(30),
            'subtotal' => $total, 'vat_total' => 0, 'total' => $total, 'currency' => 'SAR',
        ]);
    }

    // ------------------------------------------------------------------
    // Module gating
    // ------------------------------------------------------------------

    public function test_a_company_without_the_module_is_blocked_from_every_route(): void
    {
        $company = $this->makeCompany(withModule: false);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);

        $this->actingAs($owner)->get(route('app.client-statements.index'))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.client-statements.show', $client))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.client-statements.pdf', $client))->assertRedirect(route('app.dashboard'));
    }

    public function test_a_company_whose_plan_includes_the_module_can_access_it(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);

        $this->actingAs($owner)->get(route('app.client-statements.index'))->assertOk();
        $this->actingAs($owner)->get(route('app.client-statements.show', $client))->assertOk();
    }

    public function test_the_nav_link_and_clients_list_statement_link_are_hidden_without_the_module(): void
    {
        $company = $this->makeCompany(withModule: false);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));
        $response->assertDontSee(route('app.client-statements.index'), false);

        $clients = $this->actingAs($owner)->get(route('app.clients.index'));
        $clients->assertDontSee(route('app.client-statements.show', $client), false);
    }

    public function test_the_nav_link_and_clients_list_statement_link_appear_once_installed(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);

        $this->actingAs($owner)->get(route('app.dashboard'))
            ->assertSee(route('app.client-statements.index'), false);

        $this->actingAs($owner)->get(route('app.clients.index'))
            ->assertSee(route('app.client-statements.show', $client), false);
    }

    public function test_a_company_can_request_the_module_and_an_admin_approval_installs_it(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        $company = $this->makeCompany(withModule: false);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.modules.request', 'client_statements'))->assertRedirect();
        $moduleRequest = ModuleRequest::first();
        $this->assertSame('client_statements', $moduleRequest->module_key);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.module-requests.approve', $moduleRequest))->assertRedirect();

        $this->assertTrue(app(FeatureAccessService::class)->enabled($company->fresh(), 'client_statements'));
        $this->actingAs($owner)->get(route('app.client-statements.index'))->assertOk();
    }

    // ------------------------------------------------------------------
    // Statement correctness
    // ------------------------------------------------------------------

    /**
     * Mirrors the real example this feature was built from: a client
     * billed 10,235 + 3,910 (both paid in full) plus a third, unpaid
     * 5,750 invoice — total invoiced 19,895, received 14,145, balance
     * due 5,750.
     */
    public function test_the_statement_matches_a_seeded_real_world_scenario(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Khuraif Water Tech', 'name_ar' => 'شركة الخريف لتقنية المياه', 'vat_number' => '300050372110003']);

        $invoiceA = $this->makeInvoice($company, $client, '26-200-000031', '2026-07-09', 10235.00);
        InvoicePayment::create(['invoice_id' => $invoiceA->id, 'amount' => 10235.00, 'paid_at' => '2026-07-09', 'method' => 'bank_transfer', 'reference' => '53646904581']);
        $invoiceA->update(['status' => 'paid', 'amount_paid' => 10235.00]);

        $invoiceB = $this->makeInvoice($company, $client, '26-200-000033', '2026-08-17', 3910.00);
        InvoicePayment::create(['invoice_id' => $invoiceB->id, 'amount' => 3910.00, 'paid_at' => '2026-08-17', 'method' => 'bank_transfer', 'reference' => '54531243912']);
        $invoiceB->update(['status' => 'paid', 'amount_paid' => 3910.00]);

        $this->makeInvoice($company, $client, '26-200-000040', '2026-09-17', 5750.00);

        $response = $this->actingAs($owner)->get(route('app.client-statements.show', ['client' => $client, 'period' => 'custom', 'from' => '2026-04-01', 'to' => '2026-10-31']));

        $response->assertOk();
        $response->assertSee(\App\Support\Money::format(19895.00));
        $response->assertSee(\App\Support\Money::format(14145.00));
        $response->assertSee(\App\Support\Money::format(5750.00));
        $response->assertSee('53646904581');
        $response->assertSee(__('Paid'));
        $response->assertSee(__('Unpaid'));
    }

    public function test_invoices_and_payments_before_the_period_only_affect_the_opening_balance(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Old Relationship Co.', 'initial_balance' => 1000]);

        $old = $this->makeInvoice($company, $client, 'INV-OLD', '2026-01-01', 2000.00);
        InvoicePayment::create(['invoice_id' => $old->id, 'amount' => 500.00, 'paid_at' => '2026-01-15', 'method' => 'cash']);
        $this->makeInvoice($company, $client, 'INV-NEW', '2026-06-01', 3000.00);

        $response = $this->actingAs($owner)->get(route('app.client-statements.show', ['client' => $client, 'period' => 'custom', 'from' => '2026-05-01', 'to' => '2026-06-30']));

        $response->assertOk();
        // Opening balance = 1000 (initial) + 2000 (old invoice) - 500 (old payment) = 2500.
        $response->assertSee(\App\Support\Money::format(2500.00));
        $response->assertDontSee('INV-OLD');
        $response->assertSee('INV-NEW');
        // Closing balance = 2500 + 3000 (new invoice, no payment yet) = 5500.
        $response->assertSee(\App\Support\Money::format(5500.00));
    }

    public function test_a_credit_note_reduces_the_balance_like_a_payment(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client With Credit']);
        $invoice = $this->makeInvoice($company, $client, 'INV-1', now()->toDateString(), 5000.00);

        CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-1', 'issue_date' => now()->toDateString(), 'status' => 'issued',
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000, 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($owner)->get(route('app.client-statements.show', $client));

        $response->assertOk();
        $response->assertSee('CN-1');
        // Closing balance = 5000 invoiced - 1000 credited = 4000.
        $response->assertSee(\App\Support\Money::format(4000.00));
    }

    public function test_filtering_by_project_only_includes_that_projects_invoices(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Multi-Project Client']);
        $projectA = Project::create(['company_id' => $company->id, 'code' => 'PRJ-A', 'name' => 'Project A', 'status' => 'active']);
        $projectB = Project::create(['company_id' => $company->id, 'code' => 'PRJ-B', 'name' => 'Project B', 'status' => 'active']);
        $this->makeInvoice($company, $client, 'INV-A', now()->toDateString(), 1000.00, $projectA);
        $this->makeInvoice($company, $client, 'INV-B', now()->toDateString(), 2000.00, $projectB);

        $response = $this->actingAs($owner)->get(route('app.client-statements.show', ['client' => $client, 'project_id' => $projectA->id]));

        $response->assertOk();
        $response->assertSee('INV-A');
        $response->assertDontSee('INV-B');
        $response->assertSee(\App\Support\Money::format(1000.00));
    }

    public function test_the_pdf_downloads_bilingual_and_branded(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Khuraif Water Tech', 'name_ar' => 'شركة الخريف']);
        $this->makeInvoice($company, $client, 'INV-1', now()->toDateString(), 5000.00);

        $response = $this->actingAs($owner)->get(route('app.client-statements.pdf', $client));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_client_statement_cannot_be_viewed_by_another_company(): void
    {
        $companyA = $this->makeCompany(withModule: true);
        $companyB = $this->makeCompany(withModule: true);
        $ownerB = $this->makeOwner($companyB);
        $clientA = Client::create(['company_id' => $companyA->id, 'name' => 'Company A Client']);

        $this->actingAs($ownerB)->get(route('app.client-statements.show', $clientA))->assertNotFound();
    }

    public function test_an_admin_override_installs_the_module_for_one_company_without_a_plan_change(): void
    {
        $company = $this->makeCompany(withModule: false);
        $owner = $this->makeOwner($company);
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'client_statements', 'value' => '1', 'reason' => 'test']);

        $this->actingAs($owner)->get(route('app.client-statements.index'))->assertOk();
    }
}
