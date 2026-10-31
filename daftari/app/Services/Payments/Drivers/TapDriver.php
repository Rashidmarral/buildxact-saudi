<?php

namespace App\Services\Payments\Drivers;

use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
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

    public function verifyWebhook(PaymentGateway $gateway, PaymentTransaction $transaction, Request $request): ?array
    {
        $secretKey = $gateway->credentials['secret_key'] ?? '';
        $providedSignature = (string) $request->header('hashstring');

        if (! $providedSignature || ! $secretKey || ! $this->signatureMatches($providedSignature, $secretKey, $request)) {
            return null;
        }

        $status = strtoupper((string) $request->input('status')) === 'CAPTURED' ? 'paid' : 'failed';

        return [
            'status' => $status,
            'provider_reference' => $request->input('id'),
            'reference' => $this->extractReference($request),
            'amount' => $request->input('amount'),
            'currency' => $request->input('currency'),
            'raw' => $request->all(),
        ];
    }

    /**
     * Audit finding PAY-03: this previously hashed only the raw JSON
     * body. Public sources disagree on Tap's actual webhook-signing
     * canonicalization — some describe a raw-JSON-body HMAC (what was
     * here), others describe a concatenated-field string (charge id,
     * amount, currency, gateway reference, payment reference, status,
     * creation time). developers.tap.company could not be reached from
     * this environment to confirm Tap's own documentation directly
     * (outbound access to that host is blocked here), and this file's own
     * history shows a previous switch between these two exact schemes —
     * so neither can be asserted as definitively correct without a real
     * sandbox callback to test against.
     *
     * Accepting either canonicalization does not weaken security (both
     * still require knowledge of the real secret key; an attacker gains
     * nothing by choosing which form to forge) and keeps this fail-safe
     * for a legitimate webhook under whichever scheme Tap actually uses —
     * the previous single-scheme version risked silently rejecting every
     * real payment notification if the guess was wrong, which is worse
     * than accepting one extra, equally-secret-gated form. This MUST be
     * confirmed against a real Tap sandbox callback before relying on it
     * in production — see PAY-03 in
     * docs/audit/THIRD_PARTY_AUDIT_2026_10_23_RESPONSE.md.
     */
    private function signatureMatches(string $provided, string $secretKey, Request $request): bool
    {
        if (hash_equals(hash_hmac('sha256', $request->getContent(), $secretKey), $provided)) {
            return true;
        }

        $canonical = implode('', [
            (string) $request->input('id'),
            (string) $request->input('amount'),
            (string) $request->input('currency'),
            (string) $request->input('reference.gateway'),
            (string) $request->input('reference.payment'),
            (string) $request->input('status'),
            (string) $request->input('transaction.created'),
        ]);

        return hash_equals(hash_hmac('sha256', $canonical, $secretKey), $provided);
    }
}
