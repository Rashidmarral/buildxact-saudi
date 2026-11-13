<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Hamly-parity redesign (task #132): the Trial Balance now groups
 * accounts under a category ("Cash and cash equivalents", "Owner's
 * Equity", ...) with Opening/Transactions/Closing Dr/Cr column-pairs,
 * instead of a flat per-account Debit/Credit-for-the-period list. The
 * plain view shows only category subtotals; trial-balance-detailed
 * shows every account.
 */
class TrialBalanceGroupedReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_an_entry_before_the_period_counts_as_opening_balance_not_a_transaction(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();

        app(LedgerPostingService::class)->post($company, 'manual', 1, 'Opening capital', now()->subMonths(2), [
            ['account_id' => $bank->id, 'debit' => 10000],
            ['account_id' => $capital->id, 'credit' => 10000],
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.trial-balance', ['period' => 'this_month']));
        $page->assertOk();

        $data = app(\App\Services\Reports\FinancialReportService::class)->trialBalanceGrouped($company, now()->startOfMonth(), now()->endOfMonth());

        $cash = $data['types']['asset']['categories']['Cash and cash equivalents'];
        $this->assertSame(10000.0, $cash['openingDebit']);
        $this->assertSame(0.0, $cash['periodDebit']);
        $this->assertSame(10000.0, $cash['closingDebit']);

        $equity = $data['types']['equity']['categories']["Owner's Equity"];
        $this->assertSame(10000.0, $equity['openingCredit']);
    }

    public function test_a_transaction_inside_the_period_moves_the_transactions_and_closing_columns(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();

        app(LedgerPostingService::class)->post($company, 'manual', 2, 'This period contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 4000],
            ['account_id' => $capital->id, 'credit' => 4000],
        ]);

        $data = app(\App\Services\Reports\FinancialReportService::class)->trialBalanceGrouped($company, now()->startOfMonth(), now()->endOfMonth());

        $cash = $data['types']['asset']['categories']['Cash and cash equivalents'];
        $this->assertSame(0.0, $cash['openingDebit']);
        $this->assertSame(4000.0, $cash['periodDebit']);
        $this->assertSame(4000.0, $cash['closingDebit']);
    }

    public function test_the_plain_view_shows_category_subtotals_and_the_detailed_view_shows_each_account(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        app(LedgerPostingService::class)->post($company, 'manual', 3, 'Contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 1500],
            ['account_id' => $capital->id, 'credit' => 1500],
        ]);

        $plain = $this->actingAs($owner)->get(route('app.reports.trial-balance'));
        $plain->assertOk()->assertSee('Cash and cash equivalents')->assertSee('Total of Assets')->assertDontSee($bank->label());

        $detailed = $this->actingAs($owner)->get(route('app.reports.trial-balance-detailed'));
        $detailed->assertOk()->assertSee('Cash and cash equivalents')->assertSee($bank->label());
    }

    public function test_csv_and_pdf_exports_work_for_both_variants(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        app(LedgerPostingService::class)->post($company, 'manual', 4, 'Contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 2500],
            ['account_id' => $capital->id, 'credit' => 2500],
        ]);

        $csv = $this->actingAs($owner)->get(route('app.reports.trial-balance', ['export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('2500.00', str_replace("\xEF\xBB\xBF", '', $csv->streamedContent()));

        $detailedCsv = $this->actingAs($owner)->get(route('app.reports.trial-balance-detailed', ['export' => 'csv']));
        $detailedCsv->assertOk();

        $pdf = $this->actingAs($owner)->get(route('app.reports.trial-balance', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $detailedPdf = $this->actingAs($owner)->get(route('app.reports.trial-balance-detailed', ['export' => 'pdf']));
        $detailedPdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_custom_account_with_no_category_falls_back_to_an_other_type_bucket(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $custom = Account::create(['company_id' => $company->id, 'code' => '1999', 'name' => 'Petty Cash Box', 'type' => 'asset', 'normal_balance' => 'debit']);
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        app(LedgerPostingService::class)->post($company, 'manual', 5, 'Petty cash funding', now(), [
            ['account_id' => $custom->id, 'debit' => 300],
            ['account_id' => $capital->id, 'credit' => 300],
        ]);

        $data = app(\App\Services\Reports\FinancialReportService::class)->trialBalanceGrouped($company, now()->startOfMonth(), now()->endOfMonth());

        $this->assertArrayHasKey('Other assets', $data['types']['asset']['categories']->toArray());
        $this->assertSame(300.0, $data['types']['asset']['categories']['Other assets']['periodDebit']);
    }

    /**
     * "add missing like equity, income, expense as categories" — a
     * company whose Equity/Income/Expense accounts have had no activity
     * at all in the selected period used to have those entire type
     * sections silently disappear from the report (every account in
     * them had zero opening/period/closing, and the old code filtered
     * those rows out before grouping) — reading as if the categories
     * themselves were missing, rather than "nothing posted here yet." A
     * trial balance is a complete list of the chart of accounts; every
     * active account, zero-balance or not, is now included.
     */
    public function test_every_account_type_still_appears_even_with_zero_activity_in_the_period(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        // Deliberately posts nothing — seedSystemAccounts() alone already
        // creates equity/revenue/expense accounts for a fresh company.

        $data = app(\App\Services\Reports\FinancialReportService::class)->trialBalanceGrouped($company, now()->startOfMonth(), now()->endOfMonth());

        $this->assertArrayHasKey('equity', $data['types']->toArray());
        $this->assertArrayHasKey('revenue', $data['types']->toArray());
        $this->assertArrayHasKey('expense', $data['types']->toArray());
        $this->assertSame(__('Income'), $data['types']['revenue']['label']);

        $page = $this->actingAs($owner)->get(route('app.reports.trial-balance'));
        $page->assertOk()->assertSee('Equity')->assertSee('Income')->assertSee('Expenses')->assertDontSee('Revenue');
    }

    /**
     * "in income other income will not come? and in liabilities accounts
     * payable will not come" — Accounts Payable (code 2000) used to be
     * folded into the "Current Liabilities" category, and Other
     * Income/FX Gain (4200, 9100) into the main "Revenue" category —
     * both read as missing when compared against a report that lists
     * them as their own line.
     */
    public function test_accounts_payable_and_other_income_each_get_their_own_category(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $data = app(\App\Services\Reports\FinancialReportService::class)->trialBalanceGrouped($company, now()->startOfMonth(), now()->endOfMonth());

        $this->assertArrayHasKey('Accounts Payable', $data['types']['liability']['categories']->toArray());
        $this->assertArrayHasKey('Other Income', $data['types']['revenue']['categories']->toArray());

        $page = $this->actingAs($owner)->get(route('app.reports.trial-balance-detailed'));
        $page->assertOk()->assertSee('Accounts Payable')->assertSee('Other Income');
    }

    /**
     * The detailed view's closing-balance recap table at the bottom
     * (Hamly-parity) reads from the exact same $types/$grand arrays the
     * main matrix above it does, so there's no second computation that
     * could drift — just confirms the section actually renders with the
     * expected grand-total figure.
     */
    public function test_the_closing_balance_recap_table_renders_with_the_grand_total(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        app(LedgerPostingService::class)->post($company, 'manual', 6, 'Contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 7777],
            ['account_id' => $capital->id, 'credit' => 7777],
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.trial-balance-detailed'));
        $page->assertOk()->assertSee('Closing Balance Summary')->assertSee('7,777.00');
    }
}
