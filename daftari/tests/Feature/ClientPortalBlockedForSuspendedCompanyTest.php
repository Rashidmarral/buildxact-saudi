<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding M-24: EnsureClientPortalSession resolved
 * portal_client_id with an unscoped Client::find() and no further check —
 * safe today only because the one place that sets it validated a real
 * magic-link token first, but it also meant a suspended/cancelled
 * company's clients could keep browsing their invoice history in the
 * self-service portal, a surface D-0's suspension enforcement never
 * reached.
 */
class ClientPortalBlockedForSuspendedCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_of_a_manually_suspended_company_is_logged_out_of_the_portal(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'suspended']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);

        $response = $this->withSession(['portal_client_id' => $client->id])->get(route('portal.dashboard'));

        $response->assertRedirect(route('portal.login'));
        $this->assertNull(session('portal_client_id'));
    }

    public function test_a_client_of_a_company_with_an_expired_subscription_is_logged_out_of_the_portal(): void
    {
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true]);
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active']);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'expired',
            'billing_cycle' => 'monthly', 'current_period_start' => now()->subMonths(2), 'current_period_end' => now()->subMonth(),
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);

        $response = $this->withSession(['portal_client_id' => $client->id])->get(route('portal.dashboard'));

        $response->assertRedirect(route('portal.login'));
    }

    public function test_a_client_of_an_operational_company_can_still_use_the_portal(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);

        $response = $this->withSession(['portal_client_id' => $client->id])->get(route('portal.dashboard'));

        $response->assertOk();
    }
}
