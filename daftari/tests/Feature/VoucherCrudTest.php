<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature request: vouchers only supported add/void — no real edit or
 * delete. This adds full CRUD: edit() rebuilds the ledger posting from
 * scratch (delete + repost, the same pattern LedgerPostingService already
 * documents for edited records) and correctly unwinds/reapplies the linked
 * bill/invoice payment side effects; destroy() is only ever allowed on an
 * already-voided voucher, since void() is what safely reverses the ledger
 * and any linked payment — deleting is just removing the (already inert)
 * row afterwards, preserving the reversed journal entries as an audit trail.
 */
class VoucherCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Voucher CRUD Co.', 'slug' => 'voucher-crud-'.uniqid()]);
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
        return BankAccount::create([
            'company_id' => $company->id, 'name' => 'Main Account', 'bank_name' => 'Al Rajhi Bank',
            'type' => 'bank', 'is_active' => true,
        ]);
    }

    public function test_the_edit_page_loads_for_an_issued_payment_voucher(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Edit Me', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 500, 'method' => 'cash',
        ]);
        $voucher = PaymentVoucher::first();

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.edit', $voucher));

        $response->assertOk();
        $response->assertSee('Edit Me');
    }

    public function test_updating_a_manual_payment_voucher_changes_the_amount_and_reposts_the_ledger(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Original Payee', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 500, 'method' => 'cash',
        ]);
        $voucher = PaymentVoucher::first();
        $this->assertSame(1, JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->count());

        $this->actingAs($owner)->put(route('app.payment-vouchers.update', $voucher), [
            'party_type' => 'manual', 'payee_name' => 'Updated Payee', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 900, 'method' => 'cash',
        ])->assertRedirect(route('app.payment-vouchers.show', $voucher));

        $voucher->refresh();
        $this->assertSame('Updated Payee', $voucher->payee_name);
        $this->assertSame(900.0, (float) $voucher->amount);

        $this->assertSame(1, JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->count());
        $entry = JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->first();
        $this->assertSame(900.0, (float) $entry->lines()->sum('debit'));
    }

    public function test_updating_a_payment_voucher_can_switch_it_from_one_bill_to_another(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Switch Supplier']);

        $billA = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-A',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 400, 'vat_total' => 0, 'total' => 400,
        ]);
        BillItem::create(['bill_id' => $billA->id, 'description' => 'A items', 'quantity' => 1, 'unit_price' => 400, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 400]);

        $billB = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-B',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 600, 'vat_total' => 0, 'total' => 600,
        ]);
        BillItem::create(['bill_id' => $billB->id, 'description' => 'B items', 'quantity' => 1, 'unit_price' => 600, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 600]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'supplier', 'supplier_id' => $supplier->id, 'bill_id' => $billA->id,
            'payee_name' => $supplier->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 400, 'method' => 'bank_transfer',
        ]);
        $voucher = PaymentVoucher::first();
        $billA->refresh();
        $this->assertSame(400.0, (float) $billA->amount_paid);

        $this->actingAs($owner)->put(route('app.payment-vouchers.update', $voucher), [
            'party_type' => 'supplier', 'supplier_id' => $supplier->id, 'bill_id' => $billB->id,
            'payee_name' => $supplier->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 600, 'method' => 'bank_transfer',
        ])->assertRedirect(route('app.payment-vouchers.show', $voucher));

        $billA->refresh();
        $billB->refresh();
        $voucher->refresh();

        $this->assertSame(0.0, (float) $billA->amount_paid);
        $this->assertSame(600.0, (float) $billB->amount_paid);
        $this->assertSame($billB->id, $voucher->bill_id);
        $this->assertSame(1, JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->count());
    }

    public function test_updating_a_receipt_voucher_can_switch_it_from_one_invoice_to_another(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Switch Client']);

        $invoiceA = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-A',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 300, 'vat_total' => 0, 'total' => 300,
        ]);
        InvoiceItem::create(['invoice_id' => $invoiceA->id, 'description' => 'A items', 'quantity' => 1, 'unit_price' => 300, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 300]);

        $invoiceB = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-B',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 700, 'vat_total' => 0, 'total' => 700,
        ]);
        InvoiceItem::create(['invoice_id' => $invoiceB->id, 'description' => 'B items', 'quantity' => 1, 'unit_price' => 700, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 700]);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'invoice_id' => $invoiceA->id,
            'payer_name' => $client->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 300, 'method' => 'bank_transfer',
        ]);
        $voucher = ReceiptVoucher::first();
        $invoiceA->refresh();
        $this->assertSame('paid', $invoiceA->status);

        $this->actingAs($owner)->put(route('app.receipt-vouchers.update', $voucher), [
            'party_type' => 'customer', 'client_id' => $client->id, 'invoice_id' => $invoiceB->id,
            'payer_name' => $client->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 700, 'method' => 'bank_transfer',
        ])->assertRedirect(route('app.receipt-vouchers.show', $voucher));

        $invoiceA->refresh();
        $invoiceB->refresh();
        $voucher->refresh();

        $this->assertSame('sent', $invoiceA->status);
        $this->assertSame(0.0, (float) $invoiceA->amount_paid);
        $this->assertSame('paid', $invoiceB->status);
        $this->assertSame(700.0, (float) $invoiceB->amount_paid);
        $this->assertSame($invoiceB->id, $voucher->invoice_id);
    }

    public function test_a_voided_voucher_cannot_be_edited_or_updated(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Void Me', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 500, 'method' => 'cash',
        ]);
        $voucher = PaymentVoucher::first();
        $this->actingAs($owner)->post(route('app.payment-vouchers.void', $voucher));
        $voucher->refresh();
        $this->assertSame('void', $voucher->status);

        $this->actingAs($owner)->get(route('app.payment-vouchers.edit', $voucher))
            ->assertRedirect(route('app.payment-vouchers.show', $voucher));

        $this->actingAs($owner)->put(route('app.payment-vouchers.update', $voucher), [
            'party_type' => 'manual', 'payee_name' => 'Should Not Apply', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 999, 'method' => 'cash',
        ]);
        $voucher->refresh();
        $this->assertSame('Void Me', $voucher->payee_name);
    }

    public function test_destroy_is_refused_until_the_voucher_is_voided(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Keep Me', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 500, 'method' => 'cash',
        ]);
        $voucher = PaymentVoucher::first();

        $this->actingAs($owner)->delete(route('app.payment-vouchers.destroy', $voucher));

        $this->assertNotNull(PaymentVoucher::find($voucher->id));
    }

    public function test_destroying_a_voided_payment_voucher_removes_it_and_keeps_the_reversed_ledger_entries(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Delete Me', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 500, 'method' => 'cash',
        ]);
        $voucher = PaymentVoucher::first();
        $voucherId = $voucher->id;
        $this->actingAs($owner)->post(route('app.payment-vouchers.void', $voucher));

        $this->actingAs($owner)->delete(route('app.payment-vouchers.destroy', $voucher))
            ->assertRedirect(route('app.payment-vouchers.index'));

        $this->assertNull(PaymentVoucher::find($voucherId));
        $this->assertSame(1, JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucherId)->count());
        $this->assertSame(1, JournalEntry::where('source_type', 'payment_voucher_reversal')->where('source_id', $voucherId)->count());
    }

    public function test_destroying_a_voided_receipt_voucher_removes_it(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'manual', 'payer_name' => 'Delete Me Too', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 200, 'method' => 'cash',
        ]);
        $voucher = ReceiptVoucher::first();
        $voucherId = $voucher->id;
        $this->actingAs($owner)->post(route('app.receipt-vouchers.void', $voucher));

        $this->actingAs($owner)->delete(route('app.receipt-vouchers.destroy', $voucher))
            ->assertRedirect(route('app.receipt-vouchers.index'));

        $this->assertNull(ReceiptVoucher::find($voucherId));
    }
}
