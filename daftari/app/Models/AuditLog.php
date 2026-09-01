<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A permanent trail of platform-admin actions (suspend/activate a company,
 * impersonate, change a subscription, mark a payment refunded, manage admin
 * users, edit platform settings) — separate from ZatcaInvoiceLog, which
 * records tax-authority submissions rather than admin activity.
 *
 * Deliberately does NOT use BelongsToCompany: the platform Activity page
 * (Admin\ActivityLogController) needs to see every company's entries in
 * one unscoped feed, which a global company_id scope would break. Any
 * query meant to show ONE company its own activity — e.g. the company's
 * own Activity page — must go through scopeForCompany() below rather
 * than filtering by company_id inline, so that intent is explicit and
 * grep-able instead of silently relying on an unscoped model.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'admin_user_id', 'impersonated_user_id', 'action', 'subject_type', 'subject_id', 'description',
        'old_value', 'new_value', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function impersonatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subject(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * $actorId defaults to the current user. For a company user this also
     * stamps company_id so the entry shows up on that company's own
     * Activity page; platform-admin actions (actor has no company_id, or
     * none is authenticated) leave company_id null UNLESS the subject is a
     * Company, carries its own company_id column (Subscription, Payment,
     * ...), or $companyId is given explicitly — a super admin acting on
     * a specific tenant should still surface on that tenant's own
     * Activity feed, not just the platform-wide one.
     *
     * $old/$new capture the before/after state of whatever changed (plain
     * arrays, JSON-encoded) so every Super Admin action stays reconstructible
     * — never omit them for a mutating action. ip_address/user_agent are
     * captured automatically from the current request when available.
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?int $actorId = null,
        ?array $old = null,
        ?array $new = null,
        ?int $companyId = null,
    ): self {
        $actor = $actorId !== null ? User::find($actorId) : auth()->user();

        // Impersonation swaps Auth::user() to the target for the rest of
        // the session (see Admin\CompanyController::impersonate()), so any
        // call here that didn't explicitly pass $actorId would otherwise
        // silently misattribute every action taken while impersonating to
        // the tenant user rather than the admin actually behind the
        // keyboard. Detect that case and record the true actor plus who
        // was being impersonated. Excludes the impersonate() call itself:
        // it sets session('impersonator_id') one line before swapping
        // Auth::user(), so at that call site both ids are still equal.
        $impersonatedUserId = null;
        $impersonatedCompanyId = null;
        if ($actorId === null && app()->bound('session')) {
            $impersonatorId = session('impersonator_id');
            if ($impersonatorId && $impersonatorId != $actor?->id) {
                $impersonatedUserId = $actor?->id;
                $impersonatedCompanyId = $actor?->company_id;
                $actor = User::withoutGlobalScopes()->find($impersonatorId);
            }
        }

        $companyId ??= $impersonatedCompanyId
            ?? $actor?->company_id
            ?? ($subject instanceof Company ? $subject->id : null)
            ?? $subject?->getAttribute('company_id');

        $request = app()->bound('request') ? request() : null;

        return self::create([
            'company_id' => $companyId,
            'admin_user_id' => $actor?->id,
            'impersonated_user_id' => $impersonatedUserId,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'old_value' => $old,
            'new_value' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
