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

    public function test_detailed_view_shows_individual_accounts_with_a_dr_cr_suffix_and_balances(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postContribution($company, 7000);
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();

        $page = $this->actingAs($owner)->get(route('app.reports.balance-sheet-detailed'));
        $page->assertOk()
            ->assertSee('Cash and cash equivalents')
            ->assertSee($bank->label())
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
}
