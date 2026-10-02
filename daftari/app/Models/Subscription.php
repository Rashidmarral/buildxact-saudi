<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use BelongsToCompany;

    /**
     * Every status this column can hold. 'pending' = an online/offline
     * checkout was started but not yet confirmed paid (see BillingController
     * ::upgrade() / PaymentSettlementService); the rest form the lifecycle
     * ladder documented on SubscriptionLifecycleService.
     */
    public const STATUSES = [
        'trialing', 'active', 'past_due', 'grace_period', 'suspended', 'cancelled', 'expired', 'pending',
    ];

    /**
     * Statuses under which a subscription is still the company's "current"
     * one for display/limit purposes — everything short of a terminal
     * state. Used by Company::activeSubscription() so a company mid-dunning
     * (past_due/grace_period/suspended) keeps seeing its plan and usage
     * instead of the UI going blank; access itself is never blocked by
     * this alone (see SubscriptionLifecycleService's class docblock).
     */
    public const NON_TERMINAL_STATUSES = ['trialing', 'active', 'past_due', 'grace_period', 'suspended'];

    protected $fillable = [
        'company_id', 'plan_id', 'status', 'billing_cycle',
        'current_period_start', 'current_period_end', 'cancelled_at',
        'expiry_reminder_sent_at', 'grace_period_ends_at', 'suspended_at',
        'is_comp', 'comp_reason',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'is_comp' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isTrial(): bool
    {
        return $this->status === 'trialing';
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function isInGracePeriod(): bool
    {
        return $this->status === 'grace_period';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'trialing' => __('Trial'),
            'active' => __('Active'),
            'past_due' => __('Past due'),
            'grace_period' => __('Grace period'),
            'suspended' => __('Suspended'),
            'cancelled' => __('Cancelled'),
            'expired' => __('Expired'),
            'pending' => __('Pending payment'),
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'active' => 'bg-emerald-50 text-emerald-700',
            'trialing' => 'bg-amber-50 text-amber-700',
            'past_due' => 'bg-orange-50 text-orange-600',
            'grace_period' => 'bg-amber-50 text-amber-700',
            'suspended' => 'bg-red-50 text-red-600',
            'cancelled' => 'bg-slate-100 text-slate-500',
            'expired' => 'bg-slate-100 text-slate-500',
            'pending' => 'bg-sky-50 text-sky-700',
            default => 'bg-red-50 text-red-600',
        };
    }
}
