<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding CASH-03: nothing detected or visibly flagged a negative
 * bank/cash balance — a payment voucher could push an account negative
 * with no warning anywhere. This is a visibility fix, not a hard block
 * (BankAccount::currentBalance() itself is untouched and can still go
 * negative) — a company's real bank may allow it (overdraft, a same-day
 * incoming transfer not yet recorded), so the fix is making it visible
 * wherever the balance is already shown, not rejecting the transaction.
 */
class NegativeBalanceFlagTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Negative Balance Co.', 'slug' => 'neg-bal-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_bank_accounts_index_flags_a_negative_balance(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Overdrawn Account', 'type' => 'bank', 'is_active' => true, 'opening_balance' => 0]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Big Payment', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 5000, 'method' => 'cash',
        ]);

        $this->assertEqualsWithDelta(-5000, $account->fresh()->currentBalance(), 0.01);

        $response = $this->actingAs($owner)->get(route('app.bank-accounts.index'));

        $response->assertOk();
        $response->assertSee('Negative balance');
    }

    public function test_bank_accounts_index_does_not_flag_a_positive_balance(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        BankAccount::create(['company_id' => $company->id, 'name' => 'Healthy Account', 'type' => 'bank', 'is_active' => true, 'opening_balance' => 1000]);

        $response = $this->actingAs($owner)->get(route('app.bank-accounts.index'));

        $response->assertOk();
        $response->assertDontSee('Negative balance');
    }

    public function test_the_account_statements_list_also_flags_a_negative_balance(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Overdrawn Account', 'type' => 'bank', 'is_active' => true, 'opening_balance' => 0]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Big Payment', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 5000, 'method' => 'cash',
        ]);

        $response = $this->actingAs($owner)->get(route('app.account-reports.index'));

        $response->assertOk();
        $response->assertSee('text-red-600', false);
    }
}
