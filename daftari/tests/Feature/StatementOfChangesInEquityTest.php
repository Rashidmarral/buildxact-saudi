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
 * "add more reports... keep in mind final year Audit" — the fourth
 * standard financial statement a full audit expects alongside the
 * Balance Sheet, Income Statement and Cash Flow: per equity account,
 * opening balance + this period's own movement (contributions/
 * drawings actually posted to it) + net income for the period =
 * closing balance.
 */
class StatementOfChangesInEquityTest extends TestCase
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

    private function equityAccount(Company $company, string $code): Account
    {
        return Account::where('company_id', $company->id)->where('code', $code)->first();
    }

    private function bankAccount(Company $company): Account
    {
        return Account::where('company_id', $company->id)->where('code', '1100')->first();
    }

    public function test_opening_movement_and_net_income_roll_forward_to_the_closing_balance(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $capital = $this->equityAccount($company, '3000');
        $bank = $this->bankAccount($company);
        $ledger = app(LedgerPostingService::class);

        // A SAR 50,000 owner contribution BEFORE the period — becomes the opening balance.
        $ledger->post($company, 'manual', 1001, 'Owner contribution', now()->subMonthsNoOverflow(2), [
            ['account_id' => $bank->id, 'debit' => 50000],
            ['account_id' => $capital->id, 'credit' => 50000],
        ]);
        // A SAR 20,000 owner contribution INSIDE the period — becomes this period's movement.
        $ledger->post($company, 'manual', 1002, 'Owner contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 20000],
            ['account_id' => $capital->id, 'credit' => 20000],
        ]);
        // A bill posted inside the period — becomes net income for the period.
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 7000, 'vat_total' => 0, 'total' => 7000,
        ]);
        $ledger->postBillPosted($bill->fresh());

        $data = app(FinancialReportService::class)->statementOfChangesInEquity($company, now()->startOfMonth(), now()->endOfMonth());

        $capitalRow = $data['rows']->firstWhere(fn ($r) => $r['account']->code === '3000');
        $this->assertEquals(50000.0, $capitalRow['opening']);
        $this->assertEquals(20000.0, $capitalRow['movement']);
        $this->assertEquals(70000.0, $capitalRow['closing']);

        $this->assertEquals(0.0, $data['openingRetainedEarnings']);
        $this->assertEquals(-7000.0, $data['netIncomeForPeriod']);
        $this->assertEquals(50000.0, $data['totalOpening']);
        $this->assertEquals(20000.0, $data['totalMovement']);
        $this->assertEquals(63000.0, $data['totalClosing']); // 50000 + 20000 - 7000
    }

    public function test_a_company_with_no_equity_activity_shows_no_rows(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $data = app(FinancialReportService::class)->statementOfChangesInEquity($company, now()->startOfMonth(), now()->endOfMonth());

        $this->assertCount(0, $data['rows']);
        $this->assertEquals(0.0, $data['totalOpening']);
        $this->assertEquals(0.0, $data['totalClosing']);
    }

    public function test_the_page_renders_and_exports(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $capital = $this->equityAccount($company, '3000');
        $bank = $this->bankAccount($company);
        app(LedgerPostingService::class)->post($company, 'manual', 2001, 'Owner contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 12000],
            ['account_id' => $capital->id, 'credit' => 12000],
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.statement-of-changes-in-equity'));
        $page->assertOk()->assertSee("Owner's Equity")->assertSee('12,000.00');

        $csv = $this->actingAs($owner)->get(route('app.reports.statement-of-changes-in-equity', ['export' => 'csv']));
        $csv->assertOk();

        $pdf = $this->actingAs($owner)->get(route('app.reports.statement-of-changes-in-equity', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
