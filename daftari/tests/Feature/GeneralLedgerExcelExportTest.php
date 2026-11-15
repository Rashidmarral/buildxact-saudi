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
 * General Ledger's running "Balance" column is written as a genuine
 * chain of formulas (each line references the line above it), not a
 * flattened running_balance copied in from the service — this proves
 * the chain actually recomputes to the right closing balance after
 * several postings, the same way a real ledger would.
 */
class GeneralLedgerExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_running_balance_formula_chain_computes_the_correct_closing_balance(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $svc = app(LedgerPostingService::class);
        $svc->post($company, 'manual', 1, 'Capital 1', now(), [
            ['account_id' => $bank->id, 'debit' => 7000],
            ['account_id' => $capital->id, 'credit' => 7000],
        ]);
        $svc->post($company, 'manual', 2, 'Capital 2', now(), [
            ['account_id' => $bank->id, 'debit' => 3000],
            ['account_id' => $capital->id, 'credit' => 3000],
        ]);
        $svc->post($company, 'manual', 3, 'Withdrawal', now(), [
            ['account_id' => $capital->id, 'debit' => 1500],
            ['account_id' => $bank->id, 'credit' => 1500],
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.general-ledger', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'gl-xlsx-test-').'.xlsx';
        file_put_contents($path, $response->getContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $bankHeaderRow = null;
        $closingRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $value = (string) $sheet->getCell('A'.$r)->getValue();
            if (str_starts_with($value, '1100')) {
                $bankHeaderRow = $r;
            }
            if ($bankHeaderRow && $value === __('Closing balance') && $closingRow === null) {
                $closingRow = $r;
            }
        }

        $this->assertNotNull($bankHeaderRow, 'Bank account section not found.');
        $this->assertNotNull($closingRow, 'Closing balance row not found for the Bank account.');

        $cell = $sheet->getCell('F'.$closingRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue(), 'Closing balance should be a formula, not a literal.');
        $this->assertEqualsWithDelta(8500.0, (float) $cell->getCalculatedValue(), 0.01);
    }
}
