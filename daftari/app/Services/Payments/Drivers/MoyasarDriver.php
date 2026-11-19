<?php

namespace App\Services\Payments\Drivers;

use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentGatewayDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Moyasar (docs.moyasar.com) — Saudi gateway supporting mada, Visa/
 * Mastercard, Apple Pay, and STC Pay. Uses the Invoice API for a fully
 * hosted checkout page (no card form of our own, no PCI scope).
 *
 * Webhook authentication: confirmed against Moyasar's own webhook
 * documentation — unlike the other three drivers, Moyasar does not sign
 * the payload with an HMAC; it echoes a shared secret you configure back
 * inside the request body ("secret_token"), and a valid match just proves
 * the sender knows that secret (it does not prove the body is unmodified
 * in transit, since nothing actually signs it). This is Moyasar's real
 * mechanism, not a limitation of this implementation.
 *
 * NOTE: written from public documentation without a live sandbox account —
 * the endpoint, amount-in-halalas, and callback_url fields are confirmed
 * against Moyasar's own PHP SDK docs; double-check before going live.
 */
class MoyasarDriver implements PaymentGatewayDriver
{
    private const BASE_URL = 'https://api.moyasar.com/v1';

    public function createCheckout(PaymentGateway $gateway, array $params): array
    {
        $secretKey = $gateway->credentials['secret_key'] ?? '';

        $response = Http::withBasicAuth($secretKey, '')
            ->timeout(20)
            ->post(self::BASE_URL.'/invoices', [
                'amount' => (int) round($params['amount'] * 100), // halalas
                'currency' => $params['currency'],
                'description' => $params['description'],
                'callback_url' => $params['webhook_url'],
                'success_url' => $params['return_url'],
                'back_url' => $params['return_url'],
                'metadata' => ['reference' => $params['reference']],
            ])
            ->throw()
            ->json();

        return [
            'checkout_url' => $response['url'] ?? null,
            'provider_reference' => $response['id'] ?? null,
            'raw' => $response,
        ];
    }

    public function extractReference(Request $request): ?string
    {
        return $request->input('data.metadata.reference')
            ?? $request->input('metadata.reference');
    }

    /**
     * Audit finding PAY-04: the shared secret_token only proves the
     * sender knows that one static value — it doesn't sign or otherwise
     * authenticate the payload's content (amount/currency/status weren't
     * checked against anything at all), and the previous code also stored
     * that same secret in plaintext, unredacted, inside every webhook
     * event's logged payload. Fixed two ways: (1) once the shared secret
     * matches, the webhook's own `id` (the invoice id Moyasar assigned —
     * not an amount or status) is used only as a lookup key to fetch the
     * authoritative invoice via a real, secret-key-authenticated GET
     * request; the caller settles based on THAT response, never on
     * anything from the unauthenticated webhook body. (2) the stored
     * `raw` payload has secret_token stripped before being returned, so
     * it never lands in PaymentGatewayWebhookEvent/PaymentTransaction
     * storage.
     */
    public function verifyWebhook(PaymentGateway $gateway, PaymentTransaction $transaction, Request $request): ?array
    {
        $configuredSecret = $gateway->credentials['webhook_secret'] ?? null;
        $providedSecret = $request->input('secret_token');

        if (! $configuredSecret || ! $providedSecret || ! hash_equals((string) $configuredSecret, (string) $providedSecret)) {
            return null;
        }

        $payload = $request->input('data', $request->all());
        $invoiceId = $payload['id'] ?? null;

        if (! $invoiceId) {
            return null;
        }

        $secretKey = $gateway->credentials['secret_key'] ?? '';

        $invoice = Http::withBasicAuth($secretKey, '')
            ->timeout(20)
            ->get(self::BASE_URL.'/invoices/'.$invoiceId)
            ->throw()
            ->json();

        $status = ($invoice['status'] ?? null) === 'paid' ? 'paid' : 'failed';

        $redacted = $request->except('secret_token');
        if (isset($redacted['data']['secret_token'])) {
            $redacted['data']['secret_token'] = '[redacted]';
        }

        return [
            'status' => $status,
            'provider_reference' => $invoice['id'] ?? null,
            'reference' => $this->extractReference($request),
            'amount' => isset($invoice['amount']) ? $invoice['amount'] / 100 : null,
            'currency' => $invoice['currency'] ?? null,
            'raw' => $redacted,
        ];
    }
}
