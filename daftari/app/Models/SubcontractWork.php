<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named slice of work under one budget/subcontract (e.g. "MC1",
 * "RC2 Spraying") that a payment can be tagged to — same idea as
 * Subcontract's milestones, but without the percentage-of-contract-
 * value/handover-approval schedule a contractual milestone needs.
 * budget_amount is optional: a Work can just be a label to sort
 * payments by, with no amount of its own to track against.
 */
class SubcontractWork extends Model
{
    protected $fillable = ['subcontract_id', 'name', 'budget_amount', 'sort_order'];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
        ];
    }

    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(Subcontract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubcontractPayment::class, 'work_id');
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balance(): ?float
    {
        return $this->budget_amount !== null ? round((float) $this->budget_amount - $this->paidAmount(), 2) : null;
    }
}
