<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Each asset's Closing NBV cell is the roll-forward identity itself
 * (Opening + Additions − Depreciation − Disposals) written as a
 * formula, not a value copied in from FinancialReportService's own
 * computation — this confirms that formula actually evaluates to the
 * same closing NBV the service reports.
 */
class FixedAssetScheduleExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_roll_forward_formula_matches_the_services_own_closing_nbv(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $assetAccount = Account::where('company_id', $company->id)->where('code', '1500')->first();

        FixedAsset::create([
            'company_id' => $company->id, 'created_by' => $owner->id, 'account_id' => $assetAccount->id,
            'asset_code' => 'FA-001', 'name' => 'Office Laptop', 'category' => 'Equipment',
            'acquisition_date' => now()->startOfMonth(), 'acquisition_cost' => 4500, 'salvage_value' => 0,
            'useful_life_years' => 3, 'depreciation_method' => 'straight_line', 'accumulated_depreciation' => 0, 'status' => 'active',
        ]);

        $expectedRows = app(\App\Services\Reports\FinancialReportService::class)->fixedAssetSchedule($company, now()->startOfMonth(), now()->endOfMonth());
        $expectedClosingNbv = (float) $expectedRows->sum('closingNbv');

        $response = $this->actingAs($owner)->get(route('app.reports.fixed-asset-schedule', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'fa-xlsx-test-').'.xlsx';
        file_put_contents($path, $response->getContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $assetRow = null;
        $totalRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $value = (string) $sheet->getCell('A'.$r)->getValue();
            if ($value === 'FA-001') {
                $assetRow = $r;
            }
            if ($value === __('Total')) {
                $totalRow = $r;
            }
        }

        $this->assertNotNull($assetRow);
        $this->assertNotNull($totalRow);

        $assetCell = $sheet->getCell('H'.$assetRow);
        $this->assertStringContainsString('-', (string) $assetCell->getValue());
        $this->assertEqualsWithDelta(4500.0, (float) $assetCell->getCalculatedValue(), 0.01);

        $totalCell = $sheet->getCell('H'.$totalRow);
        $this->assertStringStartsWith('=SUM', (string) $totalCell->getValue());
        $this->assertEqualsWithDelta($expectedClosingNbv, (float) $totalCell->getCalculatedValue(), 0.01);
    }
}
