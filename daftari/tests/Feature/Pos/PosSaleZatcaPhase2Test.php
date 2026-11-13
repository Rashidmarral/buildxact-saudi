<?php

namespace Tests\Feature\Pos;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Plan;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Compliance review finding: a POS sale never got ZATCA Phase 2 treatment
 * at all — PosController always regenerated a plain Phase 1 (5-tag) QR on
 * every view/download, regardless of the company's onboarding status, and
 * nothing about a sale was ever cleared/reported to ZATCA. Every completed
 * POS sale is, by ZATCA's own definition, a simplified (B2C) tax invoice,
 * so checkout() now mirrors it into a real Invoice and routes it through
 * the exact same, already ZATCA-tested submission pipeline every other
 * invoice uses — rather than a parallel, unproven one.
 */
class PosSaleZatcaPhase2Test extends TestCase
{
    use RefreshDatabase;

    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private function makeShopOwner(bool $onboarded): array
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_pos' => true, 'has_zatca_phase2' => true,
        ]);

        $company = Company::create(array_merge([
            'name' => 'Corner Shop', 'slug' => 'corner-'.uniqid(), 'status' => 'active',
            'currency' => 'SAR', 'vat_number' => '300000000000003',
        ], $onboarded ? [
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::TEST_CSID,
            'zatca_production_secret' => 'secret-value',
        ] : []));

        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main Store']);
        $register = PosRegister::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Register 1', 'is_active' => true]);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Bottled Water', 'unit_price' => 5, 'vat_rate' => 15, 'is_active' => true, 'track_inventory' => true]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50]);

        $this->actingAs($owner)->post(route('app.pos.shift.open'), ['register_id' => $register->id, 'opening_cash' => 100]);

        return [$company, $owner, $register, $item];
    }

    private function checkout(User $owner, PosRegister $register, Item $item): void
    {
        $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 5.75]],
        ]);
    }

    public function test_checkout_mirrors_the_sale_as_a_simplified_fully_prepaid_invoice(): void
    {
        [$company, $owner, $register, $item] = $this->makeShopOwner(onboarded: false);

        $this->checkout($owner, $register, $item);

        $sale = PosSale::first();
        $this->assertNotNull($sale->invoice_id);
        $invoice = $sale->invoice;
        $this->assertSame('simplified', $invoice->type);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(5.75, (float) $invoice->total);
        $this->assertSame(5.75, (float) $invoice->amount_paid);
        $this->assertSame($company->walkInClient()->id, $invoice->client_id);
    }

    public function test_checkout_syncs_the_mirrored_invoice_to_zatca_when_onboarded_for_phase_2(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => base64_encode('stamped-xml')], 200)]);

        [, $owner, $register, $item] = $this->makeShopOwner(onboarded: true);

        $this->checkout($owner, $register, $item);

        $sale = PosSale::first();
        $invoice = $sale->invoice->fresh();
        $this->assertNotNull($invoice->qr_code);
        $this->assertTrue(ZatcaInvoiceLog::where('invoice_id', $invoice->id)->where('status', 'reported')->exists());

        // The receipt now shows the real Phase 2 QR, not a freshly
        // regenerated Phase 1 one.
        $response = $this->actingAs($owner)->get(route('app.pos.sales.show', $sale));
        $response->assertOk();
        $response->assertSee($invoice->qr_code, false);
    }

    public function test_checkout_does_not_attempt_zatca_sync_when_not_onboarded(): void
    {
        Http::fake();

        [, $owner, $register, $item] = $this->makeShopOwner(onboarded: false);

        $this->checkout($owner, $register, $item);

        // No ZATCA sync is attempted for a company that isn't onboarded —
        // the mirrored invoice still carries its own Phase 1 QR (set by
        // the same recalculateTotals() every other invoice uses), which
        // the receipt falls back to.
        Http::assertNothingSent();
        $this->assertFalse(ZatcaInvoiceLog::query()->exists());
        $sale = PosSale::first();
        $this->assertNotNull($sale->invoice->fresh()->qr_code);

        $response = $this->actingAs($owner)->get(route('app.pos.sales.show', $sale));
        $response->assertOk();
        $response->assertSee('data:image/png;base64,', false);
    }

    public function test_a_zatca_cleared_sale_cannot_be_silently_voided(): void
    {
        Http::fake(['*' => Http::response(['reportingStatus' => 'REPORTED'], 200)]);

        [, $owner, $register, $item] = $this->makeShopOwner(onboarded: true);
        $this->checkout($owner, $register, $item);
        $sale = PosSale::first();

        $response = $this->actingAs($owner)->post(route('app.pos.sales.void', $sale), [
            'void_reason' => 'Customer changed their mind',
        ]);

        $response->assertSessionHasErrors();
        $sale->refresh();
        $this->assertSame('completed', $sale->status);
    }
}
