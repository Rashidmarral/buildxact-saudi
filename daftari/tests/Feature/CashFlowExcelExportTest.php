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
 * The Cash Flow export's "Net cash from…" rows, "Net change in cash",
 * and "Closing cash & bank" are all formulas chained the way
 * FinancialReportService::cashFlow() derives them, ending in a
 * reconciliation check against the company's actual cash balance
 * (mirroring the Balance Sheet's "Balance check" convention) — this
 * confirms the whole chain evaluates to a reconciled result.
 */
class CashFlowExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cash_flow_formula_chain_reconciles_to_the_actual_cash_balance(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        $sales = Account::where('company_id', $company->id)->where('code', '4000')->first();

        $svc = app(LedgerPostingService::class);
        $svc->post($company, 'manual', 1, 'Capital injection', now(), [
            ['account_id' => $bank->id, 'debit' => 8000],
            ['account_id' => $capital->id, 'credit' => 8000],
        ]);
        $svc->post($company, 'manual', 2, 'Cash sale', now(), [
            ['account_id' => $bank->id, 'debit' => 2500],
            ['account_id' => $sales->id, 'credit' => 2500],
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.cash-flow', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'cf-xlsx-test-').'.xlsx';
        file_put_contents($path, $response->getContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $closingRow = null;
        $checkRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $value = $sheet->getCell('A'.$r)->getValue();
            if ($value === __('Closing cash & bank')) {
                $closingRow = $r;
            }
            if ($value === __('Reconciliation check')) {
                $checkRow = $r;
            }
        }

        $this->assertNotNull($closingRow);
        $this->assertNotNull($checkRow);

        $this->assertStringStartsWith('=', (string) $sheet->getCell('B'.$closingRow)->getValue());
        $this->assertEqualsWithDelta(10500.0, (float) $sheet->getCell('B'.$closingRow)->getCalculatedValue(), 0.01);
        $this->assertSame(__('Reconciled'), $sheet->getCell('B'.$checkRow)->getCalculatedValue());
    }
}
