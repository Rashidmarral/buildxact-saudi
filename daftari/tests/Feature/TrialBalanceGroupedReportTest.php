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
}
