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
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more... Financial Year Close preview step" — closing a period
 * now shows a preview (net income for the stretch being closed, any
 * draft/pending-approval document dated in it) before the existing
 * closePeriod() action actually runs. The preview itself commits
 * nothing; only the "Confirm & Close" button on that page does.
 */
class FinancialYearClosePreviewTest extends TestCase
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

    public function test_preview_shows_net_income_and_does_not_lock_the_books(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001',
            'issue_date' => '2026-03-15', 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500, 'amount_paid' => 0,
        ]);
        app(LedgerPostingService::class)->postInvoiceIssued($invoice->fresh());

        $page = $this->actingAs($owner)->post(route('app.settings.approvals.close-period.preview'), [
            'accounting_lock_date' => '2026-12-31',
        ]);

        $page->assertOk()->assertSee('10,000.00')->assertSee('Confirm');
        $this->assertNull($company->fresh()->accounting_lock_date);
    }

    public function test_preview_lists_open_invoices_bills_and_the_confirm_button_actually_closes(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);

        $draftInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0002',
            'issue_date' => '2026-02-01', 'status' => 'draft', 'currency' => 'SAR',
            'subtotal' => 2000, 'vat_total' => 300, 'total' => 2300, 'amount_paid' => 0,
        ]);
        $draftBill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => '2026-02-10', 'status' => 'draft', 'currency' => 'SAR', 'subtotal' => 500, 'vat_total' => 0, 'total' => 500,
        ]);

        $page = $this->actingAs($owner)->post(route('app.settings.approvals.close-period.preview'), [
            'accounting_lock_date' => '2026-12-31',
        ]);

        $page->assertOk()->assertSee($draftInvoice->invoice_number)->assertSee($draftBill->bill_number);

        $this->actingAs($owner)->post(route('app.settings.approvals.close-period'), [
            'accounting_lock_date' => '2026-12-31',
        ])->assertRedirect();

        $this->assertSame('2026-12-31', $company->fresh()->accounting_lock_date->toDateString());
    }

    public function test_preview_rejects_a_date_not_after_the_current_lock_date(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $company->update(['accounting_lock_date' => '2026-06-30']);

        $page = $this->actingAs($owner)->post(route('app.settings.approvals.close-period.preview'), [
            'accounting_lock_date' => '2026-03-31',
        ]);

        $page->assertSessionHasErrors('accounting_lock_date');
    }
}
