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
 * Security audit finding M-01: AccountController::validated() already
 * required is_active on the account it's editing, but every other place
 * that lets a user pick an arbitrary GL account for a transaction line
 * (manual journal entries, and — found by the same sweep — expenses,
 * fixed assets, payment/receipt voucher counter-accounts, recurring
 * expenses/journal entries) accepted a deactivated account_id just fine,
 * letting anyone keep posting to an account the business had deliberately
 * retired.
 */
class DeactivatedAccountCannotReceivePostingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'status' => 'active']);
    }

    public function test_a_manual_journal_entry_cannot_post_to_a_deactivated_account(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $inactive = Account::create(['company_id' => $company->id, 'code' => '9999', 'name' => 'Retired', 'type' => 'expense', 'normal_balance' => 'debit', 'is_active' => false]);

        $response = $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Test entry',
            'lines' => [
                ['account_id' => $inactive->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.account_id');
        $this->assertDatabaseMissing('journal_entries', ['description' => 'Test entry']);
    }

    public function test_an_expense_cannot_override_its_posting_account_with_a_deactivated_one(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $inactive = Account::create(['company_id' => $company->id, 'code' => '9998', 'name' => 'Retired Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'is_active' => false]);

        $response = $this->actingAs($owner)->post(route('app.expenses.store'), [
            'account_id' => $inactive->id,
            'gross_amount' => 115, 'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('account_id');
    }

    public function test_a_payment_voucher_cannot_use_a_deactivated_counter_account(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'is_active' => true]);
        $inactive = Account::create(['company_id' => $company->id, 'code' => '9997', 'name' => 'Retired Counter', 'type' => 'expense', 'normal_balance' => 'debit', 'is_active' => false]);

        $response = $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'bank_account_id' => $bankAccount->id, 'counter_account_id' => $inactive->id,
            'date' => now()->toDateString(), 'payee_name' => 'Vendor X', 'amount' => 100,
        ]);

        $response->assertSessionHasErrors('counter_account_id');
    }

    public function test_an_active_account_still_works_for_a_manual_journal_entry(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();

        $response = $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Transfer to bank',
            'lines' => [
                ['account_id' => $bank->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('journal_entries', ['description' => 'Transfer to bank']);
    }
}
