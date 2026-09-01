<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Accounting Health page (SalePro's "Accounting Health" screen was the
 * reference point) is a self-diagnostic dashboard: three areas — setup,
 * financial records, transaction processing — each backed by a real query
 * against the company's own data. These tests exercise the actual
 * detection logic, not just that the page renders.
 */
class AccountingHealthTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Health Co.', 'slug' => 'health-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_fully_configured_company_with_no_activity_shows_no_issues(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.accounting-health.index'));

        $response->assertOk();
        $response->assertViewHas('issueCount', 0);
    }

    public function test_a_missing_account_mapping_is_flagged_as_a_setup_issue(): void
    {
        $owner = $this->makeOwner();
        AccountMapping::where('company_id', $owner->company_id)->where('key', 'VAT_OUTPUT')->delete();
        Account::where('company_id', $owner->company_id)->where('code', '2100')->delete();

        $response = $this->actingAs($owner)->get(route('app.accounting-health.index'));

        $response->assertOk();
        $issues = $response->viewData('setupIssues');
        $this->assertNotEmpty($issues);
        $this->assertTrue(collect($issues)->contains(fn ($i) => str_contains($i['detail'], 'VAT output')));
    }

    public function test_a_deactivated_mapped_account_is_flagged_as_a_setup_issue(): void
    {
        $owner = $this->makeOwner();
        Account::where('company_id', $owner->company_id)->where('code', '2100')->update(['is_active' => false]);

        $response = $this->actingAs($owner)->get(route('app.accounting-health.index'));

        $issues = $response->viewData('setupIssues');
        $this->assertTrue(collect($issues)->contains(fn ($i) => str_contains($i['title'], 'inactive')));
    }

    public function test_an_invoice_sent_without_a_journal_entry_is_flagged_as_a_transaction_issue(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client']);

        // Simulate the pre-fix data-integrity gap directly (LedgerPostingService
        // now always posts or throws on send, so this can no longer happen through
        // the UI — the health check still needs to catch a document left over
        // from before that fix, or from a data import).
        Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($owner)->get(route('app.accounting-health.index'));

        $issues = $response->viewData('transactionIssues');
        $this->assertTrue(collect($issues)->contains(fn ($i) => str_contains($i['title'], 'invoices')));
    }

    public function test_an_unbalanced_journal_entry_is_flagged_as_a_financial_record_issue(): void
    {
        $owner = $this->makeOwner();
        $cash = Account::where('company_id', $owner->company_id)->where('code', '1000')->first();

        $entry = JournalEntry::create([
            'company_id' => $owner->company_id, 'entry_number' => 'JE-BROKEN', 'entry_date' => now(),
            'source_type' => 'manual', 'source_id' => 1, 'description' => 'Deliberately broken entry',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $cash->id, 'debit' => 100, 'credit' => 0]);

        $response = $this->actingAs($owner)->get(route('app.accounting-health.index'));

        $issues = $response->viewData('financialIssues');
        $this->assertTrue(collect($issues)->contains(fn ($i) => str_contains($i['title'], 'balance')));
    }
}
