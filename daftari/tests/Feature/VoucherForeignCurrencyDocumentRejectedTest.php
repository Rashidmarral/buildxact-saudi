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
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding GL-05: PaymentVoucher/ReceiptVoucher have no currency of
 * their own — ->amount is always a base-currency figure, matching exactly
 * what postPaymentVoucher()/postReceiptVoucher() post to the GL with no
 * conversion. settleBillAndPost()/settleInvoiceAndPost() increment the
 * linked bill/invoice's amount_paid by that same base-currency number
 * directly — correct only when the bill/invoice is ALSO in the base
 * currency. A foreign-currency document's amount_paid (compared against
 * its own foreign-currency total) would otherwise be silently polluted
 * with a number in the wrong currency entirely.
 */
class VoucherForeignCurrencyDocumentRejectedTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(array $companyOverrides = []): User
    {
        $company = Company::create(array_merge(['name' => 'FX Co.', 'slug' => 'fx-'.uniqid(), 'currency' => 'SAR'], $companyOverrides));
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeBankAccount(User $owner): BankAccount
    {
        return BankAccount::create(['company_id' => $owner->company_id, 'name' => 'Main Account', 'bank_name' => 'Al Rajhi Bank', 'type' => 'bank', 'is_active' => true]);
    }

    public function test_a_payment_voucher_cannot_settle_a_foreign_currency_bill(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeBankAccount($owner);
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'US Supplier']);
        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'description' => 'Items', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 1000]);

        $response = $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'supplier', 'supplier_id' => $supplier->id, 'bill_id' => $bill->id,
            'payee_name' => $supplier->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 3750, 'method' => 'bank_transfer',
        ]);

        $response->assertSessionHasErrors('bill_id');
        $this->assertSame(0, PaymentVoucher::count());
        $this->assertSame(0.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_a_payment_voucher_against_a_base_currency_bill_still_works(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeBankAccount($owner);
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Local Supplier']);
        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'description' => 'Items', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 1000]);

        $response = $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'supplier', 'supplier_id' => $supplier->id, 'bill_id' => $bill->id,
            'payee_name' => $supplier->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 1000, 'method' => 'bank_transfer',
        ]);

        $response->assertSessionDoesntHaveErrors('bill_id');
        $this->assertSame(1, PaymentVoucher::count());
        $this->assertSame(1000.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_a_receipt_voucher_cannot_settle_a_foreign_currency_invoice(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeBankAccount($owner);
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'US Client']);
        $invoice = Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => 'USD', 'exchange_rate' => 3.75, 'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Items', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 1000]);

        $response = $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'invoice_id' => $invoice->id,
            'payer_name' => $client->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 3750, 'method' => 'bank_transfer',
        ]);

        $response->assertSessionHasErrors('invoice_id');
        $this->assertSame(0, ReceiptVoucher::count());
        $this->assertSame(0.0, (float) $invoice->fresh()->amount_paid);
    }

    public function test_a_receipt_voucher_against_a_base_currency_invoice_still_works(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeBankAccount($owner);
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Local Client']);
        $invoice = Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Items', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 1000]);

        $response = $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'invoice_id' => $invoice->id,
            'payer_name' => $client->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 1000, 'method' => 'bank_transfer',
        ]);

        $response->assertSessionDoesntHaveErrors('invoice_id');
        $this->assertSame(1, ReceiptVoucher::count());
        $this->assertSame(1000.0, (float) $invoice->fresh()->amount_paid);
    }
}
