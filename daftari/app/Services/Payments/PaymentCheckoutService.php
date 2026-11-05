<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Model;

class PaymentCheckoutService
{
    /**
     * Creates a pending PaymentTransaction and starts a hosted checkout
     * for it against $gateway. $customer: ['name', 'email', 'phone'].
     */
    public function start(
        PaymentGateway $gateway,
        Model $payable,
        float $amount,
        string $currency,
        string $description,
        array $customer,
        string $returnUrl
    ): PaymentTransaction {
        $transaction = PaymentTransaction::create([
            'company_id' => $gateway->company_id,
            'payment_gateway_id' => $gateway->id,
            'provider' => $gateway->provider,
            'payable_type' => get_class($payable),
            'payable_id' => $payable->getKey(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
        ]);

        $driver = PaymentGatewayManager::driver($gateway->provider);

        $result = $driver->createCheckout($gateway, [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'customer_name' => $customer['name'] ?? '',
            'customer_email' => $customer['email'] ?? '',
            'customer_phone' => $customer['phone'] ?? '',
            'return_url' => $returnUrl,
            'webhook_url' => route('payments.webhook', ['provider' => $gateway->provider]),
            'reference' => $transaction->reference,
        ]);

        $transaction->update([
            'provider_reference' => $result['provider_reference'] ?? null,
            'checkout_url' => $result['checkout_url'] ?? null,
            'raw_response' => $result['raw'] ?? [],
        ]);

        return $transaction;
    }
}
