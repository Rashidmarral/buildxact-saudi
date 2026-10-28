<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A subcontractor's (Supplier's) agreed scope on one Project: a contract
 * value, a retention percentage withheld until handover, an optional BOQ
 * breakdown behind that value, and a milestone-wise payment schedule.
 * See subcontracts migration docblock for why this is its own entity
 * rather than fields on Project/Supplier — the same supplier can hold
 * subcontracts on several projects, and a project can have several
 * subcontractors (different trades).
 */
class Subcontract extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'supplier_id', 'contract_number', 'title',
        'contract_value', 'retention_percent', 'start_date', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'start_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(SubcontractBoqItem::class)->orderBy('sort_order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(SubcontractMilestone::class)->orderBy('sequence');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubcontractPayment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balanceBeforeRetention(): float
    {
        return round((float) $this->contract_value - $this->totalPaid(), 2);
    }

    public function retentionAmount(): float
    {
        return round((float) $this->contract_value * ((float) $this->retention_percent / 100), 2);
    }

    public function balanceAfterRetention(): float
    {
        return round($this->balanceBeforeRetention() - $this->retentionAmount(), 2);
    }

    public function percentComplete(): float
    {
        if ((float) $this->contract_value <= 0) {
            return 0.0;
        }

        return round(min(100, ($this->totalPaid() / (float) $this->contract_value) * 100), 1);
    }
}
