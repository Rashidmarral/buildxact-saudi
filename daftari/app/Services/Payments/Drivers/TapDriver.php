<?php

namespace App\Services\Payments\Drivers;

use App\Models\PaymentGateway;
use App\Services\Payments\PaymentGatewayDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Tap Payments (developers.tap.company) — GCC gateway supporting mada,
 * Visa/Mastercard, Apple Pay, and STC Pay through a single "charge" with
 * source.id = "src_all" (Tap's unified hosted checkout picks the method).
 *
 * Webhook signature: Tap sends an HMAC-SHA256 of the raw JSON request body
 * (signed with the account's secret key) in a "hashstring" header —
 * confirmed against Tap's own webhook documentation, not the field-
 * concatenation scheme an earlier version of this file guessed at.
 *
 * NOTE: written from public documentation without a live sandbox account —
 * double-check the checkout-creation field names against
 * https://developers.tap.company/ before processing real payments.
 */
class TapDriver implements PaymentGatewayDriver
{
    private const BASE_URL = 'https://api.tap.company/v2';

    public function createCheckout(PaymentGateway $gateway, array $params): array
    {
        $secretKey = $gateway->credentials['secret_key'] ?? '';

        $response = Http::withToken($secretKey)
            ->timeout(20)
            ->post(self::BASE_URL.'/charges', [
                'amount' => $params['amount'],
                'currency' => $params['currency'],
                'customer' => [
                    'first_name' => $params['customer_name'],
                    'email' => $params['customer_email'],
                    'phone' => ! empty($params['customer_phone']) ? [
                        'country_code' => '966',
                        'number' => $params['customer_phone'],
                    ] : null,
                ],
                'source' => ['id' => 'src_all'],
                'redirect' => ['url' => $params['return_url']],
                'post' => ['url' => $params['webhook_url']],
                'reference' => ['order' => $params['reference']],
                'description' => $params['description'],
                'metadata' => ['reference' => $params['reference']],
            ])
            ->throw()
            ->json();

        return [
            'checkout_url' => $response['transaction']['url'] ?? null,
            'provider_reference' => $response['id'] ?? null,
            'raw' => $response,
        ];
    }

    public function extractReference(Request $request): ?string
    {
        return $request->input('metadata.reference')
            ?? $request->input('reference.order');
    }

    public function verifyWebhook(PaymentGateway $gateway, Request $request): ?array
    {
        $secretKey = $gateway->credentials['secret_key'] ?? '';
        $providedSignature = $request->header('hashstring');

        $expected = hash_hmac('sha256', $request->getContent(), $secretKey);

        if (! $providedSignature || ! hash_equals($expected, (string) $providedSignature)) {
            return null;
        }

        $status = strtoupper((string) $request->input('status')) === 'CAPTURED' ? 'paid' : 'failed';

        return [
            'status' => $status,
            'provider_reference' => $request->input('id'),
            'reference' => $this->extractReference($request),
            'raw' => $request->all(),
        ];
    }
}
