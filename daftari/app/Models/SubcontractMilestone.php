<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcontractMilestone extends Model
{
    protected $fillable = [
        'subcontract_id', 'sequence', 'name', 'percentage', 'amount',
        'requires_handover_approval', 'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'amount' => 'decimal:2',
            'requires_handover_approval' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(Subcontract::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubcontractPayment::class, 'milestone_id');
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balance(): float
    {
        return round((float) $this->amount - $this->paidAmount(), 2);
    }

    public function isReleasable(): bool
    {
        return ! $this->requires_handover_approval || $this->approved_at !== null;
    }
}
