<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;

/**
 * Hosts HyperPay's COPYandPAY widget script — the only one of the four
 * gateways whose card entry happens inside a widget we embed, rather than
 * on a fully external hosted page. See HyperPayDriver's doc comment.
 */
class PaymentWidgetController extends Controller
{
    public function show(string $provider, string $reference)
    {
        $transaction = PaymentTransaction::where('reference', $reference)
            ->where('provider', $provider)
            ->firstOrFail();

        abort_unless($provider === 'hyperpay', 404);

        $gateway = $transaction->gateway;
        $baseUrl = $gateway->mode === 'live' ? 'https://eu-prod.oppwa.com' : 'https://eu-test.oppwa.com';

        return view('payments.hyperpay-widget', [
            'checkoutId' => $transaction->provider_reference,
            'widgetScriptUrl' => $baseUrl.'/v1/paymentWidgets.js?checkoutId='.$transaction->provider_reference,
        ]);
    }
}
