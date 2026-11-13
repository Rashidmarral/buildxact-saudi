<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Hamly has a combined 'Purchase & Sale' summary report. Everything in
 * it is already derivable from your existing reports ... just not
 * combined on one page." Covers that only finalized documents (issued
 * invoices, posted bills — never drafts) count, and that CSV export
 * matches what the page shows.
 */
class PurchaseSaleSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_report_totals_only_finalized_invoices_and_bills(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 1000,
        ]);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0002', 'status' => 'draft',
            'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 5000,
        ]);
        Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0001', 'status' => 'posted',
            'bill_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 400,
        ]);
        Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0002', 'status' => 'draft',
            'bill_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 9000,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.purchase-sale-summary'));

        $response->assertOk();
        $response->assertSee(\App\Support\Money::format(1000), false);
        $response->assertSee(\App\Support\Money::format(400), false);
        $response->assertDontSee(\App\Support\Money::format(5000), false);
        $response->assertDontSee(\App\Support\Money::format(9000), false);
    }

    public function test_csv_export_matches_the_page_totals(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 1000,
        ]);
        Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0001', 'status' => 'posted',
            'bill_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 400,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.purchase-sale-summary', ['export' => 'csv']));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('1000.00', $csv);
        $this->assertStringContainsString('400.00', $csv);
    }

    public function test_pdf_export_downloads(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 1000,
        ]);
        Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0001', 'status' => 'posted',
            'bill_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 400,
        ]);

        $pdf = $this->actingAs($owner)->get(route('app.reports.purchase-sale-summary', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
