<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A permanent log of every inbound payment-gateway webhook delivery —
 * verified or not, processed or rejected — keyed by a content fingerprint
 * so a retried/replayed delivery is detected as a duplicate before it can
 * be acted on twice. See PaymentWebhookController.
 */
class PaymentGatewayWebhookEvent extends Model
{
    protected $fillable = [
        'provider', 'payment_gateway_id', 'payment_transaction_id',
        'fingerprint', 'status', 'payload', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public static function fingerprintFor(string $provider, string $rawBody): string
    {
        return hash('sha256', $provider.'|'.$rawBody);
    }
}
