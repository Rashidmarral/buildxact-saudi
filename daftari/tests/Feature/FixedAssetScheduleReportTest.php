<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\User;
use App\Services\Accounting\AssetDepreciationService;
use App\Services\Accounting\FixedAssetLifecycleService;
use App\Services\Reports\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please add more reports... keep in mind final year Audit" — the #2
 * most-requested document on a year-end audit PBC list after the
 * General Ledger: a fixed asset roll-forward (opening NBV + additions
 * − disposals − depreciation for the period = closing NBV), tied to
 * the real depreciation journal entries AssetDepreciationService posts
 * rather than guessed from the FixedAsset row's own to-date running
 * accumulated_depreciation.
 */
class FixedAssetScheduleReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_opening_nbv_period_depreciation_and_closing_nbv_are_correct(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $asset = app(FixedAssetLifecycleService::class)->acquire($company, [
            'company_id' => $company->id, 'name' => 'Delivery Van', 'acquisition_date' => now()->subMonthsNoOverflow(3)->startOfMonth()->toDateString(),
            'acquisition_cost' => 120000, 'salvage_value' => 0, 'useful_life_years' => 5, 'depreciation_method' => 'straight_line', 'status' => 'active',
        ]);

        // Two months of depreciation posted BEFORE the reporting window...
        app(AssetDepreciationService::class)->postForCompany($company, now()->subMonthsNoOverflow(2));
        app(AssetDepreciationService::class)->postForCompany($company, now()->subMonthNoOverflow());
        // ...and one month posted INSIDE it.
        app(AssetDepreciationService::class)->postForCompany($company, now());

        $rows = app(FinancialReportService::class)->fixedAssetSchedule($company, now()->startOfMonth(), now()->endOfMonth());
        $row = $rows->firstWhere(fn ($r) => $r['asset']->id === $asset->id);

        $this->assertNotNull($row);
        $this->assertEquals(116000.0, $row['openingNbv']); // 120000 - 2 months * 2000
        $this->assertEquals(0.0, $row['additions']);
        $this->assertEquals(2000.0, $row['depreciation']);
        $this->assertEquals(0.0, $row['disposals']);
        $this->assertEquals(114000.0, $row['closingNbv']);
    }

    public function test_an_asset_acquired_this_period_shows_as_an_addition_with_zero_opening_nbv(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $asset = app(FixedAssetLifecycleService::class)->acquire($company, [
            'company_id' => $company->id, 'name' => 'New Laptop', 'acquisition_date' => now()->toDateString(),
            'acquisition_cost' => 6000, 'salvage_value' => 0, 'useful_life_years' => 3, 'depreciation_method' => 'straight_line', 'status' => 'active',
        ]);

        $rows = app(FinancialReportService::class)->fixedAssetSchedule($company, now()->startOfMonth(), now()->endOfMonth());
        $row = $rows->firstWhere(fn ($r) => $r['asset']->id === $asset->id);

        $this->assertEquals(0.0, $row['openingNbv']);
        $this->assertEquals(6000.0, $row['additions']);
        $this->assertEquals(6000.0, $row['closingNbv']);
    }

    public function test_a_disposal_this_period_writes_off_the_remaining_nbv_to_zero(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $lifecycle = app(FixedAssetLifecycleService::class);

        $asset = $lifecycle->acquire($company, [
            'company_id' => $company->id, 'name' => 'Old Forklift', 'acquisition_date' => now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(),
            'acquisition_cost' => 40000, 'salvage_value' => 0, 'useful_life_years' => 4, 'depreciation_method' => 'straight_line', 'status' => 'active',
        ]);
        app(AssetDepreciationService::class)->postForCompany($company, now()->subMonthNoOverflow());
        $lifecycle->dispose($asset->fresh(), 5000, now());

        $rows = app(FinancialReportService::class)->fixedAssetSchedule($company, now()->startOfMonth(), now()->endOfMonth());
        $row = $rows->firstWhere(fn ($r) => $r['asset']->id === $asset->id);

        $this->assertNotNull($row, 'A disposal that happened inside the period must still appear.');
        $this->assertGreaterThan(0, $row['disposals']);
        $this->assertEquals(0.0, $row['closingNbv']);
    }

    public function test_an_asset_disposed_entirely_before_the_period_is_left_out(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $lifecycle = app(FixedAssetLifecycleService::class);

        $asset = $lifecycle->acquire($company, [
            'company_id' => $company->id, 'name' => 'Sold Van', 'acquisition_date' => now()->subMonthsNoOverflow(6)->toDateString(),
            'acquisition_cost' => 10000, 'salvage_value' => 0, 'useful_life_years' => 2, 'depreciation_method' => 'straight_line', 'status' => 'active',
        ]);
        $lifecycle->dispose($asset->fresh(), 2000, now()->subMonthsNoOverflow(3));

        $rows = app(FinancialReportService::class)->fixedAssetSchedule($company, now()->startOfMonth(), now()->endOfMonth());

        $this->assertNull($rows->firstWhere(fn ($r) => $r['asset']->id === $asset->id));
    }

    public function test_the_page_renders_and_exports(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        app(FixedAssetLifecycleService::class)->acquire($company, [
            'company_id' => $company->id, 'name' => 'Office Printer', 'acquisition_date' => now()->toDateString(),
            'acquisition_cost' => 3000, 'salvage_value' => 0, 'useful_life_years' => 3, 'depreciation_method' => 'straight_line', 'status' => 'active',
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.fixed-asset-schedule'));
        $page->assertOk()->assertSee('Office Printer');

        $csv = $this->actingAs($owner)->get(route('app.reports.fixed-asset-schedule', ['export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('3000.00', str_replace("\xEF\xBB\xBF", '', $csv->streamedContent()));

        $pdf = $this->actingAs($owner)->get(route('app.reports.fixed-asset-schedule', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
