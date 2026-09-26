<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature request: a main-vendor/sub-vendor job (quote a client, then place
 * a Purchase Order with a sub-vendor for the same scope) had no way to show
 * true project profit — Project previously only tracked revenue via Invoice
 * and cost via Expense, so a sub-vendor's PO/Bill was invisible to project
 * reporting no matter how it was entered. Adds an optional project_id to
 * Quotation, PurchaseOrder and Bill (mirroring the one Invoice already
 * has), auto-inherited across Quotation->Invoice and PurchaseOrder->Bill
 * conversions, and folds posted Bills into Project::costs() so
 * revenue-minus-subcontractor-cost margin is a real, reusable number for
 * any client/sub-vendor pair — not a one-off manual comparison.
 */
class ProjectLinksQuotationsPurchaseOrdersAndBillsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyOwnerAndProject(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction Est.']);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Al-Jumum Square Road Works',
            'status' => 'active', 'client_id' => $client->id, 'created_by' => $owner->id,
        ]);

        return [$owner, $company, $client, $project];
    }

    public function test_a_quotation_can_be_created_with_a_project_and_appears_on_the_project_page(): void
    {
        [$owner, , $client, $project] = $this->makeCompanyOwnerAndProject();

        $response = $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Subbase, Base Course & Asphalt', 'quantity' => 1, 'unit_price' => 625000, 'vat_rate' => 15],
            ],
        ]);

        $response->assertRedirect();
        $quotation = Quotation::firstOrFail();
        $this->assertSame($project->id, $quotation->project_id);

        $this->actingAs($owner)->get(route('app.projects.show', $project))
            ->assertOk()
            ->assertSee($quotation->quotation_number);
    }

    public function test_a_purchase_order_can_be_created_with_a_project_and_appears_on_the_project_page(): void
    {
        [$owner, $company, , $project] = $this->makeCompanyOwnerAndProject();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);

        $response = $this->actingAs($owner)->post(route('app.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Subbase, Base Course & Asphalt (sub-vendor)', 'quantity' => 1, 'unit_price' => 570000, 'vat_rate' => 15],
            ],
        ]);

        $response->assertRedirect();
        $order = PurchaseOrder::firstOrFail();
        $this->assertSame($project->id, $order->project_id);

        $this->actingAs($owner)->get(route('app.projects.show', $project))
            ->assertOk()
            ->assertSee($order->po_number)
            ->assertSee('Saed Est.');
    }

    public function test_a_bill_can_be_created_directly_with_a_project(): void
    {
        [$owner, $company, , $project] = $this->makeCompanyOwnerAndProject();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);

        $response = $this->actingAs($owner)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'bill_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Subbase, Base Course & Asphalt (sub-vendor)', 'quantity' => 1, 'unit_price' => 570000, 'vat_rate' => 15],
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame($project->id, Bill::firstOrFail()->project_id);
    }

    public function test_converting_a_quotation_to_an_invoice_inherits_its_project(): void
    {
        [$owner, , $client, $project] = $this->makeCompanyOwnerAndProject();
        $quotation = Quotation::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'project_id' => $project->id,
            'quotation_number' => 'QTN-1', 'type' => 'quotation', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'subtotal' => 625000, 'vat_total' => 93750, 'total' => 718750,
        ]);
        $quotation->items()->create(['description' => 'Road works', 'quantity' => 1, 'unit_price' => 625000, 'vat_rate' => 15, 'vat_amount' => 93750, 'line_total' => 718750]);

        $response = $this->actingAs($owner)->post(route('app.quotations.convert', $quotation));

        $response->assertRedirect();
        $invoice = $quotation->fresh()->convertedInvoice;
        $this->assertNotNull($invoice);
        $this->assertSame($project->id, $invoice->project_id);
    }

    public function test_billing_a_purchase_order_inherits_its_project(): void
    {
        [$owner, $company, , $project] = $this->makeCompanyOwnerAndProject();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);
        $order = PurchaseOrder::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'project_id' => $project->id,
            'po_number' => 'PO-1', 'status' => 'approved', 'order_date' => now()->toDateString(),
            'subtotal' => 570000, 'vat_total' => 85500, 'total' => 655500,
        ]);
        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $order->id, 'description' => 'Road works (sub-vendor)',
            'quantity' => 1, 'unit_price' => 570000, 'vat_rate' => 15, 'vat_amount' => 85500, 'line_total' => 655500,
        ]);

        $response = $this->actingAs($owner)->post(route('app.purchase-orders.bill-store', $order), [
            'bill_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $item->id, 'description' => 'Road works (sub-vendor)', 'quantity' => 1, 'unit_price' => 570000, 'vat_rate' => 15],
            ],
        ]);

        $response->assertRedirect();
        $bill = $order->fresh()->bills()->firstOrFail();
        $this->assertSame($project->id, $bill->project_id);
    }

    public function test_project_costs_include_posted_bills_but_not_draft_or_void_ones(): void
    {
        [, $company, , $project] = $this->makeCompanyOwnerAndProject();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);

        Bill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'project_id' => $project->id, 'bill_number' => 'BILL-1', 'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => 'SAR', 'total' => 655500]);
        Bill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'project_id' => $project->id, 'bill_number' => 'BILL-2', 'status' => 'draft', 'bill_date' => now()->toDateString(), 'currency' => 'SAR', 'total' => 999999]);
        Bill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'project_id' => $project->id, 'bill_number' => 'BILL-3', 'status' => 'void', 'bill_date' => now()->toDateString(), 'currency' => 'SAR', 'total' => 999999]);

        $this->assertEqualsWithDelta(655500.0, $project->fresh()->costs(), 0.01);
    }

    public function test_project_margin_reflects_client_revenue_minus_sub_vendor_cost(): void
    {
        [, $company, $client, $project] = $this->makeCompanyOwnerAndProject();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);

        \App\Models\Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $project->id,
            'invoice_number' => 'INV-1', 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 625000, 'vat_total' => 93750, 'total' => 718750,
        ]);
        Bill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'project_id' => $project->id, 'bill_number' => 'BILL-1', 'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => 'SAR', 'total' => 655500]);

        $project = $project->fresh();
        $this->assertEqualsWithDelta(718750.0, $project->revenue(), 0.01);
        $this->assertEqualsWithDelta(655500.0, $project->costs(), 0.01);
        $this->assertEqualsWithDelta(63250.0, $project->margin(), 0.01);
    }

    public function test_deleting_a_project_is_blocked_once_a_quotation_purchase_order_or_bill_is_linked(): void
    {
        [$owner, $company, $client, $project] = $this->makeCompanyOwnerAndProject();
        Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $project->id,
            'quotation_number' => 'QTN-1', 'type' => 'quotation', 'status' => 'draft', 'issue_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($owner)->delete(route('app.projects.destroy', $project));

        $response->assertSessionHasErrors('project');
        $this->assertNotNull($project->fresh());
    }
}
