<?php

namespace App\Services;

use App\Models\ApprovalChainStep;
use App\Models\ApprovalProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Multi-tier approval chains, built alongside the older single-tier
 * company.*_approval_threshold gate rather than replacing it: a document
 * type with no ApprovalChainStep rows configured is entirely untouched by
 * this service — the controller keeps using Company::poRequiresApproval()/
 * expenseRequiresApproval() exactly as before. Only once a company defines
 * at least one step for a document type does this service take over
 * deciding what "needs approval" means for that type.
 */
class ApprovalChainService
{
    public function isConfigured(string $documentType): bool
    {
        return ApprovalChainStep::where('document_type', $documentType)->exists();
    }

    /**
     * Every step whose threshold this amount reaches, in approval order.
     * A document below every configured tier gets an empty collection
     * (auto-approved, no chain — e.g. a 2,000 SAR PO when the cheapest
     * tier starts at 5,000).
     */
    public function applicableSteps(string $documentType, float $amount): Collection
    {
        return ApprovalChainStep::where('document_type', $documentType)
            ->where('min_amount', '<=', $amount)
            ->orderBy('step_number')
            ->with('role')
            ->get();
    }

    /**
     * Snapshots the given steps as pending progress rows against the
     * document — call only once, right when it first enters
     * pending_approval.
     */
    public function start(Model $document, Collection $steps): void
    {
        foreach ($steps as $step) {
            ApprovalProgress::create([
                'company_id' => $document->company_id,
                'approvable_type' => $document::class,
                'approvable_id' => $document->id,
                'step_number' => $step->step_number,
                'role_id' => $step->role_id,
                'role_name' => $step->role->name,
                'min_amount' => $step->min_amount,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * The lowest-numbered step still awaiting a decision — null once
     * every step has been approved (the document itself should already
     * be flipped to its terminal "approved" status by then) or as soon
     * as one step has been rejected (rejectCurrent() below skips the
     * rest, so there is no longer a "current" step to act on).
     */
    public function currentStep(Model $document): ?ApprovalProgress
    {
        return ApprovalProgress::where('approvable_type', $document::class)
            ->where('approvable_id', $document->id)
            ->where('status', 'pending')
            ->orderBy('step_number')
            ->first();
    }

    public function canActOn(ApprovalProgress $step, User $user): bool
    {
        return $user->isOwner() || $user->isSuperAdmin()
            || ($step->role_id !== null && $user->roles->contains('id', $step->role_id));
    }

    /**
     * Marks the given step approved. Returns true when it was the last
     * pending step — the caller is then responsible for flipping the
     * document itself to its terminal approved status (and any side
     * effect that belongs only at final approval, like ledger posting).
     */
    public function approveStep(ApprovalProgress $step, User $user): bool
    {
        $step->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);

        return ! ApprovalProgress::where('approvable_type', $step->approvable_type)
            ->where('approvable_id', $step->approvable_id)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Rejects the current step with the given reason and skips every
     * later step — a chain is sequential, so once one link fails the
     * rest never come up for a decision.
     */
    public function rejectCurrent(Model $document, User $user, ?string $reason): void
    {
        $pending = ApprovalProgress::where('approvable_type', $document::class)
            ->where('approvable_id', $document->id)
            ->where('status', 'pending')
            ->orderBy('step_number')
            ->get();

        foreach ($pending as $index => $step) {
            $step->update($index === 0
                ? ['status' => 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'rejection_reason' => $reason]
                : ['status' => 'skipped']);
        }
    }

    /**
     * Notifies whoever currently holds the given step's role (plus the
     * owner, who can act on any step) — call right after start() for the
     * first step, and again after approveStep() returns false for the
     * next one.
     */
    public function notifyStepApprovers(ApprovalProgress $step, \Closure $notify): void
    {
        User::where('company_id', $step->company_id)
            ->get()
            ->filter(fn (User $user) => $user->isOwner() || ($step->role_id !== null && $user->roles->contains('id', $step->role_id)))
            ->each($notify);
    }
}
