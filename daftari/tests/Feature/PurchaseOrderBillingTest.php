<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-12: converting a purchase order to a bill was a
 * one-shot, all-or-nothing action — the entire ordered quantity was copied
 * onto a single bill and the PO was permanently marked "converted", with
 * no way to represent a partial delivery (a very common real-world case:
 * a supplier ships half an order now, the rest next month). This adds
 * per-line remaining-quantity tracking so a PO can be billed across
 * several bills, moving through draft -> approved -> partially_billed ->
 * converted (fully billed) as bills are raised against it, and rejects
 * any attempt to bill more than a line's remaining quantity.
 */
class PurchaseOrderBillingTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedOrder(float $quantity = 10): array
    {
        $company = Company::create(['name' => 'Billing Co.', 'slug' => 'billing-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Steel Supplier']);

        $order = PurchaseOrder::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id,
            'po_number' => 'PO-1', 'status' => 'approved', 'order_date' => now()->toDateString(),
            'subtotal' => $quantity * 100, 'vat_total' => $quantity * 15, 'total' => $quantity * 115,
        ]);
        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $order->id, 'description' => 'Steel bar',
            'quantity' => $quantity, 'unit_price' => 100, 'vat_rate' => 15,
            'vat_amount' => $quantity * 15, 'line_total' => $quantity * 115,
        ]);

        return [$owner, $order, $item];
    }

    public function test_billing_part_of_an_order_leaves_it_partially_billed_with_the_correct_remaining_quantity(): void
    {
        [$owner, $order, $item] = $this->makeApprovedOrder(10);

        $response = $this->actingAs($owner)->post(route('app.purchase-orders.bill-store', $order), [
            'bill_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $item->id, 'description' => 'Steel bar', 'quantity' => 4, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $order->refresh();
        $this->assertSame('partially_billed', $order->status);
        $this->assertSame(1, $order->bills()->count());
        $this->assertEqualsWithDelta(6.0, $item->fresh()->remainingQuantity(), 0.01);
        $this->assertEqualsWithDelta(4.0, $item->fresh()->billedQuantity(), 0.01);
    }

    public function test_a_second_bill_completing_the_remainder_marks_the_order_fully_billed(): void
    {
        [$owner, $order, $item] = $this->makeApprovedOrder(10);

        $this->actingAs($owner)->post(route('app.purchase-orders.bill-store', $order), [
            'bill_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $item->id, 'description' => 'Steel bar', 'quantity' => 4, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ]);

        $response = $this->actingAs($owner)->post(route('app.purchase-orders.bill-store', $order->fresh()), [
            'bill_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $item->id, 'description' => 'Steel bar', 'quantity' => 6, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $order->refresh();
        $this->assertSame('converted', $order->status);
        $this->assertSame(2, $order->bills()->count());
        $this->assertEqualsWithDelta(0.0, $item->fresh()->remainingQuantity(), 0.01);
        $this->assertTrue($order->isFullyBilled());
    }

    public function test_billing_more_than_the_remaining_quantity_is_rejected(): void
    {
        [$owner, $order, $item] = $this->makeApprovedOrder(10);

        $response = $this->actingAs($owner)->post(route('app.purchase-orders.bill-store', $order), [
            'bill_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $item->id, 'description' => 'Steel bar', 'quantity' => 15, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame('approved', $order->fresh()->status);
        $this->assertSame(0, $order->bills()->count());
    }

    public function test_voiding_a_partially_billed_order_is_blocked(): void
    {
        [$owner, $order, $item] = $this->makeApprovedOrder(10);

        $this->actingAs($owner)->post(route('app.purchase-orders.bill-store', $order), [
            'bill_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $item->id, 'description' => 'Steel bar', 'quantity' => 4, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ]);

        $this->actingAs($owner)->post(route('app.purchase-orders.void', $order->fresh()));

        $this->assertSame('partially_billed', $order->fresh()->status);
    }

    public function test_voiding_a_fully_billed_order_is_blocked(): void
    {
        [$owner, $order, $item] = $this->makeApprovedOrder(10);

        $this->actingAs($owner)->post(route('app.purchase-orders.bill-store', $order), [
            'bill_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $item->id, 'description' => 'Steel bar', 'quantity' => 10, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ]);

        $this->actingAs($owner)->post(route('app.purchase-orders.void', $order->fresh()));

        $this->assertSame('converted', $order->fresh()->status);
    }
}
