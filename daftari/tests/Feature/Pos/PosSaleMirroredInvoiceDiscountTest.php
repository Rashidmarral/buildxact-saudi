<?php

namespace Tests\Feature\Pos;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Plan;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding POS-01: InvoiceItem has no per-line discount column, so
 * Invoice::recalculateTotals() (called by PosSaleService::mirrorAsInvoice())
 * summed each mirrored line's full, undiscounted quantity*unit_price for
 * subtotal — a PosSaleItem's own discount_amount (already baked into its
 * line_total/vat_amount) was silently dropped, leaving the mirrored
 * invoice's total higher than what the sale actually charged and its
 * InvoicePayment rows actually recorded.
 */
class PosSaleMirroredInvoiceDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithPos(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_pos' => true,
        ]);

        $company = Company::create(['name' => 'Acme Retail', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeRegisterWithStock(Company $company, float $qty = 50): array
    {
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main Store']);
        $register = PosRegister::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Register 1', 'is_active' => true]);
        $item = Item::create([
            'company_id' => $company->id, 'name' => 'Bottled Water', 'unit_price' => 5, 'vat_rate' => 15,
            'is_active' => true, 'track_inventory' => true,
        ]);
        $stock = ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => $qty]);

        return compact('warehouse', 'register', 'item', 'stock');
    }

    private function openShift(User $owner, PosRegister $register, float $openingCash = 200): PosShift
    {
        $this->actingAs($owner)->post(route('app.pos.shift.open'), [
            'register_id' => $register->id,
            'opening_cash' => $openingCash,
        ]);

        return $register->openShift();
    }

    public function test_a_discounted_pos_sale_mirrors_to_an_invoice_whose_total_matches_what_was_actually_paid(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        ['register' => $register, 'item' => $item] = $this->makeRegisterWithStock($company);
        $this->openShift($owner, $register);

        // 3 units at 5 = 15 subtotal, a 3 discount makes the net line 12,
        // VAT 15% of 12 = 1.80, total paid = 13.80.
        $response = $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 3, 'unit_price' => 5, 'discount_amount' => 3],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 13.80],
            ],
        ]);

        $response->assertRedirect();

        $sale = PosSale::first();
        $this->assertNotNull($sale);
        $this->assertSame(13.80, (float) $sale->total);

        $invoice = $sale->invoice;
        $this->assertNotNull($invoice);
        $this->assertSame(13.80, (float) $invoice->total, 'The mirrored invoice must reflect the discounted total actually charged, not the undiscounted sum.');
        $this->assertSame(13.80, (float) $invoice->amount_paid);
        $this->assertSame((float) $invoice->total, (float) $invoice->amount_paid, 'A "paid" invoice must have its total match what was actually recorded as paid.');
    }

    public function test_an_undiscounted_pos_sale_still_mirrors_correctly(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        ['register' => $register, 'item' => $item] = $this->makeRegisterWithStock($company);
        $this->openShift($owner, $register);

        $response = $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 3, 'unit_price' => 5],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 17.25],
            ],
        ]);

        $response->assertRedirect();

        $sale = PosSale::first();
        $invoice = $sale->invoice;
        $this->assertSame(17.25, (float) $invoice->total);
        $this->assertSame(0.0, (float) $invoice->discount_total);
    }
}
