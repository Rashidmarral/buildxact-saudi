<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding GL-7: there was no traceable, safeguarded way to enter
 * opening balances for a company onboarding mid-year — only
 * BankAccount::opening_balance existed, and only for bank/cash accounts.
 * Scoped to General Ledger accounts only — see
 * docs/audit/INITIAL_AUDIT_REPORT.md for why customer/supplier opening
 * balances need a different, separate mechanism to stay consistent with
 * their own aging/balance (derived from real Invoice/Bill rows, not a
 * ledger field).
 */
class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Opening Balance Co.', 'slug' => 'opening-balance-'.uniqid()]);
        Account::seedSystemAccounts($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_balanced_opening_entries_post_with_no_equity_plug(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.opening-balances.store'), [
            'as_of_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 50000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 50000],
            ],
        ]);

        $response->assertRedirect();
        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'opening_balance')->first();
        $this->assertNotNull($entry);
        $this->assertSame(2, $entry->lines()->count(), 'A balanced entry needs no extra equity-plug line.');
        $this->assertSame(50000.0, $entry->totalDebit());
    }

    public function test_an_unbalanced_entry_is_automatically_plugged_into_owners_equity(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $inventory = Account::where('company_id', $company->id)->where('code', '1400')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        // Entered balances: Cash 30,000 debit, Inventory 20,000 debit —
        // nothing entered on the credit side at all. The user shouldn't
        // need to know this has to balance against equity themselves.
        $this->actingAs($owner)->post(route('app.opening-balances.store'), [
            'as_of_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 30000, 'credit' => 0],
                ['account_id' => $inventory->id, 'debit' => 20000, 'credit' => 0],
            ],
        ])->assertRedirect();

        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'opening_balance')->first();
        $this->assertNotNull($entry);
        $equityLine = $entry->lines()->where('account_id', $equity->id)->first();
        $this->assertNotNull($equityLine);
        $this->assertEqualsWithDelta(50000, (float) $equityLine->credit, 0.01);
        $this->assertSame(50000.0, $entry->totalDebit());
        $this->assertSame(50000.0, $entry->totalCredit());
    }

    public function test_opening_balances_can_only_be_recorded_once(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->actingAs($owner)->post(route('app.opening-balances.store'), [
            'as_of_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $response = $this->actingAs($owner)->post(route('app.opening-balances.store'), [
            'as_of_date' => '2026-02-01',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 500],
            ],
        ]);

        $response->assertSessionHasErrors('lines');
        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'opening_balance')->count());
    }

    public function test_the_create_page_shows_the_existing_entry_once_recorded(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->actingAs($owner)->post(route('app.opening-balances.store'), [
            'as_of_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $response = $this->actingAs($owner)->get(route('app.opening-balances.create'));

        $response->assertOk();
        $response->assertSee('already been recorded');
    }

    public function test_recording_opening_balances_is_audit_logged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->actingAs($owner)->post(route('app.opening-balances.store'), [
            'as_of_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $this->assertNotNull(AuditLog::forCompany($company->id)->where('action', 'opening_balance.create')->first());
    }
}
