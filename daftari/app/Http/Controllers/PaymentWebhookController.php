<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGatewayWebhookEvent;
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
 *
 * Every delivery — resolved or not, verified or not — is logged to
 * PaymentGatewayWebhookEvent for audit visibility, and a delivery whose
 * content fingerprint exactly matches one already fully processed is
 * short-circuited as a duplicate before it can be verified or acted on
 * again (gateways commonly retry a webhook until they see 200 OK, and a
 * captured request replayed by an attacker fingerprints identically too).
 */
class PaymentWebhookController extends Controller
{
    public function handle(string $provider, Request $request, PaymentSettlementService $settlement)
    {
        $rawBody = $request->getContent() ?: http_build_query($request->query());
        $fingerprint = PaymentGatewayWebhookEvent::fingerprintFor($provider, $rawBody);

        $alreadyProcessed = PaymentGatewayWebhookEvent::where('provider', $provider)
            ->where('fingerprint', $fingerprint)
            ->where('status', 'processed')
            ->exists();

        if ($alreadyProcessed) {
            PaymentGatewayWebhookEvent::create([
                'provider' => $provider, 'fingerprint' => $fingerprint, 'status' => 'duplicate',
                'payload' => $request->all(), 'ip_address' => $request->ip(),
            ]);

            return new Response('OK', 200);
        }

        $driver = PaymentGatewayManager::driver($provider);

        $reference = $driver->extractReference($request);
        $transaction = $reference
            ? PaymentTransaction::where('reference', $reference)->where('provider', $provider)->first()
            : null;

        if (! $transaction) {
            PaymentGatewayWebhookEvent::create([
                'provider' => $provider, 'fingerprint' => $fingerprint, 'status' => 'rejected',
                'payload' => $request->all(), 'ip_address' => $request->ip(),
            ]);

            abort(404);
        }

        $event = PaymentGatewayWebhookEvent::create([
            'provider' => $provider, 'fingerprint' => $fingerprint, 'status' => 'received',
            'payment_gateway_id' => $transaction->payment_gateway_id, 'payment_transaction_id' => $transaction->id,
            'payload' => $request->all(), 'ip_address' => $request->ip(),
        ]);

        $result = $driver->verifyWebhook($transaction->gateway, $request);

        if (! $result) {
            $event->update(['status' => 'rejected']);

            abort(400, 'Invalid payment notification.');
        }

        $event->update(['status' => 'verified']);

        $transaction->raw_webhook = $result['raw'];

        if (! $transaction->isPaid()) {
            $transaction->status = $result['status'];
            $transaction->provider_reference = $result['provider_reference'] ?? $transaction->provider_reference;
            $transaction->save();

            if ($result['status'] === 'paid') {
                $settlement->settle($transaction);
            } elseif (in_array($result['status'], ['failed', 'cancelled'], true)) {
                Payment::withoutGlobalScopes()->where('payment_transaction_id', $transaction->id)
                    ->where('status', 'pending')
                    ->update(['status' => $result['status']]);
            }
        } else {
            $transaction->save();
        }

        $event->update(['status' => 'processed']);

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
