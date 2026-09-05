<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Company;
use App\Models\CustomsDeclaration;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding LOW-24: a customs declaration's duty always posted
 * straight to a P&L expense account, even when it was duty on goods still
 * sitting in inventory — real landed cost means that duty is part of what
 * the goods cost, not a period expense. allocateLandedCost() spreads the
 * duty across the linked bills' tracked-inventory lines by value, bumps
 * each item's purchase_price by its per-unit share, and reclassifies the
 * same amount from expense to Inventory Asset on the ledger.
 */
class LandedCostAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Import Co.', 'slug' => 'import-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeBillWithTrackedItem(User $owner, float $unitPrice, float $quantity, ?float $purchasePrice = null): array
    {
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Overseas Supplier']);
        $item = Item::create([
            'company_id' => $owner->company_id, 'name' => 'Imported Widget', 'item_type' => 'product',
            'track_inventory' => true, 'unit_price' => $unitPrice * 2, 'purchase_price' => $purchasePrice ?? $unitPrice,
        ]);
        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-'.uniqid(),
            'status' => 'posted', 'bill_date' => now()->toDateString(),
            'subtotal' => $unitPrice * $quantity, 'vat_total' => 0, 'total' => $unitPrice * $quantity,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => $quantity, 'unit_price' => $unitPrice, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => $unitPrice * $quantity]);

        return [$item, $bill];
    }

    public function test_allocating_landed_cost_bumps_the_items_purchase_price_and_capitalizes_the_ledger(): void
    {
        $owner = $this->makeOwner();
        [$item, $bill] = $this->makeBillWithTrackedItem($owner, unitPrice: 100, quantity: 10, purchasePrice: 90);

        $declaration = CustomsDeclaration::create([
            'company_id' => $owner->company_id, 'declaration_date' => now()->toDateString(),
            'customs_value' => 1000, 'customs_duty' => 200, 'vat_rate' => 15, 'vat_amount' => 180,
        ]);
        $declaration->bills()->sync([$bill->id]);

        $response = $this->actingAs($owner)->post(route('app.customs-declarations.allocate-landed-cost', $declaration));
        $response->assertRedirect();

        // All 1000 of bill value is the tracked line itself, so the full
        // 200 duty capitalizes and spreads across the 10 units bought.
        $item->refresh();
        $this->assertEquals(90 + 20, (float) $item->purchase_price);

        $declaration->refresh();
        $this->assertNotNull($declaration->landed_cost_allocated_at);

        $entry = JournalEntry::where('source_type', 'customs_declaration_landed_cost')->where('source_id', $declaration->id)->with('lines.account')->first();
        $this->assertNotNull($entry);
        $this->assertEquals(200, (float) $entry->totalDebit());
        $this->assertEquals(200, (float) $entry->totalCredit());
        $this->assertTrue($entry->lines->contains(fn ($l) => $l->account->code === '1400' && (float) $l->debit === 200.0));
    }

    public function test_allocation_is_proportional_when_only_part_of_the_bill_value_is_tracked_inventory(): void
    {
        $owner = $this->makeOwner();
        [$item, $bill] = $this->makeBillWithTrackedItem($owner, unitPrice: 100, quantity: 5, purchasePrice: 50);

        // Add a non-tracked (service) line to the same bill, doubling its
        // total value — only half the bill is now tracked inventory.
        BillItem::create(['bill_id' => $bill->id, 'description' => 'Freight handling service', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 500]);
        $bill->update(['subtotal' => 1000, 'total' => 1000]);

        $declaration = CustomsDeclaration::create([
            'company_id' => $owner->company_id, 'declaration_date' => now()->toDateString(),
            'customs_value' => 1000, 'customs_duty' => 100, 'vat_rate' => 15, 'vat_amount' => 150,
        ]);
        $declaration->bills()->sync([$bill->id]);

        $this->actingAs($owner)->post(route('app.customs-declarations.allocate-landed-cost', $declaration));

        // Tracked share = 500/1000 = 50%, so only 50 of the 100 duty
        // capitalizes, spread across the 5 units bought = 10/unit.
        $item->refresh();
        $this->assertEquals(50 + 10, (float) $item->purchase_price);

        $entry = JournalEntry::where('source_type', 'customs_declaration_landed_cost')->where('source_id', $declaration->id)->first();
        $this->assertEquals(50, (float) $entry->totalDebit());
    }

    public function test_allocation_cannot_be_run_twice(): void
    {
        $owner = $this->makeOwner();
        [$item, $bill] = $this->makeBillWithTrackedItem($owner, unitPrice: 100, quantity: 10, purchasePrice: 90);

        $declaration = CustomsDeclaration::create([
            'company_id' => $owner->company_id, 'declaration_date' => now()->toDateString(),
            'customs_value' => 1000, 'customs_duty' => 200, 'vat_rate' => 15, 'vat_amount' => 180,
        ]);
        $declaration->bills()->sync([$bill->id]);

        $this->actingAs($owner)->post(route('app.customs-declarations.allocate-landed-cost', $declaration));
        $priceAfterFirstRun = $item->refresh()->purchase_price;

        $response = $this->post(route('app.customs-declarations.allocate-landed-cost', $declaration));
        $response->assertSessionHasErrors('landed_cost');

        $this->assertEquals($priceAfterFirstRun, $item->refresh()->purchase_price);
        $this->assertEquals(1, JournalEntry::where('source_type', 'customs_declaration_landed_cost')->count());
    }

    public function test_a_declaration_with_no_tracked_inventory_lines_cannot_be_allocated(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Overseas Supplier']);
        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-'.uniqid(),
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'subtotal' => 500, 'vat_total' => 0, 'total' => 500,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 500]);

        $declaration = CustomsDeclaration::create([
            'company_id' => $owner->company_id, 'declaration_date' => now()->toDateString(),
            'customs_value' => 500, 'customs_duty' => 50, 'vat_rate' => 15, 'vat_amount' => 82.5,
        ]);
        $declaration->bills()->sync([$bill->id]);

        $response = $this->actingAs($owner)->post(route('app.customs-declarations.allocate-landed-cost', $declaration));
        $response->assertSessionHasErrors('landed_cost');
        $this->assertNull($declaration->fresh()->landed_cost_allocated_at);
    }
}
