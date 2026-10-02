<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One step's worth of progress through a document's approval chain,
 * snapshotted at submission time from the ApprovalChainStep config that
 * applied then — so a later change to the chain's roles/thresholds never
 * rewrites history for a document already mid-approval.
 */
class ApprovalProgress extends Model
{
    use BelongsToCompany;

    // Laravel's inflector treats "progress" as uncountable and would
    // otherwise guess the singular "approval_progress" as the table name.
    protected $table = 'approval_progresses';

    protected $fillable = [
        'company_id', 'approvable_type', 'approvable_id', 'step_number',
        'role_id', 'role_name', 'min_amount', 'status',
        'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
