<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i have record payment 50005 to inv2 and create new voucher for
 * 107000 and in select invoice 2 is not available it means once the
 * invoice linked to voucher it's not available to another voucher" —
 * ClientController::outstandingInvoices()/SupplierController::
 * outstandingBills() only ever returned invoices/bills still in an
 * unpaid status, so the moment one Receipt/Payment Voucher's payment
 * pushed one to 'paid' (balanceDue() <= 0), it vanished from every
 * future voucher's "select invoice/bill" picker — blocking the exact
 * "several separate real transfers settle one invoice" workflow the
 * link exists for, and any later correction.
 */
class VoucherPickerIncludesSettledDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_fully_paid_invoice_still_appears_in_the_voucher_invoice_picker(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00002',
            'status' => 'paid', 'issue_date' => now(), 'currency' => 'SAR',
            'subtotal' => 200000, 'vat_total' => 30000, 'total' => 230000, 'amount_paid' => 230000,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Asphalt works', 'quantity' => 1, 'unit_price' => 200000, 'vat_rate' => 15, 'vat_amount' => 30000, 'line_total' => 230000]);

        $response = $this->actingAs($owner)->get("/app/clients/{$client->id}/outstanding-invoices");

        $response->assertOk();
        $response->assertJsonFragment(['invoice_number' => 'INV-00002', 'balance' => '0.00']);
    }

    public function test_an_invoice_stays_selectable_for_a_second_voucher_after_the_first_voucher_completes_it(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);
        $account = \App\Models\BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);
        \App\Models\Account::seedSystemAccounts($company->id);
        \App\Models\AccountMapping::seedDefaults($company->id);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00002',
            'status' => 'sent', 'issue_date' => now(), 'currency' => 'SAR',
            'subtotal' => 200000, 'vat_total' => 30000, 'total' => 230000,
        ]);

        // First voucher (157,000) partially settles it.
        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'invoice_id' => $invoice->id,
            'bank_account_id' => $account->id, 'date' => now()->toDateString(), 'payer_name' => $client->name,
            'amount' => 157000, 'method' => 'bank_transfer',
        ])->assertRedirect();

        $this->assertSame('partially_paid', $invoice->fresh()->status);

        // The invoice must still be in the picker — there's still a real balance due.
        $midway = $this->actingAs($owner)->get("/app/clients/{$client->id}/outstanding-invoices");
        $midway->assertJsonFragment(['invoice_number' => 'INV-00002']);

        // Second voucher (73,000) completes it — pushes status to 'paid'.
        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'invoice_id' => $invoice->id,
            'bank_account_id' => $account->id, 'date' => now()->toDateString(), 'payer_name' => $client->name,
            'amount' => 73000, 'method' => 'bank_transfer',
        ])->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(2, ReceiptVoucher::where('invoice_id', $invoice->id)->count());

        // Even now that it's fully paid, a third voucher (e.g. to correct
        // a misallocation) must still be able to select this invoice.
        $afterPaid = $this->actingAs($owner)->get("/app/clients/{$client->id}/outstanding-invoices");
        $afterPaid->assertOk();
        $afterPaid->assertJsonFragment(['invoice_number' => 'INV-00002', 'balance' => '0.00']);
    }

    public function test_an_unpaid_invoice_is_still_listed_before_a_fully_paid_one(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-PAID',
            'status' => 'paid', 'issue_date' => now(), 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'amount_paid' => 115,
        ]);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-OPEN',
            'status' => 'sent', 'issue_date' => now(), 'currency' => 'SAR',
            'subtotal' => 200, 'vat_total' => 30, 'total' => 230,
        ]);

        $response = $this->actingAs($owner)->get("/app/clients/{$client->id}/outstanding-invoices");

        $numbers = collect($response->json())->pluck('invoice_number')->values()->all();
        $this->assertSame(['INV-OPEN', 'INV-PAID'], $numbers);
    }

    public function test_a_fully_paid_bill_still_appears_in_the_payment_voucher_bill_picker(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Subcontractor Co']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-9001',
            'status' => 'posted', 'bill_date' => now(), 'currency' => 'SAR',
            'subtotal' => 300, 'vat_total' => 0, 'total' => 300, 'amount_paid' => 300,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'description' => 'Rebar', 'quantity' => 1, 'unit_price' => 300, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 300]);

        $response = $this->actingAs($owner)->get("/app/suppliers/{$supplier->id}/outstanding-bills");

        $response->assertOk();
        $response->assertJsonFragment(['bill_number' => 'BILL-9001', 'balance' => '0.00']);
    }
}
