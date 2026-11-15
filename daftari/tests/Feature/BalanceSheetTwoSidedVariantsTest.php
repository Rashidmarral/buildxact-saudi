<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hamly-parity redesign (task #133): the plain Balance Sheet now
 * groups accounts under a category with its own subtotal. Two new
 * routes mirror Hamly's separate pages — balance-sheet-detailed (a
 * two-sided "T-account" layout, Assets left / Liabilities+Equity
 * right, account-level with a Dr/Cr suffix) and balance-sheet-summary
 * (same two-sided layout, category totals only).
 */
class BalanceSheetTwoSidedVariantsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function postContribution(Company $company, float $amount): void
    {
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        app(LedgerPostingService::class)->post($company, 'manual', random_int(1, 999999), 'Owner contribution', now(), [
            ['account_id' => $bank->id, 'debit' => $amount],
            ['account_id' => $capital->id, 'credit' => $amount],
        ]);
    }

    public function test_the_plain_view_groups_assets_under_their_category_with_a_subtotal(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postContribution($company, 5000);

        $page = $this->actingAs($owner)->get(route('app.reports.balance-sheet'));
        $page->assertOk()->assertSee('Cash and cash equivalents')->assertSee("Owner's Equity");
    }

    public function test_the_plain_view_is_a_ledger_table_with_capital_before_liabilities(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postContribution($company, 7000);
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();

        $page = $this->actingAs($owner)->get(route('app.reports.balance-sheet'));
        $page->assertOk()
            ->assertSee(__('Ledger no.'))
            ->assertSee($bank->code)
            ->assertSee($bank->name)
            ->assertSee('7,000.00 Dr')
            ->assertSeeInOrder([mb_strtoupper(__('Assets')), mb_strtoupper(__('Capital')), mb_strtoupper(__('Liabilities'))])
            ->assertSee('Assets equal liabilities plus equity');
    }

    public function test_the_plain_view_shows_a_prior_column_when_comparing(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postContribution($company, 4000);

        $page = $this->actingAs($owner)->get(route('app.reports.balance-sheet', ['compare' => 'prior_period']));
        $page->assertOk()->assertSee(__('Prior'));
    }

    public function test_detailed_view_shows_individual_accounts_with_a_dr_cr_suffix_and_balances(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postContribution($company, 7000);
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();

        $page = $this->actingAs($owner)->get(route('app.reports.balance-sheet-detailed'));
        $page->assertOk()
            ->assertSee('Cash and cash equivalents')
            ->assertSee($bank->code)
            ->assertSee($bank->name)
            ->assertSee('7,000.00 Dr')
            ->assertSee('Assets equal liabilities plus equity');
    }

    public function test_summary_view_shows_only_category_totals_not_individual_accounts(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postContribution($company, 3000);
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();

        $page = $this->actingAs($owner)->get(route('app.reports.balance-sheet-summary'));
        $page->assertOk()->assertSee('Cash and cash equivalents')->assertDontSee($bank->label());
    }

    public function test_csv_and_pdf_export_work_for_both_variants(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postContribution($company, 1200);

        $detailedCsv = $this->actingAs($owner)->get(route('app.reports.balance-sheet-detailed', ['export' => 'csv']));
        $detailedCsv->assertOk();

        $summaryCsv = $this->actingAs($owner)->get(route('app.reports.balance-sheet-summary', ['export' => 'csv']));
        $summaryCsv->assertOk();

        $detailedPdf = $this->actingAs($owner)->get(route('app.reports.balance-sheet-detailed', ['export' => 'pdf']));
        $detailedPdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $summaryPdf = $this->actingAs($owner)->get(route('app.reports.balance-sheet-summary', ['export' => 'pdf']));
        $summaryPdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_profit_and_loss_account_splits_retained_earnings_from_current_year_earnings(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postContribution($company, 50000);

        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $sales = Account::where('company_id', $company->id)->where('code', '4000')->first();
        app(LedgerPostingService::class)->post($company, 'manual', 2, 'Sale', now(), [
            ['account_id' => $bank->id, 'debit' => 1000],
            ['account_id' => $sales->id, 'credit' => 1000],
        ]);

        $data = app(\App\Services\Reports\FinancialReportService::class)->balanceSheet($company, now());
        $retained = $data['equity']->firstWhere('key', 'RETAINED_EARNINGS');
        $currentYear = $data['equity']->firstWhere('key', 'CURRENT_YEAR_EARNINGS');

        $this->assertNotNull($retained);
        $this->assertNotNull($currentYear);
        $this->assertEqualsWithDelta(0.0, $retained['balance'], 0.01, 'No prior fiscal year has elapsed yet, so retained earnings should be zero.');
        $this->assertEqualsWithDelta(1000.0, $currentYear['balance'], 0.01);
        $this->assertSame(__('Profit & loss account'), $retained['category']);
        $this->assertSame(__('Profit & loss account'), $currentYear['category']);

        $page = $this->actingAs($owner)->get(route('app.reports.balance-sheet-detailed'));
        $page->assertOk()
            ->assertSee(__('Capital'))
            ->assertSee(__('Profit & loss account'))
            ->assertSee(__('Retained earnings (prior years)'))
            ->assertSee(__('Current year earnings'));
    }
}
