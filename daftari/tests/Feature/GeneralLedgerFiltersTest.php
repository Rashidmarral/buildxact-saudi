<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Reports\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hamly-parity gap (task #134): the General Ledger used to have no
 * filters at all and always dumped every account, unbounded. Adds
 * account/cost-centre/location filters mirroring the pattern
 * ledgerAccountStatement() already established.
 */
class GeneralLedgerFiltersTest extends TestCase
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

    public function test_an_account_filter_narrows_the_result_to_the_chosen_accounts_only(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0001',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh());

        $expense = Account::where('company_id', $company->id)->where('code', '5100')->first();
        $accounts = app(FinancialReportService::class)->generalLedger($company, now()->startOfMonth(), now()->endOfMonth(), collect([$expense->id]));

        $this->assertCount(1, $accounts);
        $this->assertSame($expense->id, $accounts->first()['account']->id);
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

        $accounts = app(FinancialReportService::class)->generalLedger($company, now()->startOfMonth(), now()->endOfMonth(), null, $cc->id);

        $bankRow = $accounts->firstWhere(fn ($r) => $r['account']->id === $bank->id);
        $this->assertNotNull($bankRow);
        $this->assertCount(1, $bankRow['lines']);
        $this->assertEquals(5000.0, $bankRow['periodDebit']);
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
        $accounts = app(FinancialReportService::class)->generalLedger($company, now()->startOfMonth(), now()->endOfMonth(), collect([$expense->id]), null, $branchA->id);

        $this->assertCount(1, $accounts);
        $this->assertEquals(3000.0, $accounts->first()['periodDebit']);
    }

    public function test_the_page_accepts_the_new_filter_params_and_still_renders(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $cc = CostCenter::create(['company_id' => $company->id, 'code' => 'CC1', 'name' => 'Site A']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Riyadh Branch']);
        $expense = Account::where('company_id', $company->id)->where('code', '5100')->first();

        $page = $this->actingAs($owner)->get(route('app.reports.general-ledger', [
            'account_ids' => [$expense->id], 'cost_center_id' => $cc->id, 'location_id' => $branch->id,
        ]));
        $page->assertOk()->assertSee('Site A')->assertSee('Riyadh Branch');
    }
}
