<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMaterialIssue extends Model
{
    protected $fillable = [
        'project_id', 'item_id', 'warehouse_id', 'quantity', 'unit_cost',
        'total', 'issued_to', 'issued_at', 'project_cost_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function projectCost(): BelongsTo
    {
        return $this->belongsTo(ProjectCost::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
