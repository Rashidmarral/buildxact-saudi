<?php

namespace App\Services\Coupons;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * The single place a coupon is ever validated, priced, or redeemed —
 * BillingController::upgrade() and Admin\CouponController both go through
 * this rather than re-implementing the rules. Every redemption is wrapped
 * in a DB transaction and audit-logged; every discount is clamped so a
 * subscription total can never go negative.
 */
class CouponService
{
    /**
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(Coupon $coupon, Company $company, Plan $plan, float $amount): array
    {
        $errors = [];

        if (! $coupon->is_active) {
            $errors[] = __('This coupon is no longer active.');
        }

        if ($coupon->hasNotStartedYet()) {
            $errors[] = __('This coupon is not active yet.');
        }

        if ($coupon->isExpired()) {
            $errors[] = __('This coupon has expired.');
        }

        if ($coupon->hasReachedMaxUses()) {
            $errors[] = __('This coupon has reached its maximum number of uses.');
        }

        if (! $coupon->appliesToEveryPlan() && ! $coupon->plans()->where('plans.id', $plan->id)->exists()) {
            $errors[] = __('This coupon does not apply to the selected plan.');
        }

        if ($coupon->min_subscription_amount !== null && $amount < (float) $coupon->min_subscription_amount) {
            $errors[] = __('This coupon requires a minimum subscription amount of :amount :currency.', [
                'amount' => number_format((float) $coupon->min_subscription_amount, 2), 'currency' => $coupon->currency,
            ]);
        }

        $companyRedemptionCount = CouponRedemption::where('coupon_id', $coupon->id)->where('company_id', $company->id)->count();
        $hasEverPaid = Payment::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'paid')->exists();

        if ($coupon->max_uses_per_company !== null && $companyRedemptionCount >= $coupon->max_uses_per_company) {
            $errors[] = __('This company has already used this coupon the maximum number of times.');
        }

        if ($coupon->new_customers_only && $hasEverPaid) {
            $errors[] = __('This coupon is only available to new customers.');
        }

        if ($coupon->discount_type === 'first_payment' && $hasEverPaid) {
            $errors[] = __('This coupon only applies to a company\'s first payment.');
        }

        if ($coupon->discount_type === 'limited_duration' && $coupon->duration_in_cycles !== null && $companyRedemptionCount >= $coupon->duration_in_cycles) {
            $errors[] = __('This coupon\'s limited-duration discount has already been used for this company.');
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * The raw discount for $amount, clamped so amount - discount can never
     * go below zero — "do not allow negative subscription totals" holds
     * regardless of what percentage/fixed_amount is configured.
     */
    public function calculateDiscount(Coupon $coupon, float $amount): float
    {
        if ($coupon->percentage !== null) {
            $discount = $amount * ((float) $coupon->percentage / 100);
        } elseif ($coupon->fixed_amount !== null) {
            $discount = (float) $coupon->fixed_amount;
        } else {
            $discount = 0.0;
        }

        return round(min(max($discount, 0.0), $amount), 2);
    }

    /**
     * Validates, computes the discount, and returns [finalAmount,
     * discountAmount] — throws via the caller's own error handling if
     * $coupon is null or invalid. Callers apply this BEFORE creating any
     * subscription/payment rows so an invalid code never partially commits
     * a checkout.
     *
     * @return array{0: float, 1: float}
     */
    public function priceWithCoupon(Coupon $coupon, Company $company, Plan $plan, float $amount): array
    {
        $discount = $this->calculateDiscount($coupon, $amount);

        return [round($amount - $discount, 2), $discount];
    }

    public function redeem(
        Coupon $coupon,
        Company $company,
        float $originalAmount,
        float $discountAmount,
        ?Subscription $subscription = null,
        ?Payment $payment = null,
        ?int $actorId = null,
    ): CouponRedemption {
        return DB::transaction(function () use ($coupon, $company, $originalAmount, $discountAmount, $subscription, $payment, $actorId) {
            $redemption = CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'company_id' => $company->id,
                'subscription_id' => $subscription?->id,
                'payment_id' => $payment?->id,
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => max(0, round($originalAmount - $discountAmount, 2)),
                'redeemed_at' => now(),
            ]);

            AuditLog::record(
                'coupon.redeem',
                $coupon,
                __('Applied coupon :code for :company (:discount :currency off)', [
                    'code' => $coupon->code, 'company' => $company->name,
                    'discount' => number_format($discountAmount, 2), 'currency' => $coupon->currency,
                ]),
                actorId: $actorId,
                new: $redemption->only(['original_amount', 'discount_amount', 'final_amount']),
                companyId: $company->id,
            );

            return $redemption;
        });
    }
}
