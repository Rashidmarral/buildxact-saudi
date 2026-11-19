<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Company;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "yes please do for purchase return as well" — the same structured
 * "Reason type" dropdown (Returned goods / Pricing error / Order
 * cancelled / Other) and "Original bill" summary just added to credit
 * notes and debit notes. Like credit notes, the existing purchase
 * return form already pre-fills every original bill line directly into
 * one editable table; the real gap was no way to add a return line not
 * tied to any bill line, which the backend already supported
 * (bill_item_id was always nullable) but the form never exposed.
 */
class PurchaseReturnReasonTypeAndFreeformLineTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithBill(): array
    {
        $company = Company::create(['name' => 'Purchase Return Co.', 'slug' => 'pr-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Big Supplier LLC']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'posted', 'currency' => 'SAR',
        ]);
        $bill->items()->create([
            'description' => 'Steel beams', 'quantity' => 4, 'unit_price' => 250,
            'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150,
        ]);
        $bill->recalculateTotals();
        $bill->save();

        return [$company, $owner, $supplier, $bill];
    }

    public function test_a_reason_type_can_be_saved_alongside_the_free_text_reason(): void
    {
        [, $owner, , $bill] = $this->makeCompanyWithBill();
        $billItem = $bill->items->first();

        $this->actingAs($owner)->post(route('app.purchase-returns.store'), [
            'bill_id' => $bill->id,
            'issue_date' => now()->toDateString(),
            'reason' => 'Two beams arrived bent',
            'reason_type' => 'returned_goods',
            'items' => [
                ['bill_item_id' => $billItem->id, 'description' => 'Steel beams', 'quantity' => 2, 'unit_price' => 250, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $purchaseReturn = PurchaseReturn::latest('id')->first();
        $this->assertSame('returned_goods', $purchaseReturn->reason_type);
        $this->assertSame('Returned goods', $purchaseReturn->reasonTypeLabel());
    }

    public function test_an_invalid_reason_type_is_rejected(): void
    {
        [, $owner, , $bill] = $this->makeCompanyWithBill();
        $billItem = $bill->items->first();

        $response = $this->actingAs($owner)->post(route('app.purchase-returns.store'), [
            'bill_id' => $bill->id,
            'issue_date' => now()->toDateString(),
            'reason_type' => 'not-a-real-type',
            'items' => [
                ['bill_item_id' => $billItem->id, 'description' => 'Steel beams', 'quantity' => 1, 'unit_price' => 250, 'vat_rate' => 15],
            ],
        ]);

        $response->assertSessionHasErrors('reason_type');
        $this->assertSame(0, PurchaseReturn::count());
    }

    public function test_reason_type_is_optional(): void
    {
        [, $owner, , $bill] = $this->makeCompanyWithBill();
        $billItem = $bill->items->first();

        $this->actingAs($owner)->post(route('app.purchase-returns.store'), [
            'bill_id' => $bill->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['bill_item_id' => $billItem->id, 'description' => 'Steel beams', 'quantity' => 1, 'unit_price' => 250, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $purchaseReturn = PurchaseReturn::latest('id')->first();
        $this->assertNull($purchaseReturn->reason_type);
        $this->assertNull($purchaseReturn->reasonTypeLabel());
    }

    public function test_a_freeform_line_not_tied_to_any_bill_line_can_be_returned(): void
    {
        [, $owner, , $bill] = $this->makeCompanyWithBill();

        $response = $this->actingAs($owner)->post(route('app.purchase-returns.store'), [
            'bill_id' => $bill->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Restocking fee credit', 'quantity' => 1, 'unit_price' => 20, 'vat_rate' => 15],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $purchaseReturn = PurchaseReturn::latest('id')->first();
        $this->assertCount(1, $purchaseReturn->items);
        $this->assertNull($purchaseReturn->items->first()->bill_item_id);
        $this->assertSame('Restocking fee credit', $purchaseReturn->items->first()->description);
    }

    public function test_the_create_form_shows_the_original_bill_summary(): void
    {
        [, $owner, , $bill] = $this->makeCompanyWithBill();

        $response = $this->actingAs($owner)->get(route('app.purchase-returns.create', ['bill_id' => $bill->id]));

        $response->assertOk();
        $response->assertSee($bill->bill_number);
        $response->assertSee('Steel beams');
        $response->assertSee(__('Reason type'));
        $response->assertSee(__('+ Add line'));
    }

    public function test_the_show_page_renders_the_reason_type_label_alongside_the_reason(): void
    {
        [$company, $owner, $supplier, $bill] = $this->makeCompanyWithBill();
        $purchaseReturn = PurchaseReturn::create([
            'company_id' => $company->id, 'bill_id' => $bill->id, 'supplier_id' => $supplier->id,
            'return_number' => 'PR-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'SAR',
            'reason' => 'Damaged on arrival', 'reason_type' => 'returned_goods',
            'subtotal' => 250, 'vat_total' => 37.5, 'total' => 287.5,
        ]);
        $purchaseReturn->items()->create([
            'description' => 'Steel beams', 'quantity' => 1, 'unit_price' => 250,
            'vat_rate' => 15, 'vat_amount' => 37.5, 'line_total' => 287.5,
        ]);

        $response = $this->actingAs($owner)->get(route('app.purchase-returns.show', $purchaseReturn));

        $response->assertOk();
        $response->assertSee('Returned goods');
        $response->assertSee('Damaged on arrival');
    }
}
