<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\JournalEntryLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Reports\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "more options filter on whole app... make trail balance more
 * helpful more options filter" (task #157) — adds the same
 * cost-centre/location filters generalLedger() already has to
 * trialBalanceGrouped(), on both the summary and detailed views.
 */
class TrialBalanceFiltersTest extends TestCase
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

    public function test_a_cost_centre_filter_only_counts_lines_tagged_to_that_cost_centre(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        $cc = CostCenter::create(['company_id' => $company->id, 'code' => 'CC1', 'name' => 'Site A']);

        $entry = app(LedgerPostingService::class)->post($company, 'manual', 1, 'Tagged contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 5000],
            ['account_id' => $capital->id, 'credit' => 5000],
        ]);
        JournalEntryLine::where('journal_entry_id', $entry->id)->where('account_id', $bank->id)->update(['cost_center_id' => $cc->id]);

        app(LedgerPostingService::class)->post($company, 'manual', 2, 'Untagged contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 900],
            ['account_id' => $capital->id, 'credit' => 900],
        ]);

        $data = app(FinancialReportService::class)->trialBalanceGrouped($company, now()->startOfMonth(), now()->endOfMonth(), $cc->id);

        $cash = $data['types']['asset']['categories']['Cash and cash equivalents'];
        $this->assertSame(5000.0, $cash['periodDebit']);
    }

    public function test_a_location_filter_only_counts_entries_sourced_from_an_invoice_or_bill_at_that_branch(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Riyadh Branch']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Jeddah Branch']);

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $billA = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'branch_id' => $branchA->id, 'bill_number' => 'BILL-A',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 3000, 'vat_total' => 0, 'total' => 3000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($billA->fresh());

        $billB = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'branch_id' => $branchB->id, 'bill_number' => 'BILL-B',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 700, 'vat_total' => 0, 'total' => 700,
        ]);
        app(LedgerPostingService::class)->postBillPosted($billB->fresh());

        $expense = Account::where('company_id', $company->id)->where('code', '5100')->first();
        $data = app(FinancialReportService::class)->trialBalanceGrouped($company, now()->startOfMonth(), now()->endOfMonth(), null, $branchA->id);

        $expenseRow = $data['types']['expense']['categories']->flatMap(fn ($cat) => $cat['rows'])->firstWhere(fn ($r) => $r['account']->id === $expense->id);
        $this->assertNotNull($expenseRow);
        $this->assertSame(3000.0, $expenseRow['periodDebit']);
    }

    public function test_the_summary_and_detailed_pages_accept_the_new_filter_params_and_still_render(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $cc = CostCenter::create(['company_id' => $company->id, 'code' => 'CC1', 'name' => 'Site A']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Riyadh Branch']);

        $summary = $this->actingAs($owner)->get(route('app.reports.trial-balance', [
            'cost_center_id' => $cc->id, 'location_id' => $branch->id,
        ]));
        $summary->assertOk()->assertSee('Site A')->assertSee('Riyadh Branch');

        $detailed = $this->actingAs($owner)->get(route('app.reports.trial-balance-detailed', [
            'cost_center_id' => $cc->id, 'location_id' => $branch->id,
        ]));
        $detailed->assertOk()->assertSee('Site A')->assertSee('Riyadh Branch');
    }
}
