<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An accountant, reseller, or referral partner (items 11-12 of the Sales,
 * Compliance & Business Growth request). Starts as a public application
 * (status=pending, no login); an admin approves it into a real account
 * (status=invited, a User row is created and sent a set-password invite,
 * same pattern as TeamInviteController) which becomes status=active once
 * the partner accepts.
 */
class Partner extends Model
{
    public const STATUSES = ['pending', 'invited', 'active', 'rejected', 'suspended'];

    protected $fillable = [
        'user_id', 'partner_type_id', 'name', 'email', 'phone', 'company_name', 'message', 'referral_code',
        'status', 'commission_type_override', 'commission_value_override', 'payout_method', 'bank_iban',
        'bank_account_name', 'rejected_reason', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_value_override' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partnerType(): BelongsTo
    {
        return $this->belongsTo(PartnerType::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(PartnerReferral::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(PartnerPayout::class);
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * The commission rule that actually applies to this partner: their own
     * negotiated override if set, otherwise their partner type's rule.
     * Never a hard-coded fallback — a partner with neither has no rule to
     * suggest, and the admin enters the commission amount by hand.
     */
    public function effectiveCommissionType(): ?string
    {
        return $this->commission_type_override ?? $this->partnerType?->commission_type;
    }

    public function effectiveCommissionValue(): ?float
    {
        return $this->commission_value_override !== null
            ? (float) $this->commission_value_override
            : ($this->partnerType?->commission_value !== null ? (float) $this->partnerType->commission_value : null);
    }

    public function isRecurringCommission(): bool
    {
        return (bool) $this->partnerType?->is_recurring;
    }

    /**
     * A non-binding suggestion only — see the commission_amount column
     * comment on partner_referrals. Returns null when there's no plan
     * price to suggest against or no commission rule configured yet.
     */
    public function suggestedCommission(float $planPrice): ?float
    {
        $type = $this->effectiveCommissionType();
        $value = $this->effectiveCommissionValue();

        if ($type === null || $value === null) {
            return null;
        }

        return $type === 'percentage' ? round($planPrice * $value / 100, 2) : $value;
    }

    public function unpaidApprovedBalance(): float
    {
        return (float) $this->referrals()->where('commission_status', 'approved')->sum('commission_amount');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => __('Pending review'),
            'invited' => __('Invited'),
            'active' => __('Active'),
            'rejected' => __('Rejected'),
            'suspended' => __('Suspended'),
            default => ucfirst($this->status),
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'pending' => 'bg-amber-50 text-amber-700',
            'invited' => 'bg-sky-50 text-sky-700',
            'active' => 'bg-emerald-50 text-emerald-700',
            'rejected' => 'bg-red-50 text-red-700',
            'suspended' => 'bg-slate-100 text-slate-500',
            default => 'bg-slate-100 text-slate-500',
        };
    }
}
