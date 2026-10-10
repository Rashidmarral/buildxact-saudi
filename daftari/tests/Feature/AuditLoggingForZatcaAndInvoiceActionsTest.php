<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit findings M-15 (ZATCA credential/configuration changes on
 * the user-facing side went completely unlogged — the admin-side
 * equivalent already logs these) and M-16 (draft invoice edits/deletes
 * and payment recording went unlogged, unlike every status-change action).
 */
class AuditLoggingForZatcaAndInvoiceActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_zatca_phase2' => true,
        ]);
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'status' => 'active']);
    }

    // -----------------------------------------------------------------
    // M-15: ZATCA actions
    // -----------------------------------------------------------------

    public function test_changing_the_zatca_integration_mode_is_logged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->put(route('app.zatca.mode.update'), [
            'zatca_integration_mode' => 'phase1',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'zatca.mode_update', 'subject_type' => Company::class, 'subject_id' => $company->id,
        ]);
    }

    public function test_resetting_zatca_onboarding_is_logged(): void
    {
        $company = $this->makeCompany();
        $company->update(['zatca_onboarding_status' => 'onboarded', 'zatca_production_csid' => 'fake-csid']);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.zatca.reset'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'zatca.onboarding_reset', 'subject_type' => Company::class, 'subject_id' => $company->id,
        ]);
        $this->assertSame('not_started', $company->refresh()->zatca_onboarding_status);
    }

    // -----------------------------------------------------------------
    // M-16: invoice update/destroy/payment
    // -----------------------------------------------------------------

    private function makeDraftInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001', 'type' => 'standard', 'status' => 'draft',
            'issue_date' => now(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);
    }

    public function test_updating_a_draft_invoice_is_logged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeDraftInvoice($company);

        $this->actingAs($owner)->put(route('app.invoices.update', $invoice), [
            'client_id' => $invoice->client_id, 'type' => 'standard', 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15]],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invoice.update', 'subject_type' => Invoice::class, 'subject_id' => $invoice->id,
        ]);
    }

    public function test_deleting_a_draft_invoice_is_logged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeDraftInvoice($company);

        $this->actingAs($owner)->delete(route('app.invoices.destroy', $invoice));

        $log = AuditLog::where('action', 'invoice.delete')->where('company_id', $company->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('INV-0001', $log->description);
    }

    public function test_recording_a_payment_is_logged(): void
    {
        $company = $this->makeCompany();
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = $this->makeOwner($company);
        $invoice = $this->makeDraftInvoice($company);
        $invoice->update(['status' => 'sent']);

        $this->actingAs($owner)->post(route('app.invoices.payments.store', $invoice), [
            'amount' => 50, 'paid_at' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invoice.payment_record', 'subject_type' => Invoice::class, 'subject_id' => $invoice->id,
        ]);
    }
}
