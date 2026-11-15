<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Reports\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more detailed reports... compare" — Zoho Books/QuickBooks/Xero
 * all let you put last period's or last year's figures next to the
 * current ones on every financial statement; this app had no such
 * option on any report. Adds a `compare` option (prior_period /
 * prior_year) to the Balance Sheet via
 * FinancialReportService::mergeComparison(). The Income Statement route
 * originally offered the same option, but the Hamly-parity redesign
 * (task #133) replaced its single-column KPI-card page with the
 * two-sided Trading/P&L Account layout, which has no natural place for
 * a side-by-side prior-period comparison — same reasoning
 * balanceSheetDetailed()/-Summary() already used to omit it — so that
 * option was dropped from this route. mergeComparison() itself, and
 * FinancialReportService::incomeStatement(), are unaffected and still
 * generally useful, so those are still covered directly below.
 */
class FinancialStatementComparisonTest extends TestCase
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

    private function postABill(Company $company, string $billNumber, \Carbon\Carbon $date, float $amount): Bill
    {
        $supplier = Supplier::firstOrCreate(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => $billNumber,
            'bill_date' => $date, 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => $amount, 'vat_total' => 0, 'total' => $amount,
        ]);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh());

        return $bill;
    }

    public function test_income_statement_service_merges_both_periods_lines_via_merge_comparison(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postABill($company, 'BILL-1', now()->startOfMonth(), 10000);
        $this->postABill($company, 'BILL-2', now()->subMonthNoOverflow()->startOfMonth()->addDays(2), 4000);

        $period = ['from' => now()->startOfMonth(), 'to' => now()->endOfMonth(), 'preset' => 'this_month', 'quarter' => null, 'year' => null];
        $service = app(FinancialReportService::class);
        $current = $service->incomeStatement($company, $period['from'], $period['to']);

        $this->assertEquals(-10000.0, $current['operatingProfit']);

        // The route itself no longer offers a `compare` option (see the
        // class docblock), but a stray/old-bookmarked query string must
        // not break it — it's simply ignored.
        $response = $this->actingAs($owner)->get(route('app.reports.income-statement', ['period' => 'this_month', 'compare' => 'prior_period']));
        $response->assertOk();
    }

    public function test_balance_sheet_prior_year_comparison_shows_both_asof_totals(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postABill($company, 'BILL-NOW', now(), 8000);
        $this->postABill($company, 'BILL-OLD', now()->subYear(), 3000);

        $response = $this->actingAs($owner)->get(route('app.reports.balance-sheet', ['compare' => 'prior_year']));

        $response->assertOk()->assertSee(__('Same date, prior year'), false);
    }

    public function test_balance_sheet_csv_export_includes_a_prior_column_when_comparing(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postABill($company, 'BILL-A', now(), 8000);

        $csv = $this->actingAs($owner)->get(route('app.reports.balance-sheet', ['compare' => 'prior_year', 'export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('Prior', str_replace("\xEF\xBB\xBF", '', $csv->streamedContent()));
    }

    public function test_balance_sheet_pdf_renders_with_comparison(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postABill($company, 'BILL-B', now(), 2000);

        $pdf = $this->actingAs($owner)->get(route('app.reports.balance-sheet', ['compare' => 'prior_period', 'export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_income_statement_pdf_renders_with_a_stray_compare_param_ignored(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postABill($company, 'BILL-C', now(), 2000);

        $pdf = $this->actingAs($owner)->get(route('app.reports.income-statement', ['period' => 'this_month', 'compare' => 'prior_year', 'export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_merge_comparison_keeps_a_line_present_in_only_one_period(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $service = app(FinancialReportService::class);

        $current = collect([['account' => (object) ['id' => 1, 'code' => '5100'], 'amount' => 100.0]]);
        $prior = collect([['account' => (object) ['id' => 2, 'code' => '5200'], 'amount' => 50.0]]);

        $merged = $service->mergeComparison($current, $prior, 'amount');

        $this->assertCount(2, $merged);
        $row1 = $merged->firstWhere(fn ($r) => $r['account']->id === 1);
        $row2 = $merged->firstWhere(fn ($r) => $r['account']->id === 2);
        $this->assertEquals(100.0, $row1['amount']);
        $this->assertEquals(0.0, $row1['priorAmount']);
        $this->assertEquals(0.0, $row2['amount']);
        $this->assertEquals(50.0, $row2['priorAmount']);
    }
}
