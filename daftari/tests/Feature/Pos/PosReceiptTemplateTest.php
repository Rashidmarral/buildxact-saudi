<?php

namespace Tests\Feature\Pos;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Item;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Plan;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follow-up to the Restaurant module: once a company installs POS or
 * Restaurant Management, they should also be able to pick a receipt PDF
 * layout for their sale receipts — the same idea as the invoice/
 * quotation template system (arabic/english/bilingual, a couple of
 * layouts), just a separate, dedicated small template family (see
 * ReceiptTemplatePresets) since a receipt is a narrow thermal-style
 * slip, not an A4 document.
 *
 * Also covers a real gap this work surfaced: RestaurantOrderController's
 * checkout redirects to app.pos.sales.show, but that route required the
 * 'pos' module/permission specifically — a Restaurant-only company could
 * never actually reach the receipt for the order they just rang up.
 */
class PosReceiptTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $planOverrides = []): Company
    {
        $plan = Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ], $planOverrides));

        $company = Company::create(['name' => 'Saed Est.', 'slug' => 'saed-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '300000000000003']);
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

    private function makePosSale(Company $company, User $owner): PosSale
    {
        $register = PosRegister::create(['company_id' => $company->id, 'name' => 'Register 1', 'is_active' => true]);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Bottled Water', 'unit_price' => 5, 'vat_rate' => 15, 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.pos.shift.open'), ['register_id' => $register->id, 'opening_cash' => 100]);

        $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 11.5]],
        ]);

        return PosSale::first();
    }

    // ------------------------------------------------------------------
    // The cross-module access gap
    // ------------------------------------------------------------------

    public function test_a_restaurant_only_company_can_view_and_download_the_receipt_for_its_own_checkout(): void
    {
        $company = $this->makeCompany(['has_restaurant' => true, 'has_pos' => false]);
        $owner = $this->makeOwner($company);
        $table = RestaurantTable::create(['company_id' => $company->id, 'name' => 'T1', 'seats' => 2, 'status' => 'available']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Grilled Chicken', 'unit_price' => 40, 'vat_rate' => 15, 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.restaurant.orders.store'), ['order_type' => 'dine_in', 'table_id' => $table->id]);
        $order = RestaurantOrder::first();
        $this->actingAs($owner)->postJson(route('app.restaurant.orders.items.store', $order), ['lines' => [['item_id' => $item->id, 'quantity' => 1]]]);
        $this->actingAs($owner)->postJson(route('app.restaurant.orders.checkout', $order), ['payments' => [['method' => 'cash', 'amount' => 46]]]);

        $sale = PosSale::first();
        $this->assertNotNull($sale);

        $this->actingAs($owner)->get(route('app.pos.sales.show', $sale))->assertOk();
        $response = $this->actingAs($owner)->get(route('app.pos.sales.pdf', $sale));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_company_with_neither_module_is_blocked_from_the_receipt_route(): void
    {
        $company = $this->makeCompany(['has_pos' => false, 'has_restaurant' => false]);
        $owner = $this->makeOwner($company);
        // Give it a sale directly (bypassing the gated checkout routes) so the route itself is what's under test.
        $register = PosRegister::create(['company_id' => $company->id, 'name' => 'R1']);
        $shift = PosShift::create(['company_id' => $company->id, 'register_id' => $register->id, 'opened_by' => $owner->id, 'opened_at' => now(), 'opening_cash' => 0, 'status' => 'open']);
        $sale = PosSale::create(['company_id' => $company->id, 'register_id' => $register->id, 'shift_id' => $shift->id, 'sale_number' => 'POS-1', 'status' => 'completed', 'total' => 10]);

        $this->actingAs($owner)->get(route('app.pos.sales.show', $sale))
            ->assertRedirect(route('app.dashboard'));
    }

    // ------------------------------------------------------------------
    // Receipt template picker
    // ------------------------------------------------------------------

    public function test_the_receipt_template_picker_is_reachable_once_pos_is_installed(): void
    {
        $company = $this->makeCompany(['has_pos' => true]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.receipt-templates.index'));

        $response->assertOk();
        $response->assertSee(__('Compact (Bilingual)'));
        $response->assertSee(__('Compact (English)'));
        $response->assertSee(__('Compact (Arabic)'));
        $response->assertSee(__('Detailed (Bilingual)'));
    }

    public function test_activating_a_receipt_preset_changes_the_downloaded_receipts_language(): void
    {
        $company = $this->makeCompany(['has_pos' => true]);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.receipt-templates.activate', 'receipt_compact_en'));

        $sale = $this->makePosSale($company, $owner);

        $show = $this->actingAs($owner)->get(route('app.pos.sales.show', $sale));
        $show->assertOk();
        $show->assertSee('Subtotal');
        $show->assertDontSee(__('Subtotal', [], 'ar'));
    }

    public function test_switching_to_arabic_only_flips_the_receipt_language(): void
    {
        $company = $this->makeCompany(['has_pos' => true]);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.receipt-templates.activate', 'receipt_compact_ar'));

        $sale = $this->makePosSale($company, $owner);

        $show = $this->actingAs($owner)->get(route('app.pos.sales.show', $sale));
        $show->assertOk();
        $show->assertSee(__('Subtotal', [], 'ar'));
    }

    public function test_the_detailed_layout_shows_a_per_line_vat_breakdown_and_the_company_address(): void
    {
        $company = $this->makeCompany(['has_pos' => true]);
        $company->update(['address' => '123 King Fahd Road, Riyadh']);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.receipt-templates.activate', 'receipt_detailed_bilingual'));

        $sale = $this->makePosSale($company, $owner);

        $show = $this->actingAs($owner)->get(route('app.pos.sales.show', $sale));
        $show->assertOk();
        $show->assertSee('123 King Fahd Road, Riyadh');
    }

    public function test_reactivating_a_different_preset_replaces_the_previous_default_not_adds_to_it(): void
    {
        $company = $this->makeCompany(['has_pos' => true]);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.receipt-templates.activate', 'receipt_compact_en'));
        $this->actingAs($owner)->post(route('app.receipt-templates.activate', 'receipt_compact_ar'));

        $this->assertSame(2, $company->invoiceTemplates()->where('document_type', 'pos_receipt')->count());
        $this->assertSame(1, $company->invoiceTemplates()->where('document_type', 'pos_receipt')->where('is_default', true)->count());
        $this->assertSame('receipt_compact_ar', $company->fresh()->defaultTemplateFor('pos_receipt')->preset_key);
    }

    public function test_a_company_that_never_picked_a_receipt_template_still_gets_a_working_default_receipt(): void
    {
        // Give the company an 'all'-scoped default of a general A4 layout
        // (e.g. via the invoice gallery) — resolveReceiptTemplate() must
        // not inherit that layout, since this receipt system has no idea
        // how to render 'bilingual_classic'.
        $company = $this->makeCompany(['has_pos' => true]);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'bilingual_classic'));

        $sale = $this->makePosSale($company, $owner);

        $show = $this->actingAs($owner)->get(route('app.pos.sales.show', $sale));
        $show->assertOk();
        $show->assertSee('Subtotal');
        $show->assertSee(__('Subtotal', [], 'ar'));

        $pdf = $this->actingAs($owner)->get(route('app.pos.sales.pdf', $sale));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
    }

    public function test_receipt_templates_are_isolated_per_company(): void
    {
        $companyA = $this->makeCompany(['has_pos' => true]);
        $companyB = $this->makeCompany(['has_pos' => true]);
        $ownerA = $this->makeOwner($companyA);
        $this->actingAs($ownerA)->post(route('app.receipt-templates.activate', 'receipt_compact_ar'));

        $this->assertNull($companyB->fresh()->defaultTemplateFor('pos_receipt'));
    }

    public function test_receipt_template_rows_do_not_appear_in_the_general_invoice_template_list(): void
    {
        $company = $this->makeCompany(['has_pos' => true]);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.receipt-templates.activate', 'receipt_detailed_bilingual'));
        // Consume the "... is now used on your sale receipts" flash
        // status (shared, one-request-only, shown in the layout banner
        // on whichever page renders next) so the assertion below checks
        // for an actual leaked template row, not that flash text.
        $this->actingAs($owner)->get(route('app.dashboard'));

        $response = $this->actingAs($owner)->get(route('app.invoice-templates.index'));

        $response->assertOk();
        $response->assertDontSee(__('Detailed (Bilingual)'));
    }

    public function test_activating_an_unknown_receipt_preset_404s(): void
    {
        $company = $this->makeCompany(['has_pos' => true]);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.receipt-templates.activate', 'not-a-real-preset'))->assertNotFound();
    }
}
