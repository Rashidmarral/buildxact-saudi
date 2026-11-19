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
 * Same "real formulas, not a CSV dump" treatment as Trial Balance,
 * applied to all three Balance Sheet variants: the plain 3-section
 * view, and the two-sided -detailed/-summary views.
 */
class BalanceSheetExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function postBalancedEntries(Company $company): void
    {
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $ar = Account::where('company_id', $company->id)->where('code', '1200')->first();
        $ap = Account::where('company_id', $company->id)->where('code', '2000')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();

        app(LedgerPostingService::class)->post($company, 'manual', 1, 'Owner capital', now(), [
            ['account_id' => $bank->id, 'debit' => 120000],
            ['account_id' => $capital->id, 'credit' => 120000],
        ]);
        app(LedgerPostingService::class)->post($company, 'manual', 2, 'Credit purchase', now(), [
            ['account_id' => $ar->id, 'debit' => 8000],
            ['account_id' => $ap->id, 'credit' => 8000],
        ]);
    }

    private function loadWorkbook(string $bytes): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'bs-xlsx-test-').'.xlsx';
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

    public function test_the_plain_balance_sheet_exports_as_xlsx_with_a_formula_balance_check_that_passes(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postBalancedEntries($company);

        $response = $this->actingAs($owner)->get(route('app.reports.balance-sheet', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();

        $assetsRow = $this->findRowLabeled($sheet, __('Total assets'));
        $liabEquityRow = $this->findRowLabeled($sheet, __('Total liabilities + equity'));
        $checkRow = $this->findRowLabeled($sheet, __('Balance check'));
        $this->assertNotNull($assetsRow);
        $this->assertNotNull($liabEquityRow);
        $this->assertNotNull($checkRow);

        $this->assertEqualsWithDelta(128000.0, (float) $sheet->getCell('B'.$assetsRow)->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta(128000.0, (float) $sheet->getCell('B'.$liabEquityRow)->getCalculatedValue(), 0.01);
        $this->assertSame(__('Balanced'), $sheet->getCell('B'.$checkRow)->getCalculatedValue());
    }

    public function test_the_detailed_two_sided_export_lists_account_codes_with_formula_subtotals(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postBalancedEntries($company);

        $response = $this->actingAs($owner)->get(route('app.reports.balance-sheet-detailed', ['export' => 'excel']));
        $response->assertOk();

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();

        $values = [];
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $values[] = (string) $sheet->getCell('A'.$r)->getValue();
        }
        $this->assertContains('1100', $values);
        $this->assertContains('2000', $values);

        $checkRow = $this->findRowLabeled($sheet, __('Balance check'));
        $this->assertNotNull($checkRow);
        $this->assertSame(__('Balanced'), $sheet->getCell('D'.$checkRow)->getCalculatedValue());
    }

    public function test_the_summary_two_sided_export_shows_category_totals_only_with_a_passing_balance_check(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postBalancedEntries($company);

        $response = $this->actingAs($owner)->get(route('app.reports.balance-sheet-summary', ['export' => 'excel']));
        $response->assertOk();

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();

        $values = [];
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $values[] = (string) $sheet->getCell('A'.$r)->getValue();
        }
        $this->assertContains('Accounts Payable', $values);
        $this->assertNotContains('1100', $values, 'The summary variant should not list individual account codes.');

        $checkRow = $this->findRowLabeled($sheet, __('Balance check'));
        $this->assertSame(__('Balanced'), $sheet->getCell('B'.$checkRow)->getCalculatedValue());
    }
}
