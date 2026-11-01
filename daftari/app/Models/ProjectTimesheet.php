<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTimesheet extends Model
{
    public const STATUSES = ['draft', 'approved'];

    protected $fillable = [
        'project_id', 'employee_id', 'task_id', 'work_date', 'description',
        'hours', 'overtime_hours', 'billable', 'bill_rate', 'cost_rate',
        'status', 'project_cost_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'billable' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function projectCost(): BelongsTo
    {
        return $this->belongsTo(ProjectCost::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalHours(): float
    {
        return (float) $this->hours + (float) $this->overtime_hours;
    }

    public function costAmount(): float
    {
        return $this->cost_rate !== null ? round($this->totalHours() * (float) $this->cost_rate, 2) : 0.0;
    }

    public function billAmount(): float
    {
        return $this->bill_rate !== null ? round($this->totalHours() * (float) $this->bill_rate, 2) : 0.0;
    }

    /**
     * Posts this timesheet's cost_rate x hours as a real ProjectCost
     * row (cost_head 'labour') once approved — mirrors
     * MachineryProjectDeployment::postCost()'s pattern exactly. A
     * timesheet with no cost_rate has no cost to post.
     */
    public function postCost(): void
    {
        if ($this->cost_rate === null) {
            return;
        }

        $attributes = [
            'cost_head' => 'labour',
            'cost_date' => $this->work_date,
            'description' => __(':employee — :hours h on :project', [
                'employee' => $this->employee?->full_name, 'hours' => $this->totalHours(), 'project' => $this->project->name,
            ]),
            'amount' => $this->costAmount(),
            'billable' => $this->billable ?? true,
            'created_by' => $this->created_by,
        ];

        if ($this->projectCost) {
            $this->projectCost->update($attributes);
        } else {
            $cost = $this->project->projectCosts()->create($attributes);
            $this->update(['project_cost_id' => $cost->id]);
        }
    }
}
