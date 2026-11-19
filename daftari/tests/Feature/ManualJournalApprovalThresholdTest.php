<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PendingManualJournalEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding GL-6: manual journal entries — a direct, unaudited GL
 * posting with no source document behind it — had no approval gate at
 * all, unlike Expenses/POs/Invoices/Quotations which already support a
 * threshold. This mirrors that exact pattern: null threshold (the
 * default) changes nothing; once set, an entry at or above it is staged
 * in PendingManualJournalEntry instead of posted, and only reaches the
 * real ledger once explicitly approved.
 */
class ManualJournalApprovalThresholdTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        $company = Company::create(array_merge([
            'name' => 'Manual JE Approval Co.', 'slug' => 'manual-je-approval-'.uniqid(),
        ], $overrides));

        Account::seedSystemAccounts($company->id);
        Role::seedSystemRoles($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_null_threshold_leaves_todays_instant_posting_behavior_unchanged(): void
    {
        $company = $this->makeCompany(); // manual_journal_approval_threshold defaults to null
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Large but unthrottled entry',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 500000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 500000],
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->count());
        $this->assertSame(0, PendingManualJournalEntry::count());
    }

    public function test_an_entry_at_or_above_the_threshold_is_staged_not_posted(): void
    {
        $company = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Large adjustment',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 15000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 15000],
            ],
        ]);

        $response->assertRedirect(route('app.journals.pending.index'));
        $this->assertSame(0, JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->count(), 'Nothing should reach the ledger before approval.');
        $pending = PendingManualJournalEntry::where('company_id', $company->id)->first();
        $this->assertNotNull($pending);
        $this->assertSame('pending_approval', $pending->status);
        $this->assertEqualsWithDelta(15000, $pending->total, 0.01);
        $this->assertSame($owner->id, $pending->created_by);
    }

    public function test_an_entry_below_the_threshold_still_posts_instantly(): void
    {
        $company = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Small adjustment',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 500],
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->count());
        $this->assertSame(0, PendingManualJournalEntry::count());
    }

    public function test_approving_a_pending_entry_posts_it_to_the_real_ledger(): void
    {
        $company = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Needs approval',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 20000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 20000],
            ],
        ]);
        $pending = PendingManualJournalEntry::first();

        $response = $this->actingAs($owner)->post(route('app.journals.pending.approve', $pending));

        $response->assertRedirect();
        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->first();
        $this->assertNotNull($entry);
        $this->assertSame(20000.0, $entry->totalDebit());
        $pending->refresh();
        $this->assertSame('approved', $pending->status);
        $this->assertSame($entry->id, $pending->journal_entry_id);
        $this->assertSame($owner->id, $pending->approved_by);
        $this->assertNotNull($pending->approved_at);
    }

    public function test_rejecting_a_pending_entry_never_touches_the_ledger(): void
    {
        $company = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Rejected entry',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 20000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 20000],
            ],
        ]);
        $pending = PendingManualJournalEntry::first();

        $response = $this->actingAs($owner)->post(route('app.journals.pending.reject', $pending), [
            'rejection_reason' => 'Needs a different account',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->count());
        $pending->refresh();
        $this->assertSame('rejected', $pending->status);
        $this->assertSame('Needs a different account', $pending->rejection_reason);
    }

    public function test_an_unbalanced_entry_above_the_threshold_is_still_rejected_before_staging(): void
    {
        $company = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced and large',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 20000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 19000],
            ],
        ]);

        $response->assertSessionHasErrors('lines');
        $this->assertSame(0, PendingManualJournalEntry::count());
        $this->assertSame(0, JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->count());
    }

    public function test_a_company_cannot_approve_another_companys_pending_entry(): void
    {
        $companyA = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $ownerA = $this->makeOwner($companyA);
        $companyB = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $cashB = Account::where('company_id', $companyB->id)->where('code', '1000')->first();
        $equityB = Account::where('company_id', $companyB->id)->where('code', '3000')->first();

        $pendingB = PendingManualJournalEntry::create([
            'company_id' => $companyB->id, 'entry_date' => now(), 'description' => 'Company B entry',
            'lines' => [
                ['account_id' => $cashB->id, 'debit' => 20000, 'credit' => 0],
                ['account_id' => $equityB->id, 'debit' => 0, 'credit' => 20000],
            ],
            'total' => 20000,
        ]);

        $response = $this->actingAs($ownerA)->post(route('app.journals.pending.approve', $pendingB));

        $response->assertNotFound();
    }
}
