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
 * Hamly-parity redesign (task #133): the Income Statement export is now
 * the two-sided Trading and Profit & Loss Account (income-statement) or
 * its single-column ledger-line twin (income-statement-detailed) — both
 * built from FinancialReportService::incomeStatementGrouped(). Verifies
 * the Gross profit/Gross total and Net profit/Net total figures are
 * genuine formulas that compute to the same numbers the service itself
 * reports, including a Sales Returns contra-revenue line that must net
 * OUT of Direct Income rather than add to it.
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

    private function postActivity(Company $company): void
    {
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $sales = Account::where('company_id', $company->id)->where('code', '4000')->first();
        $returns = Account::where('company_id', $company->id)->where('code', '4150')->first();
        $otherIncome = Account::where('company_id', $company->id)->where('code', '4200')->first();
        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $opex = Account::where('company_id', $company->id)->where('code', '5100')->first();

        $svc = app(LedgerPostingService::class);
        $svc->post($company, 'manual', 1, 'Sale', now(), [
            ['account_id' => $bank->id, 'debit' => 90000],
            ['account_id' => $sales->id, 'credit' => 90000],
        ]);
        $svc->post($company, 'manual', 2, 'Return', now(), [
            ['account_id' => $returns->id, 'debit' => 4000],
            ['account_id' => $bank->id, 'credit' => 4000],
        ]);
        $svc->post($company, 'manual', 3, 'Other income', now(), [
            ['account_id' => $bank->id, 'debit' => 10],
            ['account_id' => $otherIncome->id, 'credit' => 10],
        ]);
        $svc->post($company, 'manual', 4, 'COGS', now(), [
            ['account_id' => $cogs->id, 'debit' => 30000],
            ['account_id' => $bank->id, 'credit' => 30000],
        ]);
        $svc->post($company, 'manual', 5, 'Opex', now(), [
            ['account_id' => $opex->id, 'debit' => 12000],
            ['account_id' => $bank->id, 'credit' => 12000],
        ]);
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

    public function test_two_sided_export_gross_and_net_figures_match_the_service_and_net_sales_returns_correctly(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postActivity($company);

        $expected = app(\App\Services\Reports\FinancialReportService::class)->incomeStatementGrouped($company, now()->startOfYear(), now()->endOfYear());
        // 90,000 sales − 4,000 returns = 86,000 direct income; 86,000 − 30,000 COGS = 56,000 gross profit.
        $this->assertEqualsWithDelta(86000.0, $expected['totalDirectIncome'], 0.01);
        $this->assertEqualsWithDelta(56000.0, $expected['grossProfit'], 0.01);

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();

        $grossProfitRow = $this->findRowLabeled($sheet, __('Gross profit'));
        $grossTotalRows = [];
        $netProfitRow = $this->findRowLabeled($sheet, __('Net profit'));
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Gross total') || $sheet->getCell('C'.$r)->getValue() === __('Gross total')) {
                $grossTotalRows[] = $r;
            }
        }
        $this->assertNotNull($grossProfitRow);
        $this->assertNotNull($netProfitRow);
        $this->assertCount(1, $grossTotalRows, 'Gross total should appear on one shared row for both sides.');

        $this->assertEqualsWithDelta($expected['grossProfit'], (float) $sheet->getCell('B'.$grossProfitRow)->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta($expected['grossTotal'], (float) $sheet->getCell('B'.$grossTotalRows[0])->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta($expected['grossTotal'], (float) $sheet->getCell('D'.$grossTotalRows[0])->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta($expected['netProfit'], (float) $sheet->getCell('B'.$netProfitRow)->getCalculatedValue(), 0.01);
    }

    public function test_detailed_export_lists_ledger_lines_with_a_dr_cr_suffix_and_correct_section_totals(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postActivity($company);

        $expected = app(\App\Services\Reports\FinancialReportService::class)->incomeStatementGrouped($company, now()->startOfYear(), now()->endOfYear());

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement-detailed', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();

        $values = [];
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $values[] = (string) $sheet->getCell('A'.$r)->getValue();
        }
        $this->assertContains('4000', $values);
        $this->assertContains('4150', $values);

        $directIncomeTotalRow = $this->findRowLabeled($sheet, __('Total of :section', ['section' => __('Direct income')]));
        $grossProfitRow = $this->findRowLabeled($sheet, __('Gross profit'));
        $netProfitRow = $this->findRowLabeled($sheet, __('Net profit'));
        $this->assertNotNull($directIncomeTotalRow);
        $this->assertNotNull($grossProfitRow);
        $this->assertNotNull($netProfitRow);

        $this->assertStringStartsWith('=', (string) $sheet->getCell('C'.$directIncomeTotalRow)->getValue());
        $this->assertEqualsWithDelta($expected['totalDirectIncome'], (float) $sheet->getCell('C'.$directIncomeTotalRow)->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta($expected['grossProfit'], (float) $sheet->getCell('C'.$grossProfitRow)->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta($expected['netProfit'], (float) $sheet->getCell('C'.$netProfitRow)->getCalculatedValue(), 0.01);
    }
}
