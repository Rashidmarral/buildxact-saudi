<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature request: after redesigning the Purchase Order PDF to match a
 * real reference document (see PurchaseOrderPdfLayoutAndAttachmentMergeTest),
 * a user asked how to edit that content on an already-created PO and how
 * to make the PDF's greeting paragraph reference a specific quotation
 * number the way the reference document did ("With reference to the
 * quotation submitted by you numbered (0003), ...").
 *
 * Surfaced a real gap along the way: PurchaseOrderController had no
 * edit()/update() at all — only create/store — so a PO could never be
 * corrected after saving. This adds them, guarded the same way
 * QuotationController::edit()/update() already are (security finding
 * D-2): only a still-draft PO can be edited, since anything past that
 * may already have driven downstream state (an approval decision, a
 * bill, stock received).
 *
 * Also adds an optional quotation_reference field, which — when set —
 * swaps the PDF's generic "Please supply/execute the items listed
 * below..." sentence for the reference document's own phrasing with the
 * real number spliced in.
 */
class PurchaseOrderEditAndQuotationReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status = 'draft'): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);

        $order = PurchaseOrder::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id,
            'po_number' => 'PO-0001', 'status' => $status, 'order_date' => now()->toDateString(),
            'subtotal' => 570000, 'vat_total' => 85500, 'total' => 655500,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id, 'description' => 'Subbase, Base Course & Asphalt',
            'quantity' => 1, 'unit_price' => 570000, 'vat_rate' => 15, 'vat_amount' => 85500, 'line_total' => 655500,
        ]);

        return [$owner, $order];
    }

    public function test_a_draft_purchase_order_can_be_edited(): void
    {
        [$owner, $order] = $this->makeOrder('draft');
        $newSupplier = Supplier::create(['company_id' => $order->company_id, 'name' => 'Another Supplier']);
        $project = Project::create(['company_id' => $order->company_id, 'code' => 'PRJ-1', 'name' => 'Al-Jumum Square Road Works', 'status' => 'active', 'created_by' => $owner->id]);

        $this->actingAs($owner)->get(route('app.purchase-orders.edit', $order))->assertOk();

        $response = $this->actingAs($owner)->put(route('app.purchase-orders.update', $order), [
            'supplier_id' => $newSupplier->id,
            'project_id' => $project->id,
            'quotation_reference' => '0003',
            'order_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Updated scope', 'quantity' => 2, 'unit_price' => 300000, 'vat_rate' => 15],
            ],
        ]);

        $response->assertRedirect(route('app.purchase-orders.show', $order));
        $order->refresh();
        $this->assertSame($newSupplier->id, $order->supplier_id);
        $this->assertSame($project->id, $order->project_id);
        $this->assertSame('0003', $order->quotation_reference);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('Updated scope', $order->items()->first()->description);
        $this->assertEqualsWithDelta(690000.0, $order->fresh()->total, 0.01);
    }

    public function test_editing_a_draft_order_can_also_approve_it_immediately(): void
    {
        [$owner, $order] = $this->makeOrder('draft');

        $response = $this->actingAs($owner)->put(route('app.purchase-orders.update', $order), [
            'supplier_id' => $order->supplier_id,
            'order_date' => now()->toDateString(),
            'items' => [['description' => 'Scope', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15]],
            'post_immediately' => '1',
        ]);

        $response->assertRedirect(route('app.purchase-orders.show', $order));
        $this->assertSame('approved', $order->fresh()->status);
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_a_non_draft_purchase_order_cannot_be_edited(string $status): void
    {
        [$owner, $order] = $this->makeOrder($status);

        $editResponse = $this->actingAs($owner)->get(route('app.purchase-orders.edit', $order));
        $editResponse->assertRedirect(route('app.purchase-orders.show', $order));
        $editResponse->assertSessionHasErrors('purchase_order');

        $updateResponse = $this->actingAs($owner)->put(route('app.purchase-orders.update', $order), [
            'supplier_id' => $order->supplier_id,
            'order_date' => now()->toDateString(),
            'items' => [['description' => 'Should not apply', 'quantity' => 1, 'unit_price' => 1, 'vat_rate' => 15]],
        ]);
        $updateResponse->assertRedirect(route('app.purchase-orders.show', $order));
        $updateResponse->assertSessionHasErrors('purchase_order');
        $this->assertSame($status, $order->fresh()->status);
        $this->assertSame('Subbase, Base Course & Asphalt', $order->fresh()->items()->first()->description);
    }

    public static function nonDraftStatuses(): array
    {
        return [['pending_approval'], ['approved'], ['void']];
    }

    public function test_the_pdf_greeting_references_the_quotation_number_when_set(): void
    {
        [, $order] = $this->makeOrder('draft');
        $order->update(['quotation_reference' => '0003']);
        $order->load('items', 'supplier', 'project');

        $html = view('documents.print.purchase-order-pdf', [
            'order' => $order,
            'doc' => ['lines' => $order->items, 'subtotal' => $order->subtotal, 'discount_total' => 0, 'vat_total' => $order->vat_total, 'total' => $order->total],
            'embed' => fn () => null,
        ])->render();

        $this->assertStringContainsString('With reference to the quotation submitted by you numbered (0003)', $html);
        $this->assertStringContainsString('بالإشارة إلى عرض السعر المقدم من قبلكم بالرقم (0003)', $html);
        $this->assertStringNotContainsString('Please supply/execute the items listed below', $html);
    }

    public function test_the_pdf_greeting_falls_back_to_generic_wording_without_a_reference(): void
    {
        [, $order] = $this->makeOrder('draft');
        $order->load('items', 'supplier', 'project');

        $html = view('documents.print.purchase-order-pdf', [
            'order' => $order,
            'doc' => ['lines' => $order->items, 'subtotal' => $order->subtotal, 'discount_total' => 0, 'vat_total' => $order->vat_total, 'total' => $order->total],
            'embed' => fn () => null,
        ])->render();

        $this->assertStringContainsString('Please supply/execute the items listed below', $html);
        $this->assertStringNotContainsString('With reference to the quotation submitted', $html);
    }

    public function test_the_pdf_shows_the_linked_project_name(): void
    {
        [$owner, $order] = $this->makeOrder('draft');
        $project = Project::create(['company_id' => $order->company_id, 'code' => 'PRJ-1', 'name' => 'Al-Jumum Square Road Works', 'status' => 'active', 'created_by' => $owner->id]);
        $order->update(['project_id' => $project->id]);
        $order->load('items', 'supplier', 'project');

        $html = view('documents.print.purchase-order-pdf', [
            'order' => $order,
            'doc' => ['lines' => $order->items, 'subtotal' => $order->subtotal, 'discount_total' => 0, 'vat_total' => $order->vat_total, 'total' => $order->total],
            'embed' => fn () => null,
        ])->render();

        $this->assertStringContainsString('Al-Jumum Square Road Works', $html);
    }
}
