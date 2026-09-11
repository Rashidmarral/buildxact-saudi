<?php

namespace App\Services\Subscriptions;

use App\Mail\SubscriptionExpiringMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The single place every subscription status change goes through — manual
 * Super Admin actions (Admin\CompanyController) and the automatic dunning
 * ladder (RunSubscriptionLifecycleRules, scheduled daily) alike. Every
 * public method here wraps its write in a DB transaction and records an
 * AuditLog entry with the before/after state; never bypass this class to
 * write subscriptions.status directly from a controller or command.
 *
 * The dunning ladder this drives is deliberately linear and each step's
 * timing is configurable (never hardcoded — see Setting keys below):
 *
 *   trialing --(period ends, never upgraded)---------------------> expired
 *   active   --(period ends, not cancelled)-----------------------> past_due
 *   past_due --(subscription_grace_period_days later)--------------> grace_period
 *   grace_period --(subscription_suspend_after_grace_days later)---> suspended
 *   suspended --(subscription_cancel_after_suspended_days later)---> cancelled
 *
 * A subscription with cancelled_at set (the existing self-service "cancel
 * at period end" flow — BillingController::cancel()) is explicitly excluded
 * from every step of this ladder: it is handled exclusively by the
 * pre-existing subscriptions:expire-cancelled command, unchanged.
 *
 * Scope note: reaching 'past_due' / 'grace_period' / 'suspended' does NOT
 * by itself block a company's users from logging in — that remains
 * Company::status's job (Suspend/Activate, Module 03), unchanged. This
 * class only tracks and audits billing lifecycle state; wiring it into an
 * access-blocking middleware would be a separate, deliberate decision with
 * its own blast radius on live tenants.
 */
class SubscriptionLifecycleService
{
    public function defaultGracePeriodDays(): int
    {
        return (int) Setting::get('subscription_grace_period_days', 3);
    }

    public function defaultSuspendAfterGraceDays(): int
    {
        return (int) Setting::get('subscription_suspend_after_grace_days', 4);
    }

    public function defaultCancelAfterSuspendedDays(): int
    {
        return (int) Setting::get('subscription_cancel_after_suspended_days', 7);
    }

    public function trialReminderDaysBefore(): int
    {
        return (int) Setting::get('subscription_trial_reminder_days_before', 3);
    }

    // -----------------------------------------------------------------
    // Manual Super Admin actions
    // -----------------------------------------------------------------

    public function upgrade(Company $company, Plan $newPlan, string $billingCycle, ?int $actorId = null): Subscription
    {
        return $this->swapPlan($company, $newPlan, $billingCycle, 'subscription.upgrade', $actorId);
    }

    public function downgrade(Company $company, Plan $newPlan, string $billingCycle, ?int $actorId = null): Subscription
    {
        return $this->swapPlan($company, $newPlan, $billingCycle, 'subscription.downgrade', $actorId);
    }

    private function swapPlan(Company $company, Plan $newPlan, string $billingCycle, string $action, ?int $actorId): Subscription
    {
        $subscription = $company->activeSubscription();
        abort_unless($subscription, 404);

        $old = $subscription->only(['plan_id', 'billing_cycle']);

        return DB::transaction(function () use ($subscription, $newPlan, $billingCycle, $action, $actorId, $old, $company) {
            $subscription->update(['plan_id' => $newPlan->id, 'billing_cycle' => $billingCycle]);

            AuditLog::record(
                $action,
                $subscription,
                __(':action :company to :plan (:cycle)', [
                    'action' => $action === 'subscription.upgrade' ? __('Upgraded') : __('Downgraded'),
                    'company' => $company->name,
                    'plan' => $newPlan->name,
                    'cycle' => $billingCycle,
                ]),
                actorId: $actorId,
                old: $old,
                new: $subscription->only(['plan_id', 'billing_cycle']),
                companyId: $company->id,
            );

            return $subscription->fresh();
        });
    }

    public function addGracePeriod(Company $company, int $days, ?int $actorId = null): Subscription
    {
        $subscription = $company->activeSubscription();
        abort_unless($subscription, 404);
        abort_if(in_array($subscription->status, ['cancelled', 'expired'], true), 404);

        $old = $subscription->only(['status', 'grace_period_ends_at']);
        $gracePeriodEndsAt = now()->addDays($days);

        return DB::transaction(function () use ($subscription, $gracePeriodEndsAt, $actorId, $old, $company, $days) {
            $subscription->update(['status' => 'grace_period', 'grace_period_ends_at' => $gracePeriodEndsAt, 'suspended_at' => null]);

            AuditLog::record(
                'subscription.add_grace_period',
                $subscription,
                __('Added :days day(s) grace period for :company', ['days' => $days, 'company' => $company->name]),
                actorId: $actorId,
                old: $old,
                new: $subscription->only(['status', 'grace_period_ends_at']),
                companyId: $company->id,
            );

            return $subscription->fresh();
        });
    }

    /**
     * Manually pauses access without cancelling — the admin-initiated
     * equivalent of what the automatic ladder reaches on its own after
     * non-payment. Reuses the same 'suspended' status so both paths share
     * one meaning and one Resume action.
     */
    public function pause(Company $company, ?int $actorId = null): Subscription
    {
        $subscription = $company->activeSubscription();
        abort_unless($subscription, 404);
        abort_if($subscription->status === 'suspended', 404);

        $old = $subscription->only(['status']);

        return DB::transaction(function () use ($subscription, $actorId, $old, $company) {
            $subscription->update(['status' => 'suspended', 'suspended_at' => now()]);

            AuditLog::record(
                'subscription.pause',
                $subscription,
                __('Paused subscription for :company', ['company' => $company->name]),
                actorId: $actorId,
                old: $old,
                new: $subscription->only(['status']),
                companyId: $company->id,
            );

            return $subscription->fresh();
        });
    }

    /**
     * Reverses a non-terminal dunning state (past_due/grace_period/
     * suspended) back to 'active'. Distinct from reactivate(): resume is
     * for a subscription that never actually died, just fell behind.
     */
    public function resume(Company $company, ?int $actorId = null): Subscription
    {
        $subscription = $company->activeSubscription();
        abort_unless($subscription && in_array($subscription->status, ['past_due', 'grace_period', 'suspended'], true), 404);

        $old = $subscription->only(['status', 'grace_period_ends_at', 'suspended_at']);

        return DB::transaction(function () use ($subscription, $actorId, $old, $company) {
            $subscription->update(['status' => 'active', 'grace_period_ends_at' => null, 'suspended_at' => null]);

            AuditLog::record(
                'subscription.resume',
                $subscription,
                __('Resumed subscription for :company', ['company' => $company->name]),
                actorId: $actorId,
                old: $old,
                new: $subscription->only(['status', 'grace_period_ends_at', 'suspended_at']),
                companyId: $company->id,
            );

            return $subscription->fresh();
        });
    }

    /**
     * Revives a fully terminal subscription (cancelled/expired) with a
     * fresh billing period — unlike resume(), there is no "current"
     * subscription to un-lapse, so this creates the next one explicitly.
     */
    public function reactivate(Company $company, Plan $plan, string $billingCycle, ?int $actorId = null): Subscription
    {
        $latest = $company->subscriptions()->latest('id')->first();
        abort_if($latest && ! in_array($latest->status, ['cancelled', 'expired'], true), 404);

        $old = $latest?->only(['status', 'plan_id']);
        $periodEnd = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();

        return DB::transaction(function () use ($company, $plan, $billingCycle, $periodEnd, $actorId, $old) {
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]);

            AuditLog::record(
                'subscription.reactivate',
                $subscription,
                __('Reactivated subscription for :company on :plan', ['company' => $company->name, 'plan' => $plan->name]),
                actorId: $actorId,
                old: $old,
                new: $subscription->only(['status', 'plan_id']),
                companyId: $company->id,
            );

            return $subscription;
        });
    }

    /**
     * Grants a complimentary (free) subscription — active immediately, no
     * Payment record expected. is_comp/comp_reason document why, so
     * revenue/MRR reporting can exclude it later without guessing.
     */
    public function compAccount(Company $company, Plan $plan, ?string $reason, ?int $actorId = null): Subscription
    {
        $existing = $company->activeSubscription();
        $old = $existing?->only(['status', 'plan_id', 'is_comp']);

        return DB::transaction(function () use ($company, $plan, $reason, $existing, $actorId, $old) {
            if ($existing) {
                $existing->update([
                    'plan_id' => $plan->id, 'status' => 'active', 'is_comp' => true, 'comp_reason' => $reason,
                    'cancelled_at' => null, 'grace_period_ends_at' => null, 'suspended_at' => null,
                ]);
                $subscription = $existing;
            } else {
                $subscription = Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'billing_cycle' => 'monthly',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addYears(100),
                    'is_comp' => true,
                    'comp_reason' => $reason,
                ]);
            }

            AuditLog::record(
                'subscription.comp_account',
                $subscription,
                __('Granted :company a comp account on :plan', ['company' => $company->name, 'plan' => $plan->name]),
                actorId: $actorId,
                old: $old,
                new: $subscription->only(['status', 'plan_id', 'is_comp']),
                companyId: $company->id,
            );

            return $subscription->fresh();
        });
    }

    // -----------------------------------------------------------------
    // Automatic rules — called by subscriptions:run-lifecycle-rules
    // -----------------------------------------------------------------

    /** Rule: "Trial ending reminder". Unchanged mechanism, configurable window. */
    public function sendTrialEndingReminders(): int
    {
        $daysAhead = $this->trialReminderDaysBefore();

        $subscriptions = Subscription::withoutGlobalScopes()
            ->whereIn('status', ['trialing', 'active'])
            ->whereNotNull('current_period_end')
            ->whereNull('expiry_reminder_sent_at')
            ->where('current_period_end', '<=', now()->addDays($daysAhead))
            ->where('current_period_end', '>', now())
            ->with('company', 'plan')
            ->get()
            ->filter(fn (Subscription $s) => $s->company?->owners()->exists());

        foreach ($subscriptions as $subscription) {
            foreach ($subscription->company->owners as $owner) {
                Mail::to($owner->email)->send(new SubscriptionExpiringMail($subscription));
                $owner->notify(new GenericNotification(
                    title: $subscription->isTrial() ? __('Your trial is ending soon') : __('Your subscription is renewing soon'),
                    body: __(':plan · ends :date', ['plan' => $subscription->plan->name, 'date' => $subscription->current_period_end->format('Y-m-d')]),
                    url: route('app.billing.index'),
                    icon: 'clock',
                ));
            }

            $subscription->update(['expiry_reminder_sent_at' => now()]);
        }

        return $subscriptions->count();
    }

    /** Rule: "Trial expiration". A trial that never converted, once its period ends. */
    public function expireTrials(): int
    {
        $subscriptions = Subscription::withoutGlobalScopes()
            ->where('status', 'trialing')
            ->whereNull('cancelled_at')
            ->where('current_period_end', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $this->transition($subscription, 'expired', 'subscription.trial_expired', __('Trial expired'));
        }

        return $subscriptions->count();
    }

    /** Rule: "Payment failure" (period lapsed without renewal). active -> past_due. */
    public function flagPastDue(): int
    {
        $subscriptions = Subscription::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNull('cancelled_at')
            ->where('is_comp', false)
            ->where('current_period_end', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $this->transition($subscription, 'past_due', 'subscription.past_due', __('Payment not received by period end'));
        }

        return $subscriptions->count();
    }

    /** Rule: "Grace period". past_due -> grace_period after the configured delay. */
    public function advanceGracePeriod(): int
    {
        $days = $this->defaultGracePeriodDays();

        $subscriptions = Subscription::withoutGlobalScopes()
            ->where('status', 'past_due')
            ->whereNull('cancelled_at')
            ->where('current_period_end', '<=', now()->subDays($days))
            ->get();

        foreach ($subscriptions as $subscription) {
            // Anchored on current_period_end (when it actually lapsed), not
            // now() — a subscription that's been unpaid a long time (e.g.
            // the app was offline, or this command missed a few runs)
            // needs grace_period_ends_at to already be in the past so the
            // very next step (suspendExpiredGracePeriods) can pick it up
            // in the same run instead of waiting for "now + N days" to
            // elapse for real.
            $subscription->grace_period_ends_at = $subscription->current_period_end->copy()->addDays($days + $this->defaultSuspendAfterGraceDays());
            $this->transition($subscription, 'grace_period', 'subscription.grace_period', __('Entered grace period'));
        }

        return $subscriptions->count();
    }

    /** Rule: "Subscription suspension". grace_period -> suspended once it ends. */
    public function suspendExpiredGracePeriods(): int
    {
        $subscriptions = Subscription::withoutGlobalScopes()
            ->where('status', 'grace_period')
            ->whereNull('cancelled_at')
            ->where('grace_period_ends_at', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->suspended_at = now();
            $this->transition($subscription, 'suspended', 'subscription.suspended', __('Grace period ended without payment'));
        }

        return $subscriptions->count();
    }

    /** Rule: "Subscription cancellation". suspended -> cancelled after the configured delay. */
    public function cancelAbandonedSuspensions(): int
    {
        $days = $this->defaultCancelAfterSuspendedDays();

        $subscriptions = Subscription::withoutGlobalScopes()
            ->where('status', 'suspended')
            ->whereNotNull('suspended_at')
            ->where('suspended_at', '<=', now()->subDays($days))
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->cancelled_at = now();
            $this->transition($subscription, 'cancelled', 'subscription.auto_cancelled', __('Cancelled after prolonged non-payment'));
        }

        return $subscriptions->count();
    }

    /**
     * Shared write path for every automatic transition: saves whatever
     * dirty attributes the caller already staged on $subscription (e.g.
     * grace_period_ends_at) plus the new status, inside a transaction, with
     * an audit entry. No authenticated actor in this context — admin_user_id
     * stays null, exactly like every other system-initiated AuditLog entry.
     */
    private function transition(Subscription $subscription, string $newStatus, string $action, string $description): void
    {
        $old = $subscription->only(['status', 'grace_period_ends_at', 'suspended_at', 'cancelled_at']);

        DB::transaction(function () use ($subscription, $newStatus, $action, $description, $old) {
            $subscription->status = $newStatus;
            $subscription->save();

            AuditLog::record(
                $action,
                $subscription,
                $description,
                old: $old,
                new: $subscription->only(['status', 'grace_period_ends_at', 'suspended_at', 'cancelled_at']),
                companyId: $subscription->company_id,
            );
        });
    }
}
