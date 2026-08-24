<?php

namespace App\Services\Payments\Drivers;

use App\Models\PaymentGateway;
use App\Services\Payments\PaymentGatewayDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Moyasar (docs.moyasar.com) — Saudi gateway supporting mada, Visa/
 * Mastercard, Apple Pay, and STC Pay. Uses the Invoice API for a fully
 * hosted checkout page (no card form of our own, no PCI scope).
 *
 * NOTE: field names below reflect Moyasar's documented API at the time
 * this was written from public documentation, without a live sandbox
 * account to test against — verify against https://docs.moyasar.com/
 * before processing real payments, in particular the exact webhook
 * signature-verification mechanism (Moyasar verifies via a shared secret
 * echoed back in the payload rather than an HMAC header; confirm this is
 * still current).
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

    public function verifyWebhook(PaymentGateway $gateway, Request $request): ?array
    {
        $configuredSecret = $gateway->credentials['webhook_secret'] ?? null;
        $providedSecret = $request->input('secret_token');

        if (! $configuredSecret || ! $providedSecret || ! hash_equals((string) $configuredSecret, (string) $providedSecret)) {
            return null;
        }

        $payload = $request->input('data', $request->all());
        $status = ($payload['status'] ?? null) === 'paid' ? 'paid' : 'failed';

        return [
            'status' => $status,
            'provider_reference' => $payload['id'] ?? null,
            'reference' => $this->extractReference($request),
            'raw' => $request->all(),
        ];
    }
}
