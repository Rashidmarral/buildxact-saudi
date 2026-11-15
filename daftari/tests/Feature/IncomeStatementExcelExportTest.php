<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * The Income Statement export's KPI rows (Net sales → Gross profit →
 * Operating profit → Net profit) are written as a formula chain
 * mirroring FinancialReportService::incomeStatement()'s own derivation
 * (gross profit = net sales − COGS, operating profit = gross profit −
 * remaining opex) — this confirms that chain actually computes to the
 * same figures the service itself reports, not just that cells exist.
 */
class IncomeStatementExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function loadWorkbook(string $bytes): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'is-xlsx-test-').'.xlsx';
        file_put_contents($path, $bytes);
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        return $spreadsheet;
    }

    private function findRowLabeled(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $label): ?int
    {
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === $label) {
                return $r;
            }
        }

        return null;
    }

    public function test_the_kpi_formula_chain_computes_the_same_figures_the_service_reports(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $sales = Account::where('company_id', $company->id)->where('code', '4000')->first();
        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $opex = Account::where('company_id', $company->id)->where('code', '5100')->first();

        $svc = app(LedgerPostingService::class);
        $svc->post($company, 'manual', 1, 'Sale', now(), [
            ['account_id' => $bank->id, 'debit' => 90000],
            ['account_id' => $sales->id, 'credit' => 90000],
        ]);
        $svc->post($company, 'manual', 2, 'COGS', now(), [
            ['account_id' => $cogs->id, 'debit' => 30000],
            ['account_id' => $bank->id, 'credit' => 30000],
        ]);
        $svc->post($company, 'manual', 3, 'Opex', now(), [
            ['account_id' => $opex->id, 'debit' => 12000],
            ['account_id' => $bank->id, 'credit' => 12000],
        ]);

        $expectedStatement = app(\App\Services\Reports\FinancialReportService::class)->incomeStatement($company, now()->startOfYear(), now()->endOfYear());

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();

        $netSalesRow = $this->findRowLabeled($sheet, __('Net sales'));
        $grossProfitRow = $this->findRowLabeled($sheet, __('Gross profit'));
        $operatingProfitRow = $this->findRowLabeled($sheet, __('Operating profit'));
        $netProfitRow = $this->findRowLabeled($sheet, __('Net profit'));

        $this->assertNotNull($netSalesRow);
        $this->assertStringStartsWith('=', (string) $sheet->getCell('C'.$grossProfitRow)->getValue());

        $this->assertEqualsWithDelta($expectedStatement['netSales'], (float) $sheet->getCell('C'.$netSalesRow)->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta($expectedStatement['grossProfit'], (float) $sheet->getCell('C'.$grossProfitRow)->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta($expectedStatement['operatingProfit'], (float) $sheet->getCell('C'.$operatingProfitRow)->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta($expectedStatement['netProfit'], (float) $sheet->getCell('C'.$netProfitRow)->getCalculatedValue(), 0.01);
    }
}
