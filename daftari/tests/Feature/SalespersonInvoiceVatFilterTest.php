<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Salesperson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "this company has 2 main users like Ashiq and Rizwan, same company, 2
 * different business, each generate invoice for their own use — how can
 * i tackle at the end who have what invoices and vat details" — one
 * company, one CR/VAT registration, so only one combined VAT return is
 * ever filed; the Salesperson field (already a working dropdown on every
 * invoice) is the tag that splits "who has what" for internal tracking.
 * Adds a filter + subtotal for it on the Invoices list and the VAT
 * report, which previously had no way to filter by it at all.
 */
class SalespersonInvoiceVatFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Zubaida', 'slug' => 'zubaida-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(Company $company, ?Salesperson $salesperson, float $net, float $vat, string $date): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client '.uniqid()]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'issue_date' => $date, 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
            'salesperson_id' => $salesperson?->id,
        ]);
        $invoice->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => $net, 'vat_rate' => 15, 'vat_amount' => $vat, 'line_total' => $net + $vat]);
        $invoice->recalculateTotals();
        $invoice->save();

        return $invoice;
    }

    public function test_filtering_invoices_by_salesperson_shows_only_their_invoices_and_the_right_totals(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $ashiq = Salesperson::create(['company_id' => $company->id, 'name' => 'Ashiq', 'is_active' => true]);
        $rizwan = Salesperson::create(['company_id' => $company->id, 'name' => 'Rizwan', 'is_active' => true]);

        $this->makeInvoice($company, $ashiq, 1000, 150, now()->toDateString());
        $this->makeInvoice($company, $ashiq, 2000, 300, now()->toDateString());
        $rizwanInvoice = $this->makeInvoice($company, $rizwan, 5000, 750, now()->toDateString());

        $response = $this->actingAs($owner)->get(route('app.invoices.index', ['salesperson_id' => $ashiq->id]));

        $response->assertOk();
        // The salesperson dropdown itself always lists both names, so the
        // real proof the filter worked is that Rizwan's own invoice row
        // is gone, not the mere absence of his name anywhere on the page.
        $response->assertDontSee($rizwanInvoice->invoice_number);
        // 2 invoices, total 1150+2300=3450, VAT 150+300=450.
        $response->assertSee(\App\Support\Money::format(3450));
        $response->assertSee(\App\Support\Money::format(450));
    }

    public function test_the_vat_report_filtered_by_one_salesperson_shows_only_their_output_tax(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $ashiq = Salesperson::create(['company_id' => $company->id, 'name' => 'Ashiq', 'is_active' => true]);
        $rizwan = Salesperson::create(['company_id' => $company->id, 'name' => 'Rizwan', 'is_active' => true]);

        $this->makeInvoice($company, $ashiq, 1000, 150, now()->toDateString());
        $this->makeInvoice($company, $rizwan, 5000, 750, now()->toDateString());

        $response = $this->actingAs($owner)->get(route('app.reports.vat', [
            'period' => 'custom', 'from' => now()->subMonth()->toDateString(), 'to' => now()->addMonth()->toDateString(),
            'salesperson_id' => $ashiq->id, 'tab' => 'sales',
        ]));

        $response->assertOk();
        // Ashiq's own 150 output tax shows, not the combined 900.
        $response->assertSee(\App\Support\Money::format(150));
        $response->assertDontSee(\App\Support\Money::format(900));
        $response->assertSee(__('Showing output tax for this salesperson only. Purchases and expenses below stay company-wide. The figure actually filed with ZATCA is the combined output tax across every salesperson — clear this filter to see it.'));
    }

    public function test_the_vat_report_with_no_salesperson_filter_shows_the_combined_output_tax(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $ashiq = Salesperson::create(['company_id' => $company->id, 'name' => 'Ashiq', 'is_active' => true]);
        $rizwan = Salesperson::create(['company_id' => $company->id, 'name' => 'Rizwan', 'is_active' => true]);

        $this->makeInvoice($company, $ashiq, 1000, 150, now()->toDateString());
        $this->makeInvoice($company, $rizwan, 5000, 750, now()->toDateString());

        $response = $this->actingAs($owner)->get(route('app.reports.vat', [
            'period' => 'custom', 'from' => now()->subMonth()->toDateString(), 'to' => now()->addMonth()->toDateString(),
            'tab' => 'sales',
        ]));

        $response->assertOk();
        // The combined 150 + 750 = 900 is the one real figure to file.
        $response->assertSee(\App\Support\Money::format(900));
    }
}
