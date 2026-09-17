<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug report: visiting Settings > Payment gateways 500'd with "Undefined
 * array key bank_transfer" whenever the platform admin had enabled bank
 * transfer (PaymentGateway::BANK_TRANSFER) — the company-facing view's
 * $labels array only ever listed the four real online-checkout providers
 * (moyasar/hyperpay/tap/paytabs), even though the controller's query never
 * filtered bank_transfer out of $availableProviders. The admin-facing
 * equivalent page already handled bank_transfer correctly (it's a
 * deliberate on/off-only "gateway" per PaymentGateway::credentialRulesFor()'s
 * docblock — no test/live mode, no credential fields at company level,
 * since companies manage their own bank accounts under Cash & Banks).
 */
class CompanyBankTransferGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Gateway Co.', 'slug' => 'gateway-'.uniqid()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_page_renders_when_the_platform_has_enabled_bank_transfer(): void
    {
        PaymentGateway::create(['company_id' => null, 'provider' => PaymentGateway::BANK_TRANSFER, 'mode' => 'live', 'is_enabled' => true]);
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.settings.payment-gateways'));

        $response->assertOk();
        $response->assertSee(__('Bank transfer (offline)'));
    }

    public function test_a_company_can_enable_bank_transfer_without_submitting_a_mode(): void
    {
        PaymentGateway::create(['company_id' => null, 'provider' => PaymentGateway::BANK_TRANSFER, 'mode' => 'live', 'is_enabled' => true]);
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.settings.payment-gateways.update', PaymentGateway::BANK_TRANSFER), [
            'is_enabled' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $gateway = PaymentGateway::where('company_id', $owner->company_id)->where('provider', PaymentGateway::BANK_TRANSFER)->first();
        $this->assertNotNull($gateway);
        $this->assertTrue($gateway->is_enabled);
    }

    public function test_a_real_gateway_still_requires_a_mode(): void
    {
        PaymentGateway::create(['company_id' => null, 'provider' => 'moyasar', 'mode' => 'live', 'is_enabled' => true]);
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.settings.payment-gateways.update', 'moyasar'), [
            'is_enabled' => '1',
            'secret_key' => 'sk_test_123',
        ]);

        $response->assertSessionHasErrors('mode');
    }
}
