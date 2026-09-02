<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\ZatcaInvoiceLog;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature request: "we need to make a system in a way that user or govt
 * can audit company in a easy way at the end of year like by our system
 * companies can easily do their yearly audit without any accountant or
 * technical person involvement." Adds a "Company Audit" dashboard
 * (App\Services\Audit\CompanyAuditService) that runs a set of
 * self-consistency checks against data this app already generated —
 * books balance, every posted document has a matching ledger entry,
 * every eligible document has been cleared/reported to ZATCA (Phase 2
 * companies only), and the company profile has everything ZATCA/an
 * auditor would expect — and surfaces plain-language findings with a
 * direct link to the affected record, instead of requiring someone to
 * cross-reference the Trial Balance, VAT report, and ZATCA log by hand.
 */
class CompanyAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        $company = Company::create(array_merge([
            'name' => 'Audit Co.', 'slug' => 'audit-'.uniqid(),
            'vat_number' => '300012345600003', 'cr_number' => 'CRN123456',
            'street_name' => 'King Fahd Road', 'building_number' => '1234',
            'district' => 'Al Olaya', 'city' => 'Riyadh', 'postal_code' => '12345',
        ], $overrides));

        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Role::seedSystemRoles($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(Company $company, bool $post = true): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        if ($post) {
            app(LedgerPostingService::class)->postInvoiceIssued($invoice);
        }

        return $invoice;
    }

    public function test_the_audit_page_reports_no_issues_for_a_clean_company_outside_zatca_phase2(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->makeInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.audit.index'));

        $response->assertOk();
        $response->assertSee(__('No issues found for this period.'));
        $response->assertSee(__('Audit-ready'));
    }

    public function test_a_posted_invoice_missing_its_ledger_entry_is_flagged_as_critical(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company, post: false);

        $response = $this->actingAs($owner)->get(route('app.audit.index'));

        $response->assertOk();
        $response->assertDontSee(__('No issues found for this period.'));
        $response->assertSee(__('Ledger posting integrity'));
        $response->assertSee($invoice->invoice_number);
        $response->assertSee(route('app.invoices.show', $invoice), false);
    }

    public function test_an_invoice_not_yet_synced_to_zatca_is_flagged_when_phase2_is_active(): void
    {
        $company = $this->makeCompany(['zatca_integration_mode' => Company::ZATCA_MODE_PHASE2]);
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.audit.index'));

        $response->assertOk();
        $response->assertSee(__('ZATCA submission completeness'));
        $response->assertSee($invoice->invoice_number);
    }

    public function test_a_cleared_invoice_is_not_flagged_for_zatca_completeness(): void
    {
        $company = $this->makeCompany(['zatca_integration_mode' => Company::ZATCA_MODE_PHASE2]);
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company);
        ZatcaInvoiceLog::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id,
            'environment' => 'production', 'invoice_type' => 'b2b', 'direction' => 'clearance',
            'status' => 'cleared', 'request_uuid' => (string) Str::uuid(),
            'submitted_at' => now(), 'cleared_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('app.audit.index'));

        $response->assertOk();
        $response->assertSee(__('Every eligible document in this period has been cleared or reported to ZATCA.'));
    }

    public function test_a_zatca_step2_section_never_renders_when_zatca_is_not_in_phase2_mode(): void
    {
        $company = $this->makeCompany(['zatca_integration_mode' => Company::ZATCA_MODE_PHASE1]);
        $owner = $this->makeOwner($company);
        $this->makeInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.audit.index'));

        $response->assertOk();
        $response->assertDontSee(__('ZATCA submission completeness'));
    }

    public function test_a_missing_vat_number_is_flagged_under_company_profile(): void
    {
        $company = $this->makeCompany(['vat_number' => null]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.audit.index'));

        $response->assertOk();
        $response->assertSee(__('Company profile completeness'));
        $response->assertSee(__('VAT registration number'));
    }

    public function test_a_user_without_the_audit_permission_is_forbidden(): void
    {
        $plan = Plan::create(['name' => 'Basic', 'slug' => 'basic-'.uniqid(), 'price_monthly' => 50, 'price_yearly' => 500, 'is_active' => true]);
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $role = Role::create(['company_id' => $company->id, 'name' => 'No Audit', 'slug' => 'no-audit', 'is_system' => false, 'permissions' => ['dashboard']]);
        $staff = User::factory()->create(['role' => 'staff', 'company_id' => $company->id, 'status' => 'active']);
        $staff->roles()->attach($role->id);

        $response = $this->actingAs($staff)->get(route('app.audit.index'));

        $response->assertForbidden();
    }

    public function test_the_audit_page_can_be_downloaded_as_a_pdf(): void
    {
        $company = $this->makeCompany(['vat_number' => null]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.audit.index', ['export' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_audit_page_can_be_exported_as_a_csv_listing_every_finding(): void
    {
        $company = $this->makeCompany(['vat_number' => null]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.audit.index', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Company profile completeness', $csv);
        $this->assertStringContainsString('VAT registration number', $csv);
    }
}
