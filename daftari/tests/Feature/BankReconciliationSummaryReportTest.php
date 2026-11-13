<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more reports... bank reconciliation" — a single page listing
 * every bank statement reconciled in the period across every account,
 * with its GL book balance alongside the statement balance so a
 * lingering difference is visible without opening each account's
 * reconciliation workflow one at a time.
 */
class BankReconciliationSummaryReportTest extends TestCase
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

    public function test_the_report_shows_book_balance_against_statement_balance_with_any_difference(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bankGlAccount = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $bankAccount = BankAccount::create([
            'company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR',
            'is_active' => true, 'account_id' => $bankGlAccount->id,
        ]);
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();

        app(LedgerPostingService::class)->post($company, 'manual', 5001, 'Owner contribution', now()->subDays(5), [
            ['account_id' => $bankGlAccount->id, 'debit' => 20000],
            ['account_id' => $capital->id, 'credit' => 20000],
        ]);

        $reconciliation = BankReconciliation::create([
            'company_id' => $company->id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => now()->toDateString(), 'statement_ending_balance' => 20500, 'status' => 'completed',
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.bank-reconciliation-summary'));
        $page->assertOk()->assertSee('Main Bank')->assertSee('20,000.00')->assertSee('20,500.00')->assertSee('500.00');

        $csv = $this->actingAs($owner)->get(route('app.reports.bank-reconciliation-summary', ['export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('20000.00', $csvBody);
        $this->assertStringContainsString('20500.00', $csvBody);
        $this->assertStringContainsString('500.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.reports.bank-reconciliation-summary', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame($reconciliation->bank_account_id, $bankAccount->id);
    }

    public function test_a_company_with_no_reconciliations_in_the_period_shows_an_empty_state(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $page = $this->actingAs($owner)->get(route('app.reports.bank-reconciliation-summary'));
        $page->assertOk()->assertSee(__('No bank statements were reconciled in this period.'));
    }
}
