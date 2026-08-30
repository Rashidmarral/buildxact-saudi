<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToCompany;

    /**
     * Stored DB values stay backward compatible with every existing row
     * ('paid' predates this module) — "Successful" is a display label
     * only, see statusLabel(). partially_refunded/cancelled/chargeback are
     * new; nothing before this module ever wrote them.
     */
    public const STATUSES = ['paid', 'pending', 'failed', 'refunded', 'partially_refunded', 'cancelled', 'chargeback'];

    protected $fillable = [
        'company_id', 'subscription_id', 'plan_id', 'amount', 'currency',
        'status', 'method', 'reference', 'payment_transaction_id', 'paid_at',
        'refunded_amount', 'refund_reason', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'refunded_amount' => 'decimal:2',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'paid' => __('Successful'),
            'pending' => __('Pending'),
            'failed' => __('Failed'),
            'refunded' => __('Refunded'),
            'partially_refunded' => __('Partially refunded'),
            'cancelled' => __('Cancelled'),
            'chargeback' => __('Chargeback'),
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'paid' => 'bg-emerald-50 text-emerald-700',
            'pending' => 'bg-amber-50 text-amber-700',
            'failed' => 'bg-red-50 text-red-600',
            'refunded' => 'bg-slate-100 text-slate-500',
            'partially_refunded' => 'bg-orange-50 text-orange-600',
            'cancelled' => 'bg-slate-100 text-slate-500',
            'chargeback' => 'bg-red-50 text-red-600',
            default => 'bg-slate-100 text-slate-500',
        };
    }

    public function isFullyRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function remainingRefundable(): float
    {
        if (! in_array($this->status, ['paid', 'partially_refunded'], true)) {
            return 0.0;
        }

        return round((float) $this->amount - (float) ($this->refunded_amount ?? 0), 2);
    }
}
