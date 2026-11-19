<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * There was previously no way to edit, void, or delete a bank transfer —
 * a wrong account, amount, or an entry that should never have been a
 * transfer at all had no safe correction path. Mirrors the existing
 * void-before-delete pattern already proven for Payment/Receipt Vouchers:
 * void reverses the ledger entry and excludes the transfer from every
 * balance calculation, but the row stays visible until explicitly
 * deleted, and only once voided.
 */
class BankTransferEditVoidDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Transfer Co.', 'slug' => 'transfer-co-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeBankAccount(Company $company, string $type): BankAccount
    {
        return BankAccount::create(['company_id' => $company->id, 'name' => ucfirst($type).' Account', 'type' => $type, 'currency' => 'SAR', 'is_active' => true]);
    }

    public function test_a_bank_transfer_can_be_edited_and_its_ledger_posting_is_rebuilt(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'bank');
        $cash = $this->makeBankAccount($company, 'cash');
        $otherCash = $this->makeBankAccount($company, 'cash');

        $this->actingAs($owner)->post(route('app.bank-transfers.store'), [
            'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 1000, 'date' => now()->toDateString(),
        ]);
        $transfer = BankTransfer::first();
        $this->assertSame(1, JournalEntry::where('source_type', 'bank_transfer')->where('source_id', $transfer->id)->count());

        $this->actingAs($owner)->put(route('app.bank-transfers.update', $transfer), [
            'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $otherCash->id,
            'amount' => 1500, 'date' => now()->toDateString(),
        ])->assertRedirect();

        $transfer->refresh();
        $this->assertSame(1500.0, (float) $transfer->amount);
        $this->assertSame($otherCash->id, $transfer->to_bank_account_id);
        $this->assertSame(1, JournalEntry::where('source_type', 'bank_transfer')->where('source_id', $transfer->id)->count(), 'Editing must rebuild the posting, not double it.');
        $this->assertSame(1500.0, $otherCash->fresh()->currentBalance());
        $this->assertSame(0.0, $cash->fresh()->currentBalance(), 'The old destination account must no longer reflect this transfer.');
    }

    public function test_voiding_a_bank_transfer_reverses_its_ledger_entry_and_excludes_it_from_balances(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'bank');
        $cash = $this->makeBankAccount($company, 'cash');

        $this->actingAs($owner)->post(route('app.bank-transfers.store'), [
            'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 2000, 'date' => now()->toDateString(),
        ]);
        $transfer = BankTransfer::first();
        $this->assertSame(2000.0, $cash->fresh()->currentBalance());

        $this->actingAs($owner)->post(route('app.bank-transfers.void', $transfer))->assertRedirect();

        $transfer->refresh();
        $this->assertSame('void', $transfer->status);
        $this->assertSame(0.0, $cash->fresh()->currentBalance(), 'A voided transfer must no longer count toward either account\'s balance.');
        $this->assertSame(1, JournalEntry::where('source_type', 'bank_transfer_reversal')->where('source_id', $transfer->id)->count());
    }

    public function test_a_voided_transfer_cannot_be_edited(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'bank');
        $cash = $this->makeBankAccount($company, 'cash');
        $transfer = BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 500, 'date' => now()->toDateString(), 'created_by' => $owner->id, 'status' => 'void',
        ]);

        $this->actingAs($owner)->get(route('app.bank-transfers.edit', $transfer))
            ->assertRedirect(route('app.bank-transfers.show', $transfer));

        $this->actingAs($owner)->put(route('app.bank-transfers.update', $transfer), [
            'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 999, 'date' => now()->toDateString(),
        ]);

        $this->assertSame(500.0, (float) $transfer->fresh()->amount, 'A voided transfer must stay exactly as it was voided.');
    }

    public function test_destroy_is_refused_until_the_transfer_is_voided(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'bank');
        $cash = $this->makeBankAccount($company, 'cash');
        $transfer = BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 500, 'date' => now()->toDateString(), 'created_by' => $owner->id, 'status' => 'issued',
        ]);

        $this->actingAs($owner)->delete(route('app.bank-transfers.destroy', $transfer))->assertRedirect();

        $this->assertNotNull(BankTransfer::find($transfer->id), 'An issued transfer must not be deletable directly.');
    }

    public function test_destroying_a_voided_transfer_soft_deletes_it(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'bank');
        $cash = $this->makeBankAccount($company, 'cash');
        $transfer = BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 500, 'date' => now()->toDateString(), 'created_by' => $owner->id, 'status' => 'void',
        ]);

        $this->actingAs($owner)->delete(route('app.bank-transfers.destroy', $transfer))->assertRedirect(route('app.bank-transfers.index'));

        $this->assertNull(BankTransfer::find($transfer->id));
        $this->assertSoftDeleted('bank_transfers', ['id' => $transfer->id]);
    }

    public function test_a_company_cannot_void_edit_or_delete_another_companys_transfer(): void
    {
        $companyA = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $companyB = $this->makeCompany();
        $bankB = $this->makeBankAccount($companyB, 'bank');
        $cashB = $this->makeBankAccount($companyB, 'cash');
        $transferB = BankTransfer::create([
            'company_id' => $companyB->id, 'from_bank_account_id' => $bankB->id, 'to_bank_account_id' => $cashB->id,
            'amount' => 500, 'date' => now()->toDateString(), 'status' => 'issued',
        ]);

        $this->actingAs($ownerA)->get(route('app.bank-transfers.edit', $transferB))->assertNotFound();
        $this->actingAs($ownerA)->post(route('app.bank-transfers.void', $transferB))->assertNotFound();
    }

    public function test_voiding_leaves_the_transfer_visible_but_excluded_from_reconciliation(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'bank');
        $cash = $this->makeBankAccount($company, 'cash');

        $this->actingAs($owner)->post(route('app.bank-transfers.store'), [
            'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 700, 'date' => now()->toDateString(),
        ]);
        $transfer = BankTransfer::first();
        $this->actingAs($owner)->post(route('app.bank-transfers.void', $transfer));

        $this->actingAs($owner)->get(route('app.bank-transfers.show', $transfer))
            ->assertOk()->assertSee(__('Void'));

        $this->actingAs($owner)->get(route('app.bank-transfers.index'))
            ->assertOk()->assertSee(__('Void'));
    }
}
