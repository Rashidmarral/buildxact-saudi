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
 * prior_year) to the Income Statement and Balance Sheet, the two
 * statements competitors compare most, via
 * FinancialReportService::mergeComparison().
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

    public function test_income_statement_prior_period_comparison_merges_both_periods_lines(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postABill($company, 'BILL-1', now()->startOfMonth(), 10000);
        $this->postABill($company, 'BILL-2', now()->subMonthNoOverflow()->startOfMonth()->addDays(2), 4000);

        $period = ['from' => now()->startOfMonth(), 'to' => now()->endOfMonth(), 'preset' => 'this_month', 'quarter' => null, 'year' => null];
        $service = app(FinancialReportService::class);
        $current = $service->incomeStatement($company, $period['from'], $period['to']);

        $this->assertEquals(-10000.0, $current['operatingProfit']);

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement', ['period' => 'this_month', 'compare' => 'prior_period']));
        $response->assertOk()->assertSee(__('Prior'));
    }

    public function test_income_statement_comparison_page_shows_prior_amounts_for_each_line(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postABill($company, 'BILL-THIS', now(), 15000);
        $this->postABill($company, 'BILL-LAST', now()->subMonthNoOverflow(), 6000);

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement', ['period' => 'this_month', 'compare' => 'prior_period']));

        $response->assertOk()->assertSee('15,000.00')->assertSee('(6,000.00)');
    }

    public function test_income_statement_with_no_compare_behaves_exactly_as_before(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postABill($company, 'BILL-X', now(), 5000);

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement', ['period' => 'this_month']));

        $response->assertOk()->assertDontSee(__('Prior')."):");
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

    public function test_income_statement_pdf_renders_with_comparison(): void
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
