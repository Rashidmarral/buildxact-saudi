<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more reports... Profit & Loss by month" — Hamly's own
 * competitor feature: Income Statement with one column per calendar
 * month, to see trend/seasonality a single period total can't show.
 */
class IncomeStatementByMonthReportTest extends TestCase
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

    public function test_an_expense_posted_in_one_month_only_appears_in_that_months_column(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $ledger = app(LedgerPostingService::class);

        $earlyBill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-EARLY',
            'bill_date' => now()->subMonthsNoOverflow(2), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 3000, 'vat_total' => 0, 'total' => 3000,
        ]);
        $ledger->postBillPosted($earlyBill->fresh());

        $recentBill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-RECENT',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 7000, 'vat_total' => 0, 'total' => 7000,
        ]);
        $ledger->postBillPosted($recentBill->fresh());

        $page = $this->actingAs($owner)->get(route('app.reports.income-statement-by-month'));
        $page->assertOk()->assertSee(now()->translatedFormat('M Y'))->assertSee('10,000.00');

        $pdf = $this->actingAs($owner)->get(route('app.reports.income-statement-by-month', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $csv = $this->actingAs($owner)->get(route('app.reports.income-statement-by-month', ['export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('3000.00', $csvBody);
        $this->assertStringContainsString('7000.00', $csvBody);
        $this->assertStringContainsString('10000.00', $csvBody);
    }
}
