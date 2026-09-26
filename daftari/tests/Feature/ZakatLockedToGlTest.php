<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Models\ZakatCalculation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding: the Zakat estimate form displayed the company's real
 * posted equity but let the user freely retype it before submitting —
 * nothing on the server re-derived or cross-checked the figure the base
 * (and therefore the Zakat due) was actually built from. The equity
 * figure is now always recomputed server-side from the ledger; there is
 * nothing left in the request for a user to override it with.
 */
class ZakatLockedToGlTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnerWithEquity(float $equityCredit): array
    {
        $company = Company::create(['name' => 'Zakat Co.', 'slug' => 'zakat-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $equityAccount = Account::where('company_id', $company->id)->where('type', 'equity')->first();
        $cashAccount = Account::where('company_id', $company->id)->where('type', 'asset')->first();

        $entry = JournalEntry::create([
            'company_id' => $company->id, 'entry_number' => 'JE-'.uniqid(), 'entry_date' => now()->subDay()->toDateString(),
            'source_type' => 'manual', 'source_id' => 0, 'description' => 'Owner capital contribution',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $cashAccount->id, 'debit' => $equityCredit, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $equityAccount->id, 'debit' => 0, 'credit' => $equityCredit]);

        return [$company, $owner];
    }

    public function test_a_tampered_equity_amount_in_the_request_is_ignored_in_favor_of_the_real_ledger_figure(): void
    {
        [, $owner] = $this->makeOwnerWithEquity(500000);

        $response = $this->actingAs($owner)->post(route('app.zakat.store'), [
            'period_end_date' => now()->toDateString(),
            'rate_type' => 'hijri',
            'equity_amount' => 999999999, // an attacker-controlled figure the form no longer even sends
        ]);

        $response->assertSessionDoesntHaveErrors();

        $calculation = ZakatCalculation::latest('id')->first();
        $this->assertSame(500000.0, (float) $calculation->equity_amount);
        $this->assertSame(500000.0, (float) $calculation->zakat_base);
        $this->assertEqualsWithDelta(500000 * 0.025, (float) $calculation->zakat_due, 0.01);
    }

    public function test_the_create_form_shows_the_same_ledger_derived_figure_the_store_action_will_use(): void
    {
        [, $owner] = $this->makeOwnerWithEquity(120000);

        $response = $this->actingAs($owner)->get(route('app.zakat.create'));

        $response->assertOk();
        $response->assertSee('120,000.00', false);
        $response->assertDontSee('name="equity_amount"', false);
    }

    public function test_deductions_still_apply_on_top_of_the_locked_equity_figure(): void
    {
        [, $owner] = $this->makeOwnerWithEquity(200000);

        $this->actingAs($owner)->post(route('app.zakat.store'), [
            'period_end_date' => now()->toDateString(),
            'rate_type' => 'gregorian',
            'long_term_liabilities' => 50000,
            'net_fixed_assets' => 30000,
            'other_deductions' => 10000,
        ])->assertSessionDoesntHaveErrors();

        $calculation = ZakatCalculation::latest('id')->first();
        // 200,000 + 50,000 - 30,000 - 10,000 = 210,000
        $this->assertSame(210000.0, (float) $calculation->zakat_base);
        $this->assertEqualsWithDelta(210000 * 0.025775, (float) $calculation->zakat_due, 0.01);
    }
}
