<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding GL-06: OpeningBalanceController's own docblock already
 * explains why customer/supplier opening balances are deliberately not
 * part of this GL-only form — a balance posted straight to the Accounts
 * Receivable/Payable control account would never match what this system
 * actually treats as the real AR/AP subledger total (the live sum of
 * Invoice/Bill rows). Nothing previously stopped a user from picking
 * that exact account from the dropdown anyway.
 */
class OpeningBalanceControlAccountGuardTest extends TestCase
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

    public function test_an_accounts_receivable_opening_line_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $ar = Account::where('company_id', $company->id)->where('code', '1200')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.opening-balances.store'), [
            'as_of_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $ar->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 5000],
            ],
        ]);

        $response->assertSessionHasErrors('lines');
        $this->assertSame(0, JournalEntry::where('company_id', $company->id)->count());
    }

    public function test_an_accounts_payable_opening_line_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $ap = Account::where('company_id', $company->id)->where('code', '2000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.opening-balances.store'), [
            'as_of_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $equity->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $ap->id, 'debit' => 0, 'credit' => 5000],
            ],
        ]);

        $response->assertSessionHasErrors('lines');
        $this->assertSame(0, JournalEntry::where('company_id', $company->id)->count());
    }

    public function test_ordinary_gl_accounts_are_unaffected_by_the_guard(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.opening-balances.store'), [
            'as_of_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 5000],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors('lines');
        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->count());
    }
}
