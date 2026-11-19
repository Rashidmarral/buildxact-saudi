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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more... Cost Centre settings enhancements" — required-on-sales/
 * expenses toggles gate InvoiceController::send()/BillController::post(),
 * the scope setting narrows which cost centre types new cost centres
 * offer, and the "re-tag a period" tool bulk-replaces a date range's
 * cost centre tags.
 */
class CostCenterSettingsTest extends TestCase
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

    public function test_required_on_sales_blocks_sending_an_untagged_invoice_and_allows_a_tagged_one(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $company->update(['cost_center_required_on_sales' => true]);
        $costCentre = CostCenter::create(['company_id' => $company->id, 'code' => 'PRJ-1', 'name' => 'Solar', 'type' => 'project', 'is_active' => true]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001',
            'issue_date' => now()->toDateString(), 'status' => 'draft', 'currency' => 'SAR',
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'amount_paid' => 0,
        ]);

        $this->actingAs($owner)->post(route('app.invoices.send', $invoice))->assertSessionHasErrors('invoice');
        $this->assertSame('draft', $invoice->fresh()->status);

        CostCenterLink::create(['company_id' => $company->id, 'cost_center_id' => $costCentre->id, 'entity_type' => 'invoice', 'entity_id' => $invoice->id, 'allocation_percent' => 100]);

        $this->actingAs($owner)->post(route('app.invoices.send', $invoice))->assertSessionDoesntHaveErrors();
        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_required_on_expenses_blocks_posting_an_untagged_bill_and_allows_a_tagged_one(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $company->update(['cost_center_required_on_expenses' => true]);
        $costCentre = CostCenter::create(['company_id' => $company->id, 'code' => 'PRJ-1', 'name' => 'Solar', 'type' => 'project', 'is_active' => true]);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);

        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'status' => 'draft', 'currency' => 'SAR', 'subtotal' => 500, 'vat_total' => 0, 'total' => 500,
        ]);

        $this->actingAs($owner)->post(route('app.bills.post', $bill))->assertSessionHasErrors('bill');
        $this->assertSame('draft', $bill->fresh()->status);

        CostCenterLink::create(['company_id' => $company->id, 'cost_center_id' => $costCentre->id, 'entity_type' => 'bill', 'entity_id' => $bill->id, 'allocation_percent' => 100]);

        $this->actingAs($owner)->post(route('app.bills.post', $bill))->assertSessionDoesntHaveErrors();
        $this->assertSame('posted', $bill->fresh()->status);
    }

    public function test_the_toggles_are_off_by_default_and_do_not_block_anything(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001',
            'issue_date' => now()->toDateString(), 'status' => 'draft', 'currency' => 'SAR',
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'amount_paid' => 0,
        ]);

        $this->actingAs($owner)->post(route('app.invoices.send', $invoice))->assertSessionDoesntHaveErrors();
        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_saving_settings_scopes_which_types_the_add_form_offers(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $this->actingAs($owner)->post(route('app.cost-centers.settings.update'), [
            'cost_center_required_on_sales' => '1',
            'cost_center_scope' => ['project'],
        ])->assertRedirect();

        $company->refresh();
        $this->assertTrue($company->cost_center_required_on_sales);
        $this->assertSame(['project'], $company->costCenterScopeTypes());

        $page = $this->actingAs($owner)->get(route('app.cost-centers.index'));
        $page->assertOk();
    }

    public function test_retagging_a_period_replaces_existing_links_and_fills_untagged_documents(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $oldCentre = CostCenter::create(['company_id' => $company->id, 'code' => 'DEP-1', 'name' => 'Old Dept', 'type' => 'department', 'is_active' => true]);
        $newCentre = CostCenter::create(['company_id' => $company->id, 'code' => 'PRJ-1', 'name' => 'Solar', 'type' => 'project', 'is_active' => true]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);

        $taggedInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001',
            'issue_date' => '2026-03-10', 'status' => 'draft', 'currency' => 'SAR',
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'amount_paid' => 0,
        ]);
        CostCenterLink::create(['company_id' => $company->id, 'cost_center_id' => $oldCentre->id, 'entity_type' => 'invoice', 'entity_id' => $taggedInvoice->id, 'allocation_percent' => 100]);

        $untaggedInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0002',
            'issue_date' => '2026-03-20', 'status' => 'draft', 'currency' => 'SAR',
            'subtotal' => 2000, 'vat_total' => 300, 'total' => 2300, 'amount_paid' => 0,
        ]);

        $outsideInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0003',
            'issue_date' => '2026-05-01', 'status' => 'draft', 'currency' => 'SAR',
            'subtotal' => 500, 'vat_total' => 75, 'total' => 575, 'amount_paid' => 0,
        ]);
        CostCenterLink::create(['company_id' => $company->id, 'cost_center_id' => $oldCentre->id, 'entity_type' => 'invoice', 'entity_id' => $outsideInvoice->id, 'allocation_percent' => 100]);

        $this->actingAs($owner)->post(route('app.cost-centers.retag'), [
            'entity_type' => 'invoice', 'cost_center_id' => $newCentre->id,
            'from' => '2026-03-01', 'to' => '2026-03-31',
        ])->assertRedirect();

        $this->assertDatabaseHas('cost_center_links', ['entity_type' => 'invoice', 'entity_id' => $taggedInvoice->id, 'cost_center_id' => $newCentre->id, 'allocation_percent' => 100]);
        $this->assertDatabaseHas('cost_center_links', ['entity_type' => 'invoice', 'entity_id' => $untaggedInvoice->id, 'cost_center_id' => $newCentre->id, 'allocation_percent' => 100]);
        $this->assertDatabaseHas('cost_center_links', ['entity_type' => 'invoice', 'entity_id' => $outsideInvoice->id, 'cost_center_id' => $oldCentre->id]);
        $this->assertSame(1, CostCenterLink::where('entity_id', $taggedInvoice->id)->count());
    }
}
