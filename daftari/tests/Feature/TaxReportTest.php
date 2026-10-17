<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The VAT/Tax report used to only look at Invoices and Expenses — Bills
 * (Purchases) were silently excluded from input VAT entirely. This is now
 * a real three-way split (Output/Input/Expense tax) with a transaction
 * table per tab; these tests exercise the real HTTP route end to end and
 * check the totals reconcile with what was actually created.
 */
class TaxReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithAccess(): Company
    {
        $company = Company::create(['name' => 'Tax Report Co.', 'slug' => 'tax-report-'.uniqid()]);
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_vat_return_report' => true,
        ]);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
        ]);

        return $company;
    }

    public function test_tax_report_reconciles_output_input_and_expense_tax_across_all_three_tabs(): void
    {
        $company = $this->makeCompanyWithAccess();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $invoice->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);
        $invoice->recalculateTotals();
        $invoice->save();

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier A']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'posted', 'currency' => 'SAR',
        ]);
        $bill->items()->create(['description' => 'Purchase', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15, 'vat_amount' => 75, 'line_total' => 575]);
        $bill->recalculateTotals();
        $bill->save();

        Expense::create([
            'company_id' => $company->id, 'vendor_name' => 'Vendor', 'amount' => 200,
            'gross_amount' => 230, 'vat_amount' => 30, 'tax_category' => 'standard_15',
            'expense_date' => now(), 'status' => 'approved',
        ]);

        $query = ['period' => 'custom', 'from' => now()->subYear()->toDateString(), 'to' => now()->addYear()->toDateString()];

        $sales = $this->actingAs($owner)->get(route('app.reports.vat', $query + ['tab' => 'sales']));
        $sales->assertOk();
        $sales->assertSee('INV-1');
        $sales->assertSee('150.00'); // output tax
        $sales->assertSee('45.00'); // net tax position: 150 - 75 - 30

        $purchases = $this->actingAs($owner)->get(route('app.reports.vat', $query + ['tab' => 'purchases']));
        $purchases->assertOk();
        $purchases->assertSee('BILL-1');
        $purchases->assertSee('75.00');

        $expenses = $this->actingAs($owner)->get(route('app.reports.vat', $query + ['tab' => 'expenses']));
        $expenses->assertOk();
        $expenses->assertSee('Vendor');
        $expenses->assertSee('30.00');
    }

    public function test_client_filter_excludes_invoices_from_other_customers(): void
    {
        $company = $this->makeCompanyWithAccess();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Real Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-FILTER-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $invoice->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);
        $invoice->recalculateTotals();
        $invoice->save();

        $response = $this->actingAs($owner)->get(route('app.reports.vat', [
            'period' => 'custom', 'from' => now()->subYear()->toDateString(), 'to' => now()->addYear()->toDateString(),
            'tab' => 'sales', 'client_id' => 999999,
        ]));

        $response->assertOk();
        $response->assertDontSee('INV-FILTER-1');
    }

    public function test_csv_export_includes_the_bill_the_old_report_would_have_missed(): void
    {
        $company = $this->makeCompanyWithAccess();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'CSV Supplier']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'CSV-BILL-1',
            'bill_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'posted', 'currency' => 'SAR',
        ]);
        $bill->items()->create(['description' => 'Purchase', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15, 'vat_amount' => 75, 'line_total' => 575]);
        $bill->recalculateTotals();
        $bill->save();

        $response = $this->actingAs($owner)->get(route('app.reports.vat', [
            'period' => 'custom', 'from' => now()->subYear()->toDateString(), 'to' => now()->addYear()->toDateString(),
            'tab' => 'purchases', 'export' => 'csv',
        ]));

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringContainsString('CSV-BILL-1', $response->streamedContent());
        $this->assertStringContainsString('CSV Supplier', $response->streamedContent());
    }
}
