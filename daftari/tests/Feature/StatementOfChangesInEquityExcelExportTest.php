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
 * The Total equity row is a formula chain matching
 * FinancialReportService::statementOfChangesInEquity()'s own identity:
 * totalOpening + totalMovement + netIncomeForPeriod. This confirms
 * that chain evaluates to the same total equity the service reports.
 */
class StatementOfChangesInEquityExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_total_equity_formula_matches_the_service_reported_total(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        $sales = Account::where('company_id', $company->id)->where('code', '4000')->first();

        $svc = app(LedgerPostingService::class);
        $svc->post($company, 'manual', 1, 'Capital injection', now(), [
            ['account_id' => $bank->id, 'debit' => 15000],
            ['account_id' => $capital->id, 'credit' => 15000],
        ]);
        $svc->post($company, 'manual', 2, 'Sale', now(), [
            ['account_id' => $bank->id, 'debit' => 3000],
            ['account_id' => $sales->id, 'credit' => 3000],
        ]);

        $expected = app(\App\Services\Reports\FinancialReportService::class)->statementOfChangesInEquity($company, now()->startOfMonth(), now()->endOfMonth());

        $response = $this->actingAs($owner)->get(route('app.reports.statement-of-changes-in-equity', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'sce-xlsx-test-').'.xlsx';
        file_put_contents($path, $response->getContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $totalRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Total equity')) {
                $totalRow = $r;
            }
        }
        $this->assertNotNull($totalRow);

        $cell = $sheet->getCell('E'.$totalRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue());
        $this->assertEqualsWithDelta($expected['totalClosing'], (float) $cell->getCalculatedValue(), 0.01);
    }
}
