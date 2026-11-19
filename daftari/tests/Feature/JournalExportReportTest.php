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
 * "add more reports... journal export (all postings)" — every posting
 * line in the period, flat, with every dimension the ledger can
 * attach to it, for an auditor or external accountant. Contact/
 * location are a best-effort lookup through a bill-sourced entry's
 * own supplier/branch, since JournalEntry itself carries neither.
 */
class JournalExportReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bill_posting_shows_its_supplier_as_the_contact(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 5000, 'vat_total' => 0, 'total' => 5000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh());

        $page = $this->actingAs($owner)->get(route('app.reports.journal-export'));
        $page->assertOk()->assertSee('SAED EST')->assertSee('bill');

        $csv = $this->actingAs($owner)->get(route('app.reports.journal-export', ['export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('SAED EST', $csvBody);
        $this->assertStringContainsString('5000.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.reports.journal-export', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_manual_entry_shows_no_contact_but_still_appears(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        app(LedgerPostingService::class)->post($company, 'manual', 8001, 'Owner contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 3000],
            ['account_id' => $capital->id, 'credit' => 3000],
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.journal-export'));
        $page->assertOk()->assertSee('3,000.00');
    }
}
