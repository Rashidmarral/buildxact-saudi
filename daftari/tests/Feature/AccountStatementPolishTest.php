<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please make the pdf look more professional and well designed and
 * polish things that are required in customer or supplier account
 * statement" — the Account Statement gets its own dedicated design
 * (navy header band, summary cards, an aging summary, and an
 * "outstanding documents" open-item table above the full running-
 * balance ledger), matching what a mature client statement (Zoho
 * Books, QuickBooks, Xero) carries beyond a bare transaction list.
 */
class AccountStatementPolishTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_statement_shows_summary_cards_and_an_overdue_invoice_in_the_outstanding_table(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Sada Al Jeul Construction']);
        $invoice = Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-00003',
            'status' => 'sent', 'issue_date' => now()->subDays(45), 'due_date' => now()->subDays(15),
            'currency' => 'SAR', 'subtotal' => 200000, 'vat_total' => 30000, 'total' => 230000,
        ]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 157522.11, 'paid_at' => now()->subDays(20)]);
        $invoice->update(['amount_paid' => 157522.11]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id]));

        $response->assertOk();
        $response->assertSee(__('Total invoiced'));
        $response->assertSee(__('Balance due'));
        $response->assertSee(__('Outstanding invoices'));
        $response->assertSee('INV-00003');
        $response->assertSee(__('OVERDUE'));
        $response->assertSee('72,477.89');
    }

    public function test_the_pdf_includes_the_outstanding_table_and_aging_summary(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Sada Al Jeul Construction']);
        Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-00009',
            'status' => 'sent', 'issue_date' => now()->subDays(100), 'due_date' => now()->subDays(95),
            'currency' => 'SAR', 'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id, 'export' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_an_outstanding_bill_shows_on_the_supplier_statement_with_the_right_labels(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Subcontractor Co']);
        Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-00010',
            'status' => 'posted', 'bill_date' => now()->subDays(10), 'due_date' => now()->addDays(20),
            'currency' => 'SAR', 'subtotal' => 50000, 'vat_total' => 7500, 'total' => 57500,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'supplier', 'supplier_id' => $supplier->id]));

        $response->assertOk();
        $response->assertSee(__('Total billed'));
        $response->assertSee(__('Amount owed'));
        $response->assertSee(__('Outstanding bills'));
        $response->assertSee('BILL-00010');
        $response->assertDontSee(__('OVERDUE'));
    }

    public function test_a_fully_settled_party_shows_no_outstanding_table_or_aging_summary(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Sada Al Jeul Construction']);
        $invoice = Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-00020',
            'status' => 'sent', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 1150, 'paid_at' => now()]);
        $invoice->update(['amount_paid' => 1150, 'status' => 'paid']);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id]));

        $response->assertOk();
        $response->assertDontSee(__('Outstanding invoices'));
        $response->assertDontSee(__('Aging (as of today)'));
    }
}
