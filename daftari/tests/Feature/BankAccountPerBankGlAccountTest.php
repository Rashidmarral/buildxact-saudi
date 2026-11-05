<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\ReceiptVoucher;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please login and verify each and everything" (Hamly ERP) — Hamly gives
 * every real bank/cash account its own ledger line instead of every
 * account of a type sharing one generic "Bank"/"Cash" account. This
 * mirrors that: BankAccount::glAccount() creates (and reuses) a
 * dedicated Account per BankAccount, and LedgerPostingService routes
 * every bank/cash posting through it instead of DEFAULT_BANK/DEFAULT_CASH.
 */
class BankAccountPerBankGlAccountTest extends TestCase
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

    public function test_creating_a_bank_account_gives_it_its_own_dedicated_ledger_line(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'SAB Bank', 'type' => 'bank', 'currency' => 'SAR',
        ])->assertSessionDoesntHaveErrors();

        $bankAccount = BankAccount::where('name', 'SAB Bank')->first();
        $this->assertNotNull($bankAccount->account_id);
        $this->assertSame('SAB Bank', $bankAccount->account->name);
        $this->assertSame('asset', $bankAccount->account->type);
        // Bank-type accounts are allocated from 1101–1149, below the next
        // system code in that family (1150, POS Card Clearing).
        $this->assertGreaterThanOrEqual(1101, (int) $bankAccount->account->code);
        $this->assertLessThanOrEqual(1149, (int) $bankAccount->account->code);
    }

    public function test_a_cash_type_account_is_allocated_from_the_cash_code_range(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR',
        ]);

        $bankAccount = BankAccount::where('name', 'Petty Cash')->first();
        $this->assertGreaterThanOrEqual(1001, (int) $bankAccount->account->code);
        $this->assertLessThanOrEqual(1099, (int) $bankAccount->account->code);
    }

    public function test_two_bank_accounts_in_the_same_company_get_two_different_codes(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.bank-accounts.store'), ['name' => 'SAB Bank', 'type' => 'bank', 'currency' => 'SAR']);
        $this->actingAs($owner)->post(route('app.bank-accounts.store'), ['name' => 'SNB Al Ahli', 'type' => 'bank', 'currency' => 'SAR']);

        $codes = BankAccount::whereIn('name', ['SAB Bank', 'SNB Al Ahli'])->get()->pluck('account.code')->all();
        $this->assertCount(2, array_unique($codes));
    }

    public function test_renaming_a_bank_account_renames_its_ledger_line(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.bank-accounts.store'), ['name' => 'SAB Bank', 'type' => 'bank', 'currency' => 'SAR']);
        $bankAccount = BankAccount::where('name', 'SAB Bank')->first();

        $this->actingAs($owner)->put(route('app.bank-accounts.update', $bankAccount), [
            'name' => 'SAB Bank - Main Branch', 'type' => 'bank', 'currency' => 'SAR',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('SAB Bank - Main Branch', $bankAccount->account->fresh()->name);
    }

    public function test_an_existing_bank_account_created_before_this_feature_gets_a_ledger_line_lazily(): void
    {
        $company = $this->makeCompany();
        // Bypasses the controller entirely — simulates a BankAccount row
        // that already existed before account_id was introduced (exactly
        // what the backfill migration processes).
        $bankAccount = BankAccount::create([
            'company_id' => $company->id, 'name' => 'Arab National Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true,
        ]);
        $this->assertNull($bankAccount->account_id);

        $account = $bankAccount->glAccount();

        $this->assertNotNull($account->id);
        $this->assertSame($account->id, $bankAccount->fresh()->account_id);
        // Idempotent: calling it again returns the same account, doesn't
        // allocate a second code.
        $this->assertSame($account->id, $bankAccount->glAccount()->id);
    }

    public function test_a_receipt_voucher_posts_to_its_own_bank_accounts_ledger_line_not_the_shared_default(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $sab = BankAccount::create(['company_id' => $company->id, 'name' => 'SAB Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $sabAccount = $sab->glAccount();
        $defaultBank = AccountMapping::resolve($company->id, 'DEFAULT_BANK');

        $voucher = ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $sab->id, 'party_type' => 'other',
            'voucher_number' => 'REC-00001', 'date' => now(), 'payer_name' => 'Walk-in', 'amount' => 5000,
            'method' => 'bank_transfer', 'status' => 'issued', 'created_by' => $owner->id,
        ]);

        $entry = app(LedgerPostingService::class)->postReceiptVoucher($voucher);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(5000, $entry->lines->where('account_id', $sabAccount->id)->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(0, $entry->lines->where('account_id', $defaultBank->id)->sum('debit'), 0.01);
    }

    public function test_two_different_bank_accounts_receipts_land_on_two_different_ledger_lines(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $sab = BankAccount::create(['company_id' => $company->id, 'name' => 'SAB Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $snb = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB Al Ahli', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $ledger = app(LedgerPostingService::class);
        $v1 = ReceiptVoucher::create(['company_id' => $company->id, 'bank_account_id' => $sab->id, 'party_type' => 'other', 'voucher_number' => 'REC-00001', 'date' => now(), 'payer_name' => 'A', 'amount' => 1000, 'method' => 'bank_transfer', 'status' => 'issued', 'created_by' => $owner->id]);
        $v2 = ReceiptVoucher::create(['company_id' => $company->id, 'bank_account_id' => $snb->id, 'party_type' => 'other', 'voucher_number' => 'REC-00002', 'date' => now(), 'payer_name' => 'B', 'amount' => 2000, 'method' => 'bank_transfer', 'status' => 'issued', 'created_by' => $owner->id]);
        $ledger->postReceiptVoucher($v1);
        $ledger->postReceiptVoucher($v2);

        // Re-fetched fresh: $sab/$snb were loaded before the ledger calls
        // (made through $v1->bankAccount/$v2->bankAccount — separate
        // instances of the same rows) persisted their account_id.
        $sab = $sab->fresh();
        $snb = $snb->fresh();
        $this->assertNotSame($sab->account_id, $snb->account_id);
        $this->assertEqualsWithDelta(1000, $sab->glAccount()->journalEntryLines()->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(2000, $snb->glAccount()->journalEntryLines()->sum('debit'), 0.01);
    }

    public function test_gl_opening_balance_can_be_transferred_once_from_the_shared_account(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.bank-accounts.store'), ['name' => 'SAB Bank', 'type' => 'bank', 'currency' => 'SAR']);
        $bankAccount = BankAccount::where('name', 'SAB Bank')->first();
        $defaultBank = AccountMapping::resolve($company->id, 'DEFAULT_BANK');

        $response = $this->actingAs($owner)->post(route('app.bank-accounts.transfer-gl-opening-balance', $bankAccount), [
            'amount' => 10000, 'date' => now()->toDateString(),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'bank_account_gl_transfer')->where('source_id', $bankAccount->id)->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(10000, $entry->lines->where('account_id', $bankAccount->account_id)->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(10000, $entry->lines->where('account_id', $defaultBank->id)->sum('credit'), 0.01);
    }

    public function test_gl_opening_balance_transfer_is_blocked_once_already_done(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.bank-accounts.store'), ['name' => 'SAB Bank', 'type' => 'bank', 'currency' => 'SAR']);
        $bankAccount = BankAccount::where('name', 'SAB Bank')->first();
        $this->actingAs($owner)->post(route('app.bank-accounts.transfer-gl-opening-balance', $bankAccount), ['amount' => 10000, 'date' => now()->toDateString()]);

        $response = $this->actingAs($owner)->post(route('app.bank-accounts.transfer-gl-opening-balance', $bankAccount), ['amount' => 500, 'date' => now()->toDateString()]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'bank_account_gl_transfer')->where('source_id', $bankAccount->id)->count());
    }
}
