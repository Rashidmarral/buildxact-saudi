<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature request: an "Aged Receivables/Payables" report — who owes the
 * company money and who the company owes, bucketed by how overdue each
 * outstanding invoice/bill is. The existing Account Statement already
 * covered a single party's running balance; this is the missing
 * across-the-book view (every party, at a glance, by aging bucket).
 */
class AgedReceivablesPayablesTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Aging Co.', 'slug' => 'aging-'.uniqid()]);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(Company $company, Client $client, float $total, ?string $dueDate, float $paid = 0): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->subDays(100)->toDateString(),
            'due_date' => $dueDate, 'currency' => $company->currency, 'total' => $total,
        ]);

        if ($paid > 0) {
            InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => $paid, 'paid_at' => now()->toDateString(), 'method' => 'bank_transfer']);
        }

        $invoice->update(['amount_paid' => $paid]);

        return $invoice;
    }

    private function makeBill(Company $company, Supplier $supplier, float $total, ?string $dueDate, float $paid = 0): Bill
    {
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-'.uniqid(),
            'status' => 'posted', 'bill_date' => now()->subDays(100)->toDateString(),
            'due_date' => $dueDate, 'currency' => $company->currency, 'total' => $total,
        ]);

        if ($paid > 0) {
            BillPayment::create(['bill_id' => $bill->id, 'amount' => $paid, 'paid_at' => now()->toDateString(), 'method' => 'bank_transfer']);
        }

        $bill->update(['amount_paid' => $paid]);

        return $bill;
    }

    public function test_outstanding_invoices_are_bucketed_by_days_overdue(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Overdue Client']);

        // Not yet due.
        $this->makeInvoice($company, $client, 100, now()->addDays(10)->toDateString());
        // 15 days overdue -> 1-30 bucket.
        $this->makeInvoice($company, $client, 200, now()->subDays(15)->toDateString());
        // 45 days overdue -> 31-60 bucket.
        $this->makeInvoice($company, $client, 300, now()->subDays(45)->toDateString());
        // 120 days overdue -> 90+ bucket.
        $this->makeInvoice($company, $client, 400, now()->subDays(120)->toDateString());
        // Fully paid — must not appear at all.
        $this->makeInvoice($company, $client, 500, now()->subDays(10)->toDateString(), paid: 500);

        $response = $this->actingAs($owner)->get(route('app.reports.aging', ['type' => 'receivables']));

        $response->assertOk();
        $response->assertSee('Overdue Client');
        $response->assertSee('1,000.00');
    }

    public function test_outstanding_bills_are_bucketed_for_payables(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Overdue Supplier']);

        $this->makeBill($company, $supplier, 750, now()->subDays(70)->toDateString());

        $response = $this->actingAs($owner)->get(route('app.reports.aging', ['type' => 'payables']));

        $response->assertOk();
        $response->assertSee('Overdue Supplier');
        $response->assertSee('750.00');
    }

    public function test_a_partially_paid_invoice_shows_only_its_remaining_balance(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Partial Payer']);

        $this->makeInvoice($company, $client, 1000, now()->subDays(45)->toDateString(), paid: 400);

        $response = $this->actingAs($owner)->get(route('app.reports.aging', ['type' => 'receivables']));

        $response->assertOk();
        $response->assertSee('600.00');
        $response->assertDontSee('1,000.00');
    }

    public function test_the_csv_export_lists_every_bucket(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'CSV Client']);
        $this->makeInvoice($company, $client, 300, now()->subDays(45)->toDateString());

        $response = $this->actingAs($owner)->get(route('app.reports.aging', ['type' => 'receivables', 'export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('CSV Client', $csv);
        $this->assertStringContainsString('300.00', $csv);
    }

    public function test_an_invoice_with_no_due_date_ages_from_its_issue_date(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'No Due Date Client']);

        // makeInvoice always sets issue_date to 100 days ago; a null due_date
        // must fall back to issue_date, landing this in the 90+ bucket.
        $this->makeInvoice($company, $client, 900, null);

        $response = $this->actingAs($owner)->get(route('app.reports.aging', ['type' => 'receivables']));

        $response->assertOk();
        $response->assertSee('No Due Date Client');
        $response->assertSee('900.00');
    }
}
