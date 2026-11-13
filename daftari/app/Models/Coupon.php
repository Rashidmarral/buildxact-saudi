<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A SaaS-subscription discount code. discount_type governs WHEN it
 * re-applies across a company's payments (see CouponService for the full
 * state machine); percentage/fixed_amount govern HOW MUCH — exactly one
 * of the two is ever set on a given coupon.
 */
class Coupon extends Model
{
    public const DISCOUNT_TYPES = ['percentage', 'fixed', 'first_payment', 'recurring', 'limited_duration'];

    protected $fillable = [
        'code', 'description', 'discount_type', 'percentage', 'fixed_amount', 'currency',
        'starts_at', 'expires_at', 'max_uses', 'max_uses_per_company', 'min_subscription_amount',
        'duration_in_cycles', 'new_customers_only', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'min_subscription_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'new_customers_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon) {
            $coupon->code = strtoupper((string) $coupon->code);
        });
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function appliesToEveryPlan(): bool
    {
        return $this->plans()->count() === 0;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasNotStartedYet(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isFuture();
    }

    public function hasReachedMaxUses(): bool
    {
        return $this->max_uses !== null && $this->redemptions()->count() >= $this->max_uses;
    }

    public function remainingUses(): ?int
    {
        return $this->max_uses === null ? null : max(0, $this->max_uses - $this->redemptions()->count());
    }
}
