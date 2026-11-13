<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBudgetLine extends Model
{
    public const STATUSES = ['draft', 'approved'];

    protected $fillable = [
        'project_id', 'cost_head', 'description', 'budgeted_amount', 'status', 'created_by', 'sort_order',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Actual spend against this line's cost head — deliberately only
     * this project's own cost ledger (ProjectCost), not Expense/Bill:
     * see the project_costs migration's docblock for why those two
     * aren't broken down by cost head here.
     */
    public function actualAmount(): float
    {
        return (float) $this->project->projectCosts()->where('cost_head', $this->cost_head)->sum('amount');
    }
}
