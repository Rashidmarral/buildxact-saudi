<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\CostCenterLink;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more reports... Profit & Loss by cost centre" — income/expenses
 * split by cost centre, with untagged lines shown separately as
 * Unassigned. A revenue line linked to its invoice via CostCenterLink
 * counts toward that cost centre (split by allocation_percent); every
 * other line today — since nothing yet tags a bill/expense posting —
 * lands in Unassigned, which this test treats as expected, not a bug.
 */
class ProfitLossByCostCentreReportTest extends TestCase
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

    public function test_invoice_revenue_linked_to_a_cost_centre_counts_toward_it(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $costCentre = CostCenter::create(['company_id' => $company->id, 'code' => 'PRJ-0001', 'name' => 'Solar', 'type' => 'project', 'is_active' => true]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001',
            'issue_date' => now()->toDateString(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500, 'amount_paid' => 0,
        ]);
        app(LedgerPostingService::class)->postInvoiceIssued($invoice->fresh());

        CostCenterLink::create([
            'company_id' => $company->id, 'cost_center_id' => $costCentre->id,
            'entity_type' => 'invoice', 'entity_id' => $invoice->id, 'allocation_percent' => 100,
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.profit-loss-by-cost-centre'));
        $page->assertOk()->assertSee('PRJ-0001')->assertSee('Unassigned')->assertSee('10,000.00');

        $csv = $this->actingAs($owner)->get(route('app.reports.profit-loss-by-cost-centre', ['export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('PRJ-0001', $csvBody);
        $this->assertStringContainsString('10000.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.reports.profit-loss-by-cost-centre', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_bill_with_no_cost_centre_tag_lands_in_unassigned(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 4000, 'vat_total' => 0, 'total' => 4000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh());

        $page = $this->actingAs($owner)->get(route('app.reports.profit-loss-by-cost-centre'));
        $page->assertOk()->assertSee('Unassigned')->assertSee('4,000.00');
    }
}
