<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more reports... Bank/Cash Account Statement" — money in and out
 * of ONE bank/cash account, with opening, running and closing balance,
 * picked from a bank-account-only dropdown rather than the full chart
 * of accounts the generic single-account Ledger offers.
 */
class BankAccountStatementReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_running_and_closing_balance_are_correct(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $glAccount = $bankAccount->glAccount();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        $ledger = app(LedgerPostingService::class);

        // Before the period — becomes the opening balance.
        $ledger->post($company, 'manual', 7001, 'Owner contribution', now()->subMonthsNoOverflow(2), [
            ['account_id' => $glAccount->id, 'debit' => 10000],
            ['account_id' => $capital->id, 'credit' => 10000],
        ]);
        // Inside the period — becomes a statement line.
        $ledger->post($company, 'manual', 7002, 'Owner contribution', now(), [
            ['account_id' => $glAccount->id, 'debit' => 2500],
            ['account_id' => $capital->id, 'credit' => 2500],
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.bank-account-statement', [
            'bank_account_id' => $bankAccount->id, 'period' => 'custom',
            'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString(),
        ]));
        $page->assertOk()->assertSee('Main Bank')->assertSee('10,000.00')->assertSee('12,500.00');

        $csv = $this->actingAs($owner)->get(route('app.reports.bank-account-statement', [
            'bank_account_id' => $bankAccount->id, 'period' => 'custom',
            'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString(), 'export' => 'csv',
        ]));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('10000.00', $csvBody);
        $this->assertStringContainsString('12500.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.reports.bank-account-statement', ['bank_account_id' => $bankAccount->id, 'export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
