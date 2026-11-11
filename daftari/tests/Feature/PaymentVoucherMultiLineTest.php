<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "can we add multiple entries on single transaction like we have made a
 * payment to supplier like 789.43 with that bank have charged us it's
 * service charges and vat like 1.15 how we can mange this" — a Payment
 * Voucher's main amount/counter_account_id (what the supplier was
 * actually paid, still what bill settlement/subcontract linkage/WHT key
 * off) stays unchanged; "lines" are extra debit entries riding along in
 * the SAME bank transaction (bank charges, VAT on them, ...), each
 * against its own account, increasing what the bank/cash side is
 * credited for — the real total that left the account.
 */
class PaymentVoucherMultiLineTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeBankAccount(Company $company): BankAccount
    {
        return BankAccount::create(['company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR']);
    }

    private function bankChargesAccount(Company $company): Account
    {
        return Account::where('company_id', $company->id)->where('code', '5180')->firstOrFail();
    }

    private function vatInputAccount(Company $company): Account
    {
        return Account::where('company_id', $company->id)->where('code', '1300')->firstOrFail();
    }

    public function test_creating_a_voucher_with_extra_lines_posts_them_and_increases_the_bank_credit(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = $this->makeBankAccount($company);
        $bankCharges = $this->bankChargesAccount($company);
        $vatInput = $this->vatInputAccount($company);

        $response = $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'ABC Supplies',
            'bank_account_id' => $bankAccount->id, 'date' => now()->toDateString(),
            'amount' => 789.43, 'method' => 'bank_transfer',
            'lines' => [
                ['account_id' => $bankCharges->id, 'amount' => 1.00, 'description' => 'Bank service charge'],
                ['account_id' => $vatInput->id, 'amount' => 0.15, 'description' => 'VAT on bank charge'],
            ],
        ]);

        $response->assertRedirect();
        $voucher = PaymentVoucher::first();
        $this->assertNotNull($voucher);
        $this->assertCount(2, $voucher->lines);
        $this->assertSame(1.15, $voucher->extraLinesTotal());
        $this->assertSame(790.58, $voucher->totalAmount());

        $entry = JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->with('lines')->firstOrFail();
        $this->assertEqualsWithDelta(790.58, (float) $entry->lines->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(790.58, (float) $entry->lines->sum('credit'), 0.001);

        $bankLine = $entry->lines->firstWhere('account_id', $bankAccount->refresh()->glAccount()->id);
        $this->assertSame(790.58, (float) $bankLine->credit);

        $chargeLine = $entry->lines->firstWhere('account_id', $bankCharges->id);
        $this->assertSame(1.00, (float) $chargeLine->debit);

        $vatLine = $entry->lines->firstWhere('account_id', $vatInput->id);
        $this->assertSame(0.15, (float) $vatLine->debit);
    }

    public function test_bank_account_balance_reflects_the_full_transaction_including_extra_lines(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = $this->makeBankAccount($company);
        $bankCharges = $this->bankChargesAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'ABC Supplies',
            'bank_account_id' => $bankAccount->id, 'date' => now()->toDateString(),
            'amount' => 789.43, 'method' => 'bank_transfer',
            'lines' => [['account_id' => $bankCharges->id, 'amount' => 1.15, 'description' => 'Bank charges + VAT']],
        ]);

        $bankAccount->refresh();
        $this->assertEqualsWithDelta(-790.58, $bankAccount->currentBalance(), 0.001);
    }

    public function test_updating_a_voucher_replaces_its_lines_and_reposts_correctly(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = $this->makeBankAccount($company);
        $bankCharges = $this->bankChargesAccount($company);
        $vatInput = $this->vatInputAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'ABC Supplies',
            'bank_account_id' => $bankAccount->id, 'date' => now()->toDateString(),
            'amount' => 789.43, 'method' => 'bank_transfer',
            'lines' => [['account_id' => $bankCharges->id, 'amount' => 1.00]],
        ]);
        $voucher = PaymentVoucher::first();

        $this->actingAs($owner)->put(route('app.payment-vouchers.update', $voucher), [
            'party_type' => 'manual', 'payee_name' => 'ABC Supplies',
            'bank_account_id' => $bankAccount->id, 'date' => now()->toDateString(),
            'amount' => 789.43, 'method' => 'bank_transfer',
            'lines' => [
                ['account_id' => $bankCharges->id, 'amount' => 1.00],
                ['account_id' => $vatInput->id, 'amount' => 0.15],
            ],
        ])->assertRedirect();

        $voucher->refresh();
        $this->assertCount(2, $voucher->lines);
        $this->assertSame(790.58, $voucher->totalAmount());

        $entry = JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->with('lines')->firstOrFail();
        $this->assertEqualsWithDelta(790.58, (float) $entry->lines->sum('debit'), 0.001);
    }

    public function test_voiding_a_voucher_with_extra_lines_nets_everything_to_zero(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = $this->makeBankAccount($company);
        $bankCharges = $this->bankChargesAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'ABC Supplies',
            'bank_account_id' => $bankAccount->id, 'date' => now()->toDateString(),
            'amount' => 789.43, 'method' => 'bank_transfer',
            'lines' => [['account_id' => $bankCharges->id, 'amount' => 1.15]],
        ]);
        $voucher = PaymentVoucher::first();

        $this->actingAs($owner)->post(route('app.payment-vouchers.void', $voucher))->assertRedirect();

        $bankAccount->refresh();
        $this->assertSame(0.0, $bankAccount->currentBalance());

        $bankChargesBalance = JournalEntry::where('company_id', $company->id)
            ->whereHas('lines', fn ($q) => $q->where('account_id', $bankCharges->id))
            ->with('lines')
            ->get()
            ->flatMap->lines
            ->where('account_id', $bankCharges->id);
        $this->assertSame(1.15, (float) $bankChargesBalance->sum('debit'));
        $this->assertSame(1.15, (float) $bankChargesBalance->sum('credit'));
    }

    public function test_a_line_cannot_use_another_companys_account(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = $this->makeBankAccount($company);

        $otherCompany = $this->makeCompany();
        $foreignAccount = $this->bankChargesAccount($otherCompany);

        $response = $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'ABC Supplies',
            'bank_account_id' => $bankAccount->id, 'date' => now()->toDateString(),
            'amount' => 789.43, 'method' => 'bank_transfer',
            'lines' => [['account_id' => $foreignAccount->id, 'amount' => 1.00]],
        ]);

        $response->assertSessionHasErrors('lines.0.account_id');
        $this->assertDatabaseMissing('payment_vouchers', ['payee_name' => 'ABC Supplies']);
    }

    public function test_a_voucher_with_no_extra_lines_behaves_exactly_as_before(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'ABC Supplies',
            'bank_account_id' => $bankAccount->id, 'date' => now()->toDateString(),
            'amount' => 789.43, 'method' => 'bank_transfer',
        ])->assertRedirect();

        $voucher = PaymentVoucher::first();
        $this->assertCount(0, $voucher->lines);
        $this->assertSame(789.43, $voucher->totalAmount());

        $entry = JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->with('lines')->firstOrFail();
        $this->assertCount(2, $entry->lines);
    }
}
