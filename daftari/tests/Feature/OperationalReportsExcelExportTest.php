<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PaymentVoucher;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WhtRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Excel export coverage for Sales Report, Purchase & Sale Summary (Net
 * = Sales − Purchases, a per-row formula), Expense Report, and
 * Withholding Tax.
 */
class OperationalReportsExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private function loadWorkbook(string $bytes): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'ops-xlsx-test-').'.xlsx';
        file_put_contents($path, $bytes);
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        return $spreadsheet;
    }

    public function test_sales_report_excel_export_downloads_with_the_correct_line_total(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Acme Trading']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $invoice->items()->create(['description' => 'Widget', 'quantity' => 2, 'unit_price' => 500, 'vat_rate' => 0, 'line_total' => 1000]);

        $response = $this->actingAs($owner)->get(route('app.reports.sales', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
        $totalRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Total')) {
                $totalRow = $r;
            }
        }
        $this->assertNotNull($totalRow);
        $this->assertEqualsWithDelta(1000.0, (float) $sheet->getCell('E'.$totalRow)->getCalculatedValue(), 0.01);
    }

    public function test_purchase_sale_summarys_net_column_is_a_formula_that_computes_correctly(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 1000,
        ]);
        Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0001', 'status' => 'posted',
            'bill_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 400,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.purchase-sale-summary', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
        $dataRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $amount = $sheet->getCell('B'.$r)->getCalculatedValue();
            if (is_numeric($amount) && abs((float) $amount - 1000.0) < 0.01) {
                $dataRow = $r;
            }
        }
        $this->assertNotNull($dataRow, 'Row with sales=1000 not found.');

        $cell = $sheet->getCell('D'.$dataRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue());
        $this->assertEqualsWithDelta(600.0, (float) $cell->getCalculatedValue(), 0.01);
    }

    public function test_expense_report_excel_export_downloads_with_correct_totals(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        Expense::create([
            'company_id' => $company->id, 'vendor_name' => 'Vendor A', 'amount' => 500,
            'vat_amount' => 75, 'expense_date' => now(), 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.expenses', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
        $totalRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Total')) {
                $totalRow = $r;
            }
        }
        $this->assertNotNull($totalRow);
        $this->assertEqualsWithDelta(500.0, (float) $sheet->getCell('G'.$totalRow)->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta(75.0, (float) $sheet->getCell('H'.$totalRow)->getCalculatedValue(), 0.01);
    }

    public function test_withholding_tax_excel_export_downloads_with_the_correct_wht_total(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Foreign Consultant']);
        $whtRate = WhtRate::where('company_id', $company->id)->first();
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-WHT-1', 'status' => 'posted',
            'bill_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'wht_rate_id' => $whtRate?->id,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000, 'wht_amount' => 50,
        ]);
        PaymentVoucher::create([
            'company_id' => $company->id, 'bill_id' => $bill->id, 'payee_name' => $supplier->name,
            'bank_account_id' => $bankAccount->id, 'voucher_number' => 'PV-WHT-1',
            'date' => now()->toDateString(), 'amount' => 950, 'wht_amount' => 50, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.wht-return', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
        $totalRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Total')) {
                $totalRow = $r;
            }
        }
        $this->assertNotNull($totalRow);
        $this->assertEqualsWithDelta(50.0, (float) $sheet->getCell('G'.$totalRow)->getCalculatedValue(), 0.01);
    }
}
