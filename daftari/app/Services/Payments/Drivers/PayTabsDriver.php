<?php

namespace App\Services\Payments\Drivers;

use App\Models\PaymentGateway;
use App\Services\Payments\PaymentGatewayDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * PayTabs (docs.paytabs.com) — Saudi/MENA gateway supporting mada, Visa/
 * Mastercard, Apple Pay, and STC Pay through its Hosted Payment Page.
 *
 * NOTE: written from public documentation without a live sandbox account —
 * verify field names against https://docs.paytabs.com/ before processing
 * real payments. The IPN signature scheme (HMAC-SHA256 of the raw JSON
 * body using the profile's Server Key, sent in a "Signature" header) is
 * confirmed from PayTabs' support documentation.
 */
class PayTabsDriver implements PaymentGatewayDriver
{
    public function createCheckout(PaymentGateway $gateway, array $params): array
    {
        $serverKey = $gateway->credentials['server_key'] ?? '';
        $profileId = $gateway->credentials['profile_id'] ?? '';
        $region = $gateway->credentials['region'] ?? 'sa';

        $response = Http::withHeaders(['Authorization' => $serverKey])
            ->timeout(20)
            ->post("https://secure.paytabs.{$region}/payment/request", [
                'profile_id' => $profileId,
                'tran_type' => 'sale',
                'tran_class' => 'ecom',
                'cart_id' => $params['reference'],
                'cart_currency' => $params['currency'],
                'cart_amount' => $params['amount'],
                'cart_description' => $params['description'],
                'paypage_lang' => 'en',
                'customer_details' => [
                    'name' => $params['customer_name'],
                    'email' => $params['customer_email'],
                    'phone' => $params['customer_phone'],
                    'country' => 'SA',
                ],
                'callback' => $params['webhook_url'],
                'return' => $params['return_url'],
            ])
            ->throw()
            ->json();

        return [
            'checkout_url' => $response['redirect_url'] ?? null,
            'provider_reference' => $response['tran_ref'] ?? null,
            'raw' => $response,
        ];
    }

    public function extractReference(Request $request): ?string
    {
        return $request->input('cart_id');
    }

    public function verifyWebhook(PaymentGateway $gateway, Request $request): ?array
    {
        $serverKey = $gateway->credentials['server_key'] ?? '';
        $providedSignature = $request->header('Signature');

        $expected = hash_hmac('sha256', $request->getContent(), $serverKey);

        if (! $providedSignature || ! hash_equals($expected, (string) $providedSignature)) {
            return null;
        }

        $respStatus = (string) $request->input('payment_result.response_status');
        $status = $respStatus === 'A' ? 'paid' : 'failed'; // "A" = authorized/success in PayTabs' response_status codes

        return [
            'status' => $status,
            'provider_reference' => $request->input('tran_ref'),
            'reference' => $this->extractReference($request),
            'raw' => $request->all(),
        ];
    }
}
