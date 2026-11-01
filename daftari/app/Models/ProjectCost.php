<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCost extends Model
{
    protected $fillable = [
        'project_id', 'cost_head', 'cost_date', 'description', 'quantity',
        'unit_cost', 'amount', 'billable', 'created_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'cost_date' => 'date',
            'billable' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
