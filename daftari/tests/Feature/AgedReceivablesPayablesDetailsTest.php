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
 * "Account Receivable/Payable Ageing Details" — the existing Aged
 * Receivables/Payables report bucket-totals each party; this drills
 * into the individual overdue invoices/bills behind those totals, one
 * row per document, matching Hamly's own "(Details)" report pair.
 */
class AgedReceivablesPayablesDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Aging Details Co.', 'slug' => 'aging-details-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_receivables_details_lists_individual_overdue_invoices(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0099',
            'issue_date' => now()->subDays(40)->toDateString(), 'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 5000, 'vat_total' => 750, 'total' => 5750, 'amount_paid' => 0,
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.aging-details'));
        $page->assertOk()->assertSee('INV-0099')->assertSee('Al Jeul Construction');

        $csv = $this->actingAs($owner)->get(route('app.reports.aging-details', ['export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('INV-0099', $csvBody);
        $this->assertStringContainsString('5750.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.reports.aging-details', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_payables_details_lists_individual_overdue_bills(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0077',
            'bill_date' => now()->subDays(35)->toDateString(), 'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 2000, 'vat_total' => 0, 'total' => 2000,
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.aging-details', ['type' => 'payables']));
        $page->assertOk()->assertSee('BILL-0077')->assertSee('SAED EST');

        $csv = $this->actingAs($owner)->get(route('app.reports.aging-details', ['type' => 'payables', 'export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('BILL-0077', str_replace("\xEF\xBB\xBF", '', $csv->streamedContent()));
    }

    public function test_a_fully_paid_invoice_does_not_appear(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Paid-up Client']);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-PAID',
            'issue_date' => now()->subDays(40)->toDateString(), 'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'paid', 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000, 'amount_paid' => 1000,
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.aging-details'));
        $page->assertOk()->assertDontSee('INV-PAID');
    }
}
