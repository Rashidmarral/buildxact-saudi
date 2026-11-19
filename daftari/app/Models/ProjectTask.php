<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    public const STATUSES = ['open', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = [
        'project_id', 'title', 'description', 'assignee_employee_id',
        'status', 'due_date', 'completed_at', 'created_by', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'completed' && $this->status !== 'cancelled' && $this->due_date?->isPast();
    }
}
