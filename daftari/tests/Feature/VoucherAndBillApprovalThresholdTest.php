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
 * Audit finding CASH-06: Payment/Receipt Vouchers and Bills — the actual
 * movement of cash — had no approval gate at all, unlike Expenses/POs/
 * Invoices/Quotations/Manual Journal Entries. Mirrors that exact pattern:
 * null threshold (the default) changes nothing; once set, an item at/
 * above it is staged instead of posted — no bill/invoice payment
 * recorded, no GL entry — until explicitly approved.
 */
class VoucherAndBillApprovalThresholdTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        $company = Company::create(array_merge(['name' => 'Voucher Approval Co.', 'slug' => 'voucher-approval-'.uniqid()], $overrides));
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
        return BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'bank_name' => 'Al Rajhi Bank', 'type' => 'bank', 'is_active' => true]);
    }

    // --- Payment Voucher ---

    public function test_a_null_threshold_leaves_payment_voucher_posting_unchanged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Unthrottled Payee', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 500000, 'method' => 'cash',
        ])->assertRedirect();

        $voucher = PaymentVoucher::first();
        $this->assertSame('issued', $voucher->status);
        $this->assertSame(1, JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->count());
    }

    public function test_a_payment_voucher_at_or_above_the_threshold_is_staged_with_no_bill_payment_or_posting(): void
    {
        $company = $this->makeCompany(['payment_voucher_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Big Supplier']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 15000, 'vat_total' => 0, 'total' => 15000,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'description' => 'Items', 'quantity' => 1, 'unit_price' => 15000, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 15000]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'supplier', 'supplier_id' => $supplier->id, 'bill_id' => $bill->id,
            'payee_name' => $supplier->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 15000, 'method' => 'bank_transfer',
        ])->assertRedirect();

        $voucher = PaymentVoucher::first();
        $this->assertSame('pending_approval', $voucher->status);
        $this->assertNull($voucher->bill_payment_id);
        $this->assertSame(0, JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->count());
        $this->assertSame(0.0, (float) $bill->fresh()->amount_paid, 'A pending voucher must not touch the bill at all.');
    }

    public function test_approving_a_pending_payment_voucher_records_the_bill_payment_and_posts(): void
    {
        $company = $this->makeCompany(['payment_voucher_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Big Supplier']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 15000, 'vat_total' => 0, 'total' => 15000,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'description' => 'Items', 'quantity' => 1, 'unit_price' => 15000, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 15000]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'supplier', 'supplier_id' => $supplier->id, 'bill_id' => $bill->id,
            'payee_name' => $supplier->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 15000, 'method' => 'bank_transfer',
        ]);
        $voucher = PaymentVoucher::first();

        $this->actingAs($owner)->post(route('app.payment-vouchers.approve', $voucher))->assertRedirect();

        $voucher->refresh();
        $this->assertSame('issued', $voucher->status);
        $this->assertNotNull($voucher->bill_payment_id);
        $this->assertSame($owner->id, $voucher->approved_by);
        $this->assertNotNull($voucher->approved_at);
        $this->assertSame(15000.0, (float) $bill->fresh()->amount_paid);
        $this->assertSame(1, JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->count());
    }

    public function test_rejecting_a_pending_payment_voucher_never_touches_the_bill_or_ledger(): void
    {
        $company = $this->makeCompany(['payment_voucher_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Rejected Payee', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 20000, 'method' => 'cash',
        ]);
        $voucher = PaymentVoucher::first();

        $this->actingAs($owner)->post(route('app.payment-vouchers.reject', $voucher), ['rejection_reason' => 'Wrong amount'])->assertRedirect();

        $voucher->refresh();
        $this->assertSame('rejected', $voucher->status);
        $this->assertSame('Wrong amount', $voucher->rejection_reason);
        $this->assertSame(0, JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->count());
    }

    public function test_a_pending_payment_voucher_cannot_be_edited(): void
    {
        $company = $this->makeCompany(['payment_voucher_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Locked Payee', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 20000, 'method' => 'cash',
        ]);
        $voucher = PaymentVoucher::first();

        $this->actingAs($owner)->get(route('app.payment-vouchers.edit', $voucher))
            ->assertRedirect(route('app.payment-vouchers.show', $voucher));
    }

    // --- Receipt Voucher ---

    public function test_a_receipt_voucher_at_or_above_the_threshold_is_staged_and_approving_it_marks_the_invoice_paid(): void
    {
        $company = $this->makeCompany(['receipt_voucher_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Big Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 12000, 'vat_total' => 0, 'total' => 12000,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Items', 'quantity' => 1, 'unit_price' => 12000, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 12000]);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'invoice_id' => $invoice->id,
            'payer_name' => $client->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 12000, 'method' => 'bank_transfer',
        ])->assertRedirect();

        $voucher = ReceiptVoucher::first();
        $this->assertSame('pending_approval', $voucher->status);
        $this->assertSame('sent', $invoice->fresh()->status, 'A pending receipt must not touch the invoice at all.');

        $this->actingAs($owner)->post(route('app.receipt-vouchers.approve', $voucher))->assertRedirect();

        $voucher->refresh();
        $this->assertSame('issued', $voucher->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, JournalEntry::where('source_type', 'receipt_voucher')->where('source_id', $voucher->id)->count());
    }

    // --- Bills ---

    public function test_a_null_threshold_leaves_bill_posting_unchanged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);

        $this->actingAs($owner)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id, 'bill_date' => now()->toDateString(), 'post_immediately' => '1',
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 500000, 'vat_rate' => 0]],
        ])->assertRedirect();

        $bill = Bill::first();
        $this->assertSame('posted', $bill->status);
    }

    public function test_a_bill_at_or_above_the_threshold_is_queued_for_approval_instead_of_posted(): void
    {
        $company = $this->makeCompany(['bill_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);

        $this->actingAs($owner)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id, 'bill_date' => now()->toDateString(), 'post_immediately' => '1',
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 15000, 'vat_rate' => 0]],
        ])->assertRedirect();

        $bill = Bill::first();
        $this->assertSame('pending_approval', $bill->status);
        $this->assertFalse((bool) $bill->stock_received);
    }

    public function test_approving_a_pending_bill_posts_it_and_the_post_button_also_routes_through_approval(): void
    {
        $company = $this->makeCompany(['bill_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);

        // Created as a draft first (no post_immediately), then posted via
        // the standalone button — must route through approval exactly
        // the same way the create-and-post path does.
        $this->actingAs($owner)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id, 'bill_date' => now()->toDateString(),
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 15000, 'vat_rate' => 0]],
        ]);
        $bill = Bill::first();
        $this->assertSame('draft', $bill->status);

        $this->actingAs($owner)->post(route('app.bills.post', $bill))->assertRedirect();
        $bill->refresh();
        $this->assertSame('pending_approval', $bill->status);

        $this->actingAs($owner)->post(route('app.bills.approve', $bill))->assertRedirect();

        $bill->refresh();
        $this->assertSame('posted', $bill->status);
        $this->assertSame($owner->id, $bill->approved_by);
        $this->assertSame(1, JournalEntry::where('source_type', 'bill')->where('source_id', $bill->id)->count());
    }

    public function test_rejecting_a_pending_bill_returns_it_to_draft(): void
    {
        $company = $this->makeCompany(['bill_approval_threshold' => 10000]);
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);

        $this->actingAs($owner)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id, 'bill_date' => now()->toDateString(), 'post_immediately' => '1',
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 15000, 'vat_rate' => 0]],
        ]);
        $bill = Bill::first();

        $this->actingAs($owner)->post(route('app.bills.reject', $bill), ['rejection_reason' => 'Needs review'])->assertRedirect();

        $bill->refresh();
        $this->assertSame('draft', $bill->status);
        $this->assertSame('Needs review', $bill->rejection_reason);
        $this->assertSame(0, JournalEntry::where('source_type', 'bill')->where('source_id', $bill->id)->count());
    }

    public function test_a_company_cannot_approve_another_companys_pending_bill(): void
    {
        $companyA = $this->makeCompany(['bill_approval_threshold' => 10000]);
        $ownerA = $this->makeOwner($companyA);
        $companyB = $this->makeCompany(['bill_approval_threshold' => 10000]);
        $supplierB = Supplier::create(['company_id' => $companyB->id, 'name' => 'Supplier B']);
        $billB = Bill::create([
            'company_id' => $companyB->id, 'supplier_id' => $supplierB->id, 'bill_number' => 'BILL-B',
            'status' => 'pending_approval', 'bill_date' => now()->toDateString(), 'currency' => $companyB->currency,
            'subtotal' => 15000, 'vat_total' => 0, 'total' => 15000,
        ]);

        $this->actingAs($ownerA)->post(route('app.bills.approve', $billB))->assertNotFound();
    }

    public function test_the_new_thresholds_can_be_saved_from_the_approvals_settings_page(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.settings.approvals.update'), [
            'po_approval_threshold' => '', 'expense_approval_threshold' => '',
            'invoice_approval_threshold' => '', 'quotation_approval_threshold' => '',
            'manual_journal_approval_threshold' => '5000',
            'payment_voucher_approval_threshold' => '8000',
            'receipt_voucher_approval_threshold' => '9000',
            'bill_approval_threshold' => '12000',
        ])->assertRedirect();

        $company->refresh();
        $this->assertEqualsWithDelta(5000, $company->manual_journal_approval_threshold, 0.01);
        $this->assertEqualsWithDelta(8000, $company->payment_voucher_approval_threshold, 0.01);
        $this->assertEqualsWithDelta(9000, $company->receipt_voucher_approval_threshold, 0.01);
        $this->assertEqualsWithDelta(12000, $company->bill_approval_threshold, 0.01);
    }
}
