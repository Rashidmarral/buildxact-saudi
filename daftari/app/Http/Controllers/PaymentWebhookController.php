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
use Illuminate\Support\Facades\DB;

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
                'payload' => $this->redactedPayload($request), 'ip_address' => $request->ip(),
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
                'payload' => $this->redactedPayload($request), 'ip_address' => $request->ip(),
            ]);

            abort(404);
        }

        $event = PaymentGatewayWebhookEvent::create([
            'provider' => $provider, 'fingerprint' => $fingerprint, 'status' => 'received',
            'payment_gateway_id' => $transaction->payment_gateway_id, 'payment_transaction_id' => $transaction->id,
            'payload' => $this->redactedPayload($request), 'ip_address' => $request->ip(),
        ]);

        $result = $driver->verifyWebhook($transaction->gateway, $transaction, $request);

        if (! $result) {
            $event->update(['status' => 'rejected']);

            abort(400, 'Invalid payment notification.');
        }

        // Audit finding PAY-04: a verified-signature "paid" result was
        // previously settled with no check that it was actually paying
        // for THIS transaction's amount/currency. Only enforced for a
        // 'paid' result — not for 'failed'/'cancelled', where the amount
        // reported genuinely doesn't matter to us.
        if ($result['status'] === 'paid' && ! $this->amountMatches($result, $transaction)) {
            $event->update(['status' => 'rejected']);

            abort(400, 'Payment amount or currency does not match the expected transaction.');
        }

        $event->update(['status' => 'verified']);

        // Audit finding PAY-01: marking the transaction paid and settling
        // the invoice/subscription used to be two separate, unlocked
        // writes. A crash between them left isPaid() permanently true
        // with settlement never applied — and a retry then skipped
        // settlement entirely, since the unpaid branch below never
        // re-entered. Two concurrent deliveries could also both read
        // isPaid() === false before either write landed, double-settling.
        // Locking the row and doing both writes in one DB transaction
        // fixes both: a mid-way failure rolls back the status save too
        // (so a retry correctly redoes everything), and the lock
        // serializes concurrent deliveries so the second one sees the
        // already-paid status and does nothing.
        DB::transaction(function () use ($transaction, $result, $settlement) {
            $locked = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();
            $locked->raw_webhook = $result['raw'];

            if (! $locked->isPaid()) {
                $locked->status = $result['status'];
                $locked->provider_reference = $result['provider_reference'] ?? $locked->provider_reference;
                $locked->save();

                if ($result['status'] === 'paid') {
                    $settlement->settle($locked);
                } elseif (in_array($result['status'], ['failed', 'cancelled'], true)) {
                    Payment::withoutGlobalScopes()->where('payment_transaction_id', $locked->id)
                        ->where('status', 'pending')
                        ->update(['status' => $result['status']]);
                }
            } else {
                $locked->save();
            }
        });

        $transaction->refresh();
        $event->update(['status' => 'processed']);

        if ($request->isMethod('get')) {
            return $this->redirectToLandingPage($transaction);
        }

        return new Response('OK', 200);
    }

    /**
     * Audit finding PAY-04: Moyasar's webhook payload carries the shared
     * secret_token the caller is proving it knows — logging it verbatim
     * into PaymentGatewayWebhookEvent would mean every stored delivery
     * (including rejected/duplicate ones logged before any driver-level
     * handling runs) permanently persists that secret in plaintext.
     * Stripped unconditionally, at whatever depth it appears, since
     * nothing legitimate ever needs to read it back out of storage.
     */
    private function redactedPayload(Request $request): array
    {
        $payload = $request->all();
        array_walk_recursive($payload, function (&$value, $key) {
            if ($key === 'secret_token') {
                $value = '[redacted]';
            }
        });

        if (isset($payload['secret_token'])) {
            $payload['secret_token'] = '[redacted]';
        }

        return $payload;
    }

    /**
     * A driver that can't determine amount/currency (returns null for
     * either) is treated as matching — not every driver's authoritative
     * source necessarily echoes both back, and withholding settlement
     * entirely for those would be a worse outcome than this check simply
     * not applying. Currency is compared case-insensitively (providers
     * are inconsistent about 'SAR' vs 'sar'); amount allows the same 0.01
     * tolerance used throughout this app's own ledger comparisons.
     */
    private function amountMatches(array $result, PaymentTransaction $transaction): bool
    {
        if (isset($result['currency']) && strcasecmp((string) $result['currency'], (string) $transaction->currency) !== 0) {
            return false;
        }

        if (isset($result['amount']) && abs((float) $result['amount'] - (float) $transaction->amount) > 0.01) {
            return false;
        }

        return true;
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
