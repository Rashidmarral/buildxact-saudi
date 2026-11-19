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
 * "add more reports... Receivables/Payables by contact" — per
 * customer/supplier opening, movement and closing balance for the
 * period, distinct from the Aged Receivables/Payables report (which
 * only ever shows today's outstanding balance bucketed by overdue
 * days, never a period's own opening/movement/closing).
 */
class ReceivablesPayablesByContactReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Contact Balances Co.', 'slug' => 'contact-bal-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_receivables_shows_opening_movement_and_closing_per_customer(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);

        // An invoice issued BEFORE the period — becomes the opening balance.
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-OLD',
            'issue_date' => now()->startOfMonth()->subMonth()->toDateString(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 10000, 'vat_total' => 0, 'total' => 10000, 'amount_paid' => 0,
        ]);
        // An invoice issued INSIDE the period — becomes this period's movement (debit).
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-NEW',
            'issue_date' => now()->startOfMonth()->toDateString(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 4000, 'vat_total' => 0, 'total' => 4000, 'amount_paid' => 0,
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.receivables-payables-by-contact', [
            'period' => 'custom', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString(),
        ]));
        $page->assertOk()
            ->assertSee('Al Jeul Construction')
            ->assertSee('10,000.00') // opening
            ->assertSee('4,000.00') // movement (debit)
            ->assertSee('14,000.00'); // closing

        $periodParams = ['period' => 'custom', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()];

        $csv = $this->actingAs($owner)->get(route('app.reports.receivables-payables-by-contact', $periodParams + ['export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('10000.00', $csvBody);
        $this->assertStringContainsString('14000.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.reports.receivables-payables-by-contact', $periodParams + ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_payables_shows_a_supplier_with_a_bill_in_the_period(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now()->toDateString(), 'status' => 'posted', 'currency' => 'SAR',
            'subtotal' => 3000, 'vat_total' => 0, 'total' => 3000,
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.receivables-payables-by-contact', ['type' => 'payables']));
        $page->assertOk()->assertSee('SAED EST')->assertSee('3,000.00');
    }

    public function test_a_fully_paid_client_with_no_period_activity_is_excluded(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Quiet Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-QUIET',
            'issue_date' => now()->subMonthsNoOverflow(3)->toDateString(), 'status' => 'paid', 'currency' => 'SAR',
            'subtotal' => 500, 'vat_total' => 0, 'total' => 500, 'amount_paid' => 500,
        ]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 500, 'paid_at' => now()->subMonthsNoOverflow(3)->toDateString(), 'method' => 'cash']);

        $page = $this->actingAs($owner)->get(route('app.reports.receivables-payables-by-contact', [
            'period' => 'custom', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString(),
        ]));
        $page->assertOk()->assertDontSee('Quiet Client');
    }
}
