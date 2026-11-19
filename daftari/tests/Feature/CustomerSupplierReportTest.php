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
 * The "Customer Balance Summary" / "Vendor Balance Summary" every
 * modern accounting system offers alongside the single-party Account
 * Statement and the bucketed Aging report — one row per party, this
 * period's activity plus a live outstanding balance, with a drill-down
 * link into the detailed statement.
 */
class CustomerSupplierReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Party Report Co.', 'slug' => 'party-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_customer_report_aggregates_invoiced_received_and_outstanding_per_client(): void
    {
        [$company, $owner] = $this->makeOwner();
        $clientA = Client::create(['company_id' => $company->id, 'name' => 'Acme Trading', 'vat_number' => '300000000000003']);
        $clientB = Client::create(['company_id' => $company->id, 'name' => 'Zed Supplies']);

        $invoiceA1 = Invoice::create([
            'company_id' => $company->id, 'client_id' => $clientA->id, 'invoice_number' => 'INV-A1',
            'issue_date' => now()->startOfMonth(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'amount_paid' => 500,
        ]);
        InvoicePayment::create(['invoice_id' => $invoiceA1->id, 'amount' => 500, 'paid_at' => now()->startOfMonth()->addDays(2)]);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $clientB->id, 'invoice_number' => 'INV-B1',
            'issue_date' => now()->startOfMonth(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 200, 'vat_total' => 30, 'total' => 230, 'amount_paid' => 230,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.customers'));

        $response->assertOk();
        $response->assertSee('Acme Trading');
        $response->assertSee('Zed Supplies');
        $response->assertSee(\App\Support\Money::format(1150));
        $response->assertSee(\App\Support\Money::format(500));
        // Acme: 1150 total - 500 received = 650 still outstanding.
        $response->assertSee(\App\Support\Money::format(650));
    }

    public function test_customer_report_links_to_the_per_customer_account_statement(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Linked Client']);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-L1',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.customers'));

        $response->assertOk();
        // Blade's {{ }} HTML-escapes the href (& becomes &amp;), so
        // checking the two query pieces separately avoids a false
        // negative from comparing against the raw, unescaped route().
        $response->assertSee('client_id='.$client->id, false);
        $response->assertSee('type=customer', false);
    }

    public function test_customer_report_search_filters_by_name(): void
    {
        [$company, $owner] = $this->makeOwner();
        $a = Client::create(['company_id' => $company->id, 'name' => 'Findme Co']);
        $b = Client::create(['company_id' => $company->id, 'name' => 'Other Co']);
        foreach ([$a, $b] as $i => $client) {
            Invoice::create([
                'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-S'.$i,
                'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR',
                'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
            ]);
        }

        $response = $this->actingAs($owner)->get(route('app.reports.customers', ['search' => 'findme']));

        $response->assertOk();
        $response->assertSee('Findme Co');
        $response->assertDontSee('Other Co');
    }

    public function test_customer_report_exports_csv_and_pdf(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Export Co']);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-E1',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $csv = $this->actingAs($owner)->get(route('app.reports.customers', ['export' => 'csv']));
        $csv->assertOk();
        $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $pdf = $this->actingAs($owner)->get(route('app.reports.customers', ['export' => 'pdf']));
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_supplier_report_aggregates_billed_paid_and_outstanding_per_supplier(): void
    {
        [$company, $owner] = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Steel Supplier Co.']);

        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR',
            'subtotal' => 2000, 'vat_total' => 300, 'total' => 2300, 'amount_paid' => 800,
        ]);
        BillPayment::create(['bill_id' => $bill->id, 'amount' => 800, 'paid_at' => now()]);

        $response = $this->actingAs($owner)->get(route('app.reports.suppliers'));

        $response->assertOk();
        $response->assertSee('Steel Supplier Co.');
        $response->assertSee(\App\Support\Money::format(2300));
        $response->assertSee(\App\Support\Money::format(800));
        $response->assertSee(\App\Support\Money::format(1500));
    }

    public function test_supplier_report_exports_pdf(): void
    {
        [$company, $owner] = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'PDF Supplier']);
        Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-P1',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.suppliers', ['export' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
