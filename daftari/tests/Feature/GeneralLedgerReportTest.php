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
 * "add more reports... every and every thing should be trackable in
 * reports" — the #1 document a year-end audit PBC list asks for: every
 * account, its opening balance, every line posted to it in the chosen
 * period, and its closing balance — all accounts in one report, as
 * opposed to JournalController::ledger()'s single-account-at-a-time
 * dropdown with no export.
 */
class GeneralLedgerReportTest extends TestCase
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

    public function test_it_shows_opening_balance_period_lines_and_closing_balance_per_account(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);

        // One bill posted before the window (becomes the opening balance)...
        $earlierBill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0001',
            'bill_date' => now()->subMonthsNoOverflow(2), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 50000, 'vat_total' => 0, 'total' => 50000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($earlierBill->fresh());

        // ...and one inside the window (becomes a period line).
        $thisMonthBill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0002',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 20000, 'vat_total' => 0, 'total' => 20000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($thisMonthBill->fresh());

        $accounts = app(FinancialReportService::class)->generalLedger($company, now()->startOfMonth(), now()->endOfMonth());

        $expenseRow = $accounts->firstWhere(fn ($r) => $r['account']->code === '5100');
        $this->assertNotNull($expenseRow, 'Operating Expenses account should appear in the general ledger.');
        $this->assertEquals(50000.0, $expenseRow['openingBalance']);
        $this->assertCount(1, $expenseRow['lines']);
        $this->assertStringContainsString('BILL-0002', $expenseRow['lines']->first()->journalEntry->description);
        $this->assertEquals(70000.0, $expenseRow['closingBalance']);
        $this->assertEquals(20000.0, $expenseRow['periodDebit']);

        $apRow = $accounts->firstWhere(fn ($r) => $r['account']->code === '2000');
        $this->assertNotNull($apRow);
        $this->assertEquals(50000.0, $apRow['openingBalance']);
        $this->assertEquals(70000.0, $apRow['closingBalance']);
    }

    public function test_an_account_with_no_opening_balance_and_no_activity_is_left_out(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $accounts = app(FinancialReportService::class)->generalLedger($company, now()->startOfMonth(), now()->endOfMonth());

        $this->assertCount(0, $accounts);
    }

    public function test_the_general_ledger_page_renders_and_exports(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0003',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 15000, 'vat_total' => 0, 'total' => 15000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh());

        $page = $this->actingAs($owner)->get(route('app.reports.general-ledger'));
        $page->assertOk()->assertSee('BILL-0003')->assertSee('Operating Expenses');

        $csv = $this->actingAs($owner)->get(route('app.reports.general-ledger', ['export' => 'csv']));
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('15000.00', str_replace("\xEF\xBB\xBF", '', $csv->streamedContent()));
    }
}
