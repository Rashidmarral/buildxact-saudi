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
 * Small tweaks (task #135): a location_id filter on
 * ledgerAccountStatement()/accountStatementMulti(), and cost-centre/
 * location filters on journalExport().
 */
class ReportLocationAndCostCentreFiltersTest extends TestCase
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

    public function test_ledger_account_statement_location_filter_only_counts_lines_from_that_branchs_bills(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Riyadh Branch']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Jeddah Branch']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);

        $billA = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'branch_id' => $branchA->id, 'bill_number' => 'BILL-A',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 4000, 'vat_total' => 0, 'total' => 4000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($billA->fresh());

        $billB = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'branch_id' => $branchB->id, 'bill_number' => 'BILL-B',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 900, 'vat_total' => 0, 'total' => 900,
        ]);
        app(LedgerPostingService::class)->postBillPosted($billB->fresh());

        $expense = Account::where('company_id', $company->id)->where('code', '5100')->first();
        $rows = app(FinancialReportService::class)->accountStatementMulti(
            $company, collect([$expense->id]), now()->startOfMonth(), now()->endOfMonth(), null, null, $branchA->id
        );

        $this->assertCount(1, $rows->first()['lines']);
        $this->assertEquals(4000.0, $rows->first()['periodDebit']);
    }

    public function test_ledger_account_statement_page_accepts_a_location_filter_and_still_renders(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Riyadh Branch']);
        $expense = Account::where('company_id', $company->id)->where('code', '5100')->first();

        $page = $this->actingAs($owner)->get(route('app.reports.ledger-account-statement', [
            'account_ids' => [$expense->id], 'location_id' => $branch->id,
        ]));
        $page->assertOk()->assertSee('Riyadh Branch');
    }

    public function test_journal_export_cost_centre_filter_only_shows_lines_tagged_to_that_cost_centre(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        $cc = CostCenter::create(['company_id' => $company->id, 'code' => 'CC1', 'name' => 'Site A']);

        $entry = app(LedgerPostingService::class)->post($company, 'manual', 1, 'Tagged contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 2200],
            ['account_id' => $capital->id, 'credit' => 2200],
        ]);
        JournalEntryLine::where('journal_entry_id', $entry->id)->where('account_id', $bank->id)->update(['cost_center_id' => $cc->id]);

        app(LedgerPostingService::class)->post($company, 'manual', 2, 'Untagged contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 600],
            ['account_id' => $capital->id, 'credit' => 600],
        ]);

        $rows = app(FinancialReportService::class)->journalExport($company, now()->startOfMonth(), now()->endOfMonth(), $cc->id);

        $this->assertCount(1, $rows);
        $this->assertEquals(2200.0, $rows->first()['debit']);
    }

    public function test_journal_export_location_filter_only_shows_entries_sourced_from_that_branchs_bills(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Riyadh Branch']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Jeddah Branch']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);

        $billA = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'branch_id' => $branchA->id, 'bill_number' => 'BILL-A',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 1500, 'vat_total' => 0, 'total' => 1500,
        ]);
        app(LedgerPostingService::class)->postBillPosted($billA->fresh());

        $billB = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'branch_id' => $branchB->id, 'bill_number' => 'BILL-B',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 450, 'vat_total' => 0, 'total' => 450,
        ]);
        app(LedgerPostingService::class)->postBillPosted($billB->fresh());

        $rows = app(FinancialReportService::class)->journalExport($company, now()->startOfMonth(), now()->endOfMonth(), null, $branchA->id);

        $this->assertTrue($rows->every(fn ($r) => $r['reference'] !== null));
        $this->assertTrue($rows->contains(fn ($r) => $r['debit'] === 1500.0));
        $this->assertFalse($rows->contains(fn ($r) => $r['debit'] === 450.0));
    }

    public function test_journal_export_page_accepts_the_new_filters_and_still_renders(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $cc = CostCenter::create(['company_id' => $company->id, 'code' => 'CC1', 'name' => 'Site A']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Riyadh Branch']);

        $page = $this->actingAs($owner)->get(route('app.reports.journal-export', [
            'cost_center_id' => $cc->id, 'location_id' => $branch->id,
        ]));
        $page->assertOk()->assertSee('Site A')->assertSee('Riyadh Branch');
    }
}
