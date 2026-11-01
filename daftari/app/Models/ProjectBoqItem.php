<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBoqItem extends Model
{
    protected $fillable = [
        'project_id', 'item_no', 'description', 'unit_id', 'quantity', 'rate',
        'cost_rate', 'executed_quantity', 'billed_quantity', 'sort_order',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function amount(): float
    {
        return round((float) $this->quantity * (float) $this->rate, 2);
    }

    public function costAmount(): ?float
    {
        return $this->cost_rate !== null ? round((float) $this->quantity * (float) $this->cost_rate, 2) : null;
    }

    public function executedAmount(): float
    {
        return round((float) $this->executed_quantity * (float) $this->rate, 2);
    }

    public function billedAmount(): float
    {
        return round((float) $this->billed_quantity * (float) $this->rate, 2);
    }

    public function remainingToBill(): float
    {
        return max(0, $this->executedAmount() - $this->billedAmount());
    }

    public function percentComplete(): float
    {
        return $this->quantity > 0 ? round(((float) $this->executed_quantity / (float) $this->quantity) * 100, 1) : 0.0;
    }
}
