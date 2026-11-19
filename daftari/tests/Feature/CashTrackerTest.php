<?php

namespace Tests\Feature;

use App\Models\CashTracker;
use App\Models\CashTrackerEntry;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "something to tackle this kind of situation like i directly add two
 * deposit into other company account and add entries where this cash is
 * used and get report for it and that will not affect our accounting
 * system like a separate module if we miss anything live it there but
 * just to get exact report of that account to show where cash goes on
 * of that account to manage."
 *
 * A standalone cash ledger (CashTracker/CashTrackerEntry), deliberately
 * disconnected from Account/JournalEntry/BankAccount — these tests cover
 * the ledger math (deposits/withdrawals/running balance), the by-party
 * breakdown, and — critically — that none of it touches the real
 * accounting records (no Account, JournalEntry, or BankAccount rows are
 * created or changed by any of this).
 */
class CashTrackerTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Tracker Co.', 'slug' => 'tracker-'.uniqid(), 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_a_company_can_create_a_cash_tracker(): void
    {
        [$company, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.cash-trackers.store'), [
            'name' => 'Owner transfer — Site A',
            'notes' => 'Two deposits from the owner, managed by hand.',
        ]);

        $response->assertRedirect();
        $tracker = CashTracker::where('company_id', $company->id)->first();
        $this->assertNotNull($tracker);
        $this->assertSame('Owner transfer — Site A', $tracker->name);
        $this->assertSame('SAR', $tracker->currency);
    }

    public function test_deposits_and_withdrawals_compute_a_correct_running_balance(): void
    {
        [, $owner] = $this->makeOwner();
        $tracker = CashTracker::create(['company_id' => $owner->company_id, 'name' => 'Site A', 'currency' => 'SAR', 'created_by' => $owner->id]);

        $this->actingAs($owner)->post(route('app.cash-trackers.entries.store', $tracker), [
            'date' => '2026-10-01', 'type' => 'deposit', 'amount' => 25000, 'party_name' => 'Owner', 'purpose' => 'Transfer 1',
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('app.cash-trackers.entries.store', $tracker), [
            'date' => '2026-10-02', 'type' => 'deposit', 'amount' => 30000, 'party_name' => 'Owner', 'purpose' => 'Transfer 2',
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('app.cash-trackers.entries.store', $tracker), [
            'date' => '2026-10-03', 'type' => 'withdrawal', 'amount' => 10000, 'party_name' => 'Project Manager', 'purpose' => 'Paid at site',
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('app.cash-trackers.entries.store', $tracker), [
            'date' => '2026-10-03', 'type' => 'withdrawal', 'amount' => 5000, 'party_name' => 'Myself', 'purpose' => 'Kept as petty cash',
        ])->assertRedirect();

        $tracker->refresh();
        $this->assertEqualsWithDelta(55000, $tracker->totalDeposited(), 0.01);
        $this->assertEqualsWithDelta(15000, $tracker->totalWithdrawn(), 0.01);
        $this->assertEqualsWithDelta(40000, $tracker->balance(), 0.01);
    }

    public function test_the_by_party_summary_groups_deposits_and_withdrawals_correctly(): void
    {
        [, $owner] = $this->makeOwner();
        $tracker = CashTracker::create(['company_id' => $owner->company_id, 'name' => 'Site A', 'currency' => 'SAR', 'created_by' => $owner->id]);
        $tracker->entries()->create(['company_id' => $owner->company_id, 'date' => '2026-10-01', 'type' => 'deposit', 'amount' => 55000, 'party_name' => 'Owner', 'purpose' => 'Both transfers']);
        $tracker->entries()->create(['company_id' => $owner->company_id, 'date' => '2026-10-03', 'type' => 'withdrawal', 'amount' => 10000, 'party_name' => 'Project Manager', 'purpose' => 'Site costs']);
        $tracker->entries()->create(['company_id' => $owner->company_id, 'date' => '2026-10-03', 'type' => 'withdrawal', 'amount' => 20000, 'party_name' => 'Project Manager', 'purpose' => 'More site costs']);
        $tracker->entries()->create(['company_id' => $owner->company_id, 'date' => '2026-10-04', 'type' => 'withdrawal', 'amount' => 5000, 'party_name' => 'Owner', 'purpose' => 'Returned unused cash']);

        $response = $this->actingAs($owner)->get(route('app.cash-trackers.show', $tracker));

        $response->assertOk();
        $response->assertSee('Project Manager');
        $response->assertSee(\App\Support\Money::format(30000)); // PM withdrawn total: 10000 + 20000
        $response->assertSee(\App\Support\Money::format(55000)); // owner deposited
    }

    public function test_an_entry_can_be_removed(): void
    {
        [, $owner] = $this->makeOwner();
        $tracker = CashTracker::create(['company_id' => $owner->company_id, 'name' => 'Site A', 'currency' => 'SAR', 'created_by' => $owner->id]);
        $entry = $tracker->entries()->create(['company_id' => $owner->company_id, 'date' => '2026-10-01', 'type' => 'deposit', 'amount' => 1000]);

        $this->actingAs($owner)->delete(route('app.cash-trackers.entries.destroy', [$tracker, $entry]))->assertRedirect();

        $this->assertSame(0, CashTrackerEntry::where('cash_tracker_id', $tracker->id)->count());
    }

    public function test_a_company_cannot_touch_another_companys_tracker_or_entries(): void
    {
        [, $ownerA] = $this->makeOwner();
        [, $ownerB] = $this->makeOwner();
        $trackerB = CashTracker::create(['company_id' => $ownerB->company_id, 'name' => 'Company B Tracker', 'currency' => 'SAR', 'created_by' => $ownerB->id]);

        $this->actingAs($ownerA)->get(route('app.cash-trackers.show', $trackerB))->assertNotFound();
        $this->actingAs($ownerA)->post(route('app.cash-trackers.entries.store', $trackerB), [
            'date' => '2026-10-01', 'type' => 'deposit', 'amount' => 1000,
        ])->assertNotFound();
    }

    public function test_deleting_a_tracker_removes_its_entries_too(): void
    {
        [, $owner] = $this->makeOwner();
        $tracker = CashTracker::create(['company_id' => $owner->company_id, 'name' => 'Site A', 'currency' => 'SAR', 'created_by' => $owner->id]);
        $tracker->entries()->create(['company_id' => $owner->company_id, 'date' => '2026-10-01', 'type' => 'deposit', 'amount' => 1000]);

        $this->actingAs($owner)->delete(route('app.cash-trackers.destroy', $tracker))->assertRedirect();

        $this->assertSame(0, CashTrackerEntry::where('cash_tracker_id', $tracker->id)->count());
    }

    public function test_the_index_page_lists_trackers_with_their_balance(): void
    {
        [, $owner] = $this->makeOwner();
        $tracker = CashTracker::create(['company_id' => $owner->company_id, 'name' => 'Owner transfer — Site A', 'currency' => 'SAR', 'created_by' => $owner->id]);
        $tracker->entries()->create(['company_id' => $owner->company_id, 'date' => '2026-10-01', 'type' => 'deposit', 'amount' => 55000]);
        $tracker->entries()->create(['company_id' => $owner->company_id, 'date' => '2026-10-03', 'type' => 'withdrawal', 'amount' => 15000]);

        $response = $this->actingAs($owner)->get(route('app.cash-trackers.index'));

        $response->assertOk();
        $response->assertSee('Owner transfer — Site A');
        $response->assertSee(\App\Support\Money::format(40000));
    }

    public function test_the_cash_trackers_tab_appears_on_the_bank_accounts_nav(): void
    {
        [, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.cash-custody.index'));

        $response->assertOk();
        $response->assertSee(__('Cash Trackers'));
    }

    public function test_the_report_pdf_downloads(): void
    {
        [, $owner] = $this->makeOwner();
        $tracker = CashTracker::create(['company_id' => $owner->company_id, 'name' => 'Site A', 'currency' => 'SAR', 'created_by' => $owner->id]);
        $tracker->entries()->create(['company_id' => $owner->company_id, 'date' => '2026-10-01', 'type' => 'deposit', 'amount' => 55000, 'party_name' => 'Owner']);
        $tracker->entries()->create(['company_id' => $owner->company_id, 'date' => '2026-10-03', 'type' => 'withdrawal', 'amount' => 10000, 'party_name' => 'Project Manager']);

        $response = $this->actingAs($owner)->get(route('app.cash-trackers.pdf', $tracker));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    /**
     * The entire point of this feature — logging a tracker entry must
     * never create or touch an Account, JournalEntry, or BankAccount row.
     */
    public function test_logging_entries_never_touches_the_real_accounting_tables(): void
    {
        [$company, $owner] = $this->makeOwner();
        $accountsBefore = \App\Models\Account::where('company_id', $company->id)->count();
        $journalEntriesBefore = \App\Models\JournalEntry::where('company_id', $company->id)->count();
        $bankAccountsBefore = \App\Models\BankAccount::where('company_id', $company->id)->count();

        $tracker = CashTracker::create(['company_id' => $company->id, 'name' => 'Site A', 'currency' => 'SAR', 'created_by' => $owner->id]);
        $this->actingAs($owner)->post(route('app.cash-trackers.entries.store', $tracker), [
            'date' => '2026-10-01', 'type' => 'deposit', 'amount' => 25000, 'party_name' => 'Owner',
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('app.cash-trackers.entries.store', $tracker), [
            'date' => '2026-10-03', 'type' => 'withdrawal', 'amount' => 10000, 'party_name' => 'Project Manager',
        ])->assertRedirect();

        $this->assertSame($accountsBefore, \App\Models\Account::where('company_id', $company->id)->count());
        $this->assertSame($journalEntriesBefore, \App\Models\JournalEntry::where('company_id', $company->id)->count());
        $this->assertSame($bankAccountsBefore, \App\Models\BankAccount::where('company_id', $company->id)->count());
    }
}
