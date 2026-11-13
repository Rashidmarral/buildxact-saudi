<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Reports\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "how this showing profit this month... Paid this month SAR 0.00,
 * Expenses this month SAR 0.00, Purchases this month SAR 0.00, Profit
 * this month SAR 373,650.00... please look into this" — reproduced:
 * a Bill posted in an earlier month, then VOIDED this month, posts a
 * reversing journal entry dated the voiding action's own date (see
 * LedgerPostingService::reverse()) rather than the bill's original
 * date. That reversal credits Operating Expenses by the full bill
 * amount in the voiding month with nothing else around it, so the
 * Income Statement (and the dashboard tile that reads its netProfit)
 * shows a profit swing no "paid/expenses/purchases this month" tile
 * can explain, since those read Invoice/Expense/Bill dates, not the
 * GL reversal's date. FinancialReportService::priorPeriodAdjustments()
 * now surfaces exactly this as a labelled breakdown.
 */
class IncomeStatementPriorPeriodAdjustmentTest extends TestCase
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

    private function postAndVoidABill(Company $company, User $owner, \Carbon\Carbon $billDate, \Carbon\Carbon $voidAt): Bill
    {
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0001',
            'bill_date' => $billDate, 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 0, 'total' => 100000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh());

        \Carbon\Carbon::setTestNow($voidAt);
        $this->actingAs($owner)->post(route('app.bills.void', $bill))->assertRedirect();
        \Carbon\Carbon::setTestNow();

        return $bill->fresh();
    }

    public function test_voiding_a_bill_from_a_prior_month_is_flagged_as_a_prior_period_adjustment_this_month(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $thisMonthStart = now()->startOfMonth();
        $thisMonthEnd = now()->endOfMonth();

        $this->postAndVoidABill($company, $owner, now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(5), now());

        $statement = app(FinancialReportService::class)->incomeStatement($company, $thisMonthStart, $thisMonthEnd);

        $this->assertCount(1, $statement['priorPeriodAdjustments']);
        $adjustment = $statement['priorPeriodAdjustments']->first();
        $this->assertEquals(100000.0, $adjustment['amount']);
        $this->assertStringContainsString('BILL-0001', $adjustment['label']);

        // The entire "mystery" profit this month is explained by this one
        // adjustment — with nothing else posted, netProfit equals it exactly.
        $this->assertEquals(100000.0, $statement['netProfit']);
    }

    public function test_voiding_a_bill_in_the_same_month_it_was_posted_is_not_flagged(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $this->postAndVoidABill($company, $owner, now()->startOfMonth()->addDays(2), now());

        $statement = app(FinancialReportService::class)->incomeStatement($company, now()->startOfMonth(), now()->endOfMonth());

        $this->assertCount(0, $statement['priorPeriodAdjustments']);
        // A same-month post + void nets to zero — no mystery profit.
        $this->assertEquals(0.0, $statement['netProfit']);
    }

    public function test_the_income_statement_page_shows_the_adjustment_breakdown(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $this->postAndVoidABill($company, $owner, now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(5), now());

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement', ['period' => 'this_month']));

        $response->assertOk()
            ->assertSee(__('Prior-period adjustments included above'))
            ->assertSee('BILL-0001')
            ->assertSee('100,000.00');
    }

    public function test_the_income_statement_page_shows_no_adjustment_box_when_there_is_none(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement', ['period' => 'this_month']));

        $response->assertOk()->assertDontSee(__('Prior-period adjustments included above'));
    }

    public function test_the_dashboard_profit_tile_links_to_the_income_statement_when_an_adjustment_exists(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true, 'has_financial_statements' => true]);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $this->postAndVoidABill($company, $owner, now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(5), now());

        $dashboard = $this->actingAs($owner)->get(route('app.dashboard'));

        $dashboard->assertOk()
            ->assertSee(__('Includes a prior-period adjustment — tap for details'))
            ->assertSee(route('app.reports.income-statement', ['period' => 'this_month']), false);
    }

    public function test_the_dashboard_profit_tile_shows_no_note_when_there_is_no_adjustment(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $dashboard = $this->actingAs($owner)->get(route('app.dashboard'));

        $dashboard->assertOk()->assertDontSee(__('Includes a prior-period adjustment — tap for details'));
    }
}
