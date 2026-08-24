<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public, unauthenticated endpoint every gateway driver posts (or, for
 * HyperPay's widget redirect, GETs) to. Never trusts the payload until a
 * PaymentTransaction has been located by OUR OWN reference and the
 * provider's signature has been verified against THAT transaction's
 * gateway secret — see PaymentGatewayDriver's doc comments for why this
 * two-step lookup-then-verify order matters for a multi-tenant set of
 * webhook secrets.
 */
class PaymentWebhookController extends Controller
{
    public function handle(string $provider, Request $request, PaymentSettlementService $settlement)
    {
        $driver = PaymentGatewayManager::driver($provider);

        $reference = $driver->extractReference($request);
        $transaction = $reference
            ? PaymentTransaction::where('reference', $reference)->where('provider', $provider)->first()
            : null;

        if (! $transaction) {
            abort(404);
        }

        $result = $driver->verifyWebhook($transaction->gateway, $request);

        if (! $result) {
            abort(400, 'Invalid payment notification.');
        }

        $transaction->raw_webhook = $result['raw'];

        if (! $transaction->isPaid()) {
            $transaction->status = $result['status'];
            $transaction->provider_reference = $result['provider_reference'] ?? $transaction->provider_reference;
            $transaction->save();

            if ($result['status'] === 'paid') {
                $settlement->settle($transaction);
            }
        } else {
            $transaction->save();
        }

        if ($request->isMethod('get')) {
            return $this->redirectToLandingPage($transaction);
        }

        return new Response('OK', 200);
    }

    private function redirectToLandingPage(PaymentTransaction $transaction)
    {
        if ($transaction->payable_type === Subscription::class) {
            return redirect()->route('app.billing.index')->with(
                $transaction->isPaid() ? 'status' : 'error',
                $transaction->isPaid() ? __('Payment successful — your subscription is now active.') : __('Payment was not completed.')
            );
        }

        if ($transaction->payable_type === Invoice::class) {
            $invoice = Invoice::withoutGlobalScopes()->find($transaction->payable_id);

            if ($invoice) {
                return redirect()->route('public.invoices.show', ['id' => $invoice->id, 'token' => $invoice->public_token])->with(
                    $transaction->isPaid() ? 'status' : 'error',
                    $transaction->isPaid() ? __('Payment successful. Thank you!') : __('Payment was not completed.')
                );
            }
        }

        return redirect()->route('home');
    }
}
