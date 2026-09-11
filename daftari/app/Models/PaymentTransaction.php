<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'reference', 'company_id', 'payment_gateway_id', 'provider',
        'payable_type', 'payable_id', 'amount', 'currency', 'status',
        'provider_reference', 'checkout_url', 'raw_response', 'raw_webhook',
    ];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'raw_webhook' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentTransaction $transaction) {
            if (empty($transaction->reference)) {
                $transaction->reference = (string) Str::uuid();
            }
        });
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
