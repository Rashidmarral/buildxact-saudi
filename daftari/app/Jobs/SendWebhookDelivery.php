<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Notifications\GenericNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SendWebhookDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $webhookId,
        public readonly string $event,
        public readonly array $payload
    ) {}

    public function handle(): void
    {
        $webhook = Webhook::withoutGlobalScopes()->find($this->webhookId);

        if (! $webhook || ! $webhook->is_active) {
            return;
        }

        $body = [
            'event' => $this->event,
            'data' => $this->payload,
            'sent_at' => now()->toIso8601String(),
        ];

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $json, $webhook->secret);

        try {
            $response = Http::withBody($json, 'application/json')
                ->withHeaders([
                    'X-Daftari-Event' => $this->event,
                    'X-Daftari-Signature' => 'sha256='.$signature,
                ])
                ->timeout(10)
                ->post($webhook->url);
        } catch (\Throwable $e) {
            // Connection-level failure (DNS, timeout, refused) — no response
            // was ever received.
            WebhookDelivery::create([
                'webhook_id' => $webhook->id,
                'event' => $this->event,
                'payload' => $body,
                'response_status' => null,
                'success' => false,
                'error' => $e->getMessage(),
                'created_at' => now(),
            ]);

            throw $e;
        }

        WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => $this->event,
            'payload' => $body,
            'response_status' => $response->status(),
            'success' => $response->successful(),
            'created_at' => now(),
        ]);

        // Non-2xx responses go through the normal retry/backoff path rather
        // than $this->fail(), which would mark the job permanently failed
        // and skip the remaining attempts.
        $response->throw();
    }

    /**
     * Audit finding LOW-30: every attempt already lands a WebhookDelivery
     * row, but nothing ever surfaced to a human that a webhook had
     * exhausted all its retries — the failure just sat in the deliveries
     * log until someone thought to check. Laravel calls this once the
     * job gives up for good (all $tries exhausted), so it fires exactly
     * once per permanently-failed delivery, not once per retry.
     */
    public function failed(\Throwable $exception): void
    {
        $webhook = Webhook::withoutGlobalScopes()->find($this->webhookId);

        if (! $webhook) {
            return;
        }

        User::where('company_id', $webhook->company_id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('settings'))
            ->each(fn (User $user) => $user->notify(new GenericNotification(
                title: __('Webhook delivery failed'),
                body: __(':event delivery to :url failed after all retries: :error', [
                    'event' => $this->event,
                    'url' => $webhook->url,
                    'error' => Str::limit($exception->getMessage(), 150),
                ]),
                url: route('app.settings.webhooks.show', $webhook),
                icon: 'settings',
            )));
    }
}
