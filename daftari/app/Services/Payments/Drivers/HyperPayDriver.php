<?php

namespace App\Services\Payments\Drivers;

use App\Models\PaymentGateway;
use App\Services\Payments\PaymentGatewayDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * HyperPay (hyperpay.com) — MENA gateway supporting mada, Visa/Mastercard,
 * Apple Pay, and STC Pay via its COPYandPAY widget. Unlike the other three
 * drivers, HyperPay's card entry happens inside HyperPay's own hosted
 * iframe widget embedded on OUR page (routes/checkout widget view), not on
 * a fully external hosted page — so createCheckout() returns an internal
 * route (PaymentWidgetController) rather than a HyperPay URL directly.
 * Confirmation happens synchronously: the widget redirects the browser
 * back to our webhook URL with a resourcePath, which we use to fetch the
 * authoritative payment status server-to-server (HyperPay's documented
 * pattern) rather than trusting anything from the redirect itself.
 *
 * NOTE: written from public documentation without a live sandbox account —
 * verify field names/result-code families against
 * https://www.hyperpay.com/integration-guide/ before processing real
 * payments.
 */
class HyperPayDriver implements PaymentGatewayDriver
{
    private function baseUrl(PaymentGateway $gateway): string
    {
        return $gateway->mode === 'live'
            ? 'https://eu-prod.oppwa.com'
            : 'https://eu-test.oppwa.com';
    }

    public function createCheckout(PaymentGateway $gateway, array $params): array
    {
        $accessToken = $gateway->credentials['access_token'] ?? '';
        $entityId = $gateway->credentials['entity_id'] ?? '';

        $response = Http::withToken($accessToken)
            ->asForm()
            ->timeout(20)
            ->post($this->baseUrl($gateway).'/v1/checkouts', [
                'entityId' => $entityId,
                'amount' => number_format($params['amount'], 2, '.', ''),
                'currency' => $params['currency'],
                'paymentType' => 'DB',
                'merchantTransactionId' => $params['reference'],
                'customer.email' => $params['customer_email'],
                // The widget redirects here after the shopper completes
                // payment; we append the reference so the return handler
                // can find the right PaymentTransaction before it even
                // looks at resourcePath.
                'shopperResultUrl' => $params['webhook_url'].(str_contains($params['webhook_url'], '?') ? '&' : '?').'reference='.$params['reference'],
            ])
            ->throw()
            ->json();

        return [
            'checkout_url' => route('payments.widget', [
                'provider' => 'hyperpay',
                'reference' => $params['reference'],
            ]),
            'provider_reference' => $response['id'] ?? null,
            'raw' => $response,
        ];
    }

    public function extractReference(Request $request): ?string
    {
        return $request->query('reference') ?? $request->input('reference');
    }

    /**
     * Called for the GET redirect HyperPay's widget sends the browser back
     * to (shopperResultUrl). We don't trust the redirect itself — we call
     * HyperPay's status endpoint server-to-server using the resourcePath
     * it gave us, which only a real HyperPay checkout could have produced.
     */
    public function verifyWebhook(PaymentGateway $gateway, Request $request): ?array
    {
        $resourcePath = $request->query('resourcePath');

        if (! $resourcePath) {
            return null;
        }

        $accessToken = $gateway->credentials['access_token'] ?? '';
        $entityId = $gateway->credentials['entity_id'] ?? '';

        $response = Http::withToken($accessToken)
            ->timeout(20)
            ->get($this->baseUrl($gateway).$resourcePath, ['entityId' => $entityId])
            ->throw()
            ->json();

        $code = (string) ($response['result']['code'] ?? '');
        // HyperPay's documented "successful" result-code families.
        $success = (bool) preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $code);

        return [
            'status' => $success ? 'paid' : 'failed',
            'provider_reference' => $response['id'] ?? null,
            'reference' => $this->extractReference($request),
            'raw' => $response,
        ];
    }
}
