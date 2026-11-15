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
 * The Journal Export's Debit/Credit grand totals are SUM() formulas —
 * since every posting is balanced, they should always agree. Confirms
 * both totals compute to the same figure for a real set of postings.
 */
class JournalExportExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_debit_and_credit_total_formulas_agree(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        $sales = Account::where('company_id', $company->id)->where('code', '4000')->first();

        $svc = app(LedgerPostingService::class);
        $svc->post($company, 'manual', 1, 'Capital', now(), [
            ['account_id' => $bank->id, 'debit' => 6000],
            ['account_id' => $capital->id, 'credit' => 6000],
        ]);
        $svc->post($company, 'manual', 2, 'Sale', now(), [
            ['account_id' => $bank->id, 'debit' => 2500],
            ['account_id' => $sales->id, 'credit' => 2500],
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.journal-export', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'je-xlsx-test-').'.xlsx';
        file_put_contents($path, $response->getContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $totalRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Total')) {
                $totalRow = $r;
            }
        }
        $this->assertNotNull($totalRow);

        $debitCell = $sheet->getCell('G'.$totalRow);
        $creditCell = $sheet->getCell('H'.$totalRow);
        $this->assertStringStartsWith('=SUM', (string) $debitCell->getValue());
        $this->assertStringStartsWith('=SUM', (string) $creditCell->getValue());
        $this->assertEqualsWithDelta(8500.0, (float) $debitCell->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta(8500.0, (float) $creditCell->getCalculatedValue(), 0.01);
    }
}
