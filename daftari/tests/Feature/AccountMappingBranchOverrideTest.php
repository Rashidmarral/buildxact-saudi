<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AccountMappingBranchOverride;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please login and verify each and everything" (Hamly ERP) — Hamly's
 * own account-mapping settings page repeats per business location, each
 * one able to override the company-wide default for any key, falling
 * back to that default for anything left unset. Scoped here to the
 * document types that already carry their own branch_id (Invoice,
 * CreditNote, DebitNote, Bill) — see AccountMapping::branchOverridableKeys().
 */
class AccountMappingBranchOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeBranch(Company $company, string $name): Branch
    {
        return Branch::create(['company_id' => $company->id, 'name' => $name]);
    }

    private function makeInvoice(Company $company, Branch $branch): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'branch_id' => $branch->id,
            'invoice_number' => 'INV-00001', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);
    }

    public function test_an_invoice_under_a_branch_with_no_override_posts_to_the_company_default(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company, 'Riyadh Branch');
        $invoice = $this->makeInvoice($company, $branch);

        $entry = app(LedgerPostingService::class)->postInvoiceIssued($invoice);

        $defaultRevenue = AccountMapping::resolve($company->id, 'DEFAULT_SALES_REVENUE');
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(1000, $entry->lines->where('account_id', $defaultRevenue->id)->sum('credit'), 0.01);
    }

    public function test_an_invoice_under_a_branch_with_an_override_posts_to_the_branchs_own_revenue_account(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company, 'Jeddah Branch');
        $branchRevenue = Account::create(['company_id' => $company->id, 'code' => '4001', 'name' => 'Jeddah Branch Sales', 'type' => 'revenue', 'normal_balance' => 'credit']);
        AccountMappingBranchOverride::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'key' => 'DEFAULT_SALES_REVENUE', 'account_id' => $branchRevenue->id]);
        $invoice = $this->makeInvoice($company, $branch);

        $entry = app(LedgerPostingService::class)->postInvoiceIssued($invoice);

        $defaultRevenue = AccountMapping::resolve($company->id, 'DEFAULT_SALES_REVENUE');
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(1000, $entry->lines->where('account_id', $branchRevenue->id)->sum('credit'), 0.01);
        $this->assertEqualsWithDelta(0, $entry->lines->where('account_id', $defaultRevenue->id)->sum('credit'), 0.01);
    }

    public function test_an_invoice_with_no_branch_posts_to_the_company_default_as_before(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);

        $entry = app(LedgerPostingService::class)->postInvoiceIssued($invoice);

        $defaultRevenue = AccountMapping::resolve($company->id, 'DEFAULT_SALES_REVENUE');
        $this->assertEqualsWithDelta(1000, $entry->lines->where('account_id', $defaultRevenue->id)->sum('credit'), 0.01);
    }

    public function test_the_owner_can_set_and_clear_a_branch_override_through_the_settings_page(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $branch = $this->makeBranch($company, 'Jeddah Branch');
        $branchRevenue = Account::create(['company_id' => $company->id, 'code' => '4001', 'name' => 'Jeddah Branch Sales', 'type' => 'revenue', 'normal_balance' => 'credit']);

        $this->actingAs($owner)->post(route('app.accounts.branch-mappings.update', $branch), [
            'key' => 'DEFAULT_SALES_REVENUE', 'account_id' => $branchRevenue->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('account_mapping_branch_overrides', [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'key' => 'DEFAULT_SALES_REVENUE', 'account_id' => $branchRevenue->id,
        ]);

        $this->actingAs($owner)->post(route('app.accounts.branch-mappings.update', $branch), [
            'key' => 'DEFAULT_SALES_REVENUE', 'account_id' => '',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('account_mapping_branch_overrides', [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'key' => 'DEFAULT_SALES_REVENUE',
        ]);
    }

    public function test_a_branch_override_is_rejected_when_the_account_type_does_not_match(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $branch = $this->makeBranch($company, 'Jeddah Branch');
        $expenseAccount = AccountMapping::resolve($company->id, 'DEFAULT_OPERATING_EXPENSES');

        $response = $this->actingAs($owner)->post(route('app.accounts.branch-mappings.update', $branch), [
            'key' => 'DEFAULT_SALES_REVENUE', 'account_id' => $expenseAccount->id,
        ]);

        $response->assertSessionHasErrors('account_id');
        $this->assertDatabaseMissing('account_mapping_branch_overrides', ['branch_id' => $branch->id, 'key' => 'DEFAULT_SALES_REVENUE']);
    }

    public function test_a_company_cannot_configure_another_companys_branch(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $branchB = $this->makeBranch($companyB, 'Other Company Branch');

        $this->actingAs($ownerA)->get(route('app.accounts.branch-mappings', $branchB))->assertNotFound();
        $this->actingAs($ownerA)->post(route('app.accounts.branch-mappings.update', $branchB), ['key' => 'DEFAULT_SALES_REVENUE', 'account_id' => ''])->assertNotFound();
    }
}
