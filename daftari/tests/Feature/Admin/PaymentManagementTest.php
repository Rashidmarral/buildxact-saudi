<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayWebhookEvent;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Module 05 (Payment & Billing Management): the extended payment statuses,
 * the new admin Payments detail page and its actions (refund — full and
 * partial —, retry, mark manual payment, confirm bank transfer), and the
 * webhook endpoint's signature verification + duplicate-event protection +
 * event logging.
 */
class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ], $overrides));
    }

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active',
        ], $overrides));
    }

    private function withConfirmedPassword($user)
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    private function makePaidPayment(Company $company, Subscription $subscription, array $overrides = []): Payment
    {
        return $company->payments()->create(array_merge([
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'amount' => 100, 'currency' => 'SAR', 'status' => 'paid', 'method' => 'moyasar',
            'reference' => 'ref-'.uniqid(), 'paid_at' => now(),
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Index filters
    // ---------------------------------------------------------------

    public function test_index_filters_by_status_gateway_plan_and_amount(): void
    {
        $planA = $this->makePlan(['name' => 'Plan A']);
        $planB = $this->makePlan(['name' => 'Plan B']);
        $companyA = $this->makeCompany(['name' => 'Findable Co']);
        $companyB = $this->makeCompany(['name' => 'Other Co']);
        $subA = Subscription::create(['company_id' => $companyA->id, 'plan_id' => $planA->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);
        $subB = Subscription::create(['company_id' => $companyB->id, 'plan_id' => $planB->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $paid = $this->makePaidPayment($companyA, $subA, ['amount' => 50, 'method' => 'moyasar', 'plan_id' => $planA->id, 'reference' => 'REF-PAID-UNIQUE']);
        $failed = $companyB->payments()->create(['subscription_id' => $subB->id, 'plan_id' => $planB->id, 'amount' => 200, 'currency' => 'SAR', 'status' => 'failed', 'method' => 'tap', 'reference' => 'REF-FAILED-UNIQUE']);

        $admin = $this->makeAdmin();

        // The company name alone isn't a safe "absent" marker — the page's
        // "record manual payment" modal always lists every company by name
        // regardless of the table filters — so assert on each payment's
        // unique reference instead, which only ever appears in its own row.
        $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'failed']))
            ->assertSee('REF-FAILED-UNIQUE')->assertDontSee('REF-PAID-UNIQUE');

        $this->actingAs($admin)->get(route('admin.payments.index', ['gateway' => 'moyasar']))
            ->assertSee('REF-PAID-UNIQUE')->assertDontSee('REF-FAILED-UNIQUE');

        $this->actingAs($admin)->get(route('admin.payments.index', ['plan_id' => $planA->id]))
            ->assertSee('REF-PAID-UNIQUE')->assertDontSee('REF-FAILED-UNIQUE');

        $this->actingAs($admin)->get(route('admin.payments.index', ['amount_min' => 100]))
            ->assertSee('REF-FAILED-UNIQUE')->assertDontSee('REF-PAID-UNIQUE');

        $this->actingAs($admin)->get(route('admin.payments.index', ['q' => 'Findable']))
            ->assertSee('REF-PAID-UNIQUE')->assertDontSee('REF-FAILED-UNIQUE');
    }

    // ---------------------------------------------------------------
    // Show page
    // ---------------------------------------------------------------

    public function test_show_page_renders_all_required_sections(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'name' => 'Khalid Owner']);
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);
        $payment = $this->makePaidPayment($company, $subscription);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.payments.show', $payment));

        $response->assertOk()
            ->assertSee(__('Payment information'))
            ->assertSee(__('Gateway response'))
            ->assertSee(__('Related subscription'))
            ->assertSee(__('Related invoice'))
            ->assertSee(__('Timeline'))
            ->assertSee(__('Audit history'))
            ->assertSee('Khalid Owner');
    }

    public function test_show_page_exposes_the_linked_gateway_transaction_response(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);
        $gateway = PaymentGateway::create(['company_id' => null, 'provider' => 'moyasar', 'mode' => 'test', 'is_enabled' => true, 'credentials' => ['secret_key' => 'sk', 'webhook_secret' => 'whsec']]);
        $transaction = PaymentTransaction::create([
            'company_id' => null, 'payment_gateway_id' => $gateway->id, 'provider' => 'moyasar',
            'payable_type' => Subscription::class, 'payable_id' => $subscription->id,
            'amount' => 100, 'currency' => 'SAR', 'status' => 'paid',
            'raw_response' => ['url' => 'https://pay.example/checkout/123', 'id' => 'moy_1'],
        ]);
        $payment = $this->makePaidPayment($company, $subscription, ['payment_transaction_id' => $transaction->id]);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.payments.show', $payment));

        $response->assertOk()->assertSee('moy_1');
    }

    // ---------------------------------------------------------------
    // Refund — full and partial
    // ---------------------------------------------------------------

    public function test_full_refund_marks_the_payment_refunded(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);
        $payment = $this->makePaidPayment($company, $subscription, ['amount' => 100]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.payments.refund', $payment));

        $response->assertRedirect();
        $payment->refresh();
        $this->assertSame('refunded', $payment->status);
        $this->assertEquals(100, $payment->refunded_amount);

        $log = AuditLog::where('action', 'payment.refund')->latest('id')->first();
        $this->assertSame('paid', $log->old_value['status']);
        $this->assertSame('refunded', $log->new_value['status']);
    }

    public function test_partial_refund_tracks_remaining_balance_and_can_be_completed_later(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);
        $payment = $this->makePaidPayment($company, $subscription, ['amount' => 100]);
        $admin = $this->makeAdmin();

        $this->withConfirmedPassword($admin)
            ->post(route('admin.payments.refund', $payment), ['amount' => 30])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame('partially_refunded', $payment->status);
        $this->assertEquals(30, $payment->refunded_amount);
        $this->assertEquals(70, $payment->remainingRefundable());

        // Completing the refund later flips it to fully refunded.
        $this->withConfirmedPassword($admin)
            ->post(route('admin.payments.refund', $payment), ['amount' => 70])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame('refunded', $payment->status);
        $this->assertEquals(100, $payment->refunded_amount);
    }

    public function test_refund_rejects_an_amount_larger_than_the_remaining_balance(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);
        $payment = $this->makePaidPayment($company, $subscription, ['amount' => 100]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.payments.refund', $payment), ['amount' => 150]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame('paid', $payment->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Retry failed payment
    // ---------------------------------------------------------------

    public function test_retry_starts_a_new_checkout_for_a_failed_gateway_payment(): void
    {
        Http::fake([
            'api.moyasar.com/*' => Http::response(['id' => 'moy_new', 'url' => 'https://pay.example/checkout/new'], 200),
        ]);

        $plan = $this->makePlan();
        $company = $this->makeCompany();
        User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);
        PaymentGateway::create(['company_id' => null, 'provider' => 'moyasar', 'mode' => 'test', 'is_enabled' => true, 'credentials' => ['secret_key' => 'sk']]);
        $payment = $company->payments()->create(['subscription_id' => $subscription->id, 'plan_id' => $plan->id, 'amount' => 100, 'currency' => 'SAR', 'status' => 'failed', 'method' => 'moyasar']);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.payments.retry', $payment));

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', ['subscription_id' => $subscription->id, 'status' => 'pending', 'method' => 'moyasar']);

        $log = AuditLog::where('action', 'payment.retry')->latest('id')->first();
        $this->assertNotNull($log);
    }

    public function test_retry_refuses_a_bank_transfer_payment(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);
        $payment = $company->payments()->create(['subscription_id' => $subscription->id, 'plan_id' => $plan->id, 'amount' => 100, 'currency' => 'SAR', 'status' => 'failed', 'method' => 'bank_transfer']);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.payments.retry', $payment));

        $response->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Mark manual payment
    // ---------------------------------------------------------------

    public function test_admin_can_record_a_manual_payment_and_it_activates_a_pending_subscription(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'past_due', 'billing_cycle' => 'monthly', 'current_period_end' => now()->subDays(2)]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.payments.manual'), ['company_id' => $company->id, 'amount' => 100, 'reference' => 'CASH-001']);

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', ['company_id' => $company->id, 'status' => 'paid', 'method' => 'manual', 'reference' => 'CASH-001']);
        $this->assertSame('active', $subscription->fresh()->status);

        $log = AuditLog::where('action', 'payment.mark_manual')->latest('id')->first();
        $this->assertSame($company->id, $log->company_id);
    }

    // ---------------------------------------------------------------
    // Webhook: signature verification, duplicate protection, event logging
    // ---------------------------------------------------------------

    private function makeMoyasarSetup(): array
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'pending', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);
        $gateway = PaymentGateway::create(['company_id' => null, 'provider' => 'moyasar', 'mode' => 'test', 'is_enabled' => true, 'credentials' => ['secret_key' => 'sk', 'webhook_secret' => 'whsec-123']]);
        $transaction = PaymentTransaction::create([
            'company_id' => null, 'payment_gateway_id' => $gateway->id, 'provider' => 'moyasar',
            'payable_type' => Subscription::class, 'payable_id' => $subscription->id,
            'amount' => 100, 'currency' => 'SAR', 'status' => 'pending',
        ]);
        $payment = $company->payments()->create(['subscription_id' => $subscription->id, 'plan_id' => $plan->id, 'amount' => 100, 'currency' => 'SAR', 'status' => 'pending', 'method' => 'moyasar', 'payment_transaction_id' => $transaction->id]);

        return compact('plan', 'company', 'subscription', 'gateway', 'transaction', 'payment');
    }

    public function test_webhook_rejects_an_invalid_signature_and_logs_it(): void
    {
        ['transaction' => $transaction] = $this->makeMoyasarSetup();

        $response = $this->postJson('/payments/webhook/moyasar', [
            'secret_token' => 'wrong-secret',
            'data' => ['id' => 'moy_1', 'status' => 'paid', 'metadata' => ['reference' => $transaction->reference]],
        ]);

        $response->assertStatus(400);
        $this->assertSame('pending', $transaction->fresh()->status);

        $event = PaymentGatewayWebhookEvent::latest('id')->first();
        $this->assertSame('rejected', $event->status);
    }

    public function test_webhook_settles_the_subscription_and_payment_on_a_valid_paid_event(): void
    {
        ['transaction' => $transaction, 'subscription' => $subscription, 'payment' => $payment] = $this->makeMoyasarSetup();

        $response = $this->postJson('/payments/webhook/moyasar', [
            'secret_token' => 'whsec-123',
            'data' => ['id' => 'moy_1', 'status' => 'paid', 'metadata' => ['reference' => $transaction->reference]],
        ]);

        $response->assertOk();
        $this->assertSame('paid', $transaction->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);

        $event = PaymentGatewayWebhookEvent::latest('id')->first();
        $this->assertSame('processed', $event->status);
    }

    public function test_webhook_marks_the_payment_failed_on_a_failed_event(): void
    {
        ['transaction' => $transaction, 'payment' => $payment] = $this->makeMoyasarSetup();

        $this->postJson('/payments/webhook/moyasar', [
            'secret_token' => 'whsec-123',
            'data' => ['id' => 'moy_1', 'status' => 'failed', 'metadata' => ['reference' => $transaction->reference]],
        ])->assertOk();

        $this->assertSame('failed', $transaction->fresh()->status);
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_webhook_detects_and_logs_an_exact_duplicate_delivery_without_reprocessing(): void
    {
        ['transaction' => $transaction] = $this->makeMoyasarSetup();

        $body = [
            'secret_token' => 'whsec-123',
            'data' => ['id' => 'moy_1', 'status' => 'paid', 'metadata' => ['reference' => $transaction->reference]],
        ];

        $this->postJson('/payments/webhook/moyasar', $body)->assertOk();
        $firstProcessedCount = PaymentGatewayWebhookEvent::where('status', 'processed')->count();

        // Exact same delivery, replayed (gateway retry, or a captured
        // request replayed) — must be recognized as a duplicate and not
        // re-settle anything a second time.
        $this->postJson('/payments/webhook/moyasar', $body)->assertOk();

        $this->assertSame(1, $firstProcessedCount);
        $this->assertSame(1, PaymentGatewayWebhookEvent::where('status', 'processed')->count());
        $this->assertSame(1, PaymentGatewayWebhookEvent::where('status', 'duplicate')->count());
    }

    public function test_webhook_returns_not_found_for_an_unresolvable_reference_and_logs_it(): void
    {
        $this->makeMoyasarSetup();

        $response = $this->postJson('/payments/webhook/moyasar', [
            'secret_token' => 'whsec-123',
            'data' => ['id' => 'moy_1', 'status' => 'paid', 'metadata' => ['reference' => 'not-a-real-reference']],
        ]);

        $response->assertStatus(404);
        $event = PaymentGatewayWebhookEvent::latest('id')->first();
        $this->assertSame('rejected', $event->status);
        $this->assertNull($event->payment_transaction_id);
    }
}
