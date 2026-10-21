<?php

namespace Tests\Feature;

use App\Models\AdvanceCredit;
use App\Models\AdvanceCreditApplication;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PrepaymentInvoice;
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

    /**
     * An invoice's own vat_total never shrinks when an Advance Credit is
     * applied to it, so summing every invoice's vat_total would report the
     * VAT on the credited portion twice: once when the source "advance"
     * invoice was issued, and again when the final invoice carrying the
     * credit is issued. The report must subtract the applied vat_amount.
     */
    public function test_output_tax_nets_out_vat_already_reported_via_advance_credit_application(): void
    {
        $company = $this->makeCompanyWithAccess();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($owner);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Advance Client']);

        $sourceInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'ADV-SOURCE-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $sourceInvoice->items()->create(['description' => 'Advance payment', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);
        $sourceInvoice->recalculateTotals();
        $sourceInvoice->save();

        $finalInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'ADV-FINAL-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $finalInvoice->items()->create(['description' => 'Completed work', 'quantity' => 1, 'unit_price' => 5000, 'vat_rate' => 15, 'vat_amount' => 750, 'line_total' => 5750]);
        $finalInvoice->recalculateTotals();
        $finalInvoice->save();

        $credit = AdvanceCredit::create(['company_id' => $company->id, 'client_id' => $client->id, 'source_invoice_id' => $sourceInvoice->id, 'name' => 'Advance credit']);
        $portion = $credit->portions()->create(['company_id' => $company->id, 'description' => 'Advance portion', 'amount' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'total' => 1150, 'sort_order' => 0]);
        AdvanceCreditApplication::create([
            'company_id' => $company->id, 'advance_credit_portion_id' => $portion->id, 'invoice_id' => $finalInvoice->id,
            'amount' => 1000, 'vat_amount' => 150, 'total' => 1150, 'created_by' => $owner->id,
        ]);

        $query = ['period' => 'custom', 'from' => now()->subYear()->toDateString(), 'to' => now()->addYear()->toDateString(), 'tab' => 'sales'];
        $response = $this->get(route('app.reports.vat', $query));

        $response->assertOk();
        // Gross would be 150 + 750 = 900 (shown transparently in the breakdown panel);
        // net of the 150 already reported via the source invoice, the Net Tax Position is 750.
        $response->assertSee('Net output tax for this period');
        $response->assertSeeInOrder(['VAT on sales invoices (gross)', 'SAR 900.00']);
        $response->assertSeeInOrder(['Net output tax for this period', 'SAR 750.00']);
    }

    /**
     * A genuine ZATCA 386 Prepayment Invoice's own VAT never enters this
     * report at all today (it isn't an Invoice row), so its VAT is due the
     * moment it's issued but was never being counted anywhere. This checks
     * it's now included in its own issue period, and netted back out of
     * whichever later invoice applies it so the total is counted once.
     */
    public function test_output_tax_includes_prepayment_invoice_vat_and_nets_it_out_once_applied(): void
    {
        $company = $this->makeCompanyWithAccess();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($owner);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Prepayment Client']);

        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PRE-00001', 'type' => 'standard', 'status' => 'issued',
            'issue_date' => now(), 'amount' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'total' => 1150, 'currency' => 'SAR',
        ]);

        $query = ['period' => 'custom', 'from' => now()->subYear()->toDateString(), 'to' => now()->addYear()->toDateString(), 'tab' => 'sales'];

        // Before any final invoice applies it, the prepayment's own VAT should already be counted.
        $before = $this->get(route('app.reports.vat', $query));
        $before->assertOk();
        $before->assertSee('150.00');

        $finalInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'PRE-FINAL-1',
            'applies_prepayment_invoice_id' => $prepayment->id,
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $finalInvoice->items()->create(['description' => 'Completed work', 'quantity' => 1, 'unit_price' => 5000, 'vat_rate' => 15, 'vat_amount' => 750, 'line_total' => 5750]);
        $finalInvoice->recalculateTotals();
        $finalInvoice->save();

        $after = $this->get(route('app.reports.vat', $query));
        $after->assertOk();
        // Gross would be 150 (prepayment) + 750 (final invoice) = 900 double-counting the 150;
        // net output tax for the period is just the final invoice's own 750.
        $after->assertSee('750.00');
        $after->assertDontSee('900.00');
    }
}
