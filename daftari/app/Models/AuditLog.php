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
        'admin_user_id', 'action', 'subject_type', 'subject_id', 'description', 'created_at',
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

    public function subject(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public static function record(string $action, ?Model $subject = null, ?string $description = null, ?int $actorId = null): self
    {
        return self::create([
            'admin_user_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
