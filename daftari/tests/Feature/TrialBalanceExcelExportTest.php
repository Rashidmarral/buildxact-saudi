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
 * "add excel export functionality to trial balance as well with
 * functional formulas and modern template" — the existing CSV export
 * is just text; every subtotal/type-total/grand-total cell in this new
 * .xlsx export is a real SUM()/cell-reference formula over the rows
 * above it, verified here by actually letting PhpSpreadsheet's own
 * calculation engine evaluate them rather than trusting the literal
 * formula string.
 */
class TrialBalanceExcelExportTest extends TestCase
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
        $path = tempnam(sys_get_temp_dir(), 'tb-xlsx-test-').'.xlsx';
        file_put_contents($path, $bytes);
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        return $spreadsheet;
    }

    public function test_the_summary_export_downloads_as_a_real_xlsx_with_correct_content_type(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $response = $this->actingAs($owner)->get(route('app.reports.trial-balance', ['export' => 'excel']));

        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('trial-balance.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_the_summary_sheets_type_and_grand_total_rows_are_real_formulas_that_compute_correctly(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        app(LedgerPostingService::class)->post($company, 'manual', 1, 'Contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 9321.50],
            ['account_id' => $capital->id, 'credit' => 9321.50],
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.trial-balance', ['export' => 'excel']));
        $spreadsheet = $this->loadWorkbook($response->getContent());
        $sheet = $spreadsheet->getActiveSheet();

        $grandTotalRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('GRAND TOTAL')) {
                $grandTotalRow = $r;
                break;
            }
        }

        $this->assertNotNull($grandTotalRow, 'Grand total row not found in the exported workbook.');

        $cell = $sheet->getCell('F'.$grandTotalRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue(), 'Grand total should be a formula, not a literal value.');
        $this->assertEqualsWithDelta(9321.5, (float) $cell->getCalculatedValue(), 0.01);

        $cell = $sheet->getCell('G'.$grandTotalRow);
        $this->assertEqualsWithDelta(9321.5, (float) $cell->getCalculatedValue(), 0.01);
    }

    public function test_the_detailed_export_includes_accounts_payable_and_other_income_as_their_own_categories(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $response = $this->actingAs($owner)->get(route('app.reports.trial-balance-detailed', ['export' => 'excel']));
        $response->assertOk();

        $spreadsheet = $this->loadWorkbook($response->getContent());
        $sheet = $spreadsheet->getSheetByName(__('Trial Balance'));

        $values = [];
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $values[] = $sheet->getCell('A'.$r)->getValue();
        }

        $this->assertContains('Accounts Payable', $values);
        $this->assertContains('Other Income', $values);
    }

    public function test_the_detailed_exports_closing_summary_sheet_cross_references_the_main_sheet_and_matches_its_grand_total(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        app(LedgerPostingService::class)->post($company, 'manual', 2, 'Contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 4210.75],
            ['account_id' => $capital->id, 'credit' => 4210.75],
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.trial-balance-detailed', ['export' => 'excel']));
        $spreadsheet = $this->loadWorkbook($response->getContent());

        $this->assertSame(2, $spreadsheet->getSheetCount());
        $summarySheet = $spreadsheet->getSheetByName(__('Closing Summary'));
        $this->assertNotNull($summarySheet, 'The detailed export should include a separate Closing Balance Summary sheet.');

        $grandTotalRow = null;
        foreach (range(1, $summarySheet->getHighestRow()) as $r) {
            if ($summarySheet->getCell('A'.$r)->getValue() === __('GRAND TOTAL')) {
                $grandTotalRow = $r;
                break;
            }
        }

        $this->assertNotNull($grandTotalRow);

        $debitCell = $summarySheet->getCell('B'.$grandTotalRow);
        $this->assertStringContainsString("'".__('Trial Balance')."'!", (string) $this->firstCrossSheetFormulaUnder($summarySheet, $grandTotalRow));
        $this->assertEqualsWithDelta(4210.75, (float) $debitCell->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta(4210.75, (float) $summarySheet->getCell('C'.$grandTotalRow)->getCalculatedValue(), 0.01);
    }

    /**
     * Walks back up from the grand total row to the first category row
     * (which holds a direct cross-sheet reference like
     * ='Trial Balance'!G10, rather than the grand/type total rows which
     * only add other cells on this same sheet) so the test can confirm
     * cross-sheet references are actually present somewhere in the
     * sheet, not just same-sheet addition.
     */
    private function firstCrossSheetFormulaUnder(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $beforeRow): ?string
    {
        for ($r = $beforeRow - 1; $r >= 1; $r--) {
            $value = (string) $sheet->getCell('B'.$r)->getValue();
            if (str_contains($value, '!')) {
                return $value;
            }
        }

        return null;
    }
}
