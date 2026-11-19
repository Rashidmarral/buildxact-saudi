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
 * Catch-up cleanup for bank accounts deleted BEFORE
 * BankAccountController::destroy() started deactivating its ledger line
 * itself — simulated here by deleting the BankAccount directly (bypassing
 * the controller) so its Account row is orphaned but stays active, the
 * exact state a bank account deleted under the old code was left in.
 */
class DeactivateOrphanedBankLedgerAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $name = 'Dynamic Core Contracting'): Company
    {
        $company = Company::create(['name' => $name, 'slug' => 'dcc-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwnedBankAccount(Company $company, string $name): BankAccount
    {
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => $name, 'type' => 'bank', 'currency' => 'SAR',
        ])->assertSessionDoesntHaveErrors();

        return BankAccount::where('company_id', $company->id)->where('name', $name)->firstOrFail();
    }

    public function test_it_deactivates_an_orphaned_ledger_line_left_by_a_bank_account_deleted_before_the_fix(): void
    {
        $company = $this->makeCompany();
        $bankAccount = $this->makeOwnedBankAccount($company, 'Nasir');
        $accountId = $bankAccount->account_id;

        // Bypasses BankAccountController::destroy() entirely, so the
        // ledger line is left active — exactly the pre-fix state.
        BankAccount::destroy($bankAccount->id);
        $this->assertTrue(Account::find($accountId)->is_active);

        $this->artisan('accounts:deactivate-orphaned-bank-lines', ['--company' => $company->id])
            ->assertExitCode(0);

        $this->assertFalse(Account::find($accountId)->is_active);
    }

    public function test_dry_run_reports_without_changing_anything(): void
    {
        $company = $this->makeCompany();
        $bankAccount = $this->makeOwnedBankAccount($company, 'Khalid');
        $accountId = $bankAccount->account_id;
        BankAccount::destroy($bankAccount->id);

        $this->artisan('accounts:deactivate-orphaned-bank-lines', ['--company' => $company->id, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertTrue(Account::find($accountId)->is_active, '--dry-run must not change anything');
    }

    public function test_a_bank_account_that_still_exists_is_left_untouched(): void
    {
        $company = $this->makeCompany();
        $bankAccount = $this->makeOwnedBankAccount($company, 'Rashid');

        $this->artisan('accounts:deactivate-orphaned-bank-lines', ['--company' => $company->id])
            ->assertExitCode(0);

        $this->assertTrue(Account::find($bankAccount->account_id)->is_active);
    }

    public function test_system_cash_and_bank_accounts_are_never_touched_even_if_unreferenced(): void
    {
        $company = $this->makeCompany();

        $this->artisan('accounts:deactivate-orphaned-bank-lines', ['--company' => $company->id])
            ->assertExitCode(0);

        $this->assertTrue(Account::where('company_id', $company->id)->where('code', '1000')->first()->is_active);
        $this->assertTrue(Account::where('company_id', $company->id)->where('code', '1100')->first()->is_active);
    }

    public function test_scoping_to_one_company_leaves_other_companies_orphans_alone(): void
    {
        $companyA = $this->makeCompany('Company A');
        $companyB = $this->makeCompany('Company B');
        $bankAccountA = $this->makeOwnedBankAccount($companyA, 'Nasir');
        $bankAccountB = $this->makeOwnedBankAccount($companyB, 'Nasir');
        // Deleting the already-loaded instances directly, not a fresh
        // BankAccount::destroy() query — the latter would run under
        // BelongsToCompany's scope for whichever company the test's last
        // actingAs() call authenticated (company B here), silently
        // finding nothing to delete for company A at all.
        $bankAccountA->delete();
        $bankAccountB->delete();

        $this->artisan('accounts:deactivate-orphaned-bank-lines', ['--company' => $companyA->id])
            ->assertExitCode(0);

        // withoutGlobalScope('company') — the test's own last actingAs()
        // was company B's owner, which would otherwise scope company A's
        // account out of a plain find() here.
        $this->assertFalse(Account::withoutGlobalScope('company')->find($bankAccountA->account_id)->is_active);
        $this->assertTrue(Account::withoutGlobalScope('company')->find($bankAccountB->account_id)->is_active, 'a scoped run must not touch another company');
    }
}
