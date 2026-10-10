<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Webhook extends Model
{
    use BelongsToCompany;

    public const EVENTS = [
        'invoice.created',
        'invoice.sent',
        'invoice.paid',
        'client.created',
    ];

    protected $fillable = [
        'company_id', 'url', 'secret', 'events', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            // The app still needs the plaintext value to sign outbound
            // deliveries (unlike an API token, which only needs to be
            // hashed for verification) — 'encrypted' keeps it readable to
            // the app while storing ciphertext in the database, matching
            // SmsConfig/WhatsappConfig/PaymentGateway's credential columns.
            'secret' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Webhook $webhook) {
            if (empty($webhook->secret)) {
                $webhook->secret = Str::random(40);
            }
        });
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest('id');
    }

    /**
     * Queue a delivery to every active webhook the company has subscribed
     * to this event. Called from controllers right after the triggering
     * write succeeds — never inline with the request/response cycle, since
     * a slow or unreachable third-party endpoint must not block the user.
     */
    public static function trigger(int $companyId, string $event, array $payload): void
    {
        static::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->each(function (Webhook $webhook) use ($event, $payload) {
                if (in_array($event, $webhook->events ?? [], true)) {
                    \App\Jobs\SendWebhookDelivery::dispatch($webhook->id, $event, $payload);
                }
            });
    }
}
