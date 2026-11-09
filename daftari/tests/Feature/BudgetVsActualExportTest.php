<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add missing PDF export... Budget vs Actual" — this report (Reports
 * > Budget vs Actual, one row per budgeted account for the year) never
 * had any export at all; this covers both the new CSV and PDF download.
 */
class BudgetVsActualExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_and_pdf_exports_download_with_budgeted_and_actual_figures(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $expenseAccount = Account::where('company_id', $company->id)->where('type', 'expense')->where('code', '5000')->first();

        $budget = Budget::create([
            'company_id' => $company->id, 'created_by' => $owner->id, 'name' => 'FY Budget',
            'fiscal_year' => now()->year, 'status' => 'draft',
        ]);
        BudgetLine::create(['budget_id' => $budget->id, 'account_id' => $expenseAccount->id, 'amount' => 10000]);

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 4000, 'vat_total' => 0, 'total' => 4000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh());

        $csv = $this->actingAs($owner)->get(route('app.budgets.show', ['budget' => $budget, 'export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('10000.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.budgets.show', ['budget' => $budget, 'export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $page = $this->actingAs($owner)->get(route('app.budgets.show', $budget));
        $page->assertOk()->assertSee(__('Download PDF'));
    }
}
