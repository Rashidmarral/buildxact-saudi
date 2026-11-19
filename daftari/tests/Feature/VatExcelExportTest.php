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
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Tax Report's Excel export is a 4-sheet workbook (Summary + one sheet
 * per tab). The Summary's Net Tax Position is a formula chain —
 * Net output tax (itself netting prepayment/advance-credit adjustments)
 * minus Net recoverable input tax (Purchases + Expenses tax, pulled via
 * cross-sheet formula from each tab's own total row) — this confirms
 * that whole chain reconciles to the same net tax position the service
 * itself computes.
 */
class VatExcelExportTest extends TestCase
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

    public function test_the_summary_sheets_net_tax_position_formula_reconciles_across_all_three_tabs(): void
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

        $query = ['period' => 'custom', 'from' => now()->subYear()->toDateString(), 'to' => now()->addYear()->toDateString(), 'export' => 'excel'];

        $response = $this->actingAs($owner)->get(route('app.reports.vat', $query));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'vat-xlsx-test-').'.xlsx';
        file_put_contents($path, $response->getContent());
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        $this->assertSame(['Summary', 'Sales', 'Purchases', 'Expenses'], array_map(fn ($s) => $s->getTitle(), $spreadsheet->getAllSheets()));

        $sales = $spreadsheet->getSheetByName('Sales');
        $this->assertStringContainsString('INV-1', implode(' ', array_map(fn ($r) => (string) $sales->getCell('B'.$r)->getValue(), range(1, $sales->getHighestRow()))));

        $summary = $spreadsheet->getSheetByName('Summary');
        $netTaxRow = null;
        foreach (range(1, $summary->getHighestRow()) as $r) {
            if ($summary->getCell('A'.$r)->getValue() === __('Net Tax Position')) {
                $netTaxRow = $r;
            }
        }
        $this->assertNotNull($netTaxRow);

        $cell = $summary->getCell('B'.$netTaxRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue());
        $this->assertEqualsWithDelta(45.0, (float) $cell->getCalculatedValue(), 0.01);
    }
}
