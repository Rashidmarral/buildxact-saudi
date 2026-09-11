<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

interface PaymentGatewayDriver
{
    /**
     * Pull OUR OWN reference (the PaymentTransaction UUID we sent when
     * creating the checkout) back out of an unverified webhook payload, so
     * the caller can look up which PaymentGateway — and therefore which
     * secret — to verify the signature against. Safe to do before
     * verification: we only use this as a lookup key, never as something
     * we act on, so a forged payload can at worst cause a failed signature
     * check against the wrong/right gateway, never a bypass.
     */
    public function extractReference(Request $request): ?string;

    /**
     * Start a hosted checkout for $transaction and return the URL to send
     * the payer to, plus whatever provider-side reference id we should
     * remember for status lookups.
     *
     * $params keys: amount (float), currency (string), description,
     * customer_name, customer_email, customer_phone, return_url,
     * webhook_url, reference (our own PaymentTransaction UUID — always
     * round-tripped back to us in the webhook so we can resolve which
     * gateway/company it belongs to before trusting anything else).
     *
     * @return array{checkout_url: string, provider_reference: ?string, raw: array}
     */
    public function createCheckout(PaymentGateway $gateway, array $params): array;

    /**
     * Verify the incoming webhook actually came from this provider using
     * $gateway's own secret, then return a normalized result. Return null
     * if the signature is invalid or the payload isn't a recognized event
     * — the caller must never act on an unverified payload.
     *
     * @return array{status: string, provider_reference: ?string, reference: ?string, raw: array}|null
     */
    public function verifyWebhook(PaymentGateway $gateway, Request $request): ?array;
}
