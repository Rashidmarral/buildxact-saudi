<?php

namespace Tests\Feature;

use App\Jobs\SendWebhookDelivery;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayWebhookEvent;
use App\Models\PaymentTransaction;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Third-party audit findings PAY-01 through PAY-04 and SEC-05: payment
 * webhook settlement was not atomic/idempotent, HyperPay's status check
 * wasn't bound to the checkout it claimed to be for, Tap's signature
 * hashed the wrong canonical form, gateway settlement never verified the
 * paid amount/currency, and the webhook-URL SSRF guard only ran once at
 * save time. See docs/audit/THIRD_PARTY_AUDIT_2026_10_23_RESPONSE.md for
 * the full write-up of each fix.
 */
class PaymentWebhookIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Payments Co.', 'slug' => 'payments-co-'.uniqid(), 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeInvoice(Company $company, float $total = 100.0): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => $total, 'vat_total' => 0, 'total' => $total,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Item', 'quantity' => 1, 'unit_price' => $total, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => $total]);

        return $invoice;
    }

    private function makeGateway(Company $company, string $provider, array $credentials): PaymentGateway
    {
        return PaymentGateway::create([
            'company_id' => $company->id, 'provider' => $provider, 'mode' => 'test',
            'is_enabled' => true, 'credentials' => $credentials,
        ]);
    }

    private function makeTransaction(Company $company, PaymentGateway $gateway, Invoice $invoice, string $reference, float $amount): PaymentTransaction
    {
        return PaymentTransaction::create([
            'reference' => $reference, 'company_id' => $company->id, 'payment_gateway_id' => $gateway->id,
            'provider' => $gateway->provider, 'payable_type' => Invoice::class, 'payable_id' => $invoice->id,
            'amount' => $amount, 'currency' => $company->currency, 'status' => 'pending',
        ]);
    }

    // --- PAY-01: atomic, idempotent settlement -----------------------

    public function test_a_tap_payment_settles_the_invoice_exactly_once(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company, 100);
        $gateway = $this->makeGateway($company, 'tap', ['secret_key' => 'tap-secret']);
        $transaction = $this->makeTransaction($company, $gateway, $invoice, 'ref-tap-1', 100);

        $body = json_encode([
            'id' => 'chg_123', 'status' => 'CAPTURED', 'amount' => 100, 'currency' => 'SAR',
            'reference' => ['order' => 'ref-tap-1'], 'metadata' => ['reference' => 'ref-tap-1'],
        ]);
        $signature = hash_hmac('sha256', $body, 'tap-secret');

        $this->call('POST', route('payments.webhook', ['provider' => 'tap']), [], [], [], [
            'HTTP_hashstring' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $transaction->refresh();
        $this->assertSame('paid', $transaction->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, InvoicePayment::where('payment_transaction_id', $transaction->id)->count());
    }

    public function test_a_duplicate_webhook_delivery_never_double_settles(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company, 100);
        $gateway = $this->makeGateway($company, 'tap', ['secret_key' => 'tap-secret']);
        $transaction = $this->makeTransaction($company, $gateway, $invoice, 'ref-tap-2', 100);

        $body = json_encode([
            'id' => 'chg_124', 'status' => 'CAPTURED', 'amount' => 100, 'currency' => 'SAR',
            'reference' => ['order' => 'ref-tap-2'], 'metadata' => ['reference' => 'ref-tap-2'],
        ]);
        $signature = hash_hmac('sha256', $body, 'tap-secret');
        $headers = ['HTTP_hashstring' => $signature, 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', route('payments.webhook', ['provider' => 'tap']), [], [], [], $headers, $body)->assertOk();
        $this->call('POST', route('payments.webhook', ['provider' => 'tap']), [], [], [], $headers, $body)->assertOk();

        $this->assertSame(1, InvoicePayment::where('invoice_id', $invoice->id)->count());
        $this->assertSame('duplicate', PaymentGatewayWebhookEvent::latest('id')->first()->status);
    }

    // --- PAY-03: Tap signature canonicalization ------------------------

    public function test_a_tap_webhook_signed_with_the_field_concatenation_scheme_is_still_accepted(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company, 100);
        $gateway = $this->makeGateway($company, 'tap', ['secret_key' => 'tap-secret']);
        $transaction = $this->makeTransaction($company, $gateway, $invoice, 'ref-tap-3', 100);

        $payload = [
            'id' => 'chg_125', 'status' => 'CAPTURED', 'amount' => 100, 'currency' => 'SAR',
            'reference' => ['order' => 'ref-tap-3', 'gateway' => 'gw_1', 'payment' => 'pay_1'],
            'metadata' => ['reference' => 'ref-tap-3'],
            'transaction' => ['created' => '2026-10-25T00:00:00'],
        ];
        $body = json_encode($payload);
        $canonical = $payload['id'].$payload['amount'].$payload['currency'].$payload['reference']['gateway'].$payload['reference']['payment'].$payload['status'].$payload['transaction']['created'];
        $signature = hash_hmac('sha256', $canonical, 'tap-secret');

        $this->call('POST', route('payments.webhook', ['provider' => 'tap']), [], [], [], [
            'HTTP_hashstring' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $this->assertSame('paid', $transaction->fresh()->status);
    }

    public function test_a_tap_webhook_with_neither_valid_signature_is_rejected(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company, 100);
        $gateway = $this->makeGateway($company, 'tap', ['secret_key' => 'tap-secret']);
        $transaction = $this->makeTransaction($company, $gateway, $invoice, 'ref-tap-4', 100);

        $body = json_encode(['id' => 'chg_126', 'status' => 'CAPTURED', 'reference' => ['order' => 'ref-tap-4']]);

        $this->call('POST', route('payments.webhook', ['provider' => 'tap']), [], [], [], [
            'HTTP_hashstring' => 'not-a-real-signature', 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(400);

        $this->assertSame('pending', $transaction->fresh()->status);
    }

    // --- PAY-04: amount/currency must match -----------------------------

    public function test_a_paid_result_with_a_mismatched_amount_is_rejected_and_never_settled(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company, 100);
        $gateway = $this->makeGateway($company, 'tap', ['secret_key' => 'tap-secret']);
        $transaction = $this->makeTransaction($company, $gateway, $invoice, 'ref-tap-5', 100);

        // A genuinely, correctly signed webhook — but for 1 SAR, not the
        // 100 SAR this transaction actually expects.
        $body = json_encode([
            'id' => 'chg_127', 'status' => 'CAPTURED', 'amount' => 1, 'currency' => 'SAR',
            'reference' => ['order' => 'ref-tap-5'], 'metadata' => ['reference' => 'ref-tap-5'],
        ]);
        $signature = hash_hmac('sha256', $body, 'tap-secret');

        $this->call('POST', route('payments.webhook', ['provider' => 'tap']), [], [], [], [
            'HTTP_hashstring' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(400);

        $transaction->refresh();
        $this->assertSame('pending', $transaction->status);
        $this->assertSame(0, InvoicePayment::where('invoice_id', $invoice->id)->count());
    }

    public function test_settling_the_same_transaction_twice_directly_never_creates_a_second_invoice_payment(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company, 100);
        $gateway = $this->makeGateway($company, 'tap', ['secret_key' => 'tap-secret']);
        $transaction = $this->makeTransaction($company, $gateway, $invoice, 'ref-direct-1', 100);
        $transaction->update(['status' => 'paid']);

        $service = app(PaymentSettlementService::class);
        $service->settle($transaction);
        $service->settle($transaction);

        $this->assertSame(1, InvoicePayment::where('payment_transaction_id', $transaction->id)->count());
    }

    // --- PAY-02: HyperPay status must belong to the claimed checkout ---

    public function test_hyperpay_rejects_a_status_result_for_a_different_transactions_reference(): void
    {
        $company = $this->makeCompany();
        $invoiceA = $this->makeInvoice($company, 100);
        $invoiceB = $this->makeInvoice($company, 5000);
        $gateway = $this->makeGateway($company, 'hyperpay', ['access_token' => 'tok', 'entity_id' => 'ent']);
        $this->makeTransaction($company, $gateway, $invoiceA, 'ref-A', 100);
        $transactionB = $this->makeTransaction($company, $gateway, $invoiceB, 'ref-B', 5000);

        // The resourcePath genuinely belongs to, and HyperPay confirms
        // payment for, ref-A (a real, cheap, legitimately-paid checkout).
        Http::fake([
            '*/resourcepath/A*' => Http::response([
                'id' => 'hp_A', 'merchantTransactionId' => 'ref-A',
                'result' => ['code' => '000.000.000'], 'amount' => '100.00', 'currency' => 'SAR',
            ], 200),
        ]);

        // An attacker submits that real resourcePath but paired with
        // ref-B's reference, trying to get the expensive invoice credited
        // for a cheap, unrelated payment.
        $this->get(route('payments.webhook', ['provider' => 'hyperpay']).'?reference=ref-B&resourcePath=/resourcepath/A')
            ->assertStatus(400);

        $this->assertSame('pending', $transactionB->fresh()->status, 'A resourcePath belonging to a different transaction must not settle this one.');
    }

    public function test_hyperpay_accepts_a_status_result_for_its_own_reference(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company, 100);
        $gateway = $this->makeGateway($company, 'hyperpay', ['access_token' => 'tok', 'entity_id' => 'ent']);
        $transaction = $this->makeTransaction($company, $gateway, $invoice, 'ref-A', 100);

        Http::fake([
            '*/resourcepath/A*' => Http::response([
                'id' => 'hp_A', 'merchantTransactionId' => 'ref-A',
                'result' => ['code' => '000.000.000'], 'amount' => '100.00', 'currency' => 'SAR',
            ], 200),
        ]);

        $this->get(route('payments.webhook', ['provider' => 'hyperpay']).'?reference=ref-A&resourcePath=/resourcepath/A')
            ->assertRedirect();

        $this->assertSame('paid', $transaction->fresh()->status);
    }

    // --- PAY-04: Moyasar trusts the authoritative fetch, not the body --

    public function test_moyasar_settles_based_on_the_fetched_invoice_not_the_webhook_body(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company, 100);
        $gateway = $this->makeGateway($company, 'moyasar', ['webhook_secret' => 'whsec', 'secret_key' => 'sk_test']);
        $transaction = $this->makeTransaction($company, $gateway, $invoice, 'ref-moy-1', 100);

        Http::fake([
            '*/invoices/inv_1' => Http::response(['id' => 'inv_1', 'status' => 'paid', 'amount' => 10000, 'currency' => 'SAR'], 200),
        ]);

        // The body itself claims 'initiated' (not paid) — the fix must
        // ignore this and trust only the fetched invoice.
        $response = $this->postJson(route('payments.webhook', ['provider' => 'moyasar']), [
            'secret_token' => 'whsec',
            'data' => ['id' => 'inv_1', 'status' => 'initiated', 'metadata' => ['reference' => 'ref-moy-1']],
        ]);

        $response->assertOk();
        $this->assertSame('paid', $transaction->fresh()->status);
    }

    public function test_moyasar_never_persists_the_shared_secret_in_stored_payloads(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company, 100);
        $gateway = $this->makeGateway($company, 'moyasar', ['webhook_secret' => 'whsec', 'secret_key' => 'sk_test']);
        $this->makeTransaction($company, $gateway, $invoice, 'ref-moy-2', 100);

        Http::fake([
            '*/invoices/inv_2' => Http::response(['id' => 'inv_2', 'status' => 'paid', 'amount' => 10000, 'currency' => 'SAR'], 200),
        ]);

        $this->postJson(route('payments.webhook', ['provider' => 'moyasar']), [
            'secret_token' => 'whsec',
            'data' => ['id' => 'inv_2', 'status' => 'paid', 'metadata' => ['reference' => 'ref-moy-2'], 'secret_token' => 'whsec'],
        ])->assertOk();

        $event = PaymentGatewayWebhookEvent::where('status', 'processed')->latest('id')->first();
        $this->assertStringNotContainsString('whsec', json_encode($event->payload));
    }

    // --- SEC-05: webhook-URL SSRF re-validated at delivery time --------

    public function test_a_webhook_delivery_is_blocked_if_the_url_now_resolves_to_a_private_address(): void
    {
        $company = $this->makeCompany();
        $webhook = Webhook::create([
            'company_id' => $company->id, 'url' => 'http://127.0.0.1:9999/hook',
            'secret' => 'whsecret', 'events' => ['invoice.paid'], 'is_active' => true,
        ]);

        Http::fake();

        (new SendWebhookDelivery($webhook->id, 'invoice.paid', ['id' => 1]))->handle();

        Http::assertNothingSent();
        $delivery = WebhookDelivery::where('webhook_id', $webhook->id)->first();
        $this->assertNotNull($delivery);
        $this->assertFalse($delivery->success);
        $this->assertStringContainsString('no longer resolves to a public address', $delivery->error);
    }

    public function test_a_webhook_delivery_to_a_safe_url_still_sends_normally(): void
    {
        // A public IP literal, not a hostname — PublicHttpUrl::isSafe()
        // skips DNS resolution entirely for an IP literal (see its own
        // filter_var() check), so this doesn't depend on this sandbox's
        // outbound DNS actually working for an arbitrary external host.
        $company = $this->makeCompany();
        $webhook = Webhook::create([
            'company_id' => $company->id, 'url' => 'http://93.184.216.34/hook',
            'secret' => 'whsecret', 'events' => ['invoice.paid'], 'is_active' => true,
        ]);

        Http::fake(['93.184.216.34/*' => Http::response('ok', 200)]);

        (new SendWebhookDelivery($webhook->id, 'invoice.paid', ['id' => 1]))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'http://93.184.216.34/hook');
        $this->assertTrue(WebhookDelivery::where('webhook_id', $webhook->id)->first()->success);
    }
}
