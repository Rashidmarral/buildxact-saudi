<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please login and verify each and everything" (Hamly ERP) — Hamly
 * tracks a post-dated cheque as its own holding account (received: under
 * collection, issued: outstanding) rather than real cash the moment it's
 * handed over. method='cheque' already existed on both voucher types but
 * posted straight to the bank account like a same-day transfer; it now
 * holds until explicitly marked cleared.
 */
class ChequeTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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
        return BankAccount::create(['company_id' => $company->id, 'name' => 'SAB Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
    }

    public function test_a_received_cheque_posts_to_the_holding_account_not_the_bank(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'manual', 'payer_name' => 'Walk-in customer',
            'bank_account_id' => $bank->id, 'date' => now()->toDateString(), 'amount' => 5000,
            'method' => 'cheque', 'cheque_number' => 'CHQ-9981', 'cheque_date' => now()->addDays(45)->toDateString(),
        ])->assertSessionDoesntHaveErrors();

        $voucher = ReceiptVoucher::first();
        $this->assertSame('CHQ-9981', $voucher->cheque_number);
        $this->assertNull($voucher->cheque_cleared_at);

        $entry = JournalEntry::where('source_type', 'receipt_voucher')->where('source_id', $voucher->id)->first();
        $holding = AccountMapping::resolve($company->id, 'CHEQUES_RECEIVED_HOLDING');
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(5000, $entry->lines->where('account_id', $holding->id)->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(0, $entry->lines->where('account_id', $bank->fresh()->account_id)->sum('debit'), 0.01);
    }

    public function test_marking_a_received_cheque_cleared_moves_it_into_the_banks_own_ledger_line(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company);
        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'manual', 'payer_name' => 'Walk-in customer',
            'bank_account_id' => $bank->id, 'date' => now()->toDateString(), 'amount' => 5000,
            'method' => 'cheque', 'cheque_number' => 'CHQ-9981', 'cheque_date' => now()->addDays(45)->toDateString(),
        ]);
        $voucher = ReceiptVoucher::first();

        $response = $this->actingAs($owner)->post(route('app.receipt-vouchers.mark-cheque-cleared', $voucher), [
            'cheque_cleared_at' => now()->toDateString(),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $voucher->refresh();
        $this->assertNotNull($voucher->cheque_cleared_at);

        $clearance = JournalEntry::where('source_type', 'receipt_voucher_cheque_clearance')->where('source_id', $voucher->id)->first();
        $holding = AccountMapping::resolve($company->id, 'CHEQUES_RECEIVED_HOLDING');
        $this->assertNotNull($clearance);
        $this->assertEqualsWithDelta(5000, $clearance->lines->where('account_id', $bank->fresh()->account_id)->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(5000, $clearance->lines->where('account_id', $holding->id)->sum('credit'), 0.01);
    }

    public function test_a_cheque_cannot_be_marked_cleared_twice(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company);
        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'manual', 'payer_name' => 'Walk-in customer',
            'bank_account_id' => $bank->id, 'date' => now()->toDateString(), 'amount' => 5000,
            'method' => 'cheque', 'cheque_number' => 'CHQ-9981', 'cheque_date' => now()->addDays(45)->toDateString(),
        ]);
        $voucher = ReceiptVoucher::first();
        $this->actingAs($owner)->post(route('app.receipt-vouchers.mark-cheque-cleared', $voucher), ['cheque_cleared_at' => now()->toDateString()]);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.mark-cheque-cleared', $voucher), ['cheque_cleared_at' => now()->toDateString()]);

        $this->assertSame(1, JournalEntry::where('source_type', 'receipt_voucher_cheque_clearance')->where('source_id', $voucher->id)->count());
    }

    public function test_voiding_an_uncleared_received_cheque_reverses_the_holding_entry(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company);
        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'manual', 'payer_name' => 'Walk-in customer',
            'bank_account_id' => $bank->id, 'date' => now()->toDateString(), 'amount' => 5000,
            'method' => 'cheque', 'cheque_number' => 'CHQ-9981', 'cheque_date' => now()->addDays(45)->toDateString(),
        ]);
        $voucher = ReceiptVoucher::first();

        $this->actingAs($owner)->post(route('app.receipt-vouchers.void', $voucher))->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('journal_entries', ['source_type' => 'receipt_voucher_reversal', 'source_id' => $voucher->id]);
    }

    public function test_a_cleared_cheque_cannot_be_voided(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company);
        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'manual', 'payer_name' => 'Walk-in customer',
            'bank_account_id' => $bank->id, 'date' => now()->toDateString(), 'amount' => 5000,
            'method' => 'cheque', 'cheque_number' => 'CHQ-9981', 'cheque_date' => now()->addDays(45)->toDateString(),
        ]);
        $voucher = ReceiptVoucher::first();
        $this->actingAs($owner)->post(route('app.receipt-vouchers.mark-cheque-cleared', $voucher), ['cheque_cleared_at' => now()->toDateString()]);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.void', $voucher->fresh()));

        $this->assertSame('issued', $voucher->fresh()->status);
    }

    public function test_an_issued_cheque_posts_to_the_outstanding_holding_account_not_the_bank(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'supplier', 'supplier_id' => $supplier->id, 'payee_name' => $supplier->name,
            'bank_account_id' => $bank->id, 'date' => now()->toDateString(), 'amount' => 8000,
            'method' => 'cheque', 'cheque_number' => 'CHQ-100', 'cheque_date' => now()->addDays(30)->toDateString(),
        ])->assertSessionDoesntHaveErrors();

        $voucher = PaymentVoucher::first();
        $entry = JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->first();
        $holding = AccountMapping::resolve($company->id, 'CHEQUES_ISSUED_HOLDING');
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(8000, $entry->lines->where('account_id', $holding->id)->sum('credit'), 0.01);
        $this->assertEqualsWithDelta(0, $entry->lines->where('account_id', $bank->fresh()->account_id)->sum('credit'), 0.01);
    }

    public function test_marking_an_issued_cheque_cleared_moves_it_out_of_the_banks_own_ledger_line(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.', 'is_active' => true]);
        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'supplier', 'supplier_id' => $supplier->id, 'payee_name' => $supplier->name,
            'bank_account_id' => $bank->id, 'date' => now()->toDateString(), 'amount' => 8000,
            'method' => 'cheque', 'cheque_number' => 'CHQ-100', 'cheque_date' => now()->addDays(30)->toDateString(),
        ]);
        $voucher = PaymentVoucher::first();

        $response = $this->actingAs($owner)->post(route('app.payment-vouchers.mark-cheque-cleared', $voucher), [
            'cheque_cleared_at' => now()->toDateString(),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $clearance = JournalEntry::where('source_type', 'payment_voucher_cheque_clearance')->where('source_id', $voucher->id)->first();
        $holding = AccountMapping::resolve($company->id, 'CHEQUES_ISSUED_HOLDING');
        $this->assertNotNull($clearance);
        $this->assertEqualsWithDelta(8000, $clearance->lines->where('account_id', $holding->id)->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(8000, $clearance->lines->where('account_id', $bank->fresh()->account_id)->sum('credit'), 0.01);
    }

    public function test_a_normal_bank_transfer_voucher_is_unaffected_by_cheque_tracking(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'manual', 'payer_name' => 'Walk-in customer',
            'bank_account_id' => $bank->id, 'date' => now()->toDateString(), 'amount' => 3000,
            'method' => 'bank_transfer',
        ])->assertSessionDoesntHaveErrors();

        $voucher = ReceiptVoucher::first();
        $entry = JournalEntry::where('source_type', 'receipt_voucher')->where('source_id', $voucher->id)->first();
        $this->assertEqualsWithDelta(3000, $entry->lines->where('account_id', $bank->fresh()->account_id)->sum('debit'), 0.01);
    }
}
