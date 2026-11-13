<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCost extends Model
{
    protected $fillable = [
        'project_id', 'cost_head', 'cost_date', 'description', 'quantity',
        'unit_cost', 'amount', 'billable', 'created_by', 'notes', 'invoice_id', 'invoiced_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_date' => 'date',
            'billable' => 'boolean',
            'invoiced_at' => 'datetime',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
