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
 * Excel export coverage for the three Income Statement variants beyond
 * the formal one: Profit & Loss (Simple) — Net profit = Total income −
 * Total expenses; Income Statement (Traditional T-Account) — the
 * balancing Net Profit/Net Loss line is itself a formula, and both
 * sides' totals must agree; Profit & Loss by Month — a pivot whose
 * NET PROFIT row is a formula subtracting one total row from another
 * in every month column. The traditional T-account view had no export
 * at all before this round.
 */
class ProfitLossVariantsExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompanyWithActivity(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $sales = Account::where('company_id', $company->id)->where('code', '4000')->first();
        $opex = Account::where('company_id', $company->id)->where('code', '5100')->first();

        $svc = app(LedgerPostingService::class);
        $svc->post($company, 'manual', 1, 'Sale', now(), [
            ['account_id' => $bank->id, 'debit' => 7000],
            ['account_id' => $sales->id, 'credit' => 7000],
        ]);
        $svc->post($company, 'manual', 2, 'Opex', now(), [
            ['account_id' => $opex->id, 'debit' => 2500],
            ['account_id' => $bank->id, 'credit' => 2500],
        ]);

        return [$company, $owner];
    }

    private function loadWorkbook(string $bytes): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'pl-xlsx-test-').'.xlsx';
        file_put_contents($path, $bytes);
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        return $spreadsheet;
    }

    public function test_profit_loss_simple_net_profit_formula_computes_correctly(): void
    {
        [$company, $owner] = $this->seedCompanyWithActivity();

        $response = $this->actingAs($owner)->get(route('app.reports.profit-loss', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
        $netProfitRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Net profit')) {
                $netProfitRow = $r;
            }
        }
        $this->assertNotNull($netProfitRow);

        $cell = $sheet->getCell('D'.$netProfitRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue());
        $this->assertEqualsWithDelta(4500.0, (float) $cell->getCalculatedValue(), 0.01);
    }

    public function test_income_statement_traditional_t_account_balances_via_formulas(): void
    {
        [$company, $owner] = $this->seedCompanyWithActivity();

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement-traditional', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
        $netProfitRow = null;
        $totalRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Net Profit')) {
                $netProfitRow = $r;
            }
            if ($sheet->getCell('A'.$r)->getValue() === __('Total')) {
                $totalRow = $r;
            }
        }
        $this->assertNotNull($netProfitRow, 'Net Profit balancing line not found (expected since this period made a profit).');
        $this->assertNotNull($totalRow);

        $this->assertStringStartsWith('=', (string) $sheet->getCell('B'.$netProfitRow)->getValue());
        $this->assertEqualsWithDelta(4500.0, (float) $sheet->getCell('B'.$netProfitRow)->getCalculatedValue(), 0.01);

        // Both sides of the T-account must balance.
        $debitTotal = (float) $sheet->getCell('B'.$totalRow)->getCalculatedValue();
        $creditTotal = (float) $sheet->getCell('D'.$totalRow)->getCalculatedValue();
        $this->assertEqualsWithDelta($debitTotal, $creditTotal, 0.01);
        $this->assertEqualsWithDelta(7000.0, $creditTotal, 0.01);
    }

    public function test_profit_loss_by_month_net_profit_row_formula_computes_correctly(): void
    {
        [$company, $owner] = $this->seedCompanyWithActivity();

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement-by-month', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
        $netProfitRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('NET PROFIT')) {
                $netProfitRow = $r;
            }
        }
        $this->assertNotNull($netProfitRow);

        $lastColumn = $sheet->getHighestColumn();
        $cell = $sheet->getCell($lastColumn.$netProfitRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue());
        $this->assertEqualsWithDelta(4500.0, (float) $cell->getCalculatedValue(), 0.01);
    }
}
