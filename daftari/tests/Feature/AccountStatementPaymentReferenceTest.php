<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "I need to update account statement to show transaction for same
 * date reference of transaction" — every payment line on Reports >
 * Account Statement used to read as a bare "Payment received"/"Payment
 * made", with no way to tell which invoice/bill it settled or which
 * real bank transfer/cheque it corresponds to — especially painful once
 * several partial payments land on the same date, which is exactly
 * when a plain "Payment received" repeated three times in a row stops
 * being traceable back to reality. The settled document's number and
 * the payer's own reference (whichever actually exist for a given
 * payment) are now folded into the description every output (web, PDF,
 * CSV) already renders.
 *
 * "i think we don't need receipt voucher reference" — this originally
 * also appended the internal RV-xxxxx/PV-xxxxx voucher number, which
 * meant nothing to the client reading their own copy (it's not on
 * their bank statement); only the real bank/cheque reference remains.
 */
class AccountStatementPaymentReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Statement Co.', 'slug' => 'statement-'.uniqid()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_customer_payment_with_a_reference_shows_the_invoice_number_and_reference(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client']);
        $invoice = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 105007.08, 'paid_at' => now(), 'reference' => 'BT-4521']);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id]));

        $response->assertOk();
        $response->assertSee('Payment received — INV-00001 (Ref: BT-4521)');
    }

    public function test_a_customer_payment_recorded_through_a_receipt_voucher_does_not_show_the_internal_voucher_number(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client']);
        $invoice = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-00002', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        $account = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'Main Account', 'type' => 'bank', 'is_active' => true]);
        $payment = InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 50000, 'paid_at' => now(), 'reference' => 'BT-7788']);
        ReceiptVoucher::create([
            'company_id' => $owner->company_id, 'invoice_id' => $invoice->id, 'invoice_payment_id' => $payment->id,
            'bank_account_id' => $account->id, 'voucher_number' => 'REC-00001', 'date' => now(),
            'payer_name' => 'Client', 'amount' => 50000, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id]));

        $response->assertOk();
        $response->assertSee('Payment received — INV-00002 (Ref: BT-7788)');
        $response->assertDontSee('REC-00001');
    }

    public function test_a_payment_with_no_reference_or_voucher_falls_back_to_just_the_invoice_number(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client']);
        $invoice = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-00003', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 100, 'paid_at' => now()]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id]));

        $response->assertOk();
        $response->assertSee('Payment received — INV-00003');
    }

    public function test_two_same_day_payments_against_the_same_invoice_are_distinguishable_by_reference(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client']);
        $invoice = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-00004', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 200, 'vat_total' => 30, 'total' => 230]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 157000, 'paid_at' => now(), 'reference' => 'CHQ-9001']);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 73000, 'paid_at' => now(), 'reference' => 'CHQ-9002']);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id]));

        $response->assertOk();
        $response->assertSee('Ref: CHQ-9001');
        $response->assertSee('Ref: CHQ-9002');
    }

    public function test_a_supplier_payment_shows_the_bill_number_and_reference(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Supplier']);
        $bill = Bill::create(['company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-00001', 'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        BillPayment::create(['bill_id' => $bill->id, 'amount' => 115, 'paid_at' => now(), 'reference' => 'TRF-100']);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'supplier', 'supplier_id' => $supplier->id]));

        $response->assertOk();
        $response->assertSee('Payment made — BILL-00001 (Ref: TRF-100)');
    }

    public function test_a_supplier_payment_recorded_through_a_payment_voucher_does_not_show_the_internal_voucher_number(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Supplier']);
        $bill = Bill::create(['company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-00002', 'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        $account = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'Main Account', 'type' => 'bank', 'is_active' => true]);
        $payment = BillPayment::create(['bill_id' => $bill->id, 'amount' => 115, 'paid_at' => now(), 'reference' => 'BT-9900']);
        PaymentVoucher::create([
            'company_id' => $owner->company_id, 'bill_id' => $bill->id, 'bill_payment_id' => $payment->id,
            'bank_account_id' => $account->id, 'voucher_number' => 'PAY-00001', 'date' => now(),
            'payee_name' => 'Supplier', 'amount' => 115, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'supplier', 'supplier_id' => $supplier->id]));

        $response->assertOk();
        $response->assertSee('Payment made — BILL-00002 (Ref: BT-9900)');
        $response->assertDontSee('PAY-00001');
    }
}
