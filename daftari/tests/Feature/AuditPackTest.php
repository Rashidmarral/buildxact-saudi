<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\AssetDepreciationService;
use App\Services\Accounting\FixedAssetLifecycleService;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please add more reports... keep in mind final year Audit" — the
 * one-click bundle that hands an auditor every statement above (Trial
 * Balance, full General Ledger, Balance Sheet, Income Statement, Cash
 * Flow, Statement of Changes in Equity, Fixed Asset Schedule, Aged
 * Receivables/Payables) in a single PDF instead of eight separate
 * downloads.
 */
class AuditPackTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_page_renders_and_the_pdf_downloads_with_real_activity_across_every_statement(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $ledger = app(LedgerPostingService::class);

        // An owner contribution — feeds the Balance Sheet, Cash Flow
        // (financing), and Statement of Changes in Equity.
        $ledger->post($company, 'manual', 3001, 'Owner contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 50000],
            ['account_id' => $capital->id, 'credit' => 50000],
        ]);

        // A posted bill — feeds the Income Statement and Trial Balance.
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 7000, 'vat_total' => 0, 'total' => 7000,
        ]);
        $ledger->postBillPosted($bill->fresh());

        // A fixed asset with a depreciation entry — feeds the Fixed
        // Asset Schedule and Cash Flow's investing/add-back lines.
        $asset = app(FixedAssetLifecycleService::class)->acquire($company, [
            'company_id' => $company->id, 'name' => 'Delivery Van', 'acquisition_date' => now()->startOfMonth()->toDateString(),
            'acquisition_cost' => 24000, 'salvage_value' => 0, 'useful_life_years' => 5, 'depreciation_method' => 'straight_line', 'status' => 'active',
        ]);
        app(AssetDepreciationService::class)->postForCompany($company, now());

        // An unpaid invoice — feeds Aged Receivables.
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001',
            'issue_date' => now()->toDateString(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500, 'amount_paid' => 0,
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.audit-pack'));
        $page->assertOk()->assertSee('Year-End Audit Pack');

        $pdf = $this->actingAs($owner)->get(route('app.reports.audit-pack.pdf'));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->assertNotNull($asset->fresh());
    }

    public function test_a_company_with_no_activity_still_renders_a_valid_pdf(): void
    {
        $company = Company::create(['name' => 'Quiet Co', 'slug' => 'quiet-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $pdf = $this->actingAs($owner)->get(route('app.reports.audit-pack.pdf'));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
