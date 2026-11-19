<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\ReceiptVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i have delete them from bank accounts but when i open trial balance,
 * ledger, journal still those account shows" — deleting a BankAccount
 * never touched its dedicated ledger line (the Account row
 * BankAccount::glAccount() creates), which every report's account filter
 * and Trial Balance/General Ledger query gate on Account::is_active —
 * so a deleted bank account kept showing up everywhere as a live,
 * selectable account forever. BankAccountController::destroy() now
 * deactivates that ledger line once the bank account itself is gone.
 */
class BankAccountDeletionDeactivatesLedgerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_deleting_a_bank_account_deactivates_its_dedicated_ledger_line(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'Nasir', 'type' => 'bank', 'currency' => 'SAR',
        ])->assertSessionDoesntHaveErrors();

        $bankAccount = BankAccount::where('name', 'Nasir')->first();
        $accountId = $bankAccount->account_id;
        $this->assertNotNull($accountId);
        $this->assertTrue(Account::find($accountId)->is_active);

        $this->actingAs($owner)->delete(route('app.bank-accounts.destroy', $bankAccount))
            ->assertRedirect(route('app.bank-accounts.index'));

        $this->assertNull(BankAccount::find($bankAccount->id));
        $this->assertFalse(Account::find($accountId)->is_active, 'the ledger line should be deactivated, not left active, once its bank account is gone');
    }

    public function test_the_deactivated_ledger_line_no_longer_appears_in_the_general_ledger_account_filter(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'Khalid', 'type' => 'bank', 'currency' => 'SAR',
        ])->assertSessionDoesntHaveErrors();
        $bankAccount = BankAccount::where('name', 'Khalid')->first();

        $this->actingAs($owner)->delete(route('app.bank-accounts.destroy', $bankAccount));

        $response = $this->actingAs($owner)->get(route('app.reports.general-ledger'));
        $response->assertOk();
        $allAccounts = $response->viewData('allAccounts');
        $this->assertFalse($allAccounts->contains('name', 'Khalid'), 'a deleted bank account should not still be a selectable filter option');
    }

    public function test_a_bank_account_with_vouchers_still_cannot_be_deleted_and_its_ledger_line_stays_active(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'Rashid', 'type' => 'bank', 'currency' => 'SAR',
        ])->assertSessionDoesntHaveErrors();
        $bankAccount = BankAccount::where('name', 'Rashid')->first();

        ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bankAccount->id, 'party_type' => 'other',
            'voucher_number' => 'REC-00001', 'date' => now(), 'payer_name' => 'A', 'amount' => 500,
            'method' => 'bank_transfer', 'status' => 'issued', 'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->delete(route('app.bank-accounts.destroy', $bankAccount))
            ->assertSessionHasErrors('bank_account');

        $this->assertNotNull(BankAccount::find($bankAccount->id));
        $this->assertTrue(Account::find($bankAccount->account_id)->is_active);
    }
}
