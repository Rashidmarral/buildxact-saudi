<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A permanent trail of platform-admin actions (suspend/activate a company,
 * impersonate, change a subscription, mark a payment refunded, manage admin
 * users, edit platform settings) — separate from ZatcaInvoiceLog, which
 * records tax-authority submissions rather than admin activity.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'admin_user_id', 'action', 'subject_type', 'subject_id', 'description', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subject(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    /**
     * $actorId defaults to the current user. For a company user this also
     * stamps company_id so the entry shows up on that company's own
     * Activity page; platform-admin actions (actor has no company_id, or
     * none is authenticated) leave company_id null, matching the existing
     * admin-only audit trail.
     */
    public static function record(string $action, ?Model $subject = null, ?string $description = null, ?int $actorId = null): self
    {
        $actor = $actorId !== null ? User::find($actorId) : auth()->user();

        return self::create([
            'company_id' => $actor?->company_id,
            'admin_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
